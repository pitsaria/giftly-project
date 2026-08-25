<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit();
}

$user_id = $_SESSION['user_id'];
$wishlist_id = intval($_POST['wishlist_id']);

$result = $conn->query("DELETE FROM wishlist WHERE id = $wishlist_id AND user_id = $user_id");

if ($conn->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Item not found or already removed']);
}
?>