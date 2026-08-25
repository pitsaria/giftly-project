<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['place_order'])) {
    header("Location: cart.php");
    exit();
}

$selected_ids = explode(',', $_POST['selected_ids_hidden']);
if (empty($selected_ids)) {
    header("Location: cart.php");
    exit();
}

$ids_string = implode(',', array_map('intval', $selected_ids));

// 🔒 START TRANSACTION - This prevents race conditions
$conn->begin_transaction();

try {
    // 1. LOCK the products table and check stock
    $stock_check = $conn->query("SELECT c.id as cart_id, c.quantity as requested, p.id as product_id, p.name, p.quantity as available_stock 
                                FROM carts c 
                                JOIN products p ON c.product_id = p.id 
                                WHERE c.user_id = $user_id AND c.id IN ($ids_string)
                                FOR UPDATE");  // 🔒 LOCK ROWS
    
    $items = [];
    $has_stock_issues = false;
    $stock_errors = [];
    
    while ($row = $stock_check->fetch_assoc()) {
        $requested = intval($row['requested']);
        $available = intval($row['available_stock']);
        
        if ($requested > $available) {
            $has_stock_issues = true;
            if ($available <= 0) {
                $stock_errors[] = "{$row['name']} is out of stock.";
            } else {
                $stock_errors[] = "{$row['name']}: Only {$available} available, but you requested {$requested}.";
            }
        } else {
            $items[] = $row;
        }
    }
    
    // 2. If there are stock issues, rollback and show error
    if ($has_stock_issues) {
        $conn->rollback();
        $_SESSION['stock_errors'] = $stock_errors;
        header("Location: cart.php?stock_error=1");
        exit();
    }
    
    // 3. Calculate total
    $total_amount = 0;
    foreach ($items as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }
    
    // 4. Insert order
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $sender_phone = mysqli_real_escape_string($conn, $_POST['sender_phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $payment = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $delivery_date = mysqli_real_escape_string($conn, $_POST['delivery_date']);
    $delivery_time = mysqli_real_escape_string($conn, $_POST['delivery_time']);
    $gift_message = isset($_POST['gift_message']) ? mysqli_real_escape_string($conn, $_POST['gift_message']) : '';
    $delivery_type = isset($_POST['delivery_type']) ? $_POST['delivery_type'] : 'me';
    
    if ($delivery_type == 'recipient') {
        $recipient = isset($_POST['recipient_name']) ? mysqli_real_escape_string($conn, $_POST['recipient_name']) : NULL;
        $recipient_phone = isset($_POST['recipient_phone']) ? mysqli_real_escape_string($conn, $_POST['recipient_phone']) : NULL;
    } else {
        $recipient = NULL;
        $recipient_phone = NULL;
    }
    
    $shipping_fee = ($total_amount > 0 && $total_amount < 300) ? 50 : 0;
    $grand_total = $total_amount + $shipping_fee;
    
    $sql = "INSERT INTO orders (user_id, total_amount, status, fullname, sender_phone, address, city, recipient_name, recipient_phone, gift_message, payment_method, delivery_date, delivery_time) 
            VALUES ($user_id, $grand_total, 'pending', '$fullname', '$sender_phone', '$address', '$city', '$recipient', '$recipient_phone', '$gift_message', '$payment', '$delivery_date', '$delivery_time')";
    
    if (!$conn->query($sql)) {
        throw new Exception("Failed to create order: " . $conn->error);
    }
    
    $order_id = $conn->insert_id;
    
    // 5. Insert order items and update stock
    foreach ($items as $item) {
        // Insert order item
        $conn->query("INSERT INTO order_items (order_id, product_id, quantity, price) 
                      VALUES ($order_id, {$item['product_id']}, {$item['quantity']}, {$item['price']})");
        
        // 🔒 Decrease stock (this is safe because we locked the rows)
        $conn->query("UPDATE products SET quantity = quantity - {$item['quantity']} WHERE id = {$item['product_id']}");
    }
    
    // 6. Delete cart items
    $conn->query("DELETE FROM carts WHERE user_id = $user_id AND id IN ($ids_string)");
    
    // 7. Commit transaction
    $conn->commit();
    
    // 8. Redirect to success page
    header("Location: order_success.php?order_id=" . $order_id);
    exit();
    
} catch (Exception $e) {
    // 🔒 Rollback on error
    $conn->rollback();
    error_log("Order processing error: " . $e->getMessage());
    die("An error occurred while processing your order. Please try again.");
}
?>