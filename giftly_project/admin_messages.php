<?php
include 'db_connect.php';
include 'contact_lib.php';
contact_ensure_schema($conn);

// --- admin gate ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = (int) $_SESSION['user_id'];
$u = $conn->query("SELECT role FROM users WHERE id = $user_id");
if (!$u || ($u->fetch_assoc()['role'] ?? '') !== 'admin') {
    header("Location: shop.php");
    exit();
}

// --- actions (redirect after so a refresh doesn't re-submit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mid = (int) ($_POST['message_id'] ?? 0);
    $msg = '';
    if (isset($_POST['mark_read']) && $mid) {
        $conn->query("UPDATE contact_messages SET is_read = TRUE WHERE id = $mid");
        $msg = 'Marked as read.';
    } elseif (isset($_POST['mark_unread']) && $mid) {
        $conn->query("UPDATE contact_messages SET is_read = FALSE WHERE id = $mid");
        $msg = 'Marked as unread.';
    } elseif (isset($_POST['archive_message']) && $mid) {
        $conn->query("UPDATE contact_messages SET archived = TRUE, is_read = TRUE WHERE id = $mid");
        $msg = 'Message archived.';
    } elseif (isset($_POST['unarchive_message']) && $mid) {
        $conn->query("UPDATE contact_messages SET archived = FALSE WHERE id = $mid");
        $msg = 'Message restored to the inbox.';
    } elseif (isset($_POST['delete_message']) && $mid) {
        $conn->query("DELETE FROM contact_messages WHERE id = $mid");
        $msg = 'Message deleted.';
    } elseif (isset($_POST['mark_all_read'])) {
        $conn->query("UPDATE contact_messages SET is_read = TRUE WHERE is_read = FALSE AND archived = FALSE");
        $msg = 'All messages marked as read.';
    }
    $_SESSION['msg_flash'] = $msg;
    $back = 'admin_messages.php';
    $bf = $_GET['filter'] ?? '';
    if (in_array($bf, ['unread', 'archived'], true)) $back .= '?filter=' . $bf;
    header("Location: $back");
    exit();
}

$flash = $_SESSION['msg_flash'] ?? null;
unset($_SESSION['msg_flash']);

include 'admin_header.php';

$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'unread', 'archived'], true)) $filter = 'all';
if ($filter === 'unread')        $where = "WHERE is_read = FALSE AND archived = FALSE";
elseif ($filter === 'archived')  $where = "WHERE archived = TRUE";
else                             $where = "WHERE archived = FALSE";

$limit = 10;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$total = (int) ($conn->query("SELECT COUNT(*) AS c FROM contact_messages $where")->fetch_assoc()['c'] ?? 0);
$total_pages = max(1, (int) ceil($total / $limit));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

