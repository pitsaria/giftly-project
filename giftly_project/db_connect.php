<?php
// 1. Connect to database (PostgreSQL, e.g. Render Postgres)
require_once __DIR__ . '/db_pg_compat.php';

$databaseUrl = getenv('DATABASE_URL');
$sslmode = getenv('DB_SSLMODE') ?: null;
if ($databaseUrl) {
    $parts = parse_url($databaseUrl);
    $host = $parts['host'];
    $port = $parts['port'] ?? 5432;
    $user = $parts['user'];
    $pass = $parts['pass'] ?? '';
    $dbname = ltrim($parts['path'], '/');
    if (!$sslmode && !empty($parts['query'])) {
        parse_str($parts['query'], $query);
        $sslmode = $query['sslmode'] ?? null;
    }
} else {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: 5432;
    $user = getenv('DB_USER') ?: 'postgres';
    $pass = getenv('DB_PASS') ?: '';
    $dbname = getenv('DB_NAME') ?: 'giftly_db';
}

$conn = new PgCompatMysqli($host, $user, $pass, $dbname, $port, $sslmode);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Start Session to remember the user
session_start();

// 3. Set timezone (if not already set)
date_default_timezone_set('Asia/Manila');

// 4. Error reporting (for development - remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>