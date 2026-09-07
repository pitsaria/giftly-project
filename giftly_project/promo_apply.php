<?php
/**
 * AJAX: apply / remove / refresh a promo code for a checkout.
 *
 * POST params:
 *   scope   = 'products' | 'box'
 *   action  = 'apply' | 'remove' | 'refresh'   (default 'refresh')
 *   code    = the code to apply           (action=apply)
 *   ids     = comma-separated cart ids     (scope=products)
 *   box_id  = the box id                   (scope=box)
 *
 * Returns the recalculated order summary as JSON.
 */
header('Content-Type: application/json');

include 'db_connect.php';
include_once 'catalog_lib.php';
include_once 'promo_lib.php';
catalog_ensure_schema($conn);
promo_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Please sign in again.']);
    exit();
}
$user_id = (int) $_SESSION['user_id'];

$scope  = (($_POST['scope'] ?? 'products') === 'box') ? 'box' : 'products';
$action = $_POST['action'] ?? 'refresh';
$skey   = promo_session_key($scope);

// ---- compute the items subtotal from the live cart / box ----
$subtotal   = 0.0;
$item_count = 0;
$box_price  = 0.0;

if ($scope === 'box') {
    include_once 'build_a_box_lib.php';
    bab_ensure_schema($conn);
    $box_id = (int) ($_POST['box_id'] ?? 0);
    $data = bab_load_box($conn, $box_id, $user_id);
    if (!$data) {
        echo json_encode(['ok' => false, 'message' => 'Box not found.']);
        exit();
    }
    $subtotal   = (float) $data['subtotal'];
    $item_count = (int) $data['item_count'];
    $box_price  = (float) $data['box']['box_price'];
} else {
    $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    if (!empty($ids)) {
        $ids_str = implode(',', $ids);
        $q = $conn->query("SELECT c.quantity, " . catalog_price_sql('p.') . " AS price
                           FROM carts c JOIN products p ON c.product_id = p.id
                           WHERE c.user_id = $user_id AND c.id IN ($ids_str)
                             AND p.is_active = TRUE");
        while ($q && $r = $q->fetch_assoc()) {
            $subtotal   += (float) $r['price'] * (int) $r['quantity'];
            $item_count += (int) $r['quantity'];
        }
    }
}
$subtotal = round($subtotal, 2);
$base_ship = ($subtotal > 0 && $subtotal < 300) ? 50.0 : 0.0;

// ---- manage the session-held code ----
if ($action === 'remove') {
    unset($_SESSION[$skey]);
} elseif ($action === 'apply') {
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    if ($code === '') {
        unset($_SESSION[$skey]);
    } else {
        // validate before storing so a bad code isn't remembered
        $test = promo_evaluate($conn, $user_id, [
            'scope' => $scope, 'subtotal' => $subtotal,
            'shipping_fee' => $base_ship, 'item_count' => $item_count, 'code' => $code,
        ]);
        if ($test['code_error'] !== '') {
            $eval = promo_evaluate($conn, $user_id, [
                'scope' => $scope, 'subtotal' => $subtotal,
                'shipping_fee' => $base_ship, 'item_count' => $item_count,
                'code' => $_SESSION[$skey] ?? null,
            ]);
            echo json_encode(promo_summary_json($eval, $scope, $box_price, $item_count, $test['code_error']));
            exit();
        }
        $_SESSION[$skey] = $code;
    }
}

$eval = promo_evaluate($conn, $user_id, [
    'scope' => $scope, 'subtotal' => $subtotal,
    'shipping_fee' => $base_ship, 'item_count' => $item_count,
    'code' => $_SESSION[$skey] ?? null,
]);
// a stored code that has since become invalid: drop it silently
if (($_SESSION[$skey] ?? '') !== '' && $eval['code'] === '' && $eval['code_error'] !== '') {
    unset($_SESSION[$skey]);
}

echo json_encode(promo_summary_json($eval, $scope, $box_price, $item_count, ''));


function promo_summary_json($eval, $scope, $box_price, $item_count, $error_override) {
    $grand = $eval['final_total'] + (($scope === 'box') ? round((float) $box_price, 2) : 0.0);
    return [
        'ok'              => true,
        'scope'           => $scope,
        'item_count'      => $item_count,
        'subtotal'        => round($eval['subtotal'], 2),
        'discount'        => round($eval['discount'], 2),
        'shipping_fee'    => round($eval['shipping_fee'], 2),
        'shipping_waived' => (bool) $eval['shipping_waived'],
        'box_price'       => round((float) $box_price, 2),
        'total'           => round($grand, 2),
        'lines'           => $eval['lines'],
        'code'            => $eval['code'],
        'code_error'      => $error_override !== '' ? $error_override : $eval['code_error'],
        'code_min_spend'    => round((float) $eval['code_min_spend'], 2),
        'code_max_discount' => round((float) $eval['code_max_discount'], 2),
        'free_item'       => $eval['free_item'],
        'free_item_nudge' => $eval['free_item_nudge'],
    ];
}