$unread = contact_unread_count($conn);
$archived_count = (int) ($conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE archived = TRUE")->fetch_assoc()['c'] ?? 0);

$rows = $conn->query("SELECT * FROM contact_messages $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
?>

<style>
    .main-wrapper { max-width: 1000px; margin: 0 auto; padding: 40px 20px; width: 100%; flex: 1; }
    .msg-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; flex-wrap: wrap; gap: 14px; }
    .msg-top h2 { font-size: 26px; font-weight: 700; color: #222; }
    .msg-top p { color: #888; font-size: 14px; margin-top: 3px; }
    .msg-tabs { display: flex; gap: 8px; margin-bottom: 22px; }
    .msg-tab { padding: 8px 18px; border-radius: 50px; border: 1.5px solid #eee; background: #fff; color: #666; font-family: 'Poppins'; font-weight: 600; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 7px; }
    .msg-tab.active { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border-color: #FEA5B6; }
    .msg-tab .count { background: rgba(0,0,0,0.08); border-radius: 50px; padding: 1px 8px; font-size: 11px; }
    .msg-tab.active .count { background: rgba(255,255,255,0.3); }

    .msg-flash { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; padding: 13px 18px; border-radius: 14px; margin-bottom: 18px; font-size: 14px; }

    .msg-card { background: #fff; border: 1px solid #f0f0f0; border-radius: 20px; padding: 22px 24px; margin-bottom: 14px; box-shadow: 0 3px 14px rgba(0,0,0,0.03); }
    .msg-card.unread { border-color: #ffc9d8; box-shadow: 0 4px 18px rgba(255,139,167,0.12); }
    .msg-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; flex-wrap: wrap; margin-bottom: 10px; }
    .msg-from { font-size: 15px; font-weight: 700; color: #222; display: flex; align-items: center; gap: 8px; }
    .msg-dot { width: 8px; height: 8px; border-radius: 50%; background: #ff8ba7; flex-shrink: 0; }
    .msg-email { font-size: 13px; color: #888; }
    .msg-email a { color: #888; text-decoration: none; }
    .msg-email a:hover { color: #ff8ba7; }
    .msg-date { font-size: 12px; color: #aaa; white-space: nowrap; }
    .msg-subject { font-size: 13px; font-weight: 600; color: #ff8ba7; margin-bottom: 8px; }
    .msg-body { font-size: 14px; color: #555; line-height: 1.7; white-space: pre-wrap; word-break: break-word; background: #fafafa; border-radius: 12px; padding: 14px 16px; }
    .msg-actions { display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap; }
    .msg-btn { padding: 7px 16px; border-radius: 50px; border: none; font-size: 12.5px; font-weight: 600; cursor: pointer; font-family: 'Poppins'; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .msg-btn.reply { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; }
    .msg-btn.ghost { background: #f3f3f3; color: #555; }
    .msg-btn.del { background: #ffe4e4; color: #d32f2f; }

    .msg-empty { text-align: center; padding: 70px 20px; color: #999; }
    .msg-empty i { font-size: 48px; color: #ddd; display: block; margin-bottom: 14px; }

    .pagination-wrapper { display: flex; justify-content: center; gap: 8px; margin-top: 26px; flex-wrap: wrap; }
    .page-btn { padding: 8px 16px; border: 1.5px solid #eee; border-radius: 30px; background: #fff; color: #555; text-decoration: none; font-size: 14px; font-weight: 500; font-family: 'Poppins'; }
    .page-btn:hover { background: #ffc1cc; color: #fff; border-color: #ffc1cc; }
    .page-btn.active { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border-color: #FEA5B6; }
    .page-btn.disabled { opacity: 0.5; pointer-events: none; }
</style>

<div class="main-wrapper">
    <div class="msg-top">
        <div>
            <h2>📨 Contact Messages</h2>
            <p><?php echo $total; ?> message<?php echo $total === 1 ? '' : 's'; ?><?php echo $unread ? " · $unread unread" : ''; ?></p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <?php if ($unread > 0): ?>
            <form method="POST" style="margin:0;">
                <button type="submit" name="mark_all_read" value="1" class="msg-btn ghost" style="padding:9px 18px;">
                    <i class="fas fa-check-double"></i> Mark all read
                </button>
            </form>
            <?php endif; ?>
            <a href="admin_dashboard.php" style="background:#f3f3f3; padding:9px 18px; border-radius:50px; font-size:14px; font-weight:500; color:#555; text-decoration:none;">&larr; Dashboard</a>
        </div>
    </div>

    <?php if ($flash): ?><div class="msg-flash"><i class="fas fa-check-circle" style="margin-right:6px;"></i><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>

    <div class="msg-tabs">
        <a href="admin_messages.php" class="msg-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">Inbox</a>
        <a href="admin_messages.php?filter=unread" class="msg-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
            Unread <?php if ($unread): ?><span class="count"><?php echo $unread; ?></span><?php endif; ?>
        </a>
        <a href="admin_messages.php?filter=archived" class="msg-tab <?php echo $filter === 'archived' ? 'active' : ''; ?>">
            <i class="fas fa-box-archive"></i> Archived <?php if ($archived_count): ?><span class="count"><?php echo $archived_count; ?></span><?php endif; ?>
        </a>
    </div>

    <?php if ($rows && $rows->num_rows > 0): ?>
        <?php while ($m = $rows->fetch_assoc()):
            $is_read = ($m['is_read'] === true || $m['is_read'] === 't' || $m['is_read'] === '1' || $m['is_read'] === 1);
            $is_archived = ($m['archived'] === true || $m['archived'] === 't' || $m['archived'] === '1' || $m['archived'] === 1);
            $subj = trim($m['subject']) !== '' ? $m['subject'] : '(no subject)';
        ?>
        <div class="msg-card <?php echo ($is_read || $is_archived) ? '' : 'unread'; ?>">
            <div class="msg-head">
                <div>
                    <div class="msg-from">
                        <?php if (!$is_read): ?><span class="msg-dot"></span><?php endif; ?>
                        <?php echo htmlspecialchars($m['name']); ?>
                    </div>
                    <div class="msg-email"><a href="mailto:<?php echo htmlspecialchars($m['email']); ?>"><?php echo htmlspecialchars($m['email']); ?></a></div>
                </div>
                <div class="msg-date">
                    <?php echo contact_fmt_time($m['created_at']); ?>
                    <?php if ($is_archived): ?><br><span style="color:#bbb;font-size:11px;"><i class="fas fa-box-archive"></i> Archived</span><?php endif; ?>
                </div>
            </div>
            <div class="msg-subject"><?php echo htmlspecialchars($subj); ?></div>
            <div class="msg-body"><?php echo htmlspecialchars($m['message']); ?></div>
            <div class="msg-actions">
                <a class="msg-btn reply" href="mailto:<?php echo htmlspecialchars($m['email']); ?>?subject=<?php echo rawurlencode('Re: ' . $subj); ?>">
                    <i class="fas fa-reply"></i> Reply by email
                </a>
                <?php if (!$is_archived): ?>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="message_id" value="<?php echo (int) $m['id']; ?>">
                    <?php if ($is_read): ?>
                        <button type="submit" name="mark_unread" value="1" class="msg-btn ghost"><i class="fas fa-envelope"></i> Mark unread</button>
                    <?php else: ?>
                        <button type="submit" name="mark_read" value="1" class="msg-btn ghost"><i class="fas fa-envelope-open"></i> Mark read</button>
                    <?php endif; ?>
                </form>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="message_id" value="<?php echo (int) $m['id']; ?>">
                    <button type="submit" name="archive_message" value="1" class="msg-btn ghost"><i class="fas fa-box-archive"></i> Archive</button>
                </form>
                <?php else: ?>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="message_id" value="<?php echo (int) $m['id']; ?>">
                    <button type="submit" name="unarchive_message" value="1" class="msg-btn ghost"><i class="fas fa-inbox"></i> Restore to inbox</button>
                </form>
                <?php endif; ?>
                <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this message permanently?');">
                    <input type="hidden" name="message_id" value="<?php echo (int) $m['id']; ?>">
                    <button type="submit" name="delete_message" value="1" class="msg-btn del"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </div>
        <?php endwhile; ?>

        <?php if ($total_pages > 1):
            $qs = in_array($filter, ['unread', 'archived'], true) ? '&filter=' . $filter : '';
        ?>
        <div class="pagination-wrapper">
            <a href="admin_messages.php?page=<?php echo max(1, $page - 1) . $qs; ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">&larr; Prev</a>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="admin_messages.php?page=<?php echo $i . $qs; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="admin_messages.php?page=<?php echo min($total_pages, $page + 1) . $qs; ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next &rarr;</a>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="msg-empty">
            <i class="fas fa-inbox"></i>
            <p style="font-size:16px;"><?php echo $filter === 'unread' ? 'No unread messages.' : ($filter === 'archived' ? 'No archived messages.' : 'No messages yet.'); ?></p>
        </div>
    <?php endif; ?>
</div>

<?php include 'admin_footer.php'; ?>
