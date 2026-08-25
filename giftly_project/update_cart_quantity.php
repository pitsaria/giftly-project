<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['items'])) {
    echo json_encode(['success' => false, 'error' => 'No items provided']);
    exit();
}

$updates = [];
$response = ['success' => true, 'updated' => [], 'errors' => []];

foreach ($data['items'] as $item) {
    $cart_id = intval($item['cart_id']);
    $new_qty = intval($item['quantity']);
    
    // Verify the cart item belongs to this user
    $verify = $conn->query("SELECT c.id, p.quantity as stock FROM carts c JOIN products p ON c.product_id = p.id WHERE c.id = $cart_id AND c.user_id = $user_id");
    
    if ($verify->num_rows == 0) {
        $response['errors'][] = "Cart item $cart_id not found";
        continue;
    }
    
    $row = $verify->fetch_assoc();
    $max_stock = intval($row['stock']);
    
    // Ensure quantity doesn't exceed stock
    $final_qty = min($new_qty, $max_stock);
    
    if ($final_qty <= 0) {
        // Delete the item
        $conn->query("DELETE FROM carts WHERE id = $cart_id AND user_id = $user_id");
        $response['updated'][] = ['cart_id' => $cart_id, 'new_quantity' => 0, 'deleted' => true];
    } else {
        $conn->query("UPDATE carts SET quantity = $final_qty WHERE id = $cart_id AND user_id = $user_id");
        $response['updated'][] = ['cart_id' => $cart_id, 'new_quantity' => $final_qty];
    }
}

echo json_encode($response);
?>