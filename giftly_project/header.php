<?php
// Buffer output so pages that call header()/redirects after including this
// file (e.g. an auth check placed below the include) still work.
if (ob_get_level() === 0) { ob_start(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giftly - Premium Gift Boxes</title>
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- GLOBAL STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #fcfcfc; color: #333; padding-top: 10px; } 
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        
        /* --- NAVBAR (Glassmorphism) --- */
        nav {
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
            width: 95%; max-width: 1200px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 100px;
            padding: 12px 30px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.04);
            z-index: 1000;
            border: 1px solid rgba(255,255,255,0.5);
        }
        .nav-logo { font-size: 22px; font-weight: 700; color: #ff8ba7; display: flex; align-items: center; gap: 5px; }
        .nav-links { display: flex; gap: 25px; font-size: 14px; font-weight: 500; color: #555; }
        .nav-links a { 
            transition: 0.2s; 
            position: relative;
            padding-bottom: 2px;
        }
        .nav-links a:hover { color: #ff8ba7; }
        
        /* --- ACTIVE NAV LINK STYLES --- */
        .nav-links a.active {
            color: #ff8ba7;
            font-weight: 600;
        }
        .nav-links a.active::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 100%;
            height: 2.5px;
            background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
            border-radius: 10px;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { width: 0; opacity: 0; }
            to { width: 100%; opacity: 1; }
        }
        
        .nav-actions { display: flex; align-items: center; gap: 20px; }
        .nav-actions i { font-size: 18px; color: #555; cursor: pointer; }
        .btn-nav-login { 
            background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
            color: #fff; padding: 6px 24px; border-radius: 50px; 
            font-weight: 500; font-size: 14px; border: none; cursor: pointer; transition: 0.2s;
            box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
        }
        .btn-nav-login:hover { 
            background: linear-gradient(135deg, #ff8ba7 0%, #FEA5B6 100%); 
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4);
        }

        .btn-nav-signup {
            background: rgba(0, 0, 0, 0.05);
            color: #444;
            padding: 6px 24px;
            border-radius: 50px;
            font-weight: 500;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.02);
        }
        .btn-nav-signup:hover {
            background: rgba(0, 0, 0, 0.10);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.04);
            transform: scale(1.05);
        }

        .profile-icon-link {
            display: flex;
            align-items: center;
            color: #555;
            text-decoration: none;
            margin-right: 5px;
            transition: transform 0.2s ease;
        }
        .profile-icon-link:hover {
            transform: scale(1.1);
        }
        .profile-icon-link i {
            font-size: 32px;
            color: #ff8ba7;
            transition: color 0.2s ease;
        }
        .profile-icon-link:hover i {
            color: #FEA5B6;
        }

        /* --- SECTION TITLES --- */
        .section-title { text-align: center; font-size: 24px; font-weight: 600; margin-bottom: 30px; color: #222; }

        /* --- BUTTONS --- */
        .btn-primary { background: #ffc1cc; color: white; padding: 12px 25px; border-radius: 50px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { background: #ff8ba7; transform: translateY(-2px); }
        .btn-secondary { background: transparent; color: #222; border: 1px solid #ddd; padding: 12px 25px; border-radius: 50px; font-weight: 600; font-size: 14px; cursor: pointer; transition: 0.2s; }
        .btn-secondary:hover { border-color: #ff8ba7; color: #ff8ba7; }
        
        /* --- PRODUCT & SHOP STYLES --- */
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; background: #f8f8fa; padding: 40px 20px; border-radius: 35px; }
        .product-card { background: #fff; border-radius: 24px; padding: 15px 15px 20px; text-align: center; position: relative; box-shadow: 0 2px 10px rgba(0,0,0,0.02); transition: transform 0.3s, box-shadow 0.3s; }
        .product-card:hover { transform: translateY(-8px); box-shadow: 0 12px 25px rgba(0,0,0,0.05); }
        .p-image { width: 100%; height: 150px; object-fit: contain; margin-bottom: 10px; }
        .p-name { font-size: 14px; font-weight: 500; margin-bottom: 5px; }
        .p-price { font-weight: 600; font-size: 14px; color: #222; margin-bottom: 15px; }
        .btn-add-cart { background: #f3f3f3; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; color: #ff8ba7; font-size: 14px; transition: 0.2s; border: none; margin: 0 auto; cursor: pointer; }
        .btn-add-cart:hover { background: #ff8ba7; color: #fff; }

        /* --- SEARCH BAR --- */
        .search-box { text-align: center; margin-bottom: 30px; }
        .search-box input { padding: 12px 20px; width: 350px; border-radius: 30px; border: 1px solid #eee; outline: none; background: #fff; }
        .search-box button { padding: 12px 25px; border-radius: 30px; border: none; background: #ffc1cc; color: white; cursor: pointer; transition: 0.2s; margin-left: 10px;}
        .search-box button:hover { background: #ff8ba7; }

        /* --- CART STYLES --- */
        .cart-box { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 24px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); }
        .cart-item { border-bottom: 1px solid #f0f0f0; padding: 15px 0; display: flex; justify-content: space-between; align-items: center; }
        .checkout-btn { display: block; width: 100%; background: #ff8ba7; color: white; padding: 15px; border: none; border-radius: 30px; font-size: 18px; font-weight: 600; text-align: center; margin-top: 20px; cursor: pointer; transition: 0.2s; }
        .checkout-btn:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(255,139,167,0.3); }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav>
        <div class="nav-logo">
            <img src="giftly-logo.png" alt="Giftly Logo" style="height: 40px; width: auto; display: block;">
        </div>
        
        <ul class="nav-links">
            <?php
            // Get the current page name
            $current_page = basename($_SERVER['PHP_SELF']);
            ?>
            
            <li><a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">Home</a></li>
            <li><a href="shop.php" class="<?php echo ($current_page == 'shop.php') ? 'active' : ''; ?>">Shop</a></li>
            
            <?php 
            if (isset($_SESSION['user_id'])) {
                $user_id = $_SESSION['user_id'];
                $check = $conn->query("SELECT role FROM users WHERE id = $user_id");
                if ($check) {
                    $data = $check->fetch_assoc();
                    if ($data['role'] == 'admin') {
                        echo '<li><a href="javascript:void(0)" onclick="openReauthModal()"><i class="fas fa-crown" style="color:#ff8ba7; margin-right:4px;"></i> Admin Panel</a></li>';
                    }
                }
            }
            ?>

            <li><a href="build-a-box.php" class="<?php echo ($current_page == 'build-a-box.php') ? 'active' : ''; ?>">Build-a-Box</a></li>
            <li><a href="#" class="<?php echo ($current_page == 'occasion-boxes.php') ? 'active' : ''; ?>">Occasion Boxes</a></li>
            <li><a href="#" class="<?php echo ($current_page == 'bundles.php') ? 'active' : ''; ?>">Bundles</a></li>
            <li><a href="#" class="<?php echo ($current_page == 'about.php') ? 'active' : ''; ?>">About</a></li>
            <li><a href="#" class="<?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>">Contact</a></li>
        </ul>

        <div class="nav-actions">
<a href="javascript:void(0)" onclick="openCartWithCheck()"><i class="fas fa-shopping-cart"></i></a>            
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- User is Logged In -->
                <a href="profile.php" class="profile-icon-link" style="display: flex; align-items: center; gap: 0;">
                    <?php 
                    // Fetch the profile picture from the database
                    $user_id = $_SESSION['user_id'];
                    $pic_query = $conn->query("SELECT profile_pic FROM users WHERE id = $user_id");
                    $pic_row = $pic_query->fetch_assoc();
                    $profile_pic = $pic_row['profile_pic'] ?? '';

                    if($profile_pic && file_exists("uploads/profile_pics/" . $profile_pic)): ?>
                        <img src="uploads/profile_pics/<?php echo $profile_pic; ?>" 
                             style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #ffc1cc; box-shadow: 0 2px 8px rgba(255, 139, 167, 0.2);">
                    <?php else: ?>
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 16px; border: 2px solid #ffc1cc;">
                            <?php 
                            $first_letter = strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1));
                            echo $first_letter;
                            ?>
                        </div>
                    <?php endif; ?>
                </a>

                <a href="javascript:void(0)" onclick="openLogoutModal()">
                    <button class="btn-nav-login" style="width: auto; padding: 6px 24px;">
                        Logout
                    </button>
                </a>

            <?php else: ?>
                <!-- User is NOT Logged In -->
                <a href="javascript:void(0)" onclick="openRegisterModal()">
                    <button class="btn-nav-signup">
                        Sign up
                    </button>
                </a>

                <a href="javascript:void(0)" onclick="openLoginModal()">
                    <button class="btn-nav-login">Login</button>
                </a>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- LOGIN MODAL -->
    <?php include 'modal_login.php'; ?>
    
    <!-- REGISTER MODAL -->
    <?php include 'modal_register.php'; ?>

    <!-- LOGOUT MODAL -->
    <?php include 'modal_logout.php'; ?>

    <!-- ADMIN CHOICE MODAL (For Admins right after login) -->
    <?php include 'modal_admin_choice.php'; ?>

    <!-- RE-AUTHENTICATE MODAL (For Admin switching back) -->
    <?php include 'modal_reauth.php'; ?>

    <script>
function openCartWithCheck() {
    // Check if user is logged in
    fetch('check_login.php')
    .then(response => response.json())
    .then(data => {
        if (data.logged_in) {
            window.location.href = 'cart.php';
        } else {
            // Just show the login modal - stay on same page!
            openLoginModal();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
</script>
</body>


</html>