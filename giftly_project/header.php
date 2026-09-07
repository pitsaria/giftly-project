<?php
// Buffer output so pages that call header()/redirects after including this
// file (e.g. an auth check placed below the include) still work.
if (ob_get_level() === 0) { ob_start(); }

// --- site-wide promo banner: newest active promo that has a code ---
$__promo_banner = null;
if (isset($conn)) {
    if (file_exists(__DIR__ . '/promo_lib.php')) {
        include_once __DIR__ . '/promo_lib.php';
        if (function_exists('promo_ensure_schema')) {
            promo_ensure_schema($conn);
            $__pbq = $conn->query("SELECT code, type, value, min_spend FROM promos
                                   WHERE code IS NOT NULL AND active = TRUE
                                     AND (starts_at IS NULL OR starts_at <= (CURRENT_TIMESTAMP AT TIME ZONE 'UTC'))
                                     AND (ends_at   IS NULL OR ends_at   >  (CURRENT_TIMESTAMP AT TIME ZONE 'UTC'))
                                   ORDER BY id DESC LIMIT 1");
            if ($__pbq && $__pbq->num_rows) {
                $__pb = $__pbq->fetch_assoc();
                switch ($__pb['type']) {
                    case 'percent':       $__eff = rtrim(rtrim(number_format((float) $__pb['value'], 2), '0'), '.') . '% off'; break;
                    case 'fixed':         $__eff = 'PHP ' . number_format((float) $__pb['value'], 2) . ' off'; break;
                    case 'free_shipping': $__eff = 'free shipping'; break;
                    case 'free_item':     $__eff = 'a free gift'; break;
                    default:              $__eff = 'a discount';
                }
                $__min = ((float) $__pb['min_spend'] > 0) ? ' on orders over PHP ' . number_format((float) $__pb['min_spend'], 0) : '';
                $__promo_banner = [
                    'code' => strtoupper($__pb['code']),
                    'msg'  => 'Use code <b>' . htmlspecialchars(strtoupper($__pb['code'])) . '</b> at checkout for ' . $__eff . $__min,
                ];
            }
        }
    }
}
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
        body { background: #fcfcfc; color: #333; padding-top: 10px; } 
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }

        /* --- site-wide promo banner --- */
        .promo-banner {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1100;
            background: linear-gradient(135deg, #ff8ba7 0%, #e6398f 100%);
            color: #fff; font-size: 13px; font-weight: 600;
            display: flex; align-items: center; justify-content: center;
            gap: 10px; padding: 9px 46px 9px 18px; text-align: center;
            box-shadow: 0 2px 10px rgba(230, 57, 143, .25);
        }
        .promo-banner b { font-weight: 800; letter-spacing: .5px; }
        .promo-banner .pb-close {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,.22); border: none; color: #fff;
            width: 22px; height: 22px; border-radius: 50%; cursor: pointer;
            font-size: 15px; line-height: 20px; padding: 0;
        }
        .promo-banner .pb-close:hover { background: rgba(255,255,255,.4); }
        body.has-promo-banner nav { top: 50px; }
        @media (max-width: 600px) {
            .promo-banner { font-size: 11.5px; padding: 8px 40px 8px 12px; }
            body.has-promo-banner nav { top: 64px; }
        }

        /* --- light-gray placeholder / sample text so it's clearly not filled in yet --- */
        ::placeholder { color: #b3b3b3 !important; opacity: 1; }
        ::-webkit-input-placeholder { color: #b3b3b3 !important; }
        :-ms-input-placeholder { color: #b3b3b3 !important; }
        ::-ms-input-placeholder { color: #b3b3b3 !important; }
        input::placeholder, textarea::placeholder { color: #b3b3b3 !important; opacity: 1; }
        .field-hint, .form-hint { color: #9a9a9a !important; }

        /* --- cart icon count badge --- */
        .cart-icon-link { position: relative; display: inline-flex; }
        .cart-badge {
            position: absolute; top: -7px; right: -9px;
            min-width: 18px; height: 18px; padding: 0 4px;
            background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
            color: #fff; font-size: 11px; font-weight: 700;
            border-radius: 50px; display: none;
            align-items: center; justify-content: center;
            box-shadow: 0 2px 6px rgba(254, 165, 182, 0.5);
            line-height: 1;
        }
        .cart-badge.show { display: flex; }
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
<body class="<?php echo $__promo_banner ? 'has-promo-banner' : ''; ?>">
    <?php if ($__promo_banner): ?>
    <div id="promoBanner" class="promo-banner" data-code="<?php echo htmlspecialchars($__promo_banner['code']); ?>">
        <span><i class="fas fa-tags"></i> <?php echo $__promo_banner['msg']; ?></span>
        <button type="button" class="pb-close" aria-label="Dismiss" onclick="dismissPromoBanner()">&times;</button>
    </div>
    <script>
        (function () {
            try {
                var b = document.getElementById('promoBanner');
                if (b && localStorage.getItem('promoBannerDismissed') === b.dataset.code) {
                    b.remove();
                    document.body.classList.remove('has-promo-banner');
                }
            } catch (e) {}
        })();
        function dismissPromoBanner() {
            var b = document.getElementById('promoBanner');
            try { if (b) localStorage.setItem('promoBannerDismissed', b.dataset.code); } catch (e) {}
            if (b) b.remove();
            document.body.classList.remove('has-promo-banner');
        }
    </script>
    <?php endif; ?>
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
            <li><a href="occasion-boxes.php" class="<?php echo ($current_page == 'occasion-boxes.php') ? 'active' : ''; ?>">Occasion Boxes</a></li>
            <li><a href="baskets.php" class="<?php echo ($current_page == 'baskets.php') ? 'active' : ''; ?>">Baskets</a></li>
            <li><a href="about.php" class="<?php echo ($current_page == 'about.php') ? 'active' : ''; ?>">About</a></li>
            <li><a href="contact.php" class="<?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>">Contact</a></li>
        </ul>

        <div class="nav-actions">
<?php
$header_cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $__cc = $conn->query("SELECT COALESCE(SUM(quantity), 0) AS c FROM carts WHERE user_id = " . (int) $_SESSION['user_id']);
    if ($__cc) $header_cart_count = (int) $__cc->fetch_assoc()['c'];
}
?>
<a href="javascript:void(0)" onclick="openCartWithCheck()" class="cart-icon-link" aria-label="Cart"><i class="fas fa-shopping-cart"></i><span class="cart-badge <?php echo $header_cart_count > 0 ? 'show' : ''; ?>" id="cartBadge"><?php echo $header_cart_count; ?></span></a>
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
// Refresh the cart-icon count badge (call after any add-to-cart)
window.updateCartBadge = function () {
    fetch('get_cart_count.php', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var b = document.getElementById('cartBadge');
            if (!b) return;
            var n = parseInt(d.count, 10) || 0;
            b.textContent = n;
            b.classList.toggle('show', n > 0);
        })
        .catch(function () {});
};

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