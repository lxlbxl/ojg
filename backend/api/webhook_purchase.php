<?php
/**
 * Generic Flutterwave webhook for all OJG funnels.
 * 
 * This is the unified purchase ingest endpoint. It:
 *   1. Verifies the Flutterwave signature (when secret hash is configured).
 *   2. Pulls the session_id from the transaction metadata (Flutterwave `meta`
 *      array on the charge). It accepts either a top-level `session_id` or a
 *      `meta.session_id` (string or array form).
 *   3. Persists a server-side `purchase` event to experiment_events +
 *      funnel_tracking with the actual revenue, attributed to the session's
 *      currently assigned experiment/variant.
 *   4. Hands the order off to AutomationOrchestrator->handlePurchase() which
 *      records the sale, creates the user, generates the plan, and sends the
 *      welcome email.
 *
 * Per-funnel webhook files (webhook_pcos.php, webhook_weight.php, etc.) are
 * retained as thin wrappers that delegate here, so existing n8n flows keep
 * working untouched.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ExperimentRepository.php';
require_once __DIR__ . '/../classes/ExperimentTracker.php';
require_once __DIR__ . '/../classes/AssignmentService.php';
require_once __DIR__ . '/../classes/AutomationOrchestrator.php';

// ---------------------------------------------------------------------------
// 1. Read the raw payload.
// Flutterwave posts application/json. We honour that, but also accept form
// bodies for backward compatibility with legacy webhooks.
// ---------------------------------------------------------------------------
$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    // Fall back to $_POST for form-encoded deliveries
    $payload = $_POST;
}

if (!is_array($payload) || empty($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty or invalid payload']);
    exit;
}

// ---------------------------------------------------------------------------
// 2. Verify the signature (if a secret hash is configured).
// Flutterwave sends a "verif-hash" header. We accept either header or body
// field form to be tolerant of how the gateway forwards the call.
// ---------------------------------------------------------------------------
$expectedHash = Settings::getInstance()->get('flutterwave_webhook_secret', '')
    ?: Settings::getInstance()->get('webhook_secret', '');
if ($expectedHash !== '') {
    $sent = $_SERVER['HTTP_VERIF_HASH']
        ?? ($payload['verif_hash'] ?? '');
    if (!hash_equals($expectedHash, (string) $sent)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
        exit;
    }
}

// ---------------------------------------------------------------------------
// 3. Extract the canonical fields.
// ---------------------------------------------------------------------------
$eventType = (string) ($payload['event'] ?? '');
$status = (string) ($payload['data']['status'] ?? $payload['status'] ?? '');

$funnel = strtolower((string) (
    $payload['data']['meta']['funnel']
    ?? $payload['data']['meta'][0]['funnel']
    ?? $payload['meta']['funnel']
    ?? $payload['meta'][0]['funnel']
    ?? 'pcos'
));
$allowedFunnels = ['pcos', 'acne', 'weight', 'mens', 'egbon'];
if (!in_array($funnel, $allowedFunnels, true)) {
    $funnel = 'pcos';
}

$sessionId = (string) (
    $payload['data']['meta']['session_id']
    ?? $payload['data']['meta'][0]['session_id']
    ?? $payload['meta']['session_id']
    ?? $payload['meta'][0]['session_id']
    ?? $payload['session_id']
    ?? ''
);

$txRef = (string) (
    $payload['data']['tx_ref']
    ?? $payload['tx_ref']
    ?? ''
);

$revenue = (float) (
    $payload['data']['amount']
    ?? $payload['data']['charged_amount']
    ?? $payload['amount']
    ?? 0
);
$currency = (string) (
    $payload['data']['currency']
    ?? $payload['currency']
    ?? 'USD'
);

// Only act on successful charges.
$isSuccess = ($eventType === 'charge.completed' && $status === 'successful')
    || ($eventType === '' && $status === 'successful');

if (!$isSuccess) {
    http_response_code(200);
    echo json_encode([
        'status' => 'ignored',
        'reason' => 'event_not_successful',
        'event' => $eventType,
        'state' => $status,
    ]);
    exit;
}

// ---------------------------------------------------------------------------
// 4. Record server-side purchase event for A/B engine attribution.
// ---------------------------------------------------------------------------
try {
    if ($sessionId !== '') {
        $tracker = new ExperimentTracker();
        $tracker->track([
            'event_type' => 'purchase',
            'funnel' => $funnel,
            'session_id' => $sessionId,
            'revenue' => $revenue,
            'currency' => $currency,
            'tx_ref' => $txRef,
            'metadata' => [
                'source' => 'flutterwave_webhook',
                'event' => $eventType,
            ],
        ]);
    }
} catch (Throwable $e) {
    // Never let analytics failures block the actual fulfilment.
    error_log('[webhook_purchase] tracker error: ' . $e->getMessage());
}

// ---------------------------------------------------------------------------
// 5. Hand off to the orchestrator for order fulfilment.
// ---------------------------------------------------------------------------
$orderData = [
    'email' => $payload['data']['customer']['email'] ?? $payload['customer']['email'] ?? $payload['email'] ?? '',
    'name' => $payload['data']['customer']['name'] ?? $payload['customer']['name'] ?? $payload['name'] ?? '',
    'transaction_id' => $payload['data']['id'] ?? $payload['id'] ?? null,
    'tx_ref' => $txRef,
    'amount' => $revenue,
    'currency' => $currency,
    'product' => $payload['data']['meta']['product'] ?? $payload['meta']['product'] ?? $payload['product'] ?? ($funnel . ' Plan'),
    'plan_duration' => $payload['data']['meta']['plan_duration'] ?? $payload['meta']['plan_duration'] ?? $payload['plan_duration'] ?? 0,
    'session_id' => $sessionId,
    'funnel' => $funnel,
    'raw' => $payload['data'] ?? $payload,
];

$assessmentData = $payload['data']['meta']['assessment']
    ?? $payload['meta']['assessment']
    ?? null;

try {
    $orchestrator = new AutomationOrchestrator();
    $result = $orchestrator->handlePurchase($orderData, $assessmentData, $funnel);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Orchestrator failure',
        'detail' => $e->getMessage(),
    ]);
    exit;
}

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'funnel' => $funnel,
    'session' => $sessionId,
    'result' => $result,
]);
