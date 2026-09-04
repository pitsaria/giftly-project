<?php
/** Abandon a pending sign-in OTP (the "use a different account" link). */
header('Content-Type: application/json');
include 'db_connect.php';

$uid = (int) ($_SESSION['pending_otp_user'] ?? 0);
if ($uid > 0) {
    $conn->query("DELETE FROM login_otps WHERE user_id = $uid");
}
foreach (['pending_otp_user','pending_otp_email','pending_otp_name','pending_otp_role','pending_otp_redirect','pending_otp_started'] as $k) {
    unset($_SESSION[$k]);
}
echo json_encode(['status' => 'ok']);
