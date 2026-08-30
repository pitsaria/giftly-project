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

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['contact_schema_ok_v3'])) {
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
                archived   BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        // for stores where contact_messages predates is_read / archived
        $conn->query("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS is_read BOOLEAN NOT NULL DEFAULT FALSE");
        $conn->query("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS archived BOOLEAN NOT NULL DEFAULT FALSE");

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['contact_schema_ok_v3'] = true;
        }
    }

    /** Count of unread contact messages (0 if the table doesn't exist yet). Archived messages don't count. */
    function contact_unread_count($conn) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE is_read = FALSE AND archived = FALSE");
        return $r ? (int) $r->fetch_assoc()['c'] : 0;
    }

    /**
     * Format a contact_messages.created_at value for display.
     * Stored timestamps are UTC wall-time (Postgres CURRENT_TIMESTAMP on the
     * server); convert to the app timezone so the admin sees local time.
     */
    function contact_fmt_time($ts) {
        if (!$ts) return '';
        try {
            $dt = new DateTime($ts, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila'));
            return $dt->format('M j, Y · g:i A');
        } catch (Exception $e) {
            return date('M j, Y · g:i A', strtotime($ts));
        }
    }
}
