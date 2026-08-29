<?php
$user_id = $_SESSION['user_id'];
include_once 'orders_lib.php';
include_once 'reviews_lib.php';
orders_ensure_schema($conn);
reviews_ensure_schema($conn);

// --- HANDLE "CONFIRM RECEIVED" ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_received'])) {
    $oid = intval($_POST['order_id'] ?? 0);
    $conn->query("UPDATE orders SET received_at = CURRENT_TIMESTAMP
                  WHERE id = $oid AND user_id = " . intval($user_id) . "
                    AND status = 'delivered' AND received_at IS NULL");
    $_SESSION['order_flash'] = $conn->affected_rows > 0
        ? ['type' => 'ok', 'msg' => 'Thanks for confirming! You can now review the items you received.']
        : ['type' => 'error', 'msg' => 'Could not update this order.'];
    header('Location: profile.php?tab=orders');
    exit();
}

// --- HANDLE CANCELLATION REQUEST (shopper states a reason; admin approves) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_cancel'])) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $choice   = trim($_POST['cancel_choice'] ?? '');
    $text     = trim($_POST['cancel_text'] ?? '');

    $valid_choices = orders_cancel_reasons();
    if (!in_array($choice, $valid_choices, true)) {
        $_SESSION['order_flash'] = ['type' => 'error', 'msg' => 'Please pick a reason for cancelling.'];
        header('Location: profile.php?tab=orders');
        exit();
    }
    if ($choice === 'Other' && $text === '') {
        $_SESSION['order_flash'] = ['type' => 'error', 'msg' => 'Please describe your reason for cancelling.'];
        header('Location: profile.php?tab=orders');
        exit();
    }

    $reason = ($choice === 'Other') ? $text : ($choice . ($text !== '' ? ' — ' . $text : ''));
    $reason_esc = $conn->real_escape_string(mb_substr($reason, 0, 1000));

    // only pending orders that aren't already awaiting / granted a cancellation
    $conn->query("UPDATE orders
                  SET cancel_status = 'requested', cancel_reason = '$reason_esc',
                      cancel_requested_at = CURRENT_TIMESTAMP, cancel_reviewed_at = NULL,
                      cancel_admin_note = NULL
                  WHERE id = $order_id AND user_id = $user_id
                    AND status = 'pending' AND cancel_status IN ('none', 'rejected')");

    $_SESSION['order_flash'] = $conn->affected_rows > 0
        ? ['type' => 'ok', 'msg' => 'Cancellation request sent. We\'ll email you once an admin reviews it.']
        : ['type' => 'error', 'msg' => 'This order can no longer be cancelled.'];
    header('Location: profile.php?tab=orders');
    exit();
}

