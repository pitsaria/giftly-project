<?php
/**
 * Auth schema helpers.
 *
 * Adds the columns needed for "Sign in with Google" on top of the existing
 * email/password `users` table. Idempotent — safe to call on every request;
 * the DDL only runs the first time (gated on a session flag + a column check).
 */

if (!function_exists('auth_ensure_schema')) {

    function auth_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['auth_schema_ok_v1'])) {
            return;
        }

        $c = $conn->query("SELECT 1 AS c FROM information_schema.columns
                           WHERE table_name = 'users' AND column_name = 'google_id'");
        if (!($c && $c->num_rows > 0)) {
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(64)");
            // partial unique index: many NULLs allowed, but a Google account links to one user
            $conn->query("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_google_id
                          ON users (google_id) WHERE google_id IS NOT NULL");
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['auth_schema_ok_v1'] = true;
        }
    }

    /** The configured Google OAuth Web client ID, or '' when the feature is off. */
    function google_client_id() {
        return getenv('GOOGLE_CLIENT_ID') ?: '';
    }
}
