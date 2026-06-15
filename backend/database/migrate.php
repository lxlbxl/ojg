<?php
/**
 * OJG Database Migration Runner
 * 
 * Applies SQL migration files sequentially from the migrations/ directory.
 * Tracks applied migrations in the schema_migrations table.
 */

require_once __DIR__ . '/../classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Ensure migrations table exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration_name VARCHAR(255) NOT NULL UNIQUE,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    echo "Migration runner started...\n";
    
    $migrationsDir = __DIR__ . '/migrations';
    if (!is_dir($migrationsDir)) {
        mkdir($migrationsDir, 0755, true);
    }
    
    $files = scandir($migrationsDir);
    sort($files);
    
    $appliedCount = 0;
    
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            // Check if already applied
            $stmt = $db->prepare("SELECT id FROM schema_migrations WHERE migration_name = ?");
            $stmt->execute([$file]);
            
            if (!$stmt->fetch()) {
                echo "Applying migration: $file\n";
                
                $sql = file_get_contents($migrationsDir . '/' . $file);
                
                $db->beginTransaction();
                try {
                    $db->exec($sql);
                    
                    $stmt = $db->prepare("INSERT INTO schema_migrations (migration_name) VALUES (?)");
                    $stmt->execute([$file]);
                    
                    $db->commit();
                    echo "  -> Success\n";
                    $appliedCount++;
                } catch (Exception $e) {
                    $db->rollBack();
                    echo "  -> Failed: " . $e->getMessage() . "\n";
                    exit(1);
                }
            }
        }
    }
    
    if ($appliedCount === 0) {
        echo "No new migrations to apply.\n";
    } else {
        echo "Successfully applied $appliedCount migrations.\n";
    }
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
