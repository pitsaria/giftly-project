<?php
/**
 * Catalog helpers — product "type" support.
 *
 * Regular shop items, pre-made Occasion Boxes and pre-made Baskets are all
 * rows in `products`; a `product_type` column tells them apart so each has
 * its own storefront page while sharing cart / checkout / wishlist / orders.
 */

if (!function_exists('catalog_ensure_schema')) {

    /** Add products.product_type / is_active + categories.is_active (idempotent). */
    function catalog_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['catalog_schema_ok_v2'])) {
            return;
        }

        $c = $conn->query("SELECT 1 AS c FROM information_schema.columns
                           WHERE table_name = 'products' AND column_name = 'product_type'");
        if (!($c && $c->num_rows > 0)) {
            $conn->query("ALTER TABLE products
                          ADD COLUMN IF NOT EXISTS product_type VARCHAR(20) NOT NULL DEFAULT 'catalog'");
        }

        $c2 = $conn->query("SELECT 1 AS c FROM information_schema.columns
                            WHERE table_name = 'products' AND column_name = 'is_active'");
        if (!($c2 && $c2->num_rows > 0)) {
            $conn->query("ALTER TABLE products   ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE");
            $conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE");
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['catalog_schema_ok_v2'] = true;
        }
    }

    /** Boolean-ish helper for pg 't'/'f'/1/0/true values. */
    function catalog_is_active($v) {
        return !($v === false || $v === 'f' || $v === '0' || $v === 0 || $v === null);
    }

    /**
     * SQL fragment (starts with " AND ") that limits a `products` query to items
     * customers should see: the product is active AND its category isn't
     * deactivated. Pass the table alias with a trailing dot, e.g. "p.".
     */
    function catalog_visible_filter($alias = '') {
        return " AND {$alias}is_active = TRUE"
             . " AND {$alias}category_id NOT IN (SELECT id FROM categories WHERE is_active = FALSE)";
    }

    /** type key => admin-facing label */
    function catalog_types() {
        return [
            'catalog'      => 'Shop product',
            'occasion_box' => 'Occasion Box',
            'basket'       => 'Basket',
        ];
    }

    /** Normalise a submitted type to a known key ('catalog' fallback). */
    function catalog_type_key($k) {
        $t = catalog_types();
        return isset($t[$k]) ? $k : 'catalog';
    }
}
