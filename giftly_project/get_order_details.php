<?php
include 'db_connect.php';

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
</style>

<div style="margin-bottom: 20px;">
    <div class="order-detail-row"><span class="order-detail-label">Order ID</span><span class="order-detail-value">#<?php echo $order['id']; ?></span></div>
    <div class="order-detail-row"><span class="order-detail-label">Status</span><span class="order-detail-value" style="text-transform:capitalize;"><?php echo $order['status']; ?></span></div>
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
                            <img src="uploads/<?php echo $item['image']; ?>" style="width:40px; height:40px; object-fit:cover; border-radius:8px;">
                        <?php endif; ?>
                        <?php echo $item['name']; ?>
                    </div>
                </td>
                <td><?php echo $item['quantity']; ?></td>
                <td>PHP <?php echo number_format($item['price'], 2); ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<!-- 🚨 CANCEL BUTTON - ONLY SHOW IF ORDER IS PENDING -->
<?php if($order['status'] == 'pending'): ?>
    <button class="modal-cancel-btn" onclick="parent.openCancelModal(<?php echo $order['id']; ?>)">
        <i class="fas fa-times-circle"></i> Cancel Order
    </button>
<?php endif; ?>