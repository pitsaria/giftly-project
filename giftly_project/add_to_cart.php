<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'];

// First, get the current stock of the product
$product_query = $conn->query("SELECT quantity FROM products WHERE id = $product_id");
if ($product_query->num_rows == 0) {
    // Product doesn't exist
    header("Location: shop.php?error=product_not_found");
    exit();
}

$product = $product_query->fetch_assoc();
$available_stock = $product['quantity'];

// Check if the product is already in the cart
$check = $conn->query("SELECT * FROM carts WHERE user_id = $user_id AND product_id = $product_id");

if ($check->num_rows > 0) {
    // Product exists in cart - get current quantity
    $cart_item = $check->fetch_assoc();
    $current_cart_qty = $cart_item['quantity'];
    
    // Check if adding one more would exceed stock
    if ($current_cart_qty + 1 > $available_stock) {
        // Not enough stock available
        $error_message = urlencode("Sorry, only $available_stock item(s) available in stock.");
        header("Location: shop.php?error=$error_message");
        exit();
    }
    
    // Update quantity
    $conn->query("UPDATE carts SET quantity = quantity + 1 WHERE user_id = $user_id AND product_id = $product_id");
} else {
    // Product not in cart yet - check if at least 1 is available
    if ($available_stock < 1) {
        $error_message = urlencode("Sorry, this product is out of stock.");
        header("Location: shop.php?error=$error_message");
        exit();
    }
    
    // Insert new cart item
    $conn->query("INSERT INTO carts (user_id, product_id, quantity) VALUES ($user_id, $product_id, 1)");
}

// Get updated cart count
$cart_count = $conn->query("SELECT SUM(quantity) as total FROM carts WHERE user_id = $user_id");
$count = $cart_count->fetch_assoc();
$new_total = $count['total'] ?? 0;

// Redirect back to shop with success message
header("Location: shop.php?success=added");
exit();
?>