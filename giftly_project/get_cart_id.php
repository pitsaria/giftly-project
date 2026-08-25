<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo "0";
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if($product_id > 0) {
    // Find the cart ID for this user and product
    $result = $conn->query("SELECT id FROM carts WHERE user_id = $user_id AND product_id = $product_id LIMIT 1");
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo $row['id']; // Return the actual cart ID
    } else {
        echo "0";
    }
} else {
    echo "0";
}
?>