<?php
include 'db_connect.php';
include_once 'catalog_lib.php';

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($product_id <= 0) {
    echo json_encode(['stock' => 0, 'max' => 0, 'cap' => catalog_max_per_order()]);
    exit();
}

// stock = what's on the shelf; max = most one order can take (per-order cap or stock, whichever is lower)
$query = $conn->query("SELECT quantity FROM products WHERE id = $product_id");
if ($query->num_rows > 0) {
    $row = $query->fetch_assoc();
    $stock = intval($row['quantity']);
    echo json_encode(['stock' => $stock, 'max' => catalog_order_limit($stock), 'cap' => catalog_max_per_order()]);
} else {
    echo json_encode(['stock' => 0, 'max' => 0, 'cap' => catalog_max_per_order()]);
}
?>
