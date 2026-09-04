<?php
// api/services/BoxService.php
//
// Build-a-Box for the mobile app. Ports the logic in build-a-box.php,
// build_a_box_products.php, box_actions.php and box_checkout.php onto the
// token-authenticated API. The website's own PHP pages are untouched.

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/../../build_a_box_lib.php';
require_once __DIR__ . '/../../catalog_lib.php';
require_once __DIR__ . '/../../reviews_lib.php';
require_once __DIR__ . '/../../paymongo_lib.php';

class BoxService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        bab_ensure_schema($conn);
        catalog_ensure_schema($conn);
        reviews_ensure_schema($conn);
        pay_ensure_schema($conn);
    }

    private function getUserId($headers) {
        return AuthHelper::resolveUserId($this->conn, $headers);
    }

    // GET box/sizes  — box sizes + the available letter card styles
    public function sizes() {
        $styles = [];
        foreach (bab_card_styles() as $key => $s) {
            $styles[] = ['key' => $key, 'label' => $s['label'], 'emoji' => $s['emoji']];
        }
        sendSuccess([
            'sizes' => bab_box_sizes($this->conn),
            'card_styles' => $styles,
        ]);
    }

    // GET box/products?size_id=&search=&category=&page=
    public function products($params) {
        $size_id  = isset($params['size_id']) ? intval($params['size_id']) : 0;
        $search   = isset($params['search']) ? trim($params['search']) : '';
        $category = isset($params['category']) ? intval($params['category']) : 0;
        $page     = isset($params['page']) ? max(1, intval($params['page'])) : 1;
        $limit    = 12;
        $offset   = ($page - 1) * $limit;

        $size = bab_box_size($this->conn, $size_id);
        if (!$size) {
            sendError('Invalid box size.');
        }

        $where = "pbs.box_size_id = $size_id AND p.quantity > 0 AND p.product_type = 'catalog'"
               . (function_exists('catalog_visible_filter') ? catalog_visible_filter('p.') : '');
        if ($search !== '') {
            $s = $this->conn->real_escape_string($search);
            $where .= " AND p.name ILIKE '%$s%'";
        }
        if ($category > 0) {
            $where .= " AND p.category_id = $category";
        }

        $count_res = $this->conn->query("
            SELECT COUNT(*) AS total
            FROM products p
            JOIN product_box_sizes pbs ON pbs.product_id = p.id
            WHERE $where
        ");
        $total = $count_res ? intval($count_res->fetch_assoc()['total']) : 0;

        $res = $this->conn->query("
            SELECT p.id, p.name, p.description, p.price, p.image, p.quantity, p.category_id
            FROM products p
            JOIN product_box_sizes pbs ON pbs.product_id = p.id
            WHERE $where
            ORDER BY p.id ASC
            LIMIT $limit OFFSET $offset
        ");

        $products = [];
        while ($res && $row = $res->fetch_assoc()) {
            $rv = reviews_summary($this->conn, intval($row['id']));
            $products[] = [
                'id'           => intval($row['id']),
                'name'         => $row['name'],
                'description'  => $row['description'],
                'price'        => floatval($row['price']),
                'image'        => $row['image'],
                'quantity'     => intval($row['quantity']),
                'category_id'  => intval($row['category_id']),
                'rating'       => round((float) $rv['avg'], 1),
                'rating_count' => (int) $rv['count'],
            ];
        }

        sendSuccess([
            'products' => $products,
            'pagination' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => max(1, ceil($total / $limit)),
            ],
        ]);
    }

    // GET boxes  — the user's saved / in-cart boxes
    public function listBoxes($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }

        $boxes = [];
        $res = $this->conn->query("SELECT id FROM boxes WHERE user_id = $user_id
                                   AND status IN ('saved','in_cart')
                                   ORDER BY updated_at DESC, id DESC");
        while ($res && $r = $res->fetch_assoc()) {
            $b = bab_load_box($this->conn, intval($r['id']), $user_id);
            if ($b) {
                $boxes[] = $this->shapeBox($b);
            }
        }
        sendSuccess(['boxes' => $boxes]);
    }

    // GET boxes/single?id=
    public function getBox($id, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }
        $data = bab_load_box($this->conn, intval($id), $user_id);
        if (!$data) {
            sendError('Box not found', 404);
        }
        sendSuccess($this->shapeBox($data));
    }

    // DELETE boxes/single?id=
    public function deleteBox($id, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }
        $id = intval($id);
        $this->conn->query("DELETE FROM boxes WHERE id = $id AND user_id = $user_id");
        sendSuccess(null, 'Box deleted.');
    }

    // POST boxes  — create / update a box (mirrors box_actions.php action=save)
    // body: { box_id, size_id, letter, card_style, status: 'saved'|'in_cart', items: [{product_id, quantity}] }
    public function save($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }

        $box_id  = isset($input['box_id']) ? intval($input['box_id']) : 0;
        $size_id = isset($input['size_id']) ? intval($input['size_id']) : 0;
        $letter  = isset($input['letter']) ? trim($input['letter']) : '';
        $card    = bab_card_style_key($input['card_style'] ?? 'simple');
        $status  = (isset($input['status']) && $input['status'] === 'in_cart') ? 'in_cart' : 'saved';
        $raw     = isset($input['items']) && is_array($input['items']) ? $input['items'] : null;

        $size = bab_box_size($this->conn, $size_id);
        if (!$size) {
            sendError('Please choose a valid box size.');
        }
        if (!is_array($raw) || count($raw) === 0) {
            sendError('Add at least one item to your box.');
        }
        if (mb_strlen($letter) > 1000) {
            $letter = mb_substr($letter, 0, 1000);
        }

        $wanted = [];
        foreach ($raw as $it) {
            $pid = isset($it['product_id']) ? intval($it['product_id']) : 0;
            $qty = isset($it['quantity']) ? intval($it['quantity']) : 0;
            if ($pid <= 0 || $qty <= 0) continue;
            $wanted[$pid] = ($wanted[$pid] ?? 0) + $qty;
        }
        if (count($wanted) === 0) {
            sendError('Add at least one item to your box.');
        }

        $ids = implode(',', array_map('intval', array_keys($wanted)));
        $check = $this->conn->query("
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
            if (!isset($found[$pid])) {
                sendError('One of the selected items no longer exists.');
            }
            $row = $found[$pid];
            if (intval($row['eligible']) !== 1) {
                sendError($row['name'] . ' is not allowed in a ' . strtolower($size['name']) . '.');
            }
            $stock = intval($row['stock']);
            if ($stock <= 0) {
                sendError($row['name'] . ' is out of stock.');
            }
            if ($qty > $stock) $qty = $stock;
            $final[$pid] = $qty;
            $total += $qty;
        }

        if ($total > $size['max_items']) {
            sendError('A ' . strtolower($size['name']) . ' holds up to ' . $size['max_items'] .
                      ' items — your box has ' . $total . '.');
        }

        $letter_esc = $this->conn->real_escape_string($letter);

        $this->conn->begin_transaction();
        try {
            if ($box_id > 0) {
                $owns = $this->conn->query("SELECT id FROM boxes WHERE id = $box_id AND user_id = $user_id");
                if (!$owns || $owns->num_rows === 0) throw new Exception('Box not found.');
                $this->conn->query("UPDATE boxes
                                    SET box_size_id = $size_id, letter = '$letter_esc', card_style = '$card',
                                        status = '$status', updated_at = CURRENT_TIMESTAMP
                                    WHERE id = $box_id AND user_id = $user_id");
                $this->conn->query("DELETE FROM box_items WHERE box_id = $box_id");
            } else {
                $this->conn->query("INSERT INTO boxes (user_id, box_size_id, letter, card_style, status)
                                    VALUES ($user_id, $size_id, '$letter_esc', '$card', '$status')");
                $box_id = intval($this->conn->insert_id);
                if ($box_id <= 0) {
                    $q = $this->conn->query("SELECT id FROM boxes WHERE user_id = $user_id
                                             ORDER BY id DESC LIMIT 1");
                    $box_id = intval($q->fetch_assoc()['id']);
                }
            }

            foreach ($final as $pid => $qty) {
                $pid = intval($pid); $qty = intval($qty);
                $this->conn->query("INSERT INTO box_items (box_id, product_id, quantity)
                                    VALUES ($box_id, $pid, $qty)");
            }

            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollback();
            sendError('Could not save your box. ' . $e->getMessage());
        }

        sendSuccess([
            'box_id'     => $box_id,
            'box_status' => $status,
        ], $status === 'in_cart' ? 'Box added to cart.' : 'Box saved.');
    }

    // POST boxes/checkout  — place an order for a box (mirrors box_checkout.php)
    // body: { box_id, fullname, sender_phone, address, city, payment_method,
    //         delivery_date, delivery_time, delivery_type, recipient_name,
    //         recipient_phone, card_number, card_holder, card_expiry, card_cvc }
    public function checkout($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }

        $box_id = isset($input['box_id']) ? intval($input['box_id']) : 0;

        $this->conn->begin_transaction();
        try {
            $bres = $this->conn->query("SELECT b.*, s.name AS size_name, s.price AS box_price
                                        FROM boxes b JOIN box_sizes s ON s.id = b.box_size_id
                                        WHERE b.id = $box_id AND b.user_id = $user_id FOR UPDATE");
            if (!$bres || $bres->num_rows === 0) throw new Exception('Box not found.');
            $box = $bres->fetch_assoc();
            if ($box['status'] === 'ordered') throw new Exception('This box has already been ordered.');

            $ires = $this->conn->query("SELECT bi.product_id, bi.quantity, p.name, p.price, p.quantity AS stock, p.is_active
                                        FROM box_items bi JOIN products p ON p.id = bi.product_id
                                        WHERE bi.box_id = $box_id FOR UPDATE");
            $items = [];
            $stock_errors = [];
            while ($ires && $r = $ires->fetch_assoc()) {
                $req = intval($r['quantity']);
                $av  = intval($r['stock']);
                if (array_key_exists('is_active', $r) && in_array($r['is_active'], [false, 'f', '0', 0], true)) {
                    $stock_errors[] = "{$r['name']} is no longer available.";
                } elseif ($req > $av) {
                    $stock_errors[] = $av <= 0
                        ? "{$r['name']} is out of stock."
                        : "{$r['name']}: only {$av} left (box needs {$req}).";
                } else {
                    $items[] = $r;
                }
            }
            if (count($items) === 0) throw new Exception('Your box has no available items.');
            if (!empty($stock_errors)) {
                $this->conn->rollback();
                sendError(implode(' ', $stock_errors));
            }

            $fullname      = $this->conn->real_escape_string($input['fullname'] ?? '');
            $sender_phone  = $this->conn->real_escape_string($input['sender_phone'] ?? '');
            $address       = $this->conn->real_escape_string($input['address'] ?? '');
            $city          = $this->conn->real_escape_string($input['city'] ?? '');
            $payment       = $this->conn->real_escape_string($input['payment_method'] ?? 'cod');
            $delivery_date = $this->conn->real_escape_string($input['delivery_date'] ?? date('Y-m-d', strtotime('+3 days')));
            $delivery_time = $this->conn->real_escape_string($input['delivery_time'] ?? '08:00:00');
            $delivery_type = isset($input['delivery_type']) ? $input['delivery_type'] : 'me';

            // Card payment: validate, keep only last 4 + holder name.
            $card_last4 = '';
            $card_holder = '';
            if ($payment === 'card') {
                $digits = preg_replace('/\D/', '', $input['card_number'] ?? '');
                if (strlen($digits) < 13 || strlen($digits) > 19) throw new Exception('Please enter a valid card number.');
                $card_holder_raw = trim($input['card_holder'] ?? '');
                if ($card_holder_raw === '') throw new Exception('Please enter the name on the card.');
                $exp = trim($input['card_expiry'] ?? '');
                if (!preg_match('#^(0[1-9]|1[0-2])\s*/\s*([0-9]{2})$#', $exp, $mm)) throw new Exception('Card expiry must be in MM/YY format.');
                $exp_y = 2000 + (int) $mm[2];
                $exp_m = (int) $mm[1];
                if ($exp_y < (int) date('Y') || ($exp_y === (int) date('Y') && $exp_m < (int) date('n'))) throw new Exception('That card has expired.');
                $cvc = preg_replace('/\D/', '', $input['card_cvc'] ?? '');
                if (strlen($cvc) < 3 || strlen($cvc) > 4) throw new Exception('Please enter a valid CVC.');
                $card_last4 = substr($digits, -4);
                $card_holder = $this->conn->real_escape_string(mb_substr($card_holder_raw, 0, 120));
            }

            // The box letter (with its card style) becomes the order's gift message.
            $letter_txt = trim($box['letter'] ?? '');
            $styles = bab_card_styles();
            $skey = bab_card_style_key($box['card_style'] ?? 'simple');
            $gm = $letter_txt;
            if ($skey !== 'simple') {
                $hdr = $styles[$skey]['emoji'] . ' ' . $styles[$skey]['label'] . ' card';
                $gm = $letter_txt === '' ? $hdr : $hdr . "\n\n" . $letter_txt;
            }
            $gift_message = $this->conn->real_escape_string($gm);

            if ($delivery_type === 'recipient') {
                $recipient       = $this->conn->real_escape_string($input['recipient_name'] ?? '');
                $recipient_phone = $this->conn->real_escape_string($input['recipient_phone'] ?? '');
            } else {
                $recipient = ''; $recipient_phone = '';
            }

            $total_amount = 0;
            foreach ($items as $it) $total_amount += $it['price'] * $it['quantity'];
            $shipping_fee = ($total_amount > 0 && $total_amount < 300) ? 50 : 0;
            $grand_total  = $total_amount + $shipping_fee + floatval($box['box_price']);

            $sql = "INSERT INTO orders (user_id, total_amount, status, fullname, sender_phone, address, city,
                        recipient_name, recipient_phone, gift_message, payment_method, delivery_date, delivery_time,
                        card_last4, card_holder)
                    VALUES ($user_id, $grand_total, 'pending', '$fullname', '$sender_phone', '$address', '$city',
                        '$recipient', '$recipient_phone', '$gift_message', '$payment', '$delivery_date', '$delivery_time',
                        " . ($card_last4 !== '' ? "'$card_last4'" : 'NULL') . ", " . ($card_holder !== '' ? "'$card_holder'" : 'NULL') . ")";
            if (!$this->conn->query($sql)) throw new Exception('Failed to create order.');
            $order_id = intval($this->conn->insert_id);
            if ($order_id <= 0) {
                $order_id = intval($this->conn->query("SELECT id FROM orders WHERE user_id = $user_id ORDER BY id DESC LIMIT 1")->fetch_assoc()['id']);
            }

            foreach ($items as $it) {
                $pid = intval($it['product_id']); $q = intval($it['quantity']); $pr = floatval($it['price']);
                $this->conn->query("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES ($order_id, $pid, $q, $pr)");
                $this->conn->query("UPDATE products SET quantity = quantity - $q WHERE id = $pid");
            }

            $this->conn->query("UPDATE boxes SET status = 'ordered', updated_at = CURRENT_TIMESTAMP WHERE id = $box_id AND user_id = $user_id");

            $this->conn->commit();

            // --- ONLINE PAYMENT: hand off to PayMongo's hosted checkout ---
            $checkout_url = '';
            $pay_error = '';
            if ($payment === 'online'
                && function_exists('paymongo_configured') && paymongo_configured()) {
                $er = $this->conn->query("SELECT email FROM users WHERE id = $user_id");
                $email = ($er && $er->num_rows) ? ($er->fetch_assoc()['email'] ?? '') : '';
                $checkout_url = paymongo_create_checkout(
                    $this->conn, $order_id, (float) $grand_total,
                    $input['fullname'] ?? '', $email, $input['sender_phone'] ?? ''
                );
                if ($checkout_url === '') {
                    $pay_error = paymongo_last_error() ?: 'Payment could not be started.';
                }
            }

            sendSuccess([
                'order_id'      => $order_id,
                'grand_total'   => $grand_total,
                'payment'       => $payment,
                'checkout_url'  => $checkout_url,
                'pay_error'     => $pay_error,
                'delivery_date' => $input['delivery_date'] ?? $delivery_date,
                'delivery_time' => $input['delivery_time'] ?? $delivery_time,
                'address'       => ($input['address'] ?? '') . ', ' . ($input['city'] ?? ''),
                'recipient'     => $input['recipient_name'] ?? '',
                'size_name'     => $box['size_name'],
            ], 'Box on its way!');
        } catch (Exception $e) {
            $this->conn->rollback();
            sendError($e->getMessage());
        }
    }

    // Flatten bab_load_box()'s nested shape into one object for the app.
    private function shapeBox($data) {
        $box = $data['box'];
        $items = [];
        foreach ($data['items'] as $it) {
            $items[] = [
                'product_id'  => intval($it['product_id']),
                'name'        => $it['name'],
                'price'       => floatval($it['price']),
                'image'       => $it['image'],
                'quantity'    => intval($it['quantity']),
                'stock'       => intval($it['stock']),
                'unavailable' => $it['unavailable'],
            ];
        }
        return [
            'id'          => intval($box['id']),
            'box_size_id' => intval($box['box_size_id']),
            'size_name'   => $box['size_name'],
            'size_code'   => $box['size_code'] ?? '',
            'max_items'   => intval($box['max_items']),
            'box_price'   => floatval($box['box_price']),
            'letter'      => $box['letter'],
            'card_style'  => bab_card_style_key($box['card_style'] ?? 'simple'),
            'status'      => $box['status'],
            'updated_at'  => $box['updated_at'] ?? null,
            'item_count'  => intval($data['item_count']),
            'subtotal'    => floatval($data['subtotal']),
            'total'       => floatval($data['total']),
            'issues'      => $data['issues'],
            'items'       => $items,
        ];
    }
}
