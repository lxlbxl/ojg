<?php
/**
 * OJG Async Job Worker
 * 
 * To be run via cron every minute: * * * * * php /path/to/backend/cron/worker.php
 */

require_once __DIR__ . '/../classes/Database.php';

// Add job handlers here as they are created
$handlers = [
    // 'generate_protocol' => 'ProtocolGeneratorJob',
    // 'send_email' => 'EmailJob',
];

try {
    $db = Database::getInstance()->getConnection();
    
    // Lock pending jobs that are ready to run (basic concurrency control)
    // Using a simple timeout to prevent infinite locks
    $lockId = uniqid('worker_', true);
    
    // SQLite doesn't have true row-level locking or GET_LOCK, so we do a simple UPDATE WHERE
    // In MySQL, this is atomic.
    $stmt = $db->prepare("
        UPDATE jobs 
        SET status = 'processing', updated_at = CURRENT_TIMESTAMP
        WHERE status = 'pending' AND run_after <= CURRENT_TIMESTAMP
        LIMIT 5
    ");
    $stmt->execute();
    
    $processed = $stmt->rowCount();
    if ($processed === 0) {
        echo "No jobs to process.\n";
        exit(0);
    }
    
    // Fetch the jobs we just locked (this is slightly unsafe in high concurrency without connection IDs, 
    // but works for basic cron on low volume)
    // A better approach for MySQL is using FOR UPDATE
    $stmt = $db->prepare("SELECT * FROM jobs WHERE status = 'processing'");
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($jobs as $job) {
        $id = $job['id'];
        $type = $job['type'];
        $payload = json_decode($job['payload'], true);
        
        echo "Processing job {$id} of type {$type}...\n";
        
        try {
            // Implement simple job routing
            if ($type === 'send_email') {
                // Example handler
                echo "  -> Handling email job\n";
            } else if ($type === 'generate_protocol') {
                // Example handler
                echo "  -> Handling protocol generation\n";
            } else {
                throw new Exception("Unknown job type: {$type}");
            }
            
            // Success
            $stmt = $db->prepare("UPDATE jobs SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
            echo "  -> Completed\n";
            
        } catch (Exception $e) {
            $attempts = $job['attempts'] + 1;
            $maxAttempts = 3;
            
            if ($attempts >= $maxAttempts) {
                $stmt = $db->prepare("UPDATE jobs SET status = 'failed', attempts = ?, error_message = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$attempts, $e->getMessage(), $id]);
                echo "  -> Failed permanently\n";
            } else {
                // Exponential backoff
                $delayMinutes = pow(2, $attempts);
                $stmt = $db->prepare("
                    UPDATE jobs 
                    SET status = 'pending', attempts = ?, error_message = ?, run_after = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL ? MINUTE), updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$attempts, $e->getMessage(), $delayMinutes, $id]);
                echo "  -> Failed, will retry in {$delayMinutes} minutes\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Worker error: " . $e->getMessage() . "\n";
    exit(1);
}
