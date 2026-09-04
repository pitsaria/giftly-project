<?php
// 1. DATABASE AND SESSION MUST BE FIRST
include 'db_connect.php';
include_once 'auth_lib.php';

if (isset($_POST['login'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Where to send the user back to on error (strip any query string)
    $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'index.php';
    $redirect_to = strtok($redirect_to, '?');
    if (empty($redirect_to)
        || $redirect_to === 'http://localhost/giftly_project/'
        || $redirect_to === 'http://localhost/') {
        $redirect_to = 'index.php';
    }

    // --- verify the password directly against the DB ---
    $email_esc = $conn->real_escape_string($email);
    $res  = $conn->query("SELECT * FROM users WHERE email = '$email_esc'");
    $user = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;

    if (!$user) {
        header("Location: " . $redirect_to . "?login_error=notfound");
        exit();
    }
    if (!password_verify($password, $user['password'])) {
        header("Location: " . $redirect_to . "?login_error=incorrect");
        exit();
    }

    // --- password OK ---
    // If we can send email, require a one-time code before completing sign-in.
    if (otp_enabled()) {
        auth_ensure_schema($conn);
        if (otp_start($conn, $user, $redirect_to)) {
            header("Location: " . $redirect_to . "?otp=1");
            exit();
        }
        // email couldn't be sent — don't lock the user out, fall through and log in
    }

    $_SESSION['user_id']           = $user['id'];
    $_SESSION['user_name']         = $user['name'];
    $_SESSION['user_email']        = $user['email'];
    $_SESSION['role']              = $user['role'];
    $_SESSION['fresh_login_modal'] = true;
    header("Location: index.php");
    exit();
}

// If this page is loaded directly (not from the modal), redirect to homepage
if (!isset($_GET['login_error'])) {
    header("Location: index.php");
    exit();
}

// 3. LOAD HEADER ONLY AFTER PHP LOGIC
include 'header.php';
?>
