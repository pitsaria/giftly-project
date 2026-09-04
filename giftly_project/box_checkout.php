<?php
include 'db_connect.php';
include 'build_a_box_lib.php';
include_once 'orders_lib.php';
include_once 'paymongo_lib.php';
include_once 'address_lib.php';
bab_ensure_schema($conn);
orders_ensure_schema($conn);
pay_ensure_schema($conn);
addr_ensure_schema($conn);
$paymongo_on = paymongo_configured();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = intval($_SESSION['user_id']);

$box_id = 0;
if (isset($_GET['box_id'])) $box_id = intval($_GET['box_id']);
if (isset($_POST['box_id'])) $box_id = intval($_POST['box_id']);

/* =====================================================================
   PLACE ORDER
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    $conn->begin_transaction();
    try {
        // Lock box + items + product stock
        $bres = $conn->query("SELECT b.*, s.name AS size_name, s.price AS box_price
                              FROM boxes b JOIN box_sizes s ON s.id = b.box_size_id
                              WHERE b.id = $box_id AND b.user_id = $user_id FOR UPDATE");
        if (!$bres || $bres->num_rows === 0) throw new Exception('Box not found.');
        $box = $bres->fetch_assoc();
        if ($box['status'] === 'ordered') throw new Exception('This box has already been ordered.');

        $ires = $conn->query("SELECT bi.product_id, bi.quantity, p.name, p.price, p.quantity AS stock
                              FROM box_items bi JOIN products p ON p.id = bi.product_id
                              WHERE bi.box_id = $box_id FOR UPDATE");
        $items = [];
        $stock_errors = [];
        while ($ires && $r = $ires->fetch_assoc()) {
            $req = intval($r['quantity']);
            $av  = intval($r['stock']);
            if ($req > $av) {
                $stock_errors[] = $av <= 0
                    ? "{$r['name']} is out of stock."
                    : "{$r['name']}: only {$av} left (box needs {$req}).";
            } else {
                $items[] = $r;
            }
        }
        if (count($items) === 0) throw new Exception('Your box has no available items.');
        if (!empty($stock_errors)) {
            $conn->rollback();
            $_SESSION['box_stock_errors'] = $stock_errors;
            header("Location: box_checkout.php?box_id=$box_id&stock_error=1");
            exit();
        }

        $fullname       = mysqli_real_escape_string($conn, $_POST['fullname']);
        $sender_phone   = mysqli_real_escape_string($conn, $_POST['sender_phone']);
        $address        = mysqli_real_escape_string($conn, $_POST['address']);
        $city           = mysqli_real_escape_string($conn, $_POST['city']);
        $payment        = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $delivery_date  = mysqli_real_escape_string($conn, $_POST['delivery_date']);
        $delivery_time  = mysqli_real_escape_string($conn, $_POST['delivery_time']);
        $delivery_type  = isset($_POST['delivery_type']) ? $_POST['delivery_type'] : 'me';

        // Card payment: validate here, but only ever keep the last 4 digits + name.
        $card_last4 = '';
        $card_holder = '';
        if ($payment === 'card') {
            $digits = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
            if (strlen($digits) < 13 || strlen($digits) > 19) throw new Exception('Please enter a valid card number.');
            $card_holder_raw = trim($_POST['card_holder'] ?? '');
            if ($card_holder_raw === '') throw new Exception('Please enter the name on the card.');
            $exp = trim($_POST['card_expiry'] ?? '');
            if (!preg_match('#^(0[1-9]|1[0-2])\s*/\s*([0-9]{2})$#', $exp, $mm)) throw new Exception('Card expiry must be in MM/YY format.');
            $exp_y = 2000 + (int) $mm[2];
            $exp_m = (int) $mm[1];
            if ($exp_y < (int) date('Y') || ($exp_y === (int) date('Y') && $exp_m < (int) date('n'))) throw new Exception('That card has expired.');
            $cvc = preg_replace('/\D/', '', $_POST['card_cvc'] ?? '');
            if (strlen($cvc) < 3 || strlen($cvc) > 4) throw new Exception('Please enter a valid CVC.');
            $card_last4 = substr($digits, -4);
            $card_holder = mysqli_real_escape_string($conn, mb_substr($card_holder_raw, 0, 120));
        }

        // The box letter (with its card style) becomes the order's gift message
        $lr = $conn->query("SELECT letter, card_style FROM boxes WHERE id = $box_id AND user_id = $user_id");
        $lrow = $lr ? $lr->fetch_assoc() : [];
        $letter_txt = trim($lrow['letter'] ?? '');
        $styles = bab_card_styles();
        $skey = bab_card_style_key($lrow['card_style'] ?? 'simple');
        $gm = $letter_txt;
        if ($skey !== 'simple') {
            $hdr = $styles[$skey]['emoji'] . ' ' . $styles[$skey]['label'] . ' card';
            $gm = $letter_txt === '' ? $hdr : $hdr . "\n\n" . $letter_txt;
        }
        $gift_message = mysqli_real_escape_string($conn, $gm);

        if ($delivery_type === 'recipient') {
            $recipient       = isset($_POST['recipient_name']) ? mysqli_real_escape_string($conn, $_POST['recipient_name']) : '';
            $recipient_phone = isset($_POST['recipient_phone']) ? mysqli_real_escape_string($conn, $_POST['recipient_phone']) : '';
        } else {
            $recipient = ''; $recipient_phone = '';
        }

        $total_amount = 0;
        foreach ($items as $it) $total_amount += $it['price'] * $it['quantity'];
        $shipping_fee = ($total_amount > 0 && $total_amount < 300) ? 50 : 0;
        $grand_total  = $total_amount + $shipping_fee + floatval($box['box_price']);

        $sql = "INSERT INTO orders (user_id, total_amount, status, fullname, sender_phone, address, city,
                    recipient_name, recipient_phone, gift_message, payment_method, delivery_date, delivery_time,
                    card_last4, card_holder)
                VALUES ($user_id, $grand_total, 'pending', '$fullname', '$sender_phone', '$address', '$city',
                    '$recipient', '$recipient_phone', '$gift_message', '$payment', '$delivery_date', '$delivery_time',
                    " . ($card_last4 !== '' ? "'$card_last4'" : "NULL") . ", " . ($card_holder !== '' ? "'$card_holder'" : "NULL") . ")";
        if (!$conn->query($sql)) throw new Exception('Failed to create order: ' . $conn->error);
        $order_id = intval($conn->insert_id);
        if ($order_id <= 0) {
            $order_id = intval($conn->query("SELECT id FROM orders WHERE user_id = $user_id ORDER BY id DESC LIMIT 1")->fetch_assoc()['id']);
        }

        foreach ($items as $it) {
            $pid = intval($it['product_id']); $q = intval($it['quantity']); $pr = floatval($it['price']);
            $conn->query("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES ($order_id, $pid, $q, $pr)");
            $conn->query("UPDATE products SET quantity = quantity - $q WHERE id = $pid");
        }

        $conn->query("UPDATE boxes SET status = 'ordered', updated_at = CURRENT_TIMESTAMP WHERE id = $box_id AND user_id = $user_id");

        $conn->commit();

        // --- ONLINE PAYMENT: hand off to PayMongo's hosted checkout ---
        if ($paymongo_on && $payment === 'online') {
            $pay_url = paymongo_create_checkout(
                $conn, (int) $order_id, (float) $grand_total,
                $fullname, ($_SESSION['user_email'] ?? ''), $sender_phone
            );
            if ($pay_url !== '') {
                header("Location: " . $pay_url);
                exit();
            }
            // Couldn't open PayMongo — send the shopper somewhere that explains why.
            $_SESSION['pay_start_error'] = paymongo_last_error() ?: 'Payment could not be started.';
            header("Location: payment_return.php?order_id=" . (int) $order_id);
            exit();
        }

        $_SESSION['box_order_ok'] = [
            'order_id' => $order_id,
            'grand_total' => $grand_total,
            'payment' => $_POST['payment_method'],
            'delivery_date' => $_POST['delivery_date'],
            'delivery_time' => $_POST['delivery_time'],
            'address' => $_POST['address'] . ', ' . $_POST['city'],
            'recipient' => $recipient,
            'recipient_phone' => $recipient_phone,
            'size_name' => $box['size_name'],
        ];
        header("Location: box_checkout.php?success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Box order error: " . $e->getMessage());
        $_SESSION['box_checkout_error'] = $e->getMessage();
        header("Location: box_checkout.php?box_id=$box_id&err=1");
        exit();
    }
}

