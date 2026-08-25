<?php
// api/services/CartService.php

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';

class CartService {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // 🛒 GET CART
    public function getCart($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        
        $sql = "SELECT c.id as cart_id, c.quantity,
                       p.id, p.name, p.description, p.price, p.image, p.category_id,
                       p.quantity as stock
                FROM carts c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = $user_id";
        
        $result = $this->conn->query($sql);
        $items = [];
        $total = 0;
        
        while ($row = $result->fetch_assoc()) {
            $subtotal = $row['price'] * $row['quantity'];
            $total += $subtotal;
            $row['subtotal'] = $subtotal;
            $items[] = $row;
        }
        
        sendSuccess([
            'items' => $items,
            'total' => $total,
            'item_count' => count($items)
        ]);
    }
    
    // ➕ ADD TO CART
    public function addToCart($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        
        $product_id = $input['product_id'] ?? 0;
        $quantity = $input['quantity'] ?? 1;
        
        if ($product_id <= 0) {
            sendError('Product ID is required');
            return;
        }
        
        // Check stock
        $stock_check = $this->conn->query("SELECT quantity FROM products WHERE id = $product_id");
        $stock = $stock_check->fetch_assoc();
        if ($stock['quantity'] < $quantity) {
            sendError('Not enough stock available');
            return;
        }
        
        // Check if already in cart
        $existing = $this->conn->query("SELECT * FROM carts WHERE user_id = $user_id AND product_id = $product_id");
        
        if ($existing->num_rows > 0) {
            $cart = $existing->fetch_assoc();
            $new_qty = $cart['quantity'] + $quantity;
            if ($new_qty > $stock['quantity']) {
                sendError('Cannot add more than available stock');
                return;
            }
            $this->conn->query("UPDATE carts SET quantity = $new_qty WHERE id = {$cart['id']}");
        } else {
            $this->conn->query("INSERT INTO carts (user_id, product_id, quantity) VALUES ($user_id, $product_id, $quantity)");
        }
        
        sendSuccess(null, 'Added to cart successfully');
    }
    
    // 🔄 UPDATE QUANTITY
    public function updateQuantity($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        
        $cart_id = $input['cart_id'] ?? 0;
        $action = $input['action'] ?? '';
        
        if ($cart_id <= 0 || empty($action)) {
            sendError('Cart ID and action are required');
            return;
        }
        
        $cart = $this->conn->query("SELECT c.*, p.quantity as stock FROM carts c JOIN products p ON c.product_id = p.id WHERE c.id = $cart_id AND c.user_id = $user_id");
        
        if ($cart->num_rows == 0) {
            sendError('Cart item not found', 404);
            return;
        }
        
        $cart_data = $cart->fetch_assoc();
        $new_qty = $cart_data['quantity'];
        
        if ($action == 'increase') {
            if ($new_qty + 1 > $cart_data['stock']) {
                sendError('Not enough stock available. Only ' . $cart_data['stock'] . ' items left.');
                return;
            }
            $new_qty++;
        } elseif ($action == 'decrease') {
            $new_qty--;
            if ($new_qty <= 0) {
                $this->conn->query("DELETE FROM carts WHERE id = $cart_id");
                sendSuccess(null, 'Item removed from cart');
                return;
            }
        }
        
        $this->conn->query("UPDATE carts SET quantity = $new_qty WHERE id = $cart_id");
        sendSuccess(['new_quantity' => $new_qty], 'Cart updated successfully');
    }
    
    // 🗑️ REMOVE FROM CART
    public function removeItem($params, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        
        $cart_id = $params['id'] ?? 0;
        
        if ($cart_id <= 0) {
            sendError('Cart ID is required');
            return;
        }
        
        $this->conn->query("DELETE FROM carts WHERE id = $cart_id AND user_id = $user_id");
        sendSuccess(null, 'Item removed from cart');
    }

    // 🚀 VERIFY STOCK BEFORE CHECKOUT
    public function verifyStock($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }
        
        // Validate input
        if (!isset($input['cart_ids']) || empty($input['cart_ids'])) {
            sendError('No cart items provided', 400);
            return;
        }
        
        // Sanitize cart IDs
        $cart_ids = array_map('intval', $input['cart_ids']);
        $ids_string = implode(',', $cart_ids);
        
        // Check stock for selected items
        $query = "SELECT c.id as cart_id, c.quantity as requested, p.id as product_id, p.name, p.quantity as available_stock 
                  FROM carts c 
                  JOIN products p ON c.product_id = p.id 
                  WHERE c.user_id = $user_id AND c.id IN ($ids_string)";
        
        $result = $this->conn->query($query);
        
        if (!$result) {
            sendError('Database error', 500);
            return;
        }
        
        $stock_issues = [];
        $can_proceed = true;
        $items_to_update = [];
        
        while ($row = $result->fetch_assoc()) {
            $requested = intval($row['requested']);
            $available = intval($row['available_stock']);
            $cart_id = $row['cart_id'];
            $product_name = $row['name'];
            
            if ($requested > $available) {
                $can_proceed = false;
                
                $issue = [
                    'cart_id' => $cart_id,
                    'product_name' => $product_name,
                    'requested' => $requested,
                    'available' => $available
                ];
                
                // If stock is 0 or less, remove the item
                if ($available <= 0) {
                    $issue['action'] = 'removed';
                    $items_to_update[] = "DELETE FROM carts WHERE id = $cart_id AND user_id = $user_id";
                } else {
                    // Update quantity to available stock
                    $issue['action'] = 'adjusted';
                    $issue['new_quantity'] = $available;
                    $items_to_update[] = "UPDATE carts SET quantity = $available WHERE id = $cart_id AND user_id = $user_id";
                }
                
                $stock_issues[] = $issue;
            }
        }
        
        // Execute all updates if there were issues
        if (!empty($items_to_update)) {
            foreach ($items_to_update as $update_query) {
                $this->conn->query($update_query);
            }
        }
        
        // Return response
        sendSuccess([
            'can_proceed' => $can_proceed,
            'has_issues' => !empty($stock_issues),
            'issues' => $stock_issues
        ]);
    }
    
    // Helper: Get user ID from Bearer token (mobile) or session (website)
    private function getUserId($headers) {
        return AuthHelper::resolveUserId($this->conn, $headers);
    }
}
?>