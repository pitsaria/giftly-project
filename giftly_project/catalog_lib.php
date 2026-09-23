<?php
/**
 * Catalog helpers — product "type" support.
 *
 * Regular shop items, pre-made Occasion Boxes and pre-made Baskets are all
 * rows in `products`; a `product_type` column tells them apart so each has
 * its own storefront page while sharing cart / checkout / wishlist / orders.
 */

if (!function_exists('catalog_ensure_schema')) {

    /**
     * Store-wide cap on how many of ONE product a customer can buy in a single
     * order (all colors/sizes of a product count together). Change it here.
     */
    function catalog_max_per_order() {
        return 5;
    }

    /** What a customer can actually buy of a product right now: the cap, or less if stock is lower. */
    function catalog_order_limit($stock) {
        return max(0, min((int) $stock, catalog_max_per_order()));
    }

    /** Message for a customer who asks for more than the cap. */
    function catalog_cap_message($name = '') {
        $n = catalog_max_per_order();
        return ($name !== '' ? "$name: " : '') . "Limit of $n per order for each item.";
    }

    /**
     * Add-to-cart guard shared by every add path: how many more of a product a
     * customer may add given what's already in their cart. Returns
     * ['ok'=>bool, 'error'=>string|null, 'allowed'=>int].
     */
    function catalog_check_add($stock, $in_cart, $adding, $name = '') {
        $stock = (int) $stock; $in_cart = (int) $in_cart; $adding = (int) $adding;
        if ($stock <= 0) {
            return ['ok' => false, 'error' => 'Product out of stock', 'allowed' => 0];
        }
        $allowed = max(0, catalog_order_limit($stock) - $in_cart);
        if ($adding > $allowed) {
            $error = $in_cart + $adding > $stock
                ? "You've reached the maximum available stock for this product. Only $stock items available."
                : catalog_cap_message($name);
            return ['ok' => false, 'error' => $error, 'allowed' => $allowed];
        }
        return ['ok' => true, 'error' => null, 'allowed' => $allowed];
    }

    /**
     * Trim a customer's cart rows so no product exceeds the per-order limit
     * (cap or stock, whichever is lower), counting every color/size row of a
     * product together. Pass $cart_ids to only look at the rows going into one
     * order, or null for the whole cart. Newest rows are trimmed first.
     * Returns one entry per trimmed product:
     *   ['name'=>, 'requested'=>, 'allowed'=>, 'capped'=>bool]  (capped = the
     *   cap is what bit, not stock — the caller words its message accordingly).
     */
    function catalog_enforce_order_cap($conn, $user_id, $cart_ids = null) {
        $user_id = (int) $user_id;
        $where = "c.user_id = $user_id";
        if (is_array($cart_ids)) {
            $ids = implode(',', array_filter(array_map('intval', $cart_ids)));
            if ($ids === '') return [];
            $where .= " AND c.id IN ($ids)";
        }
        $res = $conn->query("SELECT c.id, c.product_id, c.quantity, p.name, p.quantity AS stock
                             FROM carts c JOIN products p ON p.id = c.product_id
                             WHERE $where ORDER BY c.id DESC");
        if (!$res) return [];
        $by_product = [];
        while ($r = $res->fetch_assoc()) {
            $by_product[(int) $r['product_id']][] = $r;
        }
        $adjusted = [];
        foreach ($by_product as $rows) {
            $total = array_sum(array_map(function ($r) { return (int) $r['quantity']; }, $rows));
            $stock = (int) $rows[0]['stock'];
            $limit = catalog_order_limit($stock);
            if ($stock <= 0 || $total <= $limit) continue; // out-of-stock rows are handled by the stock checks
            $excess = $total - $limit;
            foreach ($rows as $r) { // newest first
                if ($excess <= 0) break;
                $q = (int) $r['quantity'];
                $take = min($q, $excess);
                if ($q - $take <= 0) {
                    $conn->query("DELETE FROM carts WHERE id = " . (int) $r['id'] . " AND user_id = $user_id");
                } else {
                    $conn->query("UPDATE carts SET quantity = " . ($q - $take) . " WHERE id = " . (int) $r['id'] . " AND user_id = $user_id");
                }
                $excess -= $take;
            }
            $adjusted[] = [
                'name'      => $rows[0]['name'],
                'requested' => $total,
                'allowed'   => $limit,
                'capped'    => $stock >= catalog_max_per_order(),
            ];
        }
        return $adjusted;
    }

    /** Add products.product_type / is_active + categories.is_active (idempotent). */
    function catalog_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['catalog_schema_ok_v4'])) {
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

        $c3 = $conn->query("SELECT 1 AS c FROM information_schema.columns
                            WHERE table_name = 'products' AND column_name = 'sale_price'");
        if (!($c3 && $c3->num_rows > 0)) {
            $conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS sale_price NUMERIC(10,2)");
            $conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS sale_ends  TIMESTAMP");
        }

        $c4 = $conn->query("SELECT 1 AS c FROM information_schema.columns
                            WHERE table_name = 'products' AND column_name = 'is_featured'");
        if (!($c4 && $c4->num_rows > 0)) {
            $conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS is_featured BOOLEAN NOT NULL DEFAULT FALSE");
            $conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS featured_order INTEGER NOT NULL DEFAULT 0");
        }

        $c5 = $conn->query("SELECT 1 AS c FROM information_schema.columns
                            WHERE table_name = 'products' AND column_name = 'whats_inside'");
        if (!($c5 && $c5->num_rows > 0)) {
            $conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS whats_inside TEXT");
        }

        $conn->query("CREATE TABLE IF NOT EXISTS product_colors (
            id SERIAL PRIMARY KEY,
            product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            color_name VARCHAR(60) NOT NULL,
            image TEXT,
            sort_order INTEGER NOT NULL DEFAULT 0
        )");
        $conn->query("CREATE TABLE IF NOT EXISTS product_sizes (
            id SERIAL PRIMARY KEY,
            product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            size_name VARCHAR(60) NOT NULL,
            price NUMERIC(10,2) NOT NULL DEFAULT 0,
            sort_order INTEGER NOT NULL DEFAULT 0
        )");

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['catalog_schema_ok_v4'] = true;
        }
    }

    /**
     * Adds the columns needed to remember a cart line's chosen Occasion Box
     * color/size (and the size's own price) so it survives into checkout and
     * the placed order. Empty string, not NULL, means "no variant chosen" —
     * that keeps the WHERE-clause matching used to dedupe cart rows simple.
     */
    function cart_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['cart_schema_ok_v1'])) {
            return;
        }
        $conn->query("ALTER TABLE carts ADD COLUMN IF NOT EXISTS selected_color VARCHAR(60) NOT NULL DEFAULT ''");
        $conn->query("ALTER TABLE carts ADD COLUMN IF NOT EXISTS selected_size  VARCHAR(60) NOT NULL DEFAULT ''");
        $conn->query("ALTER TABLE carts ADD COLUMN IF NOT EXISTS variant_price  NUMERIC(10,2)");
        $conn->query("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS selected_color VARCHAR(60) NOT NULL DEFAULT ''");
        $conn->query("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS selected_size  VARCHAR(60) NOT NULL DEFAULT ''");
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['cart_schema_ok_v1'] = true;
        }
    }

    /** Split a "What's Inside" textarea (one item per line) into a clean list. */
    function catalog_whats_inside_lines($text) {
        if (!$text) return [];
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $lines = array_map('trim', $lines);
        return array_values(array_filter($lines, function ($l) { return $l !== ''; }));
    }

    /** Fetch a product's color options, ordered for display. */
    function catalog_get_colors($conn, $product_id) {
        $pid = (int) $product_id;
        $rows = [];
        $res = $conn->query("SELECT * FROM product_colors WHERE product_id = $pid ORDER BY sort_order ASC, id ASC");
        while ($res && $row = $res->fetch_assoc()) $rows[] = $row;
        return $rows;
    }

    /** Fetch a product's size options, ordered for display. */
    function catalog_get_sizes($conn, $product_id) {
        $pid = (int) $product_id;
        $rows = [];
        $res = $conn->query("SELECT * FROM product_sizes WHERE product_id = $pid ORDER BY sort_order ASC, id ASC");
        while ($res && $row = $res->fetch_assoc()) $rows[] = $row;
        return $rows;
    }

    /** Replace a product's color options from parallel $names/$images arrays. */
    function catalog_save_colors($conn, $product_id, $names, $images) {
        $pid = (int) $product_id;
        $conn->query("DELETE FROM product_colors WHERE product_id = $pid");
        $order = 0;
        foreach ($names as $i => $name) {
            $name = trim($name ?? '');
            if ($name === '') continue;
            $image = trim($images[$i] ?? '');
            $name_esc = $conn->real_escape_string(mb_substr($name, 0, 60));
            $image_sql = $image !== '' ? "'" . $conn->real_escape_string(mb_substr($image, 0, 500)) . "'" : 'NULL';
            $conn->query("INSERT INTO product_colors (product_id, color_name, image, sort_order)
                          VALUES ($pid, '$name_esc', $image_sql, $order)");
            $order++;
        }
    }

    /** Normalize one index of a PHP array-style file upload field (name="x[]") into a single $_FILES-shaped array. */
    function catalog_normalize_file($files_field, $index) {
        if (!isset($files_field['name'][$index]) || $files_field['name'][$index] === '') return null;
        return [
            'name'     => $files_field['name'][$index],
            'type'     => $files_field['type'][$index] ?? '',
            'tmp_name' => $files_field['tmp_name'][$index] ?? '',
            'error'    => $files_field['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files_field['size'][$index] ?? 0,
        ];
    }

    /** Replace a product's size options from parallel $names/$prices arrays. */
    function catalog_save_sizes($conn, $product_id, $names, $prices) {
        $pid = (int) $product_id;
        $conn->query("DELETE FROM product_sizes WHERE product_id = $pid");
        $order = 0;
        foreach ($names as $i => $name) {
            $name = trim($name ?? '');
            if ($name === '') continue;
            $price = floatval($prices[$i] ?? 0);
            $name_esc = $conn->real_escape_string(mb_substr($name, 0, 60));
            $conn->query("INSERT INTO product_sizes (product_id, size_name, price, sort_order)
                          VALUES ($pid, '$name_esc', $price, $order)");
            $order++;
        }
    }

    /**
     * SQL CASE expression for the price a customer actually pays right now:
     * the sale price when one is set, positive, below list price and not past
     * its end date — otherwise the list price. Pass the alias WITH a trailing
     * dot, e.g. catalog_price_sql('p.').
     */
    function catalog_price_sql($alias = '') {
        $a = $alias;
        return "(CASE WHEN {$a}sale_price IS NOT NULL AND {$a}sale_price > 0"
             . " AND {$a}sale_price < {$a}price"
             . " AND ({$a}sale_ends IS NULL OR {$a}sale_ends > (CURRENT_TIMESTAMP AT TIME ZONE 'UTC'))"
             . " THEN {$a}sale_price ELSE {$a}price END)";
    }

    /** The price a customer pays right now for a product row fetched from the DB. */
    function catalog_effective_price($row) {
        $price = (float) ($row['price'] ?? 0);
        $sale  = (isset($row['sale_price']) && $row['sale_price'] !== null && $row['sale_price'] !== '')
               ? (float) $row['sale_price'] : null;
        if ($sale === null || $sale <= 0 || $sale >= $price) return $price;
        $ends = $row['sale_ends'] ?? null;
        if ($ends !== null && $ends !== '' && strtotime($ends . ' UTC') < time()) return $price;
        return $sale;
    }

    /** True when the row is being sold below its list price right now. */
    function catalog_on_sale($row) {
        return catalog_effective_price($row) < (float) ($row['price'] ?? 0);
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
            'addon'        => 'Add-on (checkout upsell)',
        ];
    }

    /** Normalise a submitted type to a known key ('catalog' fallback). */
    function catalog_type_key($k) {
        $t = catalog_types();
        return isset($t[$k]) ? $k : 'catalog';
    }
}
