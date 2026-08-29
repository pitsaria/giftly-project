<?php
/**
 * Build-a-Box shared library.
 *
 * - Idempotent schema bootstrap (safe to run on every request; the heavy work
 *   only happens the first time, when the tables do not exist yet).
 * - Helper functions used by build-a-box.php, box_actions.php, box_checkout.php,
 *   profile_boxes.php and the cart.php "Your Gift Boxes" section.
 *
 * Requires an active $conn (PgCompatMysqli) from db_connect.php.
 */

if (!function_exists('bab_ensure_schema')) {

    /**
     * Create the Build-a-Box tables + seed data the first time they are needed.
     * Gated on to_regclass() so it is a single cheap SELECT on every later hit.
     */
    function bab_ensure_schema($conn) {
        if (!empty($_SESSION['bab_schema_ok_v2'])) {
            return;
        }

        $check = $conn->query("SELECT to_regclass('public.box_items') AS t");
        $row = $check ? $check->fetch_assoc() : null;
        if ($row && !empty($row['t'])) {
            // tables exist — make sure later-added columns are present too
            $col = $conn->query("SELECT 1 AS c FROM information_schema.columns
                                 WHERE table_name = 'boxes' AND column_name = 'card_style'");
            if ($col && $col->num_rows > 0) {
                $_SESSION['bab_schema_ok_v2'] = true;
                return;
            }
        }

        // --- Tables ---------------------------------------------------------
        $conn->query("
            CREATE TABLE IF NOT EXISTS box_sizes (
                id         SERIAL PRIMARY KEY,
                code       VARCHAR(20)  NOT NULL UNIQUE,
                name       VARCHAR(50)  NOT NULL,
                max_items  INTEGER      NOT NULL,
                price      NUMERIC(10,2) NOT NULL DEFAULT 0,
                sort_order INTEGER      NOT NULL DEFAULT 0
            )
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS product_box_sizes (
                product_id  INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
                box_size_id INTEGER NOT NULL REFERENCES box_sizes(id) ON DELETE CASCADE,
                PRIMARY KEY (product_id, box_size_id)
            )
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS boxes (
                id          SERIAL PRIMARY KEY,
                user_id     INTEGER NOT NULL,
                box_size_id INTEGER NOT NULL REFERENCES box_sizes(id),
                letter      TEXT NOT NULL DEFAULT '',
                card_style  VARCHAR(30) NOT NULL DEFAULT 'simple',
                status      VARCHAR(20) NOT NULL DEFAULT 'saved'
                            CHECK (status IN ('saved','in_cart','ordered')),
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        // for databases where 'boxes' predates card_style
        $conn->query("ALTER TABLE boxes ADD COLUMN IF NOT EXISTS card_style VARCHAR(30) NOT NULL DEFAULT 'simple'");

        $conn->query("
            CREATE TABLE IF NOT EXISTS box_items (
                id         SERIAL PRIMARY KEY,
                box_id     INTEGER NOT NULL REFERENCES boxes(id) ON DELETE CASCADE,
                product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
                quantity   INTEGER NOT NULL DEFAULT 1
            )
        ");

        // --- Seed box sizes (no packaging fee -> price 0) -------------------
        $conn->query("
            INSERT INTO box_sizes (code, name, max_items, price, sort_order) VALUES
                ('small',  'Small Box',  5,  0, 1),
                ('medium', 'Medium Box', 10, 0, 2),
                ('large',  'Large Box',  15, 0, 3)
            ON CONFLICT (code) DO NOTHING
        ");

        // --- Backfill: every existing product allowed in every size --------
        $conn->query("
            INSERT INTO product_box_sizes (product_id, box_size_id)
            SELECT p.id, b.id FROM products p CROSS JOIN box_sizes b
            ON CONFLICT DO NOTHING
        ");

        $_SESSION['bab_schema_ok_v2'] = true;
    }

    /**
     * Letter card styles. Keyed by the value stored in boxes.card_style.
     * 'simple' is the default and adds nothing to the order's gift message.
     */
    function bab_card_styles() {
        return [
            'simple'    => ['label' => 'Simple note',      'emoji' => '✉️'],
            'birthday'  => ['label' => 'Birthday',         'emoji' => '🎂'],
            'valentine' => ['label' => "Valentine's",      'emoji' => '💗'],
            'thank_you' => ['label' => 'Thank you',        'emoji' => '🙏'],
            'congrats'  => ['label' => 'Congratulations',  'emoji' => '🎉'],
            'holiday'   => ['label' => 'Holiday',          'emoji' => '🎄'],
            'get_well'  => ['label' => 'Get well soon',    'emoji' => '🌷'],
        ];
    }

    /** Normalise an incoming card_style to a known key ('simple' fallback). */
    function bab_card_style_key($key) {
        $styles = bab_card_styles();
        return isset($styles[$key]) ? $key : 'simple';
    }

    /** All box sizes ordered for display. Returns list of assoc rows. */
    function bab_box_sizes($conn) {
        $out = [];
        $res = $conn->query("SELECT * FROM box_sizes ORDER BY sort_order ASC, max_items ASC");
        while ($res && $r = $res->fetch_assoc()) {
            $r['max_items'] = intval($r['max_items']);
            $r['price']     = floatval($r['price']);
            $out[] = $r;
        }
        return $out;
    }

    /** Single box size by numeric id, or null. */
    function bab_box_size($conn, $id) {
        $id = intval($id);
        $res = $conn->query("SELECT * FROM box_sizes WHERE id = $id");
        if (!$res || $res->num_rows === 0) return null;
        $r = $res->fetch_assoc();
        $r['max_items'] = intval($r['max_items']);
        $r['price']     = floatval($r['price']);
        return $r;
    }

    /** Single box size by code ('small'|'medium'|'large'), or null. */
    function bab_box_size_by_code($conn, $code) {
        $code = $conn->real_escape_string($code);
        $res = $conn->query("SELECT * FROM box_sizes WHERE code = '$code'");
        if (!$res || $res->num_rows === 0) return null;
        $r = $res->fetch_assoc();
        $r['max_items'] = intval($r['max_items']);
        $r['price']     = floatval($r['price']);
        return $r;
    }

    /**
     * Load a box owned by $user_id, with its items joined to products and a
     * validation pass (deleted / out-of-stock / over-stock items).
     * Returns null if the box does not exist or is not owned by the user.
     */
    function bab_load_box($conn, $box_id, $user_id) {
        $box_id  = intval($box_id);
        $user_id = intval($user_id);

        $res = $conn->query("
            SELECT b.*, s.code AS size_code, s.name AS size_name,
                   s.max_items, s.price AS box_price
            FROM boxes b
            JOIN box_sizes s ON s.id = b.box_size_id
            WHERE b.id = $box_id AND b.user_id = $user_id
        ");
        if (!$res || $res->num_rows === 0) return null;
        $box = $res->fetch_assoc();
        $box['max_items'] = intval($box['max_items']);
        $box['box_price'] = floatval($box['box_price']);

        $items = [];
        $issues = [];
        $count = 0;
        $subtotal = 0.0;

        $ir = $conn->query("
            SELECT bi.id AS box_item_id, bi.quantity, bi.product_id,
                   p.name, p.price, p.image, p.quantity AS stock
            FROM box_items bi
            LEFT JOIN products p ON p.id = bi.product_id
            WHERE bi.box_id = $box_id
            ORDER BY bi.id ASC
        ");
        while ($ir && $r = $ir->fetch_assoc()) {
            $qty = intval($r['quantity']);
            $r['quantity'] = $qty;
            if ($r['name'] === null) {
                $r['unavailable'] = 'removed';
                $issues[] = 'An item in this box is no longer available and should be removed.';
            } elseif (intval($r['stock']) <= 0) {
                $r['unavailable'] = 'out_of_stock';
                $issues[] = $r['name'] . ' is out of stock.';
            } elseif ($qty > intval($r['stock'])) {
                $r['unavailable'] = 'low_stock';
                $issues[] = $r['name'] . ': only ' . intval($r['stock']) . ' left (box has ' . $qty . ').';
            } else {
                $r['unavailable'] = null;
            }
            $r['price'] = $r['price'] !== null ? floatval($r['price']) : 0.0;
            $count += $qty;
            $subtotal += $r['price'] * $qty;
            $items[] = $r;
        }

        return [
            'box'        => $box,
            'items'      => $items,
            'issues'     => $issues,
            'item_count' => $count,
            'subtotal'   => $subtotal,
            'total'      => $subtotal + $box['box_price'],
        ];
    }

    /** Count boxes for a user by status (status null = any). */
    function bab_box_count($conn, $user_id, $status = null) {
        $user_id = intval($user_id);
        $sql = "SELECT COUNT(*) AS c FROM boxes WHERE user_id = $user_id";
        if ($status !== null) {
            $status = $conn->real_escape_string($status);
            $sql .= " AND status = '$status'";
        }
        $res = $conn->query($sql);
        return $res ? intval($res->fetch_assoc()['c']) : 0;
    }
}
