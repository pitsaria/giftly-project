<?php
/** AJAX: step 3 of the password reset — set the new password.
 *  Requires a code that was emailed and verified in this same session. */
header('Content-Type: application/json');

include 'db_connect.php';
include_once 'pwd_otp_lib.php';
pwd_otp_ensure_schema($conn);

$response = ['success' => false, 'message' => ''];

$uid = (int) ($_SESSION['pwd_reset_uid'] ?? 0);
if ($uid <= 0 || empty($_SESSION['pwd_reset_verified'])) {
    $response['message'] = 'Please verify the emailed code first.';
    echo json_encode($response);
    exit();
}

// The verified code must still be on file and unexpired.
if (!pwd_otp_is_verified($conn, $uid, 'reset')) {
    unset($_SESSION['pwd_reset_verified']);
    $response['message'] = 'Your code expired. Please request a new one.';
    echo json_encode($response);
    exit();
}

$new_pass = (string) ($_POST['password'] ?? '');
$confirm  = (string) ($_POST['confirm_password'] ?? $new_pass);
if ($new_pass !== $confirm) {
    $response['message'] = 'The two passwords do not match.';
    echo json_encode($response);
    exit();
}

[$strong, $serr] = pwd_strength_check($new_pass);
if (!$strong) {
    $response['message'] = $serr;
    echo json_encode($response);
    exit();
}

$hashed = $conn->real_escape_string(password_hash($new_pass, PASSWORD_DEFAULT));
$conn->query("UPDATE users SET password = '$hashed', reset_token = NULL, token_expiry = NULL WHERE id = $uid");

pwd_otp_clear($conn, $uid, 'reset');
unset($_SESSION['pwd_reset_uid'], $_SESSION['pwd_reset_email'],
      $_SESSION['pwd_reset_verified'], $_SESSION['pwd_reset_verified_at']);

$response['success'] = true;
$response['message'] = 'Password updated. You can now sign in.';
echo json_encode($response);
