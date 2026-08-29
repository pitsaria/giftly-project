<?php
/**
 * AJAX endpoint for Build-a-Box create / update / delete / fetch.
 * POST body (form-encoded), param `action`:
 *   - save   : box_id(0=new), size_id, letter, status(saved|in_cart), items (JSON [{product_id,quantity}])
 *   - delete : box_id
 *   - get    : box_id
 * All responses JSON. Requires login.
 */
include 'db_connect.php';
include 'build_a_box_lib.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'code' => 'login_required', 'message' => 'Please log in.']);
    exit();
}

bab_ensure_schema($conn);

$user_id = intval($_SESSION['user_id']);
$action  = isset($_POST['action']) ? $_POST['action'] : '';

function bab_fail($msg, $code = 'error') {
    echo json_encode(['status' => 'error', 'code' => $code, 'message' => $msg]);
    exit();
}
function bab_ok($data = []) {
    echo json_encode(array_merge(['status' => 'success'], $data));
    exit();
}

/* ---------------------------------------------------------------- DELETE */
if ($action === 'delete') {
    $box_id = isset($_POST['box_id']) ? intval($_POST['box_id']) : 0;
    $conn->query("DELETE FROM boxes WHERE id = $box_id AND user_id = $user_id");
    bab_ok(['message' => 'Box deleted.']);
}

/* ------------------------------------------------------------------- GET */
if ($action === 'get') {
    $box_id = isset($_POST['box_id']) ? intval($_POST['box_id']) : 0;
    $data = bab_load_box($conn, $box_id, $user_id);
    if (!$data) bab_fail('Box not found.', 'not_found');
    bab_ok(['box' => $data]);
}

/* ------------------------------------------------------------------ SAVE */
if ($action === 'save') {
    $box_id  = isset($_POST['box_id']) ? intval($_POST['box_id']) : 0;
    $size_id = isset($_POST['size_id']) ? intval($_POST['size_id']) : 0;
    $letter  = isset($_POST['letter']) ? trim($_POST['letter']) : '';
    $status  = (isset($_POST['status']) && $_POST['status'] === 'in_cart') ? 'in_cart' : 'saved';
    $raw     = isset($_POST['items']) ? json_decode($_POST['items'], true) : null;

    $size = bab_box_size($conn, $size_id);
    if (!$size) bab_fail('Please choose a valid box size.');
    if (!is_array($raw) || count($raw) === 0) bab_fail('Add at least one item to your box.');
    if (mb_strlen($letter) > 1000) $letter = mb_substr($letter, 0, 1000);

    // Normalise + merge duplicate product ids
    $wanted = [];
    foreach ($raw as $it) {
        $pid = isset($it['product_id']) ? intval($it['product_id']) : 0;
        $qty = isset($it['quantity']) ? intval($it['quantity']) : 0;
        if ($pid <= 0 || $qty <= 0) continue;
        $wanted[$pid] = ($wanted[$pid] ?? 0) + $qty;
    }
    if (count($wanted) === 0) bab_fail('Add at least one item to your box.');

    // Validate every product: exists, eligible for this size, in stock
    $ids = implode(',', array_map('intval', array_keys($wanted)));
    $check = $conn->query("
        SELECT p.id, p.name, p.quantity AS stock,
               CASE WHEN pbs.product_id IS NULL THEN 0 ELSE 1 END AS eligible
        FROM products p
        LEFT JOIN product_box_sizes pbs
               ON pbs.product_id = p.id AND pbs.box_size_id = $size_id
        WHERE p.id IN ($ids)
    ");
    $found = [];
    while ($check && $r = $check->fetch_assoc()) {
        $found[intval($r['id'])] = $r;
    }

    $final = [];
    $total = 0;
    foreach ($wanted as $pid => $qty) {
        if (!isset($found[$pid])) bab_fail('One of the selected items no longer exists.');
        $row = $found[$pid];
        if (intval($row['eligible']) !== 1) {
            bab_fail($row['name'] . ' is not allowed in a ' . strtolower($size['name']) . '.');
        }
        $stock = intval($row['stock']);
        if ($stock <= 0) bab_fail($row['name'] . ' is out of stock.');
        if ($qty > $stock) $qty = $stock;
        $final[$pid] = $qty;
        $total += $qty;
    }

    if ($total > $size['max_items']) {
        bab_fail('A ' . strtolower($size['name']) . ' holds up to ' . $size['max_items'] .
                 ' items — your box has ' . $total . '.');
    }

    $letter_esc = $conn->real_escape_string($letter);

    $conn->begin_transaction();
    try {
        if ($box_id > 0) {
            $owns = $conn->query("SELECT id FROM boxes WHERE id = $box_id AND user_id = $user_id");
            if (!$owns || $owns->num_rows === 0) throw new Exception('Box not found.');
            $conn->query("UPDATE boxes
                          SET box_size_id = $size_id, letter = '$letter_esc',
                              status = '$status', updated_at = CURRENT_TIMESTAMP
                          WHERE id = $box_id AND user_id = $user_id");
            $conn->query("DELETE FROM box_items WHERE box_id = $box_id");
        } else {
            $conn->query("INSERT INTO boxes (user_id, box_size_id, letter, status)
                          VALUES ($user_id, $size_id, '$letter_esc', '$status')");
            $box_id = intval($conn->insert_id);
            if ($box_id <= 0) {
                $q = $conn->query("SELECT id FROM boxes WHERE user_id = $user_id
                                   ORDER BY id DESC LIMIT 1");
                $box_id = intval($q->fetch_assoc()['id']);
            }
        }

        foreach ($final as $pid => $qty) {
            $pid = intval($pid); $qty = intval($qty);
            $conn->query("INSERT INTO box_items (box_id, product_id, quantity)
                          VALUES ($box_id, $pid, $qty)");
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        bab_fail('Could not save your box. ' . $e->getMessage());
    }

    bab_ok([
        'box_id'  => $box_id,
        'status'  => $status,
        'message' => $status === 'in_cart' ? 'Box added to cart.' : 'Box saved.',
    ]);
}

bab_fail('Unknown action.');
