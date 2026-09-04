<?php
/**
 * Auth schema + helpers: "Sign in with Google" columns and email OTP for
 * password sign-in. Idempotent schema bootstrap.
 */

if (!function_exists('auth_ensure_schema')) {

    require_once __DIR__ . '/mail_lib.php';

    function auth_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['auth_schema_ok_v2'])) {
            return;
        }

        $c = $conn->query("SELECT 1 AS c FROM information_schema.columns
                           WHERE table_name = 'users' AND column_name = 'google_id'");
        if (!($c && $c->num_rows > 0)) {
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(64)");
            $conn->query("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_google_id
                          ON users (google_id) WHERE google_id IS NOT NULL");
        }

        $c2 = $conn->query("SELECT to_regclass('public.login_otps') AS t");
        $exists = $c2 && !empty(($c2->fetch_assoc()['t'] ?? null));
        if (!$exists) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS login_otps (
                    id         SERIAL PRIMARY KEY,
                    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    code_hash  VARCHAR(255) NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    attempts   SMALLINT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['auth_schema_ok_v2'] = true;
        }
    }

    /** The configured Google OAuth Web client ID, or '' when the feature is off. */
    function google_client_id() {
        return getenv('GOOGLE_CLIENT_ID') ?: '';
    }

    /* ---------------- Email OTP for password sign-in ---------------- */

    /** OTP is only enforced when we can actually send email. */
    function otp_enabled() {
        return function_exists('mail_configured') && mail_configured();
    }

    function otp_pending() {
        return !empty($_SESSION['pending_otp_user']);
    }

    /** Mask an email for display: j***e@gmail.com */
    function otp_mask_email($email) {
        $parts = explode('@', (string) $email);
        if (count($parts) !== 2) return $email;
        $u = $parts[0];
        $masked = mb_strlen($u) <= 2 ? mb_substr($u, 0, 1) . '*' : mb_substr($u, 0, 1) . str_repeat('*', min(4, mb_strlen($u) - 2)) . mb_substr($u, -1);
        return $masked . '@' . $parts[1];
    }

    /**
     * Generate + email a fresh 6-digit code for $user, and stash the pending
     * state in the session. Returns true if the email was sent.
     */
    function otp_start($conn, $user, $redirect_to = 'index.php') {
        $uid = (int) $user['id'];
        $conn->query("DELETE FROM login_otps WHERE user_id = $uid");

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = $conn->real_escape_string(password_hash($code, PASSWORD_DEFAULT));
        $exp  = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        $inner = '<p style="color:#555;font-size:14px;line-height:1.6;">Use this code to finish signing in. It expires in 10 minutes.</p>'
               . '<div style="font-size:34px;font-weight:700;letter-spacing:8px;color:#ff8ba7;text-align:center;margin:22px 0;padding:14px;background:#fff5f7;border-radius:12px;">' . $code . '</div>'
               . '<p style="color:#999;font-size:12.5px;">If you didn\'t try to sign in, you can ignore this email and your password stays safe.</p>';
        $sent = mail_send($user['email'], 'Your Giftly sign-in code: ' . $code, mail_wrap('Verify your sign-in', $inner));
        if (!$sent) {
            return false; // caller logs the user in directly rather than locking them out
        }

        $conn->query("INSERT INTO login_otps (user_id, code_hash, expires_at)
                      VALUES ($uid, '$hash', '$exp')");

        $_SESSION['pending_otp_user']     = $uid;
        $_SESSION['pending_otp_email']    = $user['email'];
        $_SESSION['pending_otp_name']     = $user['name'];
        $_SESSION['pending_otp_role']     = $user['role'];
        $_SESSION['pending_otp_redirect'] = $redirect_to ?: 'index.php';
        $_SESSION['pending_otp_started']  = time();
        return true;
    }

    /**
     * Verify a submitted code against the session's pending OTP.
     * Returns [user_id, ''] on success or [0, 'reason'] on failure.
     */
    function otp_verify($conn, $code) {
        $uid = (int) ($_SESSION['pending_otp_user'] ?? 0);
        if ($uid <= 0) return [0, 'Your sign-in session expired. Please log in again.'];
        $code = preg_replace('/\D/', '', (string) $code);
        if (strlen($code) !== 6) return [0, 'Enter the 6-digit code.'];

        $r = $conn->query("SELECT * FROM login_otps WHERE user_id = $uid ORDER BY id DESC LIMIT 1");
        $row = ($r && $r->num_rows) ? $r->fetch_assoc() : null;
        if (!$row) return [0, 'No code on file. Send a new one.'];
        if (strtotime($row['expires_at']) < time()) {
            $conn->query("DELETE FROM login_otps WHERE user_id = $uid");
            return [0, 'That code expired. Send a new one.'];
        }
        if ((int) $row['attempts'] >= 5) {
            $conn->query("DELETE FROM login_otps WHERE user_id = $uid");
            unset($_SESSION['pending_otp_user']);
            return [0, 'Too many attempts. Please log in again.'];
        }
        if (!password_verify($code, $row['code_hash'])) {
            $id = (int) $row['id'];
            $conn->query("UPDATE login_otps SET attempts = attempts + 1 WHERE id = $id");
            return [0, 'That code is incorrect.'];
        }

        // success
        $conn->query("DELETE FROM login_otps WHERE user_id = $uid");
        foreach (['pending_otp_user','pending_otp_email','pending_otp_name','pending_otp_role','pending_otp_redirect','pending_otp_started'] as $k) {
            unset($_SESSION[$k]);
        }
        return [$uid, ''];
    }
}
