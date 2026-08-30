<?php
/**
 * Retry / complete payment for an existing unpaid online order.
 * Linked from "Awaiting payment — pay now" in the customer's order list.
 */
include 'db_connect.php';
include 'paymongo_lib.php';
pay_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$uid = (int) $_SESSION['user_id'];
$oid = (int) ($_GET['order_id'] ?? 0);

$r = $oid > 0 ? $conn->query("SELECT * FROM orders WHERE id = $oid AND user_id = $uid") : null;
$order = $r ? $r->fetch_assoc() : null;

if (!$order
    || $order['payment_status'] === 'paid'
    || $order['status'] === 'cancelled'
    || $order['payment_method'] === 'cod'
    || !paymongo_configured()) {
    header('Location: profile.php?tab=orders');
    exit();
}

$url = paymongo_create_checkout(
    $conn, $oid, (float) $order['total_amount'],
    $order['fullname'] ?? '', ($_SESSION['user_email'] ?? ''), $order['sender_phone'] ?? ''
);

if ($url === '') {
    $_SESSION['pay_start_error'] = paymongo_last_error() ?: 'Payment could not be started.';
}
header('Location: ' . ($url !== '' ? $url : 'payment_return.php?order_id=' . $oid));
exit();
