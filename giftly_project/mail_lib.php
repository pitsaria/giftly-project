<?php
/**
 * Outbound email via the Resend HTTPS API (no SDK).
 *
 * Env vars (Render):
 *   RESEND_API_KEY   re_...           (required — without it email is a no-op)
 *   MAIL_FROM        "Giftly <you@yourdomain.com>"
 *                    default "Giftly <onboarding@resend.dev>" — note Resend only
 *                    delivers from onboarding@resend.dev to YOUR OWN account
 *                    email until you verify a domain.
 */

if (!function_exists('mail_send')) {

    function mail_configured() { return trim((string) getenv('RESEND_API_KEY')) !== ''; }
    function mail_from()       { return getenv('MAIL_FROM') ?: 'Giftly <onboarding@resend.dev>'; }

    /** Send an HTML email. Returns true on success. */
    function mail_send($to, $subject, $html, $text = '') {
        $key = trim((string) getenv('RESEND_API_KEY'));
        if ($key === '' || !$to) return false;

        $payload = [
            'from'    => mail_from(),
            'to'      => is_array($to) ? array_values($to) : [$to],
            'subject' => $subject,
            'html'    => $html,
        ];
        if ($text !== '') $payload['text'] = $text;
        $body = json_encode($payload);

        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
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
                'header'  => "Authorization: Bearer $key\r\nContent-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ]]);
            $resp = @file_get_contents('https://api.resend.com/emails', false, $ctx);
            $code = 0; $errs = '';
            if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
                $code = (int) $m[1];
            }
        }

        if ($code >= 200 && $code < 300) return true;
        error_log('Resend email failed (' . $code . '): ' . ($errs ?: '') . ' ' . (string) $resp);
        return false;
    }

    /**
     * Email the customer about an order.
     *   $paid = false  -> "order confirmed / have cash ready" (COD placement)
     *   $paid = true   -> "payment received" (online payment cleared)
     * No-op (returns false) when email isn't configured.
     */
    function send_order_email($conn, $order_id, $paid = false) {
        if (!mail_configured()) return false;
        $order_id = (int) $order_id;

        $o = $conn->query("SELECT o.*, u.email AS to_email, u.name AS to_name
                           FROM orders o JOIN users u ON u.id = o.user_id
                           WHERE o.id = $order_id");
        $order = $o ? $o->fetch_assoc() : null;
        if (!$order || empty($order['to_email'])) return false;

        $rows = '';
        $its = $conn->query("SELECT oi.quantity, oi.price, p.name
                             FROM order_items oi JOIN products p ON p.id = oi.product_id
                             WHERE oi.order_id = $order_id");
        while ($its && $it = $its->fetch_assoc()) {
            $line = number_format($it['price'] * $it['quantity'], 2);
            $rows .= '<tr><td style="padding:6px 0;color:#555;">' . htmlspecialchars($it['name']) . ' &times; ' . (int) $it['quantity'] . '</td>'
                   . '<td style="padding:6px 0;text-align:right;color:#222;">PHP ' . $line . '</td></tr>';
        }

        $total = number_format((float) $order['total_amount'], 2);
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
               . '<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:13.5px;">' . $rows
               . '<tr><td style="padding:10px 0 0;border-top:1px solid #eee;font-weight:700;">Total</td>'
               . '<td style="padding:10px 0 0;border-top:1px solid #eee;text-align:right;font-weight:700;">PHP ' . $total . '</td></tr></table>'
               . '<p style="color:#777;font-size:13px;line-height:1.6;">Deliver to: ' . htmlspecialchars($order['address'] . ', ' . $order['city']) . '<br>'
               . 'Delivery date: ' . htmlspecialchars($when) . '<br>'
               . 'Payment: ' . htmlspecialchars(ucfirst($order['payment_method'])) . '</p>';

        return mail_send($order['to_email'], $heading, mail_wrap($title, $inner));
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
