<?php
include 'db_connect.php';
include 'build_a_box_lib.php';
include 'catalog_lib.php';
bab_ensure_schema($conn);
catalog_ensure_schema($conn);

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

// Handle layout toggle
if (isset($_GET['view'])) {
    $_SESSION['admin_view'] = $_GET['view'];
}
$current_view = isset($_SESSION['admin_view']) ? $_SESSION['admin_view'] : 'grid'; 

// Handle Category / Type Filter & Search
$filter_category = isset($_GET['filter_cat']) ? intval($_GET['filter_cat']) : 0;
$filter_type = isset($_GET['filter_type']) ? catalog_type_key($_GET['filter_type']) : '';
if (isset($_GET['filter_type']) && !in_array($_GET['filter_type'], array_keys(catalog_types()), true)) {
    $filter_type = '';
}
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Shared WHERE fragment for the product list + count
$prod_filter = "";
if ($filter_category > 0) { $prod_filter .= " AND category_id = $filter_category"; }
if ($filter_type !== '') { $prod_filter .= " AND product_type = '" . $conn->real_escape_string($filter_type) . "'"; }
if (!empty($search)) { $prod_filter .= " AND name LIKE '%$search%'"; }

// --- PAGINATION LOGIC ---
$limit = 20;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) as total FROM products WHERE 1=1" . $prod_filter;
$count_res = $conn->query($count_sql);
$total_rows = $count_res->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

include 'admin_header.php';

// Build-a-Box: sizes + per-product allowed-size map
$bab_all_sizes = bab_box_sizes($conn);
$bab_product_sizes = [];
$bab_ps_res = $conn->query("SELECT product_id, box_size_id FROM product_box_sizes");
while ($bab_ps_res && $bab_ps_row = $bab_ps_res->fetch_assoc()) {
    $bab_product_sizes[intval($bab_ps_row['product_id'])][] = intval($bab_ps_row['box_size_id']);
}
function bab_sizes_attr($pid, $map) {
    return implode(',', $map[intval($pid)] ?? []);
}

// Handle Flash messages
$show_deleted = false;
$show_updated = false;

if (isset($_SESSION['product_deleted']) && $_SESSION['product_deleted'] === true) {
    $show_deleted = true;
    unset($_SESSION['product_deleted']);
}
if (isset($_SESSION['product_updated']) && $_SESSION['product_updated'] === true) {
    $show_updated = true;
    unset($_SESSION['product_updated']);
}
?>

