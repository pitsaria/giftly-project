<?php
// api/services/AuthService.php

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/../../auth_lib.php';
require_once __DIR__ . '/../../mail_lib.php';
require_once __DIR__ . '/../../pwd_otp_lib.php';

class AuthService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // 🔐 LOGIN
    public function login($input) {
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            sendError('Email and password are required');
        }

        $emailEsc = $this->conn->real_escape_string($email);
        $result = $this->conn->query("SELECT * FROM users WHERE email = '$emailEsc'");

        if (!$result || $result->num_rows == 0) {
            sendError('Email not found', 404);
        }

        $user = $result->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            sendError('Incorrect password', 401);
        }

        // Email OTP step — only when the server can actually send email
        // (mirrors auth_lib.php's otp_enabled()). Google sign-in always skips it.
        if (function_exists('otp_enabled') && otp_enabled()) {
            [$otp_ref, $sent] = $this->startOtp($user);
            if ($sent) {
                sendSuccess([
                    'otp_required' => true,
                    'otp_ref'      => $otp_ref,
                    'email_masked' => otp_mask_email($user['email']),
                    'cooldown'     => otp_resend_cooldown(),
                ], 'Enter the code we emailed you.');
            }
            // Couldn't send the code — log in directly rather than locking them out.
        }

        $this->finishLogin($user);
    }

    // POST auth/verify-otp  { otp_ref, code }
    public function verifyOtp($input) {
        $this->ensureOtpRef();
        $ref  = $this->conn->real_escape_string(trim($input['otp_ref'] ?? ''));
        $code = preg_replace('/\D/', '', (string) ($input['code'] ?? ''));

        if ($ref === '') {
            sendError('Your sign-in session expired. Please log in again.');
        }
        if (strlen($code) !== 6) {
            sendError('Enter the 6-digit code.');
        }

        $r = $this->conn->query("SELECT * FROM login_otps WHERE otp_ref = '$ref' ORDER BY id DESC LIMIT 1");
        $row = ($r && $r->num_rows) ? $r->fetch_assoc() : null;
        if (!$row) {
            sendError('No code on file. Send a new one.');
        }
        if (strtotime($row['expires_at']) < time()) {
            $this->conn->query("DELETE FROM login_otps WHERE otp_ref = '$ref'");
            sendError('That code expired. Send a new one.');
        }
        if ((int) $row['attempts'] >= 5) {
            $this->conn->query("DELETE FROM login_otps WHERE otp_ref = '$ref'");
            sendError('Too many attempts. Please log in again.');
        }
        if (!password_verify($code, $row['code_hash'])) {
            $rid = (int) $row['id'];
            $this->conn->query("UPDATE login_otps SET attempts = attempts + 1 WHERE id = $rid");
            sendError('That code is incorrect.');
        }

        $this->conn->query("DELETE FROM login_otps WHERE otp_ref = '$ref'");

        $uid = (int) $row['user_id'];
        $ur = $this->conn->query("SELECT * FROM users WHERE id = $uid");
        $user = ($ur && $ur->num_rows) ? $ur->fetch_assoc() : null;
        if (!$user) {
            sendError('Account not found.', 404);
        }
        $this->finishLogin($user);
    }

    // POST auth/resend-otp  { otp_ref }
    public function resendOtp($input) {
        $this->ensureOtpRef();
        $ref = $this->conn->real_escape_string(trim($input['otp_ref'] ?? ''));
        if ($ref === '') {
            sendError('Your sign-in session expired. Please log in again.');
        }
        $r = $this->conn->query("SELECT * FROM login_otps WHERE otp_ref = '$ref' ORDER BY id DESC LIMIT 1");
        $row = ($r && $r->num_rows) ? $r->fetch_assoc() : null;
        if (!$row) {
            sendError('Your sign-in session expired. Please log in again.');
        }
        $uid = (int) $row['user_id'];
        $wait = otp_seconds_until_resend($this->conn, $uid);
        if ($wait > 0) {
            sendError("Please wait {$wait}s before requesting another code.");
        }
        $ur = $this->conn->query("SELECT * FROM users WHERE id = $uid");
        $user = ($ur && $ur->num_rows) ? $ur->fetch_assoc() : null;
        if (!$user) {
            sendError('Account not found.', 404);
        }
        [, $sent] = $this->startOtp($user, $ref);
        if ($sent) {
            sendSuccess(['otp_ref' => $ref, 'cooldown' => otp_resend_cooldown()], 'A new code is on its way.');
        }
        sendError("Couldn't send the code right now. Try again shortly.");
    }

    // Generate + email a 6-digit code, stored on a login_otps row keyed by
    // otp_ref (stateless — the website uses the session instead). Reuses an
    // existing $ref on resend. Returns [otp_ref, sent].
    private function startOtp($user, $ref = '') {
        $this->ensureOtpRef();
        $uid = (int) $user['id'];
        if ($ref === '') {
            $ref = bin2hex(random_bytes(16));
        }
        $ref_esc = $this->conn->real_escape_string($ref);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = $this->conn->real_escape_string(password_hash($code, PASSWORD_DEFAULT));
        $exp  = date('Y-m-d H:i:s', time() + 600);

        $inner = '<p style="color:#555;font-size:14px;line-height:1.6;">Use this code to finish signing in. It expires in 10 minutes.</p>'
               . '<div style="font-size:34px;font-weight:700;letter-spacing:8px;color:#ff8ba7;text-align:center;margin:22px 0;padding:14px;background:#fff5f7;border-radius:12px;">' . $code . '</div>'
               . '<p style="color:#999;font-size:12.5px;">If you didn\'t try to sign in, you can ignore this email and your password stays safe.</p>';
        $sent = mail_send($user['email'], 'Your Giftly sign-in code: ' . $code, mail_wrap('Verify your sign-in', $inner));
        if (!$sent) {
            return [$ref, false];
        }

        $this->conn->query("DELETE FROM login_otps WHERE user_id = $uid");
        $this->conn->query("INSERT INTO login_otps (user_id, code_hash, expires_at, otp_ref)
                            VALUES ($uid, '$hash', '$exp', '$ref_esc')");
        return [$ref, true];
    }

    // login_otps predates otp_ref on stores set up before the mobile OTP flow.
    private function ensureOtpRef() {
        static $ok = false;
        if ($ok) return;
        $ok = true;
        auth_ensure_schema($this->conn);
        $c = $this->conn->query("SELECT 1 FROM information_schema.columns
                                 WHERE table_name = 'login_otps' AND column_name = 'otp_ref'");
        if (!$c || $c->num_rows === 0) {
            $this->conn->query("ALTER TABLE login_otps ADD COLUMN IF NOT EXISTS otp_ref VARCHAR(64)");
        }
    }

    // Issue a Bearer token + sync the website session, then respond.
    private function finishLogin($user) {
        $token = AuthHelper::issueToken($this->conn, $user['id']);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role']       = $user['role'];

        sendSuccess([
            'token' => $token,
            'user'  => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ], 'Login successful');
    }
    
    // 📝 REGISTER
    public function register($input) {
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';
        $phone = $input['phone'] ?? '';
        $password = $input['password'] ?? '';
        $confirm_password = $input['confirm_password'] ?? '';
        
        if (empty($name) || empty($email) || empty($password)) {
            sendError('Name, email, and password are required');
        }
        
        if ($password !== $confirm_password) {
            sendError('Passwords do not match');
        }
        
        // Check if email exists
        $check = $this->conn->query("SELECT id FROM users WHERE email = '$email'");
        if ($check->num_rows > 0) {
            sendError('Email already registered', 409);
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (name, email, phone, password, role) 
                VALUES ('$name', '$email', '$phone', '$hashed_password', 'customer')";
        
        if ($this->conn->query($sql)) {
            sendSuccess(null, 'Registration successful! Please login.');
        } else {
            sendError('Registration failed: ' . $this->conn->error);
        }
    }
    
    // 🚪 LOGOUT
    public function logout($headers = []) {
        AuthHelper::revokeToken($this->conn, $headers);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        sendSuccess(null, 'Logged out successfully');
    }

    // 📧 FORGOT PASSWORD — step 1 of the website's rebuilt email → code → new
    // password flow (pwd_otp_lib.php). Mobile has no session, so the in-flight
    // reset is tracked by a stateless `reset_ref` (same pattern as login_otps'
    // otp_ref) instead of $_SESSION['pwd_reset_uid'].
    public function forgotPassword($input) {
        pwd_otp_ensure_schema($this->conn);
        $this->ensureResetRef();

        $email = trim($input['email'] ?? '');
        if ($email === '') {
            sendError('Please enter your email address.');
        }

        $emailEsc = $this->conn->real_escape_string($email);
        $check = $this->conn->query("SELECT id, email, google_id FROM users WHERE email = '$emailEsc'");
        if (!$check || $check->num_rows == 0) {
            sendError('Email address not found in our system.', 404);
        }
        $user = $check->fetch_assoc();

        // Google-only accounts have a random password hash they never set —
        // steer them to "Sign in with Google" instead.
        if (!empty($user['google_id'])) {
            sendError('This account uses "Sign in with Google". Use that button to sign in.');
        }

        [$ref, $sent, $err] = $this->sendResetCode($this->conn, (int) $user['id'], $user['email']);
        if (!$sent) {
            sendError($err ?: "Couldn't send the code right now. Try again shortly.");
        }

        sendSuccess([
            'reset_ref'    => $ref,
            'email_masked' => otp_mask_email($user['email']),
            'cooldown'     => pwd_otp_cooldown(),
        ], 'We emailed a 6-digit code to ' . $user['email'] . '.');
    }

    // POST auth/verify-reset-code  { reset_ref, code } or { reset_ref, resend: true }
    public function verifyResetCode($input) {
        pwd_otp_ensure_schema($this->conn);
        $this->ensureResetRef();

        $ref = trim($input['reset_ref'] ?? '');
        if ($ref === '') {
            sendError('Your reset session expired. Please request a new code.');
        }
        $uid = $this->uidByResetRef($ref, 'reset');
        if ($uid <= 0) {
            sendError('Your reset session expired. Please request a new code.');
        }

        if (!empty($input['resend'])) {
            $wait = pwd_otp_seconds_until_resend($this->conn, $uid, 'reset');
            if ($wait > 0) {
                sendError("Please wait {$wait}s before requesting a new code.");
            }
            $ur = $this->conn->query("SELECT email FROM users WHERE id = $uid");
            $email = ($ur && $ur->num_rows) ? ($ur->fetch_assoc()['email'] ?? '') : '';
            [, $sent, $err] = $this->sendResetCode($this->conn, $uid, $email, $ref);
            if (!$sent) {
                sendError($err ?: "Couldn't send the code right now.");
            }
            sendSuccess(['cooldown' => pwd_otp_cooldown()], 'A new code is on its way.');
        }

        [$ok, $err] = pwd_otp_verify($this->conn, $uid, $input['code'] ?? '', 'reset');
        if (!$ok) {
            sendError($err);
        }
        sendSuccess(null, 'Code verified. Choose a new password.');
    }

    // 🔑 RESET PASSWORD — step 3, requires a code already verified in step 2.
    public function resetPassword($input) {
        pwd_otp_ensure_schema($this->conn);
        $this->ensureResetRef();

        $ref      = trim($input['reset_ref'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $confirm  = (string) ($input['confirm_password'] ?? $password);

        $uid = $ref !== '' ? $this->uidByResetRef($ref, 'reset') : 0;
        if ($uid <= 0 || !pwd_otp_is_verified($this->conn, $uid, 'reset')) {
            sendError('Please verify the emailed code first.');
        }
        if ($password !== $confirm) {
            sendError('The two passwords do not match.');
        }
        [$strong, $serr] = pwd_strength_check($password);
        if (!$strong) {
            sendError($serr);
        }

        $hashed = $this->conn->real_escape_string(password_hash($password, PASSWORD_DEFAULT));
        $this->conn->query("UPDATE users SET password = '$hashed' WHERE id = $uid");
        pwd_otp_clear($this->conn, $uid, 'reset');

        sendSuccess(null, 'Password updated. You can now sign in.');
    }

    // Send/resend a password-reset code, keeping `reset_ref` pointed at
    // whichever password_otps row is currently active (a fresh insert, or the
    // still-in-cooldown row reused by pwd_otp_send). Returns [ref, sent, err].
    private function sendResetCode($conn, $uid, $email, $ref = '') {
        if ($ref === '') {
            $ref = bin2hex(random_bytes(16));
        }
        [$sent, $err] = pwd_otp_send($conn, $uid, $email, 'reset');
        if ($sent) {
            $refEsc = $conn->real_escape_string($ref);
            $conn->query("UPDATE password_otps SET reset_ref = '$refEsc' WHERE user_id = " . (int) $uid . " AND purpose = 'reset'");
        }
        return [$ref, $sent, $err];
    }

    private function uidByResetRef($ref, $purpose) {
        $refEsc = $this->conn->real_escape_string($ref);
        $pEsc   = $this->conn->real_escape_string($purpose);
        $r = $this->conn->query("SELECT user_id FROM password_otps WHERE reset_ref = '$refEsc' AND purpose = '$pEsc' ORDER BY id DESC LIMIT 1");
        return ($r && $r->num_rows) ? (int) $r->fetch_assoc()['user_id'] : 0;
    }

    // password_otps predates reset_ref on stores set up before the mobile
    // password-reset flow existed.
    private function ensureResetRef() {
        static $ok = false;
        if ($ok) return;
        $ok = true;
        $c = $this->conn->query("SELECT 1 FROM information_schema.columns
                                 WHERE table_name = 'password_otps' AND column_name = 'reset_ref'");
        if (!$c || $c->num_rows === 0) {
            $this->conn->query("ALTER TABLE password_otps ADD COLUMN IF NOT EXISTS reset_ref VARCHAR(64)");
        }
    }

    // GET auth/google — the configured Web client ID (empty when the feature
    // is off, so the app hides the button exactly like the website does).
    public function googleConfig() {
        sendSuccess(['client_id' => google_client_id()]);
    }

    // POST auth/google — "Continue with Google" for the mobile app.
    // Mirrors google_auth.php's verify/link/create, but issues a Bearer token
    // for the standalone client instead of relying on the PHP session.
    public function googleLogin($input) {
        auth_ensure_schema($this->conn);

        $credential = $input['credential'] ?? '';
        if ($credential === '') {
            sendError('Missing Google credential.');
        }

        $client_id = google_client_id();
        if ($client_id === '') {
            sendError('Google sign-in is not configured on the server.', 500);
        }

        // Verify the ID token with Google's tokeninfo endpoint (no crypto libs).
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 8, 'ignore_errors' => true]]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            sendError('Could not reach Google to verify your sign-in.', 502);
        }

        $info = json_decode($resp, true);
        if (!is_array($info) || isset($info['error']) || isset($info['error_description'])) {
            sendError('Your Google sign-in could not be verified.');
        }

        $aud   = $info['aud'] ?? '';
        $iss   = $info['iss'] ?? '';
        $exp   = (int) ($info['exp'] ?? 0);
        $sub   = $info['sub'] ?? '';
        $email = strtolower(trim($info['email'] ?? ''));
        $name  = trim($info['name'] ?? '');
        $ev    = $info['email_verified'] ?? 'false';
        $email_verified = ($ev === true || $ev === 'true' || $ev === 1 || $ev === '1');

        if ($aud !== $client_id) {
            sendError('This Google sign-in was issued for a different app.');
        }
        if ($iss !== 'accounts.google.com' && $iss !== 'https://accounts.google.com') {
            sendError('Invalid token issuer.');
        }
        if ($exp > 0 && $exp < time()) {
            sendError('Your Google sign-in has expired — please try again.');
        }
        if ($sub === '' || $email === '') {
            sendError('Google did not return a usable account.');
        }
        if (!$email_verified) {
            sendError('Your Google email address is not verified.');
        }

        $sub_esc   = $this->conn->real_escape_string($sub);
        $email_esc = $this->conn->real_escape_string($email);

        // find, link, or create — same order as google_auth.php
        $user = null;
        $r = $this->conn->query("SELECT * FROM users WHERE google_id = '$sub_esc' LIMIT 1");
        if ($r && $r->num_rows > 0) {
            $user = $r->fetch_assoc();
        }
        if (!$user) {
            $r = $this->conn->query("SELECT * FROM users WHERE LOWER(email) = '$email_esc' LIMIT 1");
            if ($r && $r->num_rows > 0) {
                $user = $r->fetch_assoc();
                $this->conn->query("UPDATE users SET google_id = '$sub_esc' WHERE id = " . (int) $user['id']);
            }
        }
        if (!$user) {
            $display     = $name !== '' ? $name : (strstr($email, '@', true) ?: 'Customer');
            $display_esc = $this->conn->real_escape_string(mb_substr($display, 0, 100));
            $rand_hash   = $this->conn->real_escape_string(password_hash(bin2hex(random_bytes(18)), PASSWORD_DEFAULT));
            $ok = $this->conn->query("INSERT INTO users (name, email, password, phone, role, google_id)
                                      VALUES ('$display_esc', '$email_esc', '$rand_hash', '', 'customer', '$sub_esc')");
            if (!$ok) {
                sendError('Could not create your account. Please try again.', 500);
            }
            $new_id = (int) $this->conn->insert_id;
            $rr = $new_id > 0
                ? $this->conn->query("SELECT * FROM users WHERE id = $new_id")
                : $this->conn->query("SELECT * FROM users WHERE google_id = '$sub_esc' LIMIT 1");
            $user = $rr ? $rr->fetch_assoc() : null;
            if (!$user) {
                sendError('Account created, but sign-in failed. Please try logging in.', 500);
            }
        }

        $token = AuthHelper::issueToken($this->conn, $user['id']);

        // Keep the website session in sync too (parity with login()).
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role']       = $user['role'];

        sendSuccess([
            'token' => $token,
            'user' => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ], 'Login successful');
    }

    // ✅ VERIFY TOKEN / SESSION
    public function verify($headers) {
        $user_id = AuthHelper::resolveUserId($this->conn, $headers);
        if (!$user_id) {
            sendError('Invalid or expired session', 401);
        }

        $result = $this->conn->query("SELECT id, name, email, role FROM users WHERE id = $user_id");
        if (!$result || $result->num_rows == 0) {
            sendError('User not found', 404);
        }

        sendSuccess(['authenticated' => true, 'user' => $result->fetch_assoc()]);
    }
}
?>