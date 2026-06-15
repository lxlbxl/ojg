<?php
/**
 * Bandit — Thompson Sampling multi-armed bandit for OJG A/B Engine.
 *
 * Reward models:
 *  - 'binary':    Beta(alpha, beta) posterior; per-arm update on every conversion.
 *  - 'revenue':   Beta × empirical AOV (matches spec §7 "revenue reward").
 *
 * Sampling primitives (Marsaglia & Tsang gamma, Box-Muller normal) are
 * included so the class is self-contained and doesn't depend on ext-random.
 *
 * Design:
 *  - All math is deterministic and unit-testable.
 *  - DB writes go through the injected PDO (no Database coupling).
 *  - Draws are persisted only as a 'suggestion' — the source of truth for
 *    the chosen arm is the assignments table.
 */
class Bandit
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* -----------------------------------------------------------------
     *  Sampling primitives
     * ----------------------------------------------------------------- */

    /**
     * Marsaglia & Tsang (2000) gamma sampler.
     * Shape k > 0, scale theta = 1.
     */
    public static function sampleGamma($k)
    {
        if ($k <= 0)
            return 0.0;
        if ($k < 1) {
            // Boost via Ahrens-Diamond for shape < 1
            $u = self::sampleUniform();
            return self::sampleGamma(1 + $k) * pow($u, 1 / $k);
        }
        $d = $k - 1 / 3;
        $c = 1 / sqrt(9 * $d);
        while (true) {
            do {
                $x = self::sampleNormal();
                $v = 1 + $c * $x;
            } while ($v <= 0);
            $v = $v * $v * $v;
            $u = self::sampleUniform();
            if ($u < 1 - 0.0331 * ($x * $x) * ($x * $x)) {
                return $d * $v;
            }
            if (log($u) < 0.5 * $x * $x + $d * (1 - $v + log($v))) {
                return $d * $v;
            }
        }
    }

    /** Box-Muller standard normal */
    public static function sampleNormal()
    {
        static $spare = null;
        if ($spare !== null) {
            $v = $spare;
            $spare = null;
            return $v;
        }
        do {
            $u1 = self::sampleUniform();
        } while ($u1 <= 1e-12);
        $u2 = self::sampleUniform();
        $mag = sqrt(-2 * log($u1));
        $spare = $mag * sin(2 * M_PI * $u2);
        return $mag * cos(2 * M_PI * $u2);
    }

    public static function sampleUniform()
    {
        return mt_rand() / mt_getrandmax();
    }

    /** Draw from a Beta(alpha, beta) posterior */
    public static function sampleBeta($alpha, $beta)
    {
        $x = self::sampleGamma((float) $alpha);
        $sum = $x + self::sampleGamma((float) $beta);
        if ($sum <= 0)
            return 0.5;
        return $x / $sum;
    }

    /* -----------------------------------------------------------------
     *  Arm selection — Thompson Sampling
     * ----------------------------------------------------------------- */

    /**
     * Given a list of variants (rows from the `variants` table) and the
     * experiment's reward_type, return the chosen variant.
     *
     * @param array $variants  Each: ['id','alpha','beta','exposures','conversions','revenue_total']
     * @param string $rewardType  'binary' | 'revenue'
     * @param int    $minSamplesPerVariant  Burn-in floor; before this, uniform random.
     * @return array The chosen variant row.
     */
    public function selectArm(array $variants, $rewardType = 'binary', $minSamplesPerVariant = 1000)
    {
        if (count($variants) === 1) {
            return $variants[0];
        }

        // Burn-in: any variant still under the floor gets uniform sampling
        $inBurnIn = false;
        foreach ($variants as $v) {
            if ((int) $v['exposures'] < (int) $minSamplesPerVariant) {
                $inBurnIn = true;
                break;
            }
        }
        if ($inBurnIn) {
            return $variants[array_rand($variants)];
        }

        $draws = [];
        foreach ($variants as $v) {
            $alpha = max(0.0001, (float) $v['alpha']);
            $beta = max(0.0001, (float) $v['beta']);
            $p = self::sampleBeta($alpha, $beta);

            if ($rewardType === 'revenue') {
                $aov = ((int) $v['conversions'] > 0)
                    ? ((float) $v['revenue_total'] / (int) $v['conversions'])
                    : 0.0;
                $draws[$v['id']] = $p * $aov;
            } else {
                $draws[$v['id']] = $p;
            }
        }
        arsort($draws);
        $winnerId = array_key_first($draws);
        foreach ($variants as $v) {
            if ((int) $v['id'] === (int) $winnerId) {
                return $v;
            }
        }
        return $variants[0];
    }

    /* -----------------------------------------------------------------
     *  Posterior updates
     * ----------------------------------------------------------------- */

    /**
     * Update arm posterior on a conversion (binary or revenue event).
     * Standard Beta-Bernoulli: alpha += 1 on success.
     */
    public function recordConversion($variantId, $revenue = 0.0)
    {
        $sql = "UPDATE variants
                SET alpha = alpha + 1,
                    conversions = conversions + 1,
                    revenue_total = revenue_total + :rev
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':rev' => (float) $revenue, ':id' => (int) $variantId]);
    }

    /** Update arm posterior on a non-conversion (exposure without conversion). */
    public function recordExposure($variantId)
    {
        $sql = "UPDATE variants
                SET beta = beta + 1, exposures = exposures + 1
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => (int) $variantId]);
    }

    /* -----------------------------------------------------------------
     *  Decision logic — decide when an experiment has a winner
     * ----------------------------------------------------------------- */

    /**
     * Returns either a winner variant row, or null if no decision is yet safe.
     *
     * Decision rule (per spec §7):
     *  - p(arm = best) >= decision_p_best   (default 0.95)
     *  - expected loss vs best < decision_expected_loss (default 0.5%)
     *  - min_samples_per_variant satisfied
     *  - exposure floor satisfied (min_exposure_floor)
     *
     * @param array $experiment  Row from `experiments`
     * @param array $variants    Rows from `variants` for that experiment
     * @return array|null ['variant' => $row, 'p_best' => float, 'expected_loss' => float]
     */
    public function decide(array $experiment, array $variants)
    {
        if (count($variants) < 2)
            return null;
        $rewardType = $experiment['reward_type'] ?? 'binary';
        $pBest = (float) ($experiment['decision_p_best'] ?? 0.95);
        $maxLoss = (float) ($experiment['decision_expected_loss'] ?? 0.005);
        $minSamples = (int) ($experiment['min_samples_per_variant'] ?? 1000);
        $minFloor = (float) ($experiment['min_exposure_floor'] ?? 0.10);

        // Monte Carlo: probability each arm is the best
        $mc = 4000;
        $winCounts = array_fill_keys(array_column($variants, 'id'), 0);
        $lossSum = array_fill_keys(array_column($variants, 'id'), 0.0);

        for ($i = 0; $i < $mc; $i++) {
            $draws = [];
            foreach ($variants as $v) {
                $alpha = max(0.0001, (float) $v['alpha']);
                $beta = max(0.0001, (float) $v['beta']);
                $p = self::sampleBeta($alpha, $beta);
                $val = ($rewardType === 'revenue')
                    ? $p * (((int) $v['conversions'] > 0) ? ((float) $v['revenue_total'] / (int) $v['conversions']) : 0.0)
                    : $p;
                $draws[$v['id']] = $val;
            }
            arsort($draws);
            $bestId = array_key_first($draws);
            $bestVal = $draws[$bestId];
            $winCounts[$bestId]++;

            foreach ($draws as $id => $val) {
                $lossSum[$id] += max(0, $bestVal - $val) / $mc;
            }
        }

        // Exposure floor: drop arms with too little data
        $totalExposures = 0;
        foreach ($variants as $v)
            $totalExposures += (int) $v['exposures'];
        $eligible = [];
        foreach ($variants as $v) {
            $share = ($totalExposures > 0) ? ((int) $v['exposures'] / $totalExposures) : 0;
            if ($share >= $minFloor)
                $eligible[$v['id']] = $v;
        }
        if (count($eligible) < 2)
            return null;

        // Min samples per variant
        foreach ($eligible as $v) {
            if ((int) $v['exposures'] < $minSamples)
                return null;
        }

        // Best arm = the one with highest p(win)
        $pWin = [];
        foreach ($winCounts as $id => $c)
            $pWin[$id] = $c / $mc;
        arsort($pWin);
        $bestId = array_key_first($pWin);
        $bestP = $pWin[$bestId];
        $bestLoss = $lossSum[$bestId] / $mc;

        // Normalise expected loss to a relative fraction of best value
        $bestVal = 0;
        foreach ($variants as $v) {
            if ((int) $v['id'] === (int) $bestId) {
                $bestVal = ($rewardType === 'revenue')
                    ? ((float) $v['revenue_total'] / max(1, (int) $v['conversions']))
                    : (max(0.0001, (float) $v['alpha'] / ((float) $v['alpha'] + (float) $v['beta'])));
            }
        }
        $relLoss = ($bestVal > 0) ? ($bestLoss / $bestVal) : 0;

        if ($bestP >= $pBest && $relLoss <= $maxLoss) {
            foreach ($variants as $v) {
                if ((int) $v['id'] === (int) $bestId) {
                    return [
                        'variant' => $v,
                        'p_best' => $bestP,
                        'expected_loss' => $relLoss,
                    ];
                }
            }
        }
        return null;
    }

    /* -----------------------------------------------------------------
     *  Post-rollup
     * ----------------------------------------------------------------- */

    /**
     * Roll the per-day funnel_tracking counters into variant_metrics_daily.
     * Idempotent per (variant_id, date).
     */
    public function rollupDaily($metricDate = null)
    {
        $date = $metricDate ?: date('Y-m-d');
        $sql = "
            INSERT INTO variant_metrics_daily
                (variant_id, metric_date, exposures, assessment_starts, assessment_completes,
                 results_views, plan_selects, checkout_inits, purchases, revenue)
            SELECT
                ft.variant_id,
                DATE(ft.created_at) AS d,
                SUM(CASE WHEN ft.event_type = 'view'              THEN 1 ELSE 0 END),
                SUM(CASE WHEN ft.event_type = 'assessment_start'  THEN 1 ELSE 0 END),
                SUM(CASE WHEN ft.event_type = 'assessment_complete' THEN 1 ELSE 0 END),
                SUM(CASE WHEN ft.event_type = 'results_view'     THEN 1 ELSE 0 END),
                SUM(CASE WHEN ft.event_type = 'plan_select'      THEN 1 ELSE 0 END),
                SUM(CASE WHEN ft.event_type = 'checkout_init'    THEN 1 ELSE 0 END),
                SUM(CASE WHEN ft.event_type = 'purchase'         THEN 1 ELSE 0 END),
                COALESCE(SUM(ft.revenue), 0)
            FROM funnel_tracking ft
            WHERE ft.variant_id IS NOT NULL AND DATE(ft.created_at) = :d
            GROUP BY ft.variant_id, DATE(ft.created_at)
            ON DUPLICATE KEY UPDATE
                exposures = VALUES(exposures),
                assessment_starts = VALUES(assessment_starts),
                assessment_completes = VALUES(assessment_completes),
                results_views = VALUES(results_views),
                plan_selects = VALUES(plan_selects),
                checkout_inits = VALUES(checkout_inits),
                purchases = VALUES(purchases),
                revenue = VALUES(revenue)
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':d' => $date]);
        return $stmt->rowCount();
    }
}
