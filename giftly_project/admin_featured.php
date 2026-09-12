<?php
include 'db_connect.php';
include_once 'catalog_lib.php';
catalog_ensure_schema($conn);

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

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

include 'admin_header.php';
?>

<style>
    .wide-container { max-width: 1000px; margin: 0 auto; padding: 40px 20px; width: 100%; flex: 1; }
    .admin-table-card { background: #fff; border-radius: 24px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); }
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th { text-align: left; padding: 15px 10px; border-bottom: 2px solid #f0f0f0; color: #444; font-weight: 600; font-size: 14px; }
    .admin-table td { padding: 14px 10px; border-bottom: 1px solid #f5f5f5; font-size: 14px; color: #333; vertical-align: middle; }
    .admin-table tr:last-child td { border-bottom: none; }
    .feat-thumb { width: 44px; height: 44px; object-fit: contain; background: #fafafa; border-radius: 10px; padding: 4px; }

    .ft-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
    .ft-switch input { opacity: 0; width: 0; height: 0; }
    .ft-slider { position: absolute; cursor: pointer; inset: 0; background-color: #ddd; transition: 0.2s; border-radius: 24px; }
    .ft-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: #fff; transition: 0.2s; border-radius: 50%; }
    .ft-switch input:checked + .ft-slider { background-color: #ff8ba7; }
    .ft-switch input:checked + .ft-slider:before { transform: translateX(20px); }

    .ft-order-btns { display: inline-flex; gap: 4px; margin-left: 10px; }
    .ft-order-btn { background: #f3f3f3; color: #555; border: none; width: 26px; height: 26px; border-radius: 8px; cursor: pointer; font-size: 12px; }
    .ft-order-btn:hover { background: #ffc1cc; color: #fff; }
    .ft-order-btn:disabled { opacity: 0.35; cursor: not-allowed; }

    .pink-input { width: 100%; padding: 12px; border: 1.5px solid #eee; border-radius: 12px; font-size: 14px; font-family: 'Poppins'; outline: none; }
    .pink-input:focus { border-color: #ffc1cc; }
    .action-btn-primary { background: #ff8ba7; color: white; padding: 10px 20px; border: none; border-radius: 50px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .action-btn-primary:hover { transform: scale(1.05); }
</style>

<div class="wide-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 style="font-size: 26px; font-weight: 600; color: #222; margin-bottom: 5px;">Featured Products</h2>
        <a href="admin_dashboard.php" style="background: #f3f3f3; padding: 8px 20px; border-radius: 50px; font-size: 14px; font-weight: 500; color: #555; text-decoration: none; transition: 0.3s;">&larr; Dashboard</a>
    </div>
    <p style="color:#888; font-size:14px; margin-bottom:25px;">Turn on up to a few products to control what shows in the homepage's "Featured Products" section (first 4, top to bottom). When nothing is turned on, the homepage falls back to showing on-sale / newest products automatically.</p>

    <div class="admin-table-card">
        <div style="display:flex; gap:10px; margin-bottom:20px;">
            <form method="GET" style="display:flex; gap:10px; flex:1;">
                <input type="text" name="search" class="pink-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="action-btn-primary"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Featured</th>
                    <th>Order</th>
                </tr>
            </thead>
            <tbody id="ftTableBody">
                <?php
                $where = "product_type = 'catalog'";
                if ($search !== '') {
                    $where .= " AND name ILIKE '%" . $conn->real_escape_string($search) . "%'";
                }
                $sql = "SELECT * FROM products WHERE $where ORDER BY is_featured DESC, featured_order ASC, name ASC";
                $result = $conn->query($sql);
                if ($result && $result->num_rows > 0):
                    $featured_count = 0;
                    $rows = [];
                    while ($row = $result->fetch_assoc()) { $rows[] = $row; if (!empty($row['is_featured']) && $row['is_featured'] !== 'f') $featured_count++; }
                    $idx = 0;
                    foreach ($rows as $row):
                        $isFeatured = catalog_is_active($row['is_featured'] ?? false);
                        if ($isFeatured) $idx++;
                ?>
                    <tr data-id="<?php echo $row['id']; ?>">
                        <td><img class="feat-thumb" src="<?php echo htmlspecialchars(img_url($row['image'])); ?>" alt=""></td>
                        <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                        <td>PHP <?php echo number_format((float) $row['price'], 2); ?></td>
                        <td>
                            <label class="ft-switch">
                                <input type="checkbox" <?php echo $isFeatured ? 'checked' : ''; ?> onchange="ftToggle(<?php echo $row['id']; ?>, this.checked)">
                                <span class="ft-slider"></span>
                            </label>
                        </td>
                        <td>
                            <?php if ($isFeatured): ?>
                                <span>#<?php echo $idx; ?></span>
                                <span class="ft-order-btns">
                                    <button class="ft-order-btn" onclick="ftMove(<?php echo $row['id']; ?>, 'up')" <?php echo $idx === 1 ? 'disabled' : ''; ?>><i class="fas fa-arrow-up"></i></button>
                                    <button class="ft-order-btn" onclick="ftMove(<?php echo $row['id']; ?>, 'down')" <?php echo $idx === $featured_count ? 'disabled' : ''; ?>><i class="fas fa-arrow-down"></i></button>
                                </span>
                            <?php else: ?>
                                <span style="color:#bbb;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5" style="padding: 30px; text-align:center; color:#888;">No shop products found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function ftToggle(id, featured) {
        fetch('admin_toggle_featured.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=toggle&id=' + id + '&featured=' + (featured ? '1' : '0')
        }).then(r => r.json()).then(() => window.location.reload());
    }
    function ftMove(id, dir) {
        fetch('admin_toggle_featured.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=move&id=' + id + '&dir=' + dir
        }).then(r => r.json()).then(() => window.location.reload());
    }
</script>

<?php include 'admin_footer.php'; ?>
