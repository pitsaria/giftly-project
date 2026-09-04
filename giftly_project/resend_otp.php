<?php
/** AJAX: resend the sign-in code for the pending OTP session. */
header('Content-Type: application/json');

include 'db_connect.php';
include 'auth_lib.php';
auth_ensure_schema($conn);

$uid = (int) ($_SESSION['pending_otp_user'] ?? 0);
if ($uid <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Your sign-in session expired. Please log in again.']);
    exit();
}

// light throttle: one resend per 30s
if (!empty($_SESSION['pending_otp_started']) && time() - (int) $_SESSION['pending_otp_started'] < 30) {
    echo json_encode(['status' => 'error', 'message' => 'Please wait a few seconds before requesting another code.']);
    exit();
}

$r = $conn->query("SELECT id, name, email, role FROM users WHERE id = $uid");
$u = $r ? $r->fetch_assoc() : null;
if (!$u) {
    echo json_encode(['status' => 'error', 'message' => 'Account not found.']);
    exit();
}

$sent = otp_start($conn, $u, $_SESSION['pending_otp_redirect'] ?? 'index.php');
echo json_encode($sent
    ? ['status' => 'success', 'message' => 'A new code is on its way.']
    : ['status' => 'error', 'message' => "Couldn't send the code right now. Try again shortly."]);
