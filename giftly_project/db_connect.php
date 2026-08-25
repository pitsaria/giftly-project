<?php
// 1. Connect to database
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'giftly_db';

$conn = new mysqli($host, $user, $pass, $dbname);

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