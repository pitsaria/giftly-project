<?php
/** AJAX: step 2 of the password reset — verify the emailed code. */
header('Content-Type: application/json');

include 'db_connect.php';
include_once 'pwd_otp_lib.php';
pwd_otp_ensure_schema($conn);

$response = ['success' => false, 'message' => ''];

$uid = (int) ($_SESSION['pwd_reset_uid'] ?? 0);
if ($uid <= 0) {
    $response['message'] = 'Your reset session expired. Please request a new code.';
    echo json_encode($response);
    exit();
}

// Resend path: re-uses the same email, respects the cooldown.
if (isset($_POST['resend'])) {
    $wait = pwd_otp_seconds_until_resend($conn, $uid, 'reset');
    if ($wait > 0) {
        $response['message']     = "Please wait {$wait}s before requesting a new code.";
        $response['retry_after'] = $wait;
        echo json_encode($response);
        exit();
    }
    [$sent, $err] = pwd_otp_send($conn, $uid, $_SESSION['pwd_reset_email'] ?? '', 'reset');
    $response['success']     = (bool) $sent;
    $response['message']     = $sent ? 'A new code is on its way.' : ($err ?: "Couldn't send the code.");
    $response['retry_after'] = pwd_otp_cooldown();
    echo json_encode($response);
    exit();
}

[$ok, $err] = pwd_otp_verify($conn, $uid, $_POST['code'] ?? '', 'reset');
if (!$ok) {
    $response['message'] = $err;
    echo json_encode($response);
    exit();
}

$_SESSION['pwd_reset_verified']    = true;
$_SESSION['pwd_reset_verified_at'] = time();

$response['success'] = true;
$response['message'] = 'Code verified. Choose a new password.';
echo json_encode($response);
