<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_GET['action']) && isset($_GET['id'])) {
    $cart_id = $_GET['id'];
    
    // Check if this cart item belongs to this user (security check)
    $check = $conn->query("SELECT * FROM carts WHERE id = $cart_id AND user_id = $user_id");
    if ($check->num_rows === 0) {
        header("Location: cart.php");
        exit();
    }

    if ($_GET['action'] == 'increase') {
        $conn->query("UPDATE carts SET quantity = quantity + 1 WHERE id = $cart_id");
    } 
    elseif ($_GET['action'] == 'decrease') {
        $conn->query("UPDATE carts SET quantity = quantity - 1 WHERE id = $cart_id");
        // If quantity drops to 0, remove it automatically
        $check_qty = $conn->query("SELECT quantity FROM carts WHERE id = $cart_id");
        $row = $check_qty->fetch_assoc();
        if ($row['quantity'] <= 0) {
            $conn->query("DELETE FROM carts WHERE id = $cart_id");
        }
    } 
    elseif ($_GET['action'] == 'delete') {
        $conn->query("DELETE FROM carts WHERE id = $cart_id");
    }
}

header("Location: cart.php");
exit();
?>