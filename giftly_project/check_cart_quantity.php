<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['quantity' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($product_id <= 0) {
    echo json_encode(['quantity' => 0]);
    exit();
}

$query = $conn->query("SELECT quantity FROM carts WHERE user_id = $user_id AND product_id = $product_id");
if ($query->num_rows > 0) {
    $row = $query->fetch_assoc();
    echo json_encode(['quantity' => intval($row['quantity'])]);
} else {
    echo json_encode(['quantity' => 0]);
}
?>