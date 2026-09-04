<?php
/** Admin top-bar notification bell. Included once by admin_header.php. */
if (!isset($conn)) return;
include_once __DIR__ . '/admin_notif_lib.php';
$__n = admin_notif_counts($conn);
?>
<style>
    .admin-bell-wrap { position: fixed; top: 22px; right: 26px; z-index: 99999; font-family: 'Poppins', sans-serif; }
    .admin-bell-btn {
        width: 46px; height: 46px; border-radius: 50%; border: none; cursor: pointer;
        background: #fff; box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        display: flex; align-items: center; justify-content: center;
        color: #ff8ba7; font-size: 18px; position: relative; transition: 0.2s;
    }
    .admin-bell-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(0,0,0,0.12); }
    .admin-bell-count {
        position: absolute; top: -4px; right: -4px; min-width: 20px; height: 20px; padding: 0 5px;
        background: #ff4d6d; color: #fff; font-size: 11px; font-weight: 700;
        border-radius: 50px; display: flex; align-items: center; justify-content: center;
        border: 2px solid #fff;
    }
    .admin-bell-panel {
        position: absolute; top: 56px; right: 0; width: 320px; background: #fff;
        border-radius: 18px; box-shadow: 0 20px 50px rgba(0,0,0,0.16); overflow: hidden;
        display: none;
    }
    .admin-bell-panel.open { display: block; }
    .admin-bell-panel h4 { font-size: 14px; font-weight: 700; color: #222; padding: 16px 18px 10px; }
    .admin-bell-item {
        display: flex; align-items: center; gap: 12px; padding: 12px 18px;
        border-top: 1px solid #f4f4f4; color: #444; font-size: 13.5px; text-decoration: none;
    }
    .admin-bell-item:hover { background: #fff5f7; }
    .admin-bell-item .ic { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; }
    .admin-bell-item .n { margin-left: auto; font-weight: 700; color: #ff4d6d; }
    .admin-bell-empty { padding: 26px 18px; text-align: center; color: #999; font-size: 13px; }
    @media (max-width: 900px) { .admin-bell-wrap { top: 14px; right: 14px; } }
</style>

<div class="admin-bell-wrap" id="adminBell">
    <button class="admin-bell-btn" onclick="document.getElementById('adminBellPanel').classList.toggle('open')" aria-label="Notifications">
        <i class="fas fa-bell"></i>
        <?php if ($__n['total'] > 0): ?><span class="admin-bell-count"><?php echo $__n['total'] > 99 ? '99+' : $__n['total']; ?></span><?php endif; ?>
    </button>
    <div class="admin-bell-panel" id="adminBellPanel">
        <h4>Needs your attention</h4>
        <?php if ($__n['total'] === 0): ?>
            <div class="admin-bell-empty"><i class="fas fa-circle-check" style="color:#66bb6a;"></i> You're all caught up.</div>
        <?php else: ?>
            <?php if ($__n['new_orders'] > 0): ?>
            <a class="admin-bell-item" href="admin_orders.php">
                <span class="ic" style="background:#fff0f5;color:#ff8ba7;"><i class="fas fa-shopping-bag"></i></span>
                <span><?php echo $__n['new_orders']; ?> new order<?php echo $__n['new_orders'] === 1 ? '' : 's'; ?></span>
                <span class="n"><?php echo $__n['new_orders']; ?></span>
            </a>
            <?php endif; ?>
            <?php if ($__n['cancels'] > 0): ?>
            <a class="admin-bell-item" href="admin_orders.php?filter_status=cancel_requested">
                <span class="ic" style="background:#fff8e1;color:#a5710d;"><i class="fas fa-hourglass-half"></i></span>
                <span><?php echo $__n['cancels']; ?> cancellation request<?php echo $__n['cancels'] === 1 ? '' : 's'; ?></span>
                <span class="n"><?php echo $__n['cancels']; ?></span>
            </a>
            <?php endif; ?>
            <?php if ($__n['unpaid'] > 0): ?>
            <a class="admin-bell-item" href="admin_orders.php">
                <span class="ic" style="background:#e3f2fd;color:#1976d2;"><i class="fas fa-credit-card"></i></span>
                <span><?php echo $__n['unpaid']; ?> order<?php echo $__n['unpaid'] === 1 ? '' : 's'; ?> awaiting payment</span>
                <span class="n"><?php echo $__n['unpaid']; ?></span>
            </a>
            <?php endif; ?>
            <?php if ($__n['new_reviews'] > 0): ?>
            <a class="admin-bell-item" href="admin_reviews.php">
                <span class="ic" style="background:#fff8e6;color:#e0a800;"><i class="fas fa-star"></i></span>
                <span><?php echo $__n['new_reviews']; ?> new review<?php echo $__n['new_reviews'] === 1 ? '' : 's'; ?></span>
                <span class="n"><?php echo $__n['new_reviews']; ?></span>
            </a>
            <?php endif; ?>
            <?php if ($__n['messages'] > 0): ?>
            <a class="admin-bell-item" href="admin_messages.php?filter=unread">
                <span class="ic" style="background:#fff0f5;color:#ff8ba7;"><i class="fas fa-envelope"></i></span>
                <span><?php echo $__n['messages']; ?> unread message<?php echo $__n['messages'] === 1 ? '' : 's'; ?></span>
                <span class="n"><?php echo $__n['messages']; ?></span>
            </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<script>
    document.addEventListener('click', function (e) {
        var w = document.getElementById('adminBell');
        if (w && !w.contains(e.target)) {
            document.getElementById('adminBellPanel').classList.remove('open');
        }
    });
</script>
