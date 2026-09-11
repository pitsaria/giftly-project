<?php
include 'db_connect.php';
include_once 'orders_lib.php';
include_once 'paymongo_lib.php';
include_once 'address_lib.php';
include_once 'mail_lib.php';
include_once 'catalog_lib.php';
include_once 'promo_lib.php';
include_once 'addons_lib.php';
orders_ensure_schema($conn);
pay_ensure_schema($conn);
addr_ensure_schema($conn);
catalog_ensure_schema($conn);
promo_ensure_schema($conn);
addons_ensure_schema($conn);
$paymongo_on = paymongo_configured();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

include 'header.php';

// --- FETCH USER'S SAVED PHONE NUMBER ---
$user_data = $conn->query("SELECT phone FROM users WHERE id = $user_id");
$user_phone = '';
if($user_data->num_rows > 0) {
    $row = $user_data->fetch_assoc();
    $user_phone = $row['phone'];
}

// --- "Send a gift to X" / "Send again" pre-fill, set by gift_start.php / gift_send_again.php ---
$gift_ctx = $_SESSION['gift_context'] ?? null;

// --- Saved people (My Relations), for the "Saved Person" quick-fill dropdown ---
$saved_recipients = recip_list_for_user($conn, $user_id);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    
    $selected_ids = explode(',', $_POST['selected_ids_hidden']);
    if(empty($selected_ids)) {
        header("Location: cart.php");
        exit();
    }

    // 🚨 CRITICAL: VALIDATE STOCK BEFORE PROCESSING ORDER
    $ids_string = implode(',', array_map('intval', $selected_ids));
    
    // Check current stock for all selected items
    $stock_check = $conn->query("SELECT c.id as cart_id, c.quantity as requested, p.id as product_id, p.name, p.quantity as available_stock, p.is_active
                                FROM carts c
                                JOIN products p ON c.product_id = p.id
                                WHERE c.user_id = $user_id AND c.id IN ($ids_string)");

    $has_stock_issues = false;
    $stock_errors = [];
    $items_to_update = [];

    while ($row = $stock_check->fetch_assoc()) {
        $cart_id = $row['cart_id'];
        $requested = intval($row['requested']);
        $available = intval($row['available_stock']);
        $product_name = $row['name'];

        // Pulled from sale while it sat in the cart — block, keep it in the cart.
        if (!catalog_is_active($row['is_active'] ?? true)) {
            $has_stock_issues = true;
            $stock_errors[] = "{$product_name} is no longer available and can't be checked out. Please remove it from your cart.";
            continue;
        }

        if ($requested > $available) {
            $has_stock_issues = true;
            if ($available <= 0) {
                $stock_errors[] = "{$product_name} is out of stock and has been removed from your cart.";
                $items_to_update[] = "DELETE FROM carts WHERE id = $cart_id AND user_id = $user_id";
            } else {
                $stock_errors[] = "{$product_name}: Requested {$requested}, only {$available} available. Quantity adjusted to {$available}.";
                $items_to_update[] = "UPDATE carts SET quantity = $available WHERE id = $cart_id AND user_id = $user_id";
            }
        }
    }
    
    // If there were stock issues, update the cart and show error
    if ($has_stock_issues) {
        // Execute all updates
        foreach ($items_to_update as $update_query) {
            $conn->query($update_query);
        }
        
        // Display error and redirect back to cart
        ?>
        <style>
            .stock-error-wrapper { max-width: 600px; margin: 130px auto 60px; padding: 40px; background: #fff; border-radius: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.04); text-align: center; }
            .stock-error-icon { font-size: 60px; color: #f9a825; margin-bottom: 20px; }
            .stock-error-title { font-size: 24px; font-weight: 700; color: #222; margin-bottom: 10px; }
            .stock-error-msg { color: #555; line-height: 1.8; margin-bottom: 25px; text-align: left; }
            .stock-error-msg li { padding: 5px 0; }
            .btn-back-cart { padding: 14px 40px; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: white; text-decoration: none; font-weight: 600; display: inline-block; transition: 0.2s; }
            .btn-back-cart:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.3); }
        </style>
        <div class="stock-error-wrapper">
            <div class="stock-error-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stock-error-title">Stock Update Needed!</div>
            <p style="color: #888; margin-bottom: 15px;">Some items in your cart are no longer available in the requested quantity. We've updated your cart automatically.</p>
            <ul class="stock-error-msg">
                <?php foreach($stock_errors as $error): ?>
                    <li>• <?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="cart.php" class="btn-back-cart"><i class="fas fa-arrow-left" style="margin-right: 8px;"></i> Return to Cart</a>
        </div>
        <?php
        include 'footer.php';
        exit();
    }
    
    // If stock is valid, proceed with order
    // ... rest of your existing code ...
    
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $sender_phone = mysqli_real_escape_string($conn, $_POST['sender_phone']);
    $__house = trim($_POST['house_no'] ?? '');
    $__street = trim($_POST['address'] ?? '');
    $address = mysqli_real_escape_string($conn, mb_substr(trim($__house . ' ' . $__street), 0, 255));
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    
    // ... continue with your existing order placement code ...
    
    // 🚨 CHECK IF DELIVERY TYPE IS 'ME' OR 'RECIPIENT'
    $delivery_type = isset($_POST['delivery_type']) ? $_POST['delivery_type'] : 'me';
    
    // 🚨 FIX: Always get the gift message, regardless of delivery type
    $gift_message = isset($_POST['gift_message']) ? mysqli_real_escape_string($conn, $_POST['gift_message']) : '';
    
    // Only set recipient fields if delivery type is 'recipient'
    if($delivery_type == 'recipient') {
        $recipient = isset($_POST['recipient_name']) ? mysqli_real_escape_string($conn, $_POST['recipient_name']) : NULL;
        $recipient_phone = isset($_POST['recipient_phone']) ? mysqli_real_escape_string($conn, $_POST['recipient_phone']) : NULL;
    } else {
        // 🚨 CLEAR THE RECIPIENT DATA IF DELIVER TO ME
        $recipient = NULL;
        $recipient_phone = NULL;
    }
    
    $payment = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $delivery_date = mysqli_real_escape_string($conn, $_POST['delivery_date']);
    $delivery_time = mysqli_real_escape_string($conn, $_POST['delivery_time']);

    // Gifts sent straight to a recipient must be paid online — no cash on delivery.
    if ($delivery_type === 'recipient' && $payment === 'cod') {
        echo '<div style="max-width:520px;margin:150px auto 80px;padding:40px;background:#fff;border-radius:26px;box-shadow:0 10px 40px rgba(0,0,0,0.05);text-align:center;font-family:Poppins,sans-serif;">'
           . '<div style="font-size:46px;color:#f9a825;margin-bottom:12px;"><i class="fas fa-triangle-exclamation"></i></div>'
           . '<h2 style="font-size:21px;color:#222;margin-bottom:8px;">Choose an online payment</h2>'
           . '<p style="color:#888;line-height:1.6;margin-bottom:22px;">Cash on Delivery isn\'t available for gifts sent straight to a recipient. Please go back and pick online payment.</p>'
           . '<a href="javascript:history.back()" style="padding:13px 34px;border-radius:50px;background:linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%);color:#fff;text-decoration:none;font-weight:600;">&larr; Go back</a>'
           . '</div>';
        include 'footer.php';
        exit();
    }

    // --- Card payment: validate, but only ever keep the last 4 digits + name ---
    $card_last4 = null;
    $card_holder = null;
    if ($payment === 'card') {
        $card_digits = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
        $card_holder_raw = trim($_POST['card_holder'] ?? '');
        $card_exp = trim($_POST['card_expiry'] ?? '');
        $card_cvc = preg_replace('/\D/', '', $_POST['card_cvc'] ?? '');
        $card_ok = strlen($card_digits) >= 13 && strlen($card_digits) <= 19
            && $card_holder_raw !== ''
            && preg_match('#^(0[1-9]|1[0-2])\s*/\s*([0-9]{2})$#', $card_exp)
            && strlen($card_cvc) >= 3 && strlen($card_cvc) <= 4;
        if (!$card_ok) {
            echo '<div style="max-width:560px;margin:150px auto 60px;padding:40px;background:#fff;border-radius:26px;box-shadow:0 10px 40px rgba(0,0,0,0.05);text-align:center;">'
               . '<div style="font-size:52px;color:#f9a825;margin-bottom:14px;"><i class="fas fa-credit-card"></i></div>'
               . '<h2 style="font-size:22px;color:#222;margin-bottom:8px;">Check your card details</h2>'
               . '<p style="color:#888;line-height:1.7;margin-bottom:22px;">Please go back and enter a valid card number, name, expiry (MM/YY) and CVC.</p>'
               . '<a href="javascript:history.back()" style="padding:13px 34px;border-radius:50px;background:linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%);color:#fff;text-decoration:none;font-weight:600;">&larr; Go back</a>'
               . '</div>';
            include 'footer.php';
            exit();
        }
        $card_last4 = substr($card_digits, -4);
        $card_holder = mysqli_real_escape_string($conn, mb_substr($card_holder_raw, 0, 120));
    }

    $ids_string = implode(',', array_map('intval', $selected_ids));
    $cart_result = $conn->query("SELECT c.product_id, c.quantity, " . catalog_price_sql('p.') . " AS price
                                 FROM carts c
                                 JOIN products p ON c.product_id = p.id
                                 WHERE c.user_id = $user_id AND c.id IN ($ids_string)");
    
    $total_amount = 0;
    $items = [];
    while($row = $cart_result->fetch_assoc()){
        $total_amount += $row['price'] * $row['quantity'];
        $items[] = $row;
    }

    // Don't create an order with nothing in it.
    if (count($items) === 0 || $total_amount <= 0) {
        echo '<div style="max-width:520px;margin:150px auto 80px;padding:40px;background:#fff;border-radius:26px;box-shadow:0 10px 40px rgba(0,0,0,0.05);text-align:center;font-family:Poppins,sans-serif;">'
           . '<div style="font-size:46px;color:#f9a825;margin-bottom:12px;"><i class="fas fa-cart-shopping"></i></div>'
           . '<h2 style="font-size:21px;color:#222;margin-bottom:8px;">Your cart is empty</h2>'
           . '<p style="color:#888;line-height:1.6;margin-bottom:22px;">Add at least one item before checking out.</p>'
           . '<a href="shop.php" style="padding:13px 30px;border-radius:50px;background:linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%);color:#fff;text-decoration:none;font-weight:600;">Go to Shop</a>'
           . '</div>';
        include 'footer.php';
        exit();
    }

    // --- gift wrapping & add-ons (price always re-checked server-side, never trusted from POST) ---
    // Added on top after promos/shipping, same treatment as a build-a-box's box price — never discounted.
    [$addon_rows, $addon_total] = addons_resolve($conn, $_POST['addon_ids'] ?? []);

    // --- promos / discounts (re-evaluated server-side from the real cart) ---
    $promo_eval = promo_evaluate($conn, $user_id, [
        'scope'        => 'products',
        'subtotal'     => $total_amount,
        'shipping_fee' => ($total_amount > 0 && $total_amount < 300) ? 50 : 0,
        'item_count'   => array_sum(array_column($items, 'quantity')),
        'code'         => $_SESSION['promo_code_products'] ?? null,
    ]);
    $discount_amount           = $promo_eval['discount'];
    $shipping_fee              = $promo_eval['shipping_fee'];
    $grand_total_with_shipping = $promo_eval['final_total'] + $addon_total;
    $promo_code_sql = $promo_eval['code'] !== '' ? "'" . $conn->real_escape_string($promo_eval['code']) . "'" : 'NULL';
    $promo_id_sql   = $promo_eval['code_id'] !== null ? (int) $promo_eval['code_id'] : 'NULL';

        // ✅ PLACE ORDER DIRECTLY (API not working yet)
    $card_last4_sql  = $card_last4  !== null ? "'" . $card_last4 . "'"  : "NULL";
    $card_holder_sql = $card_holder !== null ? "'" . $card_holder . "'" : "NULL";
    $sql = "INSERT INTO orders (user_id, total_amount, status, fullname, sender_phone, address, city, recipient_name, recipient_phone, gift_message, payment_method, delivery_date, delivery_time, card_last4, card_holder, promo_code, promo_id, discount_amount)
            VALUES ($user_id, $grand_total_with_shipping, 'pending', '$fullname', '$sender_phone', '$address', '$city', '$recipient', '$recipient_phone', '$gift_message', '$payment', '$delivery_date', '$delivery_time', $card_last4_sql, $card_holder_sql, $promo_code_sql, $promo_id_sql, $discount_amount)";

    if ($conn->query($sql) === TRUE) {
        $order_id = $conn->insert_id;
        unset($_SESSION['gift_context']);

        foreach($items as $item) {
            $conn->query("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES ($order_id, {$item['product_id']}, {$item['quantity']}, {$item['price']})");
            $conn->query("UPDATE products SET quantity = quantity - {$item['quantity']} WHERE id = {$item['product_id']}");
        }

        foreach ($addon_rows as $arow) {
            $conn->query("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES ($order_id, {$arow['id']}, 1, {$arow['price']})");
            $conn->query("UPDATE products SET quantity = quantity - 1 WHERE id = {$arow['id']}");
        }

        // free gift (buy N + 1 free) — only if the freebie is still in stock
        $free_item_note = null;
        if (!empty($promo_eval['free_item'])) {
            $fip = (int) $promo_eval['free_item']['product_id'];
            $conn->query("UPDATE products SET quantity = quantity - 1 WHERE id = $fip AND quantity > 0");
            if ($conn->affected_rows > 0) {
                $conn->query("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES ($order_id, $fip, 1, 0)");
                $conn->query("UPDATE orders SET free_item_product_id = $fip WHERE id = $order_id");
                $free_item_note = $promo_eval['free_item']['name'];
            }
        }

        promo_record($conn, $promo_eval, $user_id, (int) $order_id);
        unset($_SESSION['promo_code_products']);

        $conn->query("DELETE FROM carts WHERE user_id = $user_id AND id IN ($ids_string)");

        // COD order confirmation email (no-op if email isn't configured)
        if ($payment === 'cod' && function_exists('send_order_email')) {
            $__mailok = send_order_email($conn, (int) $order_id, false);
            error_log('COD email order #' . $order_id . ': ' . ($__mailok ? 'sent' : ('FAILED - ' . mail_last_error())));
        }

        // --- ONLINE PAYMENT: hand off to PayMongo's hosted checkout ---
        // (header.php has already been sent by this point, so redirect client-side)
        if ($paymongo_on && $payment === 'online') {
            $pay_url = paymongo_create_checkout(
                $conn, (int) $order_id, (float) $grand_total_with_shipping,
                $fullname, ($_SESSION['user_email'] ?? ''), $sender_phone
            );
            if ($pay_url !== '') {
                echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($pay_url, ENT_QUOTES) . '">';
                echo '<script>location.replace(' . json_encode($pay_url) . ');</script>';
                echo '<div style="text-align:center;padding:150px 20px 80px;font-family:Poppins,sans-serif;color:#555;">'
                   . '<i class="fas fa-lock" style="font-size:32px;color:#ff8ba7;"></i>'
                   . '<p style="font-size:16px;margin-top:14px;">Taking you to the secure payment page…</p>'
                   . '<p style="margin-top:8px;"><a href="' . htmlspecialchars($pay_url, ENT_QUOTES) . '" style="color:#ff8ba7;font-weight:600;">Continue to payment</a></p>'
                   . '</div>';
                include 'footer.php';
                exit();
            }
            // PayMongo couldn't start — show why, order stays unpaid & payable later.
            $err = paymongo_last_error() ?: 'Payment could not be started right now.';
            echo '<div style="max-width:520px;margin:150px auto 80px;padding:40px;background:#fff;border-radius:26px;box-shadow:0 10px 40px rgba(0,0,0,0.05);text-align:center;font-family:Poppins,sans-serif;">'
               . '<div style="font-size:46px;color:#f9a825;margin-bottom:12px;"><i class="fas fa-triangle-exclamation"></i></div>'
               . '<h2 style="font-size:21px;color:#222;margin-bottom:8px;">Couldn\'t start the payment</h2>'
               . '<p style="color:#888;line-height:1.6;margin-bottom:8px;">Your order <strong>#' . (int) $order_id . '</strong> is saved but unpaid.</p>'
               . '<p style="color:#c77;font-size:12.5px;margin-bottom:22px;">' . htmlspecialchars($err) . '</p>'
               . '<a href="pay_order.php?order_id=' . (int) $order_id . '" style="padding:13px 30px;border-radius:50px;background:linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%);color:#fff;text-decoration:none;font-weight:600;">Try payment again</a>'
               . '<div style="margin-top:12px;"><a href="profile.php?tab=orders" style="color:#999;font-size:13px;">Go to My Orders</a></div>'
               . '</div>';
            include 'footer.php';
            exit();
        }

        // --- BEAUTIFUL SUCCESS PAGE ---
?>
<style>
    .success-wrapper { display: flex; justify-content: center; align-items: center; min-height: 80vh; padding: 130px 20px 60px 20px; background: #fcfcfc; }
    .success-card { background: #ffffff; border-radius: 40px; padding: 50px 40px; max-width: 550px; width: 100%; text-align: center; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.04); border: 1px solid rgba(255, 255, 255, 0.8); position: relative; }
    
    .success-badge { width: 100px; height: 100px; border-radius: 50%; background: #e8f5e9; color: #2e7d32; display: flex; align-items: center; justify-content: center; font-size: 50px; margin: 0 auto 20px auto; box-shadow: 0 10px 30px rgba(46, 125, 50, 0.15); animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    @keyframes popIn { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    
    .success-title { font-size: 28px; font-weight: 700; color: #222; margin-bottom: 5px; }
    .success-sub { font-size: 16px; color: #888; margin-bottom: 25px; }
    
    .success-details-box { background: #fafafa; border-radius: 20px; padding: 20px; margin-bottom: 30px; display: inline-block; width: 100%; text-align: left; }
    .success-detail { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 15px; }
    .success-detail:last-child { border-bottom: none; }
    .success-detail span:first-child { color: #888; }
    .success-detail span:last-child { font-weight: 600; color: #222; }
    
    .success-gift-message {
        background: #fff5f7;
        border-radius: 12px;
        padding: 15px 20px;
        margin-top: 5px;
        border-left: 3px solid #ff8ba7;
        font-style: italic;
        color: #555;
        line-height: 1.6;
        font-size: 14px;
        word-wrap: break-word;
        white-space: pre-wrap;
    }
    .success-gift-label {
        color: #888;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 5px;
    }

    .success-buttons { 
        display: flex; 
        gap: 15px; 
        justify-content: center; 
        flex-wrap: wrap; 
        margin-top: 10px; 
    }
    
    .btn-continue {
        padding: 14px 35px; 
        border-radius: 50px; 
        background: linear-gradient(135deg, #ff8ba7 0%, #e6738f 100%);
        color: white;
        text-decoration: none; 
        font-weight: 600; 
        font-size: 16px; 
        transition: 0.3s; 
        display: inline-block;
        border: none;
        box-shadow: 0 4px 12px rgba(230, 115, 143, 0.2);
    }
    .btn-continue:hover { 
        background: linear-gradient(135deg, #e6738f 0%, #cc5a7a 100%);
        transform: translateY(-3px); 
        box-shadow: 0 8px 20px rgba(230, 115, 143, 0.3);
    }
    
    .btn-orders {
        padding: 14px 35px; 
        border-radius: 50px; 
        background: #fff; 
        color: #555;
        text-decoration: none; 
        font-weight: 600; 
        font-size: 16px; 
        border: 2px solid #eee; 
        transition: 0.3s; 
        display: inline-block;
    }
    .btn-orders:hover { 
        background: #f5f5f5; 
        transform: translateY(-3px);
        border-color: #ddd;
    }


</style>

<div class="success-wrapper">
    <div class="success-card">
        <div class="success-badge"><i class="fas fa-check"></i></div>
        <div class="success-title">Order Placed! 🎉</div>
        <div class="success-sub">Thank you for your purchase. We'll start preparing your order right away.</div>
        
        <div class="success-details-box">
            <div class="success-detail"><span>Order ID</span><span>#<?php echo $order_id; ?></span></div>

            <?php if (!empty($discount_amount) && $discount_amount > 0): ?>
                <div class="success-detail"><span>Discount<?php echo $promo_eval['code'] !== '' ? ' (' . htmlspecialchars($promo_eval['code']) . ')' : ''; ?></span><span style="color:#2e7d32;">− PHP <?php echo number_format($discount_amount, 2); ?></span></div>
            <?php endif; ?>
            <?php if (!empty($free_item_note)): ?>
                <div class="success-detail"><span>Free gift 🎁</span><span style="color:#2e7d32;"><?php echo htmlspecialchars($free_item_note); ?></span></div>
            <?php endif; ?>

            <div class="success-detail"><span>Total Paid</span><span>PHP <?php echo number_format($grand_total_with_shipping, 2); ?></span></div>
            
            <div class="success-detail"><span>Payment Method</span><span><?php echo ucfirst($payment); ?></span></div>
            
            <div class="success-detail"><span>Delivery Date</span><span><?php echo date('F j, Y', strtotime($delivery_date)); ?></span></div>
            
            <div class="success-detail"><span>Delivery Time</span><span><?php echo date('g:i A', strtotime($delivery_time)); ?></span></div>
            
            <div class="success-detail"><span>Shipping Address</span><span><?php echo $address . ', ' . $city; ?></span></div>

            <?php if($recipient): ?>
                <div class="success-detail" style="border-bottom: none; padding-top: 8px;">
                    <span>Recipient</span>
                    <span><?php echo $recipient; ?><?php if($recipient_phone) echo ' (' . $recipient_phone . ')'; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($gift_message)): ?>
                <div class="success-detail" style="flex-direction: column; align-items: flex-start; border-bottom: none; padding-top: 12px;">
                    <div class="success-gift-label"><i class="fas fa-heart" style="color: #ff8ba7; margin-right: 5px;"></i> Gift Message</div>
                    <div class="success-gift-message"><?php echo nl2br(htmlspecialchars($gift_message)); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="success-buttons">
            <a href="profile.php?tab=orders" class="btn-orders"><i class="fas fa-box" style="margin-right: 8px;"></i> View My Orders</a>
            <a href="shop.php" class="btn-continue"><i class="fas fa-shopping-bag" style="margin-right: 8px;"></i> Continue Shopping</a>
        </div>
    </div>
</div>
<?php
include 'footer.php';
exit();
    } else {
        die("Database Error: " . $conn->error);
    }
}

// --- GET ITEMS TO DISPLAY ---
$selected_ids = isset($_GET['items']) ? explode(',', $_GET['items']) : [];
if(empty($selected_ids)) {
    echo "<p style='color:red; text-align:center; padding-top:130px;'>No items selected. <a href='cart.php' style='color:#ff8ba7;'>Go back to cart</a></p>";
    include 'footer.php';
    exit();
}
$ids_string = implode(',', array_map('intval', $selected_ids));
$items_query = $conn->query("SELECT c.id as cart_id, c.quantity, p.name,
                                    p.price AS list_price, " . catalog_price_sql('p.') . " AS price,
                                    p.image, p.quantity as stock_quantity, p.is_active
                             FROM carts c
                             JOIN products p ON c.product_id = p.id
                             WHERE c.user_id = $user_id AND c.id IN ($ids_string)");

$total_sum = 0;
$items_list = [];
$unavailable_names = [];
while($row = $items_query->fetch_assoc()){
    if (!catalog_is_active($row['is_active'] ?? true)) {
        $unavailable_names[] = $row['name'];
        continue; // don't include it in the order summary or total
    }
    $row['subtotal'] = $row['price'] * $row['quantity'];
    $total_sum += $row['subtotal'];
    $items_list[] = $row;
}

// If a selected item was pulled from sale, send them back to the cart to sort it out.
if (!empty($unavailable_names)) {
    echo '<div style="max-width:560px;margin:150px auto 80px;padding:40px;background:#fff;border-radius:26px;box-shadow:0 10px 40px rgba(0,0,0,0.05);text-align:center;font-family:Poppins,sans-serif;">'
       . '<div style="font-size:46px;color:#f9a825;margin-bottom:12px;"><i class="fas fa-triangle-exclamation"></i></div>'
       . '<h2 style="font-size:21px;color:#222;margin-bottom:8px;">Some items are no longer available</h2>'
       . '<p style="color:#888;line-height:1.6;margin-bottom:6px;">' . htmlspecialchars(implode(', ', $unavailable_names)) . '</p>'
       . '<p style="color:#888;line-height:1.6;margin-bottom:22px;">Please remove them from your cart, then check out again.</p>'
       . '<a href="cart.php" style="padding:13px 30px;border-radius:50px;background:linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%);color:#fff;text-decoration:none;font-weight:600;">Back to Cart</a>'
       . '</div>';
    include 'footer.php';
    exit();
}

// 🚀 SHIPPING FEE (PHP 50 under PHP 300, free over) — then let promos adjust it
$base_shipping_fee = ($total_sum > 0 && $total_sum < 300) ? 50 : 0;

$promo_eval = promo_evaluate($conn, $user_id, [
    'scope'        => 'products',
    'subtotal'     => $total_sum,
    'shipping_fee' => $base_shipping_fee,
    'item_count'   => array_sum(array_column($items_list, 'quantity')),
    'code'         => $_SESSION['promo_code_products'] ?? null,
]);
if (($_SESSION['promo_code_products'] ?? '') !== '' && $promo_eval['code'] === '' && $promo_eval['code_error'] !== '') {
    unset($_SESSION['promo_code_products']);
}
$discount_amount           = $promo_eval['discount'];
$shipping_fee              = $promo_eval['shipping_fee'];
$grand_total_with_shipping = $promo_eval['final_total'];

// 🎁 GIFT WRAPPING & ADD-ONS
$addons = addons_list($conn);

// 🚀 FETCH USER'S SAVED ADDRESSES
$addresses_query = $conn->query("SELECT * FROM addresses WHERE user_id = $user_id ORDER BY is_default DESC, id DESC");
?>

<style>
    /* --- 2-COLUMN CHECKOUT LAYOUT --- */
    .checkout-wrapper { 
        max-width: 1100px; 
        margin: 0 auto; 
        padding-top: 100px; 
        padding-bottom: 60px; 
        display: flex; 
        gap: 40px; 
        flex-wrap: wrap;
    }
    
    /* LEFT COLUMN: FORM */
    .checkout-left { flex: 1; min-width: 350px; }
    .checkout-title { font-size: 26px; font-weight: 700; color: #222; margin-bottom: 30px; }
    
    .checkout-section { margin-bottom: 30px; }
    .checkout-section h3 { font-size: 16px; font-weight: 600; color: #444; margin-bottom: 15px; }
    
    .form-row { display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap; }
    .form-group { flex: 1; min-width: 150px; display: flex; flex-direction: column; }
    .form-group label { font-size: 13px; font-weight: 600; color: #666; margin-bottom: 5px; }
    
    .form-input, .form-select {
        width: 100%; padding: 12px 16px; border: 1.5px solid #eee; border-radius: 12px;
        font-size: 14px; font-family: 'Poppins'; background: #fff; transition: 0.3s; outline: none;
    }
    .form-input:focus, .form-select:focus { border-color: #ffc1cc; box-shadow: 0 0 0 4px rgba(255, 193, 204, 0.1); }
    
    .form-input[type="date"], .form-input[type="time"] { color: #555; }

    .form-checkbox-group { display: flex; align-items: center; gap: 10px; font-size: 14px; color: #555; margin-top: 5px; }
    .form-checkbox-group input[type="checkbox"] { accent-color: #ff8ba7; width: 18px; height: 18px; cursor: pointer; }

    /* --- GIFT WRAPPING & ADD-ONS --- */
    .addon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
    .addon-card {
        display: flex; align-items: center; gap: 12px; border: 1.5px solid #eee; border-radius: 16px;
        padding: 12px 14px; cursor: pointer; transition: 0.2s; background: #fff;
    }
    .addon-card:hover { border-color: #ffc1cc; box-shadow: 0 4px 14px rgba(255,139,167,0.08); }
    .addon-card:has(input:checked) { border-color: #ff8ba7; background: #fff8fa; box-shadow: 0 4px 14px rgba(255,139,167,0.12); }
    .addon-card input[type="checkbox"] { accent-color: #ff8ba7; width: 18px; height: 18px; cursor: pointer; flex-shrink: 0; }
    .addon-card img { width: 40px; height: 40px; object-fit: contain; flex-shrink: 0; }
    .addon-info { flex: 1; min-width: 0; }
    .addon-name { font-size: 13.5px; font-weight: 600; color: #222; }
    .addon-desc { font-size: 11.5px; color: #999; margin-top: 1px; line-height: 1.3; }
    .addon-price { font-size: 13px; font-weight: 700; color: #ff8ba7; white-space: nowrap; flex-shrink: 0; }

    /* RIGHT COLUMN: ORDER SUMMARY */
    .checkout-right { width: 380px; flex-shrink: 0; }
    .order-summary-card {
        background: #ffffff; border-radius: 24px; padding: 30px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
        border: 1px solid #f5f5f5; position: sticky; top: 120px;
    }
    .order-summary-card h4 { font-size: 18px; font-weight: 700; color: #222; margin-bottom: 20px; }
    
    .os-item { display: flex; gap: 15px; padding-bottom: 15px; margin-bottom: 15px; border-bottom: 1px solid #f5f5f5; }
    .os-img { width: 70px; height: 70px; object-fit: contain; background: #fafafa; border-radius: 12px; padding: 8px; }
    .os-details { flex: 1; }
    .os-name { font-size: 15px; font-weight: 600; color: #222; }
    .os-price { font-size: 14px; color: #888; }
    .os-subtotal { font-size: 15px; font-weight: 600; color: #222; margin-left: auto; }
    
    .os-qty-wrapper { display: inline-flex; align-items: center; border: 1px solid #eee; border-radius: 50px; padding: 0 10px; background: #fff; margin-top: 4px; }
    .os-qty-btn { background: transparent; border: none; font-size: 14px; cursor: pointer; padding: 2px 6px; color: #555; }
    .os-qty-btn:hover { color: #ff8ba7; }
    .os-qty-display { font-size: 14px; font-weight: 600; min-width: 16px; text-align: center; color: #222; }
    
    .os-totals { margin-top: 8px; border-top: 1px solid #f5f5f5; padding-top: 20px; }
    .os-total-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; color: #555; }

    /* --- PROMO CODE --- */
    .promo-box { margin-top: 20px; }
    .promo-input-row { display: flex; gap: 8px; }
    .promo-input-row input {
        flex: 1; padding: 11px 14px; border: 1.5px solid #eee; border-radius: 12px;
        font-family: 'Poppins'; font-size: 13px; outline: none; text-transform: uppercase;
    }
    .promo-input-row input:focus { border-color: #ffc1cc; box-shadow: 0 0 0 4px rgba(255,193,204,.12); }
    .promo-input-row button {
        flex: 0 0 auto; padding: 0 18px; border: none; border-radius: 12px;
        background: #222; color: #fff; font-weight: 600; font-size: 13px; cursor: pointer;
    }
    .promo-input-row button:disabled { opacity: .5; cursor: default; }
    .promo-applied {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        background: #f0faf0; border: 1px solid #cfe9cf; color: #2e7d32;
        padding: 10px 14px; border-radius: 12px; font-size: 13px;
    }
    .promo-applied button { background: none; border: none; color: #888; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: underline; }
    .promo-terms { font-size: 11.5px; color: #888; margin-top: 5px; }
    .promo-error { color: #d32f2f; font-size: 12px; margin-top: 6px; }
    .promo-nudge { color: #d81b60; font-size: 12px; font-weight: 600; margin-top: 8px; background: #fff0f5; border-radius: 10px; padding: 8px 12px; }
    .os-grand-total { font-size: 22px; font-weight: 700; color: #222; }
    
    .btn-checkout-submit {
        width: 100%; padding: 16px; border: none; border-radius: 50px;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; font-size: 16px; font-weight: 600; cursor: pointer;
        transition: 0.2s; margin-top: 20px; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
    }
    .btn-checkout-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4); }

    /* --- DELIVERY OPTIONS --- */
    .delivery-options { display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
    .delivery-option { flex: 1; min-width: 120px; text-align: center; padding: 12px 10px; border: 2px solid #eee; border-radius: 12px; cursor: pointer; transition: 0.3s; background: #fff; font-family: 'Poppins'; font-weight: 500; font-size: 14px; color: #555; }
    .delivery-option:hover { border-color: #ffc1cc; background: #fff0f5; }
    .delivery-option input[type="radio"] { display: none; }
    .delivery-option.selected { border-color: #ff8ba7; background: #fff0f5; color: #d32f2f; box-shadow: 0 0 0 3px rgba(255, 139, 167, 0.1); }
    #recipientField { display: none; margin-top: -10px; margin-bottom: 15px; }
    #recipientField.show { display: block; }

    /* --- PAYMENT --- */
    .payment-section { padding: 15px; background: #fafafa; border-radius: 16px; margin-top: 10px; }
    .payment-options { display: flex; gap: 15px; margin-top: 10px; flex-wrap: wrap; }
    .payment-option { flex: 1; min-width: 100px; text-align: center; padding: 12px 10px; border: 2px solid #eee; border-radius: 12px; cursor: pointer; transition: 0.3s; background: #fff; font-weight: 500; font-size: 14px; color: #555; font-family: 'Poppins'; }
    .payment-option:hover { border-color: #ffc1cc; background: #fff0f5; }
    .payment-option input[type="radio"] { display: none; }
    .payment-option.selected { border-color: #ff8ba7; background: #fff0f5; color: #d32f2f; }
    .payment-option.disabled { opacity: 0.4; background: #eee; border-color: #ddd; color: #999; cursor: not-allowed; pointer-events: none; }

    /* RESPONSIVE */
    @media (max-width: 850px) {
        .checkout-wrapper { flex-direction: column; }
        .checkout-right { width: 100%; }
        .order-summary-card { position: static; }
        .form-row { flex-direction: column; gap: 10px; }
    }

        /* --- CUTE TIME ALERT MODAL --- */
    .time-alert-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(6px);
        display: none; justify-content: center; align-items: center;
        z-index: 999999; padding: 20px;
    }
    .time-alert-box {
        background: #ffffff; border-radius: 30px; padding: 40px;
        max-width: 400px; width: 90%; text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        animation: fadeUp 0.3s ease;
    }
    @keyframes fadeUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

        /* --- UNSAVED CHANGES ALERT MODAL --- */
    .unsaved-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(6px);
        display: none; justify-content: center; align-items: center;
        z-index: 999999; padding: 20px;
    }
    .unsaved-box {
        background: #ffffff; border-radius: 30px; padding: 40px;
        max-width: 400px; width: 90%; text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        animation: fadeUp 0.3s ease;
    }
    .unsaved-box .confirm-buttons { display: flex; gap: 15px; margin-top: 5px; }

/* --- PHONE VALIDATION STYLES --- */
.phone-input-wrapper {
    position: relative;
    width: 100%;
}
.phone-error-msg {
    color: #d32f2f; 
    font-size: 12px; 
    font-weight: 500; 
    margin-top: 4px;
    display: none;
}
.phone-error-msg.visible {
    display: block;
}
.phone-input-error {
    border-color: #d32f2f !important;
    background: #fff5f5 !important;
}


/* --- STOCK ALERT MODAL --- */
.stock-alert-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(6px);
    display: none; justify-content: center; align-items: center;
    z-index: 999999; padding: 20px;
}
.stock-alert-box {
    background: #ffffff; border-radius: 30px; padding: 40px;
    max-width: 400px; width: 90%; text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    animation: fadeUp 0.3s ease;
}
.stock-alert-icon {
    font-size: 50px; 
    color: #f9a825; 
    margin-bottom: 15px;
}
.stock-alert-icon i {
    background: #fff8e1; 
    padding: 15px; 
    border-radius: 50%;
}
.stock-alert-title {
    font-size: 22px; 
    font-weight: 700; 
    color: #222; 
    margin-bottom: 5px;
}
.stock-alert-sub {
    font-size: 14px; 
    color: #888; 
    margin-bottom: 25px; 
    line-height: 1.5;
}
.stock-alert-btn {
    padding: 12px 40px; 
    border: none; 
    border-radius: 50px;
    background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
    color: white; 
    font-weight: 600; 
    font-size: 15px; 
    cursor: pointer; 
    transition: 0.2s; 
    font-family: 'Poppins';
    box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
}
.stock-alert-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4);
}

/* --- CONFIRM ORDER MODAL STYLES (Add these if missing) --- */
.confirm-modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(6px);
    display: none; justify-content: center; align-items: center;
    z-index: 999998; padding: 20px;
}
.confirm-modal-box {
    background: #ffffff; border-radius: 30px; padding: 40px;
    max-width: 400px; width: 90%; text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    animation: fadeUp 0.3s ease;
}
.confirm-icon { font-size: 50px; margin-bottom: 15px; }
.confirm-title { font-size: 22px; font-weight: 700; color: #222; margin-bottom: 5px; }
.confirm-sub { font-size: 14px; color: #888; margin-bottom: 25px; line-height: 1.5; }
.confirm-buttons { display: flex; gap: 15px; justify-content: center; }

/* --- CUSTOM SELECT DROPDOWN (Giftly Design) --- */
.custom-select-wrapper {
    position: relative;
    width: 100%;
}

.custom-select {
    width: 100%;
    padding: 14px 45px 14px 20px;
    border: 2px solid #eee;
    border-radius: 16px;
    font-size: 14px;
    font-family: 'Poppins', sans-serif;
    background: #fafafa;
    color: #333;
    outline: none;
    transition: all 0.3s ease;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    cursor: pointer;
    position: relative;
    z-index: 1;
}

.custom-select:hover {
    border-color: #ffc1cc;
    background: #fff;
    box-shadow: 0 2px 12px rgba(255, 193, 204, 0.1);
}

.custom-select:focus {
    border-color: #ff8ba7;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(255, 139, 167, 0.08);
}

.custom-select option {
    padding: 12px 16px;
    background: #fff;
    color: #333;
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
}

.custom-select option:hover {
    background: #fff0f5;
}

.custom-select option:first-child {
    color: #888;
    font-weight: 400;
}

.custom-select-arrow {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    z-index: 2;
    color: #999;
    font-size: 14px;
    transition: all 0.3s ease;
}

.custom-select-wrapper:hover .custom-select-arrow {
    color: #ff8ba7;
}

.custom-select-wrapper:focus-within .custom-select-arrow {
    color: #ff8ba7;
    transform: translateY(-50%) rotate(180deg);
}

/* Style for the dropdown when it's open */
.custom-select:focus + .custom-select-arrow {
    transform: translateY(-50%) rotate(180deg);
}

/* Custom scrollbar for dropdown options */
.custom-select option {
    scrollbar-width: thin;
    scrollbar-color: #ffc1cc #f5f5f5;
}

.custom-select option::-webkit-scrollbar {
    width: 6px;
}

.custom-select option::-webkit-scrollbar-track {
    background: #f5f5f5;
    border-radius: 10px;
}

.custom-select option::-webkit-scrollbar-thumb {
    background: #ffc1cc;
    border-radius: 10px;
}

/* 🚀 Animated placeholder option */
.custom-select option[value=""] {
    color: #999;
    font-style: italic;
}

/* Add a subtle animation when selecting */
.custom-select:active {
    transform: scale(0.98);
}

/* 🎀 Special styling for saved addresses with labels */
.custom-select option[data-address] {
    border-left: 3px solid transparent;
    padding-left: 16px;
}

/* 🏠 Add a small home icon next to address options using CSS */
.custom-select option[value]:not([value=""])::before {
    content: "📍";
    margin-right: 8px;
}

/* ✨ Selected state */
.custom-select option:checked {
    background: linear-gradient(135deg, #fff0f5 0%, #ffe4e8 100%);
    color: #d32f2f;
    font-weight: 600;
}

/* 📱 Responsive */
@media (max-width: 600px) {
    .custom-select {
        padding: 12px 40px 12px 16px;
        font-size: 13px;
        border-radius: 14px;
    }
    .custom-select-arrow {
        right: 14px;
        font-size: 12px;
    }
}

/* --- ANIMATION FOR DROPDOWN --- */
.custom-select {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.custom-select-wrapper::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 2px;
    background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
    transition: all 0.3s ease;
    transform: translateX(-50%);
    border-radius: 10px;
}

.custom-select-wrapper:focus-within::after {
    width: 80%;
}

/* --- TERMS AND CONDITIONS MODAL --- */
.terms-modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(6px);
    z-index: 999999; display: none; justify-content: center; align-items: center; padding: 20px;
}
.terms-modal-box {
    background: #ffffff; 
    border-radius: 30px; 
    padding: 40px; 
    max-width: 650px; 
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15); 
    position: relative; 
    animation: fadeUp 0.3s ease;
    max-height: 85vh;  
    overflow-y: auto;  
    overflow-x: hidden; 
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.terms-modal-box::-webkit-scrollbar {
    display: none;
}
.terms-modal-close {
    position: absolute; top: 15px; right: 20px; 
    font-size: 24px; color: #888; cursor: pointer; 
    transition: 0.2s; z-index: 10;
}
.terms-modal-close:hover { 
    color: #ff8ba7; 
    transform: rotate(90deg); 
}
@keyframes fadeUp { 
    from { transform: translateY(20px); opacity: 0; } 
    to { transform: translateY(0); opacity: 1; } 
}

.terms-modal-header {
    text-align: center;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f0f0f0;
}
.terms-modal-header .terms-icon {
    font-size: 45px;
    color: #ff8ba7;
    margin-bottom: 10px;
}
.terms-modal-header .terms-icon i {
    background: #fff0f5;
    padding: 15px;
    border-radius: 50%;
}
.terms-modal-title {
    font-size: 24px;
    font-weight: 700;
    color: #222;
    margin-bottom: 5px;
}
.terms-modal-sub {
    font-size: 14px;
    color: #888;
}

.terms-content {
    margin-bottom: 25px;
}
.terms-section {
    margin-bottom: 20px;
}
.terms-section-title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.terms-section-title .section-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
}
.terms-section-text {
    font-size: 14px;
    color: #555;
    line-height: 1.7;
    padding-left: 34px;
}
.terms-section-text ul {
    padding-left: 20px;
    margin: 5px 0;
}
.terms-section-text ul li {
    margin-bottom: 4px;
}

.terms-modal-footer {
    border-top: 1px solid #f0f0f0;
    padding-top: 20px;
    display: flex;
    gap: 15px;
    justify-content: center;
}
.btn-terms-close {
    padding: 12px 40px;
    border: none;
    border-radius: 50px;
    background: #eaeaea;
    color: #555;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: 0.2s;
    font-family: 'Poppins';
}
.btn-terms-close:hover {
    background: #d6d6d6;
}

@media (max-width: 600px) {
    .terms-modal-box {
        padding: 30px 20px;
    }
    .terms-section-text {
        padding-left: 0;
    }
    .terms-modal-footer {
        flex-direction: column;
    }
}
</style>

<div class="checkout-wrapper">
    <!-- LEFT COLUMN: FORM -->
    <div class="checkout-left">
        <h1 class="checkout-title">Checkout</h1>

        <form id="orderForm" action="checkout_selected.php" method="POST">
            <input type="hidden" name="selected_ids_hidden" id="selectedIdsHidden" value="<?php echo implode(',', $selected_ids); ?>">
            <input type="hidden" name="place_order" value="1">

            <!-- 1. CONTACT INFORMATION -->
            <div class="checkout-section">
                <h3>1. Contact Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
<input type="text" name="fullname" class="form-input" value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" required>                    </div>
                    <div class="form-group">
    <label>Phone Number</label>
    <div class="phone-input-wrapper">
        <input type="tel" id="senderPhone" name="sender_phone" class="form-input" 
               value="<?php echo htmlspecialchars($user_phone); ?>" 
               placeholder="e.g. 09123456789" required 
               oninput="validatePhoneNumber('senderPhone')" 
               onblur="validatePhoneNumber('senderPhone')">
    </div>
    <div class="phone-error-msg" id="senderPhone_error">
        <i class="fas fa-exclamation-circle" style="margin-right: 4px;"></i>
        Please enter a valid 11-digit phone number.
    </div>
</div>
                </div>
            </div>

            <!-- 2. DELIVERY METHOD & DATE -->
            <div class="checkout-section">
                <h3>2. Delivery Method</h3>
                
                <div class="delivery-options">
                    <label class="delivery-option <?php echo $gift_ctx ? '' : 'selected'; ?>" id="optMe" onclick="selectDelivery('me')">
                        <input type="radio" name="delivery_type" value="me" <?php echo $gift_ctx ? '' : 'checked'; ?>>
                        <i class="fas fa-home" style="font-size: 20px; display: block; margin-bottom: 5px;"></i>
                        Deliver to Me
                    </label>
                    <label class="delivery-option <?php echo $gift_ctx ? 'selected' : ''; ?>" id="optRecipient" onclick="selectDelivery('recipient')">
                        <input type="radio" name="delivery_type" value="recipient" <?php echo $gift_ctx ? 'checked' : ''; ?>>
                        <i class="fas fa-user-friends" style="font-size: 20px; display: block; margin-bottom: 5px;"></i>
                        Deliver to Recipient
                    </label>
                </div>

                <div id="recipientField" class="<?php echo $gift_ctx ? 'show' : ''; ?>">
    <?php if (!empty($saved_recipients)): ?>
    <div class="form-row" style="margin-bottom: 10px;">
        <div class="form-group">
            <label>Saved Person <span style="color:#bbb;font-weight:400;">(optional)</span></label>
            <div class="custom-select-wrapper">
                <select id="savedRecipientSelect" class="custom-select" onchange="fillRecipientFields()">
                    <option value="">🎁 Choose a saved person</option>
                    <?php foreach ($saved_recipients as $sr): ?>
                        <option value="<?php echo (int) $sr['id']; ?>"
                                <?php echo (!empty($gift_ctx['recipient_id']) && (int) $gift_ctx['recipient_id'] === (int) $sr['id']) ? 'selected' : ''; ?>
                                data-name="<?php echo htmlspecialchars($sr['name']); ?>"
                                data-phone="<?php echo htmlspecialchars($sr['phone']); ?>"
                                data-house="<?php echo htmlspecialchars($sr['house_no']); ?>"
                                data-street="<?php echo htmlspecialchars($sr['street']); ?>"
                                data-city="<?php echo htmlspecialchars($sr['city_line']); ?>">
                            <?php echo htmlspecialchars($sr['name']); ?><?php echo $sr['relationship'] ? ' — ' . htmlspecialchars($sr['relationship']) : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="custom-select-arrow">
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
            <div style="font-size: 11px; color: #888; margin-top: 4px;">
                <i class="fas fa-address-book" style="margin-right: 4px;"></i> Pick someone from My Relations to fill in the fields below
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="form-row" style="margin-bottom: 10px;">
        <div class="form-group">
            <label>Recipient's Name</label>
            <input type="text" name="recipient_name" class="form-input" placeholder="Who is this gift for?" value="<?php echo htmlspecialchars($gift_ctx['name'] ?? ''); ?>" <?php echo $gift_ctx ? 'required' : ''; ?>>
        </div>
        <div class="form-group">
    <label>Recipient's Phone Number</label>
    <div class="phone-input-wrapper">
        <input type="tel" id="recipientPhone" name="recipient_phone" class="form-input"
               placeholder="Recipient's contact number" value="<?php echo htmlspecialchars($gift_ctx['phone'] ?? ''); ?>"
               oninput="validatePhoneNumber('recipientPhone')"
               onblur="validatePhoneNumber('recipientPhone')" <?php echo $gift_ctx ? 'required' : ''; ?>>
    </div>
    <div class="phone-error-msg" id="recipientPhone_error">
        <i class="fas fa-exclamation-circle" style="margin-right: 4px;"></i>
        Please enter a valid 11-digit phone number.
    </div>
</div>
    </div>
</div>

                                <div class="form-row">
    <div class="form-group">
        <label>Saved Address</label>
        <div class="custom-select-wrapper">
            <select name="address_id" id="addressSelect" class="custom-select" onchange="fillAddressFields()">
                <option value="">🏠 Choose a saved address</option>
                <?php while($addr = $addresses_query->fetch_assoc()):
                    $addr_def = addr_is_default($addr['is_default']);
                ?>
                    <option value="<?php echo $addr['id']; ?>"
                            <?php echo $addr_def ? 'data-default="1"' : ''; ?>
                            data-address="<?php echo htmlspecialchars($addr['address']); ?>"
                            data-city="<?php echo htmlspecialchars($addr['city']); ?>"
                            data-province="<?php echo htmlspecialchars($addr['province']); ?>"
                            data-zip="<?php echo htmlspecialchars($addr['zip']); ?>">
                        <?php echo htmlspecialchars(($addr['label'] ?: 'Address') . ' — ' . $addr['address'] . ', ' . $addr['city']) . ($addr_def ? ' (default)' : ''); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <div class="custom-select-arrow">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div style="font-size: 11px; color: #888; margin-top: 4px;">
            <i class="fas fa-map-pin" style="margin-right: 4px;"></i> Select a saved address or enter a new one below
        </div>
    </div>
</div>

                <?php $maps_id = 'co'; include 'maps_address.php'; ?>

                <div class="form-row">
                    <div class="form-group" style="flex:0 0 38%;">
                        <label>House / Unit / Block / Lot No. <span style="color:#bbb;font-weight:400;">(optional)</span></label>
                        <input type="text" name="house_no" id="checkoutHouse" class="form-input" placeholder="e.g. Blk 1 Lot 2 / Unit 4B" value="<?php echo htmlspecialchars($gift_ctx['house_no'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Street Address</label>
                        <input type="text" name="address" id="checkoutAddress" class="form-input" placeholder="Street / subdivision" value="<?php echo htmlspecialchars($gift_ctx['street'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Barangay, City, Province</label>
                        <input type="text" name="city" id="checkoutCity" class="form-input" placeholder="Barangay, City, Province" value="<?php echo htmlspecialchars($gift_ctx['city_line'] ?? ''); ?>" required>
                    </div>
                </div>
                <script>
                    (function () {
                        var m = document.getElementById('co_maps');
                        if (!m) return;
                        m.addEventListener('maps:address', function (e) {
                            var d = e.detail;
                            if (d.street) document.getElementById('checkoutAddress').value = d.street;
                            var line = [d.barangay, d.city, d.province].filter(Boolean).join(', ');
                            if (line) document.getElementById('checkoutCity').value = line;
                        });
                    })();
                </script>

                <div class="form-row">
    <div class="form-group">
        <label>Delivery Date</label>
        <input type="date" name="delivery_date" class="form-input" id="deliveryDate" required>
        <div id="dateHelperText" style="font-size: 12px; color: #888; margin-top: 4px; font-weight: 400;">
            ⏳ Please allow at least 3 days for processing and delivery.
        </div>
    </div>
    <div class="form-group">
        <label>Delivery Time</label>
        <input type="time" name="delivery_time" class="form-input" id="deliveryTime" required onchange="validateTime()" onblur="validateTime()">
        <div id="timeHelperText" style="font-size: 12px; color: #888; margin-top: 4px; font-weight: 400;">
            🕒 Delivery hours are strictly between <strong>8:00 AM</strong> and <strong>8:00 PM</strong>.
        </div>
    </div>
</div>

                <!-- Gift Message Field - Always Visible -->
<div id="giftMessageField" class="show" style="margin-top: 15px;">
    <div class="form-group">
        <label>Gift Message (Optional)</label>
        <textarea name="gift_message" id="giftMessageInput" class="form-input" style="resize: vertical; min-height: 70px;" placeholder="Write a heartfelt message for the recipient..." maxlength="300" oninput="updateCharCount()"></textarea>
        <div style="text-align: right; font-size: 12px; color: #888; margin-top: 4px;">
            <span id="charCountDisplay">0 / 300</span>
        </div>
    </div>
</div>
            </div>

            <?php if (!empty($addons)): ?>
            <!-- 3. GIFT WRAPPING & ADD-ONS -->
            <div class="checkout-section">
                <h3>3. Gift Wrapping &amp; Add-ons <span style="color:#bbb;font-weight:400;font-size:13px;">(optional)</span></h3>
                <div class="addon-grid">
                    <?php foreach ($addons as $a): ?>
                        <label class="addon-card">
                            <input type="checkbox" name="addon_ids[]" value="<?php echo (int) $a['id']; ?>" class="addon-checkbox" data-price="<?php echo (float) $a['price']; ?>" onchange="updateAddonsTotal()">
                            <img src="<?php echo htmlspecialchars(img_url($a['image'])); ?>" alt="">
                            <div class="addon-info">
                                <div class="addon-name"><?php echo htmlspecialchars($a['name']); ?></div>
                                <div class="addon-desc"><?php echo htmlspecialchars($a['description']); ?></div>
                            </div>
                            <div class="addon-price">+PHP <?php echo number_format($a['price'], 2); ?></div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 4. PAYMENT METHOD -->
            <div class="checkout-section">
                <h3>4. Payment Method</h3>
                <div class="payment-section">
                    <div class="payment-options">
                        <input type="hidden" name="payment_method" id="paymentMethodInput" value="cod">
                        <div class="payment-option selected" id="payCOD" onclick="selectPayment('cod')">
                            <i class="fas fa-money-bill-wave" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>
                            Cash on Delivery
                        </div>
<?php if ($paymongo_on): ?>
                        <div class="payment-option" id="payCard" onclick="selectPayment('online')">
                            <i class="fas fa-credit-card" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>
                            Pay Online
                            <div style="font-size:11px;color:#999;font-weight:400;margin-top:3px;">Card · GCash · Maya</div>
                        </div>
                    </div>
                    <div id="onlinePayNote" style="display:none; margin-top:16px; padding:16px 18px; border:1.5px dashed #ffc1cc; border-radius:14px; background:#fff8fa; font-size:13px; color:#777;">
                        <i class="fas fa-lock" style="color:#ff8ba7;"></i>
                        You'll be taken to PayMongo's secure page to pay by card, GCash or Maya, then brought right back.
                    </div>
<?php else: ?>
                        <div class="payment-option" id="payCard" onclick="selectPayment('card')">
                            <i class="fas fa-credit-card" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>
                            Credit / Debit Card
                        </div>
                    </div>

                    <div id="cardFields" style="display:none; margin-top:16px; padding:18px; border:1.5px dashed #ffc1cc; border-radius:14px; background:#fff8fa;">
                        <div class="form-group">
                            <label>Name on Card</label>
                            <input type="text" name="card_holder" id="cardHolder" class="form-input" autocomplete="cc-name" placeholder="e.g. Juan Dela Cruz">
                        </div>
                        <div class="form-group" style="margin-top:12px;">
                            <label>Card Number</label>
                            <input type="text" name="card_number" id="cardNumber" class="form-input" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" maxlength="23">
                        </div>
                        <div class="form-row" style="margin-top:12px;">
                            <div class="form-group">
                                <label>Expiry (MM/YY)</label>
                                <input type="text" name="card_expiry" id="cardExpiry" class="form-input" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/YY" maxlength="5">
                            </div>
                            <div class="form-group">
                                <label>CVC</label>
                                <input type="text" name="card_cvc" id="cardCvc" class="form-input" inputmode="numeric" autocomplete="cc-csc" placeholder="123" maxlength="4">
                            </div>
                        </div>
                        <div style="font-size:12px;color:#999;margin-top:8px;"><i class="fas fa-lock"></i> Demo checkout — only the last 4 digits are kept with your order.</div>
                    </div>
<?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- RIGHT COLUMN: ORDER SUMMARY -->
    <div class="checkout-right">
        <div class="order-summary-card">
            <h4>Order Summary</h4>
            
            <?php foreach($items_list as $item): ?>
    <div class="os-item" id="row_<?php echo $item['cart_id']; ?>" data-stock="<?php echo $item['stock_quantity']; ?>">
        <img src="<?php echo htmlspecialchars(img_url($item['image'])); ?>" class="os-img">
        <div class="os-details">
            <div class="os-name"><?php echo $item['name']; ?></div>
            <div class="os-price">PHP <?php echo number_format($item['price'], 2); ?> each<?php if ((float)$item['price'] < (float)$item['list_price']): ?> <span style="text-decoration:line-through;color:#bbb;">PHP <?php echo number_format($item['list_price'], 2); ?></span><?php endif; ?></div>
            <div style="font-size: 11px; color: #888; margin-top: 2px;">
                Stock: <?php echo $item['stock_quantity']; ?> available
            </div>
            
            <div class="os-qty-wrapper">
                <button type="button" class="os-qty-btn" onclick="updateCheckoutQty(<?php echo $item['cart_id']; ?>, 'decrease')">−</button>
                <span class="os-qty-display" id="cqty_<?php echo $item['cart_id']; ?>"><?php echo $item['quantity']; ?></span>
                <button type="button" class="os-qty-btn" onclick="updateCheckoutQty(<?php echo $item['cart_id']; ?>, 'increase')">+</button>
            </div>
        </div>
        <div class="os-subtotal" id="csub_<?php echo $item['cart_id']; ?>">PHP <?php echo number_format($item['subtotal'], 2); ?></div>
    </div>
<?php endforeach; ?>

            <!-- PROMO CODE -->
            <div class="promo-box" id="promoBox">
                <div class="promo-input-row" id="promoInputRow" <?php echo $promo_eval['code'] !== '' ? 'style="display:none;"' : ''; ?>>
                    <input type="text" id="promoCodeInput" placeholder="Promo code" autocomplete="off" maxlength="40"
                           value="" onkeydown="if(event.key==='Enter'){event.preventDefault();applyPromo();}">
                    <button type="button" id="promoApplyBtn" onclick="applyPromo()">Apply</button>
                </div>
                <div class="promo-applied" id="promoApplied" <?php echo $promo_eval['code'] !== '' ? '' : 'style="display:none;"'; ?>>
                    <span><i class="fas fa-tag"></i> Code <strong id="promoAppliedCode"><?php echo htmlspecialchars($promo_eval['code']); ?></strong> applied</span>
                    <button type="button" onclick="removePromo()">Remove</button>
                </div>
                <?php $__pterms = promo_terms_text($promo_eval['code_min_spend'], $promo_eval['code_max_discount']); ?>
                <div class="promo-terms" id="promoTerms" <?php echo $__pterms !== '' ? '' : 'style="display:none;"'; ?>><i class="fas fa-circle-info"></i> <span id="promoTermsText"><?php echo htmlspecialchars($__pterms); ?></span></div>
                <div class="promo-error" id="promoError" <?php echo $promo_eval['code_error'] !== '' ? '' : 'style="display:none;"'; ?>><?php echo htmlspecialchars($promo_eval['code_error']); ?></div>
                <div class="promo-nudge" id="promoNudge" <?php echo $promo_eval['free_item_nudge'] ? '' : 'style="display:none;"'; ?>>
                    <?php if ($promo_eval['free_item_nudge']): $n = $promo_eval['free_item_nudge']; ?>
                        🎁 Add <?php echo (int) $n['more']; ?> more item<?php echo $n['more'] == 1 ? '' : 's'; ?> to get a free <?php echo htmlspecialchars($n['name']); ?>!
                    <?php endif; ?>
                </div>
            </div>

            <div class="os-totals">
    <div class="os-total-row">
        <span>Subtotal</span>
        <span id="checkoutGrandTotal">PHP <?php echo number_format($total_sum, 2); ?></span>
    </div>

    <!-- DISCOUNT LINES -->
    <div id="promoLines">
        <?php foreach ($promo_eval['lines'] as $ln): ?>
            <div class="os-total-row" style="color:#2e7d32;">
                <span><?php echo htmlspecialchars($ln['label']); ?></span>
                <span><?php echo abs($ln['amount']) < 0.005 ? 'FREE' : '− PHP ' . number_format(abs($ln['amount']), 2); ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- SHIPPING ROW -->
    <div class="os-total-row" style="color: #666; margin-bottom: 5px;">
        <span>Shipping Fee</span>
        <span id="checkoutShippingFee"><?php echo ($shipping_fee == 0) ? 'FREE' : 'PHP ' . number_format($shipping_fee, 2); ?></span>
    </div>

    <!-- NEW: Small Shipping Note (Only free shipping over 300) -->
    <div style="font-size: 11px; color: #999; margin-bottom: 15px; border-bottom: 1px solid #f0f0f0; padding-bottom: 12px;">
        <i class="fas fa-truck" style="margin-right: 4px;"></i>
        Free shipping on orders over <strong>PHP 300</strong>
    </div>

    <div class="os-total-row" id="addonsTotalRow" style="color: #ff8ba7; display:none;">
        <span><i class="fas fa-gift" style="margin-right:4px;"></i> Gift Wrapping &amp; Add-ons</span>
        <span id="addonsTotalAmount">PHP 0.00</span>
    </div>

    <div class="os-total-row" style="border-top: 1px solid #f0f0f0; padding-top: 15px; margin-top: 5px;">
        <span class="os-grand-total">Total</span>
        <span class="os-grand-total" id="checkoutGrandTotalFinal">PHP <?php echo number_format($grand_total_with_shipping, 2); ?></span>
    </div>
</div>

            <button type="button" class="btn-checkout-submit" onclick="openConfirmModal()">
    <i class="fas fa-lock" style="margin-right: 8px;"></i> Place Order
</button>
            
           <div style="text-align: center; margin-top: 12px; font-size: 12px; color: #999;">
    By placing the order, you agree to our 
    <a href="#" onclick="openTermsModal(); return false;" style="color: #ff8ba7; text-decoration: underline; font-weight: 500; cursor: pointer;">
        Terms and Conditions
    </a>
</div>
        </div>
    </div>
</div>

<!-- UNSAVED CHANGES ALERT MODAL -->
<div class="unsaved-overlay" id="unsavedModal">
    <div class="unsaved-box">
        <div style="text-align: center; padding: 10px 0;">
            <div style="font-size: 50px; color: #ff8ba7; margin-bottom: 15px;">
                <i class="fas fa-exclamation-circle" style="background: #fff0f5; padding: 15px; border-radius: 50%;"></i>
            </div>
            <h3 style="font-size: 22px; font-weight: 700; color: #222; margin-bottom: 8px;">Are you sure?</h3>
            <p id="unsavedMessage" style="font-size: 15px; color: #888; margin-bottom: 25px; line-height: 1.5;">
                You have unsaved changes. Are you sure you want to leave this page?
            </p>
            <div class="confirm-buttons">
                <button onclick="cancelLeave()" style="flex: 1; padding: 14px; border: none; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: white; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Poppins'; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);">Stay on Page</button>
                <button onclick="confirmLeave()" style="flex: 1; padding: 14px; border: none; border-radius: 50px; background: #eaeaea; color: #555; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Poppins';">Leave Anyway</button>
            </div>
        </div>
    </div>
</div>

<!-- CONFIRM ORDER MODAL -->
<div class="confirm-modal-overlay" id="confirmModal">
    <div class="confirm-modal-box">
        <div class="confirm-icon"><i class="fas fa-shield-alt" style="color: #ff8ba7; background: #fff0f5; padding: 20px; border-radius: 50%; box-shadow: 0 4px 15px rgba(255,139,167,0.1);"></i></div>
        <div class="confirm-title">Confirm Your Order</div>
        <div class="confirm-sub">Are you sure you want to place this order? This action cannot be undone.</div>
        <div class="confirm-buttons">
                        <!-- Cancel Button (Gray) -->
            <button onclick="closeConfirmModal()" style="flex: 1; padding: 14px; border: none; border-radius: 50px; background: #eaeaea; color: #555; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Poppins';">Cancel</button>
            
            <!-- Yes, Place Order Button (Also Pink!) -->
            <button class="btn-modal-confirm" onclick="submitOrder()" style="flex: 1; padding: 14px; border: none; border-radius: 50px; background: linear-gradient(135deg, #ff8ba7 0%, #e6738f 100%); color: white; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Poppins'; box-shadow: 0 4px 12px rgba(230, 115, 143, 0.2);">Yes, Place Order</button>
        </div>
    </div>
</div>

<!-- CUTE TIME ALERT MODAL -->
<div class="time-alert-overlay" id="timeAlertModal">
    <div class="time-alert-box">
        <div style="text-align: center; padding: 10px 0;">
            <div style="font-size: 50px; color: #ff8ba7; margin-bottom: 15px;">
                <i class="fas fa-clock" style="background: #fff0f5; padding: 15px; border-radius: 50%;"></i>
            </div>
            <h3 style="font-size: 22px; font-weight: 700; color: #222; margin-bottom: 5px;">Time Adjusted</h3>
            <p id="timeAlertMessage" style="font-size: 15px; color: #888; margin-bottom: 25px; line-height: 1.5;">
                Delivery hours are between 8:00 AM and 8:00 PM.
            </p>
            <button onclick="closeTimeAlert()" style="padding: 12px 40px; border: none; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: white; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Poppins'; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);">
                Got it!
            </button>
        </div>
    </div>
</div>

<!-- STOCK ALERT MODAL -->
<div class="stock-alert-overlay" id="stockAlertModal">
    <div class="stock-alert-box">
        <div class="stock-alert-icon">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stock-alert-title">Not Enough Stock</div>
        <div class="stock-alert-sub" id="stockAlertMessage">
            Sorry, only <strong>22</strong> items available in stock.
        </div>
        <button class="stock-alert-btn" onclick="closeStockAlert()">
            <i class="fas fa-check" style="margin-right: 8px;"></i> Got it!
        </button>
    </div>
</div>

<!-- TERMS AND CONDITIONS MODAL -->
<div class="terms-modal-overlay" id="termsModal">
    <div class="terms-modal-box">
        <div class="terms-modal-close" onclick="closeTermsModal()">&times;</div>
        
        <div class="terms-modal-header">
            <div class="terms-icon">
                <i class="fas fa-file-contract"></i>
            </div>
            <h2 class="terms-modal-title">Terms and Conditions</h2>
            <p class="terms-modal-sub">Last Updated: August 23, 2026</p>
        </div>

        <div class="terms-content">
            <p style="font-size: 14px; color: #666; margin-bottom: 20px; line-height: 1.6;">
                Welcome to <strong>Giftly: Gift Surprise Order and Delivery System</strong>. By accessing, browsing, or placing an order through Giftly, you agree to comply with and be bound by the following Terms and Conditions. Please read them carefully before placing an order.
            </p>

            <!-- Section 1 -->
            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="section-number">1</span> General
                </div>
                <div class="terms-section-text">
                    Giftly is an online platform that allows customers to browse and purchase gifts for delivery. By using our website and placing an order, you confirm that the information you provide is accurate and complete.
                </div>
            </div>

            <!-- Section 2 -->
            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="section-number">2</span> Orders and Payments
                </div>
                <div class="terms-section-text">
                    All orders are subject to product availability and confirmation. Customers are responsible for reviewing their order details, including the selected products, quantity, recipient information, delivery address, and payment details, before completing their order.<br><br>
                    Giftly reserves the right to cancel or refuse an order if incorrect information, pricing errors, product unavailability, or other unexpected circumstances occur.
                </div>
            </div>

            <!-- Section 3 -->
            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="section-number">3</span> Product Availability
                </div>
                <div class="terms-section-text">
                    Products displayed on the website are subject to availability. In the event that an ordered product becomes unavailable, Giftly may contact the customer regarding a replacement, modification, or cancellation of the order.<br><br>
                    Product images are provided for reference purposes. Actual products, packaging, colors, and arrangements may vary slightly.
                </div>
            </div>

            <!-- Section 4 -->
            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="section-number">4</span> Delivery
                </div>
                <div class="terms-section-text">
                    Customers must provide a complete and accurate delivery address and recipient contact information. Giftly shall not be held responsible for delays or failed deliveries caused by incorrect, incomplete, or unreachable recipient information.<br><br>
                    Delivery times may vary depending on location, weather conditions, traffic, product availability, and other unforeseen circumstances.
                </div>
            </div>

            <!-- Section 5 -->
            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="section-number">5</span> Cancellations and Changes
                </div>
                <div class="terms-section-text">
                    Requests to cancel or modify an order may only be accommodated before the order has been processed or prepared. Once an order is already being prepared, dispatched, or delivered, cancellation or modification may no longer be possible.
                </div>
            </div>

            <!-- Section 6 -->
            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="section-number">6</span> Returns and Refunds
                </div>
                <div class="terms-section-text">
                    Refunds, replacements, or other resolutions will be handled depending on the circumstances of the order. Customers must report concerns regarding damaged, incorrect, or missing items as soon as possible and provide the necessary order information or evidence when requested.<br><br>
                    Giftly reserves the right to review each request before approving a refund or replacement.
                </div>
            </div>

            <!-- Section 7 -->
            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="section-number">7</span> Customer Responsibilities
                </div>
                <div class="terms-section-text">
                    Customers agree not to use the Giftly website for fraudulent, unlawful, or unauthorized purposes. Customers are responsible for ensuring that all information submitted during registration, checkout, and delivery is accurate.
                </div>
            </div>

            <!-- Section 8 -->
            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="section-number">8</span> Privacy
                </div>
                <div class="terms-section-text">
                    Giftly collects and processes customer information only as necessary to provide our services, process orders, manage deliveries, and improve the user experience. By using Giftly, you agree to the collection and use of your information in accordance with our Privacy Policy.
                </div>
            </div>

            <!-- Section 9 -->
            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="section-number">9</span> Changes to These Terms
                </div>
                <div class="terms-section-text">
                    Giftly reserves the right to update or modify these Terms and Conditions at any time. Any changes will become effective once posted on the website. Continued use of the website after changes are posted constitutes acceptance of the updated Terms and Conditions.
                </div>
            </div>

            <!-- Section 10 -->
            <div class="terms-section">
                <div class="terms-section-title">
                    <span class="section-number">10</span> Contact Us
                </div>
                <div class="terms-section-text">
                    If you have questions or concerns regarding these Terms and Conditions, please contact the Giftly team through the contact information provided on our website.
                </div>
            </div>

            <div style="background: #fff5f7; border-radius: 12px; padding: 15px 20px; margin-top: 20px; border-left: 3px solid #ff8ba7;">
                <p style="font-size: 13px; color: #555; line-height: 1.6; margin: 0;">
                    <i class="fas fa-heart" style="color: #ff8ba7; margin-right: 8px;"></i>
                    <strong>By placing an order, you acknowledge that you have read, understood, and agreed to these Terms and Conditions.</strong>
                </p>
            </div>
        </div>

        <div class="terms-modal-footer">
            <button class="btn-terms-close" onclick="closeTermsModal()">
                <i class="fas fa-times" style="margin-right: 8px;"></i> Close
            </button>
        </div>
    </div>
</div>

<script>
    /* --- PHONE NUMBER VALIDATION --- */
    function validatePhoneNumber(fieldId) {
        let input = document.getElementById(fieldId);
        let error = document.getElementById(fieldId + '_error');
        let value = input.value.trim();
        
        // Check for letters
        const hasLetter = /[a-zA-Z]/.test(value);
        
        // Remove all non-digits for length check
        const phoneClean = value.replace(/\D/g, '');
        
        // Reset error message
        error.classList.remove('visible');
        input.classList.remove('phone-input-error', 'phone-input-success');
        
        if (value.length === 0) {
            // Empty field - only show error if required
            if (input.hasAttribute('required')) {
                error.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right: 4px;"></i> Phone number is required.';
                error.classList.add('visible');
                input.classList.add('phone-input-error');
            }
            return false;
        }
        
        // Check for letters
        if (hasLetter) {
            error.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right: 4px;"></i> Phone numbers cannot contain letters.';
            error.classList.add('visible');
            input.classList.add('phone-input-error');
            return false;
        }
        
        // Check length (must be exactly 11 digits for PH numbers)
        if (phoneClean.length !== 11) {
            if (phoneClean.length < 11) {
                error.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right: 4px;"></i> Please enter a valid 11-digit phone number. (' + phoneClean.length + '/11 digits)';
            } else {
                error.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right: 4px;"></i> Phone number must be exactly 11 digits.';
            }
            error.classList.add('visible');
            input.classList.add('phone-input-error');
            return false;
        }
        
        // Valid phone number
        input.classList.add('phone-input-success');
        return true;
    }

    /* --- VALIDATE ALL PHONE NUMBERS BEFORE SUBMIT --- */
    function validateAllPhones() {
        let isValid = true;
        
        // Validate sender phone
        if (!validatePhoneNumber('senderPhone')) {
            isValid = false;
        }
        
        // Validate recipient phone if it's visible and has value
        const recipientField = document.getElementById('recipientPhone');
        if (recipientField && recipientField.value.trim() !== '') {
            if (!validatePhoneNumber('recipientPhone')) {
                isValid = false;
            }
        }
        
        return isValid;
    }

    /* --- CONFIRM ORDER MODAL FUNCTIONS --- */
function openConfirmModal() {
    let form = document.getElementById('orderForm');
    
    // First, validate all required fields
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Then validate phone numbers
    if (!validateAllPhones()) {
        // Scroll to the first error
        const firstError = document.querySelector('.phone-input-error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
        return;
    }

    // Card details (only checked when paying by card)
    if (!validateCardIfNeeded()) {
        return;
    }

    // All validations passed - show confirm modal
    isLeaving = true; 
    document.getElementById('confirmModal').style.display = 'flex';
}

function closeConfirmModal() { 
    document.getElementById('confirmModal').style.display = 'none'; 
}

function submitOrder() { 
    document.getElementById('orderForm').submit(); 
}

    /* --- PAYMENT CLICK LOGIC --- */
    window.__onlinePayValue = <?php echo $paymongo_on ? "'online'" : "'card'"; ?>;
    function selectPayment(method) {
        document.getElementById('paymentMethodInput').value = method;
        document.getElementById('payCOD').classList.remove('selected');
        document.getElementById('payCard').classList.remove('selected');
        document.getElementById('pay' + (method === 'cod' ? 'COD' : 'Card')).classList.add('selected');

        var note = document.getElementById('onlinePayNote');
        if (note) note.style.display = (method === 'online') ? 'block' : 'none';

        var cf = document.getElementById('cardFields');
        if (cf) {
            cf.style.display = (method === 'card') ? 'block' : 'none';
            ['cardHolder', 'cardNumber', 'cardExpiry', 'cardCvc'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.required = (method === 'card');
            });
        }
    }

    /* --- card field formatting + validation --- */
    (function () {
        var num = document.getElementById('cardNumber');
        var exp = document.getElementById('cardExpiry');
        var cvc = document.getElementById('cardCvc');
        if (num) num.addEventListener('input', function () {
            var v = this.value.replace(/\D/g, '').slice(0, 19);
            this.value = v.replace(/(.{4})/g, '$1 ').trim();
        });
        if (exp) exp.addEventListener('input', function () {
            var v = this.value.replace(/\D/g, '').slice(0, 4);
            this.value = v.length > 2 ? v.slice(0, 2) + '/' + v.slice(2) : v;
        });
        if (cvc) cvc.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });
    })();

    function validateCardIfNeeded() {
        if (document.getElementById('paymentMethodInput').value !== 'card') return true;
        var digits = (document.getElementById('cardNumber').value || '').replace(/\D/g, '');
        var expv = (document.getElementById('cardExpiry').value || '').trim();
        var cvcv = (document.getElementById('cardCvc').value || '').replace(/\D/g, '');
        var holder = (document.getElementById('cardHolder').value || '').trim();
        if (!holder) { alert('Please enter the name on the card.'); return false; }
        if (digits.length < 13 || digits.length > 19) { alert('Please enter a valid card number.'); return false; }
        if (!/^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(expv)) { alert('Card expiry must be in MM/YY format.'); return false; }
        if (cvcv.length < 3 || cvcv.length > 4) { alert('Please enter a valid CVC.'); return false; }
        return true;
    }

    function selectDelivery(type) {
        document.getElementById('optMe').classList.remove('selected');
        document.getElementById('optRecipient').classList.remove('selected');
        document.getElementById('opt' + (type === 'me' ? 'Me' : 'Recipient')).classList.add('selected');

        const recipientField = document.getElementById('recipientField');
        const giftMessageField = document.getElementById('giftMessageField');
        const recipientName = document.querySelector('input[name="recipient_name"]');
        const recipientPhone = document.querySelector('input[name="recipient_phone"]');

        // Always show gift message field
        giftMessageField.classList.add('show');

        if(type === 'recipient') {
            recipientField.classList.add('show');
            
            // Make recipient fields required
            recipientName.required = true;
            recipientPhone.required = true;
            
            // Payment method restriction
            document.getElementById('payCOD').classList.add('disabled');
            document.getElementById('payCOD').style.pointerEvents = 'none';
            document.getElementById('payCOD').style.opacity = '0.4';
            
            document.getElementById('payCard').classList.remove('disabled');
            document.getElementById('payCard').style.pointerEvents = 'auto';
            document.getElementById('payCard').style.opacity = '1';
            selectPayment(window.__onlinePayValue);
        } else {
            recipientField.classList.remove('show');
            
            // Clear recipient fields
            recipientName.value = '';
            recipientPhone.value = '';
            
            // Remove required attribute from recipient fields
            recipientName.required = false;
            recipientPhone.required = false;
            
            // Remove any validation errors
            document.getElementById('recipientPhone_error').classList.remove('visible');
            document.getElementById('recipientPhone').classList.remove('phone-input-error', 'phone-input-success');
            
            // Enable COD again
            document.getElementById('payCOD').classList.remove('disabled');
            document.getElementById('payCOD').style.pointerEvents = 'auto';
            document.getElementById('payCOD').style.opacity = '1';
        }
    }

    /* --- CHARACTER COUNTER --- */
    function updateCharCount() {
        let textarea = document.getElementById('giftMessageInput');
        let display = document.getElementById('charCountDisplay');
        let currentLength = textarea.value.length;
        let maxLength = textarea.getAttribute('maxlength');
        display.innerText = currentLength + ' / ' + maxLength;
        display.style.color = (currentLength >= maxLength - 10) ? '#d32f2f' : '#888';
    }

    /* --- UPDATE CHECKOUT QUANTITY WITH STOCK CHECK --- */
function updateCheckoutQty(cartId, action) {
    // Get current quantity and stock from the DOM
    let currentQty = parseInt(document.getElementById('cqty_' + cartId).innerText);
    let row = document.getElementById('row_' + cartId);
    let maxStock = parseInt(row.dataset.stock) || 999;

    // If action is 'decrease' and current quantity is 1, DO NOTHING.
    if(action === 'decrease' && currentQty <= 1) {
        return;
    }

    // Proceed with the fetch
    fetch('update_checkout_qty.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'cart_id=' + cartId + '&action=' + action
    })
    .then(response => response.json())
    .then(data => {
        // Check if data is a string (for 'deleted' or 'error')
        if (typeof data === 'string') {
            if (data === 'deleted') {
                document.getElementById('row_' + cartId).style.display = 'none';
                updateCheckoutTotal();
                return;
            } else if (data === 'error') {
                alert("Something went wrong.");
                return;
            }
        }
        
        // Check for error object
        if (data.error) {
            showStockAlert(data.error);
            return;
        }
        
        // Update quantities and totals
        document.getElementById('cqty_' + cartId).innerText = data.new_qty;
        document.getElementById('csub_' + cartId).innerText = 'PHP ' + data.new_subtotal;
        updateCheckoutTotal();
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

/* --- STOCK ALERT MODAL CONTROLS --- */
function showStockAlert(message) {
    document.getElementById('stockAlertMessage').innerHTML = message;
    document.getElementById('stockAlertModal').style.display = 'flex';
}

function closeStockAlert() {
    document.getElementById('stockAlertModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('stockAlertModal').addEventListener('click', function(e) {
    if (e.target === this) closeStockAlert();
});

    /* --- UPDATE CHECKOUT GRAND TOTAL --- */
    function pesoFmt(n) { return 'PHP ' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function promoTermsText(minSpend, maxDisc) {
        var b = [];
        if (Number(minSpend) > 0) b.push('Min spend PHP ' + Math.round(Number(minSpend)).toLocaleString());
        if (Number(maxDisc) > 0) b.push('Max discount PHP ' + Math.round(Number(maxDisc)).toLocaleString());
        return b.join(' · ');
    }

    function currentCartIds() {
        var f = document.getElementById('selectedIdsHidden');
        var ids = (f ? f.value : '').split(',').filter(Boolean);
        // drop rows removed from the summary (qty hit 0)
        return ids.filter(function (id) {
            var row = document.getElementById('row_' + id);
            return !row || row.style.display !== 'none';
        });
    }

    function updateCheckoutTotal() {
        let subtotals = document.querySelectorAll('.os-subtotal');
        let total = 0;
        subtotals.forEach(function(el) {
            if(el.closest('.os-item').style.display !== 'none') {
                let val = parseFloat(el.innerText.replace('PHP ', '').replace(/,/g, ''));
                if(!isNaN(val)) total += val;
            }
        });
        document.getElementById('checkoutGrandTotal').innerText = pesoFmt(total);
        refreshPromo();
    }

    /* --- GIFT WRAPPING & ADD-ONS --- */
    window.__addonsTotal = 0;
    function updateAddonsTotal() {
        var sum = 0;
        document.querySelectorAll('.addon-checkbox:checked').forEach(function (cb) {
            sum += parseFloat(cb.dataset.price) || 0;
        });
        window.__addonsTotal = sum;
        var row = document.getElementById('addonsTotalRow');
        if (row) {
            row.style.display = sum > 0 ? 'flex' : 'none';
            document.getElementById('addonsTotalAmount').innerText = pesoFmt(sum);
        }
        updateCheckoutTotal(); // re-runs the existing subtotal + promo + shipping refresh chain
    }

    /* --- PROMO CODE --- */
    var promoBusy = false;
    function renderPromoSummary(d) {
        if (!d || !d.ok) return;
        document.getElementById('checkoutGrandTotal').innerText = pesoFmt(d.subtotal);
        document.getElementById('checkoutShippingFee').innerText = (d.shipping_fee === 0) ? 'FREE' : pesoFmt(d.shipping_fee);
        document.getElementById('checkoutGrandTotalFinal').innerText = pesoFmt(d.total + (window.__addonsTotal || 0));

        var lines = document.getElementById('promoLines');
        lines.innerHTML = '';
        (d.lines || []).forEach(function (ln) {
            var row = document.createElement('div');
            row.className = 'os-total-row';
            row.style.color = '#2e7d32';
            var right = (Math.abs(ln.amount) < 0.005) ? 'FREE' : '− ' + pesoFmt(Math.abs(ln.amount));
            row.innerHTML = '<span>' + ln.label.replace(/</g, '&lt;') + '</span><span>' + right + '</span>';
            lines.appendChild(row);
        });

        var applied = document.getElementById('promoApplied');
        var inputRow = document.getElementById('promoInputRow');
        var err = document.getElementById('promoError');
        var terms = document.getElementById('promoTerms');
        if (d.code) {
            document.getElementById('promoAppliedCode').textContent = d.code;
            applied.style.display = 'flex';
            inputRow.style.display = 'none';
        } else {
            applied.style.display = 'none';
            inputRow.style.display = 'flex';
        }
        if (terms) {
            var t = promoTermsText(d.code ? d.code_min_spend : 0, d.code ? d.code_max_discount : 0);
            document.getElementById('promoTermsText').textContent = t;
            terms.style.display = t ? 'block' : 'none';
        }
        if (d.code_error) { err.textContent = d.code_error; err.style.display = 'block'; }
        else { err.style.display = 'none'; }

        var nudge = document.getElementById('promoNudge');
        if (nudge) {
            if (d.free_item_nudge) {
                nudge.textContent = '🎁 Add ' + d.free_item_nudge.more + ' more item' + (d.free_item_nudge.more == 1 ? '' : 's') + ' to get a free ' + d.free_item_nudge.name + '!';
                nudge.style.display = 'block';
            } else {
                nudge.style.display = 'none';
            }
        }
    }

    function promoRequest(action, code) {
        if (promoBusy) return;
        promoBusy = true;
        var btn = document.getElementById('promoApplyBtn');
        if (btn) btn.disabled = true;
        var body = 'scope=products&action=' + action + '&ids=' + encodeURIComponent(currentCartIds().join(','));
        if (code) body += '&code=' + encodeURIComponent(code);
        fetch('promo_apply.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body, credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (d) { renderPromoSummary(d); })
            .catch(function () {})
            .finally(function () { promoBusy = false; if (btn) btn.disabled = false; });
    }
    function applyPromo() {
        var code = (document.getElementById('promoCodeInput').value || '').trim();
        if (!code) return;
        promoRequest('apply', code);
    }
    function removePromo() { promoRequest('remove'); }
    function refreshPromo() { promoRequest('refresh'); }

    /* --- DATE LOGIC --- */
    document.addEventListener('DOMContentLoaded', function() {
        const PROCESSING_DAYS = 3; 

        const today = new Date();
        today.setDate(today.getDate() + PROCESSING_DAYS);
        const dd = String(today.getDate()).padStart(2, '0');
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const yyyy = today.getFullYear();
        
        const dateInput = document.getElementById('deliveryDate');
        if(dateInput) {
            dateInput.setAttribute('min', yyyy + '-' + mm + '-' + dd);
            const helperText = document.getElementById('dateHelperText');
            if(helperText) {
                helperText.innerText = '⏳ Please allow at least ' + PROCESSING_DAYS + ' days for processing and delivery.';
            }
        }

        const timeInput = document.getElementById('deliveryTime');
        if(timeInput) {
            if(!timeInput.value) {
                timeInput.value = '08:00';
            }
            timeInput.addEventListener('change', function() {
                validateTime();
            });
        }
    });

    /* --- TIME VALIDATION --- */
    function validateTime() {
        const timeInput = document.getElementById('deliveryTime');
        let timeValue = timeInput.value;

        if(timeValue) {
            let [hours, minutes] = timeValue.split(':').map(Number);
            let message = '';
            
            if(hours < 8) {
                timeInput.value = '08:00';
                message = "Delivery hours start at 8:00 AM. We've adjusted the time for you.";
                showTimeAlert(message);
                return;
            }
            
            if(hours > 20 || (hours === 20 && minutes > 0)) {
                timeInput.value = '20:00';
                message = "Delivery hours end at 8:00 PM. We've adjusted the time for you.";
                showTimeAlert(message);
                return;
            }
        }
    }

    /* --- TIME ALERT MODAL CONTROLS --- */
    function showTimeAlert(message) {
        document.getElementById('timeAlertMessage').innerText = message;
        document.getElementById('timeAlertModal').style.display = 'flex';
    }
    function closeTimeAlert() {
        document.getElementById('timeAlertModal').style.display = 'none';
    }
    document.getElementById('timeAlertModal').addEventListener('click', function(e) {
        if (e.target === this) closeTimeAlert();
    });

    /* --- UNSAVED CHANGES MODAL LOGIC --- */
    let isLeaving = false;
    let targetUrl = '';

    function showUnsavedModal(url) {
        targetUrl = url;
        document.getElementById('unsavedModal').style.display = 'flex';
    }

    function cancelLeave() {
        document.getElementById('unsavedModal').style.display = 'none';
        isLeaving = false;
        targetUrl = '';
    }

    function confirmLeave() {
        document.getElementById('unsavedModal').style.display = 'none';
        isLeaving = true;
        window.location.href = targetUrl;
    }

    document.addEventListener('click', function(e) {
        let link = e.target.closest('a');
        if (link && link.href && !link.href.includes('checkout_selected.php')) {
            e.preventDefault();
            if (!isLeaving) {
                showUnsavedModal(link.href);
            }
        }
    });

    window.addEventListener('beforeunload', function (e) {
        if (!isLeaving && !document.getElementById('orderForm').checkValidity()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    document.getElementById('unsavedModal').addEventListener('click', function(e) {
        if (e.target === this) cancelLeave();
    });

    /* --- SAVED PERSON FILL FUNCTION --- */
    function fillRecipientFields() {
        var select = document.getElementById('savedRecipientSelect');
        if (!select) return;
        var opt = select.options[select.selectedIndex];
        if (!opt || opt.value === '') return;

        document.querySelector('input[name="recipient_name"]').value = opt.getAttribute('data-name') || '';
        var phone = document.getElementById('recipientPhone');
        phone.value = opt.getAttribute('data-phone') || '';
        validatePhoneNumber('recipientPhone');
        document.getElementById('checkoutHouse').value = opt.getAttribute('data-house') || '';
        document.getElementById('checkoutAddress').value = opt.getAttribute('data-street') || '';
        document.getElementById('checkoutCity').value = opt.getAttribute('data-city') || '';
    }

    /* --- ADDRESS FILL FUNCTION --- */
    function fillAddressFields() {
        let select = document.getElementById('addressSelect');
        let selectedOption = select.options[select.selectedIndex];

        if(selectedOption.value !== "") {
            document.getElementById('checkoutAddress').value = selectedOption.getAttribute('data-address');
            document.getElementById('checkoutCity').value = selectedOption.getAttribute('data-city') + ', ' + selectedOption.getAttribute('data-province');
        } else {
            document.getElementById('checkoutAddress').value = '';
            document.getElementById('checkoutCity').value = '';
        }
    }

    /* --- preselect the default saved address, or the most recent one if none is flagged default --- */
    (function () {
        var select = document.getElementById('addressSelect');
        if (!select) return;
        var def = select.querySelector('option[data-default="1"]')
            || select.querySelector('option[value]:not([value=""])');
        if (def && def.value && !document.getElementById('checkoutAddress').value) {
            select.value = def.value;
            fillAddressFields();
        }
    })();

    <?php if ($gift_ctx): ?>
    /* pre-filled via "Send a gift" / "Send again" — put the form in recipient mode */
    selectDelivery('recipient');
    <?php endif; ?>

    /* --- TERMS AND CONDITIONS MODAL CONTROLS --- */
function openTermsModal() {
    document.getElementById('termsModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeTermsModal() {
    document.getElementById('termsModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Close modal when clicking outside
document.getElementById('termsModal').addEventListener('click', function(e) {
    if (e.target === this) closeTermsModal();
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTermsModal();
    }
});
</script>

<?php include 'footer.php'; ?>