<?php
include 'db_connect.php';

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($product_id <= 0) {
    echo json_encode(['stock' => 0]);
    exit();
}

$query = $conn->query("SELECT quantity FROM products WHERE id = $product_id");
if ($query->num_rows > 0) {
    $row = $query->fetch_assoc();
    echo json_encode(['stock' => intval($row['quantity'])]);
} else {
    echo json_encode(['stock' => 0]);
}
?>