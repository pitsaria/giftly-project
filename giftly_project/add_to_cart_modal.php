<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo "login_required";
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
$replace = isset($_POST['replace']) ? $_POST['replace'] : 0;

if ($product_id <= 0) {
    echo "error";
    exit();
}

// Get product stock
$product_query = $conn->query("SELECT quantity FROM products WHERE id = $product_id");
if ($product_query->num_rows == 0) {
    echo "error";
    exit();
}
$product = $product_query->fetch_assoc();
$available_stock = intval($product['quantity']);

// Check if product is already in cart
$cart_query = $conn->query("SELECT quantity FROM carts WHERE user_id = $user_id AND product_id = $product_id");
$current_cart_qty = 0;
if ($cart_query->num_rows > 0) {
    $cart_item = $cart_query->fetch_assoc();
    $current_cart_qty = intval($cart_item['quantity']);
}

// Check if product is out of stock
if ($available_stock <= 0) {
    echo "out_of_stock";
    exit();
}

if ($replace == 1) {
    // Replace mode - clear cart and add new quantity
    $conn->query("DELETE FROM carts WHERE user_id = $user_id AND product_id = $product_id");
    $new_qty = min($quantity, $available_stock);
    if ($new_qty > 0) {
        $conn->query("INSERT INTO carts (user_id, product_id, quantity) VALUES ($user_id, $product_id, $new_qty)");
    }
    echo "success";
    exit();
}

// Check if adding would exceed available stock
$total_after_add = $current_cart_qty + $quantity;
if ($total_after_add > $available_stock) {
    // Return the stock limit with a specific message
    echo json_encode([
        'error' => 'stock_limit',
        'message' => 'You\'ve reached the maximum available stock for this product. Only ' . $available_stock . ' items available.',
        'max_stock' => $available_stock
    ]);
    exit();
}

// Add to cart
if ($current_cart_qty > 0) {
    $conn->query("UPDATE carts SET quantity = quantity + $quantity WHERE user_id = $user_id AND product_id = $product_id");
} else {
    $conn->query("INSERT INTO carts (user_id, product_id, quantity) VALUES ($user_id, $product_id, $quantity)");
}

echo "success";
?>