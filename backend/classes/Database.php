<?php
/**
 * Database Connection Class
 * Handles database operations for OJG Herbal system
 * Falls back to file-based storage if SQLite PDO is not available
 */

// Ensure APP_ROOT is defined for internal path resolution
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

class Database
{
    private static $instance = null;
    private $connection = null;
    private $useFileStorage = false;
    private $dataPath;

    private function __construct()
    {
        $this->dataPath = APP_ROOT . '/database/data/';
        if (!is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0755, true);
        }
        $this->connect();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function isFileStorage()
    {
        return $this->useFileStorage;
    }

    public function getDataPath()
    {
        return $this->dataPath;
    }

    private function connect()
    {
        try {
            // Determine database type
            $dbType = defined('DB_TYPE') ? DB_TYPE : 'sqlite';

            if ($dbType === 'mysql') {
                $this->connectMySQL();
            } elseif ($dbType === 'pgsql') {
                $this->connectPostgres();
            } else {
                $this->connectSQLite();
            }

            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Initialize database if it's empty
            $this->initializeIfEmpty();
            $this->ensureExpandedSchema();

        } catch (PDOException $e) {
            // If MySQL fails and we haven't tried SQLite yet, try fallback
            if (isset($dbType) && $dbType === 'mysql') {
                error_log("MySQL Connection failed: " . $e->getMessage() . ". Falling back to SQLite.");
                try {
                    $this->connectSQLite();
                    $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    $this->initializeIfEmpty();
                    $this->ensureExpandedSchema();
                    return;
                } catch (PDOException $e2) {
                    // Fallback to file storage if SQLite also fails
                }
            }

            // Fall back to file storage
            $this->useFileStorage = true;
            error_log("Database connection failed completely. Using file storage. Error: " . $e->getMessage());
        }
    }

    private function connectMySQL()
    {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $this->connection = new PDO($dsn, DB_USER, DB_PASS);
    }

    private function connectPostgres()
    {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $this->connection = new PDO($dsn, DB_USER, DB_PASS);
        $this->connection->exec("SET NAMES 'UTF8'");
    }

    private function connectSQLite()
    {
        // Check if PDO SQLite is available
        if (!in_array('sqlite', PDO::getAvailableDrivers())) {
            throw new PDOException("SQLite driver not available");
        }

        // Create database directory if it doesn't exist
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        // Create SQLite connection
        $this->connection = new PDO('sqlite:' . DB_PATH);

        // Enable foreign key constraints
        $this->connection->exec('PRAGMA foreign_keys = ON');
    }

