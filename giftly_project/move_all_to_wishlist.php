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
$cart_ids = isset($_POST['cart_ids']) ? $_POST['cart_ids'] : [];

// Handle both array and single value
if (!is_array($cart_ids)) {
    $cart_ids = [$cart_ids];
}

// Remove empty values and convert to int
$cart_ids = array_filter(array_map('intval', $cart_ids));

if (empty($cart_ids)) {
    echo json_encode(['success' => false, 'message' => 'No items selected']);
    exit();
}

// Check if wishlist table exists
$table_check = $conn->query("SHOW TABLES LIKE 'wishlist'");
if ($table_check->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Wishlist table does not exist. Please run the SQL to create it.']);
    exit();
}

$moved_count = 0;
$already_in_wishlist = 0;
$errors = [];

foreach ($cart_ids as $cart_id) {
    // Get product_id from cart
    $cart_check = $conn->query("SELECT product_id FROM carts WHERE id = $cart_id AND user_id = $user_id");
    
    if (!$cart_check || $cart_check->num_rows == 0) {
        $errors[] = "Cart item $cart_id not found";
        continue;
    }
    
    $cart_item = $cart_check->fetch_assoc();
    $product_id = $cart_item['product_id'];
    
    // Check if already in wishlist
    $wishlist_check = $conn->query("SELECT id FROM wishlist WHERE user_id = $user_id AND product_id = $product_id");
    
    if ($wishlist_check && $wishlist_check->num_rows > 0) {
        // Already in wishlist - just remove from cart
        $conn->query("DELETE FROM carts WHERE id = $cart_id AND user_id = $user_id");
        $already_in_wishlist++;
    } else {
        // Add to wishlist and remove from cart
        $insert = $conn->query("INSERT INTO wishlist (user_id, product_id) VALUES ($user_id, $product_id)");
        if ($insert) {
            $conn->query("DELETE FROM carts WHERE id = $cart_id AND user_id = $user_id");
            $moved_count++;
        } else {
            $errors[] = "Failed to add product $product_id to wishlist";
        }
    }
}

$message = "Moved $moved_count item(s) to wishlist ❤️";
if ($already_in_wishlist > 0) {
    $message .= " ($already_in_wishlist were already in your wishlist)";
}
if (!empty($errors)) {
    $message .= " Errors: " . implode(', ', $errors);
}

echo json_encode([
    'success' => true, 
    'message' => $message, 
    'moved' => $moved_count, 
    'already' => $already_in_wishlist,
    'errors' => $errors
]);
?>