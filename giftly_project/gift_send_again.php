<?php
/**
 * "Send again" from a past order — reuses that order's recipient name/phone
 * and delivery address as the active gift context, same as gift_start.php.
 */
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$order_id = (int) ($_GET['order_id'] ?? 0);

$o = $conn->query("SELECT * FROM orders WHERE id = $order_id AND user_id = $user_id");
$row = ($o && $o->num_rows > 0) ? $o->fetch_assoc() : null;

if ($row && !empty($row['recipient_name'])) {
    $_SESSION['gift_context'] = [
        'recipient_id'   => null,
        'name'           => $row['recipient_name'],
        'phone'          => $row['recipient_phone'],
        'house_no'       => '',
        'street'         => $row['address'],
        'city_line'      => $row['city'],
        'occasion_label' => '',
    ];
}

header('Location: shop.php');
exit();
