<?php
include 'db_connect.php';

// Security Check: Kick out if not an admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$user_check = $conn->query("SELECT role FROM users WHERE id = $user_id");
$user_data = $user_check->fetch_assoc();
if ($user_data['role'] !== 'admin') {
    header("Location: shop.php");
    exit();
}

// Check if the product ID is passed in the URL
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // 1. Get the image filename from the database so we can delete it from the folder too
    $sql_img = "SELECT image FROM products WHERE id = $id";
    $result = $conn->query($sql_img);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stored_image = $row['image'];

        // 2a. Old local uploads: remove the file from the folder if it's still there
        if ($stored_image && !preg_match('#^https?://#i', $stored_image)) {
            $image_path = "uploads/" . $stored_image;
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        // 2b. New images live in Supabase Storage — best-effort remote delete
        } else if ($stored_image) {
            supabase_delete_image($stored_image);
        }
    }

       // 3. Delete the product from the database
    $sql = "DELETE FROM products WHERE id = $id";
    if ($conn->query($sql) === TRUE) {
        // Store a session flag instead of putting it in the URL
        $_SESSION['product_deleted'] = true;
        header("Location: admin_products.php");
        exit();
    } else {
        echo "Error deleting record: " . $conn->error;
    }
} else {
    // If no ID is provided, just send them back to the product list
    header("Location: admin_products.php");
    exit();
}
?>