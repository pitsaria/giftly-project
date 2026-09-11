<?php
include 'db_connect.php';
include_once 'orders_lib.php';
include_once 'paymongo_lib.php';
include_once 'mail_lib.php';
orders_ensure_schema($conn);
pay_ensure_schema($conn);

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$user_check = $conn->query("SELECT role FROM users WHERE id = $user_id");
$user_data = $user_check->fetch_assoc();
if ($user_data['role'] !== 'admin') {
    header("Location: shop.php");
    exit();
}

// --- HANDLE STATUS UPDATE (same rules as admin_orders.php) ---
$flash = null;
if (isset($_POST['update_status_here']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $allowed_statuses = ['pending', 'shipped', 'delivered'];

    $cur = $conn->query("SELECT status, payment_method, payment_status FROM orders WHERE id = $order_id");
    $cur_row = $cur ? $cur->fetch_assoc() : [];
    $cur_status = $cur_row['status'] ?? '';
    $cur_pm     = $cur_row['payment_method'] ?? 'cod';
    $cur_ps     = $cur_row['payment_status'] ?? 'unpaid';

    if (in_array($cur_status, ['delivered', 'cancelled'], true)) {
        $flash = ['error', 'This order is marked "' . $cur_status . '" and its status can no longer be changed.'];
    } elseif ($cur_pm !== 'cod' && $cur_ps !== 'paid') {
        $flash = ['error', 'This order is still awaiting payment — its status can\'t be changed until it\'s paid.'];
    } elseif (!in_array($new_status, $allowed_statuses, true)) {
        $flash = ['error', 'Invalid status.'];
    } else {
        if ($conn->query("UPDATE orders SET status = '$new_status' WHERE id = $order_id") === TRUE) {
            $flash = ['ok', 'Order #' . $order_id . ' updated to "' . $new_status . '".'];
            if ($new_status !== $cur_status && function_exists('send_status_email')) {
                send_status_email($conn, $order_id, $new_status);
            }
        }
    }
}

include 'admin_header.php';

// --- FILTERS ---
$jump_date = (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) ? $_GET['date'] : '';
$show_all  = !empty($_GET['show_all']);

$where = "WHERE 1=1";
if (!$show_all) {
    $where .= " AND orders.status IN ('pending', 'shipped')";
}
if ($jump_date !== '') {
    $where .= " AND orders.delivery_date = '" . $conn->real_escape_string($jump_date) . "'";
}

$sql = "SELECT orders.*, users.name AS customer_name
        FROM orders JOIN users ON orders.user_id = users.id
        $where
        ORDER BY orders.delivery_date ASC, orders.delivery_time ASC, orders.created_at ASC
        LIMIT 300";
$result = $conn->query($sql);

$groups = []; // delivery_date => [rows]
while ($result && $row = $result->fetch_assoc()) {
    $d = $row['delivery_date'] ?: 'unscheduled';
    $groups[$d][] = $row;
}

$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// quick stats (independent of the date-jump filter, but respect show_all)
$stat_where = $show_all ? "WHERE 1=1" : "WHERE status IN ('pending', 'shipped')";
$stat_today    = (int) ($conn->query("SELECT COUNT(*) c FROM orders $stat_where AND delivery_date = '$today'")->fetch_assoc()['c'] ?? 0);
$stat_overdue  = (int) ($conn->query("SELECT COUNT(*) c FROM orders WHERE status IN ('pending','shipped') AND delivery_date < '$today'")->fetch_assoc()['c'] ?? 0);
$stat_total    = (int) ($conn->query("SELECT COUNT(*) c FROM orders $stat_where")->fetch_assoc()['c'] ?? 0);
?>

<style>
    .main-wrapper { max-width: 1100px; margin: 0 auto; padding: 40px 20px; width: 100%; flex: 1; }

    .ds-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 26px; }
    .ds-stat { background: #fff; border-radius: 20px; padding: 20px 22px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); border-left: 5px solid #ff8ba7; }
    .ds-stat.warn { border-left-color: #d32f2f; }
    .ds-stat .n { font-size: 28px; font-weight: 700; color: #222; }
    .ds-stat .l { font-size: 13px; color: #888; margin-top: 2px; }

    .ds-filters { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; background: #fff; padding: 16px 20px; border-radius: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); }
    .ds-filters input[type="date"] { padding: 9px 14px; border: 1.5px solid #eee; border-radius: 30px; font-family: 'Poppins'; font-size: 13.5px; outline: none; }
    .ds-filters input[type="date"]:focus { border-color: #ffc1cc; }
    .ds-go { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border: none; padding: 9px 22px; border-radius: 30px; font-weight: 600; font-size: 13.5px; cursor: pointer; font-family: 'Poppins'; }
    .ds-link { color: #888; font-size: 13.5px; text-decoration: underline; }
    .ds-toggle { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: #555; margin-left: auto; cursor: pointer; }

    .ds-group { background: #fff; border-radius: 22px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); margin-bottom: 20px; overflow: hidden; }
    .ds-group-head { display: flex; align-items: center; gap: 12px; padding: 18px 24px; border-bottom: 1px solid #f5f5f5; }
    .ds-group-date { font-size: 16px; font-weight: 700; color: #222; }
    .ds-badge { font-size: 11px; font-weight: 700; padding: 3px 12px; border-radius: 50px; text-transform: uppercase; letter-spacing: 0.4px; }
    .ds-badge.today { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; }
    .ds-badge.tomorrow { background: #fff3e0; color: #e65100; }
    .ds-badge.overdue { background: #fdeded; color: #d32f2f; }
    .ds-group-count { margin-left: auto; font-size: 13px; color: #999; }

    .ds-row { display: flex; align-items: center; gap: 16px; padding: 16px 24px; border-bottom: 1px solid #f8f8f8; flex-wrap: wrap; }
    .ds-row:last-child { border-bottom: none; }
    .ds-time { flex: 0 0 64px; font-size: 13px; font-weight: 700; color: #ff8ba7; }
    .ds-order-id { flex: 0 0 60px; font-size: 13.5px; color: #444; }
    .ds-customer { flex: 0 0 140px; font-size: 13.5px; font-weight: 600; color: #222; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ds-address { flex: 1; min-width: 180px; font-size: 12.5px; color: #777; line-height: 1.4; }
    .ds-mode { flex: 0 0 auto; font-size: 11px; padding: 4px 11px; border-radius: 20px; white-space: nowrap; }
    .ds-mode.me { background: #e3f2fd; color: #1976d2; }
    .ds-mode.recipient { background: #fff3e0; color: #e65100; }
    .ds-pay { flex: 0 0 auto; font-size: 10px; font-weight: 700; padding: 2px 9px; border-radius: 20px; white-space: nowrap; }
    .ds-total { flex: 0 0 90px; font-weight: 700; font-size: 13.5px; color: #222; text-align: right; }
    .ds-status-select { border: none; padding: 6px 12px; border-radius: 30px; font-size: 12px; font-weight: 600; cursor: pointer; outline: none; font-family: 'Poppins'; background: #f3f3f3; color: #333; }
    .ds-status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .ds-view-btn { background: #f3f3f3; border: none; padding: 6px 14px; border-radius: 30px; font-size: 12px; font-weight: 500; cursor: pointer; transition: 0.2s; }
    .ds-view-btn:hover { background: #ffc1cc; color: #fff; }

    .ds-empty { text-align: center; padding: 60px 20px; color: #999; }
    .ds-empty i { font-size: 40px; color: #ffc1cc; margin-bottom: 14px; display: block; }

    .alert-success { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; padding: 15px 20px; border-radius: 16px; margin-bottom: 25px; text-align: center; font-weight: 500; }
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px); display: none; justify-content: center; align-items: center; z-index: 9999; }
    .modal-box { background: #fff; border-radius: 30px; padding: 40px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 50px rgba(0,0,0,0.2); position: relative; }
    .modal-close { position: absolute; top: 15px; right: 20px; font-size: 24px; color: #888; cursor: pointer; transition: 0.2s; }
    .modal-close:hover { color: #ff8ba7; transform: rotate(90deg); }

    @media (max-width: 900px) {
        .ds-stats { grid-template-columns: 1fr; }
        .ds-row { flex-direction: column; align-items: flex-start; }
        .ds-total { text-align: left; }
    }
</style>

<div class="main-wrapper">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px;">
        <div>
            <h2 style="font-size:26px; font-weight:600; color:#222; margin-bottom:5px;">Delivery Schedule</h2>
            <p style="color:#888; font-size:14px;">Orders grouped by delivery date, so you can plan the day's dispatches.</p>
        </div>
        <a href="admin_orders.php" style="background:#f3f3f3; padding:8px 20px; border-radius:50px; font-size:14px; font-weight:500; color:#555; text-decoration:none;">&larr; All Orders</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert-success" style="<?php echo $flash[0] === 'error' ? 'background:#fdeded;border-color:#ffc1cc;color:#d32f2f;' : ''; ?>">
            <i class="fas fa-<?php echo $flash[0] === 'error' ? 'circle-exclamation' : 'check-circle'; ?>" style="margin-right:8px;"></i>
            <?php echo htmlspecialchars($flash[1]); ?>
        </div>
    <?php endif; ?>

    <div class="ds-stats">
        <div class="ds-stat">
            <div class="n"><?php echo $stat_today; ?></div>
            <div class="l">Due today</div>
        </div>
        <div class="ds-stat <?php echo $stat_overdue > 0 ? 'warn' : ''; ?>">
            <div class="n"><?php echo $stat_overdue; ?></div>
            <div class="l">Overdue (still pending/shipped)</div>
        </div>
        <div class="ds-stat">
            <div class="n"><?php echo $stat_total; ?></div>
            <div class="l"><?php echo $show_all ? 'Total orders' : 'Awaiting dispatch'; ?></div>
        </div>
    </div>

    <form class="ds-filters" method="GET" action="admin_delivery_schedule.php">
        <label style="font-size:13.5px; color:#666; font-weight:600;">Jump to date</label>
        <input type="date" name="date" value="<?php echo htmlspecialchars($jump_date); ?>">
        <button type="submit" class="ds-go">Go</button>
        <?php if ($jump_date !== ''): ?>
            <a href="admin_delivery_schedule.php<?php echo $show_all ? '?show_all=1' : ''; ?>" class="ds-link">View all upcoming</a>
        <?php endif; ?>
        <a href="admin_delivery_schedule.php?date=<?php echo $today; ?><?php echo $show_all ? '&show_all=1' : ''; ?>" class="ds-link">Today</a>

        <label class="ds-toggle">
            <input type="checkbox" name="show_all" value="1" onchange="this.form.submit()" <?php echo $show_all ? 'checked' : ''; ?>>
            Include delivered / cancelled
        </label>
    </form>

    <?php if (empty($groups)): ?>
        <div class="ds-group">
            <div class="ds-empty">
                <i class="fas fa-truck"></i>
                Nothing to dispatch<?php echo $jump_date !== '' ? ' on ' . date('F j, Y', strtotime($jump_date)) : ''; ?>. 🎉
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $date_key => $rows):
            $badge = '';
            if ($date_key !== 'unscheduled') {
                if ($date_key < $today) $badge = '<span class="ds-badge overdue">Overdue</span>';
                elseif ($date_key === $today) $badge = '<span class="ds-badge today">Today</span>';
                elseif ($date_key === $tomorrow) $badge = '<span class="ds-badge tomorrow">Tomorrow</span>';
            }
            $date_label = ($date_key === 'unscheduled') ? 'No delivery date set' : date('l, F j, Y', strtotime($date_key));
        ?>
        <div class="ds-group">
            <div class="ds-group-head">
                <span class="ds-group-date"><?php echo htmlspecialchars($date_label); ?></span>
                <?php echo $badge; ?>
                <span class="ds-group-count"><?php echo count($rows); ?> order<?php echo count($rows) === 1 ? '' : 's'; ?></span>
            </div>
            <?php foreach ($rows as $row):
                $pm = $row['payment_method'] ?? 'cod';
                $ps = $row['payment_status'] ?? 'unpaid';
                $locked_awaiting_payment = ($pm !== 'cod' && $ps !== 'paid');

                if ($pm === 'cod') {
                    $pay_pill = '<span class="ds-pay" style="background:#f0f0f0;color:#777;">COD</span>';
                } elseif ($ps === 'paid') {
                    $pay_pill = '<span class="ds-pay" style="background:#e8f5e9;color:#2e7d32;">PAID</span>';
                } elseif ($ps === 'failed') {
                    $pay_pill = '<span class="ds-pay" style="background:#fdeded;color:#d32f2f;">FAILED</span>';
                } elseif ($ps === 'refunded') {
                    $pay_pill = '<span class="ds-pay" style="background:#ede7f6;color:#5e35b1;">REFUNDED</span>';
                } else {
                    $pay_pill = '<span class="ds-pay" style="background:#fff8e1;color:#a5710d;">UNPAID</span>';
                }

                $mode_html = !empty($row['recipient_name'])
                    ? '<span class="ds-mode recipient">🎁 ' . htmlspecialchars($row['recipient_name']) . '</span>'
                    : '<span class="ds-mode me">🏠 To Me</span>';
            ?>
            <div class="ds-row">
                <div class="ds-time"><?php echo $row['delivery_time'] ? date('g:i A', strtotime($row['delivery_time'])) : '—'; ?></div>
                <div class="ds-order-id">#<?php echo (int) $row['id']; ?></div>
                <div class="ds-customer" title="<?php echo htmlspecialchars($row['customer_name']); ?>"><?php echo htmlspecialchars($row['customer_name']); ?></div>
                <div class="ds-address"><?php echo htmlspecialchars($row['address'] . ', ' . $row['city']); ?></div>
                <?php echo $mode_html; ?>
                <?php echo $pay_pill; ?>
                <div class="ds-total">PHP <?php echo number_format($row['total_amount'], 2); ?></div>

                <?php if (in_array($row['status'], ['delivered', 'cancelled'], true)): ?>
                    <span class="ds-status-badge" style="background:<?php echo $row['status'] === 'delivered' ? '#e8f5e9;color:#2e7d32' : '#f5f5f5;color:#999'; ?>;"><?php echo ucfirst($row['status']); ?></span>
                <?php elseif ($locked_awaiting_payment): ?>
                    <span class="ds-status-badge" style="background:#fff8e1;color:#a5710d;"><i class="fas fa-lock" style="margin-right:4px;"></i>Unpaid</span>
                <?php else: ?>
                    <form action="admin_delivery_schedule.php<?php echo $jump_date !== '' ? '?date=' . $jump_date : ''; ?><?php echo $show_all ? ($jump_date !== '' ? '&' : '?') . 'show_all=1' : ''; ?>" method="POST" style="margin:0;">
                        <input type="hidden" name="order_id" value="<?php echo (int) $row['id']; ?>">
                        <input type="hidden" name="update_status_here" value="1">
                        <select name="status" class="ds-status-select" onchange="this.form.submit()">
                            <option value="pending" <?php echo $row['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="shipped" <?php echo $row['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $row['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        </select>
                    </form>
                <?php endif; ?>

                <button class="ds-view-btn" onclick="openModal(<?php echo (int) $row['id']; ?>)"><i class="fas fa-eye"></i></button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- VIEW ITEMS MODAL -->
<div class="modal-overlay" id="orderModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h3 style="margin-bottom: 15px; color: #222;">Order Items</h3>
        <div id="modalItemsContainer"></div>
    </div>
</div>

<script>
    function openModal(orderId) {
        fetch('get_order_items.php?order_id=' + orderId)
            .then(response => response.text())
            .then(data => {
                document.getElementById('modalItemsContainer').innerHTML = data;
                document.getElementById('orderModal').style.display = 'flex';
            });
    }
    function closeModal() { document.getElementById('orderModal').style.display = 'none'; }
    document.getElementById('orderModal').addEventListener('click', function (e) { if (e.target === this) closeModal(); });
</script>

<?php include 'admin_footer.php'; ?>
