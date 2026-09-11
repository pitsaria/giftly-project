<?php
/**
 * One-click "Send a gift to X" — stashes the recipient's saved info in the
 * session so shop.php/cart.php/checkout can pre-fill delivery details, then
 * sends the shopper to the shop to pick something.
 */
include 'db_connect.php';
include_once 'recipients_lib.php';
recip_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$recipient_id = (int) ($_GET['recipient_id'] ?? 0);
$r = recip_get($conn, $recipient_id, $user_id);

if ($r) {
    $label = '';
    if (!empty($_GET['occasion_id'])) {
        $oc_id = (int) $_GET['occasion_id'];
        $oc = $conn->query("SELECT * FROM recipient_occasions WHERE id = $oc_id AND recipient_id = " . (int) $r['id']);
        if ($oc && $oc->num_rows > 0) {
            $label = recip_occasion_label($oc->fetch_assoc());
        }
    }
    $_SESSION['gift_context'] = [
        'recipient_id'    => (int) $r['id'],
        'name'            => $r['name'],
        'phone'           => $r['phone'],
        'house_no'        => $r['house_no'],
        'street'          => $r['street'],
        'city_line'       => $r['city_line'],
        'occasion_label'  => $label,
    ];
}

header('Location: shop.php');
exit();
