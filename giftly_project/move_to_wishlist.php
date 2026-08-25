<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php';

// Set response header
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;

if ($cart_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart ID']);
    exit();
}

// Check if cart item exists
$cart_check = $conn->query("SELECT product_id FROM carts WHERE id = $cart_id AND user_id = $user_id");

if (!$cart_check) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit();
}

if ($cart_check->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Cart item not found']);
    exit();
}

$cart_item = $cart_check->fetch_assoc();
$product_id = $cart_item['product_id'];

// Check if wishlist table exists
$table_check = $conn->query("SHOW TABLES LIKE 'wishlist'");
if ($table_check->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Wishlist table does not exist. Please run the SQL to create it.']);
    exit();
}

// Check if product is already in wishlist
$wishlist_check = $conn->query("SELECT id FROM wishlist WHERE user_id = $user_id AND product_id = $product_id");

if ($wishlist_check && $wishlist_check->num_rows > 0) {
    // Already in wishlist - just remove from cart
    $conn->query("DELETE FROM carts WHERE id = $cart_id AND user_id = $user_id");
    echo json_encode(['success' => true, 'message' => 'Item already in wishlist. Removed from cart.']);
} else {
    // Add to wishlist and remove from cart
    $insert = $conn->query("INSERT INTO wishlist (user_id, product_id) VALUES ($user_id, $product_id)");
    if ($insert) {
        $conn->query("DELETE FROM carts WHERE id = $cart_id AND user_id = $user_id");
        echo json_encode(['success' => true, 'message' => 'Moved to wishlist! ❤️']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add to wishlist: ' . $conn->error]);
    }
}
?>