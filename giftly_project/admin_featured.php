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

$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_cat    = isset($_GET['filter_cat']) ? intval($_GET['filter_cat']) : 0;
$limit         = 12;
$page          = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset        = ($page - 1) * $limit;

include 'admin_header.php';

// --- Currently featured, in homepage order (not paginated — there should only ever be a handful) ---
$featured_rows = [];
$feat_res = $conn->query("SELECT p.*, c.name AS cat_name FROM products p
                          LEFT JOIN categories c ON c.id = p.category_id
                          WHERE p.product_type = 'catalog' AND p.is_featured = TRUE
                          ORDER BY p.featured_order ASC, p.id DESC");
while ($feat_res && $row = $feat_res->fetch_assoc()) $featured_rows[] = $row;
$featured_count = count($featured_rows);

// --- Browsable catalog: filterable by category, searchable, paginated ---
$where = "product_type = 'catalog'";
if ($filter_cat > 0) { $where .= " AND category_id = " . (int) $filter_cat; }
if ($search !== '') { $where .= " AND name ILIKE '%" . $conn->real_escape_string($search) . "%'"; }

$count_res  = $conn->query("SELECT COUNT(*) AS c FROM products WHERE $where");
$total_rows = $count_res ? (int) $count_res->fetch_assoc()['c'] : 0;
$total_pages = max(1, (int) ceil($total_rows / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

$list_res = $conn->query("SELECT p.*, c.name AS cat_name FROM products p
                          LEFT JOIN categories c ON c.id = p.category_id
                          WHERE $where
                          ORDER BY p.is_featured DESC, p.name ASC
                          LIMIT $limit OFFSET $offset");

$categories = [];
$cat_res = $conn->query("SELECT * FROM categories ORDER BY name ASC");
while ($cat_res && $c = $cat_res->fetch_assoc()) $categories[] = $c;

function ft_page_url($page, $filter_cat, $search) {
    return 'admin_featured.php?' . http_build_query(['page' => $page, 'filter_cat' => $filter_cat, 'search' => $search]);
}
?>

<style>
    .wide-container { max-width: 1100px; margin: 0 auto; padding: 40px 20px; width: 100%; flex: 1; }
    .admin-table-card { background: #fff; border-radius: 24px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); margin-bottom: 25px; }
    .ft-section-title { font-size: 17px; font-weight: 700; color: #222; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
    .ft-section-sub { font-size: 13px; color: #888; margin-bottom: 18px; }

    /* --- Currently featured strip --- */
    .ft-featured-list { display: flex; flex-direction: column; gap: 10px; }
    .ft-featured-row { display: flex; align-items: center; gap: 14px; background: #fff0f5; border: 1.5px solid #ffdbe4; border-radius: 16px; padding: 10px 14px; }
    .ft-rank { width: 26px; height: 26px; border-radius: 50%; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .ft-featured-thumb { width: 46px; height: 46px; object-fit: contain; background: #fff; border-radius: 10px; padding: 4px; flex-shrink: 0; }
    .ft-featured-info { flex: 1; min-width: 0; }
    .ft-featured-name { font-weight: 600; color: #222; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ft-featured-meta { font-size: 12px; color: #999; }
    .ft-empty-note { color: #999; font-size: 13.5px; padding: 10px 4px; }

    /* --- Browsable catalog grid --- */
    .ft-toolbar { display: flex; gap: 10px; margin-bottom: 22px; flex-wrap: wrap; }
    .ft-toolbar form { display: flex; gap: 10px; flex-wrap: wrap; }
    .pink-input, .pink-select { padding: 12px 16px; border: 1.5px solid #eee; border-radius: 30px; font-size: 14px; font-family: 'Poppins'; outline: none; background: #fff; }
    .pink-input:focus, .pink-select:focus { border-color: #ffc1cc; }
    .pink-input { flex: 1; min-width: 160px; }
    .action-btn-primary { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: white; padding: 12px 22px; border: none; border-radius: 30px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .action-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254,165,182,0.35); }

    .ft-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
    .ft-card { border: 1.5px solid #f0f0f0; border-radius: 18px; padding: 14px; display: flex; flex-direction: column; gap: 8px; transition: 0.2s; position: relative; }
    .ft-card.is-on { border-color: #ffc1cc; background: #fff8fa; }
    .ft-card-thumb-wrap { background: #fafafa; border-radius: 12px; height: 110px; display: flex; align-items: center; justify-content: center; }
    .ft-card-thumb { max-width: 100%; max-height: 90px; object-fit: contain; }
    .ft-card-cat { position: absolute; top: 10px; left: 10px; background: #fff; color: #888; font-size: 10.5px; font-weight: 600; padding: 3px 10px; border-radius: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
    .ft-card-name { font-size: 13.5px; font-weight: 600; color: #222; line-height: 1.3; min-height: 35px; }
    .ft-card-price { font-size: 13px; color: #666; }
    .ft-card-footer { display: flex; align-items: center; justify-content: space-between; margin-top: auto; }
    .ft-card-rank { font-size: 12px; font-weight: 700; color: #ff8ba7; }

    .ft-switch { position: relative; display: inline-block; width: 42px; height: 23px; }
    .ft-switch input { opacity: 0; width: 0; height: 0; }
    .ft-slider { position: absolute; cursor: pointer; inset: 0; background-color: #ddd; transition: 0.2s; border-radius: 24px; }
    .ft-slider:before { position: absolute; content: ""; height: 17px; width: 17px; left: 3px; bottom: 3px; background-color: #fff; transition: 0.2s; border-radius: 50%; }
    .ft-switch input:checked + .ft-slider { background-color: #ff8ba7; }
    .ft-switch input:checked + .ft-slider:before { transform: translateX(19px); }

    .ft-order-btns { display: inline-flex; gap: 4px; }
    .ft-order-btn { background: #f3f3f3; color: #555; border: none; width: 26px; height: 26px; border-radius: 8px; cursor: pointer; font-size: 12px; transition: 0.2s; }
    .ft-order-btn:hover:not(:disabled) { background: #ffc1cc; color: #fff; }
    .ft-order-btn:disabled { opacity: 0.3; cursor: not-allowed; }

    .ft-empty { text-align: center; padding: 50px 20px; color: #999; grid-column: 1 / -1; }
    .ft-empty i { font-size: 40px; color: #ddd; display: block; margin-bottom: 12px; }

    .ft-pagination { display: flex; justify-content: center; gap: 8px; margin-top: 26px; flex-wrap: wrap; }
    .ft-page-btn { padding: 9px 16px; border: 1.5px solid #eee; border-radius: 30px; background: #fff; color: #555; text-decoration: none; font-size: 13.5px; font-weight: 500; transition: 0.2s; font-family: 'Poppins'; }
    .ft-page-btn:hover { background: #ffc1cc; color: #fff; border-color: #ffc1cc; }
    .ft-page-btn.active { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border-color: #FEA5B6; }
    .ft-page-btn.disabled { opacity: 0.4; pointer-events: none; }
</style>

<div class="wide-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap:wrap; gap:10px;">
        <h2 style="font-size: 26px; font-weight: 600; color: #222; margin-bottom: 5px;">Featured Products</h2>
        <a href="admin_dashboard.php" style="background: #f3f3f3; padding: 8px 20px; border-radius: 50px; font-size: 14px; font-weight: 500; color: #555; text-decoration: none; transition: 0.3s;">&larr; Dashboard</a>
    </div>
    <p style="color:#888; font-size:14px; margin-bottom:25px;">Turn on products below to control what shows in the homepage's "Featured Products" section (first 4, top to bottom). When nothing is turned on, the homepage falls back to showing on-sale / newest products automatically.</p>

    <!-- CURRENTLY FEATURED, IN ORDER -->
    <div class="admin-table-card">
        <div class="ft-section-title"><i class="fas fa-star" style="color:#ff8ba7;"></i> Currently Featured (<?php echo $featured_count; ?>)</div>
        <div class="ft-section-sub">This is the exact order shown on the homepage, top to bottom.</div>
        <div class="ft-featured-list" id="ftFeaturedList">
            <?php if ($featured_count === 0): ?>
                <div class="ft-empty-note">Nothing featured yet — turn some on in the catalog below.</div>
            <?php else: foreach ($featured_rows as $i => $row): ?>
                <div class="ft-featured-row" data-id="<?php echo $row['id']; ?>">
                    <div class="ft-rank">#<?php echo $i + 1; ?></div>
                    <img class="ft-featured-thumb" src="<?php echo htmlspecialchars(img_url($row['image'])); ?>" alt="">
                    <div class="ft-featured-info">
                        <div class="ft-featured-name"><?php echo htmlspecialchars($row['name']); ?></div>
                        <div class="ft-featured-meta"><?php echo htmlspecialchars($row['cat_name'] ?? 'Uncategorized'); ?> · PHP <?php echo number_format((float) $row['price'], 2); ?></div>
                    </div>
                    <span class="ft-order-btns">
                        <button class="ft-order-btn" onclick="ftMove(<?php echo $row['id']; ?>, 'up')" <?php echo $i === 0 ? 'disabled' : ''; ?>><i class="fas fa-arrow-up"></i></button>
                        <button class="ft-order-btn" onclick="ftMove(<?php echo $row['id']; ?>, 'down')" <?php echo $i === $featured_count - 1 ? 'disabled' : ''; ?>><i class="fas fa-arrow-down"></i></button>
                    </span>
                    <label class="ft-switch" title="Remove from featured">
                        <input type="checkbox" checked onchange="ftToggle(<?php echo $row['id']; ?>, this.checked)">
                        <span class="ft-slider"></span>
                    </label>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- BROWSABLE CATALOG -->
    <div class="admin-table-card">
        <div class="ft-section-title"><i class="fas fa-store" style="color:#ff8ba7;"></i> Shop Products</div>
        <div class="ft-section-sub"><?php echo $total_rows; ?> product<?php echo $total_rows === 1 ? '' : 's'; ?> · flip a switch to feature or unfeature it.</div>

        <div class="ft-toolbar">
            <form method="GET" style="flex:1; display:flex; gap:10px; flex-wrap:wrap;">
                <input type="text" name="search" class="pink-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="filter_cat" class="pink-select" onchange="this.form.submit()">
                    <option value="0">📂 All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $filter_cat == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="action-btn-primary"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <div class="ft-grid" id="ftGrid">
            <?php if ($list_res && $list_res->num_rows > 0): ?>
                <?php while ($row = $list_res->fetch_assoc()):
                    $isFeatured = catalog_is_active($row['is_featured'] ?? false);
                    $rank = null;
                    if ($isFeatured) {
                        foreach ($featured_rows as $i => $fr) { if ((int) $fr['id'] === (int) $row['id']) { $rank = $i + 1; break; } }
                    }
                ?>
                    <div class="ft-card <?php echo $isFeatured ? 'is-on' : ''; ?>" data-id="<?php echo $row['id']; ?>">
                        <?php if (!empty($row['cat_name'])): ?><div class="ft-card-cat"><?php echo htmlspecialchars($row['cat_name']); ?></div><?php endif; ?>
                        <div class="ft-card-thumb-wrap"><img class="ft-card-thumb" src="<?php echo htmlspecialchars(img_url($row['image'])); ?>" alt=""></div>
                        <div class="ft-card-name"><?php echo htmlspecialchars($row['name']); ?></div>
                        <div class="ft-card-price">PHP <?php echo number_format((float) $row['price'], 2); ?></div>
                        <div class="ft-card-footer">
                            <span class="ft-card-rank"><?php echo $rank ? '#' . $rank . ' featured' : ''; ?></span>
                            <label class="ft-switch">
                                <input type="checkbox" <?php echo $isFeatured ? 'checked' : ''; ?> onchange="ftToggle(<?php echo $row['id']; ?>, this.checked)">
                                <span class="ft-slider"></span>
                            </label>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="ft-empty"><i class="fas fa-box-open"></i><p>No shop products found.</p></div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="ft-pagination">
            <a href="<?php echo ft_page_url(max(1, $page - 1), $filter_cat, $search); ?>" class="ft-page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">&larr; Prev</a>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?php echo ft_page_url($i, $filter_cat, $search); ?>" class="ft-page-btn <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="<?php echo ft_page_url(min($total_pages, $page + 1), $filter_cat, $search); ?>" class="ft-page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next &rarr;</a>
        </div>
        <?php endif; ?>
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
