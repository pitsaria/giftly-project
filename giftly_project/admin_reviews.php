<?php
include 'db_connect.php';
include 'reviews_lib.php';
reviews_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = (int) $_SESSION['user_id'];
$u = $conn->query("SELECT role FROM users WHERE id = $user_id");
if (!$u || ($u->fetch_assoc()['role'] ?? '') !== 'admin') { header("Location: shop.php"); exit(); }

// --- actions (redirect after) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rid = (int) ($_POST['review_id'] ?? 0);
    if ($rid) {
        if (isset($_POST['hide']))    { $conn->query("UPDATE product_reviews SET status = 'hidden' WHERE id = $rid"); $msg = 'Review hidden from the shop.'; }
        if (isset($_POST['publish'])) { $conn->query("UPDATE product_reviews SET status = 'published' WHERE id = $rid"); $msg = 'Review published.'; }
        if (isset($_POST['delete']))  { $conn->query("DELETE FROM product_reviews WHERE id = $rid"); $msg = 'Review deleted.'; }
    }
    $_SESSION['rv_flash'] = $msg ?? '';
    $q = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: admin_reviews.php$q");
    exit();
}
$flash = $_SESSION['rv_flash'] ?? null;
unset($_SESSION['rv_flash']);

include 'admin_header.php';

$filter = $_GET['filter'] ?? 'all';                 // all | published | hidden
$fr     = (int) ($_GET['rating'] ?? 0);             // 0 = any
$search = trim($_GET['search'] ?? '');

$where = "WHERE 1=1";
if ($filter === 'published') $where .= " AND r.status = 'published'";
if ($filter === 'hidden')    $where .= " AND r.status = 'hidden'";
if ($fr >= 1 && $fr <= 5)     $where .= " AND r.rating = $fr";
if ($search !== '')           $where .= " AND p.name ILIKE '%" . $conn->real_escape_string($search) . "%'";

$limit = 12;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$total = (int) ($conn->query("SELECT COUNT(*) AS c FROM product_reviews r JOIN products p ON p.id = r.product_id $where")->fetch_assoc()['c'] ?? 0);
$total_pages = max(1, (int) ceil($total / $limit));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

