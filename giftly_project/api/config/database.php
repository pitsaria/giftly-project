<?php
// api/config/database.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection (PostgreSQL, e.g. Render Postgres)
require_once __DIR__ . '/../../db_pg_compat.php';

$databaseUrl = getenv('DATABASE_URL');
$sslmode = getenv('DB_SSLMODE') ?: null;
if ($databaseUrl) {
    $parts = parse_url($databaseUrl);
    $host = $parts['host'];
    $port = $parts['port'] ?? 5432;
    $user = $parts['user'];
    $password = $parts['pass'] ?? '';
    $database = ltrim($parts['path'], '/');
    if (!$sslmode && !empty($parts['query'])) {
        parse_str($parts['query'], $query);
        $sslmode = $query['sslmode'] ?? null;
    }
} else {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: 5432;
    $user = getenv('DB_USER') ?: 'postgres';
    $password = getenv('DB_PASS') ?: '';
    $database = getenv('DB_NAME') ?: 'giftly_db';
}

$conn = new PgCompatMysqli($host, $user, $password, $database, $port, $sslmode);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

// Ensure products.product_type exists (Occasion Boxes / Baskets feature)
require_once __DIR__ . '/../../catalog_lib.php';
catalog_ensure_schema($conn);

// Function to send JSON response
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

// Function to send error response
function sendError($message, $status = 400) {
    sendResponse(['error' => $message, 'status' => 'error'], $status);
}

// Function to send success response
function sendSuccess($data = null, $message = 'Success') {
    sendResponse(['status' => 'success', 'message' => $message, 'data' => $data]);
}
?>