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

/** Rebuilds admin_products.php?... preserving the list state (view/category/type/search/page)
 * the admin was on when they opened the edit modal, so saving doesn't bounce them back to
 * the unfiltered "All" category. */
function admin_products_return_url($extra = []) {
    $params = [];
    if (!empty($_POST['return_view'])) $params['view'] = $_POST['return_view'];
    if (!empty($_POST['return_filter_cat'])) $params['filter_cat'] = (int) $_POST['return_filter_cat'];
    if (!empty($_POST['return_filter_type'])) $params['filter_type'] = $_POST['return_filter_type'];
    if (!empty($_POST['return_search'])) $params['search'] = $_POST['return_search'];
    if (!empty($_POST['return_page'])) $params['page'] = (int) $_POST['return_page'];
    $params = array_merge($params, $extra);
    return 'admin_products.php' . (empty($params) ? '' : '?' . http_build_query($params));
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
        header("Location: " . admin_products_return_url(["error" => "category"]));
        exit();
    }

    // 🚨 VALIDATION: Check if price is negative
    if ($price < 0) {
        $_SESSION['product_error'] = "Price cannot be negative.";
        header("Location: " . admin_products_return_url(["error" => "price_negative"]));
        exit();
    }
    
    // 🚨 VALIDATION: Check if price exceeds maximum
    if ($price > 9999.99) {
        $_SESSION['product_error'] = "Price cannot exceed 9,999.99.";
        header("Location: " . admin_products_return_url(["error" => "price_max"]));
        exit();
    }
    
    // 🚨 VALIDATION: Check if price is empty or not a number
    if ($_POST['price'] === '' || !is_numeric($_POST['price'])) {
        $_SESSION['product_error'] = "Please enter a valid price.";
        header("Location: " . admin_products_return_url(["error" => "invalid_price"]));
        exit();
    }
    
    // 🚨 VALIDATION: Check if quantity is negative
    if ($quantity < 0) {
        $_SESSION['product_error'] = "Quantity cannot be negative.";
        header("Location: " . admin_products_return_url(["error" => "quantity_negative"]));
        exit();
    }
    
    // 🚨 VALIDATION: Check if quantity exceeds maximum
    if ($quantity > 9999) {
        $_SESSION['product_error'] = "Quantity cannot exceed 9,999.";
        header("Location: " . admin_products_return_url(["error" => "quantity_max"]));
        exit();
    }
    
    // 🚨 VALIDATION: Check if quantity is empty or not a number
    if ($_POST['quantity'] === '' || !is_numeric($_POST['quantity'])) {
        $_SESSION['product_error'] = "Please enter a valid quantity.";
        header("Location: " . admin_products_return_url(["error" => "invalid_quantity"]));
        exit();
    }

    // 🚨 VALIDATION: regular shop products must be usable in at least one box size
    $box_size_ids = isset($_POST['box_sizes']) && is_array($_POST['box_sizes'])
        ? array_map('intval', $_POST['box_sizes']) : [];
    if ($product_type === 'catalog' && empty($box_size_ids)) {
        $_SESSION['product_error'] = "Please select at least one box size this product can go into.";
        header("Location: " . admin_products_return_url(["error" => "box_sizes"]));
        exit();
    }

    // Optional sale price + end date
    $sale_raw = trim($_POST['sale_price'] ?? '');
    $sale_sql = 'NULL';
    $sale_ends_sql = 'NULL';
    if ($sale_raw !== '') {
        if (!is_numeric($sale_raw) || floatval($sale_raw) < 0) {
            $_SESSION['product_error'] = "Sale price must be a valid amount.";
            header("Location: " . admin_products_return_url(["error" => "sale_price"]));
            exit();
        }
        if (floatval($sale_raw) >= $price) {
            $_SESSION['product_error'] = "Sale price must be lower than the regular price.";
            header("Location: " . admin_products_return_url(["error" => "sale_price"]));
            exit();
        }
        $sale_sql = "'" . floatval($sale_raw) . "'";
        $sale_ends_raw = trim($_POST['sale_ends'] ?? '');
        if ($sale_ends_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sale_ends_raw)) {
            $sale_ends_sql = "'" . $conn->real_escape_string($sale_ends_raw) . " 23:59:59'";
        }
    }
    $sale_frag = "sale_price=$sale_sql, sale_ends=$sale_ends_sql";

    $name = mysqli_real_escape_string($conn, $name);
    $desc = mysqli_real_escape_string($conn, $desc);
    $category_id = mysqli_real_escape_string($conn, $category_id);

    // Occasion Boxes & Baskets: "What's Inside" (one item per line)
    $whats_inside = in_array($product_type, ['occasion_box', 'basket'], true)
        ? trim($_POST['whats_inside'] ?? '') : '';
    $whats_inside_sql = $whats_inside !== '' ? "'" . $conn->real_escape_string($whats_inside) . "'" : 'NULL';

    // Occasion Boxes only: color options (each needs its own image, new or kept) + size options
    $color_names = $product_type === 'occasion_box' ? ($_POST['color_name'] ?? []) : [];
    $color_existing = $_POST['color_existing_image'] ?? [];
    $size_names  = $product_type === 'occasion_box' ? ($_POST['size_name'] ?? []) : [];
    $size_prices = $product_type === 'occasion_box' ? ($_POST['size_price'] ?? []) : [];

    $color_images = [];
    foreach ($color_names as $i => $cname) {
        if (trim($cname) === '') continue;
        $cfile = catalog_normalize_file($_FILES['color_image'] ?? [], $i);
        $curl = $cfile ? supabase_upload_image($cfile) : null;
        if ($curl === null) $curl = trim($color_existing[$i] ?? '');
        if ($curl === '') {
            $_SESSION['product_error'] = "Please add a product image for each color option.";
            header("Location: " . admin_products_return_url(["error" => "color_image"]));
            exit();
        }
        $color_images[$i] = $curl;
    }

    if (!empty($_FILES["image"]["name"])) {
        // New image chosen — upload it to Supabase Storage (returns a full public URL).
        $new_url = supabase_upload_image($_FILES["image"]);

        if ($new_url !== null) {
            $image_esc = mysqli_real_escape_string($conn, $new_url);
            $sql = "UPDATE products SET name='$name', description='$desc', price='$price', $sale_frag, quantity='$quantity', category_id='$category_id', product_type='$product_type', image='$image_esc', whats_inside=$whats_inside_sql WHERE id=$id";
        } else {
            $_SESSION['product_updated'] = false;
            header("Location: " . admin_products_return_url(["error" => "upload"]));
            exit();
        }
    } else {
        $sql = "UPDATE products SET name='$name', description='$desc', price='$price', $sale_frag, quantity='$quantity', category_id='$category_id', product_type='$product_type', whats_inside=$whats_inside_sql WHERE id=$id";
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
        catalog_save_colors($conn, $pid, $color_names, $color_images);
        catalog_save_sizes($conn, $pid, $size_names, $size_prices);
        $_SESSION['product_updated'] = true;
        header("Location: " . admin_products_return_url());
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