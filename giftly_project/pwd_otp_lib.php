<?php
/**
 * Email OTP for password changes — used by both the "forgot password" reset
 * flow (logged out) and the "change password" flow in profile settings
 * (logged in). Separate from login_otps so a pending sign-in code and a
 * pending password code never clobber each other.
 *
 * Idempotent schema bootstrap, same pattern as the other *_lib.php files.
 */

require_once __DIR__ . '/mail_lib.php';

if (!function_exists('pwd_otp_ensure_schema')) {

    function pwd_otp_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['pwd_otp_schema_ok_v1'])) {
            return;
        }

        $c = $conn->query("SELECT to_regclass('public.password_otps') AS t");
        $exists = $c && !empty(($c->fetch_assoc()['t'] ?? null));
        if (!$exists) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS password_otps (
                    id         SERIAL PRIMARY KEY,
                    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    purpose    VARCHAR(20) NOT NULL DEFAULT 'reset',
                    code_hash  VARCHAR(255) NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    attempts   SMALLINT NOT NULL DEFAULT 0,
                    verified   BOOLEAN NOT NULL DEFAULT FALSE,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['pwd_otp_schema_ok_v1'] = true;
        }
    }

    /** Seconds a fresh code stays valid. */
    function pwd_otp_ttl() {
        return 600; // 10 minutes
    }

    /** Cooldown between code requests (shared with the sign-in OTP knob). */
    function pwd_otp_cooldown() {
        $env = (int) getenv('OTP_RESEND_COOLDOWN');
        return $env > 0 ? $env : 60;
    }

    /**
     * Seconds the user must still wait before another code can be sent for this
     * account + purpose (0 = ready). Computed on the DB clock; created_at is a
     * UTC wall-clock value so it is compared against UTC "now".
     */
    function pwd_otp_seconds_until_resend($conn, $user_id, $purpose = 'reset') {
        $uid = (int) $user_id;
        if ($uid <= 0) return 0;
        $cd = (int) pwd_otp_cooldown();
        $p  = $conn->real_escape_string($purpose);
        $r  = $conn->query("SELECT CEIL(EXTRACT(EPOCH FROM (
                                MAX(created_at) + INTERVAL '$cd seconds'
                                - (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')
                            ))) AS wait
                            FROM password_otps WHERE user_id = $uid AND purpose = '$p'");
        $wait = ($r && $r->num_rows) ? (int) ($r->fetch_assoc()['wait'] ?? 0) : 0;
        return $wait > 0 ? $wait : 0;
    }

    /**
     * Email a fresh 6-digit code to $email. Returns [bool ok, string err].
     * Honours the cooldown: within the window it reports success without
     * sending a second email (the earlier code is still valid).
     */
    function pwd_otp_send($conn, $user_id, $email, $purpose = 'reset') {
        $uid = (int) $user_id;
        $p   = $conn->real_escape_string($purpose);

        if (!function_exists('mail_configured') || !mail_configured()) {
            return [false, 'Email verification is not available right now. Please contact support.'];
        }
        if ($uid <= 0 || trim((string) $email) === '') {
            return [false, 'Account not found.'];
        }
        if (pwd_otp_seconds_until_resend($conn, $uid, $purpose) > 0) {
            return [true, '']; // a code is already on its way
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = $conn->real_escape_string(password_hash($code, PASSWORD_DEFAULT));
        $exp  = gmdate('Y-m-d H:i:s', time() + pwd_otp_ttl());

        $inner = '<p style="color:#555;font-size:14px;line-height:1.6;">Use this code to confirm your password change. It expires in 10 minutes.</p>'
               . '<div style="font-size:34px;font-weight:700;letter-spacing:8px;color:#ff8ba7;text-align:center;margin:22px 0;padding:14px;background:#fff5f7;border-radius:12px;">' . $code . '</div>'
               . '<p style="color:#999;font-size:12.5px;">If you didn\'t request this, you can ignore this email — your password stays the same. If you keep getting these, change your password as a precaution.</p>';
        $ok = mail_send($email, 'Your Giftly password code: ' . $code, mail_wrap('Confirm your password change', $inner));
        if (!$ok) {
            return [false, "Couldn't send the code right now. Please try again shortly."];
        }

        $conn->query("DELETE FROM password_otps WHERE user_id = $uid AND purpose = '$p'");
        $conn->query("INSERT INTO password_otps (user_id, purpose, code_hash, expires_at)
                      VALUES ($uid, '$p', '$hash', '$exp')");
        return [true, ''];
    }

    /**
     * Check a submitted code. Returns [bool ok, string err]. On success the
     * row is marked verified so the follow-up "set new password" step can
     * confirm the code was cleared without re-entering it.
     */
    function pwd_otp_verify($conn, $user_id, $code, $purpose = 'reset') {
        $uid  = (int) $user_id;
        $p    = $conn->real_escape_string($purpose);
        $code = preg_replace('/\D/', '', (string) $code);
        if ($uid <= 0) return [false, 'Your session expired. Please start again.'];
        if (strlen($code) !== 6) return [false, 'Enter the 6-digit code.'];

        $r = $conn->query("SELECT * FROM password_otps
                           WHERE user_id = $uid AND purpose = '$p'
                           ORDER BY id DESC LIMIT 1");
        $row = ($r && $r->num_rows) ? $r->fetch_assoc() : null;
        if (!$row) return [false, 'No code on file. Request a new one.'];
        if (strtotime($row['expires_at'] . ' UTC') < time()) {
            $conn->query("DELETE FROM password_otps WHERE user_id = $uid AND purpose = '$p'");
            return [false, 'That code expired. Request a new one.'];
        }
        if ((int) $row['attempts'] >= 5) {
            $conn->query("DELETE FROM password_otps WHERE user_id = $uid AND purpose = '$p'");
            return [false, 'Too many attempts. Request a new code.'];
        }
        if (!password_verify($code, $row['code_hash'])) {
            $id = (int) $row['id'];
            $conn->query("UPDATE password_otps SET attempts = attempts + 1 WHERE id = $id");
            return [false, 'That code is incorrect.'];
        }

        $id = (int) $row['id'];
        $conn->query("UPDATE password_otps SET verified = TRUE WHERE id = $id");
        return [true, ''];
    }

    /** True when a still-valid, already-verified code exists for this user+purpose. */
    function pwd_otp_is_verified($conn, $user_id, $purpose = 'reset') {
        $uid = (int) $user_id;
        $p   = $conn->real_escape_string($purpose);
        $r = $conn->query("SELECT 1 FROM password_otps
                           WHERE user_id = $uid AND purpose = '$p' AND verified = TRUE
                             AND expires_at > (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')
                           LIMIT 1");
        return $r && $r->num_rows > 0;
    }

    /** Wipe codes for this user+purpose (call after a successful change). */
    function pwd_otp_clear($conn, $user_id, $purpose = 'reset') {
        $uid = (int) $user_id;
        $p   = $conn->real_escape_string($purpose);
        $conn->query("DELETE FROM password_otps WHERE user_id = $uid AND purpose = '$p'");
    }

    /**
     * Shared password-strength gate: 8+ chars, a letter, a number, a special.
     * Returns [bool ok, string err].
     */
    function pwd_strength_check($pw) {
        $pw = (string) $pw;
        if (strlen($pw) < 8)                                return [false, 'Password must be at least 8 characters.'];
        if (!preg_match('/[A-Za-z]/', $pw))                 return [false, 'Password must include a letter.'];
        if (!preg_match('/\d/', $pw))                       return [false, 'Password must include a number.'];
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $pw))   return [false, 'Password must include a special character.'];
        return [true, ''];
    }
}
