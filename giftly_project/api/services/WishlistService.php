<?php
// api/services/WishlistService.php
// Mirrors toggle_wishlist.php / profile_wishlist.php for standalone clients.

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';

class WishlistService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // GET wishlist
    public function getWishlist($headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $result = $this->conn->query(
            "SELECT w.id as wishlist_id, w.created_at, p.*
             FROM wishlist w
             JOIN products p ON w.product_id = p.id
             WHERE w.user_id = $user_id
             ORDER BY w.created_at DESC"
        );

        $in_stock = [];
        $out_of_stock = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['quantity'] > 0) {
                $in_stock[] = $row;
            } else {
                $out_of_stock[] = $row;
            }
        }

        sendSuccess([
            'in_stock' => $in_stock,
            'out_of_stock' => $out_of_stock,
            'total' => count($in_stock) + count($out_of_stock)
        ]);
    }

    // POST wishlist/toggle { product_id }
    public function toggle($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $product_id = intval($input['product_id'] ?? 0);
        if ($product_id <= 0) {
            sendError('Product ID is required');
            return;
        }

        $product_check = $this->conn->query("SELECT id FROM products WHERE id = $product_id");
        if (!$product_check || $product_check->num_rows == 0) {
            sendError('Product not found', 404);
            return;
        }

        $existing = $this->conn->query(
            "SELECT id FROM wishlist WHERE user_id = $user_id AND product_id = $product_id"
        );

        if ($existing->num_rows > 0) {
            $this->conn->query("DELETE FROM wishlist WHERE user_id = $user_id AND product_id = $product_id");
            sendSuccess(['action' => 'removed'], 'Removed from wishlist');
        } else {
            $this->conn->query("INSERT INTO wishlist (user_id, product_id) VALUES ($user_id, $product_id)");
            sendSuccess(['action' => 'added'], 'Added to wishlist');
        }
    }

    private function getUserId($headers) {
        return AuthHelper::resolveUserId($this->conn, $headers);
    }
}
