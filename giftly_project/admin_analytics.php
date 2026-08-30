<?php
include 'db_connect.php';

// --- admin gate ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = (int) $_SESSION['user_id'];
$user_check = $conn->query("SELECT role FROM users WHERE id = $user_id");
$user_data = $user_check ? $user_check->fetch_assoc() : null;
if (!$user_data || $user_data['role'] !== 'admin') {
    header("Location: shop.php");
    exit();
}

include 'admin_header.php';

$money = function ($n) { return 'PHP ' . number_format((float) $n, 2); };

/* ---------- headline numbers ---------- */
$rev_all   = (float) ($conn->query("SELECT COALESCE(SUM(total_amount),0) AS s FROM orders WHERE status <> 'cancelled'")->fetch_assoc()['s'] ?? 0);
$rev_month = (float) ($conn->query("SELECT COALESCE(SUM(total_amount),0) AS s FROM orders WHERE status <> 'cancelled' AND created_at >= date_trunc('month', CURRENT_DATE)")->fetch_assoc()['s'] ?? 0);
$ord_all   = (int)   ($conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'] ?? 0);
$ord_month = (int)   ($conn->query("SELECT COUNT(*) AS c FROM orders WHERE created_at >= date_trunc('month', CURRENT_DATE)")->fetch_assoc()['c'] ?? 0);
$paid_orders = (int) ($conn->query("SELECT COUNT(*) AS c FROM orders WHERE status <> 'cancelled'")->fetch_assoc()['c'] ?? 0);
$aov = $paid_orders > 0 ? $rev_all / $paid_orders : 0;

/* ---------- 12-month revenue / orders series ---------- */
$series_res = $conn->query("
    SELECT TO_CHAR(date_trunc('month', created_at), 'YYYY-MM') AS ym,
           COUNT(*) AS orders,
           COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total_amount ELSE 0 END), 0) AS revenue
    FROM orders
    WHERE created_at >= (date_trunc('month', CURRENT_DATE) - INTERVAL '11 months')
    GROUP BY 1
");
$by_month = [];
while ($series_res && $r = $series_res->fetch_assoc()) {
    $by_month[$r['ym']] = ['orders' => (int) $r['orders'], 'revenue' => (float) $r['revenue']];
}
$months = [];
$base_month = strtotime(date('Y-m-01'));
for ($i = 11; $i >= 0; $i--) {
    $ts = strtotime("-$i months", $base_month);
    $ym = date('Y-m', $ts);
    $months[] = [
        'label'   => date('M', $ts),
        'full'    => date('F Y', $ts),
        'orders'  => $by_month[$ym]['orders']  ?? 0,
        'revenue' => $by_month[$ym]['revenue'] ?? 0.0,
    ];
}
$max_rev = 0.0;
foreach ($months as $m) $max_rev = max($max_rev, $m['revenue']);

/* ---------- status + payment breakdown ---------- */
$status_rows = [];
$sr = $conn->query("SELECT status, COUNT(*) AS c FROM orders GROUP BY status");
while ($sr && $r = $sr->fetch_assoc()) $status_rows[$r['status']] = (int) $r['c'];
$status_order = ['pending' => '#d32f2f', 'shipped' => '#e65100', 'delivered' => '#2e7d32', 'cancelled' => '#999'];

$pay_rows = [];
$pr = $conn->query("SELECT payment_method, COUNT(*) AS c FROM orders GROUP BY payment_method");
while ($pr && $r = $pr->fetch_assoc()) $pay_rows[$r['payment_method'] ?: 'other'] = (int) $r['c'];

