<?php
// get_order_status.php
include 'db_connect.php'; // also starts the session

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
    exit();
}

$result = $conn->query("SELECT status FROM orders WHERE id = $order_id AND user_id = $user_id");
if ($result && $row = $result->fetch_assoc()) {
    echo json_encode(['status' => $row['status']]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
}
?>