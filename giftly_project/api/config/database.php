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

// Database connection
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'giftly_db';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

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