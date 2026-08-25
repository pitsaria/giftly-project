<?php
include 'db_connect.php'; 

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
if (isset($_POST['update_status_here']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $sql = "UPDATE orders SET status = '$new_status' WHERE id = $order_id";
    if ($conn->query($sql) === TRUE) {
        $show_updated = true; // Show the banner right here
    }
}
// -------------------------------------------------

include 'admin_header.php'; 

$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_payment = isset($_GET['filter_payment']) ? $_GET['filter_payment'] : '';
$filter_mode = isset($_GET['filter_mode']) ? $_GET['filter_mode'] : '';

// --- PAGINATION LOGIC ---
$limit = 20; // Orders per page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total orders for pagination (respecting filters)
$count_sql = "SELECT COUNT(*) as total FROM orders JOIN users ON orders.user_id = users.id WHERE 1=1";
if (!empty($filter_status)) { $count_sql .= " AND orders.status = '$filter_status'"; }
if (!empty($filter_payment)) { $count_sql .= " AND orders.payment_method = '$filter_payment'"; }
if (!empty($filter_mode)) {
    if ($filter_mode == 'me') {
        $count_sql .= " AND (orders.recipient_name IS NULL OR orders.recipient_name = '')";
    } else {
        $count_sql .= " AND orders.recipient_name IS NOT NULL AND orders.recipient_name != ''";
    }
}
$count_res = $conn->query($count_sql);
$total_rows = $count_res->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
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

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <form action="admin_orders.php" method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <select name="filter_status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="shipped" <?php echo ($filter_status == 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                <option value="delivered" <?php echo ($filter_status == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
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
                // --- MODIFIED SQL TO INCLUDE LIMIT AND OFFSET ---
                $sql = "SELECT orders.*, users.name as customer_name FROM orders JOIN users ON orders.user_id = users.id WHERE 1=1";
                if (!empty($filter_status)) { $sql .= " AND orders.status = '$filter_status'"; }
                if (!empty($filter_payment)) { $sql .= " AND orders.payment_method = '$filter_payment'"; }
                if (!empty($filter_mode)) {
                    if ($filter_mode == 'me') {
                        $sql .= " AND (orders.recipient_name IS NULL OR orders.recipient_name = '')";
                    } else {
                        $sql .= " AND orders.recipient_name IS NOT NULL AND orders.recipient_name != ''";
                    }
                }
                $sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
                // ------------------------------------------------
                
                $result = $conn->query($sql);
                
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $status_class = 'status-pending';
                        if($row['status'] == 'shipped') $status_class = 'status-shipped';
                        if($row['status'] == 'delivered') $status_class = 'status-delivered';
                        
                        $delivery_mode = '<span style="background: #e3f2fd; padding: 4px 12px; border-radius: 20px; font-size: 11px; color: #1976d2; white-space: nowrap; display: inline-block;">🏠 To Me</span>';
                        if(!empty($row['recipient_name'])) {
                            $delivery_mode = '<span style="background: #fff3e0; padding: 4px 12px; border-radius: 20px; font-size: 11px; color: #e65100; white-space: nowrap; display: inline-block;">🎁 To Recipient</span>';
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
                                $thumbs .= '<img src="uploads/'.$t['image'].'" class="prod-thumb" style="margin-right:4px;">';
                            }
                        }

                        echo '
                        <tr class="search-row">
                            <td><strong>#'.$row['id'].'</strong></td>
                            <td class="search-customer"><strong>'.$row['customer_name'].'</strong></td>
                            <td>'.$thumbs.'</td>
                            <td style="font-size: 13px;">'.$address_summary.'</td>
                            <td class="search-price"><strong>PHP '.number_format($row['total_amount'], 2).'</strong></td>
                            <td style="font-size: 12px; white-space: nowrap;">
                                '.$delivery_mode.'
                                <div style="margin-top: 4px; color: #888; font-size: 12px; white-space: nowrap;">'.ucfirst($row['payment_method']).'</div>
                            </td>
                            <td>
                                <form action="admin_orders.php" method="POST" style="margin:0; display:inline;">
                                    <input type="hidden" name="order_id" value="'.$row['id'].'">
                                    <input type="hidden" name="update_status_here" value="1">
                                    <select name="status" class="status-select" onchange="this.form.submit()">
                                        <option value="pending" '.($row['status'] == 'pending' ? 'selected' : '').'>Pending</option>
                                        <option value="shipped" '.($row['status'] == 'shipped' ? 'selected' : '').'>Shipped</option>
                                        <option value="delivered" '.($row['status'] == 'delivered' ? 'selected' : '').'>Delivered</option>
                                    </select>
                                </form>
                            </td>
                            <td style="text-align:center;">
                                <button class="btn-view-items" onclick="openModal('.$row['id'].')">
                                    <i class="fas fa-eye" style="margin-right: 5px;"></i> View
                                </button>
                            </td>
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

    <!-- --- PAGINATION BUTTONS --- -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination-wrapper">
        <a href="admin_orders.php?page=<?php echo ($page > 1) ? $page - 1 : 1; ?>&filter_status=<?php echo $filter_status; ?>&filter_payment=<?php echo $filter_payment; ?>&filter_mode=<?php echo $filter_mode; ?>" class="page-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
            &larr; Previous
        </a>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="admin_orders.php?page=<?php echo $i; ?>&filter_status=<?php echo $filter_status; ?>&filter_payment=<?php echo $filter_payment; ?>&filter_mode=<?php echo $filter_mode; ?>" class="page-btn <?php echo ($i == $page) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <a href="admin_orders.php?page=<?php echo ($page < $total_pages) ? $page + 1 : $total_pages; ?>&filter_status=<?php echo $filter_status; ?>&filter_payment=<?php echo $filter_payment; ?>&filter_mode=<?php echo $filter_mode; ?>" class="page-btn <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
            Next &rarr;
        </a>
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

<script>
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