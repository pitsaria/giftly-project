<?php
include 'db_connect.php'; 
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
?>

<style>
    /* --- COPY ALL YOUR PROFILE CSS HERE --- */
    .profile-wrapper { max-width: 1100px; margin: 0 auto; padding-top: 130px; padding-bottom: 60px; display: flex; gap: 30px; }
    .profile-sidebar { flex: 0 0 250px; background: #ffffff; border-radius: 24px; padding: 30px 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); border: 1px solid rgba(255, 255, 255, 0.8); height: fit-content; }
    .sidebar-title { font-size: 14px; font-weight: 600; color: #222; margin-bottom: 20px; padding-left: 10px; }
    .sidebar-menu { list-style: none; padding: 0; margin: 0; }
    .sidebar-menu li { margin-bottom: 8px; }
    .sidebar-menu li a { display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 16px; color: #555; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.2s; }
    .sidebar-menu li a:hover { background: #fff0f5; color: #ff8ba7; }
    .sidebar-menu li a.active { background: #fff0f5; color: #ff8ba7; border-right: 3px solid #ff8ba7; }
    .sidebar-menu li a i { font-size: 18px; width: 24px; text-align: center; }

    .profile-main { flex: 1; background: #ffffff; border-radius: 24px; padding: 40px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); border: 1px solid rgba(255, 255, 255, 0.8); }
    .page-title { font-size: 24px; font-weight: 700; color: #222; margin-bottom: 20px; }
    
    .tab-content { display: none; animation: fadeIn 0.3s ease; }
    .tab-content.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    @media (max-width: 850px) {
        .profile-wrapper { flex-direction: column; }
        .profile-sidebar { flex: 1; }
        .profile-main { padding: 25px; }
    }
</style>

<div class="profile-wrapper">
    
    <!-- SIDEBAR -->
    <div class="profile-sidebar">
        <div class="sidebar-title">Account Settings</div>
        <ul class="sidebar-menu">
            <li><a href="?tab=profile" class="<?php echo ($active_tab == 'profile') ? 'active' : ''; ?>"><i class="fas fa-user"></i> Profile Settings</a></li>
            <li><a href="?tab=addresses" class="<?php echo ($active_tab == 'addresses') ? 'active' : ''; ?>"><i class="fas fa-map-pin"></i> My Addresses</a></li>
            <li><a href="?tab=orders" class="<?php echo ($active_tab == 'orders') ? 'active' : ''; ?>"><i class="fas fa-shopping-bag"></i> Order History</a></li>
            <li><a href="?tab=boxes" class="<?php echo ($active_tab == 'boxes') ? 'active' : ''; ?>"><i class="fas fa-gift"></i> My Boxes</a></li>
            <li><a href="?tab=wishlist" class="<?php echo ($active_tab == 'wishlist') ? 'active' : ''; ?>"><i class="fas fa-heart"></i> Wishlist</a></li>
        </ul>
    </div>

    <!-- MAIN CONTENT -->
    <div class="profile-main">
        
        <!-- TAB 1: PROFILE SETTINGS -->
        <div class="tab-content <?php echo ($active_tab == 'profile') ? 'active' : ''; ?>" id="tab-profile">
            <?php include 'profile_settings.php'; ?>
        </div>

        <!-- TAB 2: ADDRESSES -->
        <div class="tab-content <?php echo ($active_tab == 'addresses') ? 'active' : ''; ?>" id="tab-addresses">
            <?php include 'profile_addresses.php'; ?>
        </div>

        <!-- TAB 3: ORDER HISTORY -->
        <div class="tab-content <?php echo ($active_tab == 'orders') ? 'active' : ''; ?>" id="tab-orders">
            <?php include 'profile_orders.php'; ?>
        </div>

        <!-- TAB: MY BOXES -->
        <div class="tab-content <?php echo ($active_tab == 'boxes') ? 'active' : ''; ?>" id="tab-boxes">
            <?php include 'profile_boxes.php'; ?>
        </div>

        <!-- TAB 4: WISHLIST -->
<div class="tab-content <?php echo ($active_tab == 'wishlist') ? 'active' : ''; ?>" id="tab-wishlist">
    <?php include 'profile_wishlist.php'; ?>
</div>

    </div>
</div>

<?php include 'footer.php'; ?>