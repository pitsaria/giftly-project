<?php
/**
 * AJAX: claim a coded promo for the logged-in customer.
 * POST: promo_id
 * JSON: { status: 'claimed'|'already'|'error', message, code? }
 */
include 'db_connect.php';
include_once 'promo_lib.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'code' => 'login_required', 'message' => 'Please log in to claim vouchers.']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit();
}

$res = promo_claim($conn, (int) $_SESSION['user_id'], (int) ($_POST['promo_id'] ?? 0));
echo json_encode(['status' => $res['status'], 'message' => $res['message']]);
