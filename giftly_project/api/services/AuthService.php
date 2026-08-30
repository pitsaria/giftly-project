<?php
// api/services/AuthService.php

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/../../auth_lib.php';

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
        
        $sql = "SELECT * FROM users WHERE email = '$email'";
        $result = $this->conn->query($sql);
        
        if ($result->num_rows == 0) {
            sendError('Email not found', 404);
        }
        
        $user = $result->fetch_assoc();
        
        if (!password_verify($password, $user['password'])) {
            sendError('Incorrect password', 401);
        }
        
        // Generate a Bearer token for standalone clients (mobile app)
        $token = AuthHelper::issueToken($this->conn, $user['id']);

        // Website still relies on the session
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        sendSuccess([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
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

    // 📧 FORGOT PASSWORD — mirrors forgot_password_ajax.php's token generation,
    // adapted for the API: returns the token directly instead of an emailed
    // link, since this project has no outbound mail configured either way.
    public function forgotPassword($input) {
        $email = $input['email'] ?? '';

        if (empty($email)) {
            sendError('Please enter your email address.');
        }

        $emailEsc = mysqli_real_escape_string($this->conn, $email);
        $check = $this->conn->query("SELECT id FROM users WHERE email = '$emailEsc'");
        if (!$check || $check->num_rows == 0) {
            sendError('Email address not found in our system.', 404);
        }

        $token = bin2hex(random_bytes(50));
        // Unlike the website's copy of this flow, token_expiry is actually
        // enforced below in resetPassword() — so this is a real, short-lived
        // window rather than dead configuration.
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $this->conn->query("UPDATE users SET reset_token = '$token', token_expiry = '$expiry' WHERE email = '$emailEsc'");

        sendSuccess(['token' => $token], 'Reset code generated. Use it to set a new password within the next hour.');
    }

    // 🔑 RESET PASSWORD — mirrors reset_password_ajax.php, with the added
    // token_expiry check that script never actually performed.
    public function resetPassword($input) {
        $token = $input['token'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($token) || empty($password)) {
            sendError('Please fill in all fields.');
        }

        $tokenEsc = mysqli_real_escape_string($this->conn, $token);
        $check = $this->conn->query("SELECT id, token_expiry FROM users WHERE reset_token = '$tokenEsc'");
        if (!$check || $check->num_rows == 0) {
            sendError('Invalid or expired reset code. Please request a new one.', 400);
        }

        $row = $check->fetch_assoc();
        if (empty($row['token_expiry']) || strtotime($row['token_expiry']) < time()) {
            sendError('Invalid or expired reset code. Please request a new one.', 400);
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $this->conn->query("UPDATE users SET password = '$hashed', reset_token = NULL, token_expiry = NULL WHERE reset_token = '$tokenEsc'");

        sendSuccess(null, 'Password reset successfully! You can now log in.');
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