<?php
/**
 * Recompute Thompson Sampling posteriors + roll up daily metrics.
 *
 * Cron schedule: every 15 minutes.
 * Job:
 *   1. Pull all currently running experiments.
 *   2. Recompute aggregate counts per variant from experiment_events.
 *   3. Persist the rollup into variant_metrics_daily.
 *   4. Make sure the bandit can read these the next time it is asked to
 *      decide.
 *
 * Skip-if-active gate is provided by ExperimentRepository::cronStart().
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ExperimentRepository.php';
require_once __DIR__ . '/../classes/Bandit.php';

$script = basename(__FILE__);
$repo = new ExperimentRepository();

// Skip if another instance is running, or if the previous run was within
// the last 5 minutes (cheap debounce).
$lock = $repo->cronStart($script, 300);
if (!$lock['acquired']) {
    fwrite(STDOUT, "[$script] skip — " . ($lock['reason'] ?? 'busy') . PHP_EOL);
    exit(0);
}

$today = date('Y-m-d');
$rowsProcessed = 0;
$notes = [];

try {
    $bandit = new Bandit();
    $experiments = $repo->listExperiments(null, 'running');

    foreach ($experiments as $exp) {
        $variants = $repo->listVariants((int) $exp['id']);
        if (empty($variants))
            continue;

        foreach ($variants as $v) {
            // Use today as the rollup key. The bandit still reads live
            // counts; this table is for the admin dashboard trends.
            $bandit->rollupDaily((int) $exp['id'], (int) $v['id'], $today);
            $rowsProcessed++;
        }

        // Decide a winner if we have enough data and no explicit winner set.
        if (empty($exp['winner_variant_id'])) {
            $decision = $bandit->decide($exp, $variants);
            if (
                !empty($decision['winner_variant_id'])
                && ($decision['confidence'] ?? 0) >= 0.95
            ) {
                $repo->updateExperimentStatus(
                    (int) $exp['id'],
                    'completed',
                    (int) $decision['winner_variant_id']
                );
                $notes[] = "exp#{$exp['id']} ({$exp['name']}) -> winner v#{$decision['winner_variant_id']} @ " . round(($decision['confidence'] ?? 0) * 100, 1) . '%';
            }
        }
    }
} catch (Throwable $e) {
    $repo->cronFinish($script, $rowsProcessed, $notes, false, $e->getMessage());
    fwrite(STDERR, "[$script] fatal: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

$repo->cronFinish($script, $rowsProcessed, $notes, true);
fwrite(STDOUT, "[$script] ok — rolled up $rowsProcessed variant rows" . PHP_EOL);
