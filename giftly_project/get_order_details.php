<?php
include 'db_connect.php';
include_once 'orders_lib.php';
include_once 'reviews_lib.php';
orders_ensure_schema($conn);
reviews_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : 0;

// Fetch Order Info
$order = $conn->query("SELECT * FROM orders WHERE id = $order_id AND user_id = $user_id")->fetch_assoc();

if (!$order) {
    echo '<p style="color:#d32f2f; text-align:center;">Order not found.</p>';
    exit();
}

// Fetch Items
$items = $conn->query("SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $order_id");
?>

<style>
    .modal-cancel-btn {
        background: #fdeded; 
        color: #d32f2f; 
        padding: 12px 30px; 
        border-radius: 50px; 
        border: none; 
        font-size: 14px; 
        font-weight: 600; 
        cursor: pointer; 
        transition: 0.2s; 
        width: 100%;
        margin-top: 20px;
        font-family: 'Poppins';
    }
    .modal-cancel-btn:hover { 
        background: #d32f2f; 
        color: white; 
    }
    
    /* 🚨 FIX FOR LONG GIFT MESSAGE */
    .gift-message-box {
        background: #fafafa;
        border-radius: 12px;
        padding: 15px 20px;
        margin-top: 5px;
        margin-bottom: 10px;
        border-left: 3px solid #ff8ba7;
        word-wrap: break-word;
        word-break: break-word;
        white-space: pre-wrap;
        max-height: 200px;
        overflow-y: auto;
        font-style: italic;
        color: #555;
        line-height: 1.6;
        font-size: 14px;
    }
    /* 🚨 REMOVE EXTRA SPACE AT THE TOP */
    .gift-message-box:empty {
        display: none;
    }
    .gift-message-box p {
        margin: 0;
        padding: 0;
    }
    
    .gift-message-box::-webkit-scrollbar {
        width: 4px;
    }
    .gift-message-box::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    .gift-message-box::-webkit-scrollbar-thumb {
        background: #ffc1cc;
        border-radius: 10px;
    }
    .gift-message-box::-webkit-scrollbar-thumb:hover {
        background: #ff8ba7;
    }
    
    .order-detail-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #f5f5f5;
        font-size: 14px;
    }
    .order-detail-row:last-child {
        border-bottom: none;
    }
    .order-detail-label {
        color: #888;
        flex-shrink: 0;
        min-width: 120px;
    }
    .order-detail-value {
        font-weight: 600;
        color: #222;
        text-align: right;
        flex: 1;
        margin-left: 20px;
        word-wrap: break-word;
    }

    /* --- DELIVERY TIMELINE TRACKER --- */
    .ot-tracker { display: flex; align-items: flex-start; margin-bottom: 18px; padding: 6px 4px 0; }
    .ot-step { display: flex; flex-direction: column; align-items: center; flex: 0 0 auto; width: 84px; }
    .ot-dot {
        width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 700; background: #f0f0f0; color: #aaa; transition: 0.2s; flex-shrink: 0;
    }
    .ot-step.done .ot-dot { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; }
    .ot-step.active .ot-dot { background: #fff; color: #ff8ba7; border: 2.5px solid #ff8ba7; box-shadow: 0 0 0 4px #fff0f5; }
    .ot-label { font-size: 11px; color: #999; margin-top: 6px; text-align: center; line-height: 1.3; }
    .ot-step.done .ot-label, .ot-step.active .ot-label { color: #444; font-weight: 600; }
    .ot-line { flex: 1; height: 3px; background: #f0f0f0; margin-top: 15px; border-radius: 2px; }
    .ot-line.done { background: linear-gradient(90deg, #FEA5B6 0%, #ff8ba7 100%); }
    .ot-note { font-size: 11.5px; color: #a5710d; background: #fff8e1; border-radius: 10px; padding: 8px 12px; margin-bottom: 18px; }
    .ot-cancelled-banner { font-size: 13px; color: #999; background: #f5f5f5; border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; text-align: center; }
</style>

<?php
$order_status_key = $order['status'];
$is_cancelled = $order_status_key === 'cancelled';
$tracker_steps = ['pending' => 'Preparing', 'shipped' => 'Out for Delivery', 'delivered' => 'Delivered'];
$tracker_keys = array_keys($tracker_steps);
$current_step_idx = array_search($order_status_key, $tracker_keys, true);
$awaiting_payment = ($order['payment_method'] ?? 'cod') !== 'cod' && ($order['payment_status'] ?? 'unpaid') !== 'paid';
?>

<?php if ($is_cancelled): ?>
    <div class="ot-cancelled-banner"><i class="fas fa-ban" style="margin-right:6px;"></i> This order was cancelled.</div>
<?php elseif ($current_step_idx !== false): ?>
    <div class="ot-tracker">
        <?php foreach ($tracker_keys as $i => $key):
            $state = $i < $current_step_idx ? 'done' : ($i === $current_step_idx ? 'active' : '');
        ?>
            <div class="ot-step <?php echo $state; ?>">
                <div class="ot-dot"><?php echo $state === 'done' ? '<i class="fas fa-check"></i>' : ($i + 1); ?></div>
                <div class="ot-label"><?php echo htmlspecialchars($tracker_steps[$key]); ?></div>
            </div>
            <?php if ($i < count($tracker_keys) - 1): ?>
                <div class="ot-line <?php echo $i < $current_step_idx ? 'done' : ''; ?>"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php if ($awaiting_payment): ?>
        <div class="ot-note"><i class="fas fa-lock" style="margin-right:5px;"></i> Preparation starts once your payment is confirmed.</div>
    <?php endif; ?>
<?php endif; ?>

<div style="margin-bottom: 20px;">
    <div class="order-detail-row"><span class="order-detail-label">Order ID</span><span class="order-detail-value">#<?php echo $order['id']; ?></span></div>
    <div class="order-detail-row"><span class="order-detail-label">Status</span><span class="order-detail-value" style="text-transform:capitalize;"><?php echo $order['status']; ?></span></div>
    <?php if (!empty($order['discount_amount']) && (float)$order['discount_amount'] > 0): ?>
    <div class="order-detail-row"><span class="order-detail-label">Discount<?php echo !empty($order['promo_code']) ? ' (' . htmlspecialchars($order['promo_code']) . ')' : ''; ?></span><span class="order-detail-value" style="color:#2e7d32;">− PHP <?php echo number_format($order['discount_amount'], 2); ?></span></div>
    <?php endif; ?>
    <div class="order-detail-row"><span class="order-detail-label">Total Paid</span><span class="order-detail-value">PHP <?php echo number_format($order['total_amount'], 2); ?></span></div>
    <div class="order-detail-row"><span class="order-detail-label">Payment</span><span class="order-detail-value"><?php echo ucfirst($order['payment_method']); ?></span></div>
    <div class="order-detail-row"><span class="order-detail-label">Delivery Date</span><span class="order-detail-value"><?php echo date('F j, Y', strtotime($order['delivery_date'])); ?></span></div>
    <div class="order-detail-row"><span class="order-detail-label">Delivery Time</span><span class="order-detail-value"><?php echo date('g:i A', strtotime($order['delivery_time'])); ?></span></div>
    <div class="order-detail-row"><span class="order-detail-label">Shipping Address</span><span class="order-detail-value"><?php echo $order['address'] . ', ' . $order['city']; ?></span></div>
    
    <?php if($order['recipient_name']): ?>
        <div class="order-detail-row"><span class="order-detail-label">Recipient</span><span class="order-detail-value"><?php echo $order['recipient_name']; ?></span></div>
    <?php endif; ?>
    
    <?php if($order['gift_message']): ?>
        <div class="order-detail-row" style="flex-direction: column; align-items: flex-start; padding: 8px 0;">
            <span class="order-detail-label" style="margin-bottom: 5px;">Gift Message</span>
            <!-- 🚨 FIX: Trim the message and use nl2br properly -->
            <div class="gift-message-box"><?php echo nl2br(trim(htmlspecialchars($order['gift_message']))); ?></div>
        </div>
    <?php endif; ?>
</div>

<h4 style="font-size: 16px; font-weight: 600; color: #222; border-bottom: 1px solid #eee; padding-bottom: 10px;">Items Ordered</h4>

<table class="order-items-table">
    <thead>
        <tr><th>Product</th><th>Qty</th><th>Price</th></tr>
    </thead>
    <tbody>
        <?php while($item = $items->fetch_assoc()): ?>
            <tr>
                <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <?php if($item['image']): ?>
                            <img src="<?php echo htmlspecialchars(img_url($item['image'])); ?>" style="width:40px; height:40px; object-fit:cover; border-radius:8px;">
                        <?php endif; ?>
                        <?php echo htmlspecialchars($item['name']); ?>
                        <?php if ((float)$item['price'] <= 0): ?><span style="font-size:11px;font-weight:700;color:#2e7d32;background:#e8f5e9;padding:1px 7px;border-radius:20px;">FREE GIFT</span><?php endif; ?>
                    </div>
                </td>
                <td><?php echo $item['quantity']; ?></td>
                <td><?php echo (float)$item['price'] <= 0 ? '<span style="color:#2e7d32;">FREE</span>' : 'PHP ' . number_format($item['price'], 2); ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php
$cs = $order['cancel_status'] ?? 'none';
if ($order['status'] === 'cancelled' && $cs === 'approved'): ?>
    <div style="margin-top:20px; background:#f5f5f5; border-radius:14px; padding:14px 16px; font-size:13px; color:#777;">
        <i class="fas fa-circle-check" style="color:#2e7d32; margin-right:6px;"></i>
        This order was cancelled — your request was approved by an admin.
        <?php if (!empty($order['cancel_reason'])): ?>
            <div style="margin-top:6px; color:#999;">Reason given: “<?php echo htmlspecialchars($order['cancel_reason']); ?>”</div>
        <?php endif; ?>
    </div>
<?php elseif ($cs === 'requested'): ?>
    <div style="margin-top:20px; background:#fff8e1; border:1px solid #ffe0a3; border-radius:14px; padding:14px 16px; font-size:13px; color:#a5710d;">
        <i class="fas fa-hourglass-half" style="margin-right:6px;"></i>
        Cancellation requested — waiting for an admin to review it.
        <?php if (!empty($order['cancel_reason'])): ?>
            <div style="margin-top:6px; color:#b98a3a;">Your reason: “<?php echo htmlspecialchars($order['cancel_reason']); ?>”</div>
        <?php endif; ?>
    </div>
<?php elseif ($order['status'] === 'delivered' && empty($order['received_at'])): ?>
    <form method="POST" action="profile.php?tab=orders" style="margin-top:20px;">
        <input type="hidden" name="confirm_received" value="1">
        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
        <button type="submit" class="modal-cancel-btn" style="background:#e8f5e9;color:#2e7d32;">
            <i class="fas fa-box-open"></i> Confirm I received this order
        </button>
    </form>
    <div style="font-size:12px;color:#999;margin-top:8px;text-align:center;">Confirming lets you review the items you got.</div>
<?php elseif ($order['status'] === 'delivered' && !empty($order['received_at'])): ?>
    <button class="modal-cancel-btn" style="background:#fff3e0;color:#e65100;" onclick="parent.closeOrderModal(); parent.openReviewModal(<?php echo $order['id']; ?>);">
        <i class="fas fa-star"></i> Review your items
    </button>
<?php elseif ($order['status'] === 'pending'): ?>
    <?php if ($cs === 'rejected' && !empty($order['cancel_admin_note'])): ?>
        <div style="margin-top:16px; background:#fdeded; border:1px solid #ffc1cc; border-radius:14px; padding:12px 16px; font-size:13px; color:#d32f2f;">
            A previous cancellation request was declined: <?php echo htmlspecialchars($order['cancel_admin_note']); ?>
        </div>
    <?php endif; ?>
    <button class="modal-cancel-btn" onclick="parent.openCancelModal(<?php echo $order['id']; ?>)">
        <i class="fas fa-times-circle"></i> Request cancellation
    </button>
<?php endif; ?>