/* ---------- top products ---------- */
$top_products = $conn->query("
    SELECT p.name, p.image,
           SUM(oi.quantity) AS qty,
           SUM(oi.quantity * oi.price) AS revenue
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status <> 'cancelled'
    GROUP BY p.id, p.name, p.image
    ORDER BY revenue DESC
    LIMIT 8
");

/* ---------- top customers ---------- */
$top_customers = $conn->query("
    SELECT u.name, u.email,
           COUNT(DISTINCT o.id) AS orders,
           COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN o.total_amount ELSE 0 END), 0) AS spent
    FROM orders o
    JOIN users u ON u.id = o.user_id
    GROUP BY u.id, u.name, u.email
    ORDER BY spent DESC
    LIMIT 8
");

/* ---------- low stock ---------- */
$low_stock = $conn->query("
    SELECT p.name, p.image, p.quantity, COALESCE(c.name, 'Uncategorised') AS cat
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.quantity <= 5
    ORDER BY p.quantity ASC, p.name ASC
    LIMIT 20
");
$oos_count = (int) ($conn->query("SELECT COUNT(*) AS c FROM products WHERE quantity = 0")->fetch_assoc()['c'] ?? 0);
?>
<style>
    .main-wrapper { max-width: 1200px; margin: 0 auto; padding: 40px 20px; width: 100%; flex: 1; }
    .an-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; flex-wrap: wrap; gap: 14px; }
    .an-top h2 { font-size: 26px; font-weight: 700; color: #222; }
    .an-top p { color: #888; font-size: 14px; margin-top: 3px; }

    .an-kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 26px; }
    .an-kpi { background: #fff; border-radius: 20px; padding: 20px 22px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); border-left: 5px solid #ff8ba7; }
    .an-kpi:nth-child(2) { border-left-color: #ffc107; }
    .an-kpi:nth-child(3) { border-left-color: #17a2b8; }
    .an-kpi:nth-child(4) { border-left-color: #66bb6a; }
    .an-kpi b { font-size: 22px; font-weight: 700; color: #222; display: block; }
    .an-kpi span { font-size: 12.5px; color: #999; }
    .an-kpi small { font-size: 12px; color: #ff8ba7; font-weight: 600; }

    .an-card { background: #fff; border-radius: 24px; padding: 26px 28px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); margin-bottom: 22px; }
    .an-card h3 { font-size: 17px; font-weight: 700; color: #222; margin-bottom: 4px; }
    .an-card .sub { font-size: 12.5px; color: #999; margin-bottom: 20px; }

    /* bar chart */
    .an-chart { display: flex; align-items: flex-end; gap: 10px; height: 210px; padding-top: 10px; }
    .an-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; justify-content: flex-end; }
    .an-bar { width: 60%; max-width: 34px; border-radius: 8px 8px 0 0; background: linear-gradient(180deg, #FEA5B6 0%, #ff8ba7 100%); position: relative; min-height: 2px; transition: 0.2s; }
    .an-bar:hover { filter: brightness(1.05); }
    .an-bar .tip { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #222; color: #fff; font-size: 11px; padding: 4px 8px; border-radius: 6px; white-space: nowrap; opacity: 0; pointer-events: none; transition: 0.15s; margin-bottom: 4px; }
    .an-bar-col:hover .tip { opacity: 1; }
    .an-bar-lbl { font-size: 11px; color: #999; }
    .an-bar-val { font-size: 10.5px; color: #bbb; }

    .an-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }

    .an-breakdown-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .an-breakdown-row .name { width: 100px; font-size: 13px; color: #555; text-transform: capitalize; }
    .an-track { flex: 1; height: 12px; background: #f3f3f3; border-radius: 50px; overflow: hidden; }
    .an-fill { height: 100%; border-radius: 50px; }
    .an-breakdown-row .c { width: 44px; text-align: right; font-size: 13px; font-weight: 700; color: #222; }

    .an-list-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }
    .an-list-item:last-child { border-bottom: none; }
    .an-list-item img { width: 42px; height: 42px; border-radius: 10px; object-fit: contain; background: #fafafa; padding: 3px; }
    .an-list-item .info { flex: 1; min-width: 0; }
    .an-list-item .info h4 { font-size: 13.5px; font-weight: 600; color: #222; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .an-list-item .info p { font-size: 12px; color: #999; }
    .an-list-item .val { font-weight: 700; font-size: 13px; color: #222; white-space: nowrap; }

    .an-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; flex-shrink: 0; }

    .an-stock-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }
    .an-stock-item:last-child { border-bottom: none; }
    .an-stock-item img { width: 40px; height: 40px; border-radius: 10px; object-fit: contain; background: #fafafa; padding: 3px; }
    .an-stock-item .info { flex: 1; min-width: 0; }
    .an-stock-item .info h4 { font-size: 13.5px; font-weight: 600; color: #222; }
    .an-stock-item .info p { font-size: 12px; color: #999; }
    .an-pill { font-size: 11px; font-weight: 700; padding: 3px 12px; border-radius: 50px; }
    .an-pill.oos { background: #ffe4e4; color: #d32f2f; }
    .an-pill.low { background: #fff3e0; color: #e65100; }

    .an-empty { text-align: center; color: #aaa; padding: 30px 10px; font-size: 13.5px; }

    @media (max-width: 900px) { .an-kpis { grid-template-columns: repeat(2, 1fr); } .an-grid-2 { grid-template-columns: 1fr; } }
</style>

<div class="main-wrapper">
    <div class="an-top">
        <div>
            <h2>📈 Analytics</h2>
            <p>Sales, customers and inventory at a glance</p>
        </div>
        <a href="admin_dashboard.php" style="background:#f3f3f3; padding:9px 18px; border-radius:50px; font-size:14px; font-weight:500; color:#555; text-decoration:none;">&larr; Dashboard</a>
    </div>

    <div class="an-kpis">
        <div class="an-kpi"><b><?php echo $money($rev_all); ?></b><span>Revenue (all-time)</span></div>
        <div class="an-kpi"><b><?php echo $money($rev_month); ?></b><span>Revenue this month</span><br><small><?php echo $ord_month; ?> orders</small></div>
        <div class="an-kpi"><b><?php echo $ord_all; ?></b><span>Total orders</span></div>
        <div class="an-kpi"><b><?php echo $money($aov); ?></b><span>Avg. order value</span></div>
    </div>

    <div class="an-card">
        <h3>Revenue over the last 12 months</h3>
        <div class="sub">Bars show non-cancelled revenue per month. Hover for order counts.</div>
        <?php if ($max_rev <= 0): ?>
            <div class="an-empty">No sales in the last 12 months yet.</div>
        <?php else: ?>
        <div class="an-chart">
            <?php foreach ($months as $m):
                $h = $max_rev > 0 ? max(2, round($m['revenue'] / $max_rev * 100)) : 2;
            ?>
            <div class="an-bar-col">
                <div class="an-bar" style="height: <?php echo $h; ?>%;">
                    <span class="tip"><?php echo htmlspecialchars($m['full']); ?>: <?php echo $money($m['revenue']); ?> · <?php echo $m['orders']; ?> orders</span>
                </div>
                <div class="an-bar-lbl"><?php echo $m['label']; ?></div>
                <div class="an-bar-val"><?php echo $m['revenue'] > 0 ? number_format($m['revenue'] / 1000, 1) . 'k' : '—'; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="an-grid-2">
        <div class="an-card">
            <h3>Orders by status</h3>
            <div class="sub"><?php echo $ord_all; ?> orders total</div>
            <?php
            $status_total = array_sum($status_rows);
            foreach ($status_order as $st => $color):
                $c = $status_rows[$st] ?? 0;
                $pct = $status_total > 0 ? round($c / $status_total * 100) : 0;
            ?>
            <div class="an-breakdown-row">
                <span class="name"><?php echo $st; ?></span>
                <span class="an-track"><span class="an-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $color; ?>;"></span></span>
                <span class="c"><?php echo $c; ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="an-card">
            <h3>Payment method</h3>
            <div class="sub">How customers pay</div>
            <?php
            $pay_total = array_sum($pay_rows);
            $pay_labels = ['cod' => 'Cash on delivery', 'card' => 'Credit / debit card'];
            $pay_colors = ['cod' => '#17a2b8', 'card' => '#5e35b1', 'other' => '#999'];
            if ($pay_total === 0): ?>
                <div class="an-empty">No orders yet.</div>
            <?php else:
                foreach ($pay_rows as $pm => $c):
                    $pct = $pay_total > 0 ? round($c / $pay_total * 100) : 0;
            ?>
            <div class="an-breakdown-row">
                <span class="name"><?php echo htmlspecialchars($pay_labels[$pm] ?? $pm); ?></span>
                <span class="an-track"><span class="an-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $pay_colors[$pm] ?? '#999'; ?>;"></span></span>
                <span class="c"><?php echo $c; ?></span>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="an-grid-2">
        <div class="an-card">
            <h3>Top products</h3>
            <div class="sub">By revenue (non-cancelled orders)</div>
            <?php if ($top_products && $top_products->num_rows > 0): ?>
                <?php while ($p = $top_products->fetch_assoc()): ?>
                <div class="an-list-item">
                    <img src="uploads/<?php echo htmlspecialchars($p['image']); ?>" alt="">
                    <div class="info">
                        <h4><?php echo htmlspecialchars($p['name']); ?></h4>
                        <p><?php echo (int) $p['qty']; ?> sold</p>
                    </div>
                    <div class="val"><?php echo $money($p['revenue']); ?></div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="an-empty">No sales yet.</div>
            <?php endif; ?>
        </div>

        <div class="an-card">
            <h3>Top customers</h3>
            <div class="sub">By total spent</div>
            <?php if ($top_customers && $top_customers->num_rows > 0): ?>
                <?php while ($c = $top_customers->fetch_assoc()):
                    $ini = strtoupper(mb_substr(trim($c['name']) !== '' ? $c['name'] : $c['email'], 0, 1));
                ?>
                <div class="an-list-item">
                    <div class="an-avatar"><?php echo htmlspecialchars($ini); ?></div>
                    <div class="info">
                        <h4><?php echo htmlspecialchars($c['name'] !== '' ? $c['name'] : $c['email']); ?></h4>
                        <p><?php echo (int) $c['orders']; ?> order<?php echo (int) $c['orders'] === 1 ? '' : 's'; ?></p>
                    </div>
                    <div class="val"><?php echo $money($c['spent']); ?></div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="an-empty">No customers with orders yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="an-card">
        <h3>Inventory alerts</h3>
        <div class="sub"><?php echo $oos_count; ?> out of stock · items at or below 5 units</div>
        <?php if ($low_stock && $low_stock->num_rows > 0): ?>
            <?php while ($p = $low_stock->fetch_assoc()):
                $q = (int) $p['quantity'];
            ?>
            <div class="an-stock-item">
                <img src="uploads/<?php echo htmlspecialchars($p['image']); ?>" alt="">
                <div class="info">
                    <h4><?php echo htmlspecialchars($p['name']); ?></h4>
                    <p><?php echo htmlspecialchars($p['cat']); ?></p>
                </div>
                <?php if ($q === 0): ?>
                    <span class="an-pill oos">Out of stock</span>
                <?php else: ?>
                    <span class="an-pill low"><?php echo $q; ?> left</span>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
            <div style="margin-top:16px;text-align:right;">
                <a href="admin_products.php" style="color:#ff8ba7;font-weight:600;font-size:13px;text-decoration:none;">Manage inventory &rarr;</a>
            </div>
        <?php else: ?>
            <div class="an-empty">Every product has more than 5 units in stock. 🎉</div>
        <?php endif; ?>
    </div>
</div>

<?php include 'admin_footer.php'; ?>
