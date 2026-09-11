<?php
/**
 * Gift wrapping & checkout add-ons (premium wrap, balloon, scented candle,
 * mini photo frame). Add-ons are ordinary `products` rows with
 * product_type = 'addon', so they reuse the existing order_items / stock /
 * thumbnail / confirmation-email machinery unchanged — no new tables.
 *
 * Every shop-facing listing (shop.php, index.php featured, catalog_grid.php,
 * build_a_box_products.php, the mobile API) already filters to a specific
 * product_type, so 'addon' rows are invisible there automatically.
 * admin_products.php's default (unfiltered) view also excludes them —
 * they're only reachable by explicitly picking "Add-on" in its type filter.
 */

require_once __DIR__ . '/catalog_lib.php';

if (!function_exists('addons_ensure_schema')) {

    /** name => [description, price, image-filename-in-uploads/] seeded once. */
    function addons_catalog_seed() {
        return [
            'Premium Gift Wrap'  => ['Extra-special wrapping paper, ribbon and a bow.', 50.00, 'addon_wrap.svg'],
            'Balloon Add-on'     => ['A cheerful balloon bundled with your order.', 80.00, 'addon_balloon.svg'],
            'Scented Candle'     => ['A softly-scented candle to go with the gift.', 70.00, 'addon_candle.svg'],
            'Mini Photo Frame'   => ['A small keepsake frame for a favorite photo.', 90.00, 'addon_frame.svg'],
        ];
    }

    function addons_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        catalog_ensure_schema($conn); // needs products.product_type / is_active

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['addons_schema_ok_v2'])) {
            return;
        }

        $seed = addons_catalog_seed();
        foreach ($seed as $name => $d) {
            $name_esc = $conn->real_escape_string($name);
            $exists = $conn->query("SELECT id FROM products WHERE name = '$name_esc' AND product_type = 'addon'");
            if ($exists && $exists->num_rows > 0) continue;

            $desc_esc  = $conn->real_escape_string($d[0]);
            $price     = (float) $d[1];
            $image_esc = $conn->real_escape_string($d[2]);
            $conn->query("INSERT INTO products (name, description, price, image, quantity, category_id, product_type, is_active)
                          VALUES ('$name_esc', '$desc_esc', $price, '$image_esc', 999, 1, 'addon', TRUE)");
        }

        // Retired add-ons (no longer in the seed list above) stop showing at checkout,
        // but the row stays — past orders still join to it for their line-item display.
        $names_sql = implode(',', array_map(function ($n) use ($conn) {
            return "'" . $conn->real_escape_string($n) . "'";
        }, array_keys($seed)));
        $conn->query("UPDATE products SET is_active = FALSE WHERE product_type = 'addon' AND name NOT IN ($names_sql)");

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['addons_schema_ok_v2'] = true;
        }
    }

    /** Active add-ons available at checkout, cheapest first. */
    function addons_list($conn) {
        $rows = [];
        $res = $conn->query("SELECT * FROM products WHERE product_type = 'addon' AND is_active = TRUE ORDER BY price ASC");
        while ($res && $row = $res->fetch_assoc()) $rows[] = $row;
        return $rows;
    }

    /**
     * Validate a submitted list of addon product ids against the DB (never
     * trust the client for price). Returns [rows[], total].
     */
    function addons_resolve($conn, $addon_ids) {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $addon_ids))));
        if (empty($ids)) return [[], 0.0];
        $ids_str = implode(',', $ids);
        $res = $conn->query("SELECT id, name, price FROM products WHERE id IN ($ids_str) AND product_type = 'addon' AND is_active = TRUE");
        $rows = [];
        $total = 0.0;
        while ($res && $row = $res->fetch_assoc()) {
            $rows[] = $row;
            $total += (float) $row['price'];
        }
        return [$rows, $total];
    }
}
