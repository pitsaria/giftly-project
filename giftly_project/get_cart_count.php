<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT SUM(quantity) as total FROM carts WHERE user_id = $user_id";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$count = $row['total'] ?? 0;

echo json_encode(['count' => $count]);
?>