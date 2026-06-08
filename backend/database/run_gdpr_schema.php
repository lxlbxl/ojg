<?php
/**
 * GDPR & NDPR Compliance Schema Installer
 * 
 * Run this file once to create compliance tables in your database
 * Access: http://yourdomain.com/backend/database/run_gdpr_schema.php
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../config/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>GDPR Schema Installer</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: #059669; background: #d1fae5; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .error { color: #dc2626; background: #fee2e2; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .info { color: #2563eb; background: #dbeafe; padding: 15px; border-radius: 8px; margin: 10px 0; }
        h1 { color: #1f2937; }
        pre { background: #f3f4f6; padding: 15px; border-radius: 8px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔒 GDPR & NDPR Compliance Schema Installer</h1>
";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $isMySQL = $pdo && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    echo "<div class='info'>Database Type: " . ($isMySQL ? "MySQL" : "SQLite") . "</div>";

    // Read and execute schema
    $schemaFile = __DIR__ . '/gdpr_schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: gdpr_schema.sql");
    }

    $sql = file_get_contents($schemaFile);

    if ($isMySQL) {
        // MySQL execution
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0)
                continue;
            // Skip SQLite-specific statements
            if (strpos($statement, 'SQLITE') !== false)
                continue;
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignore "already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    throw $e;
                }
            }
        }
        echo "<div class='success'>✅ MySQL compliance tables created successfully!</div>";
    } else {
        // SQLite execution - use SQLite-specific table names
        $sqliteStatements = [
            "CREATE TABLE IF NOT EXISTS consent_records (
                id TEXT PRIMARY KEY,
                user_id INTEGER NULL,
                session_id TEXT NULL,
                email TEXT NULL,
                category TEXT NOT NULL,
                consent_given INTEGER NOT NULL DEFAULT 0,
                consent_version TEXT NOT NULL DEFAULT '1.0',
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                page_url TEXT NULL,
                withdrawn_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_consent_email ON consent_records(email)",
            "CREATE INDEX IF NOT EXISTS idx_consent_user_id ON consent_records(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_consent_category ON consent_records(category)",
            "CREATE INDEX IF NOT EXISTS idx_consent_created ON consent_records(created_at)",

            "CREATE TABLE IF NOT EXISTS data_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                request_type TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                requested_data TEXT NULL,
                processed_by INTEGER NULL,
                processed_at DATETIME NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_req_email ON data_requests(email)",
            "CREATE INDEX IF NOT EXISTS idx_req_status ON data_requests(status)",

            "CREATE TABLE IF NOT EXISTS data_breaches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                breach_type TEXT NOT NULL,
                severity TEXT NOT NULL,
                affected_users INTEGER NULL,
                description TEXT NULL,
                discovered_at DATETIME NOT NULL,
                reported_to_authority INTEGER DEFAULT 0,
                reported_at DATETIME NULL,
                notified_users INTEGER DEFAULT 0,
                notification_sent_at DATETIME NULL,
                resolved_at DATETIME NULL,
                status TEXT NOT NULL DEFAULT 'investigating',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS compliance_settings (
                id INTEGER PRIMARY KEY,
                data_retention_days INTEGER NOT NULL DEFAULT 730,
                cookie_consent_required INTEGER NOT NULL DEFAULT 1,
                marketing_consent_required INTEGER NOT NULL DEFAULT 1,
                privacy_policy_version TEXT NOT NULL DEFAULT '1.0.0',
                dpo_email TEXT NULL,
                consent_record_retention INTEGER NOT NULL DEFAULT 1095,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",

            "INSERT OR IGNORE INTO compliance_settings (id, data_retention_days, cookie_consent_required, marketing_consent_required, privacy_policy_version, dpo_email, consent_record_retention)
             VALUES (1, 730, 1, 1, '1.0.0', '', 1095)",

            "ALTER TABLE users ADD COLUMN consent_recorded INTEGER DEFAULT 0",
            "ALTER TABLE users ADD COLUMN consent_date DATETIME NULL",
            "ALTER TABLE users ADD COLUMN marketing_opt_in INTEGER DEFAULT 0",
            "ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL"
        ];

        foreach ($sqliteStatements as $statement) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignore "already exists" errors
                if (
                    strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'duplicate column') === false
                ) {
                    echo "<div class='info'>ℹ️ Notice: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
        }
        echo "<div class='success'>✅ SQLite compliance tables created successfully!</div>";
    }

    // Verify tables were created
    $tables = ['consent_records', 'data_requests', 'data_breaches', 'compliance_settings'];
    echo "<h2>Verification</h2>";
    foreach ($tables as $table) {
        try {
            $result = $db->fetch("SELECT COUNT(*) as count FROM $table");
            echo "<div class='success'>✅ Table '$table' exists (" . $result['count'] . " records)</div>";
        } catch (Exception $e) {
            echo "<div class='error'>❌ Table '$table' not found</div>";
        }
    }

    echo "<h2>Next Steps</h2>
    <ol>
        <li>Include cookie consent on all pages: <code><script src=\"/js/cookie-consent.js\"></script></code></li>
        <li>Add consent checkboxes to forms (see backend/components/consent-checkbox.html)</li>
        <li>Configure DPO email in admin dashboard: /backend/admin/gdpr-ndpr.php</li>
        <li>Add privacy policy link to footers</li>
    </ol>";

    echo "<div class='info'>
        <strong>📖 Full documentation:</strong> See COMPLIANCE_GUIDE.md in your project root.
    </div>";

} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "
    <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px;'>
        🔒 For security, consider deleting this file after running it.
    </p>
</body>
</html>
";