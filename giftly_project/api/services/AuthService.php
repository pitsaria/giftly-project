<?php
// api/services/AuthService.php

require_once 'config/database.php';

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
        
        // Generate token (JWT or simple session token)
        $token = bin2hex(random_bytes(32));
        
        // Store token in database or session
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
    public function logout() {
        session_start();
        session_destroy();
        sendSuccess(null, 'Logged out successfully');
    }
    
    // ✅ VERIFY TOKEN
    public function verify($headers) {
        $token = $headers['Authorization'] ?? '';
        if (empty($token)) {
            sendError('No token provided', 401);
        }
        
        // Verify token logic (simplified)
        session_start();
        if (isset($_SESSION['user_id'])) {
            sendSuccess(['authenticated' => true, 'user_id' => $_SESSION['user_id']]);
        } else {
            sendError('Invalid or expired token', 401);
        }
    }
}
?>