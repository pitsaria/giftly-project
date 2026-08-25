<?php
// 1. DATABASE AND SESSION MUST BE FIRST
include 'db_connect.php'; 

if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // 🚨 Get the redirect URL from the form
    $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'index.php';
    
    // 🚨 Clean up the redirect URL - remove any existing error parameters
    $redirect_to = strtok($redirect_to, '?');
    
    // 🚨 If the redirect URL is empty or just the domain, use index.php
    if (empty($redirect_to) || $redirect_to == 'http://localhost/giftly_project/' || $redirect_to == 'http://localhost/') {
        $redirect_to = 'index.php';
    }

    // ✅ TRY API FIRST
    $api_url = 'http://localhost/giftly_project/api/index.php?route=auth/login';
    
    $data = json_encode([
        'email' => $email,
        'password' => $password
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
    
    // Check if API call was successful
    $api_success = false;
    if ($response !== false) {
        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status'] == 'success') {
            $api_success = true;
            $_SESSION['user_id'] = $result['data']['user']['id'];
            $_SESSION['user_name'] = $result['data']['user']['name'];
            $_SESSION['user_email'] = $result['data']['user']['email'];
            $_SESSION['role'] = $result['data']['user']['role'];
            $_SESSION['fresh_login_modal'] = true;
            
            // 🚀 ALWAYS redirect to index.php so the modals can trigger properly
            header("Location: index.php");
            exit();
        }
    }
    
    // If API failed, fallback to direct database
    if (!$api_success) {
        $sql = "SELECT * FROM users WHERE email = '$email'";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['name'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['fresh_login_modal'] = true;
                
                // 🚀 ALWAYS redirect to index.php so the modals can trigger properly
                header("Location: index.php");
                exit();
            } else {
                // 🚨 Redirect back with error - preserve the redirect URL
                header("Location: " . $redirect_to . "?login_error=incorrect");
                exit();
            }
        } else {
            // 🚨 Redirect back with error - preserve the redirect URL
            header("Location: " . $redirect_to . "?login_error=notfound");
            exit();
        }
    }
}

// If this page is loaded directly (not from the modal), redirect to homepage
if (!isset($_GET['login_error'])) {
    header("Location: index.php");
    exit();
}

// 3. LOAD HEADER ONLY AFTER PHP LOGIC
include 'header.php';
?>