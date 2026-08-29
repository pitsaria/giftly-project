<?php
include 'db_connect.php';
include 'catalog_lib.php';
catalog_ensure_schema($conn);
include 'header.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_id = isset($_GET['category']) ? $_GET['category'] : '';

// --- PAGINATION LOGIC ---
$limit = 20; 
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) as total FROM products WHERE 1=1";
if (!empty($search)) { $count_sql .= " AND name LIKE '%$search%'"; }
if (!empty($category_id)) { $count_sql .= " AND category_id = '$category_id'"; }
$count_res = $conn->query($count_sql);
$total_rows = $count_res->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// --- GET USER'S WISHLIST (for heart status) ---
$wishlist_ids = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $wishlist_query = $conn->query("SELECT product_id FROM wishlist WHERE user_id = $user_id");
    while ($wish = $wishlist_query->fetch_assoc()) {
        $wishlist_ids[] = $wish['product_id'];
    }
}

// --- FUNCTION TO CHECK IF PRODUCT IS IN WISHLIST ---
function isInWishlist($product_id, $wishlist_ids) {
    return in_array($product_id, $wishlist_ids);
}

?>


<style>
    /* --- CATEGORY PILLS --- */
    .category-nav { 
        display: flex; 
        justify-content: center; 
        align-items: center;
        gap: 0; 
        margin-bottom: 25px; 
        position: relative;
    }
    .cat-btn { 
        padding: 6px 18px; 
        border-radius: 50px; 
        border: 1.5px solid #ddd; 
        font-size: 13px; 
        font-weight: 500; 
        color: #555; 
        background: transparent; 
        text-decoration: none; 
        transition: all 0.2s ease; 
        font-family: 'Poppins', sans-serif; 
        display: inline-block; 
        white-space: nowrap; 
    }
    .cat-btn:hover { 
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: #fff; 
        border-color: #FEA5B6; 
        transform: translateY(-2px); 
    }
    .cat-btn.active { 
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: #fff; 
        border-color: #FEA5B6; 
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.3);
    }

    /* --- SCROLL CONTAINER --- */
    .cat-scroll-wrapper {
        max-width: 700px; 
        width: 100%;
        display: flex;
        align-items: center;
        overflow: hidden;
        position: relative;
        padding: 0 10px; 
        transition: padding 0.3s ease;
    }
    .cat-scroll-track {
        display: flex;
        gap: 12px;
        overflow-x: auto;
        scroll-behavior: smooth;
        padding: 5px 0;
        scrollbar-width: none;
        -ms-overflow-style: none;
        width: 100%;
        justify-content: flex-start;
    }
    .cat-scroll-track::-webkit-scrollbar { display: none; }

    /* --- ARROWS --- */
    .cat-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: #fff;
        border: 1.5px solid #eee;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: none; 
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        color: #555;
        transition: 0.2s;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .cat-arrow:hover {
        background: #ffc1cc;
        color: #fff;
        border-color: #ffc1cc;
        transform: translateY(-50%) scale(1.05);
    }
    .cat-arrow-left { left: 0px; margin-right: 8px; }
    .cat-arrow-right { right: 0px; margin-left: 8px; }

        /* --- 🎉 LIGHT & AIRY TRANSPARENT TOAST --- */
    .toast-alert {
        position: fixed; 
        top: 50%; 
        left: 50%; 
        transform: translate(-50%, -50%) scale(0.9);
        background: rgba(220, 220, 220, 0.35); /* Very light, very transparent gray */
        backdrop-filter: blur(12px); /* Stronger blur for a glassier feel */
        color: #333; /* Changed text to dark gray for readability */
        padding: 30px 45px;
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        z-index: 999999;
        display: none;
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        text-align: center;
        pointer-events: none;
        min-width: 180px;
    }
    .toast-alert.show {
        display: block;
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }
       .toast-check {
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 12px auto;
        width: 50px; height: 50px;
        background: linear-gradient(135deg, #81c784 0%, #66bb6a 100%); /* Darker pastel green */
        border-radius: 50%;
        color: #ffffff; 
        font-size: 22px;
        box-shadow: 0 4px 12px rgba(102, 187, 106, 0.35); /* Stronger green glow */
    }
    .toast-text { 
        font-size: 16px; 
        font-weight: 500; 
        letter-spacing: 0.5px; 
        color: #444;
    }
    
    /* --- SEARCH BOX --- */
    .search-box { text-align: center; margin-bottom: 30px; }
    .search-box input { padding: 10px 18px; width: 300px; border-radius: 30px; border: 1.5px solid #eee; outline: none; background: #fff; font-family: 'Poppins'; }
    .search-box input:focus { border-color: #ffc1cc; }
    .search-box button { padding: 10px 22px; border-radius: 30px; border: none; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: white; cursor: pointer; transition: 0.2s; margin-left: 8px; font-family: 'Poppins'; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2); }
    .search-box button:hover { background: linear-gradient(135deg, #ff8ba7 0%, #FEA5B6 100%); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4); }

    /* --- PREMIUM NIKE-STYLE PRODUCT CARD --- */
    .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 25px; padding: 10px 0 40px 0; }
    
    .product-card { 
        background: #ffffff; 
        border-radius: 24px; 
        padding: 20px; 
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04); 
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        border: 1px solid rgba(255, 255, 255, 0.8); 
        cursor: pointer; 
        display: flex; 
        flex-direction: column; 
        height: 100%; 
        position: relative;
    }
    .product-card:hover { 
        transform: translateY(-6px); 
        box-shadow: 0 15px 40px rgba(255, 139, 167, 0.12); 
    }

    /* --- IMAGE AREA --- */
    .p-image-container { 
        background: #f8f8fa; 
        border-radius: 20px; 
        padding: 25px 15px; 
        margin-bottom: 15px; 
        display: flex; 
        justify-content: center; 
        align-items: center; 
        height: 200px; 
        width: 100%;
        position: relative; 
    }
    .p-image { 
        max-width: 100%; 
        max-height: 100%; 
        object-fit: contain; 
        pointer-events: none; 
        transition: transform 0.4s ease; 
    }
    .product-card:hover .p-image { transform: scale(1.05); }

    /* --- BADGE & HEART ON IMAGE --- */
    .p-image-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        background: #ffffff;
        color: #333;
        padding: 4px 14px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        pointer-events: none;
    }
    /* --- WISHLIST HEART STYLES (REPLACE the existing .p-image-heart) --- */
.p-image-heart {
    position: absolute;
    top: 12px;
    right: 12px;
    background: #ffffff;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    color: #bbb;
    font-size: 17px;
    z-index: 5;
    pointer-events: auto !important;
}
.p-image-heart:hover {
    background: #fff0f5;
    transform: scale(1.15);
    box-shadow: 0 4px 15px rgba(255, 139, 167, 0.2);
}
.p-image-heart.active {
    color: #ff8ba7;
    background: #fff0f5;
}
.p-image-heart.active i {
    font-weight: 900;
    color: #ff8ba7;
}
.p-image-heart i {
    transition: all 0.3s ease;
    pointer-events: none;
}
.p-image-heart .fas {
    display: none;
}
.p-image-heart .far {
    display: block;
}
.p-image-heart.active .fas {
    display: block;
}
.p-image-heart.active .far {
    display: none;
}
.p-image-heart.loading {
    opacity: 0.5;
    pointer-events: none;
}

    /* --- PRODUCT NAME --- */
    .p-name { 
        font-size: 17px; 
        font-weight: 600; 
        color: #222; 
        line-height: 1.4; 
        margin-bottom: 12px; 
        text-align: left; 
    }

    /* --- BOTTOM ROW --- */
    .p-bottom-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: auto;
        padding-top: 10px;
        border-top: 1px solid #f5f5f5;
    }
    .p-price { 
        font-size: 16px; 
        font-weight: 500; 
        color: #888;
    }
    .p-price span { 
        font-weight: 700; 
        color: #222; 
    }

    .btn-action {
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; 
        border: none; 
        border-radius: 50px;
        padding: 8px 22px; 
        font-size: 14px; 
        font-weight: 600;
        cursor: pointer; 
        transition: all 0.3s ease;
        display: flex; 
        align-items: center; 
        gap: 8px;
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
        width: auto; 
        margin-left: auto; 
    }
    .btn-action:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 8px 20px rgba(254, 165, 182, 0.35);
    }
    .btn-action.disabled {
        background: #ccc !important; cursor: not-allowed !important; box-shadow: none !important; transform: none !important;
    }

    /* --- PAGINATION BUTTONS --- */
    .pagination-wrapper { display: flex; justify-content: center; gap: 8px; margin-top: 20px; margin-bottom: 40px; flex-wrap: wrap; }
    .page-btn { padding: 8px 16px; border: 1.5px solid #eee; border-radius: 30px; background: #fff; color: #555; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.2s; font-family: 'Poppins'; }
    .page-btn:hover { background: #ffc1cc; color: #fff; border-color: #ffc1cc; }
    .page-btn.active { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border-color: #FEA5B6; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.3); }
    .page-btn.disabled { opacity: 0.5; pointer-events: none; }

    /* --- UPGRADED PREMIUM MODAL UI --- */
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(8px);
        display: none; justify-content: center; align-items: center;
        z-index: 99998;
        padding: 20px;
    }
    .modal-box {
        background: #fff; border-radius: 30px; max-width: 850px; width: 100%;
        box-shadow: 0 25px 60px rgba(0,0,0,0.2);
        position: relative; overflow: hidden;
        display: flex; flex-direction: row; flex-wrap: wrap;
        animation: modalFadeIn 0.3s ease-out;
    }
    @keyframes modalFadeIn {
        from { transform: scale(0.95) translateY(10px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .modal-close {
        position: absolute; top: 15px; right: 20px;
        font-size: 24px; color: #888; cursor: pointer; transition: 0.2s; z-index: 2;
    }
    .modal-close:hover { color: #ff8ba7; transform: rotate(90deg); }

    .modal-left { flex: 0.9; min-width: 300px; background: #fafafa; padding: 40px; display: flex; justify-content: center; align-items: center; }
    .modal-img { width: 100%; max-height: 300px; object-fit: contain; border-radius: 16px; }

    .modal-right { flex: 1.1; min-width: 300px; padding: 45px 40px 35px 40px; display: flex; flex-direction: column; position: relative; }
    .modal-title { font-size: 28px; font-weight: 700; color: #222; margin-bottom: 5px; line-height: 1.2; }
    .modal-price { font-size: 24px; font-weight: 700; color: #111; margin-bottom: 15px; }
    .modal-desc { font-size: 15px; color: #666; line-height: 1.6; margin-bottom: 25px; word-wrap: break-word; overflow-wrap: break-word; }
    
    #modalStock { font-size: 14px; font-weight: 500; margin-bottom: 15px; color: #2e7d32; }

              /* --- MODAL ACTION ROW (LEFT-ALIGNED, VERTICALLY STACKED) --- */
    .modal-actions-row {
        display: flex;
        flex-direction: column; /* Forces vertical stacking */
        align-items: flex-start; /* PINS EVERYTHING TO THE LEFT */
        gap: 12px;
        margin-top: auto;
        width: 100%;
    }

    /* --- QUANTITY WRAPPER (Left aligned) --- */
    .qty-wrapper {
        display: flex;
        align-items: center;
        border: 1px solid #eee;
        border-radius: 50px;
        padding: 4px 12px;
        background: #fff;
        width: fit-content;
        margin: 0; /* Removes any centering margins */
    }
    .qty-btn { background: transparent; border: none; font-size: 20px; cursor: pointer; padding: 0 8px; color: #555; font-family: 'Poppins'; }
    .qty-btn:hover { color: #ff8ba7; }
    .qty-display { font-size: 18px; font-weight: 600; min-width: 30px; text-align: center; color: #222; }
    
    /* --- ADD TO CART BUTTON (Full width, aligned left) --- */
    .btn-modal-add {
        width: 100%;
        padding: 12px 0; 
        border: none; 
        border-radius: 50px;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; 
        font-size: 14px; 
        font-weight: 600; 
        cursor: pointer; 
        transition: 0.2s;
        display: flex; 
        justify-content: center; 
        align-items: center; 
        gap: 8px;
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
        margin: 0; /* Ensures it starts strictly from the left */
    }
    .btn-modal-add:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4); }

    /* --- BUY NOW BUTTON (Full width, aligned left) --- */
    .btn-modal-buy {
        width: 100%;
        padding: 12px 0; 
        border: none; 
        border-radius: 50px;
        background: #eaeaea; 
        color: #444; 
        font-size: 14px; 
        font-weight: 600; 
        cursor: pointer; 
        transition: 0.2s;
        display: flex; 
        justify-content: center; 
        align-items: center; 
        gap: 8px;
        margin: 0; /* Ensures it starts strictly from the left */
    }
    .btn-modal-buy:hover { background: #d6d6d6; transform: translateY(-2px); }

    .btn-modal-buy.disabled, .btn-modal-add.disabled {
        background: #ccc !important; color: #888 !important; cursor: not-allowed !important; transform: none !important; box-shadow: none !important;
    }

    /* --- OUT OF STOCK PRODUCTS - NEW DESIGN --- */
.product-card.out-of-stock-product {
    opacity: 0.85;
    border-color: #e8e8e8;
    cursor: default;
    position: relative;
    overflow: hidden;
}
.product-card.out-of-stock-product:hover {
    transform: none;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
}
.product-card.out-of-stock-product .p-image-container {
    background: #f5f5f5;
    position: relative;
}
.product-card.out-of-stock-product .p-image {
    opacity: 0.4;
    filter: grayscale(0.8);
}
.product-card.out-of-stock-product .p-name {
    color: #999;
}
.product-card.out-of-stock-product .p-price {
    color: #ccc;
}
.product-card.out-of-stock-product .p-price span {
    color: #ccc;
}

/* --- OUT OF STOCK OVERLAY BADGE --- */
.out-of-stock-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(4px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 20px;
    z-index: 5;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}
.product-card.out-of-stock-product:hover .out-of-stock-overlay {
    opacity: 1;
}
.out-of-stock-overlay i {
    font-size: 40px;
    color: #d32f2f;
    margin-bottom: 8px;
}
.out-of-stock-overlay span {
    font-size: 16px;
    font-weight: 700;
    color: #d32f2f;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* --- OUT OF STOCK RIBBON --- */
.out-of-stock-ribbon {
    position: absolute;
    top: 12px;
    right: -30px;
    background: #d32f2f;
    color: white;
    padding: 4px 30px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transform: rotate(45deg);
    box-shadow: 0 2px 8px rgba(211, 47, 47, 0.3);
    z-index: 6;
    pointer-events: none;
    white-space: nowrap;
}

/* --- OUT OF STOCK BUTTON --- */
.btn-action.out-of-stock-btn {
    background: #e8e8e8 !important;
    color: #999 !important;
    cursor: not-allowed !important;
    box-shadow: none !important;
    transform: none !important;
    pointer-events: none;
}
.btn-action.out-of-stock-btn i {
    color: #bbb;
}
.btn-action.out-of-stock-btn:hover {
    transform: none !important;
    box-shadow: none !important;
}

/* --- STOCK ALERT MODAL --- */
.stock-alert-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(6px);
    display: none; justify-content: center; align-items: center;
    z-index: 999999; padding: 20px;
}
.stock-alert-box {
    background: #ffffff; border-radius: 30px; padding: 40px;
    max-width: 400px; width: 90%; text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    animation: fadeUp 0.3s ease;
}
@keyframes fadeUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.stock-alert-icon {
    font-size: 50px; 
    color: #f9a825; 
    margin-bottom: 15px;
}
.stock-alert-icon i {
    background: #fff8e1; 
    padding: 15px; 
    border-radius: 50%;
}
.stock-alert-title {
    font-size: 22px; 
    font-weight: 700; 
    color: #222; 
    margin-bottom: 5px;
}
.stock-alert-sub {
    font-size: 14px; 
    color: #888; 
    margin-bottom: 25px; 
    line-height: 1.5;
}
.stock-alert-btn {
    padding: 12px 40px; 
    border: none; 
    border-radius: 50px;
    background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
    color: white; 
    font-weight: 600; 
    font-size: 15px; 
    cursor: pointer; 
    transition: 0.2s; 
    font-family: 'Poppins';
    box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
}
.stock-alert-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4);
}
</style>

<!-- 🎉 NEW PREMIUM TOAST ALERT -->
<div id="toastAlert" class="toast-alert">
    <div class="toast-check"><i class="fas fa-check"></i></div>
    <div class="toast-text">Added to cart</div>
</div>

<div class="container" style="padding-top: 130px;">
    <h2 class="section-title" style="margin-bottom: 20px;">Browse Our Products</h2>
    
    <div class="category-nav">
        <div class="cat-scroll-wrapper">
            <div class="cat-arrow cat-arrow-left" onclick="scrollCategories('left')">
                <i class="fas fa-chevron-left"></i>
            </div>
            <div class="cat-scroll-track" id="catScrollTrack">
                <a href="shop.php" class="cat-btn <?php echo empty($category_id) ? 'active' : ''; ?>">All Items</a>
                <?php
                $cat_sql = "SELECT * FROM categories ORDER BY name ASC";
                $cat_result = $conn->query($cat_sql);
                if ($cat_result->num_rows > 0) {
                    while($cat_row = $cat_result->fetch_assoc()) {
                        $cat_name = $cat_row['name'];
                        $active_class = ($category_id == $cat_row['id']) ? 'active' : '';
                        echo '<a href="shop.php?category='.$cat_row['id'].'" class="cat-btn '.$active_class.'">'.$cat_name.'</a>';
                    }
                }
                ?>
            </div>
            <div class="cat-arrow cat-arrow-right" onclick="scrollCategories('right')">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>
    </div>

    <div class="search-box">
        <form action="shop.php" method="GET">
            <input type="hidden" name="category" value="<?php echo $category_id; ?>">
            <input type="text" name="search" placeholder="Search..." value="<?php echo $search; ?>">
            <button type="submit">Search</button>
        </form>
    </div>

   <div class="product-grid">
       <?php
   // ✅ FETCH PRODUCTS FROM API (Oldest first)
$api_url = 'http://127.0.0.1:' . ($_SERVER['SERVER_PORT'] ?? 80) . '/api/index.php?route=products&order=asc&type=catalog';
if (!empty($search)) {
    $api_url .= '&search=' . urlencode($search);
}
if (!empty($category_id)) {
    $api_url .= '&category=' . $category_id;
}
$api_url .= '&page=' . $page . '&limit=' . $limit;
    
    $response = file_get_contents($api_url);
    $result = json_decode($response, true);
    
    $products = [];
    if (isset($result['status']) && $result['status'] == 'success') {
        $products = $result['data']['products'] ?? [];
        $total_rows = $result['data']['pagination']['total'] ?? 0;
        $total_pages = $result['data']['pagination']['total_pages'] ?? 1;
    }

    if (!empty($products)) {
    foreach($products as $row) {
        $isInStock = $row['quantity'] > 0;
        $onClick = $isInStock ? "openModal(".$row['id'].", '".addslashes($row['name'])."', '".addslashes($row['description'])."', '".$row['image']."', ".$row['price'].", ".$row['quantity'].")" : "";
        $cardClass = $isInStock ? 'product-card' : 'product-card out-of-stock-product';

        // Check if product is in wishlist
$isInWishlist = in_array($row['id'], $wishlist_ids);
$heartClass = $isInWishlist ? 'active' : '';
        $heartIcon = $isInWishlist ? 'fas' : 'far';

        // BEST SELLER LOGIC
        $totalSold = 0;
        $sales_check = $conn->query("SELECT SUM(quantity) as total FROM order_items WHERE product_id = ".$row['id']);
        if($sales_check && $sales_check->num_rows > 0) {
            $sale_data = $sales_check->fetch_assoc();
            $totalSold = intval($sale_data['total']);
        }

        echo '
        <div class="'.$cardClass.'">
            
            <div class="p-image-container" onclick="'.$onClick.'">
                <img src="uploads/'.$row['image'].'" class="p-image" alt="'.$row['name'].'">
                
                <!-- 🚀 WISHLIST HEART BUTTON -->
                <button class="p-image-heart '.$heartClass.'" onclick="event.stopPropagation(); toggleWishlist(this, '.$row['id'].')" data-product-id="'.$row['id'].'">
                    <i class="fas fa-heart"></i>
                    <i class="far fa-heart"></i>
                </button>
                
                ' . (!$isInStock ? '<div class="out-of-stock-ribbon">Out of Stock</div>' : '') . '
                
                ' . (!$isInStock ? '
                <div class="out-of-stock-overlay">
                    <i class="fas fa-times-circle"></i>
                    <span>Out of Stock</span>
                </div>' : '') . '
                
                ' . ($totalSold > 10 ? '<div class="p-image-badge">Best Seller</div>' : '') . '
            </div>
            
            <div class="p-name" onclick="'.$onClick.'">'.$row['name'].'</div>
            
            <div class="p-bottom-row">
                <div class="p-price" onclick="'.$onClick.'">PHP <span>'.number_format($row['price'], 2).'</span></div>
                
                ' . ($isInStock ? '
                <button class="btn-action" onclick="event.stopPropagation(); quickAdd('.$row['id'].')">
                    <i class="fas fa-shopping-cart"></i> Add
                </button>' : '
                <button class="btn-action out-of-stock-btn" onclick="event.stopPropagation();">
                    <i class="fas fa-times-circle"></i> Unavailable
                </button>') . '
                
            </div>
        </div>
        ';
    }
} else {
    echo "<p style='text-align:center; grid-column: span 4; padding: 40px; color:#888;'>No products found.</p>";
}
    ?>
</div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination-wrapper">
        <a href="shop.php?page=<?php echo ($page > 1) ? $page - 1 : 1; ?>&category=<?php echo $category_id; ?>&search=<?php echo $search; ?>" class="page-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
            &larr; Previous
        </a>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="shop.php?page=<?php echo $i; ?>&category=<?php echo $category_id; ?>&search=<?php echo $search; ?>" class="page-btn <?php echo ($i == $page) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        <a href="shop.php?page=<?php echo ($page < $total_pages) ? $page + 1 : $total_pages; ?>&category=<?php echo $category_id; ?>&search=<?php echo $search; ?>" class="page-btn <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
            Next &rarr;
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- UPGRADED QUICK VIEW MODAL WITH BUY NOW -->
<div class="modal-overlay" id="productModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <div class="modal-left">
            <img id="modalImg" src="" class="modal-img">
        </div>
        <div class="modal-right">
            <div id="modalTitle" class="modal-title">Product Name</div>
            <div id="modalPrice" class="modal-price">PHP 0.00</div>
            <div id="modalDesc" class="modal-desc">Product Description</div>
            <div id="modalStock"></div>

                                    <div class="modal-actions-row">
                
                <!-- QUANTITY (Centered on top) -->
                <div class="qty-wrapper">
                    <button class="qty-btn" onclick="updateQty(-1)">−</button>
                    <span id="qtyDisplay" class="qty-display">1</span>
                    <button class="qty-btn" onclick="updateQty(1)">+</button>
                </div>
                
                <!-- ADD TO CART (Directly underneath quantity) -->
                <button id="modalAddBtn" class="btn-modal-add" onclick="addFromModal()">
                    <i class="fas fa-shopping-cart"></i> Add to Cart
                </button>
                
                <!-- BUY NOW (Directly underneath Add to Cart) -->
                <button id="modalBuyBtn" class="btn-modal-buy" onclick="buyNow()">
                    <i class="fas fa-bolt"></i> Buy Now
                </button>

            </div>
        </div>
    </div>
</div>

<!-- STOCK ALERT MODAL -->
<div class="stock-alert-overlay" id="stockAlertModal">
    <div class="stock-alert-box">
        <div class="stock-alert-icon">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stock-alert-title">Not Enough Stock</div>
        <div class="stock-alert-sub" id="stockAlertMessage">
            Not enough stock available. Only 25 items left.
        </div>
        <button class="stock-alert-btn" onclick="closeStockAlert()">
            <i class="fas fa-check" style="margin-right: 8px;"></i> Got it!
        </button>
    </div>
</div>

<script>
    function scrollCategories(direction) {
        const track = document.getElementById('catScrollTrack');
        const scrollAmount = 200; 
        if(direction === 'left') {
            track.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        } else {
            track.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
    }

    function checkArrows() {
        const track = document.getElementById('catScrollTrack');
        const wrapper = track.parentElement;
        const leftArrow = document.querySelector('.cat-arrow-left');
        const rightArrow = document.querySelector('.cat-arrow-right');
        
        if (track.scrollWidth <= wrapper.clientWidth) {
            leftArrow.style.display = 'none';
            rightArrow.style.display = 'none';
            track.style.justifyContent = 'center';
            wrapper.style.padding = '0 10px'; 
        } else {
            leftArrow.style.display = 'flex';
            rightArrow.style.display = 'flex';
            track.style.justifyContent = 'flex-start';
            wrapper.style.padding = '0 45px'; 
        }
    }

    window.addEventListener('load', checkArrows);
    window.addEventListener('resize', checkArrows);

    let currentModalId = 0;
    let currentQty = 1;
    let currentStock = 0;

    function openModal(id, name, desc, image, price, stock) {
        currentModalId = id;
        currentStock = stock;
        currentQty = 1;
        document.getElementById('qtyDisplay').innerText = 1;
        document.getElementById('modalImg').src = 'uploads/' + image;
        document.getElementById('modalTitle').innerText = name;
        document.getElementById('modalDesc').innerText = desc || 'No description available.';
        document.getElementById('modalPrice').innerText = 'PHP ' + parseFloat(price).toFixed(2);
        
        const stockEl = document.getElementById('modalStock');
        const addBtn = document.getElementById('modalAddBtn');
        const buyBtn = document.getElementById('modalBuyBtn');
        
        if(stock > 0) {
            stockEl.innerHTML = '<span style="color: #2e7d32;">In Stock: ' + stock + ' units</span>';
            addBtn.classList.remove('disabled');
            addBtn.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
            buyBtn.classList.remove('disabled');
            buyBtn.innerHTML = '<i class="fas fa-bolt"></i> Buy Now';
        } else {
            stockEl.innerHTML = '<span style="color: #d32f2f;">✗ Out of Stock</span>';
            addBtn.classList.add('disabled');
            addBtn.innerHTML = 'Out of Stock';
            buyBtn.classList.add('disabled');
            buyBtn.innerHTML = 'Out of Stock';
        }

        document.getElementById('productModal').style.display = 'flex';
    }

    function closeModal() { document.getElementById('productModal').style.display = 'none'; }

    /* --- UPDATE QUANTITY IN MODAL --- */
function updateQty(change) {
    let newQty = currentQty + change;
    if (newQty >= 1 && newQty <= currentStock) {
        currentQty = newQty;
        document.getElementById('qtyDisplay').innerText = newQty;
    } else if (newQty > currentStock) {
        showStockAlert('Not enough stock available. Only ' + currentStock + ' items left.');
    }
}

    /* --- STOCK ALERT MODAL CONTROLS --- */
    function showStockAlert(message) {
        document.getElementById('stockAlertMessage').innerHTML = message;
        document.getElementById('stockAlertModal').style.display = 'flex';
    }

    function closeStockAlert() {
        document.getElementById('stockAlertModal').style.display = 'none';
    }

    // Close modal when clicking outside
    document.getElementById('stockAlertModal').addEventListener('click', function(e) {
        if (e.target === this) closeStockAlert();
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeStockAlert();
        }
    });

    /* --- QUICK ADD FROM CARD (WITH STOCK CHECK) --- */
function quickAdd(id) {
    // Check if user is logged in
    fetch('check_login.php')
    .then(response => response.json())
    .then(data => {
        if(!data.logged_in) {
            setTimeout(openLoginModal, 300);
            return;
        }
        
        // Get product stock
        fetch('get_product_stock.php?product_id=' + id)
        .then(response => response.json())
        .then(stockData => {
            let availableStock = stockData.stock || 0;
            
            if (availableStock <= 0) {
                showStockAlert('This product is currently out of stock.');
                return;
            }
            
            // Check current cart quantity for this product
            fetch('check_cart_quantity.php?product_id=' + id)
            .then(response => response.json())
            .then(cartData => {
                let currentCartQty = cartData.quantity || 0;
                
                // Check if user already has maximum stock in cart
                if (currentCartQty >= availableStock) {
                    showStockAlert('You\'ve reached the maximum available stock (' + availableStock + ' items) for this product.');
                    return;
                }
                
                // Proceed with adding to cart
                fetch('add_to_cart_modal.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'product_id=' + id + '&quantity=1'
                })
                .then(response => response.text())
                .then(data => {
                    if(data.trim() === 'login_required') {
                        setTimeout(openLoginModal, 300);
                    } else if(data.trim() === 'stock_limit_reached') {
                        showStockAlert('You\'ve reached the maximum available stock for this product.');
                    } else {
                        showToast();
                    }
                });
            });
        });
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

    /* --- ADD TO CART FROM MODAL (WITH STOCK CHECK) --- */
function addFromModal() {
    if(currentModalId === 0 || currentStock <= 0) return;
    
    // Check if user is logged in
    fetch('check_login.php')
    .then(response => response.json())
    .then(data => {
        if(!data.logged_in) {
            closeModal();
            setTimeout(openLoginModal, 300);
            return;
        }
        
        // Check current cart quantity for this product
        fetch('check_cart_quantity.php?product_id=' + currentModalId)
        .then(response => response.json())
        .then(cartData => {
            let currentCartQty = cartData.quantity || 0;
            
            // Check if user already has maximum stock in cart
            if (currentCartQty >= currentStock) {
                showStockAlert('You\'ve reached the maximum available stock (' + currentStock + ' items) for this product.');
                return;
            }
            
            let totalAfterAdd = currentCartQty + currentQty;
            
            if (totalAfterAdd > currentStock) {
                let maxCanAdd = currentStock - currentCartQty;
                if (maxCanAdd <= 0) {
                    showStockAlert('You\'ve reached the maximum available stock (' + currentStock + ' items) for this product.');
                } else {
                    showStockAlert('You can only add ' + maxCanAdd + ' more item(s). Only ' + currentStock + ' available in stock.');
                }
                return;
            }
            
            // Proceed with adding to cart
            fetch('add_to_cart_modal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + currentModalId + '&quantity=' + currentQty
            })
            .then(response => response.text())
            .then(data => {
                if(data.trim() === 'login_required') {
                    closeModal();
                    setTimeout(openLoginModal, 300);
                } else if(data.trim() === 'stock_limit_reached') {
                    showStockAlert('You\'ve reached the maximum available stock for this product.');
                } else {
                    closeModal(); 
                    showToast();
                }
            });
        });
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

    /* --- BUY NOW LOGIC (WITH STOCK CHECK) --- */
function buyNow() {
    if(currentModalId === 0 || currentStock <= 0) return;
    
    // Check if user is logged in
    fetch('check_login.php')
    .then(response => response.json())
    .then(data => {
        if(!data.logged_in) {
            closeModal();
            setTimeout(openLoginModal, 300);
            return;
        }
        
        // Check current cart quantity for this product
        fetch('check_cart_quantity.php?product_id=' + currentModalId)
        .then(response => response.json())
        .then(cartData => {
            let currentCartQty = cartData.quantity || 0;
            
            // Check if user already has maximum stock in cart
            if (currentCartQty >= currentStock) {
                showStockAlert('You\'ve reached the maximum available stock (' + currentStock + ' items) for this product.');
                return;
            }
            
            let totalAfterAdd = currentCartQty + currentQty;
            
            if (totalAfterAdd > currentStock) {
                let maxCanAdd = currentStock - currentCartQty;
                if (maxCanAdd <= 0) {
                    showStockAlert('You\'ve reached the maximum available stock (' + currentStock + ' items) for this product.');
                } else {
                    showStockAlert('You can only add ' + maxCanAdd + ' more item(s). Only ' + currentStock + ' available in stock.');
                }
                return;
            }
            
            // Proceed with buy now
            fetch('add_to_cart_modal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + currentModalId + '&quantity=' + currentQty + '&replace=1'
            })
            .then(response => response.text())
            .then(data => {
                if(data.trim() === 'login_required') {
                    closeModal();
                    setTimeout(openLoginModal, 300);
                } else if(data.trim() === 'stock_limit_reached') {
                    showStockAlert('You\'ve reached the maximum available stock for this product.');
                } else {
                    fetch('get_cart_id.php?product_id=' + currentModalId)
                    .then(res => res.text())
                    .then(cartId => {
                        closeModal();
                        window.location.href = 'checkout_selected.php?items=' + cartId;
                    });
                }
            });
        });
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

    /* --- TOAST NOTIFICATION --- */
    function showToast() {
        var toast = document.getElementById('toastAlert');
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 2500);
    }

    /* --- WISHLIST TOGGLE --- */
function toggleWishlist(element, productId) {
    // Prevent multiple rapid clicks
    if (element.classList.contains('loading')) return;
    element.classList.add('loading');
    
    // Check if user is logged in
    fetch('check_login.php')
    .then(response => response.json())
    .then(loginData => {
        if (!loginData.logged_in) {
            element.classList.remove('loading');
            alert('Please login to add items to your wishlist.');
            return;
        }
        
        // Toggle wishlist
        fetch('toggle_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'product_id=' + productId
        })
        .then(response => response.json())
        .then(data => {
            element.classList.remove('loading');
            
            if (data.success) {
                if (data.action === 'added') {
                    element.classList.add('active');
                    showWishlistToast('Added to wishlist ❤️');
                } else {
                    element.classList.remove('active');
                    showWishlistToast('Removed from wishlist 💔');
                }
            } else {
                alert(data.message || 'Something went wrong');
            }
        })
        .catch(error => {
            element.classList.remove('loading');
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    })
    .catch(error => {
        element.classList.remove('loading');
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

/* --- WISHLIST TOAST NOTIFICATION --- */
function showWishlistToast(message) {
    var toast = document.getElementById('wishlistToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'wishlistToast';
        toast.style.cssText = `
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(100px);
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(12px);
            color: #333; padding: 16px 30px; border-radius: 16px;
            border: 1px solid rgba(255, 139, 167, 0.2);
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            z-index: 999999; opacity: 0; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-family: 'Poppins', sans-serif; font-size: 15px; font-weight: 500;
            display: flex; align-items: center; gap: 10px;
            white-space: nowrap;
        `;
        document.body.appendChild(toast);
    }
    
    toast.innerHTML = message;
    toast.style.opacity = '1';
    toast.style.transform = 'translateX(-50%) translateY(0px)';
    
    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-50%) translateY(20px)';
    }, 2500);
}
</script>

<?php include 'footer.php'; ?>