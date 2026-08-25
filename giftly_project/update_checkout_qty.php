<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo "error";
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($cart_id === 0) {
    echo "error";
    exit();
}

// Security check: Ensure this cart item belongs to the user AND get stock info
$check = $conn->query("SELECT c.id, c.quantity, c.product_id, p.quantity as stock_quantity, p.price 
                       FROM carts c 
                       JOIN products p ON c.product_id = p.id 
                       WHERE c.id = $cart_id AND c.user_id = $user_id");

if ($check->num_rows === 0) {
    echo "error";
    exit();
}

$cart_item = $check->fetch_assoc();
$current_qty = intval($cart_item['quantity']);
$stock_available = intval($cart_item['stock_quantity']);
$unit_price = floatval($cart_item['price']);

if ($action === 'increase') {
    // Check if adding one would exceed stock
    if ($current_qty + 1 > $stock_available) {
        // Return error with stock info
        echo json_encode([
            'error' => 'Not enough stock available. Only ' . $stock_available . ' items left.'
        ]);
        exit();
    }
    $conn->query("UPDATE carts SET quantity = quantity + 1 WHERE id = $cart_id");
    
} elseif ($action === 'decrease') {
    $conn->query("UPDATE carts SET quantity = quantity - 1 WHERE id = $cart_id");
    // If quantity reaches 0, delete it
    $check_qty = $conn->query("SELECT quantity FROM carts WHERE id = $cart_id");
    $row = $check_qty->fetch_assoc();
    if ($row['quantity'] <= 0) {
        $conn->query("DELETE FROM carts WHERE id = $cart_id");
        echo "deleted";
        exit();
    }
} else {
    echo "error";
    exit();
}

// Return the new quantity and the product price so we can update the total
$row = $conn->query("SELECT c.quantity, p.price FROM carts c JOIN products p ON c.product_id = p.id WHERE c.id = $cart_id")->fetch_assoc();

if ($row) {
    $new_qty = intval($row['quantity']);
    $unit_price = floatval($row['price']);
    $new_subtotal = $new_qty * $unit_price;

    echo json_encode([
        'new_qty' => $new_qty,
        'new_subtotal' => number_format($new_subtotal, 2),
        'unit_price' => number_format($unit_price, 2)
    ]);
} else {
    // Item was deleted
    echo "deleted";
}
exit();
?>