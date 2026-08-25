<?php
// add_to_cart_ajax.php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit();
}

// Check if product exists and has stock
$product_check = $conn->query("SELECT id, quantity FROM products WHERE id = $product_id");
if ($product_check->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit();
}

$product = $product_check->fetch_assoc();
if ($product['quantity'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'Product out of stock']);
    exit();
}

// Check if item already in cart
$cart_check = $conn->query("SELECT id, quantity FROM carts WHERE user_id = $user_id AND product_id = $product_id");

if ($cart_check->num_rows > 0) {
    // Update existing cart item
    $cart_item = $cart_check->fetch_assoc();
    $new_qty = $cart_item['quantity'] + 1;
    $conn->query("UPDATE carts SET quantity = $new_qty WHERE id = {$cart_item['id']}");
} else {
    // Add new cart item
    $conn->query("INSERT INTO carts (user_id, product_id, quantity) VALUES ($user_id, $product_id, 1)");
}

// Get updated cart count
$count_result = $conn->query("SELECT SUM(quantity) as total FROM carts WHERE user_id = $user_id");
$count_row = $count_result->fetch_assoc();
$total_items = $count_row['total'] ?? 0;

echo json_encode([
    'success' => true,
    'message' => 'Added to cart',
    'cart_count' => $total_items
]);
?>