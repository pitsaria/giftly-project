<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_ids = isset($_POST['cart_ids']) ? $_POST['cart_ids'] : [];

if (empty($cart_ids)) {
    echo json_encode(['success' => false, 'message' => 'No items selected']);
    exit();
}

$ids_string = implode(',', array_map('intval', $cart_ids));

// Get product_ids from cart and check if in wishlist
$query = "SELECT c.id as cart_id, c.product_id, p.name 
          FROM carts c 
          JOIN products p ON c.product_id = p.id 
          WHERE c.id IN ($ids_string) AND c.user_id = $user_id";

$result = $conn->query($query);

$already_in_wishlist = [];
$not_in_wishlist = [];

while ($row = $result->fetch_assoc()) {
    $check = $conn->query("SELECT id FROM wishlist WHERE user_id = $user_id AND product_id = {$row['product_id']}");
    if ($check->num_rows > 0) {
        $already_in_wishlist[] = $row;
    } else {
        $not_in_wishlist[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'already_in_wishlist' => $already_in_wishlist,
    'not_in_wishlist' => $not_in_wishlist
]);
?>