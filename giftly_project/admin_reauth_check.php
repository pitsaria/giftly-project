<?php
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo "error";
    exit();
}

$user_id = $_SESSION['user_id'];
$password = $_POST['password'];

// Fetch the hashed password from the database
$result = $conn->query("SELECT password, role FROM users WHERE id = $user_id");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // Check if the user is actually an Admin
    if ($row['role'] != 'admin') {
        echo "error";
        exit();
    }

    // Verify the password
    if (password_verify($password, $row['password'])) {
        echo "success";
    } else {
        echo "error";
    }
} else {
    echo "error";
}
?>