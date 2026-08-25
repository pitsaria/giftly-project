<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$user_id = $_SESSION['user_id'];
$product_ids = isset($_GET['product_ids']) ? $_GET['product_ids'] : '';

if (empty($product_ids)) {
    echo json_encode(['success' => true, 'wishlist' => []]);
    exit();
}

// Sanitize IDs
$ids_array = array_map('intval', explode(',', $product_ids));
$ids_string = implode(',', $ids_array);

$query = "SELECT product_id FROM wishlist WHERE user_id = $user_id AND product_id IN ($ids_string)";
$result = $conn->query($query);

$wishlist_ids = [];
while ($row = $result->fetch_assoc()) {
    $wishlist_ids[] = $row['product_id'];
}

echo json_encode(['success' => true, 'wishlist' => $wishlist_ids]);
?>