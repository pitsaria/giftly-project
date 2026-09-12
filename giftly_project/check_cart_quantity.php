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

// A product can now have several cart rows (one per chosen color/size combo),
// so this needs to add them all up rather than reading a single row.
$query = $conn->query("SELECT COALESCE(SUM(quantity), 0) AS quantity FROM carts WHERE user_id = $user_id AND product_id = $product_id");
$row = $query ? $query->fetch_assoc() : null;
echo json_encode(['quantity' => $row ? intval($row['quantity']) : 0]);
?>