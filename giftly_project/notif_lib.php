<?php
/**
 * In-app notification history — the shared entry point every event (order
 * status, promo launch, new product) calls so a history row and its push
 * can never drift apart. Counterpart to push_lib.php's push_send_to_user(),
 * which this wraps.
 */

if (!function_exists('notif_ensure_schema')) {

    function notif_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;
        $conn->query("CREATE TABLE IF NOT EXISTS notifications (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            category VARCHAR(20) NOT NULL,
            title TEXT NOT NULL,
            body TEXT NOT NULL,
            data TEXT,
            read_at TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')
        )");
        $conn->query("CREATE INDEX IF NOT EXISTS idx_notifications_user_created ON notifications (user_id, created_at DESC)");
        $conn->query("CREATE INDEX IF NOT EXISTS idx_notifications_user_unread ON notifications (user_id, read_at) WHERE read_at IS NULL");
    }

    /**
     * Records one history row for $user_id AND sends the push. Pass RAW
     * (unescaped) $title/$body — this function owns SQL escaping; passing
     * an already-escaped string here would double-escape it in storage.
     * $category: 'order' | 'promo' | 'product'
     * $data: plain assoc array, json_encode()'d into the `data` column and
     * also forwarded to push_send_to_user() as-is.
     * Returns the push result (bool) so callers that log it, like
     * send_status_push()'s caller in admin_orders.php, keep working
     * unchanged — the history row itself is always written regardless.
     */
    function notif_create($conn, $user_id, $category, $title, $body, $data = []) {
        notif_ensure_schema($conn);
        $user_id = (int) $user_id;
        $cat_esc = $conn->real_escape_string($category);
        $title_esc = $conn->real_escape_string($title);
        $body_esc = $conn->real_escape_string($body);
        $data_esc = $conn->real_escape_string(json_encode($data));
        $conn->query("INSERT INTO notifications (user_id, category, title, body, data)
                      VALUES ($user_id, '$cat_esc', '$title_esc', '$body_esc', '$data_esc')");

        if (function_exists('push_send_to_user')) {
            return push_send_to_user($conn, $user_id, $title, $body, $data);
        }
        return false;
    }
}
