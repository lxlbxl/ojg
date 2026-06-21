<?php
header('Content-Type: application/json');
$allowedOrigins = ['https://ojg.ng', 'https://www.ojg.ng', 'http://localhost:5173', 'http://localhost:8080'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit;
require_once '../../backend/config/config.php';
require_once '../../backend/classes/Database.php';
require_once '../../backend/classes/MemberAuth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';

if (!$token) {
    echo json_encode(['success' => false, 'error' => 'No token provided']);
    exit;
}

$db = Database::getInstance();
// Verify token
$tokenRow = $db->fetch("SELECT user_id FROM auth_tokens WHERE token = ? AND expires_at > ?", [$token, date('Y-m-d H:i:s')]);

if ($tokenRow) {
    $userId = $tokenRow['user_id'];
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);

    if ($user) {
        // Log in user (set session)
        // We'll manually set session to avoid adding "loginWithId" to Auth class for now, 
        // to minimize changes, matching login.php logic.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['ojg_member_session'] = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'] ?? $user['name'] ?? '',
            'type' => $user['type'] ?? 'customer'
        ];

        // Consume token
        $db->query("DELETE FROM auth_tokens WHERE token = ?", [$token]);

        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid or expired token']);
?>