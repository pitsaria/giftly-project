<?php
// api/services/OrderService.php

require_once 'config/database.php';

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
        
        // Calculate shipping
        $shipping_fee = ($total_amount < 300) ? 50 : 0;
        $grand_total = $total_amount + $shipping_fee;
        
        // Insert order
        $sql = "INSERT INTO orders (user_id, total_amount, status, fullname, address, city, 
                                    payment_method, delivery_date, delivery_time, gift_message, 
                                    recipient_name, recipient_phone) 
                VALUES ($user_id, $grand_total, 'pending', '$fullname', '$address', '$city', 
                        '$payment_method', '$delivery_date', '$delivery_time', '$gift_message', 
                        '$recipient_name', '$recipient_phone')";
        
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
    
    // ❌ CANCEL ORDER
    public function cancelOrder($id, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
        }
        
        $sql = "UPDATE orders SET status = 'cancelled' WHERE id = $id AND user_id = $user_id AND status = 'pending'";
        if ($this->conn->query($sql) && $this->conn->affected_rows > 0) {
            sendSuccess(null, 'Order cancelled successfully');
        } else {
            sendError('Cannot cancel this order. Either it\'s already processed or you don\'t have permission.');
        }
    }
    
    // Helper: Get user ID from token
    private function getUserId($headers) {
        $token = $headers['Authorization'] ?? '';
        if (empty($token)) return null;
        
        session_start();
        return $_SESSION['user_id'] ?? null;
    }
}
?>