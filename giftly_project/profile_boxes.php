<?php
/**
 * "My Boxes" tab — included by profile.php.
 * Expects $conn and $user_id in scope.
 */
if (!isset($conn)) { include 'db_connect.php'; }
include_once 'build_a_box_lib.php';
bab_ensure_schema($conn);
if (!isset($user_id)) { $user_id = intval($_SESSION['user_id'] ?? 0); }

$boxes = [];
$res = $conn->query("SELECT id FROM boxes WHERE user_id = " . intval($user_id) . "
                     AND status IN ('saved','in_cart') ORDER BY updated_at DESC, id DESC");
while ($res && $r = $res->fetch_assoc()) {
    $b = bab_load_box($conn, $r['id'], $user_id);
    if ($b) $boxes[] = $b;
}
?>
<style>
    .mb-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; flex-wrap:wrap; gap:12px; }
    .mb-new { background:linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%); color:#fff; padding:10px 22px; border-radius:50px; font-weight:600; font-size:14px; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
    .mb-new:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(254,165,182,0.35); }
    .mb-card { border:1px solid #f0f0f0; border-radius:20px; padding:20px; margin-bottom:16px; box-shadow:0 4px 15px rgba(0,0,0,0.03); }
    .mb-top { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
    .mb-size { font-size:16px; font-weight:700; color:#222; }
    .mb-meta { font-size:13px; color:#888; margin-top:2px; }
    .mb-badge { font-size:11px; font-weight:600; padding:3px 12px; border-radius:50px; }
    .mb-badge.cart { background:#fff0f5; color:#d81b60; }
    .mb-badge.saved { background:#eef2ff; color:#3f51b5; }
    .mb-thumbs { display:flex; gap:8px; margin:14px 0; flex-wrap:wrap; }
    .mb-thumbs img { width:46px; height:46px; object-fit:contain; background:#fafafa; border-radius:10px; padding:4px; border:1px solid #f0f0f0; }
    .mb-thumbs .more { width:46px; height:46px; border-radius:10px; background:#f5f5f5; display:flex; align-items:center; justify-content:center; font-size:12px; color:#888; font-weight:600; }
    .mb-issue { background:#fff8e1; border:1px solid #ffd54f; color:#7a5c00; font-size:12px; padding:8px 12px; border-radius:10px; margin:10px 0; }
    .mb-foot { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; border-top:1px solid #f5f5f5; padding-top:14px; margin-top:6px; }
    .mb-total { font-size:15px; font-weight:700; color:#222; }
    .mb-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .mb-btn { padding:8px 16px; border-radius:50px; font-size:13px; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-family:'Poppins'; }
    .mb-btn.edit { background:#eef4ff; color:#1976d2; }
    .mb-btn.co { background:linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%); color:#fff; }
    .mb-btn.del { background:#ffe4e4; color:#d32f2f; }
    .mb-btn:disabled, .mb-btn.disabled { opacity:.5; cursor:not-allowed; pointer-events:none; }
    .mb-empty { text-align:center; padding:50px 20px; color:#999; }
    .mb-empty i { font-size:48px; color:#ddd; display:block; margin-bottom:14px; }
</style>

<div class="mb-head">
    <h2 class="page-title" style="margin-bottom:0;">My Boxes</h2>
    <a href="build-a-box.php" class="mb-new"><i class="fas fa-plus"></i> Build a new box</a>
</div>

<?php if (empty($boxes)): ?>
    <div class="mb-empty">
        <i class="fas fa-gift"></i>
        <p style="font-size:16px;margin-bottom:16px;">You haven't built any boxes yet.</p>
        <a href="build-a-box.php" class="mb-new" style="display:inline-flex;"><i class="fas fa-plus"></i> Build a Box</a>
    </div>
<?php else: ?>
    <?php foreach ($boxes as $d):
        $box = $d['box'];
        $bad = count($d['issues']) > 0;
    ?>
    <div class="mb-card" id="mbCard<?php echo $box['id']; ?>">
        <div class="mb-top">
            <div>
                <div class="mb-size"><?php echo htmlspecialchars($box['size_name']); ?></div>
                <div class="mb-meta"><?php echo $d['item_count']; ?> / <?php echo $box['max_items']; ?> items
                    · updated <?php echo date('M j, Y', strtotime($box['updated_at'])); ?></div>
            </div>
            <span class="mb-badge <?php echo $box['status'] === 'in_cart' ? 'cart' : 'saved'; ?>">
                <?php echo $box['status'] === 'in_cart' ? 'In cart' : 'Saved'; ?>
            </span>
        </div>

        <div class="mb-thumbs">
            <?php
            $shown = 0;
            foreach ($d['items'] as $it) {
                if ($it['unavailable'] === 'removed') continue;
                if ($shown >= 6) break;
                echo '<img src="uploads/' . htmlspecialchars($it['image']) . '" alt="">';
                $shown++;
            }
            $remaining = count($d['items']) - $shown;
            if ($remaining > 0) echo '<div class="more">+' . $remaining . '</div>';
            ?>
        </div>

        <?php if (trim($box['letter']) !== ''): ?>
            <div style="font-size:13px;color:#777;font-style:italic;border-left:3px solid #ffc1cc;padding-left:10px;margin-bottom:10px;">
                “<?php echo htmlspecialchars(mb_strimwidth($box['letter'], 0, 120, '…')); ?>”
            </div>
        <?php endif; ?>

        <?php if ($bad): ?>
            <div class="mb-issue"><i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($d['issues'][0]); ?> Edit the box before checkout.
            </div>
        <?php endif; ?>

        <div class="mb-foot">
            <div class="mb-total">PHP <?php echo number_format($d['total'], 2); ?></div>
            <div class="mb-actions">
                <a href="build-a-box.php?box_id=<?php echo $box['id']; ?>" class="mb-btn edit"><i class="fas fa-pen"></i> Edit</a>
                <a href="box_checkout.php?box_id=<?php echo $box['id']; ?>" class="mb-btn co <?php echo $bad ? 'disabled' : ''; ?>"><i class="fas fa-lock"></i> Checkout</a>
                <button class="mb-btn del" onclick="mbDelete(<?php echo $box['id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
function mbDelete(id) {
    if (!confirm('Delete this box? This cannot be undone.')) return;
    const body = new URLSearchParams({ action: 'delete', box_id: id });
    fetch('box_actions.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') {
                const c = document.getElementById('mbCard' + id);
                if (c) c.remove();
            } else {
                alert(d.message || 'Could not delete box');
            }
        });
}
</script>
