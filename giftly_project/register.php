<?php
// TURN ON FULL DEBUGGING (REMOVE THIS LATER)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db_connect.php'; 

// If user is already logged in, send them straight to the shop
if (isset($_SESSION['user_id'])) {
    header("Location: shop.php");
    exit();
}

if (isset($_POST['register'])) {
    // 1. Capture ALL data correctly
    $firstname = isset($_POST['firstname']) ? mysqli_real_escape_string($conn, $_POST['firstname']) : '';
    $lastname  = isset($_POST['lastname']) ? mysqli_real_escape_string($conn, $_POST['lastname']) : '';
    $email     = isset($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : '';
    $phone     = isset($_POST['phone']) ? mysqli_real_escape_string($conn, $_POST['phone']) : '';
    $password  = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm   = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'index.php';
    $redirect_to = strtok($redirect_to, '?');

    // 2. Basic Validations
    if ($password !== $confirm) {
        header("Location: " . $redirect_to . "?reg_msg=error&reg_error=Passwords do not match.");
        exit();
    }

    // 3. Check if email already exists
    $check = $conn->query("SELECT id FROM users WHERE email = '$email'");
    if ($check->num_rows > 0) {
        header("Location: " . $redirect_to . "?reg_msg=error&reg_error=Email is already registered.");
        exit();
    }

    // 4. Hash password and insert
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $fullname = $firstname . ' ' . $lastname;

    // ✅ TRY API FIRST
    $api_url = 'http://localhost/giftly_project/api/index.php?route=auth/register';
    
    $data = json_encode([
        'name' => $fullname,
        'email' => $email,
        'phone' => $phone,
        'password' => $password,
        'confirm_password' => $confirm
    ]);
    
    $options = [
        'http' => [
            'header' => "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => $data,
            'ignore_errors' => true,
            'timeout' => 5,
        ],
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($api_url, false, $context);
    
    $api_success = false;
    if ($response !== false) {
        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status'] == 'success') {
            $api_success = true;
            header("Location: " . $redirect_to . "?reg_msg=success");
            exit();
        } else {
            // API returned error but we already checked email exists, so show the error
            $error = $result['error'] ?? 'Registration failed. Please try again.';
            header("Location: " . $redirect_to . "?reg_msg=error&reg_error=" . urlencode($error));
            exit();
        }
    }
    
    // If API failed, fallback to direct database
    if (!$api_success) {
        $sql = "INSERT INTO users (name, email, password, phone, role) 
                VALUES ('$fullname', '$email', '$hashed_password', '$phone', 'customer')";
        
        if ($conn->query($sql) === TRUE) {
            header("Location: " . $redirect_to . "?reg_msg=success");
            exit();
        } else {
            header("Location: " . $redirect_to . "?reg_msg=error&reg_error=" . urlencode($conn->error));
            exit();
        }
    }
}

// If this page is loaded directly, redirect to homepage
header("Location: index.php");
exit();
?>