<?php
/**
 * Shared "needs attention" counts for the admin sidebar badges + top-bar bell.
 *
 * Orders / Reviews use a "new since you last opened that page" model: the
 * relevant admin page stamps $_SESSION['notif_seen_orders' | 'notif_seen_reviews']
 * (UTC) on load, and the badge counts rows created after that. Open cancellation
 * requests are always counted (they're a real task, not just "new activity").
 */

if (!function_exists('admin_notif_counts')) {

    /** Call from admin_orders.php / admin_reviews.php on page load. */
    function admin_notif_mark_seen($key) {
        if (in_array($key, ['orders', 'reviews'], true)) {
            $_SESSION['notif_seen_' . $key] = gmdate('Y-m-d H:i:s');
        }
    }

    function admin_notif_counts($conn) {
        static $cache = null;
        if ($cache !== null) return $cache;

        $q = function ($sql) use ($conn) {
            $res = @$conn->query($sql);
            return $res ? (int) ($res->fetch_assoc()['c'] ?? 0) : 0;
        };

        // First run (never opened the page): show the last 7 days of activity.
        $seen_orders  = $_SESSION['notif_seen_orders']  ?? gmdate('Y-m-d H:i:s', time() - 7 * 86400);
        $seen_reviews = $_SESSION['notif_seen_reviews'] ?? gmdate('Y-m-d H:i:s', time() - 7 * 86400);
        $so = $conn->real_escape_string($seen_orders);
        $sr = $conn->real_escape_string($seen_reviews);

        $messages = $q("SELECT COUNT(*) AS c FROM contact_messages WHERE is_read = FALSE AND archived = FALSE");
        $cancels  = $q("SELECT COUNT(*) AS c FROM orders WHERE cancel_status = 'requested'");
        $new_orders = $q("SELECT COUNT(*) AS c FROM orders
                          WHERE created_at > '$so' AND status <> 'cancelled'
                            AND (cancel_status IS NULL OR cancel_status <> 'requested')");
        $unpaid   = $q("SELECT COUNT(*) AS c FROM orders
                        WHERE payment_status = 'unpaid' AND status = 'pending'
                          AND payment_method NOT IN ('cod')");
        $new_reviews = $q("SELECT COUNT(*) AS c FROM product_reviews WHERE created_at > '$sr'");

        $cache = [
            'messages'    => $messages,
            'cancels'     => $cancels,
            'new_orders'  => $new_orders,
            'unpaid'      => $unpaid,
            'new_reviews' => $new_reviews,
            'orders'      => $new_orders + $cancels,      // sidebar badge on "Orders"
            'reviews'     => $new_reviews,                // sidebar badge on "Reviews"
            'total'       => $messages + $cancels + $new_orders + $unpaid + $new_reviews,
        ];
        return $cache;
    }
}
