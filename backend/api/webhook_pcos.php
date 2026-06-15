<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Settings.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../classes/AIOrchestrator.php';
require_once __DIR__ . '/../classes/MealPlanner.php';
require_once __DIR__ . '/../classes/AutomationOrchestrator.php';
require_once __DIR__ . '/../classes/ExperimentTracker.php';

// Allow CORS if needed
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON Input
$rawBody = file_get_contents('php://input') ?: '';
$input = json_decode($rawBody, true);

if (!$input) {
    // Fallback to POST vars
    $input = $_POST;
}

// 1. Verify the signature (if a secret hash is configured).
$expectedHash = Settings::getInstance()->get('flutterwave_webhook_secret', '')
    ?: Settings::getInstance()->get('webhook_secret', '');

if ($expectedHash !== '') {
    $sent = $_SERVER['HTTP_VERIF_HASH']
        ?? ($input['verif_hash'] ?? '');
    if (!hash_equals($expectedHash, (string) $sent)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid signature']);
        exit;
    }
}

// Validate Input
if (!isset($input['email']) || !isset($input['name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields (email, name)']);
    exit;
}

try {
    $orchestrator = new AutomationOrchestrator();

    // Prepare Customer Data
    $product = $input['product'] ?? 'PCOS Bundle';

    // Determine plan duration from product name or explicit field
    $planDuration = intval($input['plan_duration'] ?? 0);
    if ($planDuration === 0) {
        if (stripos($product, '90') !== false) {
            $planDuration = 90;
        } elseif (stripos($product, '30') !== false) {
            $planDuration = 30;
        } else {
            $amount = floatval($input['amount'] ?? 0);
            $planDuration = ($amount > 40000) ? 90 : 30;
        }
    }

    // Extract session_id from meta (Flutterwave stores it there) or top-level
    $sessionId = $input['meta']['session_id']
        ?? (is_array($input['meta'] ?? null) ? ($input['meta'][0]['session_id'] ?? null) : null)
        ?? $input['session_id']
        ?? $_COOKIE['ojg_sid']
        ?? null;

    $orderData = [
        'email' => $input['email'],
        'name' => $input['name'],
        'transaction_id' => $input['transaction_id'] ?? $input['order_id'] ?? null,
        'tx_ref' => $input['tx_ref'] ?? $input['reference'] ?? null,
        'amount' => $input['amount'] ?? 0,
        'currency' => $input['currency'] ?? 'NGN',
        'product' => $product,
        'plan_duration' => $planDuration,
        'session_id' => $sessionId,
    ];

    // Prepare Assessment Data
    $assessmentData = [
        'pcos_type' => $input['pcos_type'] ?? 'General',
        'cycle_length' => $input['cycle_length'] ?? 28,
        'last_period_date' => $input['last_period_date'] ?? date('Y-m-d'),
        'allergies' => $input['allergies'] ?? 'None'
    ];

    // Handle Purchase (Creates/Updates User + Logs Sale + Activity)
    $result = $orchestrator->handlePurchase($orderData, $assessmentData, 'pcos');

    // Emit server-side experiment purchase event (server-trusted revenue)
    if (!empty($sessionId) && !empty($result['success'])) {
        try {
            $tracker = new ExperimentTracker();
            $tracker->track([
                'session_id' => $sessionId,
                'funnel' => 'pcos',
                'event' => 'purchase',
                'revenue' => floatval($input['amount'] ?? 0),
                'currency' => $input['currency'] ?? 'NGN',
                'transaction_id' => $input['transaction_id'] ?? $input['order_id'] ?? null,
                'product' => $product,
                'plan_duration' => $planDuration,
                'source' => 'webhook',
            ]);
        } catch (Exception $e) {
            error_log("ExperimentTracker webhook error: " . $e->getMessage());
        }
    }

    echo json_encode($result);
} catch (\Throwable $e) {
    error_log("Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
