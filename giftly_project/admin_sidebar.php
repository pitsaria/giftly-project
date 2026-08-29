<?php
// Unread contact-message count for the sidebar badge (safe if table is absent)
$sidebar_unread_msgs = 0;
if (isset($conn)) {
    $__um = @$conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE is_read = FALSE");
    if ($__um) $sidebar_unread_msgs = (int) $__um->fetch_assoc()['c'];
}
?>
<!-- ADMIN SIDEBAR -->
<div class="admin-sidebar">
    
    <!-- Sidebar Header (Logo & Avatar) -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="giftly-logo.png" alt="Giftly" style="height: 35px; width: auto; display: block;">
        </div>
        
        <!-- AVATAR & GREETING SECTION -->
        <div class="sidebar-profile" style="margin-top: 40px; text-align: center; padding-bottom: 20px; border-bottom: 1px solid #f0f0f0;">
            
            <!-- BUNNY IMAGE PROFILE -->
            <img src="bunny.png" alt="Admin Profile" style="width: 110px; height: 110px; border-radius: 50%; object-fit: cover; box-shadow: 0 12px 30px rgba(254, 165, 182, 0.3); border: 4px solid #ffc1cc;">            
            <h4 style="margin-top: 12px; font-size: 16px; font-weight: 600; color: #222;">Hi, Admin! 👋</h4>
            <p style="font-size: 13px; color: #888; margin-top: 2px;">Good to see you again</p>
        </div>
    </div>

    <!-- Sidebar Menu -->
    <ul class="sidebar-menu">
        <li>
            <a href="admin_dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="admin_products.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_products.php') ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
        </li>
        <li>
            <a href="admin_add_product.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_add_product.php') ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Add Product</span>
            </a>
        </li>
        <li>
            <a href="admin_categories.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_categories.php') ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                <span>Categories</span>
            </a>
        </li>
        <li>
            <a href="admin_orders.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_orders.php') ? 'active' : ''; ?>">
                <i class="fas fa-shopping-bag"></i>
                <span>Orders</span>
            </a>
        </li>
        <li>
            <a href="admin_messages.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_messages.php') ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
                <?php if ($sidebar_unread_msgs > 0): ?>
                    <span style="margin-left:auto; background:#ff8ba7; color:#fff; font-size:11px; font-weight:700; min-width:20px; height:20px; border-radius:50px; display:inline-flex; align-items:center; justify-content:center; padding:0 6px;"><?php echo $sidebar_unread_msgs; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="#" class="disabled-link">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </li>
        <li>
            <a href="#" class="disabled-link">
                <i class="fas fa-chart-line"></i>
                <span>Analytics</span>
            </a>
        </li>
        <!-- 🚀 Go to Shop Button - GRAY -->
        <li style="margin-top: 15px; border-top: 1px solid #f0f0f0; padding-top: 15px;">
            <a href="shop.php" style="background: #f0f0f0; color: #666; border-radius: 16px; padding: 12px 15px; display: flex; align-items: center; gap: 15px; transition: 0.3s;">
                <i class="fas fa-store" style="font-size: 18px; width: 24px; text-align: center; color: #888;"></i>
                <span>Go to Shop</span>
                <i class="fas fa-arrow-right" style="margin-left: auto; font-size: 14px; color: #aaa;"></i>
            </a>
        </li>
    </ul>

    <!-- Bottom Logout Button - PINK GRADIENT -->
    <div class="sidebar-footer">
        <a href="javascript:void(0)" onclick="openLogoutModal()" class="sidebar-logout-btn" style="background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: white; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </div>
</div>

<style>
    /* --- Sidebar Styles --- */
    .admin-sidebar {
        width: 260px;
        background: #ffffff;
        border-right: 1px solid #f0f0f0;
        padding: 30px 20px;
        display: flex;
        flex-direction: column;
        height: 100vh;
        position: sticky;
        top: 0;
        overflow-y: auto;
        flex-shrink: 0;
    }
    .sidebar-header { margin-bottom: 20px; padding-left: 10px; }
    .sidebar-logo { font-size: 24px; font-weight: 700; color: #ff8ba7; }
    .sidebar-menu { flex: 1; }
    .sidebar-menu li { margin-bottom: 8px; }
    .sidebar-menu li a {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px 15px;
        border-radius: 16px;
        color: #666;
        font-weight: 500;
        transition: 0.2s;
        font-size: 14px;
    }
    .sidebar-menu li a:hover { background: #fff0f5; color: #ff8ba7; }
    .sidebar-menu li a.active { background: #fff0f5; color: #ff8ba7; }
    .sidebar-menu li a i { font-size: 18px; width: 24px; text-align: center; }
    .sidebar-menu li a.disabled-link { opacity: 0.5; cursor: not-allowed; }
    
    /* 🚀 Go to Shop Button Hover */
    .sidebar-menu li a[href="shop.php"]:hover {
        background: #e8e8e8 !important;
        transform: translateY(-2px);
        color: #555 !important;
    }
    .sidebar-menu li a[href="shop.php"]:hover i {
        color: #666 !important;
    }
    .sidebar-menu li a[href="shop.php"]:hover .fa-arrow-right {
        color: #666 !important;
    }
    
    .sidebar-footer { margin-top: auto; border-top: 1px solid #f0f0f0; padding-top: 20px; }
    .sidebar-logout-btn {
        display: flex; 
        align-items: center; 
        justify-content: center; 
        gap: 10px;
        width: 100%; 
        padding: 12px; 
        border-radius: 50px;
        font-weight: 600; 
        transition: 0.2s;
        font-size: 14px;
        border: none;
        cursor: pointer;
        font-family: 'Poppins';
    }
    .sidebar-logout-btn:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4); 
    }

    /* The Avatar Circle */
    .admin-avatar-circle {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 44px;
        font-weight: 700;
        margin: 0 auto;
        box-shadow: 0 12px 30px rgba(254, 165, 182, 0.3);
        border: 4px solid #fff;
    }

    /* --- RESPONSIVE --- */
    @media (max-width: 900px) {
        .admin-sidebar { display: none; }
    }
</style>