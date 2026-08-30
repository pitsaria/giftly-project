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

// 🚨 Check if there's a login error and show the modal
$login_error = isset($_GET['login_error']) ? $_GET['login_error'] : '';
if ($login_error) {
    // Don't redirect, just show the modal
    $show_login_modal = true;
}

// Get Stats for the Dashboard
$order_count = $conn->query("SELECT COUNT(*) as total FROM orders")->fetch_assoc()['total'];
$product_count = $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'];
$user_count = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='customer'")->fetch_assoc()['total'];

// Get Last 4 Orders
$last_orders = $conn->query("SELECT orders.*, users.name as customer_name FROM orders JOIN users ON orders.user_id = users.id ORDER BY created_at DESC LIMIT 4");

// Get Top 4 Products (by total sold quantity)
$top_products = $conn->query("SELECT p.name, p.image, p.price, SUM(oi.quantity) as total_sold FROM order_items oi JOIN products p ON oi.product_id = p.id GROUP BY p.id ORDER BY total_sold DESC LIMIT 4");

include 'admin_header.php'; 
?>

<style>
    .main-wrapper { 
        max-width: 1200px; 
        margin: 0 auto; 
        margin-bottom: 20px;
    }

    /* --- DASHBOARD HERO (The Carousel) --- */
    .dashboard-hero {
        border-radius: 30px;
        overflow: hidden;
        margin-bottom: 30px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
        position: relative;
        height: 320px;
        background: linear-gradient(225deg, #FFDBDF 0%, #fff4d8 20%, #ffffff 60%, #E2D5F1 150%);
        display: flex;
        align-items: center;
        padding: 0 50px;
    }

    .hero-text {
        flex: 1;
        padding-right: 20px;
    }
    .hero-text h1 {
        font-size: 32px;
        font-weight: 700;
        color: #222;
        margin-bottom: 10px;
    }
    .hero-text h1 span {
        color: #ff8ba7;
    }
    .hero-text p {
        font-size: 16px;
        color: #666;
        max-width: 400px;
        line-height: 1.5;
    }

    .hero-image {
        flex: 1;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        height: 100%;
    }
    .hero-image img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    /* --- STATS CARDS (WITH FLOAT ON HOVER) --- */
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
    
    .stat-card { 
        padding: 25px; 
        border-radius: 20px; 
        background: #fff; 
        box-shadow: 0 5px 20px rgba(0,0,0,0.03); 
        border-left: 5px solid #ff8ba7; 
        display: flex; 
        flex-direction: column; 
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .stat-card:hover { 
        transform: translateY(-6px); 
        box-shadow: 0 15px 35px rgba(0,0,0,0.06); 
    }
    .stat-number { font-size: 32px; font-weight: 700; margin-bottom: 5px; }
    .stat-label { font-size: 14px; color: #888; }
    .stat-card:nth-child(2) { border-left-color: #ffc107; }
    .stat-card:nth-child(3) { border-left-color: #17a2b8; }

    /* --- GRID FOR LAST ORDERS & TOP PRODUCTS (WITH FLOAT ON HOVER) --- */
    .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    
    .admin-card { 
        background: #fff; 
        border-radius: 24px; 
        padding: 30px; 
        box-shadow: 0 5px 20px rgba(0,0,0,0.03); 
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .admin-card:hover { 
        transform: translateY(-6px); 
        box-shadow: 0 15px 35px rgba(0,0,0,0.06); 
    }
    
    .order-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
    .order-item:last-child { border-bottom: none; }
    .order-info { display: flex; flex-direction: column; gap: 4px; }
    .order-info strong { font-size: 14px; color: #222; }
    .order-info span { font-size: 12px; color: #888; }
    .order-status { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #fff0f5; color: #d32f2f; }
    .order-status.shipped { background: #fff3e0; color: #e65100; }
    .order-status.delivered { background: #e8f5e9; color: #2e7d32; }

    .product-list-item { display: flex; align-items: center; gap: 15px; padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
    .product-list-item:last-child { border-bottom: none; }
    .pl-img { width: 50px; height: 50px; border-radius: 12px; background: #fafafa; object-fit: cover; }
    .pl-info { flex: 1; }
    .pl-info h4 { font-size: 14px; font-weight: 600; color: #222; }
    .pl-info p { font-size: 12px; color: #888; }
    .pl-price { font-weight: 600; font-size: 14px; color: #222; }

    /* --- CUSTOM GO TO SHOP BUTTON --- */
    .btn-go-shop {
        display: inline-flex; align-items: center; gap: 6px;
        background: #f5f5f5; color: #444;
        padding: 6px 18px; border-radius: 50px;
        font-size: 14px; font-weight: 500; text-decoration: none;
        transition: all 0.2s ease;
    }
    .btn-go-shop:hover { background: #ff8ba7; color: white; transform: translateY(-2px); }

    /* --- RESPONSIVE --- */
    @media (max-width: 700px) {
        .dashboard-hero { flex-direction: column; height: auto; padding: 30px 20px; text-align: center; }
        .hero-text { padding-right: 0; margin-bottom: 20px; }
        .hero-image { width: 100%; justify-content: center; }
        .stats-grid { grid-template-columns: 1fr; }
        .dashboard-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="main-wrapper">
    <!-- Top Bar -->
<div class="admin-topbar">
    <div style="display: flex; align-items: center; gap: 15px;">
        <h2 style="margin:0;">Dashboard</h2>
    </div>
</div>

    <!-- DASHBOARD HERO (CAROUSEL TOP CARD) -->
    <div class="dashboard-hero">
        <div class="hero-text">
            <h1>Welcome back, <span>Admin!</span></h1>
            <p>Everything is ready for you. Manage your products, track orders, and keep growing your store.</p>
        </div>
        <div class="hero-image">
            <img src="giftbox.png" alt="Gift Box">
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number" style="color: #ff8ba7;"><?php echo $order_count; ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #ffc107;"><?php echo $product_count; ?></div>
            <div class="stat-label">Products in Inventory</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #17a2b8;"><?php echo $user_count; ?></div>
            <div class="stat-label">Registered Customers</div>
        </div>
    </div>

    <!-- Dashboard Grid: Last Orders & Top Products -->
    <div class="dashboard-grid">
        
        <!-- Last Orders Column -->
        <div class="admin-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="font-size: 18px; font-weight: 600; color: #222;">Last Orders</h3>
                <a href="admin_orders.php" style="font-size: 13px; color: #ff8ba7;">View All</a>
            </div>
            
            <?php if ($last_orders->num_rows > 0): ?>
                <?php while($row = $last_orders->fetch_assoc()): 
                    $class = '';
                    if($row['status'] == 'shipped') $class = 'shipped';
                    if($row['status'] == 'delivered') $class = 'delivered';
                ?>
                <div class="order-item">
                    <div class="order-info">
                        <strong>#<?php echo $row['id']; ?> - <?php echo $row['customer_name']; ?></strong>
                        <span>PHP <?php echo number_format($row['total_amount'], 2); ?></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="order-status <?php echo $class; ?>"><?php echo ucfirst($row['status']); ?></span>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: #888; text-align: center; padding: 20px;">No orders yet.</p>
            <?php endif; ?>
        </div>

        <!-- Top Products Column -->
        <div class="admin-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="font-size: 18px; font-weight: 600; color: #222;">Top Selling Products</h3>
                <a href="admin_products.php" style="font-size: 13px; color: #ff8ba7;">View All</a>
            </div>
            
            <?php if ($top_products->num_rows > 0): ?>
                <?php while($row = $top_products->fetch_assoc()): ?>
                <div class="product-list-item">
                    <img src="<?php echo htmlspecialchars(img_url($row['image'])); ?>" class="pl-img">
                    <div class="pl-info">
                        <h4><?php echo $row['name']; ?></h4>
                        <p>Sold: <?php echo $row['total_sold']; ?> units</p>
                    </div>
                    <div class="pl-price">PHP <?php echo number_format($row['price'], 2); ?></div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: #888; text-align: center; padding: 20px;">No sales yet.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include 'admin_footer.php'; ?>