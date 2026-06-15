<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once '../admin/auth.php';
require_once '../classes/Database.php';
require_once '../classes/Settings.php';

$db = Database::getInstance();

$tx_ref = $_GET['tx_ref'] ?? $_POST['tx_ref'] ?? '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$expiry = intval($_GET['expiry'] ?? $_POST['expiry'] ?? 0);

if (empty($tx_ref)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Transaction reference required']);
    exit;
}

if (empty($token) || empty($expiry)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Authentication token required']);
    exit;
}

// 1. Check token expiry (15-min TTL)
if (time() > $expiry) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Authentication token expired']);
    exit;
}

try {
    // 2. Check if token has already been consumed
    $alreadyConsumed = $db->fetch("SELECT token FROM consumed_tokens WHERE token = ?", [$token]);
    if ($alreadyConsumed) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Authentication token already used']);
        exit;
    }

    // 3. Find the sale by transaction ref to get user_id
    $sale = $db->fetch("SELECT user_id, email, payment_status, created_at FROM sales WHERE tx_ref = ? OR transaction_id = ?", [$tx_ref, $tx_ref]);

    if (!$sale) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Transaction not found yet.']);
        exit;
    }

    $userId = $sale['user_id'];

    // 4. Verify HMAC token signature
    $secretKey = Settings::getInstance()->get('flutterwave_webhook_secret', '')
        ?: Settings::getInstance()->get('webhook_secret', '')
        ?: 'ojg_fallback_secret_key_123';

    $expectedSignature = hash_hmac('sha256', $tx_ref . '_' . $userId . '_' . $expiry, $secretKey);
    if (!hash_equals($expectedSignature, $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid authentication token']);
        exit;
    }

    // 5. Mark token as consumed
    $db->insert('consumed_tokens', [
        'token' => $token,
        'consumed_at' => date('Y-m-d H:i:s')
    ]);

    // 6. Fetch User Details (Explicitly strip password_hash from SELECT)
    $user = $db->fetch("SELECT id, email, username, first_name, name, type, status FROM users WHERE id = ?", [$userId]);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User account not found']);
        exit;
    }

    // 7. Generate Auto-Login Token
    $authToken = bin2hex(random_bytes(16));
    $authExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $db->query(
        "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
        [$userId, $authToken, $authExpiry]
    );

    // 8. Return Data
    echo json_encode([
        'success' => true,
        'username' => $user['username'] ?? $user['email'], // Fallback to email if no username
        'auto_login_token' => $authToken,
        'email' => $user['email'],
        'name' => $user['name'] ?? $user['first_name'],
        'message' => 'Account ready'
    ]);

} catch (Exception $e) {
    error_log("Credential Fetch Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
