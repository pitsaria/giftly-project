<?php
/**
 * Promotions engine — discount codes + automatic discounts, shared by both
 * checkouts (products: checkout_selected.php, gift boxes: box_checkout.php)
 * and the promo_apply.php AJAX endpoint.
 *
 * Rules (see the promos plan):
 *  - Sale prices are already baked into the subtotal passed in.
 *  - At most ONE typed code per order; automatic promos also apply on top.
 *  - Apply order: percent -> fixed -> free shipping (-> free item, Phase 3).
 *  - Total item discount can never exceed the subtotal; the total never goes
 *    negative.
 *
 * Idempotent schema bootstrap, same pattern as the other *_lib.php files.
 */

// Free-item nudges resolve a product image via img_url() — the website loads
// this through db_connect.php already, but the mobile API's services (which
// require this file directly) never did, causing "Call to undefined function
// img_url()" fatal errors whenever a cart sat 1-2 items short of an active
// buy-N-get-1-free auto promo.
require_once __DIR__ . '/supabase_storage.php';

if (!function_exists('promo_ensure_schema')) {

    function promo_ensure_schema($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['promo_schema_ok_v1'])) {
            return;
        }

        $c = $conn->query("SELECT to_regclass('public.promos') AS t");
        $exists = $c && !empty(($c->fetch_assoc()['t'] ?? null));
        if (!$exists) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS promos (
                    id                   SERIAL PRIMARY KEY,
                    code                 VARCHAR(40) UNIQUE,
                    name                 VARCHAR(120) NOT NULL,
                    type                 VARCHAR(20)  NOT NULL,   -- percent | fixed | free_shipping | free_item
                    value                NUMERIC(10,2) NOT NULL DEFAULT 0,
                    auto                 BOOLEAN NOT NULL DEFAULT FALSE,
                    min_spend            NUMERIC(10,2) NOT NULL DEFAULT 0,
                    first_order_only     BOOLEAN NOT NULL DEFAULT FALSE,
                    applies_to           VARCHAR(20) NOT NULL DEFAULT 'all',  -- all | products | box
                    free_item_product_id INTEGER,
                    free_item_min_qty    SMALLINT NOT NULL DEFAULT 3,
                    max_discount         NUMERIC(10,2),
                    usage_limit          INTEGER,
                    per_user_limit       SMALLINT NOT NULL DEFAULT 1,
                    starts_at            TIMESTAMP,
                    ends_at              TIMESTAMP,
                    active               BOOLEAN NOT NULL DEFAULT TRUE,
                    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $conn->query("
                CREATE TABLE IF NOT EXISTS promo_redemptions (
                    id              SERIAL PRIMARY KEY,
                    promo_id        INTEGER NOT NULL REFERENCES promos(id) ON DELETE CASCADE,
                    user_id         INTEGER NOT NULL,
                    order_id        INTEGER NOT NULL,
                    code            VARCHAR(40),
                    discount_amount NUMERIC(10,2) NOT NULL DEFAULT 0,
                    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $conn->query("CREATE INDEX IF NOT EXISTS idx_promo_redemptions_promo ON promo_redemptions (promo_id)");
            $conn->query("CREATE INDEX IF NOT EXISTS idx_promo_redemptions_order ON promo_redemptions (order_id)");

            // --- seed a few starter promos (only on first create; tweak or
            //     switch them off in the DB / the admin Promos page later) ---
            $conn->query("INSERT INTO promos (code, name, type, value, auto, first_order_only, applies_to)
                          VALUES (NULL, 'First order · 10% off', 'percent', 10, TRUE, TRUE, 'all')");
            $conn->query("INSERT INTO promos (code, name, type, value, auto, min_spend, applies_to)
                          VALUES (NULL, 'Free shipping over PHP 250', 'free_shipping', 0, TRUE, 250, 'all')");
            $conn->query("INSERT INTO promos (code, name, type, value, min_spend, per_user_limit, applies_to)
                          VALUES ('GIFTLY10', 'GIFTLY10 · 10% off', 'percent', 10, 500, 1, 'all')");
            $conn->query("INSERT INTO promos (code, name, type, value, min_spend, per_user_limit, applies_to)
                          VALUES ('WELCOME50', 'WELCOME50 · PHP 50 off', 'fixed', 50, 300, 1, 'all')");
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['promo_schema_ok_v1'] = true;
        }
    }

    /** pg boolean-ish helper. */
    function promo_bool($v) {
        return ($v === true || $v === 't' || $v === '1' || $v === 1);
    }

    /** Customer-facing label for a promo row. */
    function promo_label($p) {
        $n = trim((string) ($p['name'] ?? ''));
        if ($n !== '') return $n;
        switch ($p['type']) {
            case 'percent':       return rtrim(rtrim(number_format((float) $p['value'], 2), '0'), '.') . '% off';
            case 'fixed':         return 'PHP ' . number_format((float) $p['value'], 2) . ' off';
            case 'free_shipping': return 'Free shipping';
            case 'free_item':     return 'Free gift';
        }
        return 'Discount';
    }

    /** Orders this user has already placed (cancelled ones don't count). */
    function promo_user_order_count($conn, $user_id) {
        $uid = (int) $user_id;
        $r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE user_id = $uid AND status <> 'cancelled'");
        return ($r && $r->num_rows) ? (int) $r->fetch_assoc()['c'] : 0;
    }

    /** How many times a promo has been redeemed (optionally by one user). Cancelled orders free the slot. */
    function promo_redemption_count($conn, $promo_id, $user_id = null) {
        $pid = (int) $promo_id;
        $sql = "SELECT COUNT(*) AS c FROM promo_redemptions r
                JOIN orders o ON o.id = r.order_id
                WHERE r.promo_id = $pid AND o.status <> 'cancelled'";
        if ($user_id !== null) $sql .= " AND r.user_id = " . (int) $user_id;
        $r = $conn->query($sql);
        return ($r && $r->num_rows) ? (int) $r->fetch_assoc()['c'] : 0;
    }

    /**
     * Reason a promo can't be applied for this cart, or '' when it's good.
     * Works for both typed codes and automatic promos.
     */
    /**
     * The product a "free item" promo gives away, or null when it can't be
     * fulfilled right now (missing / hidden / out of stock).
     */
    function promo_free_item_product($conn, $product_id) {
        static $cache = [];
        $pid = (int) $product_id;
        if ($pid <= 0) return null;
        if (array_key_exists($pid, $cache)) return $cache[$pid];
        // products.is_active is guaranteed to exist (catalog_lib schema)
        $r = $conn->query("SELECT id, name, image, price, quantity FROM products WHERE id = $pid AND is_active = TRUE");
        $row = ($r && $r->num_rows) ? $r->fetch_assoc() : null;
        $cache[$pid] = (!$row || (int) $row['quantity'] <= 0) ? null : [
            'id'    => (int) $row['id'],
            'name'  => $row['name'],
            'image' => $row['image'],
            'price' => (float) $row['price'],
        ];
        return $cache[$pid];
    }

    function promo_reason($conn, $p, $user_id, $scope, $subtotal, $item_count = null) {
        if (!$p)                          return "That code isn't valid.";
        if (!promo_bool($p['active']))    return "That code is no longer active.";
        if (!empty($p['starts_at']) && strtotime($p['starts_at'] . ' UTC') > time()) return "That code isn't active yet.";
        if (!empty($p['ends_at'])   && strtotime($p['ends_at'] . ' UTC')   < time()) return "That code has expired.";

        $applies = $p['applies_to'] ?: 'all';
        if ($applies !== 'all' && $applies !== $scope) {
            return $scope === 'box'
                ? "That code doesn't apply to gift boxes."
                : "That code doesn't apply to these items.";
        }
        if ((float) $p['min_spend'] > 0 && $subtotal < (float) $p['min_spend']) {
            return 'Spend at least PHP ' . number_format((float) $p['min_spend'], 2) . ' to use this.';
        }
        if ($p['type'] === 'free_item') {
            $need = max(1, (int) ($p['free_item_min_qty'] ?? 3));
            if ($item_count !== null && (int) $item_count < $need) {
                return 'Add ' . ($need - (int) $item_count) . ' more item' . (($need - (int) $item_count) === 1 ? '' : 's') . ' for the free gift.';
            }
            if (!promo_free_item_product($conn, $p['free_item_product_id'] ?? 0)) {
                return 'The free gift is out of stock.';
            }
        }
        if (promo_bool($p['first_order_only']) && promo_user_order_count($conn, $user_id) > 0) {
            return 'This is for your first order only.';
        }
        $pid = (int) $p['id'];
        if ($p['usage_limit'] !== null && $p['usage_limit'] !== '' && (int) $p['usage_limit'] > 0
            && promo_redemption_count($conn, $pid) >= (int) $p['usage_limit']) {
            return 'This code has reached its limit.';
        }
        $perUser = (int) ($p['per_user_limit'] ?? 1);
        if ($perUser > 0 && promo_redemption_count($conn, $pid, $user_id) >= $perUser) {
            return "You've already used this code.";
        }
        return '';
    }

    /**
     * Work out every discount that applies.
     *
     * $ctx = [
     *   'scope'        => 'products' | 'box',
     *   'subtotal'     => float,   // items only, sale prices already applied
     *   'shipping_fee' => float,   // the fee before any promo
     *   'item_count'   => int,
     *   'code'         => string|null,   // a typed code to try
     * ]
     *
     * Returns an array with: subtotal, shipping_fee (after waiver), discount,
     * shipping_waived, final_total, lines [{label, amount<0}], code, code_id,
     * code_error, applied [{id, code, amount}].
     */
    function promo_evaluate($conn, $user_id, $ctx) {
        $scope    = (($ctx['scope'] ?? 'products') === 'box') ? 'box' : 'products';
        $subtotal = round(max(0.0, (float) ($ctx['subtotal'] ?? 0)), 2);
        $base_ship = round(max(0.0, (float) ($ctx['shipping_fee'] ?? 0)), 2);
        $item_count = (int) ($ctx['item_count'] ?? 0);
        $code     = strtoupper(trim((string) ($ctx['code'] ?? '')));

        $out = [
            'subtotal'       => $subtotal,
            'shipping_fee'   => $base_ship,
            'discount'       => 0.0,
            'shipping_waived'=> false,
            'final_total'    => round($subtotal + $base_ship, 2),
            'lines'          => [],
            'code'           => '',
            'code_id'        => null,
            'code_error'     => '',
            'code_min_spend' => 0.0,
            'code_max_discount' => 0.0,
            'applied'        => [],
            'free_item'      => null,
            'free_item_nudge'=> null,
        ];
        if ($subtotal <= 0) return $out;

        $promos = [];
        $seen   = [];

        // automatic promos
        $sesc = $conn->real_escape_string($scope);
        $auto = $conn->query("SELECT * FROM promos
                              WHERE auto = TRUE AND active = TRUE
                                AND applies_to IN ('all', '$sesc')
                              ORDER BY id ASC");
        while ($auto && $r = $auto->fetch_assoc()) {
            $why = promo_reason($conn, $r, $user_id, $scope, $subtotal, $item_count);
            if ($why !== '') {
                // "so close" nudge for an auto free-item promo
                if ($r['type'] === 'free_item' && $out['free_item_nudge'] === null) {
                    $need = max(1, (int) ($r['free_item_min_qty'] ?? 3));
                    $more = $need - $item_count;
                    if ($more > 0 && $more <= 2 && promo_free_item_product($conn, $r['free_item_product_id'] ?? 0)) {
                        $fi = promo_free_item_product($conn, $r['free_item_product_id'] ?? 0);
                        $out['free_item_nudge'] = ['name' => $fi['name'], 'more' => $more, 'image' => img_url($fi['image'] ?? '')];
                    }
                }
                continue;
            }
            $promos[] = $r;
            $seen[(int) $r['id']] = true;
        }

        // typed code
        if ($code !== '') {
            $cesc = $conn->real_escape_string($code);
            $cr = $conn->query("SELECT * FROM promos WHERE UPPER(code) = '$cesc' LIMIT 1");
            $crow = ($cr && $cr->num_rows) ? $cr->fetch_assoc() : null;
            $reason = promo_reason($conn, $crow, $user_id, $scope, $subtotal, $item_count);
            if ($reason !== '') {
                $out['code_error'] = $reason;
            } else {
                $out['code']    = strtoupper($crow['code']);
                $out['code_id'] = (int) $crow['id'];
                $out['code_min_spend']    = (float) $crow['min_spend'];
                $out['code_max_discount'] = ($crow['max_discount'] !== null && $crow['max_discount'] !== '') ? (float) $crow['max_discount'] : 0.0;
                if (empty($seen[(int) $crow['id']])) {
                    $promos[] = $crow; // not already applied automatically
                }
            }
        }

        // order the promos: percent, then fixed, then free_shipping
        $rank = ['percent' => 0, 'fixed' => 1, 'free_shipping' => 2, 'free_item' => 3];
        usort($promos, function ($a, $b) use ($rank) {
            return ($rank[$a['type']] ?? 9) <=> ($rank[$b['type']] ?? 9);
        });

        $disc = 0.0;
        $ship_waived = false;
        foreach ($promos as $p) {
            $type = $p['type'];
            $remaining = max(0.0, round($subtotal - $disc, 2));

            if ($type === 'percent' && $remaining > 0) {
                $amt = $remaining * ((float) $p['value'] / 100.0);
                if ($p['max_discount'] !== null && $p['max_discount'] !== '' && (float) $p['max_discount'] > 0) {
                    $amt = min($amt, (float) $p['max_discount']);
                }
                $amt = round(min($amt, $remaining), 2);
                if ($amt > 0) {
                    $disc += $amt;
                    $out['lines'][]   = ['label' => promo_label($p), 'amount' => -$amt];
                    $out['applied'][] = ['id' => (int) $p['id'], 'code' => $p['code'], 'amount' => $amt];
                }
            } elseif ($type === 'fixed' && $remaining > 0) {
                $amt = round(min((float) $p['value'], $remaining), 2);
                if ($amt > 0) {
                    $disc += $amt;
                    $out['lines'][]   = ['label' => promo_label($p), 'amount' => -$amt];
                    $out['applied'][] = ['id' => (int) $p['id'], 'code' => $p['code'], 'amount' => $amt];
                }
            } elseif ($type === 'free_shipping' && $base_ship > 0 && !$ship_waived) {
                $ship_waived = true;
                $out['lines'][]   = ['label' => promo_label($p), 'amount' => -$base_ship];
                $out['applied'][] = ['id' => (int) $p['id'], 'code' => $p['code'], 'amount' => $base_ship];
            } elseif ($type === 'free_item' && $out['free_item'] === null) {
                $fi = promo_free_item_product($conn, $p['free_item_product_id'] ?? 0);
                if ($fi) {
                    $out['free_item'] = [
                        'promo_id'   => (int) $p['id'],
                        'code'       => $p['code'],
                        'product_id' => $fi['id'],
                        'name'       => $fi['name'],
                        'image'      => $fi['image'],
                        'value'      => $fi['price'],
                    ];
                    $out['lines'][]   = ['label' => 'Free: ' . $fi['name'], 'amount' => 0];
                    $out['applied'][] = ['id' => (int) $p['id'], 'code' => $p['code'], 'amount' => $fi['price']];
                }
            }
        }

        $disc = round(min($disc, $subtotal), 2);
        $ship = $ship_waived ? 0.0 : $base_ship;
        $out['discount']        = $disc;
        $out['shipping_fee']    = $ship;
        $out['shipping_waived'] = $ship_waived;
        $out['final_total']     = round(max(0.0, $subtotal - $disc) + $ship, 2);
        return $out;
    }

    /** Write redemption rows after an order is placed. */
    function promo_record($conn, $eval, $user_id, $order_id) {
        $uid = (int) $user_id;
        $oid = (int) $order_id;
        foreach ($eval['applied'] as $a) {
            $pid = (int) $a['id'];
            $amt = round((float) $a['amount'], 2);
            $code = $a['code'] !== null ? "'" . $conn->real_escape_string($a['code']) . "'" : 'NULL';
            $conn->query("INSERT INTO promo_redemptions (promo_id, user_id, order_id, code, discount_amount)
                          VALUES ($pid, $uid, $oid, $code, $amt)");
        }
    }

    /** "Min spend PHP 500 · Max discount PHP 200" for an applied code (empty when neither applies). */
    function promo_terms_text($min_spend, $max_discount) {
        $b = [];
        if ((float) $min_spend > 0)    $b[] = 'Min spend PHP ' . number_format((float) $min_spend, 0);
        if ((float) $max_discount > 0) $b[] = 'Max discount PHP ' . number_format((float) $max_discount, 0);
        return implode(' · ', $b);
    }

    /** The session key that holds the applied code for a given checkout scope. */
    function promo_session_key($scope) {
        return 'promo_code_' . (($scope === 'box') ? 'box' : 'products');
    }
}
