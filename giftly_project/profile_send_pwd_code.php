<?php
/** AJAX: email a 6-digit code to confirm a password change (logged-in user). */
header('Content-Type: application/json');

include 'db_connect.php';
include_once 'auth_lib.php';
include_once 'pwd_otp_lib.php';
auth_ensure_schema($conn);
pwd_otp_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please sign in again.']);
    exit();
}
$uid = (int) $_SESSION['user_id'];

$row = $conn->query("SELECT email, google_id FROM users WHERE id = $uid");
$u = $row ? $row->fetch_assoc() : null;
if (!$u) {
    echo json_encode(['success' => false, 'message' => 'Account not found.']);
    exit();
}
if (!empty($u['google_id'])) {
    echo json_encode(['success' => false, 'message' => 'This account signs in with Google and has no password to change.']);
    exit();
}

$wait = pwd_otp_seconds_until_resend($conn, $uid, 'change');
if ($wait > 0) {
    echo json_encode(['success' => false, 'retry_after' => $wait,
        'message' => "Please wait {$wait}s before requesting a new code."]);
    exit();
}

[$sent, $err] = pwd_otp_send($conn, $uid, $u['email'], 'change');
echo json_encode($sent
    ? ['success' => true, 'retry_after' => pwd_otp_cooldown(),
       'message' => 'We emailed a 6-digit code to ' . $u['email'] . '.']
    : ['success' => false, 'message' => $err ?: "Couldn't send the code right now."]);
