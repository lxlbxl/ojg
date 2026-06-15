<?php
/**
 * track-event.php — Server-side event ingestion endpoint for the A/B engine.
 *
 * POST JSON:
 *   {
 *     "session_id":  "s_...",
 *     "funnel_name": "pcos",
 *     "event_type":  "results_view",
 *     "revenue":     0,
 *     "metadata":    { "...": "..." }
 *   }
 *
 * - CORS-friendly: allows POST from any OJG funnel origin
 * - GDPR-gated: when OJGConsent is present on the client, the client should
 *   not POST tracking data without consent. This endpoint does not enforce
 *   consent (it's session-id based) but does NOT log revenue-bearing events
 *   without a server-side session anchor.
 * - Always returns JSON.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

// Bootstrap
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}
require_once APP_ROOT . '/backend/config.php';
require_once APP_ROOT . '/backend/classes/Database.php';
require_once APP_ROOT . '/backend/classes/Bandit.php';
require_once APP_ROOT . '/backend/classes/ExperimentRepository.php';
require_once APP_ROOT . '/backend/classes/AssignmentService.php';
require_once APP_ROOT . '/backend/classes/ExperimentTracker.php';

// Read body
$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
if (empty($body) && !empty($_POST)) {
    $body = $_POST; // form-encoded fallback
}

$sessionId = $body['session_id'] ?? '';
$funnelName = $body['funnel_name'] ?? '';
$eventType = $body['event_type'] ?? '';
$revenue = isset($body['revenue']) ? (float) $body['revenue'] : 0.0;
$metadata = $body['metadata'] ?? null;

if (!$sessionId || !$funnelName || !$eventType) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields: session_id, funnel_name, event_type']);
    exit;
}

// Sanitise metadata: never trust client to set its own experiment_id
if (is_array($metadata)) {
    unset($metadata['experiment_id'], $metadata['variant_id']);
}

try {
    $db = Database::getInstance();
    if ($db->isFileStorage()) {
        // Tracking requires a real DB; quietly succeed to avoid client errors.
        echo json_encode(['ok' => true, 'warning' => 'file_storage_mode']);
        exit;
    }
    $pdo = $db->getConnection();
    $repo = new ExperimentRepository($pdo);
    $bandit = new Bandit($pdo);
    $tracker = new ExperimentTracker($pdo, $repo, $bandit);

    $result = $tracker->track([
        'session_id' => $sessionId,
        'funnel_name' => $funnelName,
        'event_type' => $eventType,
        'revenue' => $revenue,
        'metadata' => $metadata,
    ]);

    if (!$result['ok']) {
        http_response_code(422);
    }
    echo json_encode($result);
} catch (Throwable $e) {
    error_log('track-event error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server error']);
}
