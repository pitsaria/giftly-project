<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = intval($_POST['product_id']);

// Check if product exists
$product_check = $conn->query("SELECT id FROM products WHERE id = $product_id");
if ($product_check->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit();
}

// Check if already in wishlist
$check = $conn->query("SELECT id FROM wishlist WHERE user_id = $user_id AND product_id = $product_id");

if ($check->num_rows > 0) {
    // Remove from wishlist
    $conn->query("DELETE FROM wishlist WHERE user_id = $user_id AND product_id = $product_id");
    echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Removed from wishlist']);
} else {
    // Add to wishlist
    $conn->query("INSERT INTO wishlist (user_id, product_id) VALUES ($user_id, $product_id)");
    echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Added to wishlist']);
}
?>