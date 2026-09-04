<?php
/** AJAX: verify the 6-digit sign-in code and finish logging the user in. */
header('Content-Type: application/json');

include 'db_connect.php';
include 'auth_lib.php';
auth_ensure_schema($conn);

$code = $_POST['code'] ?? '';
[$uid, $err] = otp_verify($conn, $code);

if ($uid <= 0) {
    echo json_encode(['status' => 'error', 'message' => $err]);
    exit();
}

$r = $conn->query("SELECT id, name, email, role FROM users WHERE id = " . (int) $uid);
$u = $r ? $r->fetch_assoc() : null;
if (!$u) {
    echo json_encode(['status' => 'error', 'message' => 'Account not found.']);
    exit();
}

$_SESSION['user_id']          = $u['id'];
$_SESSION['user_name']        = $u['name'];
$_SESSION['user_email']       = $u['email'];
$_SESSION['role']             = $u['role'];
$_SESSION['fresh_login_modal'] = true;

echo json_encode(['status' => 'success', 'redirect' => 'index.php']);
