<?php
/**
 * Outbound email via the Brevo (Sendinblue) HTTPS API — no SDK, no domain
 * needed: Brevo just requires ONE verified sender address, then you can send
 * to any recipient. Free tier is 300 emails/day.
 *
 * Env vars (Render):
 *   BREVO_API_KEY    xkeysib-...      (required — without it email is a no-op)
 *   MAIL_FROM        "Giftly <your-verified-sender@example.com>"
 *                    the email here MUST be a verified sender in Brevo
 *                    (Senders, Domains & Dedicated IPs -> Senders).
 */

if (!function_exists('mail_send')) {

    function mail_api_key()   { return trim((string) getenv('BREVO_API_KEY')); }
    function mail_configured() { return mail_api_key() !== ''; }

    /** Last failure reason from mail_send() / send_order_email(), for diagnostics. */
    function mail_last_error($set = null) {
        static $e = '';
        if ($set !== null) $e = (string) $set;
        return $e;
    }

    /** Parse MAIL_FROM ("Name <email>" or "email") into ['name'=>, 'email'=>]. */
    function mail_sender() {
        $raw = getenv('MAIL_FROM') ?: 'Giftly <no-reply@giftly.example>';
        if (preg_match('/^\s*"?(.*?)"?\s*<\s*([^>]+?)\s*>\s*$/', $raw, $m)) {
            return ['name' => $m[1] !== '' ? $m[1] : 'Giftly', 'email' => trim($m[2])];
        }
        return ['name' => 'Giftly', 'email' => trim($raw)];
    }

    /** Send an HTML email. Returns true on success. */
    function mail_send($to, $subject, $html, $text = '') {
        $key = mail_api_key();
        if ($key === '' || !$to) return false;

        $recips = array_map(function ($addr) { return ['email' => $addr]; }, is_array($to) ? array_values($to) : [$to]);
        $sender = mail_sender();

        $payload = [
            'sender'      => $sender,
            'to'          => $recips,
            'subject'     => $subject,
            'htmlContent' => $html,
        ];
        if ($text !== '') $payload['textContent'] = $text;
        $body = json_encode($payload);

        $url = 'https://api.brevo.com/v3/smtp/email';
        $headers = ['api-key: ' . $key, 'Content-Type: application/json', 'Accept: application/json'];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_POSTFIELDS     => $body,
            ]);
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errs = curl_error($ch);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ]]);
            $resp = @file_get_contents($url, false, $ctx);
            $code = 0; $errs = '';
            if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
                $code = (int) $m[1];
            }
        }

        if ($code >= 200 && $code < 300) { mail_last_error(''); return true; }
        $why = 'Brevo HTTP ' . $code . ' ' . ($errs ?: '') . ' ' . (string) $resp;
        mail_last_error($why);
        error_log('Brevo email failed: ' . $why);
        return false;
    }

    /**
     * Email the customer about an order.
     *   $paid = false  -> "order confirmed / have cash ready" (COD placement)
     *   $paid = true   -> "payment received" (online payment cleared)
     * No-op (returns false) when email isn't configured.
     */
    function send_order_email($conn, $order_id, $paid = false) {
        if (!mail_configured()) { mail_last_error('BREVO_API_KEY not set'); return false; }
        $order_id = (int) $order_id;

        $o = $conn->query("SELECT o.*, u.email AS to_email, u.name AS to_name
                           FROM orders o JOIN users u ON u.id = o.user_id
                           WHERE o.id = $order_id");
        $order = $o ? $o->fetch_assoc() : null;
        if (!$order) { mail_last_error("order #$order_id not found"); error_log("send_order_email: $order_id not found"); return false; }
        if (empty($order['to_email'])) { mail_last_error("no email on the customer's account"); error_log("send_order_email: order #$order_id customer has no email"); return false; }

        $rows = '';
        $its = $conn->query("SELECT oi.quantity, oi.price, p.name
                             FROM order_items oi JOIN products p ON p.id = oi.product_id
                             WHERE oi.order_id = $order_id");
        while ($its && $it = $its->fetch_assoc()) {
            $is_free = ((float) $it['price']) <= 0;
            $name_html = htmlspecialchars($it['name']) . ' &times; ' . (int) $it['quantity'] . ($is_free ? ' <span style="color:#2e7d32;font-weight:600;">(free gift 🎁)</span>' : '');
            $amt_html = $is_free ? '<span style="color:#2e7d32;">FREE</span>' : 'PHP ' . number_format($it['price'] * $it['quantity'], 2);
            $rows .= '<tr><td style="padding:6px 0;color:#555;">' . $name_html . '</td>'
                   . '<td style="padding:6px 0;text-align:right;color:#222;">' . $amt_html . '</td></tr>';
        }

        $total = number_format((float) $order['total_amount'], 2);
        $discount_row = '';
        if (!empty($order['discount_amount']) && (float) $order['discount_amount'] > 0) {
            $dc = number_format((float) $order['discount_amount'], 2);
            $dlabel = !empty($order['promo_code']) ? 'Discount (' . htmlspecialchars($order['promo_code']) . ')' : 'Discount';
            $discount_row = '<tr><td style="padding:6px 0;color:#2e7d32;">' . $dlabel . '</td>'
                          . '<td style="padding:6px 0;text-align:right;color:#2e7d32;">&minus; PHP ' . $dc . '</td></tr>';
        }
        if ($paid) {
            $heading = 'Payment received — order #' . $order_id;
            $title   = 'Payment received 🎉';
            $lead    = "Thanks! We've received your payment and your order is being prepared.";
        } else {
            $heading = 'Order confirmed — #' . $order_id;
            $title   = 'Order confirmed 🎁';
            $lead    = 'Thanks for your order! Please have <strong>PHP ' . $total . '</strong> ready in cash when it arrives.';
        }

        $when = !empty($order['delivery_date'])
            ? date('F j, Y', strtotime($order['delivery_date']))
            : 'to be scheduled';

        $inner = '<p style="color:#555;font-size:14px;line-height:1.6;">' . $lead . '</p>'
               . '<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:13.5px;">' . $rows . $discount_row
               . '<tr><td style="padding:10px 0 0;border-top:1px solid #eee;font-weight:700;">Total</td>'
               . '<td style="padding:10px 0 0;border-top:1px solid #eee;text-align:right;font-weight:700;">PHP ' . $total . '</td></tr></table>'
               . '<p style="color:#777;font-size:13px;line-height:1.6;">Deliver to: ' . htmlspecialchars($order['address'] . ', ' . $order['city']) . '<br>'
               . 'Delivery date: ' . htmlspecialchars($when) . '<br>'
               . 'Payment: ' . htmlspecialchars(ucfirst($order['payment_method'])) . '</p>';

        return mail_send($order['to_email'], $heading, mail_wrap($title, $inner));
    }

    /**
     * Email the customer when an admin moves an order to 'shipped' or
     * 'delivered'. Other statuses are ignored. No-op if email isn't configured.
     */
    function send_status_email($conn, $order_id, $status) {
        if (!mail_configured()) return false;
        $status   = strtolower(trim((string) $status));
        if (!in_array($status, ['shipped', 'delivered'], true)) return false;
        $order_id = (int) $order_id;

        $o = $conn->query("SELECT o.*, u.email AS to_email FROM orders o
                           JOIN users u ON u.id = o.user_id WHERE o.id = $order_id");
        $order = $o ? $o->fetch_assoc() : null;
        if (!$order || empty($order['to_email'])) {
            mail_last_error("order #$order_id: no order / no customer email");
            return false;
        }

        $addr = htmlspecialchars($order['address'] . ', ' . $order['city']);
        $when = !empty($order['delivery_date']) ? date('F j, Y', strtotime($order['delivery_date'])) : 'soon';

        if ($status === 'shipped') {
            $heading = 'Your order is on the way — #' . $order_id;
            $title   = 'On the way 🚚';
            $inner   = '<p style="color:#555;font-size:14px;line-height:1.6;">Good news — order <strong>#' . $order_id
                     . '</strong> has been shipped and is heading to you.</p>'
                     . '<p style="color:#777;font-size:13px;line-height:1.6;">Delivering to: ' . $addr . '<br>Expected: ' . htmlspecialchars($when) . '</p>'
                     . '<p style="font-size:13px;"><a href="' . htmlspecialchars(app_base_url_safe()) . '/profile.php?tab=orders" style="color:#ff8ba7;font-weight:600;">Track it in My Orders</a></p>';
        } else {
            $heading = 'Your order was delivered — #' . $order_id;
            $title   = 'Delivered ✅';
            $inner   = '<p style="color:#555;font-size:14px;line-height:1.6;">Order <strong>#' . $order_id
                     . '</strong> has been marked delivered. We hope you love it! 🎁</p>'
                     . '<p style="color:#777;font-size:13px;line-height:1.6;">Once you\'ve got it in hand, head to '
                     . '<a href="' . htmlspecialchars(app_base_url_safe()) . '/profile.php?tab=orders" style="color:#ff8ba7;font-weight:600;">My Orders</a> '
                     . 'to confirm receipt and leave a review.</p>';
        }

        return mail_send($order['to_email'], $heading, mail_wrap($title, $inner));
    }

    /**
     * Email the customer that their cancellation request was approved.
     * If the order was paid online (payment_status is now 'refunded'), the
     * email also says the payment is being refunded.
     */
    function send_cancel_approved_email($conn, $order_id) {
        if (!mail_configured()) return false;
        $order_id = (int) $order_id;

        $o = $conn->query("SELECT o.*, u.email AS to_email FROM orders o
                           JOIN users u ON u.id = o.user_id WHERE o.id = $order_id");
        $order = $o ? $o->fetch_assoc() : null;
        if (!$order || empty($order['to_email'])) {
            mail_last_error("order #$order_id: no order / no customer email");
            return false;
        }

        $total    = number_format((float) $order['total_amount'], 2);
        $refunded = ($order['payment_status'] ?? '') === 'refunded';
        $method   = ucfirst($order['payment_method'] ?? 'cod');
        if ($order['payment_method'] === 'card' && !empty($order['card_last4'])) {
            $method = 'card ending ' . $order['card_last4'];
        }

        $inner = '<p style="color:#555;font-size:14px;line-height:1.6;">Your request to cancel order '
               . '<strong>#' . $order_id . '</strong> has been approved. The order is now cancelled and any items have been released.</p>';

        if ($refunded) {
            $inner .= '<div style="background:#e8f5e9;border-radius:12px;padding:14px 16px;margin:14px 0;color:#2e7d32;font-size:13.5px;line-height:1.6;">'
                    . '<strong>Refund on the way.</strong> PHP ' . $total . ' will be returned to your ' . htmlspecialchars($method)
                    . '. Online refunds usually take <strong>5–10 business days</strong> to appear, depending on your bank or e-wallet.'
                    . '</div>';
        } else {
            $inner .= '<p style="color:#777;font-size:13px;line-height:1.6;">No payment was collected for this order '
                    . '(cash on delivery), so there\'s nothing to refund.</p>';
        }

        $inner .= '<p style="font-size:13px;">Questions? Just reply to this email.</p>';

        return mail_send($order['to_email'],
            'Cancellation confirmed — order #' . $order_id,
            mail_wrap($refunded ? 'Cancelled & refunded 💸' : 'Order cancelled', $inner));
    }

    /** Best-effort site URL for links inside emails. */
    function app_base_url_safe() {
        $env = getenv('APP_BASE_URL') ?: getenv('RENDER_EXTERNAL_URL');
        if ($env) return rtrim($env, '/');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return 'https://' . $host;
    }

    /** Branded HTML shell for an email body. */
    function mail_wrap($heading, $inner) {
        $h = htmlspecialchars($heading);
        return '<div style="font-family:Arial,Helvetica,sans-serif;background:#fdf2f5;padding:28px 0;">'
             . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,0.06);">'
             . '<div style="background:linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%);padding:22px 28px;">'
             . '<span style="color:#fff;font-size:20px;font-weight:700;">🎁 Giftly</span></div>'
             . '<div style="padding:28px;">'
             . '<h2 style="margin:0 0 14px;font-size:19px;color:#222;">' . $h . '</h2>'
             . $inner
             . '</div>'
             . '<div style="padding:16px 28px;border-top:1px solid #f2f2f2;color:#999;font-size:12px;">'
             . 'Giftly — Premium Gift Boxes. This is an automated message.'
             . '</div></div></div>';
    }
}
