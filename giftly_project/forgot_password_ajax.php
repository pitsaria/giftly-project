<?php
/** AJAX: step 1 of the password reset — email a 6-digit code. */
header('Content-Type: application/json');

include 'db_connect.php';
include_once 'auth_lib.php';
include_once 'pwd_otp_lib.php';
auth_ensure_schema($conn);
pwd_otp_ensure_schema($conn);

$response = ['success' => false, 'message' => ''];

$email = trim($_POST['email'] ?? '');
if ($email === '') {
    $response['message'] = 'Please enter your email address.';
    echo json_encode($response);
    exit();
}

$email_esc = $conn->real_escape_string($email);
$res  = $conn->query("SELECT id, email, google_id FROM users WHERE email = '$email_esc'");
$user = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;

if (!$user) {
    $response['message'] = 'Email address not found in our system.';
    echo json_encode($response);
    exit();
}

// Google-only accounts have a random password hash they never set — steer them
// to "Sign in with Google" instead of resetting a password they don't use.
if (!empty($user['google_id'])) {
    $response['message'] = 'This account uses "Sign in with Google". Use that button to sign in.';
    echo json_encode($response);
    exit();
}

[$sent, $err] = pwd_otp_send($conn, (int) $user['id'], $user['email'], 'reset');
if (!$sent) {
    $response['message'] = $err ?: "Couldn't send the code right now. Try again shortly.";
    echo json_encode($response);
    exit();
}

// Remember who we're resetting; the code itself lives (hashed) in the DB.
$_SESSION['pwd_reset_uid']      = (int) $user['id'];
$_SESSION['pwd_reset_email']    = $user['email'];
unset($_SESSION['pwd_reset_verified'], $_SESSION['pwd_reset_verified_at']);

$response['success']  = true;
$response['message']  = 'We emailed a 6-digit code to ' . $email . '.';
$response['cooldown'] = pwd_otp_cooldown();
echo json_encode($response);
