<?php
/**
 * ExperimentRepository — CRUD layer for the OJG A/B engine.
 *
 * Thin wrapper over the new tables (experiments, variants, assignments,
 * variant_metrics_daily, ai_insights, assignment_failures, cron_runs,
 * experiment_events).
 *
 * Deliberately does no math — math lives in Bandit, AI prompts live in
 * AIOrchestrator. This class is just typed access to the new tables so
 * the rest of the codebase doesn't have to know the SQL.
 */
class ExperimentRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ---------- Experiments ---------- */

    public function listExperiments($funnel = null, $status = null)
    {
        $sql = "SELECT * FROM experiments WHERE 1=1";
        $params = [];
        if ($funnel) {
            $sql .= " AND funnel_name = :f";
            $params[':f'] = $funnel;
        }
        if ($status) {
            $sql .= " AND status = :s";
            $params[':s'] = $status;
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExperiment($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM experiments WHERE id = :id");
        $stmt->execute([':id' => (int) $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findActiveExperimentForFunnel($funnel, $stage = null)
    {
        $sql = "SELECT * FROM experiments
                WHERE funnel_name = :f
                  AND status IN ('burn_in','active')";
        $params = [':f' => $funnel];
        if ($stage) {
            $sql .= " AND stage = :st";
            $params[':st'] = $stage;
        }
        $sql .= " ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createExperiment(array $data)
    {
        $allowed = [
            'funnel_name',
            'name',
            'hypothesis',
            'stage',
            'primary_metric',
            'reward_type',
            'status',
            'burn_in_hours',
            'min_exposure_floor',
            'min_samples_per_variant',
            'decision_p_best',
            'decision_expected_loss',
            'started_at'
        ];
        $cols = array_intersect_key($data, array_flip($allowed));
        if (empty($cols['funnel_name']) || empty($cols['name']) || empty($cols['stage']) || empty($cols['primary_metric'])) {
            throw new InvalidArgumentException('funnel_name, name, stage and primary_metric are required');
        }
        $cols['status'] = $cols['status'] ?? 'draft';
        $colList = array_keys($cols);
        $placeholders = array_map(fn($c) => ':' . $c, $colList);
        $sql = "INSERT INTO experiments (" . implode(',', $colList) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_combine($placeholders, array_values($cols)));
        return (int) $this->pdo->lastInsertId();
    }

    public function updateExperimentStatus($id, $status, $winnerVariantId = null)
    {
        $sql = "UPDATE experiments SET status = :s";
        $params = [':s' => $status, ':id' => (int) $id];
        if ($status === 'active' && !$this->hasStartedAt($id)) {
            $sql .= ", started_at = NOW()";
        }
        if ($status === 'concluded') {
            $sql .= ", concluded_at = NOW()";
            if ($winnerVariantId) {
                $sql .= ", winner_variant_id = :wv";
                $params[':wv'] = (int) $winnerVariantId;
            }
        }
        $sql .= " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    private function hasStartedAt($id)
    {
        $stmt = $this->pdo->prepare("SELECT started_at FROM experiments WHERE id = :id");
        $stmt->execute([':id' => (int) $id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($r['started_at']);
    }

    /* ---------- Variants ---------- */

    public function listVariants($experimentId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM variants WHERE experiment_id = :id ORDER BY id ASC");
        $stmt->execute([':id' => (int) $experimentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVariant($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM variants WHERE id = :id");
        $stmt->execute([':id' => (int) $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addVariant(array $data)
    {
        $allowed = [
            'experiment_id',
            'name',
            'type',
            'directory',
            'overrides',
            'alpha',
            'beta',
            'status',
            'source',
            'ai_rationale',
            'is_essential'
        ];
        $cols = array_intersect_key($data, array_flip($allowed));
        $colList = array_keys($cols);
        $placeholders = array_map(fn($c) => ':' . $c, $colList);
        $sql = "INSERT INTO variants (" . implode(',', $colList) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_combine($placeholders, array_values($cols)));
        return (int) $this->pdo->lastInsertId();
    }

    public function setVariantStatus($id, $status)
    {
        $stmt = $this->pdo->prepare("UPDATE variants SET status = :s WHERE id = :id");
        $stmt->execute([':s' => $status, ':id' => (int) $id]);
        return $stmt->rowCount();
    }

    /* ---------- Assignments ---------- */

    public function getAssignment($sessionId, $experimentId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM assignments
             WHERE session_id = :s AND experiment_id = :e
             LIMIT 1"
        );
        $stmt->execute([':s' => $sessionId, ':e' => (int) $experimentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createAssignment($sessionId, $experimentId, $variantId)
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO assignments (session_id, experiment_id, variant_id)
             VALUES (:s, :e, :v)
             ON DUPLICATE KEY UPDATE variant_id = variant_id"  // MySQL no-op
        );
        try {
            $stmt->execute([
                ':s' => $sessionId,
                ':e' => (int) $experimentId,
                ':v' => (int) $variantId,
            ]);
        } catch (PDOException $ex) {
            // SQLite: INSERT OR IGNORE
            $stmt = $this->pdo->prepare(
                "INSERT OR IGNORE INTO assignments (session_id, experiment_id, variant_id)
                 VALUES (:s, :e, :v)"
            );
            $stmt->execute([
                ':s' => $sessionId,
                ':e' => (int) $experimentId,
                ':v' => (int) $variantId,
            ]);
        }
        return (int) $this->pdo->lastInsertId();
    }

    /* ---------- Events ---------- */

    /**
     * Append a server-side event to experiment_events. This is the
     * authoritative log; funnel_tracking is kept in sync too.
     */
    public function recordEvent(array $payload)
    {
        $allowed = [
            'session_id',
            'experiment_id',
            'variant_id',
            'funnel_name',
            'event_type',
            'revenue',
            'metadata',
            'user_agent',
            'ip_address'
        ];
        $row = array_intersect_key($payload, array_flip($allowed));
        if (empty($row['session_id']) || empty($row['funnel_name']) || empty($row['event_type'])) {
            throw new InvalidArgumentException('session_id, funnel_name and event_type are required');
        }
        $colList = array_keys($row);
        $placeholders = array_map(fn($c) => ':' . $c, $colList);
        $sql = "INSERT INTO experiment_events (" . implode(',', $colList) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_combine($placeholders, array_values($row)));
        return (int) $this->pdo->lastInsertId();
    }

    /* ---------- AI insights ---------- */

    public function saveInsight($experimentId, $funnelName, $type, array $content)
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO ai_insights (experiment_id, funnel_name, insight_type, content)
             VALUES (:e, :f, :t, :c)"
        );
        $stmt->execute([
            ':e' => $experimentId,
            ':f' => $funnelName,
            ':t' => $type,
            ':c' => json_encode($content),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function listInsights($experimentId = null, $funnel = null, $limit = 20)
    {
        $sql = "SELECT * FROM ai_insights WHERE 1=1";
        $params = [];
        if ($experimentId) {
            $sql .= " AND experiment_id = :e";
            $params[':e'] = (int) $experimentId;
        }
        if ($funnel) {
            $sql .= " AND funnel_name = :f";
            $params[':f'] = $funnel;
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ---------- Assignment failure log ---------- */

    public function logAssignmentFailure($sessionId, $experimentId, $error)
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO assignment_failures (session_id, experiment_id, error)
                 VALUES (:s, :e, :err)"
            );
            $stmt->execute([
                ':s' => $sessionId,
                ':e' => $experimentId,
                ':err' => substr((string) $error, 0, 1000),
            ]);
        } catch (Exception $e) { /* swallow */
        }
    }

    /* ---------- Cron run gate ---------- */

    /**
     * Returns true if the caller may proceed (no active run for $scriptName).
     * Atomic: inserts a "running" row in the same statement.
     */
    public function cronStart($scriptName)
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = ($driver === 'mysql')
            ? "INSERT INTO cron_runs (script_name, started_at, status)
               VALUES (:s, NOW(), 'running')
               ON DUPLICATE KEY UPDATE id = id"   // never updates; just consumes the unique key
            : "INSERT OR IGNORE INTO cron_runs (script_name, started_at, status)
               VALUES (:s, datetime('now'), 'running')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':s' => $scriptName]);

        // Was this the row we just inserted? If id was inserted, lastInsertId is non-zero.
        $newId = (int) $this->pdo->lastInsertId();
        if ($newId > 0)
            return true;

        // Existing row: is it still running? If so, skip.
        $stmt = $this->pdo->prepare(
            "SELECT * FROM cron_runs
             WHERE script_name = :s AND status = 'running'
             ORDER BY started_at DESC LIMIT 1"
        );
        $stmt->execute([':s' => $scriptName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // Stale lock? (>1 hour)
            $staleThreshold = date('Y-m-d H:i:s', time() - 3600);
            if (($row['started_at'] ?? '') < $staleThreshold) {
                $stmt = $this->pdo->prepare(
                    "UPDATE cron_runs SET status='failed', finished_at=NOW()
                     WHERE id = :id"
                );
                $stmt->execute([':id' => $row['id']]);
                return $this->cronStart($scriptName); // retry once
            }
            return false;
        }
        return true;
    }

    public function cronFinish($scriptName, $rowsAffected = 0, $notes = null, $success = true)
    {
        $stmt = $this->pdo->prepare(
            "UPDATE cron_runs
             SET status = :st, finished_at = NOW(), rows_affected = :r, notes = :n
             WHERE script_name = :s AND status = 'running'
             ORDER BY started_at DESC LIMIT 1"
        );
        $stmt->execute([
            ':st' => $success ? 'completed' : 'failed',
            ':r' => (int) $rowsAffected,
            ':n' => $notes,
            ':s' => $scriptName,
        ]);
    }

    /* ---------- Aggregates / admin queries ---------- */

    public function summaryForExperiment($experimentId)
    {
        $sql = "SELECT
                  v.id, v.name, v.type, v.directory, v.alpha, v.beta,
                  v.exposures, v.conversions, v.revenue_total, v.status,
                  (v.alpha / (v.alpha + v.beta)) AS posterior_mean,
                  (v.alpha + v.beta) AS posterior_n
                FROM variants v
                WHERE v.experiment_id = :e
                ORDER BY v.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':e' => (int) $experimentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recentEvents($experimentId = null, $limit = 100)
    {
        $sql = "SELECT * FROM experiment_events WHERE 1=1";
        $params = [];
        if ($experimentId) {
            $sql .= " AND experiment_id = :e";
            $params[':e'] = (int) $experimentId;
        }
        $sql .= " ORDER BY id DESC LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
