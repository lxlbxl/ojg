<?php
/**
 * GDPR & NDPR Consent Recording API
 * 
 * Records user consent for audit trail compliance
 */

header('Content-Type: application/json');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit();
}

try {
    $db = Database::getInstance();

    $sessionId = sanitize($input['session_id'] ?? '');
    $email = sanitize($input['email'] ?? '');
    $consent = $input['consent'] ?? [];
    $url = sanitize($input['url'] ?? '');
    $userAgent = sanitize($input['user_agent'] ?? '');
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

    // Validate consent data
    if (empty($consent) || !isset($consent['categories'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid consent data']);
        exit();
    }

    // Get or create user ID from email
    $userId = null;
    if ($email) {
        $user = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        $userId = $user['id'] ?? null;

        // If user doesn't exist, we still record consent for audit
    }

    // Record consent for each category
    $consentId = uniqid('consent_');
    $now = date('Y-m-d H:i:s');

    foreach ($consent['categories'] as $category => $granted) {
        $db->insert('consent_records', [
            'id' => uniqid('cr_'),
            'user_id' => $userId,
            'session_id' => $sessionId,
            'email' => $email,
            'category' => $category,
            'consent_given' => $granted ? 1 : 0,
            'consent_version' => $consent['version'] ?? '1.0',
            'ip_address' => $ipAddress,
            'user_agent' => substr($userAgent, 0, 500),
            'page_url' => $url,
            'created_at' => $now,
            'updated_at' => $now
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Consent recorded successfully',
        'consent_id' => $consentId
    ]);

} catch (Exception $e) {
    error_log("Consent recording error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to record consent']);
}

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}