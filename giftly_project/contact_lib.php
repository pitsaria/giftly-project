<?php
/**
 * Contact-form message store.
 * Messages submitted on contact.php land in `contact_messages`; the admin
 * reads them on admin_messages.php.
 */

if (!function_exists('contact_ensure_schema')) {

    function contact_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['contact_schema_ok_v2'])) {
            return;
        }

        $conn->query("
            CREATE TABLE IF NOT EXISTS contact_messages (
                id         SERIAL PRIMARY KEY,
                name       VARCHAR(120) NOT NULL,
                email      VARCHAR(160) NOT NULL,
                subject    VARCHAR(160) NOT NULL DEFAULT '',
                message    TEXT NOT NULL,
                is_read    BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        // for stores where contact_messages predates is_read
        $conn->query("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS is_read BOOLEAN NOT NULL DEFAULT FALSE");

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['contact_schema_ok_v2'] = true;
        }
    }

    /** Count of unread contact messages (0 if the table doesn't exist yet). */
    function contact_unread_count($conn) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE is_read = FALSE");
        return $r ? (int) $r->fetch_assoc()['c'] : 0;
    }
}
