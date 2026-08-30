<?php
// api/services/OrderService.php

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';

class OrderService {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // 📋 GET ORDERS
    public function getOrders($headers, $params) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }
        
        $limit = isset($params['limit']) ? intval($params['limit']) : 10;
        $offset = isset($params['offset']) ? intval($params['offset']) : 0;
        
        $sql = "SELECT * FROM orders WHERE user_id = $user_id ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
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
        
        $ids_string = implode(',', $selected_ids);
        $cart_result = $this->conn->query("SELECT c.product_id, c.quantity, p.price 
                                           FROM carts c 
                                           JOIN products p ON c.product_id = p.id 
                                           WHERE c.user_id = $user_id AND c.id IN ($ids_string)");
        
        $total_amount = 0;
        $items = [];
        while ($row = $cart_result->fetch_assoc()) {
            $total_amount += $row['price'] * $row['quantity'];
            $items[] = $row;
        }
        
        // Order details
        $fullname = $input['fullname'] ?? '';
        $address = $input['address'] ?? '';
        $city = $input['city'] ?? '';
        $payment_method = $input['payment_method'] ?? 'cod';
        $delivery_date = $input['delivery_date'] ?? date('Y-m-d', strtotime('+3 days'));
        $delivery_time = $input['delivery_time'] ?? '08:00:00';
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

        // Calculate shipping
        $shipping_fee = ($total_amount > 0 && $total_amount < 300) ? 50 : 0;
        $grand_total = $total_amount + $shipping_fee;

        $card_last4_sql = $card_last4 !== null ? "'" . $card_last4 . "'" : 'NULL';
        $card_holder_sql = $card_holder !== null ? "'" . $card_holder . "'" : 'NULL';

        // Insert order
        $sql = "INSERT INTO orders (user_id, total_amount, status, fullname, address, city,
                                    payment_method, delivery_date, delivery_time, gift_message,
                                    recipient_name, recipient_phone, sender_phone, card_last4, card_holder)
                VALUES ($user_id, $grand_total, 'pending', '$fullname', '$address', '$city',
                        '$payment_method', '$delivery_date', '$delivery_time', '$gift_message',
                        '$recipient_name', '$recipient_phone', '$sender_phone', $card_last4_sql, $card_holder_sql)";
        
        if ($this->conn->query($sql)) {
            $order_id = $this->conn->insert_id;
            
            // Insert order items
            foreach ($items as $item) {
                $this->conn->query("INSERT INTO order_items (order_id, product_id, quantity, price) 
                                    VALUES ($order_id, {$item['product_id']}, {$item['quantity']}, {$item['price']})");
                // Update stock
                $this->conn->query("UPDATE products SET quantity = quantity - {$item['quantity']} WHERE id = {$item['product_id']}");
            }
            
            // Clear cart
            $this->conn->query("DELETE FROM carts WHERE user_id = $user_id AND id IN ($ids_string)");
            
            sendSuccess(['order_id' => $order_id], 'Order placed successfully!');
        } else {
            sendError('Failed to place order: ' . $this->conn->error);
        }
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
        
        $items = $this->conn->query("SELECT oi.*, p.name, p.image 
                                     FROM order_items oi 
                                     JOIN products p ON oi.product_id = p.id 
                                     WHERE oi.order_id = $id");
        
        $order_items = [];
        while ($item = $items->fetch_assoc()) {
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