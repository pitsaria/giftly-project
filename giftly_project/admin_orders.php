<?php
include 'db_connect.php';
include_once 'orders_lib.php';
include_once 'paymongo_lib.php';
orders_ensure_schema($conn);
pay_ensure_schema($conn);

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$user_check = $conn->query("SELECT role FROM users WHERE id = $user_id");
$user_data = $user_check->fetch_assoc();
if ($user_data['role'] !== 'admin') {
    header("Location: shop.php");
    exit();
}

// --- HANDLE STATUS UPDATE DIRECTLY IN THIS FILE ---
$show_updated = false;
$flash = null;
if (!empty($_SESSION['order_update_error'])) {
    $flash = ['error', $_SESSION['order_update_error']];
    unset($_SESSION['order_update_error']);
}
if (isset($_POST['update_status_here']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $allowed_statuses = ['pending', 'shipped', 'delivered'];

    // A delivered (or cancelled) order is final — its status can't be changed anymore.
    $cur = $conn->query("SELECT status FROM orders WHERE id = $order_id");
    $cur_status = $cur ? ($cur->fetch_assoc()['status'] ?? '') : '';

    if (in_array($cur_status, ['delivered', 'cancelled'], true)) {
        $flash = ['error', 'This order is marked "' . $cur_status . '" and its status can no longer be changed.'];
    } elseif (!in_array($new_status, $allowed_statuses, true)) {
        $flash = ['error', 'Invalid status.'];
    } else {
        $sql = "UPDATE orders SET status = '$new_status' WHERE id = $order_id";
        if ($conn->query($sql) === TRUE) {
            $show_updated = true; // Show the banner right here
        }
    }
}

// --- HANDLE CANCELLATION REVIEW ---
if (isset($_POST['approve_cancel'])) {
    $ok = orders_approve_cancel($conn, intval($_POST['order_id'] ?? 0));
    $flash = $ok
        ? ['ok', 'Cancellation approved — the order is cancelled and stock has been restored.']
        : ['error', 'Could not approve this cancellation.'];
}
if (isset($_POST['reject_cancel'])) {
    $ok = orders_reject_cancel($conn, intval($_POST['order_id'] ?? 0), $_POST['admin_note'] ?? '');
    $flash = $ok
        ? ['ok', 'Cancellation request declined — the order continues.']
        : ['error', 'Could not decline this request.'];
}
// -------------------------------------------------

include 'admin_header.php';

$pending_cancels = 0;
$pc_res = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE cancel_status = 'requested'");
if ($pc_res) $pending_cancels = (int) $pc_res->fetch_assoc()['c'];

$filter_status  = (isset($_GET['filter_status'])  && is_string($_GET['filter_status']))  ? preg_replace('/[^a-z_]/', '', $_GET['filter_status'])  : '';
$filter_payment = (isset($_GET['filter_payment']) && is_string($_GET['filter_payment'])) ? preg_replace('/[^a-z]/', '', $_GET['filter_payment']) : '';
$filter_mode    = (isset($_GET['filter_mode'])    && is_string($_GET['filter_mode']))    ? preg_replace('/[^a-z]/', '', $_GET['filter_mode'])    : '';

// Shared WHERE fragment for the count + list
$order_where = "";
if ($filter_status === 'cancel_requested') {
    $order_where .= " AND orders.cancel_status = 'requested'";
} elseif (in_array($filter_status, ['pending', 'shipped', 'delivered', 'cancelled'], true)) {
    $order_where .= " AND orders.status = '$filter_status'";
}
if (in_array($filter_payment, ['cod', 'card'], true)) {
    $order_where .= " AND orders.payment_method = '$filter_payment'";
}
if ($filter_mode === 'me') {
    $order_where .= " AND (orders.recipient_name IS NULL OR orders.recipient_name = '')";
} elseif ($filter_mode === 'recipient') {
    $order_where .= " AND orders.recipient_name IS NOT NULL AND orders.recipient_name != ''";
}

// --- PAGINATION LOGIC ---
$limit = 8; // Orders per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$count_res = $conn->query("SELECT COUNT(*) as total FROM orders JOIN users ON orders.user_id = users.id WHERE 1=1" . $order_where);
$total_rows = $count_res ? (int) $count_res->fetch_assoc()['total'] : 0;
$total_pages = max(1, (int) ceil($total_rows / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }
$showing_from = $total_rows ? $offset + 1 : 0;
$showing_to = min($offset + $limit, $total_rows);
// ------------------------------------------------
?>

<style>
    .main-wrapper { max-width: 1200px; margin: 0 auto; padding: 40px 20px; width: 100%; flex: 1; }
    .admin-table-card { background: #fff; border-radius: 24px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); overflow-x: auto; }
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th { text-align: left; padding: 15px 10px; border-bottom: 2px solid #f0f0f0; color: #444; font-weight: 600; font-size: 14px; }
    .admin-table td { padding: 20px 10px; border-bottom: 1px solid #f5f5f5; font-size: 14px; color: #333; vertical-align: middle; }
    .admin-table tr:last-child td { border-bottom: none; }
    .status-badge { padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block; }
    .status-pending { background: #fff0f5; color: #d32f2f; }
    .status-shipped { background: #fff3e0; color: #e65100; }
    .status-delivered { background: #e8f5e9; color: #2e7d32; }
    
    .status-select { border: none; padding: 6px 12px; border-radius: 30px; font-size: 12px; font-weight: 600; cursor: pointer; outline: none; font-family: 'Poppins'; transition: 0.2s; background: #f3f3f3; color: #333; }
    .status-select:focus { box-shadow: 0 0 0 2px #ffc1cc; }
    .btn-view-items { background: #f3f3f3; border: none; padding: 6px 16px; border-radius: 30px; font-size: 13px; font-weight: 500; cursor: pointer; transition: 0.2s; margin-left: 8px; }
    .btn-view-items:hover { background: #ffc1cc; color: white; }
    
    /* --- SEARCH BAR (NEW!) --- */
    .search-container { 
        display: flex; align-items: center; gap: 15px;
        background: #fff; border: 1.5px solid #eee; border-radius: 30px;
        padding: 6px 16px 6px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        transition: 0.3s;
    }
    .search-container:focus-within { border-color: #ffc1cc; box-shadow: 0 0 0 3px rgba(255, 193, 204, 0.1); }
    .search-container input { border: none; outline: none; width: 100%; font-size: 13px; font-family: 'Poppins'; background: transparent; }
    .search-container i { color: #888; }

    .filter-bar { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; align-items: center; }
    .filter-select { padding: 8px 16px; border: 1.5px solid #eee; border-radius: 30px; font-size: 13px; font-family: 'Poppins'; background: #fff; color: #555; outline: none; cursor: pointer; transition: 0.2s; }
    .filter-select:focus, .filter-select:hover { border-color: #ffc1cc; }
        .filter-btn { 
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; padding: 8px 20px; border: none; border-radius: 30px; font-weight: 600; 
        cursor: pointer; transition: 0.2s; font-family: 'Poppins'; 
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
    }
    .filter-btn:hover { 
        background: linear-gradient(135deg, #ff8ba7 0%, #FEA5B6 100%); 
        transform: translateY(-2px); 
        box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4);
    }

    .alert-success { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; padding: 15px 20px; border-radius: 16px; margin-bottom: 25px; text-align: center; font-weight: 500; transition: opacity 0.5s ease; }
    .prod-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 8px; background: #fafafa; }
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px); display: none; justify-content: center; align-items: center; z-index: 9999; }
    .modal-box { background: #fff; border-radius: 30px; padding: 40px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 50px rgba(0,0,0,0.2); position: relative; }
    .modal-close { position: absolute; top: 15px; right: 20px; font-size: 24px; color: #888; cursor: pointer; transition: 0.2s; }
    .modal-close:hover { color: #ff8ba7; transform: rotate(90deg); }
    .modal-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
    .modal-item:last-child { border-bottom: none; }
    .modal-total { text-align: right; font-size: 18px; font-weight: 700; margin-top: 15px; padding-top: 15px; border-top: 2px solid #ffc1cc; }

    /* --- PAGINATION STYLES --- */
    .pagination-wrapper { display: flex; justify-content: center; gap: 8px; margin-top: 30px; margin-bottom: 20px; flex-wrap: wrap; }
    .page-btn { padding: 8px 16px; border: 1.5px solid #eee; border-radius: 30px; background: #fff; color: #555; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.2s; font-family: 'Poppins'; }
    .page-btn:hover { background: #ffc1cc; color: #fff; border-color: #ffc1cc; }
    .page-btn.active { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border-color: #FEA5B6; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.3); }
    .page-btn.disabled { opacity: 0.5; pointer-events: none; }
</style>

<div class="main-wrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-size: 26px; font-weight: 600; color: #222; margin-bottom: 5px;">Customer Orders</h2>
            <p style="color: #888; font-size: 14px;">Track, view, and update order statuses</p>
        </div>
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            
            <!-- SEARCH BAR (NEW!) -->
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="adminOrderSearch" placeholder="Search orders...">
            </div>

            <a href="admin_dashboard.php" style="background: #f3f3f3; padding: 8px 20px; border-radius: 50px; font-size: 14px; font-weight: 500; color: #555; text-decoration: none; transition: 0.3s;">&larr; Dashboard</a>
        </div>
    </div>

    <!-- SUCCESS BANNER -->
    <?php if ($show_updated): ?>
        <div id="alertUpdated" class="alert-success">
            <i class="fas fa-check-circle" style="margin-right: 8px;"></i> Order status updated successfully!
        </div>
        <script>
            setTimeout(function(){
                var e = document.getElementById('alertUpdated');
                if(e) { e.style.opacity='0'; setTimeout(()=>e.style.display='none',500); }
            }, 3000);
        </script>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="alert-success" style="<?php echo $flash[0] === 'error' ? 'background:#fdeded;border-color:#ffc1cc;color:#d32f2f;' : ''; ?>">
            <i class="fas fa-<?php echo $flash[0] === 'error' ? 'circle-exclamation' : 'check-circle'; ?>" style="margin-right:8px;"></i>
            <?php echo htmlspecialchars($flash[1]); ?>
        </div>
    <?php endif; ?>

    <?php if ($pending_cancels > 0 && $filter_status !== 'cancel_requested'): ?>
        <div style="background:#fff8e1; border:1px solid #ffe0a3; color:#a5710d; padding:14px 20px; border-radius:16px; margin-bottom:20px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <i class="fas fa-hourglass-half"></i>
            <strong><?php echo $pending_cancels; ?></strong> cancellation request<?php echo $pending_cancels === 1 ? '' : 's'; ?> waiting for your review.
            <a href="admin_orders.php?filter_status=cancel_requested" style="margin-left:auto; background:#ff8ba7; color:#fff; padding:6px 16px; border-radius:50px; font-size:13px; font-weight:600; text-decoration:none;">Review now</a>
        </div>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <form action="admin_orders.php" method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <select name="filter_status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="shipped" <?php echo ($filter_status == 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                <option value="delivered" <?php echo ($filter_status == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                <option value="cancelled" <?php echo ($filter_status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                <option value="cancel_requested" <?php echo ($filter_status == 'cancel_requested') ? 'selected' : ''; ?>>⏳ Cancellation requested</option>
            </select>
            <select name="filter_payment" class="filter-select">
                <option value="">All Payments</option>
                <option value="cod" <?php echo ($filter_payment == 'cod') ? 'selected' : ''; ?>>Cash on Delivery</option>
                <option value="card" <?php echo ($filter_payment == 'card') ? 'selected' : ''; ?>>Credit / Debit</option>
            </select>
            <select name="filter_mode" class="filter-select">
                <option value="">All Modes</option>
                <option value="me" <?php echo ($filter_mode == 'me') ? 'selected' : ''; ?>>Deliver to Me</option>
                <option value="recipient" <?php echo ($filter_mode == 'recipient') ? 'selected' : ''; ?>>Deliver to Recipient</option>
            </select>
            <button type="submit" class="filter-btn">Apply Filters</button>
            <?php if($filter_status || $filter_payment || $filter_mode): ?>
                <a href="admin_orders.php" style="color: #ff8ba7; font-size: 14px; text-decoration: underline;">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="admin-table-card">
        <table class="admin-table" id="ordersTable">
            <thead>
                <tr>
                    <th style="width: 10%;">Order ID</th>
                    <th style="width: 15%;">Customer</th>
                    <th style="width: 12%;">Items</th>
                    <th style="width: 25%;">Address / Recipient</th>
                    <th style="width: 12%;">Total</th>
                    <th style="width: 14%;">Mode & Payment</th>
                    <th style="width: 12%;">Status</th>
                    <th style="width: 10%; text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT orders.*, users.name as customer_name
                        FROM orders JOIN users ON orders.user_id = users.id
                        WHERE 1=1" . $order_where . "
                        ORDER BY (orders.cancel_status = 'requested') DESC, created_at DESC
                        LIMIT $limit OFFSET $offset";

                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $cs = $row['cancel_status'] ?? 'none';
                        $status_class = 'status-pending';
                        if($row['status'] == 'shipped') $status_class = 'status-shipped';
                        if($row['status'] == 'delivered') $status_class = 'status-delivered';
                        $row_style = ($cs === 'requested') ? ' style="background:#fffdf5;"' : '';

                        $delivery_mode = '<span style="background: #e3f2fd; padding: 4px 12px; border-radius: 20px; font-size: 11px; color: #1976d2; white-space: nowrap; display: inline-block;">🏠 To Me</span>';
                        if(!empty($row['recipient_name'])) {
                            $delivery_mode = '<span style="background: #fff3e0; padding: 4px 12px; border-radius: 20px; font-size: 11px; color: #e65100; white-space: nowrap; display: inline-block;">🎁 To Recipient</span>';
                        }

                        // payment status pill
                        $pm = $row['payment_method'] ?? 'cod';
                        $ps = $row['payment_status'] ?? 'unpaid';
                        if ($pm === 'cod') {
                            $pay_pill = '<span style="background:#f0f0f0;color:#777;padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700;">COD</span>';
                        } elseif ($ps === 'paid') {
                            $pay_pill = '<span style="background:#e8f5e9;color:#2e7d32;padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700;">PAID</span>';
                        } elseif ($ps === 'failed') {
                            $pay_pill = '<span style="background:#fdeded;color:#d32f2f;padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700;">PAYMENT FAILED</span>';
                        } else {
                            $pay_pill = '<span style="background:#fff8e1;color:#a5710d;padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700;">UNPAID</span>';
                        }

                        $address_summary = $row['address'] . ', ' . $row['city'];
                        if(!empty($row['recipient_name'])) {
                            $address_summary .= '<br><strong>Recipient:</strong> ' . $row['recipient_name'];
                        }

                        $thumb_sql = "SELECT p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = " . $row['id'] . " LIMIT 2";
                        $thumb_res = $conn->query($thumb_sql);
                        $thumbs = '';
                        if($thumb_res->num_rows > 0) {
                            while($t = $thumb_res->fetch_assoc()) {
                                $thumbs .= '<img src="'.htmlspecialchars(img_url($t['image'])).'" class="prod-thumb" style="margin-right:4px;">';
                            }
                        }

                        // --- STATUS CELL ---
                        if ($cs === 'requested') {
                            $status_cell = '<span class="status-badge" style="background:#fff8e1;color:#a5710d;">Cancellation requested</span>'
                                . '<div style="margin-top:6px; font-size:11px; color:#999; white-space:normal; max-width:180px;">“'
                                . htmlspecialchars($row['cancel_reason'] ?? '') . '”</div>';
                        } elseif ($row['status'] === 'cancelled') {
                            $status_cell = '<span class="status-badge" style="background:#f5f5f5;color:#999;text-decoration:line-through;">Cancelled</span>'
                                . ($cs === 'approved' ? '<div style="margin-top:4px;font-size:11px;color:#aaa;">request approved</div>' : '');
                        } elseif ($row['status'] === 'delivered') {
                            // Delivered is final — no more status changes allowed.
                            $status_cell = '<span class="status-badge status-delivered">Delivered</span>'
                                . '<div style="margin-top:4px;font-size:11px;color:#aaa;"><i class="fas fa-lock" style="margin-right:3px;"></i>final</div>';
                        } else {
                            $status_cell = '<form action="admin_orders.php" method="POST" style="margin:0; display:inline;">'
                                . '<input type="hidden" name="order_id" value="'.$row['id'].'">'
                                . '<input type="hidden" name="update_status_here" value="1">'
                                . '<select name="status" class="status-select" onchange="this.form.submit()">'
                                . '<option value="pending" '.($row['status'] == 'pending' ? 'selected' : '').'>Pending</option>'
                                . '<option value="shipped" '.($row['status'] == 'shipped' ? 'selected' : '').'>Shipped</option>'
                                . '<option value="delivered" '.($row['status'] == 'delivered' ? 'selected' : '').'>Delivered</option>'
                                . '</select></form>';
                            if ($cs === 'rejected') {
                                $status_cell .= '<div style="margin-top:4px;font-size:11px;color:#d32f2f;">cancellation declined</div>';
                            }
                        }

                        // --- ACTION CELL ---
                        $action_cell = '<button class="btn-view-items" onclick="openModal('.$row['id'].')"><i class="fas fa-eye" style="margin-right:5px;"></i> View</button>';
                        if ($cs === 'requested') {
                            $action_cell = '<div style="display:flex;flex-direction:column;gap:6px;align-items:center;">'
                                . '<form action="admin_orders.php" method="POST" style="margin:0;width:100%;">'
                                . '<input type="hidden" name="order_id" value="'.$row['id'].'">'
                                . '<button type="submit" name="approve_cancel" value="1" onclick="return confirm(\'Approve this cancellation? Stock will be restored.\')" style="width:100%;background:#e8f5e9;color:#2e7d32;border:none;padding:7px 12px;border-radius:30px;font-size:12px;font-weight:700;cursor:pointer;font-family:Poppins;">Approve</button>'
                                . '</form>'
                                . '<button type="button" onclick="openRejectModal('.$row['id'].')" style="width:100%;background:#fdeded;color:#d32f2f;border:none;padding:7px 12px;border-radius:30px;font-size:12px;font-weight:700;cursor:pointer;font-family:Poppins;">Decline</button>'
                                . '<button class="btn-view-items" style="margin:0;" onclick="openModal('.$row['id'].')"><i class="fas fa-eye"></i></button>'
                                . '</div>';
                        }

                        echo '
                        <tr class="search-row"'.$row_style.'>
                            <td><strong>#'.$row['id'].'</strong></td>
                            <td class="search-customer"><strong>'.htmlspecialchars($row['customer_name']).'</strong></td>
                            <td>'.$thumbs.'</td>
                            <td style="font-size: 13px;">'.$address_summary.'</td>
                            <td class="search-price"><strong>PHP '.number_format($row['total_amount'], 2).'</strong></td>
                            <td style="font-size: 12px; white-space: nowrap;">
                                '.$delivery_mode.'
                                <div style="margin-top: 4px; color: #888; font-size: 12px; white-space: nowrap;">'.ucfirst($row['payment_method']).' '.$pay_pill.'</div>
                            </td>
                            <td>'.$status_cell.'</td>
                            <td style="text-align:center;">'.$action_cell.'</td>
                        </tr>
                        ';
                    }
                } else {
                    echo "<tr><td colspan='8' style='padding: 40px; text-align:center; color:#888;'>No orders match your filters.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- --- PAGINATION --- -->
    <?php
    $qs = '&filter_status=' . urlencode($filter_status) . '&filter_payment=' . urlencode($filter_payment) . '&filter_mode=' . urlencode($filter_mode);
    ?>
    <div style="text-align:center; color:#999; font-size:13px; margin-top:24px;">
        <?php if ($total_rows > 0): ?>
            Showing <strong><?php echo $showing_from; ?>–<?php echo $showing_to; ?></strong> of <strong><?php echo $total_rows; ?></strong> order<?php echo $total_rows === 1 ? '' : 's'; ?>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination-wrapper">
        <a href="admin_orders.php?page=<?php echo max(1, $page - 1); ?><?php echo $qs; ?>" class="page-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">&larr; Prev</a>
        <?php
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        if ($start > 1) {
            echo '<a href="admin_orders.php?page=1'.$qs.'" class="page-btn">1</a>';
            if ($start > 2) echo '<span class="page-btn disabled" style="border:none;background:transparent;">…</span>';
        }
        for ($i = $start; $i <= $end; $i++) {
            echo '<a href="admin_orders.php?page='.$i.$qs.'" class="page-btn '.($i == $page ? 'active' : '').'">'.$i.'</a>';
        }
        if ($end < $total_pages) {
            if ($end < $total_pages - 1) echo '<span class="page-btn disabled" style="border:none;background:transparent;">…</span>';
            echo '<a href="admin_orders.php?page='.$total_pages.$qs.'" class="page-btn">'.$total_pages.'</a>';
        }
        ?>
        <a href="admin_orders.php?page=<?php echo min($total_pages, $page + 1); ?><?php echo $qs; ?>" class="page-btn <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">Next &rarr;</a>
    </div>
    <?php endif; ?>

</div>

<!-- MODAL -->
<div class="modal-overlay" id="orderModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h3 style="margin-bottom: 15px; color: #222;">Order Items</h3>
        <div id="modalItemsContainer"></div>
    </div>
</div>

<!-- DECLINE CANCELLATION MODAL -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-box" style="max-width: 420px;">
        <span class="modal-close" onclick="closeRejectModal()">&times;</span>
        <h3 style="margin-bottom: 6px; color: #222;">Decline cancellation</h3>
        <p style="font-size: 13.5px; color: #999; margin-bottom: 16px;">The order will continue. You can leave the customer a short note.</p>
        <form action="admin_orders.php" method="POST">
            <input type="hidden" name="order_id" id="rejectOrderId">
            <input type="hidden" name="reject_cancel" value="1">
            <textarea name="admin_note" maxlength="500" placeholder="e.g. This order has already been packed and dispatched." style="width:100%; min-height:90px; border:1.5px solid #eee; border-radius:14px; padding:12px 14px; font-family:'Poppins'; font-size:14px; resize:vertical; outline:none; background:#fafafa;"></textarea>
            <div style="display:flex; gap:10px; margin-top:16px;">
                <button type="button" onclick="closeRejectModal()" style="flex:1; padding:12px; border:none; border-radius:50px; background:#eaeaea; color:#555; font-weight:600; font-family:'Poppins'; cursor:pointer;">Cancel</button>
                <button type="submit" style="flex:1; padding:12px; border:none; border-radius:50px; background:#d32f2f; color:#fff; font-weight:600; font-family:'Poppins'; cursor:pointer;">Decline request</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRejectModal(id) {
        document.getElementById('rejectOrderId').value = id;
        document.getElementById('rejectModal').style.display = 'flex';
    }
    function closeRejectModal() { document.getElementById('rejectModal').style.display = 'none'; }
    document.getElementById('rejectModal').addEventListener('click', function (e) { if (e.target === this) closeRejectModal(); });

    function openModal(orderId) {
        fetch('get_order_items.php?order_id=' + orderId)
            .then(response => response.text())
            .then(data => {
                document.getElementById('modalItemsContainer').innerHTML = data;
                document.getElementById('orderModal').style.display = 'flex';
            });
    }

    function closeModal() {
        document.getElementById('orderModal').style.display = 'none';
    }

    document.getElementById('orderModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    /* --- LIVE SEARCH ENGINE (NEW!) --- */
    document.getElementById('adminOrderSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.getElementsByClassName('search-row');
        for (let i = 0; i < rows.length; i++) {
            let row = rows[i];
            let idText = row.cells[0].innerText.toLowerCase();
            let customerText = row.cells[1].innerText.toLowerCase();
            let priceText = row.cells[4].innerText.toLowerCase();
            let statusText = row.cells[6].innerText.toLowerCase();

            if (idText.indexOf(filter) > -1 || customerText.indexOf(filter) > -1 || 
                priceText.indexOf(filter) > -1 || statusText.indexOf(filter) > -1) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
    });
</script>

<?php include 'admin_footer.php'; ?>