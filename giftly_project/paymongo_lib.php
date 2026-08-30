<?php
/**
 * PayMongo integration — Checkout Sessions (hosted payment page).
 *
 * Flow:
 *   1. checkout creates the order (status 'pending', payment_status 'unpaid')
 *      and decrements stock / clears the cart as before.
 *   2. paymongo_create_checkout() opens a PayMongo Checkout Session and returns
 *      its hosted URL; the shopper is redirected there to pay (card/GCash/Maya).
 *   3. paymongo_webhook.php receives `checkout_session.payment.paid` and calls
 *      pay_mark_paid(). The browser redirect to payment_return.php is only for
 *      UX — the webhook is the source of truth.
 *   4. pay_sweep_stale() cancels + restocks online orders left unpaid for 2h
 *      (abandoned checkouts).
 *
 * Env vars (set on Render):
 *   PAYMONGO_SECRET_KEY      sk_test_... / sk_live_...
 *   PAYMONGO_WEBHOOK_SECRET  whsec_...  (from Developers -> Webhooks)
 *   APP_BASE_URL             optional; otherwise derived from the request
 *
 * No Composer / SDK — plain HTTPS via cURL (falls back to streams).
 */

if (!function_exists('pay_ensure_schema')) {

    function paymongo_secret_key() { return trim((string) getenv('PAYMONGO_SECRET_KEY')); }
    function paymongo_configured()  { return paymongo_secret_key() !== ''; }

    /**
     * Payment methods offered on the hosted page. Override with a comma-list in
     * env PAYMONGO_METHODS once you've activated more in the PayMongo dashboard.
     */
    function paymongo_methods() {
        $env = trim((string) getenv('PAYMONGO_METHODS'));
        if ($env !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $env))));
        }
        return ['card', 'gcash'];
    }

    /** Last PayMongo API error for the current request (for surfacing to the user). */
    function paymongo_last_error($set = null) {
        static $err = '';
        if ($set !== null) $err = $set;
        return $err;
    }

    /** Absolute https base URL for building success/cancel URLs. */
    function app_base_url() {
        $env = getenv('APP_BASE_URL') ?: getenv('RENDER_EXTERNAL_URL');
        if ($env) return rtrim($env, '/');
        $proto = 'https';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $proto = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0];
        } elseif (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            $proto = 'http';
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $proto . '://' . $host;
    }

    function pay_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        if (!(session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['pay_schema_ok_v1']))) {
            $c = $conn->query("SELECT 1 AS c FROM information_schema.columns
                               WHERE table_name = 'orders' AND column_name = 'payment_status'");
            if (!($c && $c->num_rows > 0)) {
                $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid'");
                $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_ref VARCHAR(120)");
                $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS paid_at TIMESTAMP");
            }
            if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['pay_schema_ok_v1'] = true;
        }

        // opportunistic cleanup of abandoned online checkouts — only on real
        // logged-in page views (never the webhook), throttled to once/10min.
        if (!empty($_SESSION['user_id'])) {
            $last = $_SESSION['pay_swept_at'] ?? 0;
            if (time() - $last > 600) {
                $_SESSION['pay_swept_at'] = time();
                pay_sweep_stale($conn);
            }
        }
    }

    /** Low-level PayMongo API call. Returns [httpCode, decodedBody]. */
    function paymongo_request($method, $path, $payload = null) {
        $url = 'https://api.paymongo.com/v1/' . ltrim($path, '/');
        $auth = 'Basic ' . base64_encode(paymongo_secret_key() . ':');
        $body = $payload !== null ? json_encode($payload) : null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $headers = ['Authorization: ' . $auth, 'Accept: application/json'];
            if ($body !== null) $headers[] = 'Content-Type: application/json';
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => $method,
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
                paymongo_last_error('Network error reaching PayMongo' . ($curl_err ? ': ' . $curl_err : ''));
            }
        } else {
            $hdr = "Authorization: $auth\r\nAccept: application/json\r\n";
            if ($body !== null) $hdr .= "Content-Type: application/json\r\n";
            $ctx = stream_context_create(['http' => [
                'method' => $method, 'header' => $hdr, 'content' => $body,
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

    /**
     * Create a Checkout Session for an order and return its hosted URL,
     * or '' on failure. Stores the session id on orders.payment_ref.
     */
    function paymongo_create_checkout($conn, $order_id, $amount, $name, $email, $phone) {
        $order_id = (int) $order_id;
        $centavos = (int) round($amount * 100);
        if ($centavos < 2000) $centavos = 2000; // PayMongo minimum is PHP 20.00

        $base = app_base_url();
        $payload = ['data' => ['attributes' => [
            'line_items' => [[
                'name'     => 'Giftly Order #' . $order_id,
                'amount'   => $centavos,
                'currency' => 'PHP',
                'quantity' => 1,
            ]],
            // card + gcash are enabled on every PayMongo account by default.
            // Add 'paymaya' / 'grab_pay' here once you've activated them.
            'payment_method_types' => paymongo_methods(),
            'description'      => 'Giftly order #' . $order_id,
            'reference_number' => (string) $order_id,
            'success_url'      => $base . '/payment_return.php?order_id=' . $order_id,
            'cancel_url'       => $base . '/payment_return.php?order_id=' . $order_id . '&cancel=1',
            'metadata'         => ['order_id' => (string) $order_id],
        ]]];
        $billing = array_filter([
            'name'  => trim((string) $name),
            'email' => trim((string) $email),
            'phone' => trim((string) $phone),
        ]);
        if (!empty($billing['email'])) $payload['data']['attributes']['billing'] = $billing;

        [$code, $res] = paymongo_request('POST', 'checkout_sessions', $payload);
        $attr = $res['data']['attributes'] ?? null;
        if ($code >= 200 && $code < 300 && $attr && !empty($attr['checkout_url'])) {
            $ref = $conn->real_escape_string($res['data']['id'] ?? '');
            $conn->query("UPDATE orders SET payment_ref = '$ref' WHERE id = $order_id");
            paymongo_last_error('');
            return $attr['checkout_url'];
        }
        $detail = $res['errors'][0]['detail'] ?? ($res['error'] ?? 'Unknown error');
        paymongo_last_error('PayMongo (' . $code . '): ' . $detail);
        error_log('PayMongo checkout_session failed (' . $code . '): ' . json_encode($res));
        return '';
    }

    /** Mark an order paid. Idempotent — only the first call takes effect. */
    function pay_mark_paid($conn, $order_id, $ref = '', $method = '') {
        $order_id = (int) $order_id;
        if ($order_id <= 0) return false;
        $ref_esc = $conn->real_escape_string($ref);
        $set_ref = $ref !== '' ? ", payment_ref = '$ref_esc'" : '';
        $set_method = '';
        if ($method !== '') {
            $m = $conn->real_escape_string($method);
            $set_method = ", payment_method = '$m'";
        }
        $conn->query("UPDATE orders
                      SET payment_status = 'paid', paid_at = CURRENT_TIMESTAMP $set_ref $set_method
                      WHERE id = $order_id AND payment_status <> 'paid'");
        return $conn->affected_rows > 0;
    }

    /**
     * Payment failed / abandoned: cancel the order and put the stock back.
     * Idempotent; only touches orders that are still unpaid & not cancelled.
     */
    function pay_mark_failed($conn, $order_id) {
        $order_id = (int) $order_id;
        if ($order_id <= 0) return false;

        $r = $conn->query("SELECT payment_status, status FROM orders WHERE id = $order_id");
        $row = $r ? $r->fetch_assoc() : null;
        if (!$row || $row['payment_status'] === 'paid' || $row['status'] === 'cancelled') return false;

        $items = $conn->query("SELECT product_id, quantity FROM order_items WHERE order_id = $order_id");
        while ($items && $it = $items->fetch_assoc()) {
            $pid = (int) $it['product_id'];
            $qty = (int) $it['quantity'];
            $conn->query("UPDATE products SET quantity = quantity + $qty WHERE id = $pid");
        }
        $conn->query("UPDATE orders SET status = 'cancelled', payment_status = 'failed' WHERE id = $order_id");
        return true;
    }

    /** Cancel + restock online orders that have sat unpaid for over 2 hours. */
    function pay_sweep_stale($conn) {
        $res = $conn->query("SELECT id FROM orders
                             WHERE payment_status = 'unpaid'
                               AND status = 'pending'
                               AND payment_method NOT IN ('cod')
                               AND created_at < (CURRENT_TIMESTAMP - INTERVAL '2 hours')
                             LIMIT 50");
        while ($res && $row = $res->fetch_assoc()) {
            pay_mark_failed($conn, (int) $row['id']);
        }
    }

    /** Verify a PayMongo webhook signature header against PAYMONGO_WEBHOOK_SECRET. */
    function paymongo_verify_signature($payload, $header) {
        $secret = getenv('PAYMONGO_WEBHOOK_SECRET') ?: '';
        if ($secret === '' || $header === '') return false;
        $parts = [];
        foreach (explode(',', $header) as $kv) {
            $p = explode('=', $kv, 2);
            if (count($p) === 2) $parts[trim($p[0])] = trim($p[1]);
        }
        $t = $parts['t'] ?? '';
        $sig = $parts['te'] ?? ($parts['li'] ?? '');
        if ($t === '' || $sig === '') return false;
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        return hash_equals($expected, $sig);
    }
}