<style>
    .main-wrapper { max-width: 1200px; margin: 0 auto; padding: 40px 20px; width: 100%; flex: 1; }
    
    /* --- ALERT STYLES --- */
    .alert-success { 
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9); 
        border: 1px solid #a5d6a7; 
        color: #2e7d32; 
        padding: 16px 24px; 
        border-radius: 20px; 
        margin-bottom: 25px; 
        text-align: center; 
        font-weight: 500; 
        transition: opacity 0.5s ease; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        gap: 10px;
        box-shadow: 0 4px 15px rgba(46, 125, 50, 0.08);
    }
    .alert-danger { 
        background: linear-gradient(135deg, #fdeded, #ffcdd2); 
        border: 1px solid #ffc1cc; 
        color: #d32f2f; 
        padding: 16px 24px; 
        border-radius: 20px; 
        margin-bottom: 25px; 
        text-align: center; 
        font-weight: 500; 
        transition: opacity 0.5s ease; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        gap: 10px;
        box-shadow: 0 4px 15px rgba(211, 47, 47, 0.08);
    }
    
    /* --- TOP BAR --- */
    .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
        background: #fff;
        padding: 20px 25px;
        border-radius: 24px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.03);
        border: 1px solid #f5f5f5;
    }
    .top-bar-left h2 {
        font-size: 24px;
        font-weight: 700;
        color: #222;
        margin-bottom: 2px;
    }
    .top-bar-left p {
        color: #999;
        font-size: 14px;
        margin: 0;
    }
    .top-bar-right {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    /* --- SEARCH BAR --- */
    .search-container { 
        display: flex; 
        align-items: center; 
        gap: 12px;
        background: #f8f8fa; 
        border: 1.5px solid #eee; 
        border-radius: 50px;
        padding: 4px 16px 4px 20px; 
        transition: 0.3s;
    }
    .search-container:focus-within { 
        border-color: #ffc1cc; 
        background: #fff;
        box-shadow: 0 0 0 4px rgba(255, 193, 204, 0.12); 
    }
    .search-container input { 
        border: none; 
        outline: none; 
        width: 180px; 
        font-size: 13px; 
        font-family: 'Poppins'; 
        background: transparent; 
        padding: 10px 0;
    }
    .search-container i { color: #aaa; }
    
    /* --- FILTER DROPDOWN --- */
    .filter-select {
        padding: 10px 20px;
        border: 1.5px solid #eee;
        border-radius: 50px;
        font-size: 13px;
        font-family: 'Poppins';
        background: #f8f8fa;
        color: #555;
        outline: none;
        cursor: pointer;
        transition: 0.2s;
    }
    .filter-select:focus, .filter-select:hover { 
        border-color: #ffc1cc; 
        background: #fff;
    }
    
    /* --- BUTTONS --- */
    .btn-pink { 
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; 
        padding: 10px 24px; 
        border-radius: 50px; 
        font-weight: 600; 
        font-size: 14px;
        text-decoration: none; 
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.25);
    }
    .btn-pink:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 20px rgba(254, 165, 182, 0.35);
    }
    .btn-pink-outline {
        background: transparent;
        color: #ff8ba7;
        padding: 8px 20px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 13px;
        text-decoration: none;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1.5px solid #ffc1cc;
        cursor: pointer;
    }
    .btn-pink-outline:hover {
        background: #fff0f5;
        transform: translateY(-2px);
    }
    
    /* --- VIEW TOGGLE --- */
    .view-toggle-container { 
        display: flex; 
        gap: 4px; 
        background: #f3f3f3; 
        padding: 4px; 
        border-radius: 50px; 
    }
    .view-btn { 
        background: transparent; 
        border: none; 
        padding: 8px 16px; 
        border-radius: 30px; 
        font-size: 13px; 
        font-weight: 500; 
        cursor: pointer; 
        color: #888; 
        transition: 0.2s; 
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .view-btn.active { 
        background: #fff; 
        color: #333; 
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); 
    }
    .view-btn:hover:not(.active) { color: #ff8ba7; }

    /* --- GRID VIEW --- */
    .product-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); 
        gap: 25px; 
        display: none; 
    }
    .product-grid.active { display: grid; }
    
    .admin-card { 
        background: #fff; 
        border-radius: 24px; 
        padding: 20px 20px 18px; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.03); 
        border: 1px solid #f5f5f5; 
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        text-align: center; 
        position: relative;
    }
    .admin-card:hover { 
        transform: translateY(-6px); 
        box-shadow: 0 12px 35px rgba(255, 139, 167, 0.10); 
        border-color: #ffc1cc;
    }
    .card-image-wrapper {
        background: #fafafa;
        border-radius: 16px;
        padding: 15px;
        margin-bottom: 15px;
        position: relative;
        overflow: hidden;
    }
    .card-image { 
        width: 100%; 
        height: 150px; 
        object-fit: contain; 
        transition: transform 0.4s ease;
    }
    .admin-card:hover .card-image {
        transform: scale(1.05);
    }
    .card-stock-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        padding: 4px 14px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 600;
        background: #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    }
    .card-stock-badge.in-stock { color: #2e7d32; }
    .card-stock-badge.out-stock { color: #d32f2f; }
    
    .card-name { 
        font-size: 16px; 
        font-weight: 600; 
        margin-bottom: 4px; 
        color: #222; 
    }
    .card-cat-badge { 
        display: inline-block; 
        font-size: 11px; 
        background: #f0f0f0; 
        color: #666; 
        padding: 3px 14px; 
        border-radius: 20px; 
        margin-bottom: 6px; 
        font-weight: 500;
    }
    .card-desc { 
        font-size: 13px; 
        color: #999; 
        margin-bottom: 12px; 
        line-height: 1.5; 
        display: -webkit-box; 
        -webkit-line-clamp: 2; 
        -webkit-box-orient: vertical; 
        overflow: hidden;
        min-height: 38px;
    }
    .card-price { 
        font-size: 20px; 
        font-weight: 700; 
        color: #222; 
        margin-bottom: 6px; 
    }
    .card-actions { 
        display: flex; 
        justify-content: center; 
        gap: 10px; 
        margin-top: 12px;
        padding-top: 14px;
        border-top: 1px solid #f5f5f5;
    }
    .btn-edit { 
        background: #e3f2fd; 
        color: #1976d2; 
        border: none; 
        padding: 8px 20px; 
        border-radius: 50px; 
        font-size: 13px; 
        font-weight: 600; 
        cursor: pointer; 
        transition: 0.2s; 
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-edit:hover { 
        background: #1976d2; 
        color: white; 
        transform: translateY(-2px);
    }
    .btn-delete { 
        background: #ffe4e4; 
        color: #d32f2f; 
        border: none; 
        padding: 8px 20px; 
        border-radius: 50px; 
        font-size: 13px; 
        font-weight: 600; 
        cursor: pointer; 
        transition: 0.2s; 
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-delete:hover { 
        background: #d32f2f; 
        color: white; 
        transform: translateY(-2px);
    }

    /* --- LIST VIEW --- */
    .list-view-container { 
        display: none; 
        background: #fff; 
        border-radius: 24px; 
        padding: 20px; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.03); 
        border: 1px solid #f5f5f5;
    }
    .list-view-container.active { display: block; }
    
    .admin-table { 
        width: 100%; 
        border-collapse: collapse; 
    }
    .admin-table th { 
        text-align: left; 
        padding: 14px 12px; 
        border-bottom: 2px solid #f0f0f0; 
        color: #444; 
        font-weight: 600; 
        font-size: 13px; 
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .admin-table td { 
        padding: 16px 12px; 
        border-bottom: 1px solid #f5f5f5; 
        font-size: 14px; 
        color: #333; 
        vertical-align: middle; 
    }
    .admin-table tr:last-child td { border-bottom: none; }
    .admin-table tr:hover td { background: #fafafa; }
    
    .prod-thumb { 
        width: 50px; 
        height: 50px; 
        object-fit: cover; 
        border-radius: 12px; 
        background: #fafafa;
        border: 1px solid #f0f0f0;
    }
    .prod-name-cell {
        font-weight: 600;
        color: #222;
    }
    .prod-desc-cell {
        font-size: 12px;
        color: #999;
        display: block;
        margin-top: 2px;
    }
    .prod-cat-badge {
        background: #f0f0f0;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 12px;
        color: #666;
        display: inline-block;
        font-weight: 500;
    }
    .prod-stock-in {
        color: #2e7d32;
        font-weight: 600;
    }
    .prod-stock-out {
        color: #d32f2f;
        font-weight: 600;
    }
    .prod-price {
        font-weight: 700;
        color: #222;
        font-size: 16px;
    }
    .list-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }
    .list-actions .btn-edit,
    .list-actions .btn-delete {
        padding: 6px 16px;
        font-size: 12px;
    }

    /* --- MODAL --- */
    .modal-overlay { 
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background: rgba(0, 0, 0, 0.5); 
        backdrop-filter: blur(8px); 
        display: none; 
        justify-content: center; 
        align-items: center; 
        z-index: 9999; 
        padding: 20px;
    }
    .modal-box { 
        background: #fff; 
        border-radius: 30px; 
        padding: 40px; 
        max-width: 480px; 
        width: 100%; 
        box-shadow: 0 25px 60px rgba(0,0,0,0.2); 
        position: relative; 
        animation: modalSlideIn 0.3s ease-out;
    }
    @keyframes modalSlideIn {
        from { transform: scale(0.95) translateY(20px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .modal-close { 
        position: absolute; 
        top: 15px; 
        right: 20px; 
        font-size: 24px; 
        color: #888; 
        cursor: pointer; 
        transition: 0.2s; 
    }
    .modal-close:hover { 
        color: #ff8ba7; 
        transform: rotate(90deg); 
    }
    .modal-title {
        font-size: 22px;
        font-weight: 700;
        color: #222;
        margin-bottom: 25px;
    }
    .modal-input { 
        width: 100%; 
        padding: 14px 16px; 
        border: 1.5px solid #eee; 
        border-radius: 14px; 
        margin-bottom: 16px; 
        font-family: 'Poppins';
        font-size: 14px;
        background: #fafafa;
        transition: 0.3s;
    }
    .modal-input:focus { 
        border-color: #ffc1cc; 
        outline: none; 
        background: #fff;
        box-shadow: 0 0 0 4px rgba(255, 193, 204, 0.1);
    }
    .modal-label {
        font-weight: 600;
        font-size: 14px;
        color: #555;
        display: block;
        margin-bottom: 5px;
    }
    .modal-btn { 
        width: 100%; 
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; 
        padding: 16px; 
        border: none; 
        border-radius: 50px; 
        font-weight: 600; 
        font-size: 16px;
        cursor: pointer; 
        transition: 0.2s; 
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.25);
        margin-top: 5px;
    }
    .modal-btn:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 20px rgba(254, 165, 182, 0.35);
    }

    /* --- PAGINATION --- */
    .pagination-wrapper { 
        display: flex; 
        justify-content: center; 
        gap: 8px; 
        margin-top: 35px; 
        margin-bottom: 20px; 
        flex-wrap: wrap; 
    }
    .page-btn { 
        padding: 10px 18px; 
        border: 1.5px solid #eee; 
        border-radius: 30px; 
        background: #fff; 
        color: #555; 
        text-decoration: none; 
        font-size: 14px; 
        font-weight: 500; 
        transition: 0.2s; 
        font-family: 'Poppins'; 
    }
    .page-btn:hover { 
        background: #ffc1cc; 
        color: #fff; 
        border-color: #ffc1cc; 
    }
    .page-btn.active { 
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); 
        color: #fff; 
        border-color: #FEA5B6; 
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.3); 
    }
    .page-btn.disabled { 
        opacity: 0.4; 
        pointer-events: none; 
    }

    /* --- RESPONSIVE --- */
    @media (max-width: 768px) {
        .top-bar { flex-direction: column; align-items: stretch; }
        .top-bar-right { flex-wrap: wrap; justify-content: center; }
        .search-container input { width: 120px; }
        .product-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
        .admin-table { font-size: 12px; }
        .admin-table th, .admin-table td { padding: 10px 8px; }
        .modal-box { padding: 30px 20px; }
    }
</style>

<div class="main-wrapper">
    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="top-bar-left">
            <h2>🛍️ Product Inventory</h2>
            <p><?php echo $total_rows; ?> products in your store</p>
        </div>
        <div class="top-bar-right">
            <form action="admin_products.php" method="GET" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <input type="hidden" name="view" value="<?php echo $current_view; ?>">
                <input type="hidden" name="filter_cat" value="<?php echo $filter_category; ?>">
                <input type="hidden" name="filter_type" value="<?php echo htmlspecialchars($filter_type); ?>">

                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo $search; ?>">
                </div>
                <button type="submit" class="btn-pink" style="padding: 8px 20px; font-size: 13px;">Search</button>
                <?php if(!empty($search)): ?>
                    <a href="admin_products.php" class="btn-pink-outline" style="padding: 6px 16px; font-size: 12px;">Clear</a>
                <?php endif; ?>
            </form>

            <select class="filter-select" onchange="window.location.href='admin_products.php?view=<?php echo $current_view; ?>&search=<?php echo urlencode($search); ?>&filter_type=<?php echo urlencode($filter_type); ?>&filter_cat=' + this.value">
                <option value="0">📂 All Categories</option>
                <?php
                $cat_sql = "SELECT * FROM categories ORDER BY name ASC";
                $cat_result = $conn->query($cat_sql);
                if ($cat_result->num_rows > 0) {
                    while($cat_row = $cat_result->fetch_assoc()) {
                        $selected = ($filter_category == $cat_row['id']) ? 'selected' : '';
                        echo '<option value="'.$cat_row['id'].'" '.$selected.'>'.$cat_row['name'].'</option>';
                    }
                }
                ?>
            </select>

            <select class="filter-select" onchange="window.location.href='admin_products.php?view=<?php echo $current_view; ?>&search=<?php echo urlencode($search); ?>&filter_cat=<?php echo $filter_category; ?>&filter_type=' + this.value">
                <option value="">🏷️ All Types</option>
                <?php foreach (catalog_types() as $tk => $tl): ?>
                    <option value="<?php echo $tk; ?>" <?php echo ($filter_type === $tk) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tl); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="view-toggle-container">
                <button class="view-btn <?php echo ($current_view == 'grid') ? 'active' : ''; ?>" onclick="window.location.href='admin_products.php?view=grid&search=<?php echo urlencode($search); ?>&filter_cat=<?php echo $filter_category; ?>&filter_type=<?php echo urlencode($filter_type); ?>'">
                    <i class="fas fa-th-large"></i> Grid
                </button>
                <button class="view-btn <?php echo ($current_view == 'list') ? 'active' : ''; ?>" onclick="window.location.href='admin_products.php?view=list&search=<?php echo urlencode($search); ?>&filter_cat=<?php echo $filter_category; ?>&filter_type=<?php echo urlencode($filter_type); ?>'">
                    <i class="fas fa-list"></i> List
                </button>
            </div>

            <a href="admin_add_product.php" class="btn-pink">
                <i class="fas fa-plus"></i> Add New
            </a>
        </div>
    </div>

    <!-- ALERT MESSAGES -->
    <?php if ($show_updated): ?>
        <div id="alertUpdated" class="alert-success">
            <i class="fas fa-check-circle" style="font-size: 20px;"></i> Product updated successfully!
        </div>
        <script>setTimeout(function(){ var e = document.getElementById('alertUpdated'); if(e) { e.style.opacity='0'; setTimeout(()=>e.style.display='none',500); } }, 3000);</script>
    <?php endif; ?>

    <?php if ($show_deleted): ?>
        <div id="alertDeleted" class="alert-danger">
            <i class="fas fa-trash-alt" style="font-size: 18px;"></i> Product deleted successfully!
        </div>
        <script>setTimeout(function(){ var e = document.getElementById('alertDeleted'); if(e) { e.style.opacity='0'; setTimeout(()=>e.style.display='none',500); } }, 3000);</script>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert-danger">
            <i class="fas fa-exclamation-circle" style="font-size: 18px;"></i> 
            <?php 
            $error_msg = '';
            switch($_GET['error']) {
                case 'price_negative': $error_msg = 'Price cannot be negative.'; break;
                case 'invalid_price': $error_msg = 'Please enter a valid price.'; break;
                case 'quantity_negative': $error_msg = 'Quantity cannot be negative.'; break;
                case 'invalid_quantity': $error_msg = 'Please enter a valid quantity.'; break;
                case 'upload': $error_msg = 'Failed to upload image. Please try again.'; break;
                case 'price_max':
    $error_msg = 'Price cannot exceed 9,999.99.';
    break;
case 'quantity_max':
    $error_msg = 'Quantity cannot exceed 9,999.';
    break;
case 'box_sizes':
    $error_msg = 'Please select at least one box size for the product.';
    break;
                default: $error_msg = 'An error occurred. Please try again.';
            }
            echo $error_msg;
            ?>
        </div>
        <script>setTimeout(function(){ var e = document.querySelector('.alert-danger'); if(e) { e.style.opacity='0'; setTimeout(()=>e.style.display='none',500); } }, 4000);</script>
    <?php endif; ?>

    <!-- GRID VIEW -->
    <div class="product-grid <?php echo ($current_view == 'grid') ? 'active' : ''; ?>" id="productGrid">
        <?php
        $sql = "SELECT * FROM products WHERE 1=1" . $prod_filter . " ORDER BY id DESC LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $isInStock = $row['quantity'] > 0;
                $stock_status = $isInStock ? 'In Stock: '.$row['quantity'] : 'Out of Stock';
                $stock_class = $isInStock ? 'in-stock' : 'out-stock';
                
                $cat_name = 'General';
                if(isset($row['category_id']) && $row['category_id'] > 0) {
                    $cat_res = $conn->query("SELECT name FROM categories WHERE id = ".$row['category_id']);
                    if($cat_res->num_rows > 0) {
                        $cat_row = $cat_res->fetch_assoc();
                        $cat_name = $cat_row['name'];
                    }
                }
                $cat_display = !empty($cat_name) ? '<span class="card-cat-badge">'.$cat_name.'</span>' : '';
                $tkey = catalog_type_key($row['product_type'] ?? 'catalog');
                $type_display = $tkey !== 'catalog'
                    ? '<span class="card-cat-badge" style="background:#fff0f5;color:#d81b60;">'.htmlspecialchars(catalog_types()[$tkey]).'</span>'
                    : '';
                echo '
                <div class="admin-card search-item">
                    <div class="card-image-wrapper">
                        <img src="uploads/'.$row['image'].'" class="card-image" alt="Product">
                        <span class="card-stock-badge '.$stock_class.'">'.$stock_status.'</span>
                    </div>
                    <div class="card-name search-name">'.$row['name'].'</div>
                    '.$cat_display.$type_display.'
                    <div class="card-desc">'.(strlen($row['description']) > 0 ? $row['description'] : '<span style="color:#ddd;">No description</span>').'</div>
                    <div class="card-price">PHP '.number_format($row['price'], 2).'</div>
                    <div class="card-actions">
                        <button class="btn-edit" onclick="openEditModal('.$row['id'].', \''.addslashes($row['name']).'\', \''.addslashes($row['description']).'\', '.$row['price'].', '.$row['quantity'].', '.$row['category_id'].', \''.bab_sizes_attr($row['id'], $bab_product_sizes).'\', \''.catalog_type_key($row['product_type'] ?? 'catalog').'\')">
                            <i class="fas fa-pen"></i> Edit
                        </button>
                        <a href="admin_delete_product.php?id='.$row['id'].'" onclick="return confirm(\'Are you sure you want to delete this product?\');" class="btn-delete">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
                ';
            }
        } else {
            echo '<div style="grid-column: 1 / -1; text-align: center; padding: 80px 20px; color: #999;">
                    <i class="fas fa-box-open" style="font-size: 48px; display: block; margin-bottom: 15px; color: #ddd;"></i>
                    <p style="font-size: 18px; margin-bottom: 5px;">No products found</p>
                    <p style="font-size: 14px;">Try adjusting your search or filter</p>
                  </div>';
        }
        ?>
    </div>

    <!-- LIST VIEW -->
    <div class="list-view-container <?php echo ($current_view == 'list') ? 'active' : ''; ?>" id="listViewContainer">
        <table class="admin-table" id="productTable">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Price</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT * FROM products WHERE 1=1" . $prod_filter . " ORDER BY id DESC LIMIT $limit OFFSET $offset";

                $result = $conn->query($sql);
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $isInStock = $row['quantity'] > 0;
                        $stock_status = $isInStock ? 'In Stock: '.$row['quantity'] : 'Out of Stock';
                        $stock_class = $isInStock ? 'prod-stock-in' : 'prod-stock-out';
                        
                        $cat_name = 'General';
                        if(isset($row['category_id']) && $row['category_id'] > 0) {
                            $cat_res = $conn->query("SELECT name FROM categories WHERE id = ".$row['category_id']);
                            if($cat_res->num_rows > 0) {
                                $cat_row = $cat_res->fetch_assoc();
                                $cat_name = $cat_row['name'];
                            }
                        }
                        echo '
                        <tr class="search-item">
                            <td><img src="uploads/'.$row['image'].'" class="prod-thumb"></td>
                            <td>
                                <span class="search-name prod-name-cell">'.$row['name'].'</span>
                                <span class="prod-desc-cell">'.(strlen($row['description']) > 0 ? substr($row['description'], 0, 50).'...' : 'No description').'</span>
                            </td>
                            <td><span class="prod-cat-badge">'.$cat_name.'</span>'.(catalog_type_key($row['product_type'] ?? 'catalog') !== 'catalog' ? ' <span class="prod-cat-badge" style="background:#fff0f5;color:#d81b60;">'.htmlspecialchars(catalog_types()[catalog_type_key($row['product_type'] ?? 'catalog')]).'</span>' : '').'</td>
                            <td><span class="'.$stock_class.'">'.$stock_status.'</span></td>
                            <td><span class="prod-price">PHP '.number_format($row['price'], 2).'</span></td>
                            <td>
                                <div class="list-actions">
                                    <button class="btn-edit" onclick="openEditModal('.$row['id'].', \''.addslashes($row['name']).'\', \''.addslashes($row['description']).'\', '.$row['price'].', '.$row['quantity'].', '.$row['category_id'].', \''.bab_sizes_attr($row['id'], $bab_product_sizes).'\', \''.catalog_type_key($row['product_type'] ?? 'catalog').'\')">
                                        <i class="fas fa-pen"></i> Edit
                                    </button>
                                    <a href="admin_delete_product.php?id='.$row['id'].'" onclick="return confirm(\'Are you sure you want to delete this product?\');" class="btn-delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                        ';
                    }
                } else {
                    echo "<tr><td colspan='6' style='padding: 60px; text-align:center; color:#999;'>
                            <i class='fas fa-box-open' style='font-size: 36px; display: block; margin-bottom: 10px; color: #ddd;'></i>
                            No products found
                          </td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_rows > $limit): ?>
    <div class="pagination-wrapper">
        <a href="admin_products.php?page=<?php echo ($page > 1) ? $page - 1 : 1; ?>&view=<?php echo $current_view; ?>&search=<?php echo $search; ?>&filter_cat=<?php echo $filter_category; ?>&filter_type=<?php echo urlencode($filter_type); ?>" class="page-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
            <i class="fas fa-chevron-left"></i> Previous
        </a>

        <?php 
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        if ($start_page > 1) {
            echo '<a href="admin_products.php?page=1&view='.$current_view.'&search='.$search.'&filter_cat='.$filter_category.'" class="page-btn">1</a>';
            if ($start_page > 2) echo '<span class="page-btn disabled" style="border: none; background: transparent;">...</span>';
        }
        for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="admin_products.php?page=<?php echo $i; ?>&view=<?php echo $current_view; ?>&search=<?php echo $search; ?>&filter_cat=<?php echo $filter_category; ?>&filter_type=<?php echo urlencode($filter_type); ?>" class="page-btn <?php echo ($i == $page) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor;
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) echo '<span class="page-btn disabled" style="border: none; background: transparent;">...</span>';
            echo '<a href="admin_products.php?page='.$total_pages.'&view='.$current_view.'&search='.$search.'&filter_cat='.$filter_category.'&filter_type='.urlencode($filter_type).'" class="page-btn">'.$total_pages.'</a>';
        }
        ?>

        <a href="admin_products.php?page=<?php echo ($page < $total_pages) ? $page + 1 : $total_pages; ?>&view=<?php echo $current_view; ?>&search=<?php echo $search; ?>&filter_cat=<?php echo $filter_category; ?>&filter_type=<?php echo urlencode($filter_type); ?>" class="page-btn <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
            Next <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeEditModal()">&times;</span>
        <h3 class="modal-title">✏️ Edit Product</h3>
        <form action="admin_update_product.php" method="POST" enctype="multipart/form-data" onsubmit="return validateEditForm()">
            <input type="hidden" name="id" id="edit_id">

            <label class="modal-label">Product Type</label>
            <select name="product_type" id="edit_product_type" class="modal-input" onchange="toggleEditBoxSizes()">
                <?php foreach (catalog_types() as $tk => $tl): ?>
                    <option value="<?php echo $tk; ?>"><?php echo htmlspecialchars($tl); ?></option>
                <?php endforeach; ?>
            </select>

            <label class="modal-label">Product Name</label>
            <input type="text" name="name" id="edit_name" class="modal-input" required>
            
            <label class="modal-label">Description</label>
            <textarea name="description" id="edit_desc" class="modal-input" rows="3"></textarea>
            
            <label class="modal-label">Price (PHP)</label>
            <input type="number" step="0.01" name="price" id="edit_price" class="modal-input" min="0" required>
            
            <label class="modal-label">Stock Quantity</label>
            <input type="number" name="quantity" id="edit_quantity" class="modal-input" min="0" required>
            
            <label class="modal-label">Category</label>
            <select name="category_id" id="edit_category_id" class="modal-input">
                <option value="">Select a category</option>
                <?php
                $cat_sql = "SELECT * FROM categories ORDER BY name ASC";
                $cat_result = $conn->query($cat_sql);
                while($cat_row = $cat_result->fetch_assoc()) {
                    echo '<option value="'.$cat_row['id'].'">'.$cat_row['name'].'</option>';
                }
                ?>
            </select>

            <div id="edit_box_sizes_group">
            <label class="modal-label">Allowed Box Sizes</label>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px;">
                <?php foreach ($bab_all_sizes as $bs): ?>
                    <label style="flex: 1; min-width: 120px; display: flex; align-items: center; gap: 8px; padding: 10px 12px; border: 1.5px solid #eee; border-radius: 12px; background: #fafafa; cursor: pointer; font-size: 13px;">
                        <input type="checkbox" name="box_sizes[]" value="<?php echo $bs['id']; ?>" class="edit-box-size"
                               style="width: 16px; height: 16px; accent-color: #ff8ba7;">
                        <?php echo htmlspecialchars($bs['name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            </div>

            <label class="modal-label">New Image <span style="font-weight: 400; color: #999;">(Optional)</span></label>
            <input type="file" name="image" class="modal-input" style="padding: 10px; background: #fafafa;">
            
            <button type="submit" name="update_product" class="modal-btn">
                <i class="fas fa-save" style="margin-right: 8px;"></i> Save Changes
            </button>
        </form>
    </div>
</div>

<script>
    function toggleEditBoxSizes() {
        var t = document.getElementById('edit_product_type').value;
        var grp = document.getElementById('edit_box_sizes_group');
        var show = (t === 'catalog');
        grp.style.display = show ? '' : 'none';
        grp.querySelectorAll('input[type="checkbox"]').forEach(function(cb) { cb.disabled = !show; });
    }

    function openEditModal(id, name, desc, price, quantity, category_id, boxSizes, productType) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_desc').value = desc;
        document.getElementById('edit_price').value = price;
        document.getElementById('edit_quantity').value = quantity;
        document.getElementById('edit_category_id').value = category_id;
        document.getElementById('edit_product_type').value = productType || 'catalog';

        var allowed = (boxSizes ? String(boxSizes).split(',') : []).filter(Boolean);
        document.querySelectorAll('.edit-box-size').forEach(function(cb) {
            cb.checked = allowed.indexOf(cb.value) !== -1;
        });
        toggleEditBoxSizes();

        document.getElementById('editModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function closeEditModal() { 
        document.getElementById('editModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    
    document.getElementById('editModal').addEventListener('click', function(e) { 
        if (e.target === this) { 
            closeEditModal(); 
        } 
    });

    function validateEditForm() {
    var quantity = parseInt(document.getElementById('edit_quantity').value);
    var price = parseFloat(document.getElementById('edit_price').value);
    
    if (quantity < 0) {
        alert('Quantity cannot be negative.');
        return false;
    }
    if (isNaN(quantity)) {
        alert('Please enter a valid quantity.');
        return false;
    }
    if (quantity > 9999) {
        alert('Maximum quantity allowed is 9,999.');
        return false;
    }
    if (price < 0) {
        alert('Price cannot be negative.');
        return false;
    }
    if (isNaN(price) || price === '') {
        alert('Please enter a valid price.');
        return false;
    }
    if (price > 9999.99) {
        alert('Maximum price allowed is 9,999.99.');
        return false;
    }
    if (document.getElementById('edit_product_type').value === 'catalog'
        && document.querySelectorAll('.edit-box-size:checked').length === 0) {
        alert('Please select at least one box size for this product.');
        return false;
    }
    return true;
}

// Prevent typing negative sign or 'e' for quantity in edit modal
document.getElementById('edit_quantity').addEventListener('keydown', function(e) {
    if (e.key === '-' || e.key === 'e') {
        e.preventDefault();
    }
});

// Prevent typing negative sign or 'e' for price in edit modal
document.getElementById('edit_price').addEventListener('keydown', function(e) {
    if (e.key === '-' || e.key === 'e') {
        e.preventDefault();
    }
});

// Add max value validation on blur for edit modal
document.getElementById('edit_quantity').addEventListener('blur', function() {
    if (parseInt(this.value) > 9999) {
        this.value = 9999;
        alert('Maximum quantity allowed is 9,999.');
    }
});

document.getElementById('edit_price').addEventListener('blur', function() {
    if (parseFloat(this.value) > 9999.99) {
        this.value = 9999.99;
        alert('Maximum price allowed is 9,999.99.');
    }
});
</script>

<?php include 'admin_footer.php'; ?>