<?php
// api/services/OrderService.php

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/../../paymongo_lib.php';
require_once __DIR__ . '/../../mail_lib.php';
require_once __DIR__ . '/../../catalog_lib.php';
require_once __DIR__ . '/../../orders_lib.php';
require_once __DIR__ . '/../../promo_lib.php';
require_once __DIR__ . '/../../addons_lib.php';

class OrderService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        pay_ensure_schema($conn);
        catalog_ensure_schema($conn);
        cart_ensure_schema($conn);
        orders_ensure_schema($conn);
        promo_ensure_schema($conn);
        addons_ensure_schema($conn);
    }

    // 📋 GET ORDERS
    public function getOrders($headers, $params) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }

        // The website cancels abandoned online orders on page views; the app
        // never triggers that, so sweep here (cheap, throttled inside).
        if (function_exists('pay_sweep_stale')) {
            pay_sweep_stale($this->conn);
        }

        // No cap unless the caller explicitly asks for a page — mirrors
        // profile_orders.php's own unlimited "SELECT * FROM orders" on the
        // website. The mobile app's My Orders never passed limit/offset, so
        // this silently capped everyone's order history at 10.
        $limit = isset($params['limit']) ? intval($params['limit']) : null;
        $offset = isset($params['offset']) ? intval($params['offset']) : 0;

        $sql = "SELECT * FROM orders WHERE user_id = $user_id ORDER BY created_at DESC";
        if ($limit !== null) {
            $sql .= " LIMIT $limit OFFSET $offset";
        }
        $result = $this->conn->query($sql);
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        sendSuccess(['orders' => $orders]);
    }
    
    // 📝 CREATE ORDER
    public function createOrder($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }
        
        // Get selected cart items
        $selected_ids = $input['selected_ids'] ?? [];
        if (empty($selected_ids)) {
            sendError('No items selected');
        }
        
        $ids_string = implode(',', array_map('intval', $selected_ids));
        $cart_result = $this->conn->query("SELECT c.product_id, c.quantity, c.selected_color, c.selected_size,
                                                   COALESCE(c.variant_price, " . catalog_price_sql('p.') . ") AS price, p.name, p.is_active
                                           FROM carts c
                                           JOIN products p ON c.product_id = p.id
                                           WHERE c.user_id = $user_id AND c.id IN ($ids_string)");

        $total_amount = 0;
        $items = [];
        while ($row = $cart_result->fetch_assoc()) {
            // Pulled from sale while it sat in the cart — block the whole order.
            if (array_key_exists('is_active', $row)
                && in_array($row['is_active'], [false, 'f', '0', 0], true)) {
                sendError($row['name'] . ' is no longer available. Please remove it from your cart.');
            }
            $total_amount += $row['price'] * $row['quantity'];
            $items[] = $row;
        }
        if (count($items) === 0 || $total_amount <= 0) {
            sendError('Your cart is empty. Add at least one item before checking out.');
        }
        
        // Order details
        $fullname = $input['fullname'] ?? '';
        $address = $input['address'] ?? '';
        $city = $input['city'] ?? '';
        $payment_method = $input['payment_method'] ?? 'cod';
        $delivery_date = $input['delivery_date'] ?? date('Y-m-d', strtotime('+3 days'));
        $delivery_time = $input['delivery_time'] ?? '08:00:00';

        // Delivery hours are strictly 8:00 AM-8:00 PM, and the date must be at
        // least 3 days out (processing/prep time) — mirrors checkout_selected.php's
        // client-side clamp/note, enforced here server-side since neither the
        // mobile app nor a raw API call had this checked.
        $delivery_ts = strtotime("$delivery_date $delivery_time");
        if ($delivery_ts === false) {
            sendError('Invalid delivery date/time.');
        }
        $delivery_time_only = date('H:i:s', $delivery_ts);
        if ($delivery_time_only < '08:00:00' || $delivery_time_only > '20:00:00') {
            sendError('Delivery hours are between 8:00 AM and 8:00 PM.');
        }
        $min_delivery_date = date('Y-m-d', strtotime('+3 days'));
        if ($delivery_date < $min_delivery_date) {
            sendError('Delivery date must be at least 3 days out to allow for processing.');
        }

        $gift_message = $input['gift_message'] ?? '';
        $recipient_name = $input['recipient_name'] ?? null;
        $recipient_phone = $input['recipient_phone'] ?? null;
        $sender_phone = $input['sender_phone'] ?? null;

        // Card payment: validate here, but only ever keep the last 4 digits + name
        // (mirrors checkout_selected.php's card block).
        $card_last4 = null;
        $card_holder = null;
        if ($payment_method === 'card') {
            $card_digits = preg_replace('/\D/', '', $input['card_number'] ?? '');
            $card_holder_raw = trim($input['card_holder'] ?? '');
            $card_exp = trim($input['card_expiry'] ?? '');
            $card_cvc = preg_replace('/\D/', '', $input['card_cvc'] ?? '');
            $card_ok = strlen($card_digits) >= 13 && strlen($card_digits) <= 19
                && $card_holder_raw !== ''
                && preg_match('#^(0[1-9]|1[0-2])\s*/\s*([0-9]{2})$#', $card_exp)
                && strlen($card_cvc) >= 3 && strlen($card_cvc) <= 4;
            if (!$card_ok) {
                sendError('Please enter a valid card number, name, expiry (MM/YY) and CVC.');
            }
            $card_last4 = substr($card_digits, -4);
            $card_holder = $this->conn->real_escape_string(mb_substr($card_holder_raw, 0, 120));
        }

        // Gifts sent straight to a recipient must be paid online — no COD.
        if (trim((string) $recipient_name) !== '' && $payment_method === 'cod') {
            sendError("Cash on Delivery isn't available for gifts sent straight to a recipient. Please choose an online payment.");
        }

        // --- gift wrapping & add-ons (price always re-checked server-side, never trusted from input) ---
        [$addon_rows, $addon_total] = addons_resolve($this->conn, $input['addon_ids'] ?? []);

        // --- promos / discounts (re-evaluated server-side from the real cart) ---
        // Add-ons count toward the free-shipping threshold even though they're
        // never discounted — a PHP 199 flower plus a PHP 130 add-on is PHP 329.
        $item_count = array_sum(array_column($items, 'quantity'));
        $ship_basis = $total_amount + $addon_total;
        $base_shipping_fee = ($ship_basis > 0 && $ship_basis < 300) ? 50 : 0;
        $promo_code_input = isset($input['promo_code']) ? trim((string) $input['promo_code']) : null;
        $promo_eval = promo_evaluate($this->conn, $user_id, [
            'scope'        => 'products',
            'subtotal'     => $total_amount,
            'shipping_fee' => $base_shipping_fee,
            'item_count'   => $item_count,
            'code'         => $promo_code_input,
        ]);
        $discount_amount = $promo_eval['discount'];
        $grand_total = $promo_eval['final_total'] + $addon_total;
        $promo_code_sql = $promo_eval['code'] !== '' ? "'" . $this->conn->real_escape_string($promo_eval['code']) . "'" : 'NULL';
        $promo_id_sql = $promo_eval['code_id'] !== null ? (int) $promo_eval['code_id'] : 'NULL';

        $card_last4_sql = $card_last4 !== null ? "'" . $card_last4 . "'" : 'NULL';
        $card_holder_sql = $card_holder !== null ? "'" . $card_holder . "'" : 'NULL';

        // 🚨 Authoritative stock check: lock the product rows for the rest of
        // this request so two shoppers racing for the last unit can't both
        // succeed — the cart fetch above only reads and isn't atomic, and
        // (unlike checkout_selected.php) never compared quantity to stock at all.
        $product_ids = array_values(array_unique(array_map(function ($it) { return (int) $it['product_id']; }, $items)));
        $pid_list = implode(',', $product_ids);
        $this->conn->begin_transaction();
        $lock_res = $this->conn->query("SELECT id, name, quantity FROM products WHERE id IN ($pid_list) FOR UPDATE");
        $stock_by_id = [];
        while ($lock_res && $r = $lock_res->fetch_assoc()) {
            $stock_by_id[(int) $r['id']] = $r;
        }
        foreach ($items as $item) {
            $pid = (int) $item['product_id'];
            $available = isset($stock_by_id[$pid]) ? (int) $stock_by_id[$pid]['quantity'] : 0;
            if ((int) $item['quantity'] > $available) {
                $this->conn->rollback();
                $name = $stock_by_id[$pid]['name'] ?? ($item['name'] ?? 'An item');
                sendError("$name: only $available left in stock. Please update your cart and try again.");
            }
        }
        // Per-order cap: every color/size row of one product counts together.
        $qty_by_pid = [];
        foreach ($items as $item) {
            $pid = (int) $item['product_id'];
            $qty_by_pid[$pid] = ($qty_by_pid[$pid] ?? 0) + (int) $item['quantity'];
        }
        foreach ($qty_by_pid as $pid => $q) {
            $available = isset($stock_by_id[$pid]) ? (int) $stock_by_id[$pid]['quantity'] : 0;
            if ($q > catalog_order_limit($available)) {
                $this->conn->rollback();
                $name = $stock_by_id[$pid]['name'] ?? 'An item';
                sendError($available < catalog_max_per_order()
                    ? "$name: only $available left in stock. Please update your cart and try again."
                    : catalog_cap_message($name) . ' Please update your cart and try again.');
            }
        }

        // Insert order
        $sql = "INSERT INTO orders (user_id, total_amount, status, fullname, address, city,
                                    payment_method, delivery_date, delivery_time, gift_message,
                                    recipient_name, recipient_phone, sender_phone, card_last4, card_holder,
                                    promo_code, promo_id, discount_amount)
                VALUES ($user_id, $grand_total, 'pending', '$fullname', '$address', '$city',
                        '$payment_method', '$delivery_date', '$delivery_time', '$gift_message',
                        '$recipient_name', '$recipient_phone', '$sender_phone', $card_last4_sql, $card_holder_sql,
                        $promo_code_sql, $promo_id_sql, $discount_amount)";

        if ($this->conn->query($sql)) {
            $order_id = (int) $this->conn->insert_id;
            if ($order_id <= 0) {
                $q = $this->conn->query("SELECT id FROM orders WHERE user_id = $user_id ORDER BY id DESC LIMIT 1");
                $order_id = $q ? (int) $q->fetch_assoc()['id'] : 0;
            }

            // Insert order items
            foreach ($items as $item) {
                $item_color_esc = $this->conn->real_escape_string($item['selected_color'] ?? '');
                $item_size_esc  = $this->conn->real_escape_string($item['selected_size'] ?? '');
                $this->conn->query("INSERT INTO order_items (order_id, product_id, quantity, price, selected_color, selected_size)
                                    VALUES ($order_id, {$item['product_id']}, {$item['quantity']}, {$item['price']}, '$item_color_esc', '$item_size_esc')");
                // Update stock
                $this->conn->query("UPDATE products SET quantity = quantity - {$item['quantity']} WHERE id = {$item['product_id']}");
            }

            foreach ($addon_rows as $arow) {
                $this->conn->query("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES ($order_id, {$arow['id']}, 1, {$arow['price']})");
                $this->conn->query("UPDATE products SET quantity = quantity - 1 WHERE id = {$arow['id']}");
            }

            // Free gift (buy N + 1 free) — only if the freebie is still in stock
            $free_item_name = null;
            if (!empty($promo_eval['free_item'])) {
                $fip = (int) $promo_eval['free_item']['product_id'];
                $this->conn->query("UPDATE products SET quantity = quantity - 1 WHERE id = $fip AND quantity > 0");
                if ($this->conn->affected_rows > 0) {
                    $this->conn->query("INSERT INTO order_items (order_id, product_id, quantity, price)
                                        VALUES ($order_id, $fip, 1, 0)");
                    $this->conn->query("UPDATE orders SET free_item_product_id = $fip WHERE id = $order_id");
                    $free_item_name = $promo_eval['free_item']['name'];
                }
            }
            promo_record($this->conn, $promo_eval, $user_id, $order_id);

            // Clear cart
            $this->conn->query("DELETE FROM carts WHERE user_id = $user_id AND id IN ($ids_string)");

            $this->conn->commit();

            // --- ONLINE PAYMENT: open a PayMongo hosted checkout ---
            $checkout_url = '';
            $pay_error = '';
            if ($payment_method === 'online'
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
            } elseif ($payment_method === 'cod' && function_exists('send_order_email')) {
                @send_order_email($this->conn, $order_id, false);
            }

            sendSuccess([
                'order_id'         => $order_id,
                'checkout_url'     => $checkout_url,
                'pay_error'        => $pay_error,
                'discount_amount'  => $discount_amount,
                'promo_code'       => $promo_eval['code'],
                'free_item_name'   => $free_item_name,
            ], 'Order placed successfully!');
        } else {
            $this->conn->rollback();
            sendError('Failed to place order: ' . $this->conn->error);
        }
    }

    // GET orders/payment?id=[&url=1]  — payment status; add url=1 to also mint a
    // fresh PayMongo checkout URL for an unpaid online order ("Pay now" /
    // "reopen"). Polling omits url=1 to avoid a PayMongo call every few seconds.
    public function paymentStatus($params, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }
        $id = intval($params['id'] ?? 0);
        $r = $this->conn->query("SELECT * FROM orders WHERE id = $id AND user_id = $user_id");
        $order = ($r && $r->num_rows) ? $r->fetch_assoc() : null;
        if (!$order) {
            sendError('Order not found', 404);
        }

        $payment_status = $order['payment_status'] ?? 'unpaid';
        $checkout_url = '';
        if (!empty($params['url'])
            && $payment_status !== 'paid'
            && ($order['status'] ?? '') !== 'cancelled'
            && ($order['payment_method'] ?? 'cod') !== 'cod'
            && function_exists('paymongo_configured') && paymongo_configured()) {
            $er = $this->conn->query("SELECT email FROM users WHERE id = $user_id");
            $email = ($er && $er->num_rows) ? ($er->fetch_assoc()['email'] ?? '') : '';
            $checkout_url = paymongo_create_checkout(
                $this->conn, $id, (float) $order['total_amount'],
                $order['fullname'] ?? '', $email, $order['sender_phone'] ?? ''
            );
        }

        sendSuccess([
            'payment_status' => $payment_status,
            'status'         => $order['status'] ?? 'pending',
            'checkout_url'   => $checkout_url,
        ]);
    }
    
    // 🔍 GET ORDER DETAILS
    public function getOrderDetails($id, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }
        
        $order = $this->conn->query("SELECT * FROM orders WHERE id = $id AND user_id = $user_id");
        if ($order->num_rows == 0) {
            sendError('Order not found', 404);
        }
        
        $order_data = $order->fetch_assoc();
        
        // Same color-photo swap as CartService::getCart() — a chosen Occasion
        // Box color shows its own photo instead of the product's base image.
        // whats_inside is included raw (one line per item) for Occasion
        // Box/Basket items — the client splits it the same way
        // catalog_whats_inside_lines() does server-side elsewhere.
        $items = $this->conn->query("SELECT oi.*, p.name, p.image, p.whats_inside, pc.image AS color_image
                                     FROM order_items oi
                                     JOIN products p ON oi.product_id = p.id
                                     LEFT JOIN product_colors pc ON pc.product_id = oi.product_id
                                        AND pc.color_name = oi.selected_color AND oi.selected_color <> ''
                                     WHERE oi.order_id = $id");

        $order_items = [];
        while ($item = $items->fetch_assoc()) {
            if (!empty($item['color_image'])) {
                $item['image'] = $item['color_image'];
            }
            unset($item['color_image']);
            $order_items[] = $item;
        }
        
        $order_data['items'] = $order_items;
        sendSuccess($order_data);
    }
    
    // ❌ REQUEST ORDER CANCELLATION (admin must approve)
    public function cancelOrder($id, $input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }

        $id = intval($id);
        $reason = is_array($input) && isset($input['reason']) ? trim($input['reason']) : '';
        $reason = mb_substr($reason, 0, 1000);
        $reason_esc = $this->conn->real_escape_string($reason !== '' ? $reason : 'Requested via app');

        $sql = "UPDATE orders
                SET cancel_status = 'requested', cancel_reason = '$reason_esc',
                    cancel_requested_at = CURRENT_TIMESTAMP, cancel_reviewed_at = NULL, cancel_admin_note = NULL
                WHERE id = $id AND user_id = $user_id
                  AND status = 'pending' AND cancel_status IN ('none', 'rejected')";
        if ($this->conn->query($sql) && $this->conn->affected_rows > 0) {
            sendSuccess(null, 'Cancellation request submitted. An admin will review it shortly.');
        } else {
            sendError('Cannot request cancellation for this order.');
        }
    }
    
    // ✅ CONFIRM ORDER RECEIVED (unlocks reviewing the items)
    public function markReceived($id, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }

        $id = intval($id);
        $this->conn->query("UPDATE orders SET received_at = CURRENT_TIMESTAMP
                            WHERE id = $id AND user_id = $user_id
                              AND status = 'delivered' AND received_at IS NULL");
        if ($this->conn->affected_rows > 0) {
            sendSuccess(null, 'Thanks for confirming! You can now review the items you received.');
        } else {
            sendError('Could not update this order.');
        }
    }

    // Helper: Get user ID from Bearer token (mobile) or session (website)
    private function getUserId($headers) {
        return AuthHelper::resolveUserId($this->conn, $headers);
    }
}
?>