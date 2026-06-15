<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once '../classes/Database.php';
require_once '../classes/Settings.php';

$db = Database::getInstance();

$tx_ref = $_GET['tx_ref'] ?? $_POST['tx_ref'] ?? '';
$token  = $_GET['token']  ?? $_POST['token']  ?? '';
$expiry = intval($_GET['expiry'] ?? $_POST['expiry'] ?? 0);
$email  = strtolower(trim($_GET['email'] ?? $_POST['email'] ?? ''));

if (empty($tx_ref)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Transaction reference required']);
    exit;
}

$useTokenAuth = !empty($token) && !empty($expiry);

if ($useTokenAuth) {
    if (time() > $expiry) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Authentication token expired']);
        exit;
    }
} elseif (empty($email)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

try {
    if ($useTokenAuth) {
        // Check if token has already been consumed (graceful if table missing)
        try {
            $alreadyConsumed = $db->fetch("SELECT token FROM consumed_tokens WHERE token = ?", [$token]);
            if ($alreadyConsumed) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Authentication token already used']);
                exit;
            }
        } catch (\Throwable $e) {
            error_log("consumed_tokens check skipped: " . $e->getMessage());
        }
    }

    // Find the sale by transaction ref
    $sale = $db->fetch(
        "SELECT user_id, email, payment_status FROM sales WHERE tx_ref = ? OR transaction_id = ?",
        [$tx_ref, $tx_ref]
    );

    if (!$sale) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Transaction not found yet.']);
        exit;
    }

    $userId = $sale['user_id'];

    if ($useTokenAuth) {
        $secretKey = Settings::getInstance()->get('flutterwave_webhook_secret', '')
            ?: Settings::getInstance()->get('webhook_secret', '')
            ?: 'ojg_fallback_secret_key_123';

        $expectedSignature = hash_hmac('sha256', $tx_ref . '_' . $userId . '_' . $expiry, $secretKey);
        if (!hash_equals($expectedSignature, $token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid authentication token']);
            exit;
        }

        try {
            $db->insert('consumed_tokens', [
                'token'       => $token,
                'consumed_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            error_log("consumed_tokens insert skipped: " . $e->getMessage());
        }
    } else {
        // Email fallback — verify email matches the sale record
        if ($email !== strtolower(trim($sale['email']))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Email does not match transaction']);
            exit;
        }
    }

    // Fetch user details
    $user = $db->fetch(
        "SELECT id, email, username, first_name, name, type, status FROM users WHERE id = ?",
        [$userId]
    );

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User account not found']);
        exit;
    }

    // Generate auto-login token
    $authToken  = bin2hex(random_bytes(16));
    $authExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $db->query(
        "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
        [$userId, $authToken, $authExpiry]
    );

    echo json_encode([
        'success'          => true,
        'username'         => $user['username'] ?? $user['email'],
        'auto_login_token' => $authToken,
        'email'            => $user['email'],
        'name'             => $user['name'] ?? $user['first_name'],
        'message'          => 'Account ready'
    ]);

} catch (\Throwable $e) {
    error_log("Credential Fetch Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
