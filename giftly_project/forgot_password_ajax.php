<?php
include 'db_connect.php';

$response = array('success' => false, 'message' => '');

if (isset($_POST['email'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // 🚨 THIS IS THE REAL CHECK! It checks your actual database.
    $check = $conn->query("SELECT id FROM users WHERE email = '$email'");
    
    if ($check->num_rows > 0) {
        $token = bin2hex(random_bytes(50));
       $expiry = date('Y-m-d H:i:s', strtotime('+365 days'));
        $conn->query("UPDATE users SET reset_token = '$token', token_expiry = '$expiry' WHERE email = '$email'");
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $reset_link = $protocol . "://" . $host . $path . "/reset_password.php?token=" . $token;
        
        $response['success'] = true;
        $response['link'] = $reset_link;
        $response['message'] = "Reset link generated successfully! Click the link below:";
    } else {
        // 🚨 THIS TRIGGERS THE RED ERROR BOX
        $response['success'] = false;
        $response['message'] = "Email address not found in our system.";
    }
} else {
    $response['message'] = "Please enter your email address.";
}

echo json_encode($response);
exit();
?>