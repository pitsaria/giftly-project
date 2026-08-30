<?php
include 'db_connect.php';
include 'paymongo_lib.php';
pay_ensure_schema($conn);

$order_id  = (int) ($_GET['order_id'] ?? 0);
$cancelled = isset($_GET['cancel']);
$try       = max(0, (int) ($_GET['n'] ?? 0));
$user_id   = (int) ($_SESSION['user_id'] ?? 0);

$order = null;
if ($order_id > 0 && $user_id > 0) {
    $r = $conn->query("SELECT * FROM orders WHERE id = $order_id AND user_id = $user_id");
    $order = $r ? $r->fetch_assoc() : null;
}

$paid = $order && $order['payment_status'] === 'paid';
$failed = $order && $order['payment_status'] === 'failed';

// Keep re-checking for a few seconds while the webhook lands.
$polling = $order && !$paid && !$failed && !$cancelled && $try < 6;

include 'header.php';
?>
<?php if ($polling): ?>
<script>setTimeout(function(){ location.href = 'payment_return.php?order_id=<?php echo $order_id; ?>&n=<?php echo $try + 1; ?>'; }, 3000);</script>
<?php endif; ?>
<style>
    .pr-wrap { display:flex; justify-content:center; align-items:center; min-height:75vh; padding:130px 20px 60px; }
    .pr-card { background:#fff; border-radius:36px; padding:46px 40px; max-width:520px; width:100%; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,0.05); }
    .pr-badge { width:92px; height:92px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:44px; margin:0 auto 20px; }
    .pr-badge.ok { background:#e8f5e9; color:#2e7d32; }
    .pr-badge.wait { background:#fff8e1; color:#f9a825; }
    .pr-badge.bad { background:#fdeded; color:#d32f2f; }
    .pr-card h1 { font-size:25px; font-weight:700; color:#222; margin-bottom:8px; }
    .pr-card p { font-size:15px; color:#888; line-height:1.6; margin-bottom:24px; }
    .pr-row { display:flex; justify-content:space-between; font-size:14px; padding:7px 0; border-bottom:1px solid #f2f2f2; }
    .pr-row:last-child { border-bottom:none; }
    .pr-row span:first-child { color:#999; }
    .pr-row span:last-child { font-weight:600; color:#222; }
    .pr-box { background:#fafafa; border-radius:18px; padding:16px 20px; margin-bottom:26px; text-align:left; }
    .pr-btns { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
    .pr-btns a { padding:13px 30px; border-radius:50px; font-weight:600; font-size:15px; text-decoration:none; }
    .pr-btns .primary { background:linear-gradient(135deg,#ff8ba7 0%,#e6738f 100%); color:#fff; }
    .pr-btns .ghost { background:#fff; color:#555; border:2px solid #eee; }
    .pr-spin { display:inline-block; width:16px; height:16px; border:3px solid #f1d38b; border-top-color:transparent; border-radius:50%; animation:prspin 0.8s linear infinite; vertical-align:-3px; margin-right:6px; }
    @keyframes prspin { to { transform:rotate(360deg); } }
</style>

<div class="pr-wrap">
    <div class="pr-card">
        <?php if (!$order): ?>
            <div class="pr-badge bad"><i class="fas fa-circle-question"></i></div>
            <h1>Order not found</h1>
            <p>We couldn't find that order on your account.</p>
            <div class="pr-btns"><a href="shop.php" class="primary">Back to shop</a></div>

        <?php elseif ($cancelled && !$paid): ?>
            <div class="pr-badge bad"><i class="fas fa-xmark"></i></div>
            <h1>Payment cancelled</h1>
            <p>You closed the payment before it completed. Your order <strong>#<?php echo $order_id; ?></strong> is still saved but unpaid — you can pay for it from your orders.</p>
            <div class="pr-btns">
                <a href="profile.php?tab=orders" class="ghost">My Orders</a>
                <a href="cart.php" class="primary">Back to Cart</a>
            </div>

        <?php elseif ($paid): ?>
            <script>try{Object.keys(sessionStorage).forEach(function(k){if(k.indexOf('boxCheckout_')===0)sessionStorage.removeItem(k);});}catch(e){}</script>
            <div class="pr-badge ok"><i class="fas fa-check"></i></div>
            <h1>Payment received! 🎉</h1>
            <p>Thank you! Your payment for order <strong>#<?php echo $order_id; ?></strong> went through and we're getting it ready.</p>
            <div class="pr-box">
                <div class="pr-row"><span>Order</span><span>#<?php echo $order_id; ?></span></div>
                <div class="pr-row"><span>Amount</span><span>PHP <?php echo number_format((float) $order['total_amount'], 2); ?></span></div>
                <div class="pr-row"><span>Method</span><span><?php echo htmlspecialchars(ucfirst($order['payment_method'])); ?></span></div>
                <div class="pr-row"><span>Status</span><span style="color:#2e7d32;">Paid</span></div>
            </div>
            <div class="pr-btns">
                <a href="profile.php?tab=orders" class="ghost">View My Orders</a>
                <a href="shop.php" class="primary">Continue Shopping</a>
            </div>

        <?php elseif ($failed): ?>
            <div class="pr-badge bad"><i class="fas fa-xmark"></i></div>
            <h1>Payment didn't go through</h1>
            <p>Order <strong>#<?php echo $order_id; ?></strong> was cancelled and any reserved stock was released. You can place the order again.</p>
            <div class="pr-btns"><a href="cart.php" class="primary">Back to Cart</a></div>

        <?php else: ?>
            <div class="pr-badge wait"><i class="fas fa-hourglass-half"></i></div>
            <h1><span class="pr-spin"></span>Confirming your payment…</h1>
            <p>
                <?php if ($polling): ?>
                    This usually takes a few seconds. This page will refresh automatically.
                <?php else: ?>
                    It's taking a little longer than usual. Your payment may still complete — check <strong>My Orders</strong> in a minute.
                <?php endif; ?>
            </p>
            <div class="pr-btns">
                <a href="payment_return.php?order_id=<?php echo $order_id; ?>&n=0" class="ghost">Check again</a>
                <a href="profile.php?tab=orders" class="primary">My Orders</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
