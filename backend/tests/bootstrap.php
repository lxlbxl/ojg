<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Mock some environment details for tests
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');

// Define some constants that might be needed by the backend
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
