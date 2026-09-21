<?php
include 'db_connect.php';
include_once 'notif_lib.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$uid = (int) $_SESSION['user_id'];
notif_ensure_schema($conn);

$categories = ['all' => 'All', 'order' => 'Orders', 'promo' => 'Promos', 'product' => 'Products'];
$cat = $_GET['category'] ?? 'all';
if (!isset($categories[$cat])) $cat = 'all';

$per_page = 20;
$limit = min(200, max($per_page, (int) ($_GET['limit'] ?? $per_page)));

/** notifications.php URL for a category/limit, omitting defaults. */
function nt_url($cat, $limit = 20) {
    $q = [];
    if ($cat !== 'all') $q['category'] = $cat;
    if ($limit > 20) $q['limit'] = $limit;
    return 'notifications.php' . ($q ? '?' . http_build_query($q) : '');
}

// --- mark as read (POST) — handled before any output ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_one'])) {
        $nid = (int) $_POST['mark_one'];
        $conn->query("UPDATE notifications SET read_at = (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')
                      WHERE id = $nid AND user_id = $uid AND read_at IS NULL");
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit();
    }
    if (isset($_POST['mark_all'])) {
        $conn->query("UPDATE notifications SET read_at = (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')
                      WHERE user_id = $uid AND read_at IS NULL");
        header('Location: ' . nt_url($cat, $limit));
        exit();
    }
}

