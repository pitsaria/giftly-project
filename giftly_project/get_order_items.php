<?php
include 'db_connect.php';

if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    
    // 1. GET ORDER DETAILS (Address, Recipient, Payment, Gift Message)
    $info_sql = "SELECT orders.*, users.name as customer_name 
                 FROM orders 
                 JOIN users ON orders.user_id = users.id 
                 WHERE orders.id = $order_id";
    $info_res = $conn->query($info_sql);
    $info = $info_res->fetch_assoc();

    // 2. GET ITEMS WITH IMAGES
    $sql = "SELECT oi.quantity, oi.price, p.name, p.image 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = $order_id";
    $result = $conn->query($sql);

    // 3. DETERMINE DELIVERY MODE
    $mode_icon = '🏠 Deliver to Me';
    if(!empty($info['recipient_name'])) {
        $mode_icon = '🎁 Deliver to Recipient';
    }
    ?>
    
    <div style="padding: 5px 0;">
        <!-- Header Info -->
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 15px;">
            <div>
                <div style="font-size: 20px; font-weight: 700; color: #222;">Order #<?php echo $info['id']; ?></div>
                <div style="font-size: 14px; color: #888;">Placed by: <strong><?php echo $info['customer_name']; ?></strong></div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 14px; font-weight: 600; color: #ff8ba7;"><?php echo ucfirst($info['payment_method']); ?></div>
                <div style="font-size: 12px; color: #555; background: #f5f5f5; padding: 2px 10px; border-radius: 20px; display: inline-block;"><?php echo $mode_icon; ?></div>
            </div>
        </div>

        <!-- Address & Recipient Details -->
        <div style="background: #fafafa; border-radius: 16px; padding: 15px; margin-bottom: 20px;">
            <div style="font-size: 14px; font-weight: 600; color: #444; margin-bottom: 5px;">📍 Shipping Details</div>
            <div style="font-size: 14px; color: #333; line-height: 1.6;">
                <strong><?php echo $info['fullname']; ?></strong><br>
                <?php echo $info['address']; ?><br>
                <?php echo $info['city']; ?>
            </div>
            <?php if(!empty($info['recipient_name'])): ?>
                <div style="margin-top: 8px; border-top: 1px dashed #eee; padding-top: 8px; font-size: 14px; color: #e65100;">
                    🎁 <strong>Recipient:</strong> <?php echo $info['recipient_name']; ?>
                </div>
            <?php endif; ?>
            <!-- NEW: GIFT MESSAGE -->
                        <?php if(!empty($info['gift_message'])): ?>
                <div style="margin-top: 8px; border-top: 1px dashed #eee; padding-top: 8px; font-size: 14px; color: #1976d2; font-style: italic; word-break: break-word;">
                    💌 <strong>Gift Message:</strong> "<?php echo htmlspecialchars($info['gift_message']); ?>"
                </div>
            <?php endif; ?>
        </div>

        <!-- Item List with Thumbnails -->
        <div style="border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 10px;">
            <div style="font-size: 14px; font-weight: 600; color: #444; margin-bottom: 10px;">📦 Items Ordered</div>
        </div>

        <?php
        $total = 0;
        while($row = $result->fetch_assoc()) {
            $sub = $row['price'] * $row['quantity'];
            $total += $sub;
            echo '
            <div style="display: flex; align-items: center; gap: 15px; padding: 12px 0; border-bottom: 1px solid #f5f5f5;">
                <img src="'.htmlspecialchars(img_url($row['image'])).'" style="width: 50px; height: 50px; object-fit: contain; background: #fafafa; border-radius: 12px; padding: 5px;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 15px; color: #222;">'.$row['name'].'</div>
                    <div style="font-size: 13px; color: #888;">Qty: '.$row['quantity'].'</div>
                </div>
                <div style="font-weight: 600; font-size: 15px; color: #222;">PHP '.number_format($sub, 2).'</div>
            </div>
            ';
        }
        ?>

        <!-- Grand Total -->
        <div style="text-align: right; font-size: 20px; font-weight: 700; color: #222; margin-top: 20px; padding-top: 15px; border-top: 2px solid #ffc1cc;">
            Total: PHP <?php echo number_format($total, 2); ?>
        </div>
    </div>

    <?php
}
?>