<?php
/**
 * PayMongo webhook receiver.
 * Configure at Developers -> Webhooks:
 *   URL   https://<your-host>/paymongo_webhook.php
 *   events checkout_session.payment.paid  (and .failed if you like)
 */

include 'db_connect.php';
include 'paymongo_lib.php';
pay_ensure_schema($conn);

$raw = file_get_contents('php://input');
$sig = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

if (!paymongo_verify_signature($raw, $sig)) {
    http_response_code(400);
    echo 'invalid signature';
    exit();
}

$evt      = json_decode($raw, true);
$type     = $evt['data']['attributes']['type'] ?? '';
$resource = $evt['data']['attributes']['data'] ?? [];
$attr     = $resource['attributes'] ?? [];
$ref      = $resource['id'] ?? '';

$order_id = (int) ($attr['metadata']['order_id'] ?? $attr['reference_number'] ?? 0);

if ($order_id > 0) {
    if ($type === 'checkout_session.payment.paid' || $type === 'payment.paid') {
        // best-effort: pull the actual method (gcash / card / paymaya ...)
        $method = '';
        $payments = $attr['payments'] ?? [];
        if (!empty($payments[0]['attributes']['source']['type'])) {
            $method = $payments[0]['attributes']['source']['type'];
        } elseif (!empty($attr['source']['type'])) {
            $method = $attr['source']['type'];
        }
        pay_mark_paid($conn, $order_id, $ref, $method);
    }
    // Note: a single `payment.failed` is not treated as final — the shopper may
    // retry on the same checkout session. pay_sweep_stale() handles abandonment.
}

http_response_code(200);
echo 'ok';
