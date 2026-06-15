<?php
/**
 * AssignmentService — sticky variant assignment via ojg_exp cookie.
 *
 * Flow:
 *   1. Caller (router.php) asks: "what variant of experiment $expId
 *      should session $sessionId see?"
 *   2. We look up the assignments table (the source of truth).
 *   3. If none, we ask Bandit to select an arm and persist it.
 *   4. We return the chosen variant row (with overrides/directory).
 *
 * The cookie is a JSON map {experiment_id: variant_id}. We also mirror
 * to the `assignments` table so that cookie loss (incognito, different
 * device, 90-day expiry) doesn't break replay.
 */
class AssignmentService
{
    /** @var PDO */
    private $pdo;
    /** @var ExperimentRepository */
    private $repo;
    /** @var Bandit */
    private $bandit;

    public function __construct(PDO $pdo, ExperimentRepository $repo, Bandit $bandit)
    {
        $this->pdo = $pdo;
        $this->repo = $repo;
        $this->bandit = $bandit;
    }

    /**
     * Get or create a sticky assignment for ($sessionId, $experimentId).
     * Returns the full variant row.
     */
    public function getOrAssign($sessionId, $experimentId)
    {
        // 1. Sticky replay
        $existing = $this->repo->getAssignment($sessionId, $experimentId);
        if ($existing) {
            $variant = $this->repo->getVariant($existing['variant_id']);
            if ($variant)
                return $variant;
        }

        // 2. Fresh assignment
        $experiment = $this->repo->getExperiment($experimentId);
        if (!$experiment) {
            throw new RuntimeException("Experiment $experimentId not found");
        }
        if (in_array($experiment['status'], ['concluded', 'archived', 'draft'], true)) {
            // Treat as no experiment; return first variant without persisting
            $variants = $this->repo->listVariants($experimentId);
            return $variants[0] ?? null;
        }

        $variants = $this->repo->listVariants($experimentId);
        if (empty($variants)) {
            throw new RuntimeException("No variants defined for experiment $experimentId");
        }

        try {
            $chosen = $this->bandit->selectArm(
                $variants,
                $experiment['reward_type'] ?? 'binary',
                (int) ($experiment['min_samples_per_variant'] ?? 1000)
            );
        } catch (Throwable $e) {
            $this->repo->logAssignmentFailure($sessionId, $experimentId, $e->getMessage());
            $chosen = $variants[0]; // safe fallback
        }

        $this->repo->createAssignment($sessionId, $experimentId, $chosen['id']);
        return $chosen;
    }

    /**
     * Bulk version: for a list of experiments, return [expId => variant].
     * Used by router.php to do the full decision in one request.
     */
    public function getAllForSession($sessionId, array $experiments)
    {
        $out = [];
        foreach ($experiments as $exp) {
            try {
                $variant = $this->getOrAssign($sessionId, (int) $exp['id']);
                if ($variant) {
                    $out[(int) $exp['id']] = $variant;
                }
            } catch (Throwable $e) {
                $this->repo->logAssignmentFailure($sessionId, (int) $exp['id'], $e->getMessage());
            }
        }
        return $out;
    }

    /**
     * Generate a fresh session id (used on first hit when ojg_sid is missing).
     */
    public static function newSessionId()
    {
        return 's_' . bin2hex(random_bytes(12));
    }

    /**
     * Encode the ojg_exp cookie value from a map of expId => variantId.
     */
    public static function encodeCookieMap(array $map)
    {
        return json_encode($map, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Decode the ojg_exp cookie value. Returns [] on parse error.
     */
    public static function decodeCookieMap($raw)
    {
        if (!$raw)
            return [];
        $out = json_decode($raw, true);
        return is_array($out) ? $out : [];
    }
}
