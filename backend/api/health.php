<?php
/**
 * Simple Health Check Endpoint
 * Verifies basic connectivity for monitoring services
 */

require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json');

$status = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'services' => [
        'database' => 'unknown'
    ]
];

// Check DB Connection
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT 1");
    if ($stmt) {
        $status['services']['database'] = 'ok';
    } else {
        $status['services']['database'] = 'error';
        $status['status'] = 'degraded';
    }
} catch (Exception $e) {
    $status['services']['database'] = 'error';
    $status['status'] = 'degraded';
    $status['error'] = $e->getMessage();
}

if ($status['status'] === 'ok') {
    http_response_code(200);
} else {
    http_response_code(503);
}

echo json_encode($status);
