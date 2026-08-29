<?php
/**
 * Product reviews.
 *
 * Any product (shop item, Occasion Box, Basket) can be reviewed, but only by
 * a customer who ordered it AND confirmed the order arrived
 * (orders.status = 'delivered' and orders.received_at IS NOT NULL).
 * One review per customer per product; they can edit it.
 * Admins moderate via status: 'published' | 'hidden'.
 */

if (!function_exists('reviews_ensure_schema')) {

    function reviews_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['reviews_schema_ok'])) {
            return;
        }

        $c = $conn->query("SELECT to_regclass('public.product_reviews') AS t");
        $exists = $c && !empty(($c->fetch_assoc()['t'] ?? null));

        $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS received_at TIMESTAMP");

        if (!$exists) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS product_reviews (
                    id         SERIAL PRIMARY KEY,
                    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
                    user_id    INTEGER NOT NULL,
                    order_id   INTEGER,
                    rating     SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
                    comment    TEXT NOT NULL DEFAULT '',
                    status     VARCHAR(20) NOT NULL DEFAULT 'published',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (product_id, user_id)
                )
            ");
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['reviews_schema_ok'] = true;
        }
    }

    /** ['avg' => float, 'count' => int] for a product's published reviews. */
    function reviews_summary($conn, $product_id) {
        $pid = (int) $product_id;
        $r = $conn->query("SELECT COALESCE(AVG(rating),0) AS avg, COUNT(*) AS c
                           FROM product_reviews WHERE product_id = $pid AND status = 'published'");
        if (!$r) return ['avg' => 0.0, 'count' => 0];
        $row = $r->fetch_assoc();
        return ['avg' => round((float) $row['avg'], 1), 'count' => (int) $row['c']];
    }

    /** The user's own review for a product, or null. */
    function reviews_user_review($conn, $user_id, $product_id) {
        $uid = (int) $user_id;
        $pid = (int) $product_id;
        $r = $conn->query("SELECT * FROM product_reviews WHERE user_id = $uid AND product_id = $pid");
        return ($r && $r->num_rows) ? $r->fetch_assoc() : null;
    }

    /**
     * Can this user review this product right now?
     * Returns the order_id that proves it, or 0 if not eligible.
     */
    function reviews_eligible_order($conn, $user_id, $product_id) {
        $uid = (int) $user_id;
        $pid = (int) $product_id;
        $r = $conn->query("
            SELECT o.id
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            WHERE o.user_id = $uid AND oi.product_id = $pid
              AND o.status = 'delivered' AND o.received_at IS NOT NULL
            ORDER BY o.received_at DESC
            LIMIT 1
        ");
        return ($r && $r->num_rows) ? (int) $r->fetch_assoc()['id'] : 0;
    }

    /** Published reviews for a product, newest first. */
    function reviews_list($conn, $product_id, $limit = 20, $offset = 0, $include_hidden = false) {
        $pid = (int) $product_id;
        $limit = (int) $limit;
        $offset = (int) $offset;
        $st = $include_hidden ? "" : " AND r.status = 'published'";
        $out = [];
        $res = $conn->query("
            SELECT r.*, u.name AS user_name
            FROM product_reviews r
            JOIN users u ON u.id = r.user_id
            WHERE r.product_id = $pid $st
            ORDER BY r.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        while ($res && $row = $res->fetch_assoc()) $out[] = $row;
        return $out;
    }

    /** Inline star markup (Font Awesome). $rating may be fractional. */
    function reviews_stars($rating, $class = '') {
        $rating = (float) $rating;
        $html = '<span class="rv-stars ' . htmlspecialchars($class) . '">';
        for ($i = 1; $i <= 5; $i++) {
            if ($rating >= $i)            $html .= '<i class="fas fa-star"></i>';
            elseif ($rating >= $i - 0.5)  $html .= '<i class="fas fa-star-half"></i>';
            else                          $html .= '<i class="far fa-star"></i>';
        }
        return $html . '</span>';
    }
}
