<?php
/** AJAX (admin): feature / unfeature a product for the homepage, and reorder featured items. */
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

$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['status' => 'error', 'message' => 'Bad request']); exit(); }

if ($action === 'toggle') {
    $featured = !empty($_POST['featured']);
    if ($featured) {
        $max = $conn->query("SELECT COALESCE(MAX(featured_order), -1) AS m FROM products WHERE is_featured = TRUE");
        $next = ((int) ($max ? $max->fetch_assoc()['m'] : -1)) + 1;
        $conn->query("UPDATE products SET is_featured = TRUE, featured_order = $next WHERE id = $id");
    } else {
        $conn->query("UPDATE products SET is_featured = FALSE WHERE id = $id");
    }
    echo json_encode(['status' => 'success', 'featured' => $featured]);
    exit();
}

if ($action === 'move') {
    $dir = $_POST['dir'] ?? '';
    $cur = $conn->query("SELECT featured_order FROM products WHERE id = $id AND is_featured = TRUE");
    if (!$cur || $cur->num_rows === 0) { echo json_encode(['status' => 'error', 'message' => 'Not featured']); exit(); }
    $curOrder = (int) $cur->fetch_assoc()['featured_order'];

    if ($dir === 'up') {
        $neighbor = $conn->query("SELECT id, featured_order FROM products WHERE is_featured = TRUE AND featured_order < $curOrder ORDER BY featured_order DESC LIMIT 1");
    } else {
        $neighbor = $conn->query("SELECT id, featured_order FROM products WHERE is_featured = TRUE AND featured_order > $curOrder ORDER BY featured_order ASC LIMIT 1");
    }
    if ($neighbor && $neighbor->num_rows > 0) {
        $n = $neighbor->fetch_assoc();
        $nId = (int) $n['id'];
        $nOrder = (int) $n['featured_order'];
        $conn->query("UPDATE products SET featured_order = $nOrder WHERE id = $id");
        $conn->query("UPDATE products SET featured_order = $curOrder WHERE id = $nId");
    }
    echo json_encode(['status' => 'success']);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