$order_flash = $_SESSION['order_flash'] ?? null;
unset($_SESSION['order_flash']);

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

    .status-badge.cancel-req { background: #fff8e1; color: #a5710d; }
    .cancel-note { display: block; font-size: 11px; color: #999; margin-top: 4px; }
    .cancel-note.declined { color: #d32f2f; }
    .btn-req-cancel { background: #fdeded; color: #d32f2f; border: none; padding: 6px 14px; border-radius: 50px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; font-family: 'Poppins'; }
    .btn-req-cancel:hover { background: #d32f2f; color: #fff; }
    .btn-confirm-recv { background: #e8f5e9; color: #2e7d32; border: none; padding: 6px 14px; border-radius: 50px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: 'Poppins'; }
    .btn-confirm-recv:hover { background: #2e7d32; color: #fff; }
    .btn-review { background: #fff3e0; color: #e65100; border: none; padding: 6px 14px; border-radius: 50px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: 'Poppins'; }
    .btn-review:hover { background: #e65100; color: #fff; }

    /* review modal */
    .rvm-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(6px); z-index: 999999; display: none; justify-content: center; align-items: center; padding: 20px; }
    .rvm-box { background: #fff; border-radius: 28px; padding: 32px; max-width: 480px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.18); animation: fadeUp 0.3s ease; max-height: 88vh; overflow-y: auto; }
    .rvm-box h3 { font-size: 20px; font-weight: 700; color: #222; margin-bottom: 4px; }
    .rvm-box .sub { font-size: 13px; color: #999; margin-bottom: 18px; }
    .rvm-item { border: 1px solid #f0f0f0; border-radius: 16px; padding: 14px; margin-bottom: 12px; }
    .rvm-item .prod { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    .rvm-item .prod img { width: 42px; height: 42px; object-fit: contain; background: #fafafa; border-radius: 10px; padding: 4px; }
    .rvm-item .prod strong { font-size: 14px; color: #222; }
    .rvm-pick { display: flex; gap: 4px; font-size: 22px; color: #ddd; margin-bottom: 8px; }
    .rvm-pick i { cursor: pointer; }
    .rvm-pick i.on { color: #ffb400; }
    .rvm-item textarea { width: 100%; min-height: 54px; border: 1.5px solid #eee; border-radius: 12px; padding: 9px 12px; font-family: 'Poppins'; font-size: 13px; resize: vertical; outline: none; background: #fafafa; }
    .rvm-item textarea:focus { border-color: #ffc1cc; background: #fff; }
    .rvm-item .save { margin-top: 8px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border: none; border-radius: 50px; padding: 7px 18px; font-family: 'Poppins'; font-weight: 600; font-size: 12px; cursor: pointer; }
    .rvm-item .st { font-size: 11px; margin-left: 10px; }
    .rvm-close { margin-top: 8px; width: 100%; padding: 12px; border: none; border-radius: 50px; background: #eaeaea; color: #555; font-family: 'Poppins'; font-weight: 600; cursor: pointer; }

    .of-flash { padding: 12px 18px; border-radius: 14px; margin-bottom: 20px; font-size: 14px; }
    .of-flash.ok { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; }
    .of-flash.error { background: #fdeded; border: 1px solid #ffc1cc; color: #d32f2f; }

    /* reason modal */
    .rc-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(6px); z-index: 999999; display: none; justify-content: center; align-items: center; padding: 20px; }
    .rc-box { background: #fff; border-radius: 28px; padding: 34px; max-width: 440px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.18); animation: fadeUp 0.3s ease; max-height: 88vh; overflow-y: auto; }
    .rc-box h3 { font-size: 20px; font-weight: 700; color: #222; margin-bottom: 4px; }
    .rc-box .sub { font-size: 13.5px; color: #999; margin-bottom: 20px; }
    .rc-opt { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border: 1.5px solid #eee; border-radius: 14px; margin-bottom: 8px; cursor: pointer; font-size: 14px; color: #444; transition: 0.15s; }
    .rc-opt:hover { border-color: #ffc1cc; }
    .rc-opt input { accent-color: #ff8ba7; width: 16px; height: 16px; }
    .rc-opt.sel { border-color: #ff8ba7; background: #fff0f5; }
    .rc-box textarea { width: 100%; min-height: 80px; border: 1.5px solid #eee; border-radius: 14px; padding: 12px 14px; font-family: 'Poppins'; font-size: 14px; resize: vertical; outline: none; background: #fafafa; margin-top: 6px; }
    .rc-box textarea:focus { border-color: #ffc1cc; background: #fff; }
    .rc-actions { display: flex; gap: 10px; margin-top: 18px; }
    .rc-actions button { flex: 1; padding: 13px; border: none; border-radius: 50px; font-family: 'Poppins'; font-weight: 600; font-size: 14px; cursor: pointer; }
    .rc-actions .keep { background: #eaeaea; color: #555; }
    .rc-actions .submit { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; }
</style>

<div class="page-title">Order History</div>

<?php if ($order_flash): ?>
    <div class="of-flash <?php echo $order_flash['type'] === 'ok' ? 'ok' : 'error'; ?>">
        <i class="fas fa-<?php echo $order_flash['type'] === 'ok' ? 'circle-check' : 'circle-exclamation'; ?>" style="margin-right:6px;"></i>
        <?php echo htmlspecialchars($order_flash['msg']); ?>
    </div>
<?php endif; ?>

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
                $cs = $row['cancel_status'] ?? 'none';
                $can_request = ($row['status'] === 'pending' && in_array($cs, ['none', 'rejected'], true));
                $delivered = ($row['status'] === 'delivered');
                $received  = $delivered && !empty($row['received_at']);
            ?>
            <tr>
                <td><strong>#<?php echo $row['id']; ?></strong></td>
                <td><strong>PHP <?php echo number_format($row['total_amount'], 2); ?></strong></td>
                <td>
                    <?php if ($row['status'] === 'cancelled'): ?>
                        <span class="status-badge cancelled">Cancelled</span>
                        <?php if ($cs === 'approved'): ?><span class="cancel-note">Cancellation approved</span><?php endif; ?>
                    <?php elseif ($cs === 'requested'): ?>
                        <span class="status-badge cancel-req">Cancellation requested</span>
                        <span class="cancel-note">Awaiting admin approval</span>
                    <?php else: ?>
                        <span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span>
                        <?php if ($cs === 'rejected'): ?>
                            <span class="cancel-note declined">Cancellation declined<?php echo !empty($row['cancel_admin_note']) ? ' — ' . htmlspecialchars($row['cancel_admin_note']) : ''; ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td style="color: #888;"><?php echo date('F j, Y', strtotime($row['created_at'])); ?></td>
                <td>
                    <div class="order-actions">
                        <button class="btn-order-view" onclick="openOrderModal(<?php echo $row['id']; ?>)">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <?php if ($can_request): ?>
                            <button class="btn-req-cancel" onclick="openReasonModal(<?php echo $row['id']; ?>)">
                                <i class="fas fa-xmark"></i> Cancel
                            </button>
                        <?php endif; ?>
                        <?php if ($delivered && !$received): ?>
                            <form method="POST" action="profile.php?tab=orders" style="margin:0;">
                                <input type="hidden" name="confirm_received" value="1">
                                <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn-confirm-recv"><i class="fas fa-box-open"></i> Confirm received</button>
                            </form>
                        <?php elseif ($received): ?>
                            <button class="btn-review" onclick="openReviewModal(<?php echo $row['id']; ?>)">
                                <i class="fas fa-star"></i> Review items
                            </button>
                        <?php endif; ?>
                    </div>
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

<!-- CANCELLATION REASON MODAL -->
<div class="rc-overlay" id="reasonModal">
    <div class="rc-box">
        <h3>Request order cancellation</h3>
        <div class="sub">Tell us why — an admin will review and approve it. Your order stays active until then.</div>
        <form method="POST" action="profile.php?tab=orders" id="reasonForm">
            <input type="hidden" name="request_cancel" value="1">
            <input type="hidden" name="order_id" id="rcOrderId" value="">
            <?php foreach (orders_cancel_reasons() as $reason): ?>
                <label class="rc-opt">
                    <input type="radio" name="cancel_choice" value="<?php echo htmlspecialchars($reason); ?>" onchange="rcSync()">
                    <span><?php echo htmlspecialchars($reason); ?></span>
                </label>
            <?php endforeach; ?>
            <textarea name="cancel_text" id="rcText" maxlength="600" placeholder="Add more detail (required if you pick “Other”)"></textarea>
            <div class="rc-actions">
                <button type="button" class="keep" onclick="closeReasonModal()">Keep order</button>
                <button type="submit" class="submit" id="rcSubmit" disabled>Submit request</button>
            </div>
        </form>
    </div>
</div>

<script>
    /* --- ORDER DETAILS MODAL --- */
    function openOrderModal(orderId) {
        document.getElementById('orderModal').style.display = 'flex';
        fetch('get_order_details.php?order_id=' + orderId)
            .then(r => r.text())
            .then(d => { document.getElementById('orderModalContent').innerHTML = d; })
            .catch(() => { document.getElementById('orderModalContent').innerHTML = '<p style="color:#d32f2f; text-align:center;">Error loading order details.</p>'; });
    }
    function closeOrderModal() { document.getElementById('orderModal').style.display = 'none'; }
    document.getElementById('orderModal').addEventListener('click', function (e) { if (e.target === this) closeOrderModal(); });

    /* --- CANCELLATION REASON MODAL --- */
    function openReasonModal(orderId) {
        document.getElementById('rcOrderId').value = orderId;
        document.getElementById('reasonForm').reset();
        document.getElementById('rcOrderId').value = orderId;
        rcSync();
        document.getElementById('reasonModal').style.display = 'flex';
    }
    function closeReasonModal() { document.getElementById('reasonModal').style.display = 'none'; }
    // parent.openReasonModal is called from the details modal's Cancel button
    function openCancelModal(orderId) { closeOrderModal(); openReasonModal(orderId); }

    function rcSync() {
        const picked = document.querySelector('#reasonForm input[name="cancel_choice"]:checked');
        document.querySelectorAll('#reasonForm .rc-opt').forEach(o => o.classList.toggle('sel', o.querySelector('input').checked));
        const isOther = picked && picked.value === 'Other';
        const text = document.getElementById('rcText').value.trim();
        document.getElementById('rcSubmit').disabled = !picked || (isOther && text === '');
    }
    document.getElementById('rcText').addEventListener('input', rcSync);
    document.getElementById('reasonModal').addEventListener('click', function (e) { if (e.target === this) closeReasonModal(); });

    /* --- REVIEW ITEMS MODAL --- */
    function openReviewModal(orderId) {
        const m = document.getElementById('reviewModal');
        m.style.display = 'flex';
        document.getElementById('rvmBody').innerHTML = '<div style="text-align:center;color:#aaa;padding:20px;"><i class="fas fa-spinner fa-spin"></i></div>';
        fetch('get_order_review_items.php?order_id=' + orderId)
            .then(r => r.text())
            .then(h => { document.getElementById('rvmBody').innerHTML = h; rvmBindStars(); })
            .catch(() => { document.getElementById('rvmBody').innerHTML = '<p style="color:#d32f2f;text-align:center;">Could not load items.</p>'; });
    }
    function closeReviewModal() { document.getElementById('reviewModal').style.display = 'none'; }
    document.getElementById('reviewModal').addEventListener('click', function (e) { if (e.target === this) closeReviewModal(); });

    function rvmBindStars() {
        document.querySelectorAll('#rvmBody .rvm-pick').forEach(function (pick) {
            const stars = pick.querySelectorAll('i');
            const input = pick.parentElement.querySelector('.rvm-rating');
            function paint(v) { stars.forEach(s => s.classList.toggle('on', parseInt(s.dataset.v) <= v)); }
            stars.forEach(function (s) {
                s.addEventListener('mouseenter', () => paint(parseInt(s.dataset.v)));
                s.addEventListener('click', () => { input.value = s.dataset.v; paint(parseInt(s.dataset.v)); });
            });
            pick.addEventListener('mouseleave', () => paint(parseInt(input.value) || 0));
        });
    }

    function rvmSave(btn, productId) {
        const item = btn.closest('.rvm-item');
        const rating = item.querySelector('.rvm-rating').value;
        const comment = item.querySelector('textarea').value;
        const st = item.querySelector('.st');
        if (!rating || rating < 1) { st.style.color = '#d32f2f'; st.textContent = 'Pick a rating'; return; }
        st.style.color = '#888'; st.textContent = 'Saving…';
        fetch('submit_review.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'product_id=' + productId + '&rating=' + rating + '&comment=' + encodeURIComponent(comment)
        }).then(r => r.json()).then(d => {
            if (d.status === 'success') { st.style.color = '#2e7d32'; st.textContent = 'Saved ✓'; btn.textContent = 'Update'; }
            else { st.style.color = '#d32f2f'; st.textContent = d.message || 'Failed'; }
        }).catch(() => { st.style.color = '#d32f2f'; st.textContent = 'Network error'; });
    }
</script>

<!-- REVIEW ITEMS MODAL -->
<div class="rvm-overlay" id="reviewModal">
    <div class="rvm-box">
        <h3>Review your items</h3>
        <div class="sub">Only you and other shoppers will see this. Be honest — it helps everyone.</div>
        <div id="rvmBody"></div>
        <button class="rvm-close" onclick="closeReviewModal()">Done</button>
    </div>
</div>