<?php
// api/services/CartService.php

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/../../catalog_lib.php';

class CartService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        catalog_ensure_schema($conn);
        cart_ensure_schema($conn);
    }

    // 🛒 GET CART
    public function getCart($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $sql = "SELECT c.id as cart_id, c.quantity, c.selected_color, c.selected_size,
                       p.id, p.name, p.description, p.price, p.sale_price, p.sale_ends, p.image, p.category_id,
                       p.quantity as stock, p.is_active,
                       COALESCE(c.variant_price, " . catalog_price_sql('p.') . ") AS variant_effective_price
                FROM carts c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = $user_id";

        $result = $this->conn->query($sql);
        $items = [];
        $total = 0;
        $item_count = 0;

        while ($row = $result->fetch_assoc()) {
            // Product deactivated by the shop while it sat in the cart — keep it
            // visible so the customer can remove it, but never count it.
            $unavailable = array_key_exists('is_active', $row)
                && in_array($row['is_active'], [false, 'f', '0', 0], true);
            $row['unavailable'] = $unavailable;
            // Sale-aware pricing: same rewrite as ProductService. A chosen
            // size's own price (variant_price) overrides the product's price.
            $row['on_sale'] = catalog_on_sale($row);
            $row['list_price'] = $row['price'];
            $row['price'] = (string) $row['variant_effective_price'];
            unset($row['variant_effective_price']);
            $subtotal = $unavailable ? 0 : $row['price'] * $row['quantity'];
            $total += $subtotal;
            $row['subtotal'] = $subtotal;
            $item_count += (int) $row['quantity'];
            $items[] = $row;
        }

        sendSuccess([
            'items' => $items,
            'total' => $total,
            'item_count' => $item_count
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

        // Occasion Box color/size (ignored for products that don't have them) —
        // the size's price is looked up server-side, never trusted from the
        // client. Mirrors giftly_project/add_to_cart_modal.php.
        $color = trim($input['color'] ?? '');
        $size  = trim($input['size'] ?? '');
        $variant_price = null;
        if ($size !== '') {
            $size_esc = $this->conn->real_escape_string($size);
            $sr = $this->conn->query("SELECT price FROM product_sizes WHERE product_id = $product_id AND size_name = '$size_esc'");
            if ($sr && $sr->num_rows > 0) {
                $variant_price = (float) $sr->fetch_assoc()['price'];
            } else {
                $size = ''; // not a real size option for this product — ignore it
            }
        }
        if ($color !== '') {
            $color_esc = $this->conn->real_escape_string($color);
            $cr = $this->conn->query("SELECT id FROM product_colors WHERE product_id = $product_id AND color_name = '$color_esc'");
            if (!$cr || $cr->num_rows === 0) $color = ''; // not a real color option — ignore it
        }
        $color_esc = $this->conn->real_escape_string($color);
        $size_esc  = $this->conn->real_escape_string($size);
        $variant_price_sql = $variant_price !== null ? (float) $variant_price : 'NULL';

        // Stock is shared across every color/size of a product, so the limit
        // check adds up every variant row already in this user's cart.
        $cart_total_q = $this->conn->query("SELECT COALESCE(SUM(quantity), 0) AS t FROM carts WHERE user_id = $user_id AND product_id = $product_id");
        $current_cart_qty = $cart_total_q ? (int) $cart_total_q->fetch_assoc()['t'] : 0;
        if ($current_cart_qty + $quantity > $stock['quantity']) {
            sendError('Cannot add more than available stock');
            return;
        }

        // Check if this exact variant is already in the cart
        $existing = $this->conn->query("SELECT * FROM carts WHERE user_id = $user_id AND product_id = $product_id
                                        AND selected_color = '$color_esc' AND selected_size = '$size_esc'");

        if ($existing->num_rows > 0) {
            $cart = $existing->fetch_assoc();
            $new_qty = $cart['quantity'] + $quantity;
            $this->conn->query("UPDATE carts SET quantity = $new_qty WHERE id = {$cart['id']}");
        } else {
            $this->conn->query("INSERT INTO carts (user_id, product_id, quantity, selected_color, selected_size, variant_price)
                                VALUES ($user_id, $product_id, $quantity, '$color_esc', '$size_esc', $variant_price_sql)");
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
        $query = "SELECT c.id as cart_id, c.quantity as requested, p.id as product_id, p.name, p.quantity as available_stock, p.is_active
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

            // Deactivated while in the cart — block, but leave it in the cart so
            // the customer removes it deliberately (mirrors verify_stock_ajax.php).
            $inactive = array_key_exists('is_active', $row)
                && in_array($row['is_active'], [false, 'f', '0', 0], true);
            if ($inactive) {
                $can_proceed = false;
                $stock_issues[] = [
                    'cart_id' => $cart_id,
                    'product_name' => $product_name,
                    'requested' => $requested,
                    'available' => 0,
                    'action' => 'blocked',
                    'unavailable' => true,
                ];
                continue;
            }

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