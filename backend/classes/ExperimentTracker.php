<?php
/**
 * ExperimentTracker — server-side event ingestion.
 *
 * Used by:
 *  - backend/api/track-event.php  (client ping)
 *  - backend/api/webhook_purchase.php  (purchase moment)
 *  - backend/classes/AutomationOrchestrator  (PCOS purchase path)
 *
 * Validates event taxonomy, looks up the active assignment for the
 * session, increments arm posteriors, and writes to:
 *   - experiment_events (authoritative log)
 *   - funnel_tracking  (legacy compatibility — extends with exp/variant/rev)
 */
class ExperimentTracker
{
    /** Spec §5 event taxonomy. */
    public const ALLOWED_EVENTS = [
        'view',
        'assessment_start',
        'assessment_complete',
        'results_view',
        'plan_select',
        'checkout_init',
        'purchase',
    ];

    /** Spec: allowed funnels. */
    public const ALLOWED_FUNNELS = [
        'pcos',
        'acne',
        'weight',
        'mens',
        'egbon',
    ];

    /** Events that count as conversions in the binary posterior. */
    public const CONVERSION_EVENTS = ['purchase'];

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
     * Record a server-side event.
     *
     * @param array $payload {
     *   @type string $session_id
     *   @type string $funnel_name
     *   @type string $event_type
     *   @type int    $experiment_id  (optional, looked up by funnel+stage if missing)
     *   @type int    $variant_id     (optional, looked up by session+experiment if missing)
     *   @type float  $revenue
     *   @type array  $metadata
     *   @type string $user_agent
     *   @type string $ip_address
     * }
     * @return array{ok:bool, id?:int, error?:string}
     */
    public function track(array $payload)
    {
        $sessionId = $payload['session_id'] ?? null;
        $funnelName = $payload['funnel_name'] ?? null;
        $eventType = $payload['event_type'] ?? null;
        $revenue = (float) ($payload['revenue'] ?? 0);
        $metadata = $payload['metadata'] ?? null;
        $userAgent = $payload['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ip = $payload['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');

        if (!$sessionId || !$funnelName || !$eventType) {
            return ['ok' => false, 'error' => 'session_id, funnel_name and event_type are required'];
        }
        if (!in_array($eventType, self::ALLOWED_EVENTS, true)) {
            return ['ok' => false, 'error' => "event_type '$eventType' not in taxonomy"];
        }
        if (!in_array($funnelName, self::ALLOWED_FUNNELS, true)) {
            return ['ok' => false, 'error' => "funnel_name '$funnelName' not allowed"];
        }

        // Resolve experiment + variant if not supplied
        $experimentId = (int) ($payload['experiment_id'] ?? 0);
        $variantId = (int) ($payload['variant_id'] ?? 0);

        if ($experimentId === 0) {
            $exp = $this->repo->findActiveExperimentForFunnel($funnelName);
            $experimentId = $exp ? (int) $exp['id'] : 0;
        }
        if ($experimentId > 0 && $variantId === 0) {
            $a = $this->repo->getAssignment($sessionId, $experimentId);
            if ($a)
                $variantId = (int) $a['variant_id'];
        }

        $isConversion = in_array($eventType, self::CONVERSION_EVENTS, true);

        // 1. experiment_events
        $eventId = $this->repo->recordEvent([
            'session_id' => $sessionId,
            'experiment_id' => $experimentId ?: null,
            'variant_id' => $variantId ?: null,
            'funnel_name' => $funnelName,
            'event_type' => $eventType,
            'revenue' => $revenue,
            'metadata' => is_array($metadata) ? json_encode($metadata) : (string) $metadata,
            'user_agent' => substr($userAgent, 0, 500),
            'ip_address' => $ip,
        ]);

        // 2. funnel_tracking — extend with experiment context if columns exist
        try {
            $sql = "INSERT INTO funnel_tracking
                    (session_id, funnel_name, step_name, event_type, metadata, url, ip_address, user_agent, experiment_id, variant_id, revenue)
                    VALUES (:s, :f, :st, :et, :m, :u, :ip, :ua, :e, :v, :r)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':s' => $sessionId,
                ':f' => $funnelName,
                ':st' => $eventType,
                ':et' => $eventType,
                ':m' => is_array($metadata) ? json_encode($metadata) : (string) $metadata,
                ':u' => $_SERVER['HTTP_REFERER'] ?? '',
                ':ip' => $ip,
                ':ua' => substr($userAgent, 0, 500),
                ':e' => $experimentId ?: null,
                ':v' => $variantId ?: null,
                ':r' => $revenue,
            ]);
        } catch (Throwable $e) {
            // funnel_tracking may not be available in file-storage mode; ignore.
        }

        // 3. Update arm posterior
        if ($variantId > 0) {
            if ($isConversion) {
                $this->bandit->recordConversion($variantId, $revenue);
            } else {
                $this->bandit->recordExposure($variantId);
            }
        }

        return ['ok' => true, 'id' => $eventId];
    }
}
