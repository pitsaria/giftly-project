<?php
/** Admin-only email diagnostic. Sends a test email and/or resends an order email. */
include 'db_connect.php';
include_once 'mail_lib.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
$uid = (int) $_SESSION['user_id'];
$ur  = $conn->query("SELECT role, email FROM users WHERE id = $uid");
$me  = $ur ? $ur->fetch_assoc() : null;
if (!$me || $me['role'] !== 'admin') { header('Location: shop.php'); exit(); }

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_test'])) {
        $to = trim($_POST['to'] ?? '') ?: $me['email'];
        $ok = mail_send($to, 'Giftly test email', mail_wrap('It works ✅',
            '<p style="color:#555;font-size:14px;">If you got this, Brevo is wired up correctly.</p>'));
        $result = $ok
            ? ['ok', "Sent to $to. Check the inbox (and spam) and Brevo → Transactional → Logs."]
            : ['err', 'Failed: ' . (mail_last_error() ?: 'unknown')];
    } elseif (isset($_POST['resend_order'])) {
        $oid = (int) ($_POST['order_id'] ?? 0);
        $r = $conn->query("SELECT id, payment_method, payment_status FROM orders WHERE id = $oid");
        $ord = $r ? $r->fetch_assoc() : null;
        if (!$ord) {
            $result = ['err', "Order #$oid not found."];
        } else {
            $paid = ($ord['payment_status'] ?? '') === 'paid';
            $ok = send_order_email($conn, $oid, $paid);
            $result = $ok
                ? ['ok', "Order email for #$oid sent."]
                : ['err', 'Failed: ' . (mail_last_error() ?: 'unknown')];
        }
    }
}

include 'admin_header.php';
?>
<div class="main-wrapper" style="max-width:640px;margin:0 auto;padding:40px 20px;">
    <h2 style="font-size:24px;font-weight:700;color:#222;margin-bottom:6px;">Email diagnostic</h2>
    <p style="color:#888;font-size:14px;margin-bottom:20px;">
        Provider: Brevo &middot; key <?php echo mail_configured() ? 'set ✓' : '<span style="color:#d32f2f;">NOT set</span>'; ?>
        &middot; from: <code><?php echo htmlspecialchars(getenv('MAIL_FROM') ?: '(MAIL_FROM not set)'); ?></code>
    </p>

    <?php if ($result): ?>
        <div style="padding:14px 16px;border-radius:12px;margin-bottom:20px;font-size:14px;<?php
            echo $result[0] === 'ok' ? 'background:#e8f5e9;color:#2e7d32;' : 'background:#fdeded;color:#d32f2f;'; ?>">
            <?php echo htmlspecialchars($result[1]); ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="background:#fff;border-radius:16px;padding:22px;box-shadow:0 4px 16px rgba(0,0,0,0.04);margin-bottom:18px;">
        <label style="font-weight:600;font-size:13px;color:#555;display:block;margin-bottom:6px;">Send a test email to</label>
        <input type="email" name="to" placeholder="<?php echo htmlspecialchars($me['email']); ?>"
               style="width:100%;padding:11px 13px;border:1.5px solid #eee;border-radius:10px;font-family:'Poppins';margin-bottom:12px;">
        <button type="submit" name="send_test" value="1"
                style="padding:11px 22px;border:none;border-radius:50px;background:linear-gradient(135deg,#FEA5B6,#ff8ba7);color:#fff;font-weight:600;cursor:pointer;font-family:'Poppins';">Send test</button>
    </form>

    <form method="POST" style="background:#fff;border-radius:16px;padding:22px;box-shadow:0 4px 16px rgba(0,0,0,0.04);">
        <label style="font-weight:600;font-size:13px;color:#555;display:block;margin-bottom:6px;">Re-send the confirmation email for order #</label>
        <input type="number" name="order_id" placeholder="e.g. 42"
               style="width:100%;padding:11px 13px;border:1.5px solid #eee;border-radius:10px;font-family:'Poppins';margin-bottom:12px;">
        <button type="submit" name="resend_order" value="1"
                style="padding:11px 22px;border:none;border-radius:50px;background:#f3f3f3;color:#555;font-weight:600;cursor:pointer;font-family:'Poppins';">Re-send order email</button>
    </form>
</div>
<?php include 'admin_footer.php'; ?>
