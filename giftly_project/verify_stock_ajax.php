<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get selected cart IDs
if (!isset($_POST['items'])) {
    echo json_encode(['success' => false, 'error' => 'No items selected']);
    exit();
}

$cart_ids = array_map('intval', explode(',', $_POST['items']));
if (empty($cart_ids)) {
    echo json_encode(['success' => false, 'error' => 'No valid items']);
    exit();
}

$ids_string = implode(',', $cart_ids);

// Check stock for selected items
$query = "SELECT c.id as cart_id, c.quantity as requested, p.id as product_id, p.name, p.quantity as available_stock 
          FROM carts c 
          JOIN products p ON c.product_id = p.id 
          WHERE c.user_id = $user_id AND c.id IN ($ids_string)";

$result = $conn->query($query);

$stock_issues = [];
$items_to_update = [];
$can_proceed = true;

while ($row = $result->fetch_assoc()) {
    $cart_id = $row['cart_id'];
    $requested = intval($row['requested']);
    $available = intval($row['available_stock']);
    $product_name = $row['name'];
    
    if ($requested > $available) {
        $stock_issues[] = [
            'cart_id' => $cart_id,
            'product_name' => $product_name,
            'requested' => $requested,
            'available' => $available
        ];
        
        // If stock is 0 or less, we need to remove the item
        if ($available <= 0) {
            $items_to_update[] = "DELETE FROM carts WHERE id = $cart_id AND user_id = $user_id";
            // We'll need to notify user that item is out of stock
        } else {
            // Update quantity to available stock
            $items_to_update[] = "UPDATE carts SET quantity = $available WHERE id = $cart_id AND user_id = $user_id";
        }
        
        $can_proceed = false;
    }
}

// Execute updates if there were issues
if (!empty($items_to_update)) {
    foreach ($items_to_update as $update_query) {
        $conn->query($update_query);
    }
}

if ($can_proceed) {
    echo json_encode([
        'success' => true,
        'has_stock_issues' => false,
        'message' => 'All items are available.'
    ]);
} else {
    // Build a descriptive message
    $message_parts = [];
    foreach ($stock_issues as $issue) {
        if ($issue['available'] <= 0) {
            $message_parts[] = "<strong>{$issue['product_name']}</strong> is out of stock and has been removed from your cart.";
        } else {
            $message_parts[] = "<strong>{$issue['product_name']}</strong>: Requested {$issue['requested']}, only {$issue['available']} available. Quantity has been adjusted.";
        }
    }
    $message = "Some items in your cart were adjusted:<br><br>" . implode('<br>', $message_parts);
    
    echo json_encode([
        'success' => false,
        'has_stock_issues' => true,
        'message' => $message,
        'issues' => $stock_issues
    ]);
}
?>