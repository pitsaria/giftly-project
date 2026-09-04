<?php
/** AJAX (admin): activate / deactivate a product or category. */
header('Content-Type: application/json');
include 'db_connect.php';
include 'catalog_lib.php';
catalog_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'Not logged in']); exit(); }
$uid = (int) $_SESSION['user_id'];
$r = $conn->query("SELECT role FROM users WHERE id = $uid");
if (!$r || ($r->fetch_assoc()['role'] ?? '') !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Not allowed']);
    exit();
}

$kind   = $_POST['kind'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);
$active = !empty($_POST['active']) ? 'TRUE' : 'FALSE';

if ($id <= 0 || !in_array($kind, ['product', 'category'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Bad request']);
    exit();
}

$table = $kind === 'product' ? 'products' : 'categories';
$ok = $conn->query("UPDATE $table SET is_active = $active WHERE id = $id");

echo json_encode($ok
    ? ['status' => 'success', 'active' => ($active === 'TRUE')]
    : ['status' => 'error', 'message' => 'Update failed']);
