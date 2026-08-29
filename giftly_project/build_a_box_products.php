<?php
/**
 * AJAX: products that are eligible for a given box size.
 * GET params: size_id (required), search, category, page
 * Returns JSON { status, products:[...], pagination:{...} }
 */
include 'db_connect.php';
include 'build_a_box_lib.php';
header('Content-Type: application/json');

bab_ensure_schema($conn);

$size_id  = isset($_GET['size_id']) ? intval($_GET['size_id']) : 0;
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$page     = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit    = 24;
$offset   = ($page - 1) * $limit;

$size = bab_box_size($conn, $size_id);
if (!$size) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid box size.']);
    exit();
}

// Lightweight mode: just the set of product ids eligible for this size
// (used when the shopper switches box size to prune items that no longer fit).
if (isset($_GET['ids_only'])) {
    $res = $conn->query("
        SELECT p.id
        FROM products p
        JOIN product_box_sizes pbs ON pbs.product_id = p.id
        WHERE pbs.box_size_id = $size_id
    ");
    $ids = [];
    while ($res && $row = $res->fetch_assoc()) {
        $ids[] = intval($row['id']);
    }
    echo json_encode(['status' => 'success', 'ids' => $ids]);
    exit();
}

$where = "pbs.box_size_id = $size_id AND p.quantity > 0";
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND p.name LIKE '%$s%'";
}
if ($category > 0) {
    $where .= " AND p.category_id = $category";
}

$count_res = $conn->query("
    SELECT COUNT(*) AS total
    FROM products p
    JOIN product_box_sizes pbs ON pbs.product_id = p.id
    WHERE $where
");
$total = $count_res ? intval($count_res->fetch_assoc()['total']) : 0;

$res = $conn->query("
    SELECT p.id, p.name, p.description, p.price, p.image, p.quantity, p.category_id
    FROM products p
    JOIN product_box_sizes pbs ON pbs.product_id = p.id
    WHERE $where
    ORDER BY p.id ASC
    LIMIT $limit OFFSET $offset
");

$products = [];
while ($res && $row = $res->fetch_assoc()) {
    $products[] = [
        'id'          => intval($row['id']),
        'name'        => $row['name'],
        'description' => $row['description'],
        'price'       => floatval($row['price']),
        'image'       => $row['image'],
        'quantity'    => intval($row['quantity']),
    ];
}

echo json_encode([
    'status'   => 'success',
    'products' => $products,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => max(1, ceil($total / $limit)),
    ],
]);
