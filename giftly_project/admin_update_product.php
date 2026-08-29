<?php
include 'db_connect.php';
include 'build_a_box_lib.php';
include 'catalog_lib.php';
bab_ensure_schema($conn);
catalog_ensure_schema($conn);

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
    $product_type = catalog_type_key($_POST['product_type'] ?? 'catalog');
    // Categories apply to shop products only; pre-made boxes/baskets use 0.
    $category_id = ($product_type === 'catalog') ? intval($_POST['category_id'] ?? 0) : 0;
    if ($product_type === 'catalog' && $category_id <= 0) {
        $_SESSION['product_error'] = "Please select a category.";
        header("Location: admin_products.php?error=category");
        exit();
    }

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

    // 🚨 VALIDATION: regular shop products must be usable in at least one box size
    $box_size_ids = isset($_POST['box_sizes']) && is_array($_POST['box_sizes'])
        ? array_map('intval', $_POST['box_sizes']) : [];
    if ($product_type === 'catalog' && empty($box_size_ids)) {
        $_SESSION['product_error'] = "Please select at least one box size this product can go into.";
        header("Location: admin_products.php?error=box_sizes");
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
            $sql = "UPDATE products SET name='$name', description='$desc', price='$price', quantity='$quantity', category_id='$category_id', product_type='$product_type', image='$new_filename' WHERE id=$id";
        } else {
            $_SESSION['product_updated'] = false;
            header("Location: admin_products.php?error=upload");
            exit();
        }
    } else {
        $sql = "UPDATE products SET name='$name', description='$desc', price='$price', quantity='$quantity', category_id='$category_id', product_type='$product_type' WHERE id=$id";
    }

    if ($conn->query($sql) === TRUE) {
        // Sync allowed box sizes
        $pid = intval($id);
        $conn->query("DELETE FROM product_box_sizes WHERE product_id = $pid");
        foreach ($box_size_ids as $bsid) {
            $bsid = intval($bsid);
            $conn->query("INSERT INTO product_box_sizes (product_id, box_size_id)
                          VALUES ($pid, $bsid) ON CONFLICT DO NOTHING");
        }
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