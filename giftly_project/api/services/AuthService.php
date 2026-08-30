<?php
// api/services/AuthService.php

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';

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