include 'header.php';

/* =====================================================================
   SUCCESS PAGE
   ===================================================================== */
if (isset($_GET['success']) && isset($_SESSION['box_order_ok'])) {
    $o = $_SESSION['box_order_ok'];
    unset($_SESSION['box_order_ok']);
    ?>
    <style>
        .success-wrapper { display:flex; justify-content:center; align-items:center; min-height:70vh; padding:130px 20px 60px; }
        .success-card { background:#fff; border-radius:40px; padding:50px 40px; max-width:550px; width:100%; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,0.04); }
        .success-badge { width:100px; height:100px; border-radius:50%; background:#e8f5e9; color:#2e7d32; display:flex; align-items:center; justify-content:center; font-size:50px; margin:0 auto 20px; }
        .success-title { font-size:28px; font-weight:700; color:#222; margin-bottom:5px; }
        .success-sub { font-size:16px; color:#888; margin-bottom:25px; }
        .success-details-box { background:#fafafa; border-radius:20px; padding:20px; margin-bottom:30px; text-align:left; }
        .success-detail { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0; font-size:15px; }
        .success-detail:last-child { border-bottom:none; }
        .success-detail span:first-child { color:#888; }
        .success-detail span:last-child { font-weight:600; color:#222; }
        .btn-continue { padding:14px 35px; border-radius:50px; background:linear-gradient(135deg,#ff8ba7 0%,#e6738f 100%); color:#fff; text-decoration:none; font-weight:600; display:inline-block; }
        .btn-orders { padding:14px 35px; border-radius:50px; background:#fff; color:#555; text-decoration:none; font-weight:600; border:2px solid #eee; display:inline-block; }
        .success-buttons { display:flex; gap:15px; justify-content:center; flex-wrap:wrap; }
    </style>
    <script>
        try {
            Object.keys(sessionStorage).forEach(function (k) {
                if (k.indexOf('boxCheckout_') === 0) sessionStorage.removeItem(k);
            });
        } catch (e) {}
    </script>
    <div class="success-wrapper">
        <div class="success-card">
            <div class="success-badge"><i class="fas fa-gift"></i></div>
            <div class="success-title">Box on its way! 🎁</div>
            <div class="success-sub">Your custom <?php echo htmlspecialchars($o['size_name']); ?> has been ordered.</div>
            <div class="success-details-box">
                <div class="success-detail"><span>Order ID</span><span>#<?php echo $o['order_id']; ?></span></div>
                <div class="success-detail"><span>Total Paid</span><span>PHP <?php echo number_format($o['grand_total'], 2); ?></span></div>
                <div class="success-detail"><span>Payment</span><span><?php echo htmlspecialchars(ucfirst($o['payment'])); ?></span></div>
                <div class="success-detail"><span>Delivery Date</span><span><?php echo date('F j, Y', strtotime($o['delivery_date'])); ?></span></div>
                <div class="success-detail"><span>Delivery Time</span><span><?php echo date('g:i A', strtotime($o['delivery_time'])); ?></span></div>
                <div class="success-detail"><span>Address</span><span><?php echo htmlspecialchars($o['address']); ?></span></div>
                <?php if ($o['recipient']): ?>
                <div class="success-detail"><span>Recipient</span><span><?php echo htmlspecialchars($o['recipient']); ?></span></div>
                <?php endif; ?>
            </div>
            <div class="success-buttons">
                <a href="profile.php?tab=orders" class="btn-orders"><i class="fas fa-box"></i> View My Orders</a>
                <a href="build-a-box.php" class="btn-continue"><i class="fas fa-gift"></i> Build Another</a>
            </div>
        </div>
    </div>
    <?php
    include 'footer.php';
    exit();
}

/* =====================================================================
   CHECKOUT FORM
   ===================================================================== */
$data = bab_load_box($conn, $box_id, $user_id);
if (!$data || count($data['items']) === 0) {
    echo "<div style='max-width:600px;margin:160px auto;text-align:center;color:#888;'>
            <p style='font-size:18px;'>This box is empty or unavailable.</p>
            <a href='build-a-box.php' style='color:#ff8ba7;font-weight:600;'>Build a box</a>
          </div>";
    include 'footer.php';
    exit();
}
if ($data['box']['status'] === 'ordered') {
    echo "<div style='max-width:600px;margin:160px auto;text-align:center;color:#888;'>
            <p style='font-size:18px;'>This box has already been ordered.</p>
            <a href='profile.php?tab=orders' style='color:#ff8ba7;font-weight:600;'>View my orders</a>
          </div>";
    include 'footer.php';
    exit();
}

$blocked = count($data['issues']) > 0;

$user_row = $conn->query("SELECT name, phone FROM users WHERE id = $user_id")->fetch_assoc();
$addresses_query = $conn->query("SELECT * FROM addresses WHERE user_id = $user_id ORDER BY is_default DESC, id DESC");

$subtotal = $data['subtotal'];
$box_price = floatval($data['box']['box_price']);
$shipping_fee = ($subtotal > 0 && $subtotal < 300) ? 50 : 0;
$grand = $subtotal + $shipping_fee + $box_price;

$stock_errors = $_SESSION['box_stock_errors'] ?? [];
unset($_SESSION['box_stock_errors']);
$checkout_error = $_SESSION['box_checkout_error'] ?? '';
unset($_SESSION['box_checkout_error']);
?>
<style>
    .co-wrap { max-width: 1050px; margin: 0 auto; padding: 120px 20px 60px; display: flex; gap: 38px; flex-wrap: wrap; }
    .co-left { flex: 1; min-width: 320px; }
    .co-right { width: 360px; flex-shrink: 0; }
    .co-title { font-size: 26px; font-weight: 700; color: #222; margin-bottom: 26px; }
    .co-sec { margin-bottom: 26px; }
    .co-sec h3 { font-size: 16px; font-weight: 600; color: #444; margin-bottom: 14px; }
    .co-row { display: flex; gap: 18px; margin-bottom: 14px; flex-wrap: wrap; }
    .co-grp { flex: 1; min-width: 150px; display: flex; flex-direction: column; }
    .co-grp label { font-size: 13px; font-weight: 600; color: #666; margin-bottom: 5px; }
    .co-input { width: 100%; padding: 12px 15px; border: 1.5px solid #eee; border-radius: 12px; font-family: 'Poppins'; font-size: 14px; outline: none; background: #fff; }
    .co-input:focus { border-color: #ffc1cc; box-shadow: 0 0 0 4px rgba(255,193,204,0.12); }
    .co-delivery { display: flex; gap: 14px; margin-bottom: 14px; flex-wrap: wrap; }
    .co-opt { flex: 1; min-width: 130px; text-align: center; padding: 12px; border: 2px solid #eee; border-radius: 12px; cursor: pointer; font-weight: 500; font-size: 14px; color: #555; transition: 0.2s; }
    .co-opt.sel { border-color: #ff8ba7; background: #fff0f5; color: #d32f2f; }
    .co-opt input { display: none; }
    #recipientBox { display: none; }
    #recipientBox.show { display: block; }
    .co-card { background: #fff; border-radius: 24px; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,0.04); border: 1px solid #f5f5f5; position: sticky; top: 110px; }
    .co-card h4 { font-size: 18px; font-weight: 700; color: #222; margin-bottom: 16px; }
    .co-item { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; align-items: center; }
    .co-item img { width: 46px; height: 46px; object-fit: contain; background: #fafafa; border-radius: 10px; padding: 4px; }
    .co-item .nm { flex: 1; font-size: 13px; font-weight: 600; color: #333; }
    .co-item .nm small { display: block; font-weight: 400; color: #999; }
    .co-tot { margin-top: 16px; }
    .co-tot .r { display: flex; justify-content: space-between; font-size: 14px; color: #555; margin-bottom: 7px; }
    .co-tot .r.g { border-top: 1px solid #f0f0f0; padding-top: 12px; font-size: 19px; font-weight: 700; color: #222; }
    .co-btn { width: 100%; padding: 15px; border: none; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 16px; transition: 0.2s; }
    .co-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254,165,182,0.4); }
    .co-btn:disabled { background: #ccc; cursor: not-allowed; transform: none; }
    .co-edit-box { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; margin-top: 12px; padding: 12px; border: 2px solid #ffc1cc; border-radius: 50px; color: #ff8ba7; font-weight: 600; font-size: 14px; text-decoration: none; transition: 0.2s; background: #fff; }
    .co-edit-box:hover { background: #fff0f5; transform: translateY(-2px); }
    #cardFields { display: none; margin-top: 14px; padding: 16px; border: 1.5px dashed #ffc1cc; border-radius: 14px; background: #fff8fa; }
    #cardFields.show { display: block; }
    #cardFields .hint { font-size: 12px; color: #999; margin-top: 4px; }
    .co-letter { background: #fff5f7; border-left: 3px solid #ff8ba7; border-radius: 12px; padding: 12px 16px; font-style: italic; color: #555; font-size: 13px; white-space: pre-wrap; margin-top: 8px; }
    .co-alert { background: #fdeded; border: 1px solid #ffc1cc; color: #d32f2f; padding: 14px 18px; border-radius: 14px; margin-bottom: 20px; font-size: 14px; }
    @media (max-width: 880px) { .co-right { width: 100%; } .co-card { position: static; } }
</style>

<div class="co-wrap">
    <div class="co-left">
        <h1 class="co-title">Checkout — <?php echo htmlspecialchars($data['box']['size_name']); ?></h1>

        <?php if ($checkout_error): ?>
            <div class="co-alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($checkout_error); ?></div>
        <?php endif; ?>
        <?php if (!empty($stock_errors)): ?>
            <div class="co-alert">
                <strong>Stock changed:</strong>
                <ul style="margin:6px 0 0 18px;">
                    <?php foreach ($stock_errors as $se): ?><li><?php echo htmlspecialchars($se); ?></li><?php endforeach; ?>
                </ul>
                <a href="build-a-box.php?box_id=<?php echo $box_id; ?>" style="color:#d32f2f;font-weight:600;">Edit box</a>
            </div>
        <?php endif; ?>
        <?php if ($blocked): ?>
            <div class="co-alert">
                Some items in this box aren't available right now.
                <a href="build-a-box.php?box_id=<?php echo $box_id; ?>" style="color:#d32f2f;font-weight:600;">Edit your box</a> before checking out.
            </div>
        <?php endif; ?>

        <form id="boxOrderForm" action="box_checkout.php" method="POST">
            <input type="hidden" name="box_id" value="<?php echo $box_id; ?>">
            <input type="hidden" name="place_order" value="1">

            <div class="co-sec">
                <h3>1. Contact Information</h3>
                <div class="co-row">
                    <div class="co-grp">
                        <label>Full Name</label>
                        <input type="text" name="fullname" class="co-input" value="<?php echo htmlspecialchars($user_row['name'] ?? ''); ?>" required>
                    </div>
                    <div class="co-grp">
                        <label>Phone Number</label>
                        <input type="tel" name="sender_phone" class="co-input" value="<?php echo htmlspecialchars($user_row['phone'] ?? ''); ?>" placeholder="09XXXXXXXXX" pattern="[0-9]{11}" required>
                    </div>
                </div>
            </div>

            <div class="co-sec">
                <h3>2. Delivery</h3>
                <div class="co-delivery">
                    <label class="co-opt sel" id="optMe" onclick="coDelivery('me')">
                        <input type="radio" name="delivery_type" value="me" checked>
                        <i class="fas fa-home" style="display:block;margin-bottom:4px;"></i> Deliver to Me
                    </label>
                    <label class="co-opt" id="optRec" onclick="coDelivery('recipient')">
                        <input type="radio" name="delivery_type" value="recipient">
                        <i class="fas fa-user-friends" style="display:block;margin-bottom:4px;"></i> Deliver to Recipient
                    </label>
                </div>
                <div id="recipientBox">
                    <div class="co-row">
                        <div class="co-grp">
                            <label>Recipient's Name</label>
                            <input type="text" name="recipient_name" class="co-input" placeholder="Who is this gift for?">
                        </div>
                        <div class="co-grp">
                            <label>Recipient's Phone</label>
                            <input type="tel" name="recipient_phone" class="co-input" placeholder="09XXXXXXXXX" pattern="[0-9]{11}">
                        </div>
                    </div>
                </div>

                <?php if ($addresses_query && $addresses_query->num_rows > 0): ?>
                <div class="co-row">
                    <div class="co-grp">
                        <label>Saved Address</label>
                        <select id="savedAddr" class="co-input" onchange="coFillAddr()">
                            <option value="">Choose a saved address</option>
                            <?php while ($a = $addresses_query->fetch_assoc()):
                                $a_def = addr_is_default($a['is_default']);
                            ?>
                                <option value="<?php echo $a['id']; ?>"
                                    <?php echo $a_def ? 'data-default="1"' : ''; ?>
                                    data-address="<?php echo htmlspecialchars($a['address']); ?>"
                                    data-city="<?php echo htmlspecialchars($a['city'] . ', ' . $a['province']); ?>">
                                    <?php echo htmlspecialchars(($a['label'] ?: 'Address') . ' — ' . $a['address'] . ', ' . $a['city']) . ($a_def ? ' (default)' : ''); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <div class="co-row">
                    <div class="co-grp">
                        <label>Street Address</label>
                        <input type="text" name="address" id="coAddr" class="co-input" placeholder="Street address" required>
                    </div>
                    <div class="co-grp">
                        <label>City / Province</label>
                        <input type="text" name="city" id="coCity" class="co-input" placeholder="City, Province" required>
                    </div>
                </div>
                <div class="co-row">
                    <div class="co-grp">
                        <label>Delivery Date</label>
                        <input type="date" name="delivery_date" id="coDate" class="co-input" required>
                        <div style="font-size:12px;color:#888;margin-top:4px;">Allow at least 3 days for delivery.</div>
                    </div>
                    <div class="co-grp">
                        <label>Delivery Time</label>
                        <input type="time" name="delivery_time" id="coTime" class="co-input" min="08:00" max="20:00" required>
                        <div style="font-size:12px;color:#888;margin-top:4px;">Between 8:00 AM and 8:00 PM.</div>
                    </div>
                </div>
            </div>

            <div class="co-sec">
                <h3>3. Payment</h3>
                <input type="hidden" name="payment_method" id="payInput" value="cod">
                <div class="co-delivery">
                    <label class="co-opt sel" id="payCod" onclick="coPay('cod')"><i class="fas fa-money-bill-wave" style="display:block;margin-bottom:4px;"></i> Cash on Delivery</label>
<?php if ($paymongo_on): ?>
                    <label class="co-opt" id="payCard" onclick="coPay('online')"><i class="fas fa-credit-card" style="display:block;margin-bottom:4px;"></i> Pay Online <span style="display:block;font-size:11px;color:#999;">Card · GCash · Maya</span></label>
                </div>
                <div id="onlinePayNote" style="display:none;">
                    <div class="hint" style="margin-top:12px;"><i class="fas fa-lock" style="color:#ff8ba7;"></i> You'll be sent to PayMongo's secure page to pay by card, GCash or Maya, then brought back here.</div>
                </div>
<?php else: ?>
                    <label class="co-opt" id="payCard" onclick="coPay('card')"><i class="fas fa-credit-card" style="display:block;margin-bottom:4px;"></i> Credit / Debit Card</label>
                </div>

                <div id="cardFields">
                    <div class="co-row">
                        <div class="co-grp">
                            <label>Name on Card</label>
                            <input type="text" name="card_holder" id="cardHolder" class="co-input" autocomplete="cc-name" placeholder="e.g. Juan Dela Cruz">
                        </div>
                    </div>
                    <div class="co-row">
                        <div class="co-grp">
                            <label>Card Number</label>
                            <input type="text" name="card_number" id="cardNumber" class="co-input" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" maxlength="23">
                        </div>
                    </div>
                    <div class="co-row">
                        <div class="co-grp">
                            <label>Expiry (MM/YY)</label>
                            <input type="text" name="card_expiry" id="cardExpiry" class="co-input" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/YY" maxlength="5">
                        </div>
                        <div class="co-grp">
                            <label>CVC</label>
                            <input type="text" name="card_cvc" id="cardCvc" class="co-input" inputmode="numeric" autocomplete="cc-csc" placeholder="123" maxlength="4">
                        </div>
                    </div>
                    <div class="hint"><i class="fas fa-lock"></i> Demo checkout — only the last 4 digits are kept with your order.</div>
                </div>
<?php endif; ?>
            </div>
        </form>
    </div>

    <div class="co-right">
        <div class="co-card">
            <h4>Your Box</h4>
            <?php foreach ($data['items'] as $it): ?>
                <div class="co-item">
                    <img src="<?php echo htmlspecialchars(img_url($it['image'])); ?>" alt="">
                    <div class="nm"><?php echo htmlspecialchars($it['name']); ?>
                        <small>x<?php echo $it['quantity']; ?> · PHP <?php echo number_format($it['price'], 2); ?></small>
                    </div>
                    <div style="font-weight:700;font-size:13px;">PHP <?php echo number_format($it['price'] * $it['quantity'], 2); ?></div>
                </div>
            <?php endforeach; ?>

            <?php
            $co_styles = bab_card_styles();
            $co_skey = bab_card_style_key($data['box']['card_style'] ?? 'simple');
            $co_has_letter = trim($data['box']['letter']) !== '';
            if ($co_has_letter || $co_skey !== 'simple'): ?>
                <div style="font-size:12px;color:#888;margin-top:12px;font-weight:600;">
                    <i class="fas fa-heart" style="color:#ff8ba7;"></i>
                    <?php echo $co_styles[$co_skey]['emoji'] . ' ' . htmlspecialchars($co_styles[$co_skey]['label']); ?> card
                </div>
                <?php if ($co_has_letter): ?>
                    <div class="co-letter"><?php echo htmlspecialchars($data['box']['letter']); ?></div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="co-tot">
                <div class="r"><span>Subtotal (<?php echo $data['item_count']; ?> items)</span><span>PHP <?php echo number_format($subtotal, 2); ?></span></div>
                <?php if ($box_price > 0): ?>
                <div class="r"><span>Box</span><span>PHP <?php echo number_format($box_price, 2); ?></span></div>
                <?php endif; ?>
                <div class="r"><span>Shipping</span><span><?php echo $shipping_fee == 0 ? 'FREE' : 'PHP ' . number_format($shipping_fee, 2); ?></span></div>
                <div class="r g"><span>Total</span><span>PHP <?php echo number_format($grand, 2); ?></span></div>
            </div>

            <button type="submit" form="boxOrderForm" class="co-btn" <?php echo $blocked ? 'disabled' : ''; ?>>
                <i class="fas fa-lock"></i> Place Order
            </button>
            <a href="build-a-box.php?box_id=<?php echo $box_id; ?>" class="co-edit-box">
                <i class="fas fa-pen-to-square"></i> Edit this box
            </a>
        </div>
    </div>
</div>

<script>
    function coDelivery(t) {
        document.getElementById('optMe').classList.toggle('sel', t === 'me');
        document.getElementById('optRec').classList.toggle('sel', t === 'recipient');
        document.querySelector('input[name="delivery_type"][value="' + t + '"]').checked = true;
        document.getElementById('recipientBox').classList.toggle('show', t === 'recipient');
        const rn = document.querySelector('input[name="recipient_name"]');
        const rp = document.querySelector('input[name="recipient_phone"]');
        if (rn) rn.required = (t === 'recipient');
        if (rp) rp.required = (t === 'recipient');
    }
    function coPay(p) {
        document.getElementById('payCod').classList.toggle('sel', p === 'cod');
        document.getElementById('payCard').classList.toggle('sel', p !== 'cod');
        document.getElementById('payInput').value = p;
        const note = document.getElementById('onlinePayNote');
        if (note) note.style.display = (p === 'online') ? 'block' : 'none';
        const cf = document.getElementById('cardFields');
        if (cf) {
            cf.classList.toggle('show', p === 'card');
            ['cardHolder', 'cardNumber', 'cardExpiry', 'cardCvc'].forEach(function (id) {
                const el = document.getElementById(id);
                if (el) el.required = (p === 'card');
            });
        }
    }
    function coFillAddr() {
        const o = document.getElementById('savedAddr').selectedOptions[0];
        if (!o || !o.value) return;
        document.getElementById('coAddr').value = o.dataset.address || '';
        document.getElementById('coCity').value = o.dataset.city || '';
    }
    /* preselect the default saved address */
    (function () {
        const sel = document.getElementById('savedAddr');
        if (!sel) return;
        const def = sel.querySelector('option[data-default="1"]');
        if (def && !document.getElementById('coAddr').value) {
            sel.value = def.value;
            coFillAddr();
        }
    })();
    (function () {
        const d = new Date();
        d.setDate(d.getDate() + 3);
        document.getElementById('coDate').min = d.toISOString().split('T')[0];
    })();

    /* --- card field formatting --- */
    (function () {
        const num = document.getElementById('cardNumber');
        const exp = document.getElementById('cardExpiry');
        const cvc = document.getElementById('cardCvc');
        if (num) num.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 19);
            this.value = v.replace(/(.{4})/g, '$1 ').trim();
        });
        if (exp) exp.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 4);
            this.value = v.length > 2 ? v.slice(0, 2) + '/' + v.slice(2) : v;
        });
        if (cvc) cvc.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });
    })();

    document.getElementById('boxOrderForm').addEventListener('submit', function (e) {
        const t = document.getElementById('coTime').value;
        if (t && (t < '08:00' || t > '20:00')) {
            e.preventDefault();
            alert('Delivery time must be between 8:00 AM and 8:00 PM.');
            return;
        }
        if (document.getElementById('payInput').value === 'card') {
            const digits = (document.getElementById('cardNumber').value || '').replace(/\D/g, '');
            const expv = (document.getElementById('cardExpiry').value || '').trim();
            const cvcv = (document.getElementById('cardCvc').value || '').replace(/\D/g, '');
            const holder = (document.getElementById('cardHolder').value || '').trim();
            if (!holder) { e.preventDefault(); alert('Please enter the name on the card.'); return; }
            if (digits.length < 13 || digits.length > 19) { e.preventDefault(); alert('Please enter a valid card number.'); return; }
            if (!/^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(expv)) { e.preventDefault(); alert('Card expiry must be in MM/YY format.'); return; }
            if (cvcv.length < 3 || cvcv.length > 4) { e.preventDefault(); alert('Please enter a valid CVC.'); return; }
        }
        if (window.__clearBoxCheckout) window.__clearBoxCheckout();
    });

    /* --- keep what the customer typed if they pop back to edit the box --- */
    (function () {
        const form = document.getElementById('boxOrderForm');
        if (!form) return;
        const KEY = 'boxCheckout_<?php echo (int) $box_id; ?>';
        const fields = ['fullname', 'sender_phone', 'delivery_type', 'recipient_name', 'recipient_phone',
                        'address', 'city', 'delivery_date', 'delivery_time', 'payment_method', 'card_holder', 'card_expiry'];

        function collect() {
            const data = {};
            fields.forEach(function (name) {
                const el = form.elements[name];
                if (!el) return;
                if (el.length && el[0] && el[0].type === 'radio') {
                    const checked = form.querySelector('input[name="' + name + '"]:checked');
                    data[name] = checked ? checked.value : '';
                } else {
                    data[name] = el.value;
                }
            });
            return data;
        }
        function save() { try { sessionStorage.setItem(KEY, JSON.stringify(collect())); } catch (e) {} }

        try {
            const saved = JSON.parse(sessionStorage.getItem(KEY) || '{}');
            if (saved && Object.keys(saved).length) {
                Object.keys(saved).forEach(function (name) {
                    if (!saved[name]) return;
                    const el = form.elements[name];
                    if (!el) return;
                    if (el.length && el[0] && el[0].type === 'radio') {
                        const radio = form.querySelector('input[name="' + name + '"][value="' + saved[name] + '"]');
                        if (radio) radio.checked = true;
                    } else {
                        el.value = saved[name];
                    }
                });
                if (saved.delivery_type) coDelivery(saved.delivery_type);
                if (saved.payment_method) coPay(saved.payment_method);
            }
        } catch (e) {}

        form.addEventListener('input', save);
        form.addEventListener('change', save);
        window.__clearBoxCheckout = function () { try { sessionStorage.removeItem(KEY); } catch (e) {} };
    })();
</script>

<?php include 'footer.php'; ?>
