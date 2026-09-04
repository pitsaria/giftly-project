<?php
/**
 * Saved-address helpers: adds a per-user "default" flag on top of the
 * existing free-text label. Idempotent schema bootstrap.
 */

if (!function_exists('addr_ensure_schema')) {

    function addr_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['addr_schema_ok_v1'])) {
            return;
        }

        $c = $conn->query("SELECT 1 AS c FROM information_schema.columns
                           WHERE table_name = 'addresses' AND column_name = 'is_default'");
        if (!($c && $c->num_rows > 0)) {
            $conn->query("ALTER TABLE addresses ADD COLUMN IF NOT EXISTS is_default BOOLEAN NOT NULL DEFAULT FALSE");
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['addr_schema_ok_v1'] = true;
        }
    }

    /** Preset labels offered in the address form. */
    function addr_labels() {
        return ['Home', 'Office'];
    }

    /** Make one address the user's default; clears the flag on their others. */
    function addr_set_default($conn, $user_id, $address_id) {
        $user_id    = (int) $user_id;
        $address_id = (int) $address_id;
        // only if it belongs to this user
        $chk = $conn->query("SELECT id FROM addresses WHERE id = $address_id AND user_id = $user_id");
        if (!$chk || $chk->num_rows === 0) return false;
        $conn->query("UPDATE addresses SET is_default = FALSE WHERE user_id = $user_id");
        $conn->query("UPDATE addresses SET is_default = TRUE  WHERE id = $address_id AND user_id = $user_id");
        return true;
    }

    /** Boolean-ish helper for the pg 't'/'f'/true values. */
    function addr_is_default($v) {
        return ($v === true || $v === 't' || $v === '1' || $v === 1);
    }
}
