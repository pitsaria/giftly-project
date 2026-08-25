<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_id = intval($_POST['cart_id']);
$action = $_POST['action'];

// Security check - get cart item and product stock
$check = $conn->query("SELECT c.id, c.quantity, c.product_id, p.quantity as stock_quantity 
                       FROM carts c 
                       JOIN products p ON c.product_id = p.id 
                       WHERE c.id = $cart_id AND c.user_id = $user_id");

if ($check->num_rows == 0) {
    echo json_encode(['success' => false]);
    exit();
}

$cart_item = $check->fetch_assoc();
$current_qty = intval($cart_item['quantity']);
$stock_available = intval($cart_item['stock_quantity']);

if ($action == 'increase') {
    // Check if adding one would exceed stock
    if ($current_qty + 1 > $stock_available) {
        echo json_encode([
            'success' => false, 
            'error' => 'Not enough stock available. Only ' . $stock_available . ' items left.'
        ]);
        exit();
    }
    $conn->query("UPDATE carts SET quantity = quantity + 1 WHERE id = $cart_id");
    
} elseif ($action == 'decrease') {
    // Check if quantity would go below 1
    if ($current_qty <= 1) {
        // Remove the item if quantity would be 0
        $conn->query("DELETE FROM carts WHERE id = $cart_id");
        echo json_encode([
            'success' => true,
            'new_qty' => 0,
            'new_subtotal' => '0.00'
        ]);
        exit();
    }
    $conn->query("UPDATE carts SET quantity = quantity - 1 WHERE id = $cart_id");
    
} elseif ($action == 'delete') {
    $conn->query("DELETE FROM carts WHERE id = $cart_id");
    echo json_encode([
        'success' => true,
        'new_qty' => 0,
        'new_subtotal' => '0.00'
    ]);
    exit();
}

// Get new quantity and subtotal
$row = $conn->query("SELECT c.quantity, p.price FROM carts c JOIN products p ON c.product_id = p.id WHERE c.id = $cart_id")->fetch_assoc();

if ($row) {
    $new_qty = intval($row['quantity']);
    $new_subtotal = $new_qty * floatval($row['price']);
    
    echo json_encode([
        'success' => true,
        'new_qty' => $new_qty,
        'new_subtotal' => number_format($new_subtotal, 2)
    ]);
} else {
    // Item was deleted
    echo json_encode([
        'success' => true,
        'new_qty' => 0,
        'new_subtotal' => '0.00'
    ]);
}
?>