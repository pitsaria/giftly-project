<?php
include 'db_connect.php';

// Security Check
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

if (isset($_POST['update_product'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);
    $category_id = $_POST['category_id'];

    // 🚨 VALIDATION: Check if price is negative
    if ($price < 0) {
        $_SESSION['product_error'] = "Price cannot be negative.";
        header("Location: admin_products.php?error=price_negative");
        exit();
    }
    
    // 🚨 VALIDATION: Check if price exceeds maximum
    if ($price > 9999.99) {
        $_SESSION['product_error'] = "Price cannot exceed 9,999.99.";
        header("Location: admin_products.php?error=price_max");
        exit();
    }
    
    // 🚨 VALIDATION: Check if price is empty or not a number
    if ($_POST['price'] === '' || !is_numeric($_POST['price'])) {
        $_SESSION['product_error'] = "Please enter a valid price.";
        header("Location: admin_products.php?error=invalid_price");
        exit();
    }
    
    // 🚨 VALIDATION: Check if quantity is negative
    if ($quantity < 0) {
        $_SESSION['product_error'] = "Quantity cannot be negative.";
        header("Location: admin_products.php?error=quantity_negative");
        exit();
    }
    
    // 🚨 VALIDATION: Check if quantity exceeds maximum
    if ($quantity > 9999) {
        $_SESSION['product_error'] = "Quantity cannot exceed 9,999.";
        header("Location: admin_products.php?error=quantity_max");
        exit();
    }
    
    // 🚨 VALIDATION: Check if quantity is empty or not a number
    if ($_POST['quantity'] === '' || !is_numeric($_POST['quantity'])) {
        $_SESSION['product_error'] = "Please enter a valid quantity.";
        header("Location: admin_products.php?error=invalid_quantity");
        exit();
    }

    $name = mysqli_real_escape_string($conn, $name);
    $desc = mysqli_real_escape_string($conn, $desc);
    $category_id = mysqli_real_escape_string($conn, $category_id);

    if (!empty($_FILES["image"]["name"])) {
        $target_dir = "uploads/";
        $original_filename = $_FILES["image"]["name"];
        $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $new_filename = "product_" . time() . "." . $extension;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $sql = "UPDATE products SET name='$name', description='$desc', price='$price', quantity='$quantity', category_id='$category_id', image='$new_filename' WHERE id=$id";
        } else {
            $_SESSION['product_updated'] = false;
            header("Location: admin_products.php?error=upload");
            exit();
        }
    } else {
        $sql = "UPDATE products SET name='$name', description='$desc', price='$price', quantity='$quantity', category_id='$category_id' WHERE id=$id";
    }

    if ($conn->query($sql) === TRUE) {
        $_SESSION['product_updated'] = true;
        header("Location: admin_products.php");
        exit();
    } else {
        echo "Database Error: " . $conn->error;
        exit();
    }
} else {
    header("Location: admin_products.php");
    exit();
}
?>