<?php
/**
 * AJAX endpoint: "Sign in with Google".
 *
 * The browser (Google Identity Services) posts a signed ID token as `credential`.
 * We verify it with Google's tokeninfo endpoint (no crypto libraries needed),
 * then find / link / create the matching row in `users` and start the session
 * exactly like the normal login flow does.
 */

header('Content-Type: application/json');

include 'db_connect.php';
include 'auth_lib.php';
auth_ensure_schema($conn);

function gauth_fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit();
}

$credential = $_POST['credential'] ?? '';
if ($credential === '') gauth_fail('Missing Google credential.');

$client_id = google_client_id();
if ($client_id === '') gauth_fail('Google sign-in is not configured on the server.', 500);

// --- verify the ID token with Google ---
$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
$ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 8, 'ignore_errors' => true]]);
$resp = @file_get_contents($url, false, $ctx);
if ($resp === false) gauth_fail('Could not reach Google to verify your sign-in.', 502);

$info = json_decode($resp, true);
if (!is_array($info) || isset($info['error']) || isset($info['error_description'])) {
    gauth_fail('Your Google sign-in could not be verified.');
}

$aud   = $info['aud'] ?? '';
$iss   = $info['iss'] ?? '';
$exp   = (int) ($info['exp'] ?? 0);
$sub   = $info['sub'] ?? '';
$email = strtolower(trim($info['email'] ?? ''));
$name  = trim($info['name'] ?? '');
$ev    = $info['email_verified'] ?? 'false';
$email_verified = ($ev === true || $ev === 'true' || $ev === 1 || $ev === '1');

if ($aud !== $client_id)  gauth_fail('This Google sign-in was issued for a different app.');
if ($iss !== 'accounts.google.com' && $iss !== 'https://accounts.google.com') gauth_fail('Invalid token issuer.');
if ($exp > 0 && $exp < time()) gauth_fail('Your Google sign-in has expired — please try again.');
if ($sub === '' || $email === '') gauth_fail('Google did not return a usable account.');
if (!$email_verified) gauth_fail('Your Google email address is not verified.');

$sub_esc   = $conn->real_escape_string($sub);
$email_esc = $conn->real_escape_string($email);

// --- find, link, or create the user ---
$user = null;

$r = $conn->query("SELECT * FROM users WHERE google_id = '$sub_esc' LIMIT 1");
if ($r && $r->num_rows > 0) {
    $user = $r->fetch_assoc();
}

if (!$user) {
    $r = $conn->query("SELECT * FROM users WHERE LOWER(email) = '$email_esc' LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $user = $r->fetch_assoc();
        $conn->query("UPDATE users SET google_id = '$sub_esc' WHERE id = " . (int) $user['id']);
        $user['google_id'] = $sub;
    }
}

if (!$user) {
    $display     = $name !== '' ? $name : (strstr($email, '@', true) ?: 'Customer');
    $display_esc = $conn->real_escape_string(mb_substr($display, 0, 100));
    // Google accounts have no local password; store a random un-guessable hash.
    $rand_hash   = $conn->real_escape_string(password_hash(bin2hex(random_bytes(18)), PASSWORD_DEFAULT));

    $ok = $conn->query("INSERT INTO users (name, email, password, phone, role, google_id)
                        VALUES ('$display_esc', '$email_esc', '$rand_hash', '', 'customer', '$sub_esc')");
    if (!$ok) gauth_fail('Could not create your account. Please try again.', 500);

    $new_id = (int) $conn->insert_id;
    $rr = $new_id > 0
        ? $conn->query("SELECT * FROM users WHERE id = $new_id")
        : $conn->query("SELECT * FROM users WHERE google_id = '$sub_esc' LIMIT 1");
    $user = $rr ? $rr->fetch_assoc() : null;
    if (!$user) gauth_fail('Account created, but sign-in failed. Please try logging in.', 500);
}

// --- start the session (same keys the rest of the site expects) ---
$_SESSION['user_id']          = $user['id'];
$_SESSION['user_name']        = $user['name'];
$_SESSION['user_email']       = $user['email'];
$_SESSION['role']             = $user['role'];
$_SESSION['fresh_login_modal'] = true;

echo json_encode(['status' => 'success', 'redirect' => 'index.php']);