$stats = $conn->query("SELECT COUNT(*) AS total, COALESCE(ROUND(AVG(rating),2),0) AS avg,
                              SUM(CASE WHEN status='hidden' THEN 1 ELSE 0 END) AS hidden
                       FROM product_reviews")->fetch_assoc();

$rows = $conn->query("
    SELECT r.*, p.name AS product_name, p.image AS product_image, u.name AS user_name
    FROM product_reviews r
    JOIN products p ON p.id = r.product_id
    JOIN users u ON u.id = r.user_id
    $where
    ORDER BY r.created_at DESC
    LIMIT $limit OFFSET $offset
");

function admin_stars($n) {
    $h = '';
    for ($i = 1; $i <= 5; $i++) $h .= '<i class="fa' . ($i <= $n ? 's' : 'r') . ' fa-star"></i>';
    return $h;
}
?>
<style>
    .main-wrapper { max-width: 1000px; margin: 0 auto; padding: 40px 20px; width: 100%; flex: 1; }
    .rvA-top { margin-bottom: 22px; }
    .rvA-top h2 { font-size: 26px; font-weight: 700; color: #222; }
    .rvA-stats { display: flex; gap: 14px; margin: 14px 0 22px; flex-wrap: wrap; }
    .rvA-stat { background: #fff; border: 1px solid #f0f0f0; border-radius: 16px; padding: 14px 20px; box-shadow: 0 3px 12px rgba(0,0,0,0.03); }
    .rvA-stat b { font-size: 20px; color: #222; display: block; }
    .rvA-stat span { font-size: 12px; color: #999; }
    .rvA-filters { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
    .rvA-filters a, .rvA-filters button { padding: 7px 16px; border-radius: 50px; border: 1.5px solid #eee; background: #fff; color: #666; font-family: 'Poppins'; font-weight: 600; font-size: 12.5px; text-decoration: none; cursor: pointer; }
    .rvA-filters a.on { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border-color: #FEA5B6; }
    .rvA-filters form { display: flex; gap: 6px; }
    .rvA-filters input { border: 1.5px solid #eee; border-radius: 50px; padding: 6px 14px; font-family: 'Poppins'; font-size: 12.5px; outline: none; }
    .rvA-flash { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; padding: 12px 18px; border-radius: 14px; margin-bottom: 16px; font-size: 14px; }
    .rvA-card { background: #fff; border: 1px solid #f0f0f0; border-radius: 18px; padding: 18px 20px; margin-bottom: 12px; box-shadow: 0 3px 12px rgba(0,0,0,0.03); }
    .rvA-card.hidden { opacity: 0.6; }
    .rvA-head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: center; margin-bottom: 8px; }
    .rvA-prod { display: flex; align-items: center; gap: 10px; font-weight: 700; color: #222; font-size: 14px; }
    .rvA-prod img { width: 36px; height: 36px; object-fit: contain; background: #fafafa; border-radius: 8px; padding: 3px; }
    .rvA-stars { color: #ffb400; font-size: 13px; }
    .rvA-meta { font-size: 12px; color: #999; }
    .rvA-body { font-size: 13.5px; color: #555; line-height: 1.6; white-space: pre-wrap; margin: 6px 0 12px; }
    .rvA-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .rvA-btn { padding: 6px 14px; border-radius: 50px; border: none; font-size: 12px; font-weight: 600; cursor: pointer; font-family: 'Poppins'; }
    .rvA-btn.hide { background: #fff3e0; color: #e65100; }
    .rvA-btn.pub { background: #e8f5e9; color: #2e7d32; }
    .rvA-btn.del { background: #ffe4e4; color: #d32f2f; }
    .rvA-tag { font-size: 11px; font-weight: 700; padding: 2px 10px; border-radius: 50px; }
    .rvA-tag.h { background: #fff3e0; color: #e65100; }
    .rvA-empty { text-align: center; padding: 60px 20px; color: #999; }
    .pagination-wrapper { display: flex; justify-content: center; gap: 8px; margin-top: 24px; flex-wrap: wrap; }
    .page-btn { padding: 8px 16px; border: 1.5px solid #eee; border-radius: 30px; background: #fff; color: #555; text-decoration: none; font-size: 14px; font-family: 'Poppins'; }
    .page-btn.active { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border-color: #FEA5B6; }
    .page-btn.disabled { opacity: 0.5; pointer-events: none; }
</style>

<div class="main-wrapper">
    <div class="rvA-top">
        <h2>⭐ Product Reviews</h2>
        <div class="rvA-stats">
            <div class="rvA-stat"><b><?php echo (int) $stats['total']; ?></b><span>total reviews</span></div>
            <div class="rvA-stat"><b><?php echo number_format((float) $stats['avg'], 2); ?></b><span>average rating</span></div>
            <div class="rvA-stat"><b><?php echo (int) $stats['hidden']; ?></b><span>hidden</span></div>
        </div>
    </div>

    <?php if ($flash): ?><div class="rvA-flash"><i class="fas fa-check-circle" style="margin-right:6px;"></i><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>

    <div class="rvA-filters">
        <?php
        $base = function ($f, $r) use ($search) {
            $p = ['filter' => $f, 'rating' => $r];
            if ($search !== '') $p['search'] = $search;
            return 'admin_reviews.php?' . http_build_query(array_filter($p));
        };
        ?>
        <a href="<?php echo $base('all', $fr); ?>" class="<?php echo $filter === 'all' ? 'on' : ''; ?>">All</a>
        <a href="<?php echo $base('published', $fr); ?>" class="<?php echo $filter === 'published' ? 'on' : ''; ?>">Published</a>
        <a href="<?php echo $base('hidden', $fr); ?>" class="<?php echo $filter === 'hidden' ? 'on' : ''; ?>">Hidden</a>
        <span style="width:1px;height:20px;background:#eee;"></span>
        <?php for ($s = 5; $s >= 1; $s--): ?>
            <a href="<?php echo $base($filter, $fr === $s ? 0 : $s); ?>" class="<?php echo $fr === $s ? 'on' : ''; ?>"><?php echo $s; ?>★</a>
        <?php endfor; ?>
        <span style="width:1px;height:20px;background:#eee;"></span>
        <form method="GET" action="admin_reviews.php">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="hidden" name="rating" value="<?php echo $fr; ?>">
            <input type="text" name="search" placeholder="Product name…" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="rvA-btn pub">Search</button>
        </form>
        <a href="admin_dashboard.php" style="margin-left:auto; background:#f3f3f3; color:#555;">&larr; Dashboard</a>
    </div>

    <?php if ($rows && $rows->num_rows > 0): ?>
        <?php while ($r = $rows->fetch_assoc()):
            $hidden = ($r['status'] === 'hidden');
        ?>
        <div class="rvA-card <?php echo $hidden ? 'hidden' : ''; ?>">
            <div class="rvA-head">
                <div class="rvA-prod">
                    <img src="uploads/<?php echo htmlspecialchars($r['product_image']); ?>" alt="">
                    <?php echo htmlspecialchars($r['product_name']); ?>
                    <?php if ($hidden): ?><span class="rvA-tag h">Hidden</span><?php endif; ?>
                </div>
                <div class="rvA-stars"><?php echo admin_stars((int) $r['rating']); ?></div>
            </div>
            <div class="rvA-meta">by <strong><?php echo htmlspecialchars($r['user_name']); ?></strong> · <?php echo date('M j, Y', strtotime($r['created_at'])); ?><?php echo $r['order_id'] ? ' · order #' . (int) $r['order_id'] : ''; ?></div>
            <?php if (trim($r['comment']) !== ''): ?>
                <div class="rvA-body"><?php echo htmlspecialchars($r['comment']); ?></div>
            <?php endif; ?>
            <div class="rvA-actions">
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="review_id" value="<?php echo (int) $r['id']; ?>">
                    <?php if ($hidden): ?>
                        <button type="submit" name="publish" value="1" class="rvA-btn pub"><i class="fas fa-eye"></i> Publish</button>
                    <?php else: ?>
                        <button type="submit" name="hide" value="1" class="rvA-btn hide"><i class="fas fa-eye-slash"></i> Hide</button>
                    <?php endif; ?>
                </form>
                <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this review permanently?');">
                    <input type="hidden" name="review_id" value="<?php echo (int) $r['id']; ?>">
                    <button type="submit" name="delete" value="1" class="rvA-btn del"><i class="fas fa-trash"></i> Delete</button>
                </form>
            </div>
        </div>
        <?php endwhile; ?>

        <?php if ($total_pages > 1):
            $qp = array_filter(['filter' => $filter, 'rating' => $fr, 'search' => $search]);
        ?>
        <div class="pagination-wrapper">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="admin_reviews.php?<?php echo http_build_query(array_merge($qp, ['page' => $i])); ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="rvA-empty"><i class="fas fa-star" style="font-size:44px;color:#ddd;display:block;margin-bottom:12px;"></i>No reviews match this filter.</div>
    <?php endif; ?>
</div>

<?php include 'admin_footer.php'; ?>
