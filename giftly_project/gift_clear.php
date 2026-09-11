<?php
/** Clears the active "shopping for X" gift context, then returns the shopper to where they were. */
include 'db_connect.php';
unset($_SESSION['gift_context']);

$back = $_GET['back'] ?? 'shop.php';
if (!preg_match('/^[a-zA-Z0-9_\-]+\.php(\?[a-zA-Z0-9_=&\-\.%]*)?$/', $back)) {
    $back = 'shop.php';
}
header('Location: ' . $back);
exit();
