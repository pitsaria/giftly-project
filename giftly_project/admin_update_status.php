<?php
include 'db_connect.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$user_check = $conn->query("SELECT role FROM users WHERE id = $user_id");
$user_data = $user_check->fetch_assoc();
if ($user_data['role'] !== 'admin') {
    header("Location: shop.php");
    exit();
}

// Check if the form was actually submitted
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    
    $order_id = intval($_POST['order_id']); // Force it to be a safe integer
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);

    // A delivered / cancelled order is final; an unpaid online order can't be progressed.
    $cur = $conn->query("SELECT status, payment_method, payment_status FROM orders WHERE id = $order_id");
    $cur_row = $cur ? $cur->fetch_assoc() : [];
    if (in_array($cur_row['status'] ?? '', ['delivered', 'cancelled'], true)) {
        $_SESSION['order_update_error'] = 'This order is already ' . $cur_row['status'] . ' — its status can no longer be changed.';
        header("Location: admin_orders.php");
        exit();
    }
    if (($cur_row['payment_method'] ?? 'cod') !== 'cod' && ($cur_row['payment_status'] ?? 'unpaid') !== 'paid') {
        $_SESSION['order_update_error'] = 'This order is still awaiting payment.';
        header("Location: admin_orders.php");
        exit();
    }

    // Run the update
    $sql = "UPDATE orders SET status = '$new_status' WHERE id = $order_id";

    if ($conn->query($sql) === TRUE) {
        // Set the success flag
        $_SESSION['order_updated'] = true;
        // Redirect back to the admin page
        header("Location: admin_orders.php");
        exit();
    } else {
        // If there's a database error, show it directly (so you know what's wrong)
        die("Database Error: " . $conn->error);
    }
} else {
    // If someone accesses this file directly without submitting the form
    header("Location: admin_orders.php");
    exit();
}
?>