<?php
// api/services/ProductService.php

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/../../catalog_lib.php';

class ProductService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        catalog_ensure_schema($conn);
    }

    // Sale-aware pricing: keep `price` meaning "what you pay right now"
    // (mirrors catalog_price_sql('p.') on the website); the original price
    // moves to `list_price` for a strikethrough, `on_sale` flags the badge.
    private function applySalePricing(array &$row): void {
        $row['on_sale'] = catalog_on_sale($row);
        $row['list_price'] = $row['price'];
        $row['price'] = (string) catalog_effective_price($row);
    }
    
    // 📦 GET ALL PRODUCTS
public function getAll($params) {
    $this->ensureActiveColumn();

    $page = isset($params['page']) ? intval($params['page']) : 1;
    $limit = isset($params['limit']) ? intval($params['limit']) : 20;
    $offset = ($page - 1) * $limit;
    $search = isset($params['search']) ? $params['search'] : '';
    $category = isset($params['category']) ? $params['category'] : '';
    $order = isset($params['order']) ? $params['order'] : 'desc'; // Default to desc
    // Occasion Boxes / Baskets are also products; the storefront shop shows
    // only 'catalog' items unless a caller asks for another type explicitly.
    $type = isset($params['type']) ? $params['type'] : 'catalog';

    // Set ORDER BY based on parameter
    $order_by = ($order == 'asc') ? 'ASC' : 'DESC';

    // Customers only see active products in active categories.
    $sql = "SELECT * FROM products WHERE is_active = TRUE"
         . " AND category_id NOT IN (SELECT id FROM categories WHERE is_active = FALSE)";
    if (!empty($search)) {
        $sql .= " AND name ILIKE '%$search%'";
    }
    if (!empty($category)) {
        $sql .= " AND category_id = '$category'";
    }
    if (!empty($type)) {
        $sql .= " AND product_type = '" . $this->conn->real_escape_string($type) . "'";
    }
    
    // Get total count
    $count_sql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
    $count_result = $this->conn->query($count_sql);
    $total = $count_result->fetch_assoc()['total'];

    // Attach published-review aggregates to the row query (not the count query)
    $sql = str_replace(
        "SELECT *",
        "SELECT *, " .
        "(SELECT COALESCE(ROUND(AVG(rating), 1), 0) FROM product_reviews pr WHERE pr.product_id = products.id AND pr.status = 'published') AS avg_rating, " .
        "(SELECT COUNT(*) FROM product_reviews pr WHERE pr.product_id = products.id AND pr.status = 'published') AS review_count",
        $sql
    );

    // 🚀 ORDER BY: in stock first, then items on sale, then by ID
    $sale_live = "(sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price"
               . " AND (sale_ends IS NULL OR sale_ends > (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')))";
    $sql .= " ORDER BY CASE WHEN quantity > 0 THEN 0 ELSE 1 END,"
          . " CASE WHEN $sale_live THEN 0 ELSE 1 END, id $order_by";
    $sql .= " LIMIT $limit OFFSET $offset";
    $result = $this->conn->query($sql);
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $this->applySalePricing($row);
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

        $row = $result->fetch_assoc();
        $this->applySalePricing($row);
        sendSuccess($row);
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
    
    // 📚 GET CATEGORIES
    public function getCategories() {
        $this->ensureActiveColumn();
        $result = $this->conn->query("SELECT * FROM categories WHERE is_active = TRUE ORDER BY name ASC");
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        sendSuccess(['categories' => $categories]);
    }

    // Make sure the visibility columns exist before we filter on them.
    private function ensureActiveColumn() {
        static $ok = false;
        if ($ok) return;
        $ok = true;
        $c = $this->conn->query("SELECT 1 FROM information_schema.columns
                                 WHERE table_name = 'products' AND column_name = 'is_active'");
        if (!$c || $c->num_rows === 0) {
            $this->conn->query("ALTER TABLE products   ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE");
            $this->conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE");
        }
        $s = $this->conn->query("SELECT 1 FROM information_schema.columns
                                 WHERE table_name = 'products' AND column_name = 'sale_price'");
        if (!$s || $s->num_rows === 0) {
            $this->conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS sale_price NUMERIC(10,2)");
            $this->conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS sale_ends  TIMESTAMP");
        }
    }

    // Helper: Check if user is admin
    private function isAdmin($headers) {
        return AuthHelper::resolveIsAdmin($this->conn, $headers);
    }
}
?>