    private function initializeIfEmpty()
    {
        // Check if admin_users table exists
        if ($this->isMySQL()) {
            $stmt = $this->connection->query("SHOW TABLES LIKE 'admin_users'");
        } elseif ($this->isPostgres()) {
            $stmt = $this->connection->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'admin_users')");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            if ($row && !$row[0]) {
                $this->initializeSchema();
            }
            return;
        } else {
            $stmt = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
        }

        $table = $stmt->fetch();

        if (!$table) {
            $this->initializeSchema();
        }
    }

    private function initializeSchema()
    {
        $schemaFile = $this->isPostgres()
            ? APP_ROOT . '/database/schema_postgres.sql'
            : APP_ROOT . '/database/schema.sql';

        if (file_exists($schemaFile)) {
            $schema = file_get_contents($schemaFile);

            // Adjust schema for MySQL if needed
            if ($this->isMySQL()) {
                $schema = str_replace('AUTOINCREMENT', 'AUTO_INCREMENT', $schema);
                $schema = str_replace('datetime(\'now\')', 'NOW()', $schema);
            }

            // For PostgreSQL, use the dedicated schema file (no adjustments needed)
            // For SQLite, execute the statements split by semicolon

            // Split schema into individual statements
            $statements = $this->splitSchemaStatements($schema);

            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    try {
                        $this->connection->exec($statement);
                    } catch (PDOException $e) {
                        // Ignore "table already exists" errors and PostgreSQL "already exists" errors
                        $msg = $e->getMessage();
                        if (
                            strpos($msg, 'already exists') === false &&
                            strpos($msg, '1050') === false &&
                            strpos($msg, '42P07') === false &&
                            strpos($msg, '42P16') === false &&
                            strpos($msg, '42710') === false &&
                            strpos($msg, 'duplicate key') === false &&
                            strpos($msg, 'unique constraint') === false
                        ) {
                            if (DEBUG_MODE) {
                                error_log("Schema execution error: " . $msg);
                            }
                        }
                    }
                }
            }
        }
    }

    private function splitSchemaStatements($schema)
    {
        // For PostgreSQL, split on semicolons but respect DO$$...$$ blocks
        if (strpos($schema, '$$') !== false) {
            // Return schema as single block for PostgreSQL DO blocks and functions
            $statements = [];
            $parts = preg_split('/(?<=;)\s*(?=\S)/', $schema);
            $current = '';
            foreach ($parts as $part) {
                $current .= $part;
                if (substr_count($current, '$$') === 0 || (substr_count($current, '$$') % 2) === 0) {
                    if (trim($current) !== '' && !preg_match('/^\s*--/', trim($current))) {
                        $statements[] = trim($current);
                    }
                    $current = '';
                } else {
                    $current .= "\n";
                }
            }
            if (trim($current) !== '') {
                $statements[] = trim($current);
            }
            return $statements;
        }

        return array_filter(
            array_map('trim', explode(';', $schema)),
            function ($stmt) {
                return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
            }
        );
    }

    private function initializeMemberSchema()
    {
        $schemaFile = APP_ROOT . '/database/member_schema.sql';

        if (file_exists($schemaFile)) {
            $schema = file_get_contents($schemaFile);

            // Adjust schema for MySQL if needed
            if ($this->isMySQL()) {
                $schema = str_replace('AUTOINCREMENT', 'AUTO_INCREMENT', $schema);
                $schema = str_replace("datetime('now')", "NOW()", $schema);
                $schema = str_replace("INSERT OR IGNORE", "INSERT IGNORE", $schema);
            }

            // Split schema into individual statements
            $statements = array_filter(
                array_map('trim', explode(';', $schema)),
                function ($stmt) {
                    return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
                }
            );

            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    try {
                        $this->connection->exec($statement);
                    } catch (PDOException $e) {
                        // Ignore "table already exists" errors
                        if (
                            strpos($e->getMessage(), 'already exists') === false &&
                            strpos($e->getMessage(), '1050') === false
                        ) { // MySQL error 1050
                            if (DEBUG_MODE) {
                                error_log("Member Schema execution error: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }
    }

    private function ensureExpandedSchema()
    {
        try {
            if ($this->useFileStorage) {
                return;
            }

            // Check if tables exist (MySQL/SQLite compatible way)
            $tables = ['assessments', 'sales', 'webhook_queue', 'funnel_tracking'];
            foreach ($tables as $table) {
                $this->createTableIfNotExists($table);
            }

            // Add missing columns to sales, users and tracking tables
            $this->ensureSalesColumns();
            $this->ensureUserColumns();
            $this->ensureTrackingColumns();

            // Initialize Member Schema (Dual DB Support)
            $this->initializeMemberSchema();

            // Ensure AI Logs Columns
            $this->ensureAiLogsColumns();

            // Create security and rate limiting tables if they don't exist
            $isMySQL = $this->isMySQL();
            $isPostgres = $this->isPostgres();
            if ($isMySQL) {
                $this->connection->exec("CREATE TABLE IF NOT EXISTS consumed_tokens (
                    token VARCHAR(64) PRIMARY KEY,
                    consumed_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $this->connection->exec("CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    identifier VARCHAR(255) NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_identifier (identifier),
                    INDEX idx_ip (ip_address)
                )");
                $this->connection->exec("CREATE TABLE IF NOT EXISTS rate_limits_bucket (
                    ip_address VARCHAR(45) PRIMARY KEY,
                    tokens DECIMAL(10, 4) NOT NULL,
                    last_updated INT NOT NULL
                )");
            } elseif ($isPostgres) {
                // PostgreSQL tables created by schema_postgres.sql
                // No additional setup needed here
            } else {
                $this->connection->exec("CREATE TABLE IF NOT EXISTS consumed_tokens (
                    token TEXT PRIMARY KEY,
                    consumed_at TEXT DEFAULT CURRENT_TIMESTAMP
                )");
                $this->connection->exec("CREATE TABLE IF NOT EXISTS login_attempts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    identifier TEXT NOT NULL,
                    ip_address TEXT NOT NULL,
                    attempted_at TEXT DEFAULT CURRENT_TIMESTAMP
                )");
                $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_ident ON login_attempts(identifier)");
                $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address)");
                
                $this->connection->exec("CREATE TABLE IF NOT EXISTS rate_limits_bucket (
                    ip_address TEXT PRIMARY KEY,
                    tokens REAL NOT NULL,
                    last_updated INTEGER NOT NULL
                )");
            }

            // A/B Engine migrations (experiments, variants, assignments, etc.)
            $this->ensureExperimentColumns();

        } catch (Exception $e) {
            error_log("Ensure schema error: " . $e->getMessage());
        }
    }

    /**
     * A/B Engine schema bootstrap.
     * Runs the migration file (idempotent CREATE TABLE IF NOT EXISTS),
     * and adds new columns to funnel_tracking. Safe to call on every request.
     */
    private function ensureExperimentColumns()
    {
        try {
            // 1. Run migration file (creates experiments, variants, assignments, etc.)
            $migrationFile = $this->isMySQL()
                ? APP_ROOT . '/database/migrations/001_ab_engine_mysql.sql'
                : APP_ROOT . '/database/migrations/001_ab_engine_sqlite.sql';

            if (file_exists($migrationFile)) {
                $sql = file_get_contents($migrationFile);

                // Split on semicolons; ignore comments and empty lines
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    function ($s) {
                        return $s !== '' && strpos($s, '--') !== 0 && strpos($s, "\n--") !== 1;
                    }
                );

                foreach ($statements as $stmt) {
                    if (preg_match('/^\s*--/', $stmt))
                        continue;
                    if (trim($stmt) === '')
                        continue;
                    try {
                        $this->connection->exec($stmt);
                    } catch (PDOException $e) {
                        // Ignore "already exists" so this is idempotent
                        $msg = $e->getMessage();
                        if (
                            strpos($msg, 'already exists') === false &&
                            strpos($msg, '1050') === false &&      // MySQL: table exists
                            strpos($msg, '1060') === false &&      // MySQL: duplicate column
                            strpos($msg, '1061') === false &&      // MySQL: duplicate key
                            strpos($msg, '1062') === false &&      // MySQL: duplicate entry
                            strpos($msg, 'duplicate column') === false &&
                            strpos($msg, 'no such column') === false
                        ) {
                            if (DEBUG_MODE) {
                                error_log("A/B migration stmt error: " . $msg . " | SQL: " . substr($stmt, 0, 120));
                            }
                        }
                    }
                }
            }

            // 2. Add experiment_id / variant_id / revenue to funnel_tracking (defensive)
            $this->addColumnIfMissing('funnel_tracking', 'experiment_id', $this->isMySQL() ? 'INT NULL' : 'INTEGER');
            $this->addColumnIfMissing('funnel_tracking', 'variant_id', $this->isMySQL() ? 'INT NULL' : 'INTEGER');
            $this->addColumnIfMissing('funnel_tracking', 'revenue', $this->isMySQL() ? 'DECIMAL(10,2) NULL' : 'REAL');

        } catch (Exception $e) {
            error_log("ensureExperimentColumns error: " . $e->getMessage());
        }
    }

    /**
     * Defensive helper: add a column to a table only if it doesn't already exist.
     * Works for MySQL, PostgreSQL, and SQLite.
     */
    private function addColumnIfMissing($table, $column, $definition)
    {
        try {
            $exists = false;
            if ($this->isMySQL()) {
                $stmt = $this->connection->prepare(
                    "SELECT COUNT(*) AS c FROM information_schema.columns
                     WHERE table_schema = ? AND table_name = ? AND column_name = ?"
                );
                $dbName = defined('DB_NAME') ? DB_NAME : '';
                $stmt->execute([$dbName, $table, $column]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $exists = ((int) ($row['c'] ?? 0)) > 0;
            } elseif ($this->isPostgres()) {
                $stmt = $this->connection->prepare(
                    "SELECT EXISTS (SELECT FROM information_schema.columns 
                     WHERE table_schema = 'public' AND table_name = ? AND column_name = ?)"
                );
                $stmt->execute([$table, $column]);
                $exists = (bool) $stmt->fetchColumn();
            } else {
                $cols = $this->fetchAll("PRAGMA table_info($table)");
                foreach ($cols as $c) {
                    if (($c['name'] ?? null) === $column) {
                        $exists = true;
                        break;
                    }
                }
            }
            if (!$exists) {
                $this->connection->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            }
        } catch (Exception $e) {
            // Already exists or no permission — ignore
        }
    }

    private function createTableIfNotExists($table)
    {
        // This is handled by schema.sql now, but kept as backup
        if ($table === 'assessments') {
            $sql = $this->isMySQL() ?
                "CREATE TABLE IF NOT EXISTS assessments (id VARCHAR(50) PRIMARY KEY, user_id INTEGER, email VARCHAR(255) NOT NULL, name VARCHAR(100), phone VARCHAR(20), assessment_type VARCHAR(50), assessment_data TEXT, score DECIMAL(5,2), recommendations TEXT, tracking_data TEXT, ip_address VARCHAR(45), user_agent TEXT, referrer TEXT, status VARCHAR(20) DEFAULT 'completed', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)" :
                "CREATE TABLE IF NOT EXISTS assessments (id VARCHAR(50) PRIMARY KEY, user_id INTEGER, email VARCHAR(255) NOT NULL, name VARCHAR(100), phone VARCHAR(20), assessment_type VARCHAR(50), assessment_data TEXT, score REAL, recommendations TEXT, tracking_data TEXT, ip_address TEXT, user_agent TEXT, referrer TEXT, status TEXT DEFAULT 'completed', created_at TEXT, updated_at TEXT)";
            $this->connection->exec($sql);
        } else if ($table === 'funnel_tracking') {
            $sql = $this->isMySQL() ?
                "CREATE TABLE IF NOT EXISTS funnel_tracking (id INT AUTO_INCREMENT PRIMARY KEY, session_id VARCHAR(100), user_id INT, email VARCHAR(255), funnel_name VARCHAR(50), step_name VARCHAR(100), event_type VARCHAR(50), metadata TEXT, url TEXT, ip_address VARCHAR(45), user_agent TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL)" :
                "CREATE TABLE IF NOT EXISTS funnel_tracking (id INTEGER PRIMARY KEY AUTOINCREMENT, session_id VARCHAR(100), user_id INTEGER, email VARCHAR(255), funnel_name VARCHAR(50), step_name VARCHAR(100), event_type VARCHAR(50), metadata TEXT, url TEXT, ip_address VARCHAR(45), user_agent TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)";
            $this->connection->exec($sql);
        }
    }

    private function ensureSalesColumns()
    {
        // Get existing columns
        if ($this->isMySQL()) {
            $stmt = $this->connection->prepare("DESCRIBE sales");
            $stmt->execute();
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($this->isPostgres()) {
            $stmt = $this->connection->prepare(
                "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'sales'"
            );
            $stmt->execute();
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $cols = $this->fetchAll("PRAGMA table_info(sales)");
            $cols = array_map(function ($c) {
                return $c['name'];
            }, $cols);
        }

        $add = function ($name, $type) use ($cols) {
            if (!in_array($name, $cols)) {
                $this->connection->exec("ALTER TABLE sales ADD COLUMN $name $type");
            }
        };

        $textType = $this->isMySQL() ? 'TEXT' : 'TEXT';
        $realType = $this->isMySQL() ? 'DECIMAL(10,2)' : 'REAL';

        $add('email', $textType);
        $add('name', $textType);
        $add('phone', $textType);
        $add('currency', $textType);
        $add('tx_ref', $textType);
        $add('amount', $realType);
        $add('product_type', $textType);
        $add('customer_data', $textType);
        $add('product_data', $textType);
        $add('ip_address', $textType);
        $add('user_agent', $textType);
        $add('referrer', $textType);
        $add('notes', $textType);
        $add('tracking_data', $textType);
        $add('created_at', $this->isMySQL() ? 'DATETIME' : 'TEXT');
        $add('updated_at', $this->isMySQL() ? 'DATETIME' : 'TEXT');
    }

    private function ensureUserColumns()
    {
        if ($this->isMySQL()) {
            $stmt = $this->connection->prepare("DESCRIBE users");
            $stmt->execute();
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($this->isPostgres()) {
            $stmt = $this->connection->prepare(
                "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'users'"
            );
            $stmt->execute();
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $cols = $this->fetchAll("PRAGMA table_info(users)");
            $cols = array_map(function ($c) {
                return $c['name'];
            }, $cols);
        }

        if (!in_array('type', $cols)) {
            $this->connection->exec("ALTER TABLE users ADD COLUMN type VARCHAR(20) DEFAULT 'lead'");
        }
        if (!in_array('name', $cols)) {
            $this->connection->exec("ALTER TABLE users ADD COLUMN name VARCHAR(255)");
        }
        if (!in_array('username', $cols)) {
            $this->connection->exec("ALTER TABLE users ADD COLUMN username VARCHAR(50)");
        }
        if (!in_array('password_hash', $cols)) {
            $this->connection->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255)");
        }
        if (!in_array('condition_type', $cols)) {
            $this->connection->exec("ALTER TABLE users ADD COLUMN condition_type VARCHAR(50)");
        }
        if (!in_array('marketing_consent', $cols)) {
            $this->connection->exec("ALTER TABLE users ADD COLUMN marketing_consent INTEGER DEFAULT 0");
        }
        if (!in_array('data_consent', $cols)) {
            $this->connection->exec("ALTER TABLE users ADD COLUMN data_consent INTEGER DEFAULT 1");
        }

    }

    private function ensureTrackingColumns()
    {
        if ($this->isMySQL()) {
            $stmt = $this->connection->prepare("DESCRIBE funnel_tracking");
            $stmt->execute();
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($this->isPostgres()) {
            $stmt = $this->connection->prepare(
                "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'funnel_tracking'"
            );
            $stmt->execute();
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $cols = $this->fetchAll("PRAGMA table_info(funnel_tracking)");
            $cols = array_map(function ($c) {
                return $c['name'];
            }, $cols);
        }

        $add = function ($name, $type) use ($cols) {
            if (!in_array($name, $cols)) {
                $this->connection->exec("ALTER TABLE funnel_tracking ADD COLUMN $name $type");
            }
        };

        $add('email', 'VARCHAR(255)');
        $add('url', 'TEXT');
    }

    private function ensureAiLogsColumns()
    {
        // First check if table exists
        if ($this->isMySQL()) {
            $stmt = $this->connection->query("SHOW TABLES LIKE 'ai_generation_logs'");
        } else {
            $stmt = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ai_generation_logs'");
        }

        if (!$stmt->fetch()) {
            return; // Table doesn't exist yet, schema init will handle it
        }

        if ($this->isMySQL()) {
            $stmt = $this->connection->prepare("DESCRIBE ai_generation_logs");
            $stmt->execute();
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $cols = $this->fetchAll("PRAGMA table_info(ai_generation_logs)");
            $cols = array_map(function ($c) {
                return $c['name'];
            }, $cols);
        }

        if (!in_array('metadata', $cols)) {
            $type = $this->isMySQL() ? 'TEXT' : 'TEXT';
            try {
                $this->connection->exec("ALTER TABLE ai_generation_logs ADD COLUMN metadata $type");
            } catch (Exception $e) {
                // Ignore if it fails, maybe already there or locked
            }
        }
    }

    private function isMySQL()
    {
        return $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    private function isPostgres()
    {
        return $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function isUsingFileStorage()
    {
        return $this->useFileStorage;
    }

    public function prepare($sql)
    {
        if ($this->useFileStorage) {
            // Return a mock statement for file storage
            // This is a simplification; file storage logic is handled in query()
            // Ideally, code should use query() instead of prepare() for compatibility
            return new MockPDOStatement($this, $sql);
        }
        return $this->connection->prepare($sql);
    }

    public function exec($sql)
    {
        if ($this->useFileStorage) {
            return $this->handleFileQuery($sql);
        }
        return $this->connection->exec($sql);
    }

    // File-based storage methods
    private function getFilePath($table)
    {
        return $this->dataPath . $table . '.json';
    }

    private function loadData($table)
    {
        $filePath = $this->getFilePath($table);
        if (!file_exists($filePath)) {
            return [];
        }
        $data = file_get_contents($filePath);
        return json_decode($data, true) ?: [];
    }

    private function saveData($table, $data)
    {
        $filePath = $this->getFilePath($table);
        return file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function query($sql, $params = [])
    {
        if ($this->useFileStorage) {
            return $this->handleFileQuery($sql, $params);
        }

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception('Query failed: ' . $e->getMessage());
        }
    }

    private function handleFileQuery($sql, $params = [])
    {
        // Simple file-based query handler for basic operations
        $sql = strtolower(trim($sql));

        if (strpos($sql, 'select') === 0) {
            return $this->handleFileSelect($sql, $params);
        } elseif (strpos($sql, 'insert') === 0) {
            return $this->handleFileInsert($sql, $params);
        } elseif (strpos($sql, 'update') === 0) {
            return $this->handleFileUpdate($sql, $params);
        } elseif (strpos($sql, 'delete') === 0) {
            return $this->handleFileDelete($sql, $params);
        }

        return false;
    }

    private function handleFileSelect($sql, $params)
    {
        // Extract table name from SQL
        preg_match('/from\s+(\w+)/i', $sql, $matches);
        $table = $matches[1] ?? '';

        if ($table === 'admin_users') {
            // Load admin users from file
            $data = $this->loadData($table);

            if (empty($data)) {
                // Return hardcoded admin user if no file exists
                $adminUser = [
                    'id' => 1,
                    'username' => 'admin',
                    'email' => 'admin@ojgherbal.com',
                    'password' => password_hash('admin123', PASSWORD_DEFAULT),
                    'full_name' => 'System Administrator',
                    'role' => 'admin',
                    'permissions' => '["all"]',
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'last_login' => null,
                    'login_count' => 0
                ];

                return new FileQueryResult([$adminUser]);
            }

            // Filter data based on WHERE conditions
            $filteredData = $data;

            // Simple WHERE condition parsing for username
            if (strpos($sql, 'username') !== false && isset($params[':identifier'])) {
                $filteredData = array_filter($data, function ($record) use ($params) {
                    return $record['username'] === $params[':identifier'] ||
                        $record['email'] === $params[':identifier'];
                });
            } elseif (strpos($sql, 'username') !== false && isset($params[':username'])) {
                $filteredData = array_filter($data, function ($record) use ($params) {
                    return $record['username'] === $params[':username'];
                });
            }

            return new FileQueryResult(array_values($filteredData));
        }

        // For other tables, load from file
        $data = $this->loadData($table);
        return new FileQueryResult($data);
    }

    private function handleFileInsert($sql, $params)
    {
        // Extract table name and handle basic inserts
        preg_match('/insert\s+into\s+(\w+)/i', $sql, $matches);
        if (isset($matches[1])) {
            $table = $matches[1];
            $data = $this->loadData($table);

            // Create new record with auto-increment ID
            $newId = count($data) + 1;
            $newRecord = array_merge(['id' => $newId], $params);
            $newRecord['created_at'] = date('Y-m-d H:i:s');

            $data[] = $newRecord;
            $this->saveData($table, $data);

            return new FileQueryResult([$newRecord]);
        }

        return false;
    }

    private function handleFileUpdate($sql, $params)
    {
        // Basic update handling
        return new FileQueryResult([]);
    }

    private function handleFileDelete($sql, $params)
    {
        // Basic delete handling
        return new FileQueryResult([]);
    }

    public function fetch($sql, $params = [])
    {
        if ($this->useFileStorage) {
            $result = $this->handleFileQuery($sql, $params);
            return $result ? $result->fetch() : false;
        }

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('Query failed: ' . $e->getMessage());
        }
    }

    public function fetchAll($sql, $params = [])
    {
        if ($this->useFileStorage) {
            $result = $this->handleFileQuery($sql, $params);
            return $result ? $result->fetchAll() : [];
        }

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Query failed: ' . $e->getMessage());
        }
    }

    public function insert($table, $data)
    {
        if ($this->useFileStorage) {
            $columns = array_keys($data);
            $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (...)";
            $result = $this->handleFileInsert($sql, $data);
            return $result ? 1 : false;
        }

        $columns = array_keys($data);
        $placeholders = array_map(function ($col) {
            return ':' . $col;
        }, $columns);

        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $params = [];
        foreach ($data as $key => $value) {
            $params[':' . $key] = $value;
        }

        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        if ($this->useFileStorage) {
            // Basic update for file storage
            return 1;
        }

        $setParts = [];
        $params = [];

        foreach ($data as $key => $value) {
            $setParts[] = "{$key} = :{$key}";
            $params[':' . $key] = $value;
        }

        // Merge where parameters
        $params = array_merge($params, $whereParams);

        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete($table, $where, $params = [])
    {
        if ($this->useFileStorage) {
            // Basic delete for file storage
            return 1;
        }

        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function count($table, $where = '1=1', $params = [])
    {
        if ($this->useFileStorage) {
            $data = $this->loadData($table);
            return count($data);
        }

        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$where}";
        $result = $this->fetch($sql, $params);
        return (int) $result['count'];
    }

    public function lastInsertId()
    {
        if ($this->useFileStorage) {
            return 1; // Return a default ID for file storage
        }
        return $this->connection->lastInsertId();
    }

    public function beginTransaction()
    {
        if (!$this->useFileStorage && $this->connection) {
            return $this->connection->beginTransaction();
        }
        return true;
    }

    public function commit()
    {
        if (!$this->useFileStorage && $this->connection) {
            return $this->connection->commit();
        }
        return true;
    }

    public function rollback()
    {
        if (!$this->useFileStorage && $this->connection) {
            return $this->connection->rollback();
        }
        return true;
    }

    // Statistics methods
    public function getStats()
    {
        try {
            $stats = [];

            if ($this->useFileStorage) {
                // File-based stats
                $usersFile = $this->dataPath . 'users.json';
                $assessmentsFile = $this->dataPath . 'assessments.json';
                $salesFile = $this->dataPath . 'sales.json';
                $contactsFile = $this->dataPath . 'contacts.json';

                $stats['users'] = file_exists($usersFile) ? count(json_decode(file_get_contents($usersFile), true) ?: []) : 0;
                $stats['assessments'] = file_exists($assessmentsFile) ? count(json_decode(file_get_contents($assessmentsFile), true) ?: []) : 0;
                $stats['sales'] = file_exists($salesFile) ? count(json_decode(file_get_contents($salesFile), true) ?: []) : 0;
                $stats['contacts'] = file_exists($contactsFile) ? count(json_decode(file_get_contents($contactsFile), true) ?: []) : 0;

            } else {
                // Database stats
                $stmt = $this->connection->query("SELECT COUNT(*) as count FROM users");
                $stats['users'] = $stmt->fetch()['count'];

                $stmt = $this->connection->query("SELECT COUNT(*) as count FROM assessments");
                $stats['assessments'] = $stmt->fetch()['count'];

                $stmt = $this->connection->query("SELECT COUNT(*) as count FROM sales");
                $stats['sales'] = $stmt->fetch()['count'];

                $stmt = $this->connection->query("SELECT COUNT(*) as count FROM contacts");
                $stats['contacts'] = $stmt->fetch()['count'];
            }

            return $stats;

        } catch (Exception $e) {
            return ['users' => 0, 'assessments' => 0, 'sales' => 0, 'contacts' => 0];
        }
    }

    public function getAssessments($search = '', $type = '', $status = '', $date_from = '', $date_to = '')
    {
        try {
            if ($this->useFileStorage) {
                // ... (existing file storage logic, omitted for brevity but should ideally be updated too)
                $file = $this->dataPath . 'assessments.json';
                if (file_exists($file)) {
                    $data = json_decode(file_get_contents($file), true);
                    return is_array($data) ? $data : [];
                }
                return [];
            } else {
                $sql = "SELECT * FROM assessments WHERE 1=1";
                $params = [];

                if ($search) {
                    $sql .= " AND (name LIKE :search OR email LIKE :search)";
                    $params[':search'] = "%$search%";
                }
                if ($type) {
                    $sql .= " AND assessment_type = :type"; // Note: using assessment_type column
                    $params[':type'] = $type;
                }
                if ($status) {
                    $sql .= " AND status = :status";
                    $params[':status'] = $status;
                }
                if ($date_from) {
                    $sql .= " AND created_at >= :date_from";
                    $params[':date_from'] = $date_from . ' 00:00:00';
                }
                if ($date_to) {
                    $sql .= " AND created_at <= :date_to";
                    $params[':date_to'] = $date_to . ' 23:59:59';
                }

                $sql .= " ORDER BY created_at DESC";

                $stmt = $this->connection->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            return [];
        }
    }

    public function getSales()
    {
        try {
            if ($this->useFileStorage) {
                $file = $this->dataPath . 'sales.json';
                if (file_exists($file)) {
                    $data = json_decode(file_get_contents($file), true);
                    return is_array($data) ? $data : [];
                }
                return [];
            } else {
                $stmt = $this->connection->query("SELECT * FROM sales ORDER BY created_at DESC");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            return [];
        }
    }

    public function getSalesCount($status = null, $type = null)
    {
        // Return count of sales data with optional filtering
        $sales = $this->getSales();

        // Filter by status if provided
        if ($status) {
            $sales = array_filter($sales, function ($sale) use ($status) {
                return ($sale['payment_status'] ?? 'pending') === $status;
            });
        }

        // Filter by product type if provided
        if ($type) {
            $sales = array_filter($sales, function ($sale) use ($type) {
                return ($sale['product_type'] ?? 'general') === $type;
            });
        }

        return count($sales);
    }

    public function getTotalRevenue($status = null)
    {
        // Calculate total revenue from sales data
        try {
            $sales = $this->getSales();
            $totalRevenue = 0;

            foreach ($sales as $sale) {
                // Only count completed/successful sales for revenue
                $paymentStatus = $sale['payment_status'] ?? 'pending';
                if ($status === null) {
                    // If no status filter, only count completed sales
                    if ($paymentStatus === 'completed' || $paymentStatus === 'successful') {
                        $totalRevenue += floatval($sale['amount'] ?? 0);
                    }
                } else {
                    // If status filter provided, count sales with that status
                    if ($paymentStatus === $status) {
                        $totalRevenue += floatval($sale['amount'] ?? 0);
                    }
                }
            }

            return $totalRevenue;
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getAssessmentCount($status = null, $type = null)
    {
        // Return count of assessments data with optional filtering
        $assessments = $this->getAssessments();

        // Filter by status if provided
        if ($status) {
            $assessments = array_filter($assessments, function ($assessment) use ($status) {
                return ($assessment['status'] ?? 'pending') === $status;
            });
        }

        // Filter by assessment type if provided
        if ($type) {
            $assessments = array_filter($assessments, function ($assessment) use ($type) {
                return ($assessment['assessment_type'] ?? 'general') === $type;
            });
        }

        return count($assessments);
    }

    public function getUserCount()
    {
        try {
            if ($this->useFileStorage) {
                $file = $this->dataPath . 'users.json';
                if (file_exists($file)) {
                    $data = json_decode(file_get_contents($file), true);
                    return is_array($data) ? count($data) : 0;
                }
                return 0;
            } else {
                $stmt = $this->connection->query("SELECT COUNT(*) as count FROM users");
                return $stmt->fetch()['count'];
            }
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getContactCount()
    {
        try {
            if ($this->useFileStorage) {
                $file = $this->dataPath . 'contacts.json';
                if (file_exists($file)) {
                    $data = json_decode(file_get_contents($file), true);
                    return is_array($data) ? count($data) : 0;
                }
                return 0;
            } else {
                $stmt = $this->connection->query("SELECT COUNT(*) as count FROM contacts");
                return $stmt->fetch()['count'];
            }
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getDailyAssessments($days = 7, $type = null)
    {
        try {
            $dates = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $dates[date('Y-m-d', strtotime("-$i days"))] = 0;
            }

            if ($this->useFileStorage) {
                $assessments = $this->getAssessments();
                foreach ($assessments as $assessment) {
                    if ($type && ($assessment['assessment_type'] ?? 'general') !== $type) {
                        continue;
                    }
                    $date = substr($assessment['created_at'], 0, 10);
                    if (isset($dates[$date])) {
                        $dates[$date]++;
                    }
                }
            } else {
                $isMySQL = $this->isMySQL();
                $isPostgres = $this->isPostgres();

                if ($isPostgres) {
                    $where = "created_at >= NOW() - INTERVAL '$days days'";
                } elseif ($isMySQL) {
                    $where = "created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                } else {
                    $where = "created_at >= date('now', '-' || ? || ' days')";
                }

                $params = [];
                if (!$isPostgres) {
                    $params = [$days];
                }

                if ($type) {
                    $where .= " AND assessment_type = ?";
                    $params[] = $type;
                }

                if ($isPostgres) {
                    $sql = "SELECT DATE(created_at) as date, COUNT(*) as count FROM assessments WHERE $where GROUP BY DATE(created_at)";
                } elseif ($isMySQL) {
                    $sql = "SELECT DATE(created_at) as date, COUNT(*) as count FROM assessments WHERE $where GROUP BY DATE(created_at)";
                } else {
                    $sql = "SELECT date(created_at) as date, COUNT(*) as count FROM assessments WHERE $where GROUP BY date(created_at)";
                }

                $stmt = $this->connection->prepare($sql);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                foreach ($results as $date => $count) {
                    if (isset($dates[$date])) {
                        $dates[$date] = (int) $count;
                    }
                }
            }

            return $dates;
        } catch (Exception $e) {
            return [];
        }
    }

    public function getDailySales($days = 7, $type = null)
    {
        try {
            $dates = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $dates[date('Y-m-d', strtotime("-$i days"))] = 0;
            }

            if ($this->useFileStorage) {
                $sales = $this->getSales();
                foreach ($sales as $sale) {
                    if (($sale['payment_status'] ?? 'pending') !== 'completed') {
                        continue;
                    }
                    if ($type && ($sale['product_type'] ?? 'general') !== $type) {
                        continue;
                    }
                    $date = substr($sale['created_at'], 0, 10);
                    if (isset($dates[$date])) {
                        $dates[$date]++;
                    }
                }
            } else {
                $isMySQL = $this->isMySQL();
                $isPostgres = $this->isPostgres();

                if ($isPostgres) {
                    $where = "created_at >= NOW() - INTERVAL '$days days' AND payment_status = 'completed'";
                } elseif ($isMySQL) {
                    $where = "created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND payment_status = 'completed'";
                } else {
                    $where = "created_at >= date('now', '-' || ? || ' days') AND payment_status = 'completed'";
                }

                $params = [];
                if (!$isPostgres) {
                    $params = [$days];
                }

                if ($type) {
                    $where .= " AND product_type = ?";
                    $params[] = $type;
                }

                if ($isPostgres) {
                    $sql = "SELECT DATE(created_at) as date, COUNT(*) as count FROM sales WHERE $where GROUP BY DATE(created_at)";
                } elseif ($isMySQL) {
                    $sql = "SELECT DATE(created_at) as date, COUNT(*) as count FROM sales WHERE $where GROUP BY DATE(created_at)";
                } else {
                    $sql = "SELECT date(created_at) as date, COUNT(*) as count FROM sales WHERE $where GROUP BY date(created_at)";
                }

                $stmt = $this->connection->prepare($sql);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                foreach ($results as $date => $count) {
                    if (isset($dates[$date])) {
                        $dates[$date] = (int) $count;
                    }
                }
            }

            return $dates;
        } catch (Exception $e) {
            return [];
        }
    }
}

// Mock result class for file-based queries
class FileQueryResult
{
    private $data;
    private $index = 0;

    public function __construct($data)
    {
        $this->data = is_array($data) ? $data : [];
    }

    public function fetch()
    {
        if ($this->index < count($this->data)) {
            return $this->data[$this->index++];
        }
        return false;
    }

    public function fetchAll()
    {
        return $this->data;
    }

    public function rowCount()
    {
        return count($this->data);
    }
}
class MockPDOStatement
{
    private $db;
    private $sql;
    private $result;

    public function __construct($db, $sql)
    {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute($params = [])
    {
        $this->result = $this->db->query($this->sql, $params);
        return $this->result !== false;
    }

    public function fetch()
    {
        return $this->result ? $this->result->fetch() : false;
    }

    public function fetchAll()
    {
        return $this->result ? $this->result->fetchAll() : [];
    }

    public function rowCount()
    {
        return $this->result ? $this->result->rowCount() : 0;
    }
}