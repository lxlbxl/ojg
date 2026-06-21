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

$auth = new MemberAuth();
$isLoggedIn = $auth->isLoggedIn();
$user = $auth->getCurrentUser();

echo json_encode([
    'authenticated' => $isLoggedIn,
    'user_id' => $user ? ($user['user_id'] ?? $user['id']) : null
]);
?>