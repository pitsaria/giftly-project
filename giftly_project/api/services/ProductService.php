<?php
// api/services/ProductService.php

require_once 'config/database.php';

class ProductService {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // 📦 GET ALL PRODUCTS
public function getAll($params) {
    $page = isset($params['page']) ? intval($params['page']) : 1;
    $limit = isset($params['limit']) ? intval($params['limit']) : 20;
    $offset = ($page - 1) * $limit;
    $search = isset($params['search']) ? $params['search'] : '';
    $category = isset($params['category']) ? $params['category'] : '';
    $order = isset($params['order']) ? $params['order'] : 'desc'; // Default to desc
    
    // Set ORDER BY based on parameter
    $order_by = ($order == 'asc') ? 'ASC' : 'DESC';
    
    $sql = "SELECT * FROM products WHERE 1=1";
    if (!empty($search)) {
        $sql .= " AND name LIKE '%$search%'";
    }
    if (!empty($category)) {
        $sql .= " AND category_id = '$category'";
    }
    
    // Get total count
    $count_sql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
    $count_result = $this->conn->query($count_sql);
    $total = $count_result->fetch_assoc()['total'];
    
    // 🚀 ORDER BY: In stock first, then by ID (oldest or newest)
    $sql .= " ORDER BY CASE WHEN quantity > 0 THEN 0 ELSE 1 END, id $order_by";
    $sql .= " LIMIT $limit OFFSET $offset";
    $result = $this->conn->query($sql);
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    sendSuccess([
        'products' => $products,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => intval($total),
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}
    
    // 🔍 GET SINGLE PRODUCT
    public function getOne($id) {
        $sql = "SELECT * FROM products WHERE id = $id";
        $result = $this->conn->query($sql);
        
        if ($result->num_rows == 0) {
            sendError('Product not found', 404);
        }
        
        sendSuccess($result->fetch_assoc());
    }
    
    // ➕ CREATE PRODUCT (Admin only)
    public function create($input, $headers) {
        // Admin check
        if (!$this->isAdmin($headers)) {
            sendError('Unauthorized', 401);
        }
        
        $name = $input['name'] ?? '';
        $description = $input['description'] ?? '';
        $price = $input['price'] ?? 0;
        $quantity = $input['quantity'] ?? 0;
        $category_id = $input['category_id'] ?? null;
        $image = $input['image'] ?? '';
        
        if (empty($name) || empty($price)) {
            sendError('Name and price are required');
        }
        
        $sql = "INSERT INTO products (name, description, price, quantity, category_id, image) 
                VALUES ('$name', '$description', '$price', '$quantity', '$category_id', '$image')";
        
        if ($this->conn->query($sql)) {
            sendSuccess(['id' => $this->conn->insert_id], 'Product created successfully');
        } else {
            sendError('Failed to create product: ' . $this->conn->error);
        }
    }
    
    // ✏️ UPDATE PRODUCT (Admin only)
    public function update($id, $input, $headers) {
        if (!$this->isAdmin($headers)) {
            sendError('Unauthorized', 401);
        }
        
        $sets = [];
        foreach (['name', 'description', 'price', 'quantity', 'category_id', 'image'] as $field) {
            if (isset($input[$field])) {
                $sets[] = "$field = '{$input[$field]}'";
            }
        }
        
        if (empty($sets)) {
            sendError('No fields to update');
        }
        
        $sql = "UPDATE products SET " . implode(', ', $sets) . " WHERE id = $id";
        
        if ($this->conn->query($sql)) {
            sendSuccess(null, 'Product updated successfully');
        } else {
            sendError('Failed to update product: ' . $this->conn->error);
        }
    }
    
    // 🗑️ DELETE PRODUCT (Admin only)
    public function delete($id, $headers) {
        if (!$this->isAdmin($headers)) {
            sendError('Unauthorized', 401);
        }
        
        $sql = "DELETE FROM products WHERE id = $id";
        if ($this->conn->query($sql)) {
            sendSuccess(null, 'Product deleted successfully');
        } else {
            sendError('Failed to delete product: ' . $this->conn->error);
        }
    }
    
    // Helper: Check if user is admin
    private function isAdmin($headers) {
        $token = $headers['Authorization'] ?? '';
        if (empty($token)) return false;
        
        // Simple check (in production, verify JWT token)
        session_start();
        return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
    }
}
?>