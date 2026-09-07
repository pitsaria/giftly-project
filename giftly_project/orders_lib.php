<?php
/**
 * Order-cancellation helpers.
 *
 * A shopper can't cancel an order outright — they submit a cancellation
 * request with a reason, and an admin approves or declines it. Extra columns
 * on `orders` track that:
 *   cancel_status        none | requested | approved | rejected
 *   cancel_reason        the shopper's reason
 *   cancel_requested_at  when they asked
 *   cancel_reviewed_at   when the admin decided
 *   cancel_admin_note    optional note from the admin (e.g. why declined)
 */

if (!function_exists('orders_ensure_schema')) {

    function orders_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['orders_schema_ok_v4'])) {
            return;
        }

        $c = $conn->query("SELECT 1 AS c FROM information_schema.columns
                           WHERE table_name = 'orders' AND column_name = 'cancel_status'");
        if (!($c && $c->num_rows > 0)) {
            $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancel_status VARCHAR(20) NOT NULL DEFAULT 'none'");
            $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancel_reason TEXT");
            $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancel_requested_at TIMESTAMP");
            $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancel_reviewed_at TIMESTAMP");
            $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancel_admin_note TEXT");
        }
        $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS refunded_at TIMESTAMP");

        // Card payments: we only ever keep the last 4 digits + cardholder name.
        $cc = $conn->query("SELECT 1 AS c FROM information_schema.columns
                            WHERE table_name = 'orders' AND column_name = 'card_last4'");
        if (!($cc && $cc->num_rows > 0)) {
            $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS card_last4 VARCHAR(4)");
            $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS card_holder VARCHAR(120)");
        }

        // Promo / discount columns
        $pc = $conn->query("SELECT 1 AS c FROM information_schema.columns
                            WHERE table_name = 'orders' AND column_name = 'discount_amount'");
        if (!($pc && $pc->num_rows > 0)) {
            $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS promo_code VARCHAR(40)");
            $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS promo_id INTEGER");
            $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS discount_amount NUMERIC(10,2) NOT NULL DEFAULT 0");
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['orders_schema_ok_v4'] = true;
        }
    }

    /** Preset cancellation reasons shown to the shopper. */
    function orders_cancel_reasons() {
        return [
            'Changed my mind',
            'Ordered by mistake',
            'Found a better price elsewhere',
            'Delivery is too slow / date too far out',
            'Wrong item, quantity, or details',
            'Financial reasons',
            'Other',
        ];
    }

    /**
     * Approve a cancellation request: mark the order cancelled and put the
     * stock back. Assumes the caller has already checked admin rights.
     */
    function orders_approve_cancel($conn, $order_id) {
        $order_id = intval($order_id);
        $conn->begin_transaction();
        try {
            $r = $conn->query("SELECT status, cancel_status, payment_method, payment_status FROM orders WHERE id = $order_id FOR UPDATE");
            if (!$r || $r->num_rows === 0) throw new Exception('Order not found.');
            $o = $r->fetch_assoc();
            if ($o['cancel_status'] !== 'requested') throw new Exception('No pending cancellation request.');

            // restore stock for every line item
            $items = $conn->query("SELECT product_id, quantity FROM order_items WHERE order_id = $order_id");
            while ($items && $it = $items->fetch_assoc()) {
                $pid = intval($it['product_id']);
                $qty = intval($it['quantity']);
                $conn->query("UPDATE products SET quantity = quantity + $qty WHERE id = $pid");
            }

            // a completed online payment now needs refunding
            $refund_sql = '';
            if (($o['payment_method'] ?? 'cod') !== 'cod' && ($o['payment_status'] ?? '') === 'paid') {
                $refund_sql = ", payment_status = 'refunded', refunded_at = CURRENT_TIMESTAMP";
            }

            $conn->query("UPDATE orders
                          SET status = 'cancelled', cancel_status = 'approved',
                              cancel_reviewed_at = CURRENT_TIMESTAMP $refund_sql
                          WHERE id = $order_id");
            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            return false;
        }
    }

    /** Decline a cancellation request; the order continues as normal. */
    function orders_reject_cancel($conn, $order_id, $note = '') {
        $order_id = intval($order_id);
        $note_esc = $conn->real_escape_string(mb_substr(trim($note), 0, 500));
        $ok = $conn->query("UPDATE orders
                            SET cancel_status = 'rejected',
                                cancel_admin_note = '$note_esc',
                                cancel_reviewed_at = CURRENT_TIMESTAMP
                            WHERE id = $order_id AND cancel_status = 'requested'");
        return $ok && $conn->affected_rows > 0;
    }
}
