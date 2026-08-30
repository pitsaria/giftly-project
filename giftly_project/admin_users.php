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

// --- filters ---
$search = trim($_GET['search'] ?? '');
$role_f = $_GET['role'] ?? 'all';
if (!in_array($role_f, ['all', 'customer', 'admin'], true)) $role_f = 'all';

$where = "WHERE 1=1";
if ($role_f !== 'all') {
    $where .= " AND u.role = '" . $conn->real_escape_string($role_f) . "'";
}
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (u.name ILIKE '%$s%' OR u.email ILIKE '%$s%' OR u.phone ILIKE '%$s%')";
}

// --- stats (all users, unaffected by filters) ---
$total_users     = (int) ($conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0);
$total_customers = (int) ($conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'customer'")->fetch_assoc()['c'] ?? 0);
$total_admins    = (int) ($conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch_assoc()['c'] ?? 0);
$new_30d         = (int) ($conn->query("SELECT COUNT(*) AS c FROM users WHERE created_at >= (CURRENT_TIMESTAMP - INTERVAL '30 days')")->fetch_assoc()['c'] ?? 0);

// --- pagination ---
$limit = 12;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$total = (int) ($conn->query("SELECT COUNT(*) AS c FROM users u $where")->fetch_assoc()['c'] ?? 0);
$total_pages = max(1, (int) ceil($total / $limit));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

$rows = $conn->query("
    SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at,
           COALESCE(o.order_count, 0)  AS order_count,
           COALESCE(o.total_spent, 0)  AS total_spent,
           o.last_order
    FROM users u
    LEFT JOIN (
        SELECT user_id,
               COUNT(*) AS order_count,
               SUM(CASE WHEN status <> 'cancelled' THEN total_amount ELSE 0 END) AS total_spent,
               MAX(created_at) AS last_order
        FROM orders
        GROUP BY user_id
    ) o ON o.user_id = u.id
    $where
    ORDER BY u.created_at DESC, u.id DESC
    LIMIT $limit OFFSET $offset
");

$qs = '&search=' . urlencode($search) . '&role=' . urlencode($role_f);
?>
<style>
    .main-wrapper { max-width: 1200px; margin: 0 auto; padding: 40px 20px; width: 100%; flex: 1; }
    .u-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 14px; }
    .u-top h2 { font-size: 26px; font-weight: 700; color: #222; }
    .u-top p { color: #888; font-size: 14px; margin-top: 3px; }

    .u-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin: 20px 0 24px; }
    .u-stat { background: #fff; border-radius: 18px; padding: 18px 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); border-left: 5px solid #ff8ba7; }
    .u-stat:nth-child(2) { border-left-color: #ffc107; }
    .u-stat:nth-child(3) { border-left-color: #17a2b8; }
    .u-stat:nth-child(4) { border-left-color: #66bb6a; }
    .u-stat b { font-size: 26px; font-weight: 700; color: #222; display: block; }
    .u-stat span { font-size: 13px; color: #999; }

    .u-filter-bar { display: flex; gap: 12px; margin-bottom: 22px; flex-wrap: wrap; align-items: center; }
    .u-filter-bar select, .u-filter-bar input { padding: 9px 16px; border: 1.5px solid #eee; border-radius: 30px; font-size: 13px; font-family: 'Poppins'; background: #fff; color: #555; outline: none; }
    .u-filter-bar input { min-width: 240px; }
    .u-filter-bar input:focus, .u-filter-bar select:focus { border-color: #ffc1cc; }
    .u-btn { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; padding: 9px 22px; border: none; border-radius: 30px; font-weight: 600; cursor: pointer; font-family: 'Poppins'; }
    .u-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254,165,182,0.4); }

    .u-table-card { background: #fff; border-radius: 24px; padding: 26px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); overflow-x: auto; }
    .u-table { width: 100%; border-collapse: collapse; }
    .u-table th { text-align: left; padding: 14px 10px; border-bottom: 2px solid #f0f0f0; color: #444; font-weight: 600; font-size: 13px; }
    .u-table td { padding: 16px 10px; border-bottom: 1px solid #f5f5f5; font-size: 13.5px; color: #333; vertical-align: middle; }
    .u-table tr:last-child td { border-bottom: none; }
    .u-avatar { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; margin-right: 10px; vertical-align: middle; }
    .u-role { padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
    .u-role.admin { background: #ede7f6; color: #5e35b1; }
    .u-role.customer { background: #e8f5e9; color: #2e7d32; }
    .u-muted { color: #aaa; }
    .u-link { color: #ff8ba7; font-weight: 600; text-decoration: none; font-size: 12.5px; }

    .pagination-wrapper { display: flex; justify-content: center; gap: 8px; margin-top: 24px; flex-wrap: wrap; }
    .page-btn { padding: 8px 16px; border: 1.5px solid #eee; border-radius: 30px; background: #fff; color: #555; text-decoration: none; font-size: 14px; font-family: 'Poppins'; }
    .page-btn:hover { background: #ffc1cc; color: #fff; border-color: #ffc1cc; }
    .page-btn.active { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border-color: #FEA5B6; }
    .page-btn.disabled { opacity: 0.5; pointer-events: none; }

    @media (max-width: 800px) { .u-stats { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="main-wrapper">
    <div class="u-top">
        <div>
            <h2>👥 Users</h2>
            <p><?php echo $total; ?> matching <?php echo $total === 1 ? 'user' : 'users'; ?></p>
        </div>
        <a href="admin_dashboard.php" style="background:#f3f3f3; padding:9px 18px; border-radius:50px; font-size:14px; font-weight:500; color:#555; text-decoration:none;">&larr; Dashboard</a>
    </div>

    <div class="u-stats">
        <div class="u-stat"><b><?php echo $total_users; ?></b><span>Total users</span></div>
        <div class="u-stat"><b><?php echo $total_customers; ?></b><span>Customers</span></div>
        <div class="u-stat"><b><?php echo $total_admins; ?></b><span>Admins</span></div>
        <div class="u-stat"><b><?php echo $new_30d; ?></b><span>New (30 days)</span></div>
    </div>

    <form method="GET" action="admin_users.php" class="u-filter-bar">
        <input type="text" name="search" placeholder="Search name, email or phone…" value="<?php echo htmlspecialchars($search); ?>">
        <select name="role">
            <option value="all"      <?php echo $role_f === 'all' ? 'selected' : ''; ?>>All roles</option>
            <option value="customer" <?php echo $role_f === 'customer' ? 'selected' : ''; ?>>Customers</option>
            <option value="admin"    <?php echo $role_f === 'admin' ? 'selected' : ''; ?>>Admins</option>
        </select>
        <button type="submit" class="u-btn">Search</button>
        <?php if ($search !== '' || $role_f !== 'all'): ?>
            <a href="admin_users.php" style="color:#ff8ba7; font-size:13px; text-decoration:underline;">Clear</a>
        <?php endif; ?>
    </form>

    <div class="u-table-card">
        <table class="u-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Contact</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th style="text-align:center;">Orders</th>
                    <th style="text-align:right;">Total spent</th>
                    <th>Last order</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows && $rows->num_rows > 0): ?>
                <?php while ($u = $rows->fetch_assoc()):
                    $initial = strtoupper(mb_substr(trim($u['name']) !== '' ? $u['name'] : $u['email'], 0, 1));
                ?>
                <tr>
                    <td>
                        <span class="u-avatar"><?php echo htmlspecialchars($initial); ?></span>
                        <strong><?php echo htmlspecialchars($u['name'] !== '' ? $u['name'] : '(no name)'); ?></strong>
                    </td>
                    <td>
                        <div><a href="mailto:<?php echo htmlspecialchars($u['email']); ?>" style="color:#333;text-decoration:none;"><?php echo htmlspecialchars($u['email']); ?></a></div>
                        <div class="u-muted" style="font-size:12px;"><?php echo htmlspecialchars($u['phone'] !== '' ? $u['phone'] : '—'); ?></div>
                    </td>
                    <td><span class="u-role <?php echo $u['role'] === 'admin' ? 'admin' : 'customer'; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                    <td><?php echo $u['created_at'] ? date('M j, Y', strtotime($u['created_at'])) : '—'; ?></td>
                    <td style="text-align:center;"><?php echo (int) $u['order_count']; ?></td>
                    <td style="text-align:right;">PHP <?php echo number_format((float) $u['total_spent'], 2); ?></td>
                    <td><?php echo $u['last_order'] ? date('M j, Y', strtotime($u['last_order'])) : '<span class="u-muted">—</span>'; ?></td>
                    <td>
                        <?php if ((int) $u['order_count'] > 0): ?>
                            <a class="u-link" href="admin_orders.php">View orders</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" style="padding:40px; text-align:center; color:#888;">No users match your search.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination-wrapper">
        <a href="admin_users.php?page=<?php echo max(1, $page - 1) . $qs; ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">&larr; Prev</a>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="admin_users.php?page=<?php echo $i . $qs; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <a href="admin_users.php?page=<?php echo min($total_pages, $page + 1) . $qs; ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next &rarr;</a>
    </div>
    <?php endif; ?>
</div>

<?php include 'admin_footer.php'; ?>
