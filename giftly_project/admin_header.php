<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giftly Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        // Resolve a stored product image (full URL from Supabase, or a legacy
        // filename that lives in /uploads) to a usable <img src>.
        window.imgUrl = function (v) {
            v = (v == null ? '' : String(v)).trim();
            if (!v) return '';
            return /^https?:\/\//i.test(v) ? v : 'uploads/' + v.replace(/^\/+/, '');
        };
    </script>

    <style>
        /* --- GLOBAL STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { 
            background: #fcfcfc; 
            color: #333; 
            display: flex; 
            min-height: 100vh; 
        }
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }

        /* --- ADMIN LAYOUT --- */
        .admin-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* --- SIDEBAR --- */
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
        .sidebar-header { margin-bottom: 40px; padding-left: 10px; }
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
        
        .sidebar-footer { margin-top: auto; border-top: 1px solid #f0f0f0; padding-top: 20px; }
        .sidebar-logout-btn {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%; padding: 12px; border-radius: 50px;
            background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
            color: white; font-weight: 600; transition: 0.2s;
            box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
        }
        .sidebar-logout-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4); }

        /* --- TOP HEADER (Right side) --- */
.admin-topbar {
    display: flex; 
    justify-content: space-between; 
    align-items: center;
    margin-bottom: 30px; 
    padding: 10px 0; 
    border-bottom: 1px solid #f0f0f0;
}
.admin-topbar h2 { 
    font-size: 22px; 
    font-weight: 700; 
    color: #222; 
}
.topbar-right { 
    display: flex; 
    align-items: center; 
    gap: 20px; 
}
        .topbar-search {
            display: flex; align-items: center; gap: 10px;
            background: #f5f5f5; padding: 8px 16px; border-radius: 50px;
            border: 1px solid transparent; transition: 0.2s;
        }
        .topbar-search:focus-within { border-color: #ff8ba7; background: #fff; }
        .topbar-search input { border: none; background: transparent; outline: none; font-family: 'Poppins'; font-size: 14px; width: 200px; }
        .topbar-search i { color: #888; }
        .admin-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: #ff8ba7; color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 16px;
        }

                     /* --- MAIN CONTENT --- */
        .admin-main {
            flex: 1;
            padding: 40px 40px 0 40px; /* Padding on Top, Left, Right. NO padding on Bottom */
            background: #fcfcfc;
            display: flex;             
            flex-direction: column;    
        }
        .main-wrapper { 
            max-width: 1200px; 
            margin: 0 auto; 
            width: 100%;
            flex: 1; /* Forces the wrapper to stretch to the bottom */
        }

        .admin-card { background: #fff; border-radius: 24px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); }
        
        /* --- STATS CARDS --- */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { padding: 25px; border-radius: 20px; background: #fff; box-shadow: 0 5px 20px rgba(0,0,0,0.03); border-left: 5px solid #ff8ba7; display: flex; flex-direction: column; }
        .stat-number { font-size: 32px; font-weight: 700; margin-bottom: 5px; }
        .stat-label { font-size: 14px; color: #888; }
        .stat-card:nth-child(2) { border-left-color: #ffc107; }
        .stat-card:nth-child(3) { border-left-color: #17a2b8; }

        /* --- GRID FOR LAST ORDERS & TOP PRODUCTS --- */
        .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
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

        /* --- RESPONSIVE --- */
        @media (max-width: 900px) {
            .admin-sidebar { display: none; } /* Hidden on small screens for now */
            .stats-grid { grid-template-columns: 1fr; }
            .dashboard-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar is included here -->
        <?php include 'admin_sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="admin-main">


                <!-- LOGOUT MODAL -->
        <?php include 'modal_logout.php'; ?>

        <!-- LOGIN MODAL (Reused from Customer Side) -->
        <?php include 'modal_login.php'; ?>
    </body>
</html>

<script>
    // 🚨 Auto-open login modal on admin pages if there's a login error
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('login_error')) {
        // Small delay to ensure the page is fully loaded
        setTimeout(function() {
            openLoginModal();
        }, 300);
    }
});
</script>