// --- unread counts per category, for the tab badges ---
$unread = ['all' => 0, 'order' => 0, 'promo' => 0, 'product' => 0];
$uc = $conn->query("SELECT category, COUNT(*) AS c FROM notifications
                    WHERE user_id = $uid AND read_at IS NULL GROUP BY category");
while ($uc && $r = $uc->fetch_assoc()) {
    $n = (int) $r['c'];
    $unread['all'] += $n;
    if (isset($unread[$r['category']])) $unread[$r['category']] = $n;
}

// --- the list (fetch one extra row to know whether there's more) ---
$where = "user_id = $uid";
if ($cat !== 'all') $where .= " AND category = '" . $conn->real_escape_string($cat) . "'";
$fetch = $limit + 1;
$items = [];
$res = $conn->query("SELECT id, category, title, body, read_at, created_at FROM notifications
                     WHERE $where ORDER BY created_at DESC, id DESC LIMIT $fetch");
while ($res && $row = $res->fetch_assoc()) $items[] = $row;
$has_more = count($items) > $limit;
if ($has_more) $items = array_slice($items, 0, $limit);

function nt_utc($created_at) {
    try { return new DateTime($created_at, new DateTimeZone('UTC')); } catch (Exception $e) { return null; }
}

/** Today / Yesterday / This week / Earlier — by Philippine calendar day. */
function nt_group($created_at) {
    $dt = nt_utc($created_at);
    if (!$dt) return 'Earlier';
    $tz = new DateTimeZone('Asia/Manila');
    $day = $dt->setTimezone($tz)->setTime(0, 0, 0);
    $today = new DateTime('today', $tz);
    $days = -1 * (int) $today->diff($day)->format('%r%a');
    if ($days <= 0) return 'Today';
    if ($days === 1) return 'Yesterday';
    if ($days < 7) return 'This week';
    return 'Earlier';
}

function nt_ago($created_at) {
    $dt = nt_utc($created_at);
    if (!$dt) return '';
    $secs = max(0, time() - $dt->getTimestamp());
    if ($secs < 60) return 'just now';
    if ($secs < 3600) return floor($secs / 60) . 'm ago';
    if ($secs < 86400) return floor($secs / 3600) . 'h ago';
    if ($secs < 7 * 86400) return floor($secs / 86400) . 'd ago';
    return ph_datetime($created_at, 'M j, Y');
}

$group_order = ['Today', 'Yesterday', 'This week', 'Earlier'];
$groups = [];
foreach ($items as $it) $groups[nt_group($it['created_at'])][] = $it;

$icons = ['order' => 'fa-receipt', 'promo' => 'fa-tag', 'product' => 'fa-bag-shopping'];

include 'header.php';
?>

<style>
    .nt-wrap { max-width: 820px; margin: 0 auto; padding: 130px 20px 60px; }
    .nt-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
    .nt-head h1 { font-size: 32px; font-weight: 700; color: #222; }
    .nt-markall { background: none; border: none; cursor: pointer; font-size: 14px; font-weight: 600; color: #ff8ba7; padding: 8px 4px; }
    .nt-markall:hover { text-decoration: underline; }

    .nt-tabs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 30px; }
    .nt-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; border-radius: 50px; background: #fff0f5; color: #ff8ba7; font-size: 14px; font-weight: 600; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s; }
    .nt-tabs a:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(255, 139, 167, 0.2); }
    .nt-tabs a.active { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; box-shadow: 0 8px 20px rgba(254, 165, 182, 0.4); }
    .nt-count { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 50px; background: #ff8ba7; color: #fff; font-size: 11px; font-weight: 700; display: none; align-items: center; justify-content: center; line-height: 1; }
    .nt-count.show { display: inline-flex; }
    .nt-tabs a.active .nt-count { background: rgba(255, 255, 255, 0.3); }

    .nt-group { font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #b0a8ab; margin: 26px 4px 10px; }
    .nt-tabs + .nt-group { margin-top: 0; }

    .nt-item { position: relative; display: flex; align-items: flex-start; gap: 16px; padding: 18px 22px 18px 26px; margin-bottom: 12px; background: #fff; border: 1px solid #f5f5f5; border-radius: 22px; box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04); overflow: hidden; }
    .nt-item.unread { background: #fff8fa; border-color: #ffe1e8; cursor: pointer; }
    .nt-item.unread::before { content: ''; position: absolute; top: 0; bottom: 0; left: 0; width: 5px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); }
    .nt-item.unread .nt-title { font-weight: 800; }
    .nt-icon { flex-shrink: 0; width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 19px; background: #fff0f5; color: #ff8ba7; }
    .nt-icon.order { background: #eaf2fd; color: #4f8fdb; }
    .nt-icon.promo { background: #fff0f5; color: #ff8ba7; }
    .nt-icon.product { background: #f3effc; color: #9b7fe0; }
    .nt-text { flex: 1; min-width: 0; }
    .nt-title { font-size: 16px; font-weight: 700; color: #222; margin-bottom: 3px; }
    .nt-body { font-size: 14px; color: #666; line-height: 1.6; }
    .nt-time { font-size: 12px; color: #b0a8ab; margin-top: 8px; }
    .nt-dot { position: absolute; top: 20px; right: 20px; width: 10px; height: 10px; border-radius: 50%; background: #ff8ba7; }

    .nt-empty { text-align: center; padding: 70px 20px; color: #b0a8ab; }
    .nt-empty i { font-size: 46px; margin-bottom: 14px; display: block; }
    .nt-empty p { font-size: 15px; }

    .nt-more { display: block; text-align: center; margin-top: 18px; padding: 13px; border-radius: 50px; border: 2px solid #ff8ba7; color: #ff8ba7; font-weight: 700; text-decoration: none; transition: background 0.2s, color 0.2s; }
    .nt-more:hover { background: #ff8ba7; color: #fff; }
</style>

<div class="nt-wrap">
    <div class="nt-head">
        <h1>Notifications</h1>
        <form method="post" action="<?php echo htmlspecialchars(nt_url($cat, $limit)); ?>" id="markAllForm" style="<?php echo $unread['all'] > 0 ? '' : 'display:none;'; ?>">
            <button type="submit" name="mark_all" value="1" class="nt-markall">Mark all read</button>
        </form>
    </div>

    <div class="nt-tabs">
        <?php foreach ($categories as $key => $label): ?>
            <a href="<?php echo htmlspecialchars(nt_url($key)); ?>" class="<?php echo $key === $cat ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($label); ?>
                <span class="nt-count <?php echo $unread[$key] > 0 ? 'show' : ''; ?>" id="ntc-<?php echo $key; ?>"><?php echo $unread[$key]; ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($items)): ?>
        <div class="nt-empty">
            <i class="far fa-bell-slash"></i>
            <p>No notifications here yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($group_order as $label):
            if (empty($groups[$label])) continue; ?>
            <div class="nt-group"><?php echo htmlspecialchars($label); ?></div>
            <?php foreach ($groups[$label] as $n):
                $is_unread = empty($n['read_at']);
                $icon = $icons[$n['category']] ?? 'fa-bell';
            ?>
                <div class="nt-item<?php echo $is_unread ? ' unread' : ''; ?>" data-id="<?php echo (int) $n['id']; ?>" data-cat="<?php echo htmlspecialchars($n['category']); ?>">
                    <div class="nt-icon <?php echo htmlspecialchars($n['category']); ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                    <div class="nt-text">
                        <div class="nt-title"><?php echo htmlspecialchars($n['title']); ?></div>
                        <div class="nt-body"><?php echo htmlspecialchars($n['body']); ?></div>
                        <div class="nt-time"><?php echo htmlspecialchars(nt_ago($n['created_at'])); ?></div>
                    </div>
                    <?php if ($is_unread): ?><span class="nt-dot"></span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <?php if ($has_more): ?>
            <a class="nt-more" href="<?php echo htmlspecialchars(nt_url($cat, $limit + $per_page)); ?>">Load more</a>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    // Clicking an unread notification marks it read without reloading, and
    // keeps the navbar bell and the tab counts in step.
    (function () {
        function bump(id, by) {
            var el = document.getElementById(id);
            if (!el) return;
            var n = Math.max(0, (parseInt(el.textContent, 10) || 0) + by);
            el.textContent = n > 99 ? '99+' : n;
            el.classList.toggle('show', n > 0);
        }
        document.querySelectorAll('.nt-item.unread').forEach(function (item) {
            item.addEventListener('click', function () {
                if (!item.classList.contains('unread')) return;
                item.classList.remove('unread');
                var dot = item.querySelector('.nt-dot');
                if (dot) dot.remove();
                bump('bellBadge', -1);
                bump('ntc-all', -1);
                bump('ntc-' + item.dataset.cat, -1);
                var allEl = document.getElementById('ntc-all');
                if (allEl && !allEl.classList.contains('show')) {
                    var f = document.getElementById('markAllForm');
                    if (f) f.style.display = 'none';
                }
                var fd = new FormData();
                fd.append('mark_one', item.dataset.id);
                fetch('notifications.php', { method: 'POST', body: fd }).catch(function () {});
            });
        });
    })();
</script>

<?php include 'footer.php'; ?>
