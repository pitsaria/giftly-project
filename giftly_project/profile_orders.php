<?php
$user_id = $_SESSION['user_id'];

// --- HANDLE ORDER CANCELLATION - DIRECT DATABASE ---
if (isset($_GET['cancel_order'])) {
    $order_id = intval($_GET['cancel_order']);
    
    // ✅ DIRECT DATABASE - NO API
    $conn->query("UPDATE orders SET status = 'cancelled' WHERE id = $order_id AND user_id = $user_id");
    
    // Redirect back
    echo '<meta http-equiv="refresh" content="0; url=profile.php?tab=orders">';
    exit();
}

// --- FETCH ALL ORDERS - DIRECT DATABASE ---
$orders = [];
$sql = "SELECT * FROM orders WHERE user_id = $user_id ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>

<style>
    .order-table { width: 100%; border-collapse: collapse; }
    .order-table th { text-align: left; padding: 12px 10px; border-bottom: 2px solid #f0f0f0; color: #444; font-weight: 600; font-size: 14px; }
    .order-table td { padding: 16px 10px; border-bottom: 1px solid #f5f5f5; font-size: 14px; color: #333; vertical-align: middle; }
    .status-badge { padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block; }
    .status-badge.pending { background: #fff0f5; color: #d32f2f; }
    .status-badge.shipped { background: #fff3e0; color: #e65100; }
    .status-badge.delivered { background: #e8f5e9; color: #2e7d32; }
    .status-badge.cancelled { background: #f5f5f5; color: #999; text-decoration: line-through; }

    /* Action Buttons */
    .order-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .btn-order-view { background: #f3f3f3; color: #555; padding: 6px 16px; border-radius: 50px; border: none; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .btn-order-view:hover { background: #ffc1cc; color: white; }

    /* --- ORDER DETAILS MODAL --- */
    .order-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(6px);
        z-index: 999999; display: none; justify-content: center; align-items: center; padding: 20px;
    }
    .order-modal-box {
        background: #ffffff; 
        border-radius: 30px; 
        padding: 40px; 
        max-width: 550px; 
        width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15); 
        position: relative; 
        animation: fadeUp 0.3s ease;
        max-height: 85vh;  
        overflow-y: auto;  
        overflow-x: hidden; 
        
        /* 🚨 HIDE SCROLLBAR FOR CHROME/SAFARI */
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none; /* IE and Edge */
    }
    
    /* 🚨 HIDE SCROLLBAR FOR CHROME/SAFARI */
    .order-modal-box::-webkit-scrollbar {
        display: none;
    }
    
    .order-modal-close {
        position: absolute; top: 15px; right: 20px; font-size: 24px; color: #888; cursor: pointer; transition: 0.2s;
    }
    .order-modal-close:hover { color: #ff8ba7; transform: rotate(90deg); }
    @keyframes fadeUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    
    .order-detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f5f5; font-size: 14px; }
    .order-detail-row:last-child { border-bottom: none; }
    .order-detail-label { color: #888; }
    .order-detail-value { font-weight: 600; color: #222; }
    
    .order-items-table { width: 100%; margin-top: 15px; border-collapse: collapse; background: #fafafa; border-radius: 12px; overflow: hidden; }
    .order-items-table th { background: #f0f0f0; padding: 10px; text-align: left; font-size: 13px; color: #555; }
    .order-items-table td { padding: 10px; border-bottom: 1px solid #eee; font-size: 14px; }
    .order-items-table tr:last-child td { border-bottom: none; }

    /* Cancel button inside modal */
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

    /* --- ORDER CANCEL CONFIRM MODAL STYLES --- */
    .cancel-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(6px);
        z-index: 999999; display: none; justify-content: center; align-items: center; padding: 20px;
    }
    .cancel-modal-box {
        background: #ffffff; border-radius: 30px; padding: 40px; max-width: 400px; width: 90%;
        text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        animation: fadeUp 0.3s ease;
    }
    @keyframes fadeUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .cancel-icon { font-size: 50px; color: #d32f2f; margin-bottom: 15px; }
    .cancel-modal-title { font-size: 22px; font-weight: 700; color: #222; margin-bottom: 5px; }
    .cancel-modal-sub { font-size: 14px; color: #888; margin-bottom: 25px; line-height: 1.5; }
    .cancel-buttons { display: flex; gap: 15px; justify-content: center; }
    .btn-cancel-no { 
        flex: 1; padding: 14px; border: none; border-radius: 50px;
        background: #eaeaea; color: #555; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Poppins';
    }
    .btn-cancel-no:hover { background: #d6d6d6; }
    .btn-cancel-yes { 
        flex: 1; padding: 14px; border: none; border-radius: 50px;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Poppins';
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
    }
    .btn-cancel-yes:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4); }
</style>

<div class="page-title">Order History</div>

<?php if (!empty($orders)): ?>
    <table class="order-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
        </thead>
                <tbody>
            <?php foreach($orders as $row): 
                $status_class = strtolower($row['status']);
                $is_cancellable = ($row['status'] == 'pending');
            ?>
            <tr>
                <td><strong>#<?php echo $row['id']; ?></strong></td>
                <td><strong>PHP <?php echo number_format($row['total_amount'], 2); ?></strong></td>
                <td><span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                <td style="color: #888;"><?php echo date('F j, Y', strtotime($row['created_at'])); ?></td>
                <td>
                    <!-- Only View Button now -->
                    <button class="btn-order-view" onclick="openOrderModal(<?php echo $row['id']; ?>)">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p style="color:#888; text-align:center; padding:40px;">You haven't placed any orders yet. <a href="shop.php" style="color:#ff8ba7;">Start shopping!</a></p>
<?php endif; ?>

<!-- 🚨 ORDER DETAILS MODAL -->
<div class="order-modal-overlay" id="orderModal">
    <div class="order-modal-box">
        <div class="order-modal-close" onclick="closeOrderModal()">&times;</div>
        <h3 style="font-size: 22px; font-weight: 700; color: #222; margin-bottom: 20px;">Order Details</h3>
        
        <div id="orderModalContent">
            <!-- Content will be loaded via AJAX -->
            <div style="text-align: center; padding: 20px; color: #888;">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i> Loading...
            </div>
        </div>
    </div>
</div>

<!-- 🚨 ORDER CANCEL CONFIRMATION MODAL -->
<div class="cancel-modal-overlay" id="cancelConfirmModal">
    <div class="cancel-modal-box">
        <div class="cancel-icon"><i class="fas fa-times-circle" style="background: #fdeded; padding: 15px; border-radius: 50%;"></i></div>
        <div class="cancel-modal-title">Cancel this order?</div>
        <div class="cancel-modal-sub" id="cancelModalMessage">Are you sure you want to cancel this order? This action cannot be undone.</div>
        <div class="cancel-buttons">
            <button class="btn-cancel-no" onclick="closeCancelModal()">Keep Order</button>
            <button class="btn-cancel-yes" id="confirmCancelBtn">Yes, Cancel Order</button>
        </div>
    </div>
</div>

<script>
    /* --- ORDER DETAILS MODAL CONTROLS --- */
    let currentOrderId = 0;

    function openOrderModal(orderId) {
        currentOrderId = orderId;
        document.getElementById('orderModal').style.display = 'flex';
        
        // Fetch order details via AJAX
        fetch('get_order_details.php?order_id=' + orderId)
            .then(response => response.text())
            .then(data => {
                document.getElementById('orderModalContent').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('orderModalContent').innerHTML = '<p style="color:#d32f2f; text-align:center;">Error loading order details.</p>';
            });
    }

    function closeOrderModal() {
        document.getElementById('orderModal').style.display = 'none';
        currentOrderId = 0;
    }

    // Close modal when clicking outside
    document.getElementById('orderModal').addEventListener('click', function(e) {
        if (e.target === this) closeOrderModal();
    });

    /* --- ORDER CANCEL CONFIRMATION MODAL CONTROLS --- */
    let cancelTargetId = 0;

    function openCancelModal(orderId) {
        cancelTargetId = orderId;
        document.getElementById('cancelModalMessage').textContent = 
            'Are you sure you want to cancel order #' + orderId + '? This action cannot be undone.';
        document.getElementById('cancelConfirmModal').style.display = 'flex';
    }

    function closeCancelModal() {
        document.getElementById('cancelConfirmModal').style.display = 'none';
        cancelTargetId = 0;
    }

    document.getElementById('confirmCancelBtn').addEventListener('click', function() {
        if(cancelTargetId > 0) {
            // Redirect to the cancel URL
            window.location.href = 'profile.php?tab=orders&cancel_order=' + cancelTargetId;
        }
    });

    // Close modal when clicking outside the white box
    document.getElementById('cancelConfirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeCancelModal();
    });
</script>