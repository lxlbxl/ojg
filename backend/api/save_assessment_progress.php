<?php
/**
 * Save partial assessment progress
 */

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/RateLimiter.php';

header('Content-Type: application/json');

// Apply basic rate limiting
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!RateLimiter::consume("assessment_progress_{$ip}", 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['session_id']) || !isset($data['funnel_name']) || !isset($data['progress_data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payload']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        INSERT INTO assessment_progress (session_id, funnel_name, current_step, progress_data, created_at, updated_at)
        VALUES (:session_id, :funnel_name, :current_step, :progress_data, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE 
            funnel_name = :funnel_name,
            current_step = :current_step,
            progress_data = :progress_data,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    // Fallback for SQLite which uses a different UPSERT syntax
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $stmt = $db->prepare("
            INSERT INTO assessment_progress (session_id, funnel_name, current_step, progress_data, created_at, updated_at)
            VALUES (:session_id, :funnel_name, :current_step, :progress_data, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(session_id) DO UPDATE SET 
                funnel_name = :funnel_name,
                current_step = :current_step,
                progress_data = :progress_data,
                updated_at = CURRENT_TIMESTAMP
        ");
    }
    
    $stmt->execute([
        ':session_id' => $data['session_id'],
        ':funnel_name' => $data['funnel_name'],
        ':current_step' => $data['current_step'] ?? 1,
        ':progress_data' => json_encode($data['progress_data'])
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    error_log("Assessment progress save error: " . $e->getMessage());
}
