<?php
// product_open_data.php — returns exactly what catalog_grid.php's catOpen(...)
// needs for one product ($_GET['id']), computed with the same helpers the
// grid itself uses when rendering a card (img_url, catalog_effective_price,
// catalog_whats_inside_lines, catalog_get_colors/sizes) so a deep-linked
// product opens identically to clicking its card. Used by catalog_grid.php's
// own auto-open script for ?product=<id> links (shared from the mobile app,
// or any "check this out" link to the website).
include 'db_connect.php';
include_once 'catalog_lib.php';
catalog_ensure_schema($conn);

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['error' => 'Invalid product id']);
    exit();
}

$result = $conn->query("SELECT * FROM products WHERE id = $id AND is_active = TRUE
                        AND category_id NOT IN (SELECT id FROM categories WHERE is_active = FALSE)");
if (!$result || $result->num_rows === 0) {
    echo json_encode(['error' => 'Product not found']);
    exit();
}

$row = $result->fetch_assoc();
$eff_price = catalog_effective_price($row);
$whats_inside_lines = catalog_whats_inside_lines($row['whats_inside'] ?? '');
$is_occasion_box = ($row['product_type'] ?? 'catalog') === 'occasion_box';
$opt_colors = $is_occasion_box ? catalog_get_colors($conn, $id) : [];
$opt_sizes  = $is_occasion_box ? catalog_get_sizes($conn, $id) : [];
$colors_js = array_map(function ($c) { return ['name' => $c['color_name'], 'image' => img_url($c['image'] ?? '')]; }, $opt_colors);
$sizes_js  = array_map(function ($s) { return ['name' => $s['size_name'], 'price' => (float) $s['price']]; }, $opt_sizes);

echo json_encode([
    'id' => $id,
    'name' => $row['name'],
    'description' => $row['description'],
    'image' => img_url($row['image']),
    'price' => (float) $eff_price,
    'quantity' => (int) $row['quantity'],
    'whatsInside' => $whats_inside_lines,
    'colors' => $colors_js,
    'sizes' => $sizes_js,
]);
