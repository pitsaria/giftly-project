<?php
/**
 * Push notifications (Firebase Cloud Messaging) — server-sent alerts for
 * events the customer's phone has no other way to learn about, chiefly an
 * admin changing an order's status while the app isn't even open. This is
 * the counterpart to the mobile app's local notifications (NotificationService),
 * which only schedule things the device already knows (a delivery date, a
 * saved recipient's birthday) entirely on-device.
 *
 * Setup (env vars, set on Render):
 *   FIREBASE_SERVICE_ACCOUNT_JSON   the full JSON key downloaded from
 *                                   Firebase console > Project settings >
 *                                   Service accounts > Generate new private key
 *                                   (paste the whole file's contents as one
 *                                   env var, same pattern as PAYMONGO_SECRET_KEY)
 *
 * No Composer / SDK — the OAuth2 service-account exchange is a plain RS256
 * JWT signed with openssl, then a cURL call to FCM's HTTP v1 API (the legacy
 * server-key API this used to use was shut down by Google in 2024).
 */

include_once __DIR__ . '/notif_lib.php';

if (!function_exists('push_ensure_schema')) {

    function push_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['push_schema_ok_v1'])) {
            return;
        }
        $conn->query("CREATE TABLE IF NOT EXISTS user_push_tokens (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token TEXT NOT NULL UNIQUE,
            platform VARCHAR(20) NOT NULL DEFAULT 'android',
            created_at TIMESTAMP NOT NULL DEFAULT (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')
        )");
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['push_schema_ok_v1'] = true;
        }
    }

    // === Device token registry ===

    /** A device token belongs to one account — re-registering it (e.g. a new
     *  user logging in on the same phone) moves it rather than duplicating it. */
    function push_register_token($conn, $user_id, $token, $platform = 'android') {
        $user_id = (int) $user_id;
        $token_esc = $conn->real_escape_string($token);
        $platform_esc = $conn->real_escape_string(mb_substr((string) $platform, 0, 20));
        $conn->query("DELETE FROM user_push_tokens WHERE token = '$token_esc' AND user_id <> $user_id");
        $conn->query("INSERT INTO user_push_tokens (user_id, token, platform)
                      VALUES ($user_id, '$token_esc', '$platform_esc')
                      ON CONFLICT (token) DO UPDATE SET user_id = EXCLUDED.user_id, platform = EXCLUDED.platform");
    }

    function push_unregister_token($conn, $token) {
        $token_esc = $conn->real_escape_string($token);
        $conn->query("DELETE FROM user_push_tokens WHERE token = '$token_esc'");
    }

    // === FCM (HTTP v1) ===

    function fcm_service_account() {
        static $sa = null;
        if ($sa !== null) return $sa === false ? false : $sa;
        $json = trim((string) getenv('FIREBASE_SERVICE_ACCOUNT_JSON'));
        if ($json === '') { $sa = false; return false; }
        $decoded = json_decode($json, true);
        $valid = is_array($decoded) && !empty($decoded['private_key']) && !empty($decoded['client_email']) && !empty($decoded['project_id']);
        $sa = $valid ? $decoded : false;
        return $sa;
    }

    function fcm_configured() { return fcm_service_account() !== false; }

    /** Last FCM/OAuth error for the current request (for surfacing in logs). */
    function fcm_last_error($set = null) {
        static $err = '';
        if ($set !== null) $err = $set;
        return $err;
    }

    function fcm_base64url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** Low-level HTTPS POST. $json true sends a JSON body, false form-encoded. */
    function fcm_http_post($url, $payload, $json = true, $bearer = null) {
        $body = $json ? json_encode($payload) : http_build_query($payload);
        $headers = [$json ? 'Content-Type: application/json' : 'Content-Type: application/x-www-form-urlencoded'];
        if ($bearer) $headers[] = 'Authorization: Bearer ' . $bearer;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_POSTFIELDS     => $body,
            ]);
            $resp = curl_exec($ch);
            $curl_err = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($resp === false || $code === 0) {
                fcm_last_error('Network error reaching Firebase' . ($curl_err ? ': ' . $curl_err : ''));
            }
        } else {
            $hdr = implode("\r\n", $headers) . "\r\n";
            $ctx = stream_context_create(['http' => [
                'method' => 'POST', 'header' => $hdr, 'content' => $body,
                'timeout' => 20, 'ignore_errors' => true,
            ]]);
            $resp = @file_get_contents($url, false, $ctx);
            $code = 0;
            if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
                $code = (int) $m[1];
            }
        }

        return [$code, json_decode((string) $resp, true)];
    }

    /** Exchanges the service-account key for a short-lived OAuth2 access token. */
    function fcm_access_token() {
        static $cached = null;
        static $cachedAt = 0;
        if ($cached && (time() - $cachedAt) < 1800) return $cached;

        $sa = fcm_service_account();
        if (!$sa) { fcm_last_error('Firebase service account not configured'); return null; }

        $now = time();
        $header = fcm_base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = fcm_base64url(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));
        $unsigned = "$header.$claims";
        $signature = '';
        if (!openssl_sign($unsigned, $signature, $sa['private_key'], 'sha256WithRSAEncryption')) {
            fcm_last_error('Could not sign the FCM auth JWT');
            return null;
        }
        $jwt = $unsigned . '.' . fcm_base64url($signature);

        [$code, $resp] = fcm_http_post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ], false);

        if ($code !== 200 || empty($resp['access_token'])) {
            fcm_last_error('FCM token exchange failed: ' . ($resp['error_description'] ?? $resp['error'] ?? "HTTP $code"));
            return null;
        }
        $cached = $resp['access_token'];
        $cachedAt = $now;
        return $cached;
    }

    /**
     * Sends to one device token. Returns true (sent), false (failed — see
     * fcm_last_error()), or 'invalid_token' when FCM says the token is
     * dead/unregistered, so the caller can prune it from the database.
     */
    function fcm_send_raw($token, $title, $body, $data = []) {
        $sa = fcm_service_account();
        if (!$sa) { fcm_last_error('Firebase service account not configured'); return false; }
        $access = fcm_access_token();
        if (!$access) return false;

        $url = 'https://fcm.googleapis.com/v1/projects/' . $sa['project_id'] . '/messages:send';
        $message = ['message' => [
            'token'        => $token,
            'notification' => ['title' => $title, 'body' => $body],
        ]];
        if ($data) $message['message']['data'] = array_map('strval', $data);

        [$code, $resp] = fcm_http_post($url, $message, true, $access);
        if ($code === 200) return true;

        $status = $resp['error']['status'] ?? "HTTP $code";
        fcm_last_error("FCM send failed ($status): " . ($resp['error']['message'] ?? ''));
        return in_array($status, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true) ? 'invalid_token' : false;
    }

    /** Pushes to every device registered for a user; prunes tokens FCM rejects. */
    function push_send_to_user($conn, $user_id, $title, $body, $data = []) {
        if (!fcm_configured()) { fcm_last_error('Firebase not configured'); return false; }
        $user_id = (int) $user_id;
        $res = $conn->query("SELECT id, token FROM user_push_tokens WHERE user_id = $user_id");
        $sent = 0;
        while ($res && $row = $res->fetch_assoc()) {
            $result = fcm_send_raw($row['token'], $title, $body, $data);
            if ($result === 'invalid_token') {
                $conn->query("DELETE FROM user_push_tokens WHERE id = " . (int) $row['id']);
            } elseif ($result === true) {
                $sent++;
            }
        }
        return $sent > 0;
    }

    /** Mirrors send_status_email() (mail_lib.php) — same trigger, push instead. */
    function send_status_push($conn, $order_id, $status) {
        $status = strtolower(trim((string) $status));
        if (!in_array($status, ['shipped', 'delivered'], true)) return false;
        $order_id = (int) $order_id;

        $o = $conn->query("SELECT user_id FROM orders WHERE id = $order_id");
        $order = $o ? $o->fetch_assoc() : null;
        if (!$order) return false;

        if ($status === 'shipped') {
            $title = 'Your order is on the way! 🚚';
            $body  = "Order #GLY-$order_id has shipped and is heading to you.";
        } else {
            $title = 'Your order was delivered! ✅';
            $body  = "Order #GLY-$order_id has been marked delivered. We hope you love it!";
        }

        return notif_create($conn, $order['user_id'], 'order', $title, $body, [
            'type'     => 'order_status',
            'order_id' => $order_id,
            'status'   => $status,
        ]);
    }
}
