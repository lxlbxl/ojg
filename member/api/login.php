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
require_once '../../backend/classes/ActivityLogger.php';

$auth = new MemberAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';

    $result = $auth->login($email, $password);
    if ($result['success']) {
        // Log Activity
        try {
            $logger = new ActivityLogger();
            $logger->log($auth->getCurrentUser()['user_id'], 'login', ['method' => 'email_password']);
        } catch (Exception $e) {
        }

        echo json_encode(['success' => true, 'user' => $auth->getCurrentUser()]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => $result['message'] ?? 'Invalid email or password']);
    }
}
?>