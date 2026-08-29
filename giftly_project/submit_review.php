<?php
/**
 * AJAX: create / update the logged-in customer's review for a product.
 * POST: product_id, rating (1-5), comment
 * JSON response.
 */
include 'db_connect.php';
include 'reviews_lib.php';
reviews_ensure_schema($conn);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'code' => 'login_required', 'message' => 'Please log in.']);
    exit();
}

$user_id    = (int) $_SESSION['user_id'];
$product_id = (int) ($_POST['product_id'] ?? 0);
$rating     = (int) ($_POST['rating'] ?? 0);
$comment    = trim($_POST['comment'] ?? '');

if ($product_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['status' => 'error', 'message' => 'Please give a star rating.']);
    exit();
}
$comment = mb_substr($comment, 0, 1500);

// One review per customer per product — no editing once posted.
if (reviews_user_review($conn, $user_id, $product_id)) {
    echo json_encode(['status' => 'error', 'code' => 'already', 'message' => 'You\'ve already reviewed this item.']);
    exit();
}

$order_id = reviews_eligible_order($conn, $user_id, $product_id);
if ($order_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'You can only review items from an order you\'ve received.']);
    exit();
}

$comment_esc = $conn->real_escape_string($comment);

$conn->query("
    INSERT INTO product_reviews (product_id, user_id, order_id, rating, comment, status)
    VALUES ($product_id, $user_id, $order_id, $rating, '$comment_esc', 'published')
    ON CONFLICT (product_id, user_id) DO NOTHING
");

$summary = reviews_summary($conn, $product_id);
echo json_encode([
    'status'  => 'success',
    'message' => 'Thanks for your review!',
    'summary' => $summary,
]);
