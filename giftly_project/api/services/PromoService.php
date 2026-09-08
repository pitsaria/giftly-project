<?php
// api/services/PromoService.php
//
// Ports promo_apply.php + index.php's "Special Promotions" section onto the
// token-authenticated API. Thin caller of promo_lib.php's promo_evaluate() —
// same calculator both website checkouts use. The website's own PHP pages
// are untouched.

require_once 'config/database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/../../promo_lib.php';
require_once __DIR__ . '/../../catalog_lib.php';
require_once __DIR__ . '/../../build_a_box_lib.php';

class PromoService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        promo_ensure_schema($conn);
        catalog_ensure_schema($conn);
        bab_ensure_schema($conn);
    }

    private function getUserId($headers) {
        return AuthHelper::resolveUserId($this->conn, $headers);
    }

    // POST promo/evaluate — { scope: 'products'|'box', code?, selected_ids?: number[], box_id?: number }
    public function evaluate($input, $headers) {
        $user_id = $this->getUserId($headers);
        if (!$user_id) {
            sendError('Unauthorized', 401);
            return;
        }

        $scope = ((isset($input['scope']) ? $input['scope'] : 'products') === 'box') ? 'box' : 'products';
        $code  = isset($input['code']) ? trim((string) $input['code']) : null;

        $subtotal   = 0.0;
        $item_count = 0;
        $box_price  = 0.0;

        if ($scope === 'box') {
            $box_id = isset($input['box_id']) ? intval($input['box_id']) : 0;
            $data = bab_load_box($this->conn, $box_id, $user_id);
            if (!$data) {
                sendError('Box not found', 404);
                return;
            }
            $subtotal   = (float) $data['subtotal'];
            $item_count = (int) $data['item_count'];
            $box_price  = (float) $data['box']['box_price'];
        } else {
            $ids = isset($input['selected_ids']) && is_array($input['selected_ids'])
                ? array_filter(array_map('intval', $input['selected_ids']))
                : [];
            if (!empty($ids)) {
                $ids_str = implode(',', $ids);
                $q = $this->conn->query("SELECT c.quantity, " . catalog_price_sql('p.') . " AS price
                                         FROM carts c JOIN products p ON c.product_id = p.id
                                         WHERE c.user_id = $user_id AND c.id IN ($ids_str)
                                           AND p.is_active = TRUE");
                while ($q && $r = $q->fetch_assoc()) {
                    $subtotal   += (float) $r['price'] * (int) $r['quantity'];
                    $item_count += (int) $r['quantity'];
                }
            }
        }

        $subtotal = round($subtotal, 2);
        $base_ship = ($subtotal > 0 && $subtotal < 300) ? 50.0 : 0.0;

        $eval = promo_evaluate($this->conn, $user_id, [
            'scope' => $scope, 'subtotal' => $subtotal,
            'shipping_fee' => $base_ship, 'item_count' => $item_count,
            'code' => $code,
        ]);

        $grand = $eval['final_total'] + ($scope === 'box' ? round($box_price, 2) : 0.0);

        sendSuccess([
            'scope'             => $scope,
            'item_count'        => $item_count,
            'subtotal'          => round($eval['subtotal'], 2),
            'discount'          => round($eval['discount'], 2),
            'shipping_fee'      => round($eval['shipping_fee'], 2),
            'shipping_waived'   => (bool) $eval['shipping_waived'],
            'box_price'         => round($box_price, 2),
            'total'             => round($grand, 2),
            'lines'             => $eval['lines'],
            'code'              => $eval['code'],
            'code_error'        => $eval['code_error'],
            'code_min_spend'    => round((float) $eval['code_min_spend'], 2),
            'code_max_discount' => round((float) $eval['code_max_discount'], 2),
            'code_terms'        => promo_terms_text($eval['code_min_spend'], $eval['code_max_discount']),
            'free_item'         => $eval['free_item'],
            'free_item_nudge'   => $eval['free_item_nudge'],
        ]);
    }

    // GET promos/active — live promo cards for the home page, no auth required.
    public function activePromos() {
        $promos = [];
        $hp = $this->conn->query("SELECT * FROM promos
                                  WHERE active = TRUE
                                    AND (starts_at IS NULL OR starts_at <= (CURRENT_TIMESTAMP AT TIME ZONE 'UTC'))
                                    AND (ends_at   IS NULL OR ends_at   >  (CURRENT_TIMESTAMP AT TIME ZONE 'UTC'))
                                  ORDER BY (code IS NULL), id DESC
                                  LIMIT 4");
        while ($hp && $r = $hp->fetch_assoc()) {
            $promos[] = $this->homePromoCard($r);
        }
        sendSuccess(['promos' => $promos]);
    }

    // Mirrors index.php's home_promo_card().
    private function homePromoCard($p) {
        $type = $p['type'];
        $headline = 'Special offer';
        $icon = 'pricetag-outline';
        if ($type === 'percent') {
            $headline = rtrim(rtrim(number_format((float) $p['value'], 2), '0'), '.') . '% OFF';
            $icon = 'pricetag-outline';
        } elseif ($type === 'fixed') {
            $headline = 'PHP ' . number_format((float) $p['value'], 0) . ' OFF';
            $icon = 'pricetag-outline';
        } elseif ($type === 'free_shipping') {
            $headline = 'FREE SHIPPING';
            $icon = 'car-outline';
        } elseif ($type === 'free_item') {
            $headline = 'FREE GIFT';
            $icon = 'gift-outline';
        }
        $bits = [];
        if ((float) $p['min_spend'] > 0) {
            $bits[] = 'On orders over PHP ' . number_format((float) $p['min_spend'], 0);
        }
        if (promo_bool($p['first_order_only'])) $bits[] = 'First order only';
        if ($type === 'free_item') $bits[] = 'Buy ' . max(1, (int) ($p['free_item_min_qty'] ?? 3)) . '+ items';
        if (!empty($p['ends_at'])) $bits[] = 'Ends ' . date('M j', strtotime($p['ends_at']));
        if (empty($bits)) $bits[] = 'On your whole order';
        return [
            'headline' => $headline,
            'icon'     => $icon,
            'cond'     => implode(' · ', $bits),
            'code'     => $p['code'] ? strtoupper($p['code']) : '',
        ];
    }
}
