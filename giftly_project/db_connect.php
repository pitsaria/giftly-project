<?php
// 1. Connect to database (PostgreSQL, e.g. Render Postgres)
require_once __DIR__ . '/db_pg_compat.php';

// Image storage helper (Supabase Storage + img_url()). No DB dependency; loaded
// here so img_url() is available on every page that has a DB connection.
require_once __DIR__ . '/supabase_storage.php';

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

// Formats a TIMESTAMP column Postgres stores as UTC wall-clock time (every
// `created_at DEFAULT CURRENT_TIMESTAMP` column, plus paid_at/received_at/etc.)
// as Philippine local time for display. Plain strtotime()+date() silently
// mis-reads these: date_default_timezone_set('Asia/Manila') above makes
// strtotime() treat the raw UTC string as if it were ALREADY Manila time,
// so it just echoes the UTC value back unconverted (an 11:05 AM order was
// showing as 3:05 AM — 11:05 AM PHT is 3:05 AM UTC).
if (!function_exists('ph_datetime')) {
    function ph_datetime($utc_value, $format) {
        if (!$utc_value) return '';
        try {
            $dt = new DateTime($utc_value, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Asia/Manila'));
            return $dt->format($format);
        } catch (Exception $e) {
            return '';
        }
    }
}

// 4. Error reporting (for development - remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>