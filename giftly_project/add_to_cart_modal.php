<?php
include 'db_connect.php';
include_once 'catalog_lib.php';
cart_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) {
    echo "login_required";
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
$replace = isset($_POST['replace']) ? $_POST['replace'] : 0;

if ($product_id <= 0) {
    echo "error";
    exit();
}

// Get product stock
$product_query = @$conn->query("SELECT quantity, is_active FROM products WHERE id = $product_id");
if (!$product_query) {
    $product_query = $conn->query("SELECT quantity FROM products WHERE id = $product_id");
}
if (!$product_query || $product_query->num_rows == 0) {
    echo "error";
    exit();
}
$product = $product_query->fetch_assoc();
// deactivated product — no longer purchasable
if (array_key_exists('is_active', $product)
    && in_array($product['is_active'], [false, 'f', '0', 0], true)) {
    echo "out_of_stock";
    exit();
}
$available_stock = intval($product['quantity']);

// Occasion Box color/size (ignored for products that don't have them) — the size's
// price is looked up server-side, never trusted from the client.
$color = trim($_POST['color'] ?? '');
$size  = trim($_POST['size'] ?? '');
$variant_price = null;
if ($size !== '') {
    $size_esc = $conn->real_escape_string($size);
    $sr = $conn->query("SELECT price FROM product_sizes WHERE product_id = $product_id AND size_name = '$size_esc'");
    if ($sr && $sr->num_rows > 0) {
        $variant_price = (float) $sr->fetch_assoc()['price'];
    } else {
        $size = ''; // not a real size option for this product — ignore it
    }
}
if ($color !== '') {
    $color_esc = $conn->real_escape_string($color);
    $cr = $conn->query("SELECT id FROM product_colors WHERE product_id = $product_id AND color_name = '$color_esc'");
    if (!$cr || $cr->num_rows === 0) $color = ''; // not a real color option — ignore it
}
$color_esc = $conn->real_escape_string($color);
$size_esc  = $conn->real_escape_string($size);
$variant_price_sql = $variant_price !== null ? (float) $variant_price : 'NULL';

// Stock is shared across every color/size of a product, so the limit check adds
// up every variant row already in this user's cart for this product.
$cart_total_query = $conn->query("SELECT COALESCE(SUM(quantity), 0) AS t FROM carts WHERE user_id = $user_id AND product_id = $product_id");
$current_cart_qty = $cart_total_query ? intval($cart_total_query->fetch_assoc()['t']) : 0;

// Check if out of stock
if ($available_stock <= 0) {
    echo "out_of_stock";
    exit();
}

if ($replace == 1) {
    // Replace mode - clear every variant of this product from the cart, add just this one
    $conn->query("DELETE FROM carts WHERE user_id = $user_id AND product_id = $product_id");
    $new_qty = min($quantity, $available_stock);
    if ($new_qty > 0) {
        $conn->query("INSERT INTO carts (user_id, product_id, quantity, selected_color, selected_size, variant_price)
                      VALUES ($user_id, $product_id, $new_qty, '$color_esc', '$size_esc', $variant_price_sql)");
    }
    echo "success";
    exit();
}

// Check if adding would exceed available stock
$total_after_add = $current_cart_qty + $quantity;
if ($total_after_add > $available_stock) {
    // Return the stock limit with a specific message
    echo json_encode([
        'error' => 'stock_limit',
        'message' => 'You\'ve reached the maximum available stock for this product. Only ' . $available_stock . ' items available.',
        'max_stock' => $available_stock
    ]);
    exit();
}

// Add to cart — merge into the matching color/size row if one already exists,
// otherwise start a new row for this variant.
$variant_query = $conn->query("SELECT id FROM carts WHERE user_id = $user_id AND product_id = $product_id
                               AND selected_color = '$color_esc' AND selected_size = '$size_esc'");
if ($variant_query && $variant_query->num_rows > 0) {
    $conn->query("UPDATE carts SET quantity = quantity + $quantity
                  WHERE user_id = $user_id AND product_id = $product_id
                    AND selected_color = '$color_esc' AND selected_size = '$size_esc'");
} else {
    $conn->query("INSERT INTO carts (user_id, product_id, quantity, selected_color, selected_size, variant_price)
                  VALUES ($user_id, $product_id, $quantity, '$color_esc', '$size_esc', $variant_price_sql)");
}

echo "success";
?>
