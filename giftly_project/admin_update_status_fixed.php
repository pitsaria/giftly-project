<?php
include 'db_connect.php';

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

if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);

    // Delivered / cancelled orders are final.
    $cur = $conn->query("SELECT status FROM orders WHERE id = $order_id");
    $cur_status = $cur ? ($cur->fetch_assoc()['status'] ?? '') : '';
    if (in_array($cur_status, ['delivered', 'cancelled'], true)) {
        header("Location: admin_orders.php");
        exit();
    }

    $sql = "UPDATE orders SET status = '$new_status' WHERE id = $order_id";
    
    if ($conn->query($sql) === TRUE) {
        $_SESSION['order_updated'] = true;
        header("Location: admin_orders.php");
        exit();
    } else {
        echo "Database Error: " . $conn->error;
        exit();
    }
} else {
    header("Location: admin_orders.php");
    exit();
}
?>