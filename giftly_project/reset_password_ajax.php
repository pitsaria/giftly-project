<?php
include 'db_connect.php';

$response = array('success' => false, 'message' => '');

if (isset($_POST['token']) && isset($_POST['password'])) {
    $token = mysqli_real_escape_string($conn, $_POST['token']);
    $new_pass = $_POST['password'];
    
    // 💡 THIS IS THE ONLY PLACE WE CHECK THE TOKEN NOW
    $check = $conn->query("SELECT id FROM users WHERE reset_token = '$token'");
    
    if ($check->num_rows > 0) {
        // Hash the new password and clear the token
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password = '$hashed', reset_token = NULL, token_expiry = NULL WHERE reset_token = '$token'");
        
        $response['success'] = true;
        $response['message'] = "Password reset successfully! You can now login.";
    } else {
        $response['message'] = "Invalid or expired token. Please request a new reset link.";
    }
} else {
    $response['message'] = "Please fill in all fields.";
}

echo json_encode($response);
exit();
?>