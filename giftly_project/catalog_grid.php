<?php
/**
 * Shared storefront grid — used by occasion-boxes.php and baskets.php
 * (and safe to reuse elsewhere). Renders a search box, a product grid for a
 * given product_type, a quick-view modal, pagination and the add-to-cart /
 * wishlist behaviour. Mirrors the look of shop.php.
 *
 * Expects, before include:
 *   $conn                 active DB connection
 *   $cat_type             'occasion_box' | 'basket' | 'catalog'
 *   $cat_title            heading text
 *   $cat_subtitle         (optional) sub-heading
 *   $cat_empty_msg        (optional) empty-state text
 *   $cat_limit            (optional) per-page, default 12
 */

include_once 'reviews_lib.php';
include_once 'catalog_lib.php';
reviews_ensure_schema($conn);
catalog_ensure_schema($conn);

$cat_type      = isset($cat_type) ? $cat_type : 'catalog';
$cat_title     = isset($cat_title) ? $cat_title : 'Products';
$cat_subtitle  = isset($cat_subtitle) ? $cat_subtitle : '';
$cat_empty_msg = isset($cat_empty_msg) ? $cat_empty_msg : 'Nothing here yet — check back soon!';
$cat_limit     = isset($cat_limit) ? (int) $cat_limit : 12;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page   = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $cat_limit;

$type_esc = $conn->real_escape_string($cat_type);
$where = "product_type = '$type_esc'" . catalog_visible_filter();
if ($search !== '') {
    $where .= " AND name ILIKE '%" . $conn->real_escape_string($search) . "%'";
}

$total_res   = $conn->query("SELECT COUNT(*) AS c FROM products WHERE $where");
$total_rows  = $total_res ? (int) $total_res->fetch_assoc()['c'] : 0;
$total_pages = max(1, (int) ceil($total_rows / $cat_limit));

$prod_res = $conn->query("SELECT * FROM products WHERE $where
                          ORDER BY CASE WHEN quantity > 0 THEN 0 ELSE 1 END, id ASC
                          LIMIT $cat_limit OFFSET $offset");

// wishlist state
$wishlist_ids = [];
if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $wq = $conn->query("SELECT product_id FROM wishlist WHERE user_id = $uid");
    while ($wq && $w = $wq->fetch_assoc()) {
        $wishlist_ids[] = $w['product_id'];
    }
}
?>

<style>
    .cat-hero { text-align: center; margin-bottom: 30px; }
    .cat-hero h2 { font-size: 26px; font-weight: 700; color: #222; margin-bottom: 6px; }
    .cat-hero p { font-size: 14px; color: #888; }

    .cat-search { text-align: center; margin-bottom: 34px; }
    .cat-search input { padding: 11px 20px; width: 320px; max-width: 80vw; border-radius: 30px; border: 1.5px solid #eee; outline: none; background: #fff; font-family: 'Poppins'; }
    .cat-search input:focus { border-color: #ffc1cc; }
    .cat-search button { padding: 11px 24px; border-radius: 30px; border: none; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; cursor: pointer; margin-left: 8px; font-family: 'Poppins'; box-shadow: 0 4px 12px rgba(254,165,182,0.2); transition: 0.2s; }
    .cat-search button:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254,165,182,0.4); }

    .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 25px; padding: 10px 0 40px; }
    .cat-card { background: #fff; border-radius: 24px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); transition: all 0.3s cubic-bezier(0.175,0.885,0.32,1.275); border: 1px solid rgba(255,255,255,0.8); cursor: pointer; display: flex; flex-direction: column; height: 100%; position: relative; }
    .cat-card:hover { transform: translateY(-6px); box-shadow: 0 15px 40px rgba(255,139,167,0.12); }
    .cat-card.oos { opacity: 0.9; cursor: default; }
    .cat-card.oos:hover { transform: none; box-shadow: 0 4px 15px rgba(0,0,0,0.04); }

    .ci-img-box { background: #f8f8fa; border-radius: 20px; padding: 25px 15px; margin-bottom: 15px; display: flex; justify-content: center; align-items: center; height: 200px; position: relative; }
    .ci-img-box img { max-width: 100%; max-height: 100%; object-fit: contain; pointer-events: none; transition: transform 0.4s ease; }
    .cat-card:hover .ci-img-box img { transform: scale(1.05); }
    .cat-card.oos .ci-img-box img { opacity: 0.4; filter: grayscale(0.8); }

    .ci-badge { position: absolute; top: 12px; left: 12px; background: #fff; color: #333; padding: 4px 14px; border-radius: 50px; font-size: 12px; font-weight: 600; box-shadow: 0 2px 10px rgba(0,0,0,0.04); pointer-events: none; }
    .ci-ribbon { position: absolute; top: 12px; right: -30px; background: #d32f2f; color: #fff; padding: 4px 30px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; transform: rotate(45deg); box-shadow: 0 2px 8px rgba(211,47,47,0.3); z-index: 6; pointer-events: none; white-space: nowrap; }

    .ci-heart { position: absolute; top: 12px; right: 12px; background: #fff; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: none; cursor: pointer; transition: all 0.3s cubic-bezier(0.175,0.885,0.32,1.275); color: #bbb; font-size: 17px; z-index: 5; }
    .ci-heart:hover { background: #fff0f5; transform: scale(1.15); }
    .ci-heart.active { color: #ff8ba7; background: #fff0f5; }
    .ci-heart .fas { display: none; }
    .ci-heart .far { display: block; }
    .ci-heart.active .fas { display: block; }
    .ci-heart.active .far { display: none; }
    .ci-heart.loading { opacity: 0.5; pointer-events: none; }

    .ci-name { font-size: 17px; font-weight: 600; color: #222; line-height: 1.4; margin-bottom: 6px; text-align: left; }
    .ci-desc { font-size: 13px; color: #999; line-height: 1.5; margin-bottom: 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .ci-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 10px; border-top: 1px solid #f5f5f5; }
    .ci-price { font-size: 16px; font-weight: 500; color: #888; }
    .ci-price span { font-weight: 700; color: #222; }

    .ci-add { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border: none; border-radius: 50px; padding: 8px 22px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(254,165,182,0.2); }
    .ci-add:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 8px 20px rgba(254,165,182,0.35); }
    .ci-add.disabled { background: #e8e8e8 !important; color: #999 !important; cursor: not-allowed !important; box-shadow: none !important; transform: none !important; }

    .cat-empty { text-align: center; padding: 70px 20px; color: #999; grid-column: 1 / -1; }
    .cat-empty i { font-size: 52px; color: #ddd; display: block; margin-bottom: 16px; }

    .cat-pagination { display: flex; justify-content: center; gap: 8px; margin-top: 10px; margin-bottom: 40px; flex-wrap: wrap; }
    .cat-page-btn { padding: 8px 16px; border: 1.5px solid #eee; border-radius: 30px; background: #fff; color: #555; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.2s; font-family: 'Poppins'; }
    .cat-page-btn:hover { background: #ffc1cc; color: #fff; border-color: #ffc1cc; }
    .cat-page-btn.active { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; border-color: #FEA5B6; box-shadow: 0 4px 12px rgba(254,165,182,0.3); }
    .cat-page-btn.disabled { opacity: 0.5; pointer-events: none; }

    /* toast */
    .toast-alert { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.9); background: rgba(220,220,220,0.35); backdrop-filter: blur(12px); color: #333; padding: 30px 45px; border-radius: 24px; border: 1px solid rgba(255,255,255,0.3); box-shadow: 0 10px 30px rgba(0,0,0,0.05); z-index: 999999; display: none; opacity: 0; transition: all 0.3s cubic-bezier(0.175,0.885,0.32,1.275); text-align: center; pointer-events: none; min-width: 180px; }
    .toast-alert.show { display: block; opacity: 1; transform: translate(-50%, -50%) scale(1); }
    .toast-check { display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; width: 50px; height: 50px; background: linear-gradient(135deg, #81c784 0%, #66bb6a 100%); border-radius: 50%; color: #fff; font-size: 22px; box-shadow: 0 4px 12px rgba(102,187,106,0.35); }
    .toast-text { font-size: 16px; font-weight: 500; letter-spacing: 0.5px; color: #444; }

    /* quick-view modal */
    .cat-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(8px); display: none; justify-content: center; align-items: center; z-index: 99998; padding: 20px; }
    .cat-modal-box { background: #fff; border-radius: 30px; max-width: 850px; width: 100%; box-shadow: 0 25px 60px rgba(0,0,0,0.2); position: relative; overflow: hidden; display: flex; flex-wrap: wrap; animation: catModalIn 0.3s ease-out; }
    @keyframes catModalIn { from { transform: scale(0.95) translateY(10px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
    .cat-modal-close { position: absolute; top: 15px; right: 20px; font-size: 24px; color: #888; cursor: pointer; transition: 0.2s; z-index: 2; }
    .cat-modal-close:hover { color: #ff8ba7; transform: rotate(90deg); }
    .cat-modal-left { flex: 0.9; min-width: 300px; background: #fafafa; padding: 40px; display: flex; justify-content: center; align-items: center; }
    .cat-modal-left img { width: 100%; max-height: 300px; object-fit: contain; border-radius: 16px; }
    .cat-modal-right { flex: 1.1; min-width: 300px; padding: 45px 40px 35px; display: flex; flex-direction: column; }
    .cat-modal-right h3 { font-size: 26px; font-weight: 700; color: #222; margin-bottom: 5px; }
    .cat-modal-price { font-size: 23px; font-weight: 700; color: #111; margin-bottom: 15px; }
    .cat-modal-desc { font-size: 15px; color: #666; line-height: 1.6; margin-bottom: 20px; word-wrap: break-word; }
    #catModalStock { font-size: 14px; font-weight: 500; margin-bottom: 15px; }
    .cat-modal-actions { display: flex; flex-direction: column; align-items: flex-start; gap: 12px; margin-top: auto; width: 100%; }
    .cat-qty { display: flex; align-items: center; border: 1px solid #eee; border-radius: 50px; padding: 4px 12px; background: #fff; width: fit-content; }
    .cat-qty button { background: transparent; border: none; font-size: 20px; cursor: pointer; padding: 0 8px; color: #555; font-family: 'Poppins'; }
    .cat-qty button:hover { color: #ff8ba7; }
    .cat-qty span { font-size: 18px; font-weight: 600; min-width: 30px; text-align: center; color: #222; }
    .btn-cm-add { width: 100%; padding: 12px 0; border: none; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(254,165,182,0.2); }
    .btn-cm-add:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254,165,182,0.4); }
    .btn-cm-buy { width: 100%; padding: 12px 0; border: none; border-radius: 50px; background: #eaeaea; color: #444; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; }
    .btn-cm-buy:hover { background: #d6d6d6; transform: translateY(-2px); }
    .btn-cm-add.disabled, .btn-cm-buy.disabled { background: #ccc !important; color: #888 !important; cursor: not-allowed !important; transform: none !important; box-shadow: none !important; }

    /* stock alert */
    .cat-stock-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(6px); display: none; justify-content: center; align-items: center; z-index: 999999; padding: 20px; }
    .cat-stock-box { background: #fff; border-radius: 30px; padding: 40px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.15); animation: catModalIn 0.3s ease; }
    .cat-stock-box .ico { font-size: 50px; color: #f9a825; margin-bottom: 15px; }
    .cat-stock-box .ico i { background: #fff8e1; padding: 15px; border-radius: 50%; }
    .cat-stock-box h4 { font-size: 22px; font-weight: 700; color: #222; margin-bottom: 5px; }
    .cat-stock-box p { font-size: 14px; color: #888; margin-bottom: 25px; line-height: 1.5; }
    .cat-stock-box button { padding: 12px 40px; border: none; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; font-weight: 600; font-size: 15px; cursor: pointer; font-family: 'Poppins'; box-shadow: 0 4px 12px rgba(254,165,182,0.2); }

    @media (max-width: 600px) {
        .cat-modal-left { padding: 25px; }
        .cat-modal-right { padding: 30px 25px; }
    }

    .rv-stars { color: #ffb400; letter-spacing: 1px; }
    .ci-rating { font-size: 12px; margin-bottom: 8px; color: #999; }
    .ci-rating .rv-stars { font-size: 12px; }
    /* fixed height whether or not the product has reviews — the right column scrolls */
    .cat-modal-box { height: min(88vh, 600px); }
    .cat-modal-left { align-self: stretch; }
    .cat-modal-right { height: 100%; overflow-y: auto; }
    #modalReviews { width: 100%; margin-top: 4px; }
    #modalReviews .rv-wrap { margin-top: 14px; padding-top: 14px; }
    #modalReviews .rv-list-scroll { max-height: none; overflow: visible; }
    @media (max-width: 640px) {
        .cat-modal-box { height: auto; max-height: 90vh; overflow-y: auto; }
        .cat-modal-left { align-self: auto; }
        .cat-modal-right { height: auto; overflow: visible; }
    }
</style>

<div id="catToast" class="toast-alert">
    <div class="toast-check"><i class="fas fa-check"></i></div>
    <div class="toast-text">Added to cart</div>
</div>

<div class="container" style="padding-top: 130px;">
    <div class="cat-hero">
        <h2><?php echo htmlspecialchars($cat_title); ?></h2>
        <?php if ($cat_subtitle !== ''): ?><p><?php echo htmlspecialchars($cat_subtitle); ?></p><?php endif; ?>
    </div>

    <div class="cat-search">
        <form method="GET" action="">
            <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="cat-grid">
        <?php if ($prod_res && $prod_res->num_rows > 0): ?>
            <?php while ($row = $prod_res->fetch_assoc()):
                $id      = (int) $row['id'];
                $inStock = (int) $row['quantity'] > 0;
                $isWish  = in_array($row['id'], $wishlist_ids);
                $sold    = 0;
                $sres = $conn->query("SELECT SUM(quantity) AS t FROM order_items WHERE product_id = $id");
                if ($sres && $sres->num_rows) $sold = (int) $sres->fetch_assoc()['t'];
                $rvs = reviews_summary($conn, $id);
                $onClick = $inStock
                    ? htmlspecialchars(
                        "catOpen($id, " . json_encode($row['name']) . ", " . json_encode($row['description']) . ", " . json_encode(img_url($row['image'])) . ", " . (float) $row['price'] . ", " . (int) $row['quantity'] . ")",
                        ENT_QUOTES)
                    : "";
            ?>
            <div class="cat-card <?php echo $inStock ? '' : 'oos'; ?>">
                <div class="ci-img-box" onclick="<?php echo $onClick; ?>">
                    <img src="<?php echo htmlspecialchars(img_url($row['image'])); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                    <button class="ci-heart <?php echo $isWish ? 'active' : ''; ?>" data-product-id="<?php echo $id; ?>"
                            onclick="event.stopPropagation(); catWishlist(this, <?php echo $id; ?>)">
                        <i class="fas fa-heart"></i><i class="far fa-heart"></i>
                    </button>
                    <?php if (!$inStock): ?><div class="ci-ribbon">Sold Out</div><?php endif; ?>
                    <?php if ($sold > 10): ?><div class="ci-badge">Popular</div><?php endif; ?>
                </div>
                <div class="ci-name" onclick="<?php echo $onClick; ?>"><?php echo htmlspecialchars($row['name']); ?></div>
                <?php if ($rvs['count'] > 0): ?>
                    <div class="ci-rating" onclick="<?php echo $onClick; ?>"><?php echo reviews_stars($rvs['avg']); ?> <span>(<?php echo $rvs['count']; ?>)</span></div>
                <?php endif; ?>
                <?php if (trim($row['description']) !== ''): ?>
                    <div class="ci-desc"><?php echo htmlspecialchars($row['description']); ?></div>
                <?php endif; ?>
                <div class="ci-bottom">
                    <div class="ci-price" onclick="<?php echo $onClick; ?>">PHP <span><?php echo number_format($row['price'], 2); ?></span></div>
                    <?php if ($inStock): ?>
                        <button class="ci-add" onclick="event.stopPropagation(); catQuickAdd(<?php echo $id; ?>)">
                            <i class="fas fa-shopping-cart"></i> Add
                        </button>
                    <?php else: ?>
                        <button class="ci-add disabled" onclick="event.stopPropagation();">
                            <i class="fas fa-times-circle"></i> Unavailable
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="cat-empty">
                <i class="fas fa-box-open"></i>
                <p style="font-size:16px;"><?php echo htmlspecialchars($cat_empty_msg); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="cat-pagination">
        <a href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search); ?>" class="cat-page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">&larr; Prev</a>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="cat-page-btn <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <a href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search); ?>" class="cat-page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next &rarr;</a>
    </div>
    <?php endif; ?>
</div>

<!-- QUICK VIEW MODAL -->
<div class="cat-modal-overlay" id="catModal">
    <div class="cat-modal-box">
        <span class="cat-modal-close" onclick="catClose()">&times;</span>
        <div class="cat-modal-left"><img id="catModalImg" src="" alt=""></div>
        <div class="cat-modal-right">
            <h3 id="catModalTitle"></h3>
            <div class="cat-modal-price" id="catModalPrice"></div>
            <div class="cat-modal-desc" id="catModalDesc"></div>
            <div id="catModalStock"></div>
            <div class="cat-modal-actions">
                <div class="cat-qty">
                    <button onclick="catQty(-1)">&minus;</button>
                    <span id="catQtyDisplay">1</span>
                    <button onclick="catQty(1)">+</button>
                </div>
                <button id="catModalAdd" class="btn-cm-add" onclick="catAddFromModal()"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
                <button id="catModalBuy" class="btn-cm-buy" onclick="catBuyNow()"><i class="fas fa-bolt"></i> Buy Now</button>
            </div>
            <div id="modalReviews"></div>
        </div>
    </div>
</div>

<script src="reviews_widget.js"></script>

<!-- STOCK ALERT -->
<div class="cat-stock-overlay" id="catStockModal">
    <div class="cat-stock-box">
        <div class="ico"><i class="fas fa-exclamation-circle"></i></div>
        <h4>Not enough stock</h4>
        <p id="catStockMsg"></p>
        <button onclick="catCloseStock()"><i class="fas fa-check" style="margin-right:8px;"></i> Got it!</button>
    </div>
</div>

<script>
let catId = 0, catQtyVal = 1, catStock = 0;

function catOpen(id, name, desc, image, price, stock) {
    catId = id; catStock = stock; catQtyVal = 1;
    document.getElementById('catQtyDisplay').innerText = 1;
    document.getElementById('catModalImg').src = image; // already resolved by img_url() in PHP
    document.getElementById('catModalTitle').innerText = name;
    document.getElementById('catModalDesc').innerText = desc || 'No description available.';
    document.getElementById('catModalPrice').innerText = 'PHP ' + parseFloat(price).toFixed(2);
    const stockEl = document.getElementById('catModalStock');
    const addBtn = document.getElementById('catModalAdd');
    const buyBtn = document.getElementById('catModalBuy');
    if (stock > 0) {
        stockEl.innerHTML = '<span style="color:#2e7d32;">In stock: ' + stock + ' available</span>';
        addBtn.classList.remove('disabled'); buyBtn.classList.remove('disabled');
    } else {
        stockEl.innerHTML = '<span style="color:#d32f2f;">Out of stock</span>';
        addBtn.classList.add('disabled'); buyBtn.classList.add('disabled');
    }
    document.getElementById('catModal').style.display = 'flex';
    if (window.loadProductReviews) loadProductReviews(id);
}
function catClose() { document.getElementById('catModal').style.display = 'none'; }
document.getElementById('catModal').addEventListener('click', function (e) { if (e.target === this) catClose(); });

function catQty(delta) {
    let n = catQtyVal + delta;
    if (n >= 1 && n <= catStock) { catQtyVal = n; document.getElementById('catQtyDisplay').innerText = n; }
    else if (n > catStock) catShowStock('Only ' + catStock + ' available in stock.');
}

function catShowStock(msg) { document.getElementById('catStockMsg').innerHTML = msg; document.getElementById('catStockModal').style.display = 'flex'; }
function catCloseStock() { document.getElementById('catStockModal').style.display = 'none'; }
document.getElementById('catStockModal').addEventListener('click', function (e) { if (e.target === this) catCloseStock(); });
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { catClose(); catCloseStock(); } });

function catRequireLogin(cb) {
    fetch('check_login.php').then(r => r.json()).then(d => {
        if (!d.logged_in) { if (window.openLoginModal) setTimeout(openLoginModal, 200); return; }
        cb();
    }).catch(() => {});
}

function catQuickAdd(id) {
    catRequireLogin(() => {
        fetch('get_product_stock.php?product_id=' + id).then(r => r.json()).then(s => {
            const avail = s.stock || 0;
            if (avail <= 0) { catShowStock('This item is currently out of stock.'); return; }
            fetch('check_cart_quantity.php?product_id=' + id).then(r => r.json()).then(c => {
                if ((c.quantity || 0) >= avail) { catShowStock('You already have the maximum available (' + avail + ') in your cart.'); return; }
                fetch('add_to_cart_modal.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'product_id=' + id + '&quantity=1' })
                    .then(r => r.text()).then(t => {
                        if (t.trim() === 'login_required') { if (window.openLoginModal) openLoginModal(); }
                        else if (t.trim() === 'stock_limit_reached') catShowStock('You have reached the maximum available stock for this item.');
                        else catToast();
                    });
            });
        });
    });
}

function catAddFromModal() {
    if (catId === 0 || catStock <= 0) return;
    catRequireLogin(() => {
        fetch('check_cart_quantity.php?product_id=' + catId).then(r => r.json()).then(c => {
            const cur = c.quantity || 0;
            if (cur + catQtyVal > catStock) {
                const canAdd = catStock - cur;
                catShowStock(canAdd <= 0 ? 'You already have the maximum available (' + catStock + ') in your cart.' : 'You can only add ' + canAdd + ' more. Only ' + catStock + ' in stock.');
                return;
            }
            fetch('add_to_cart_modal.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'product_id=' + catId + '&quantity=' + catQtyVal })
                .then(r => r.text()).then(t => {
                    if (t.trim() === 'login_required') { catClose(); if (window.openLoginModal) openLoginModal(); }
                    else if (t.trim() === 'stock_limit_reached') catShowStock('You have reached the maximum available stock for this item.');
                    else { catClose(); catToast(); }
                });
        });
    });
}

function catBuyNow() {
    if (catId === 0 || catStock <= 0) return;
    catRequireLogin(() => {
        fetch('add_to_cart_modal.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'product_id=' + catId + '&quantity=' + catQtyVal + '&replace=1' })
            .then(r => r.text()).then(t => {
                if (t.trim() === 'login_required') { catClose(); if (window.openLoginModal) openLoginModal(); return; }
                if (t.trim() === 'stock_limit_reached') { catShowStock('You have reached the maximum available stock for this item.'); return; }
                fetch('get_cart_id.php?product_id=' + catId).then(r => r.text()).then(cid => {
                    catClose();
                    window.location.href = 'checkout_selected.php?items=' + cid;
                });
            });
    });
}

function catToast() {
    const t = document.getElementById('catToast');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2200);
    if (window.updateCartBadge) window.updateCartBadge();
}

function catWishlist(el, productId) {
    if (el.classList.contains('loading')) return;
    el.classList.add('loading');
    fetch('check_login.php').then(r => r.json()).then(d => {
        if (!d.logged_in) { el.classList.remove('loading'); if (window.openLoginModal) openLoginModal(); return; }
        fetch('toggle_wishlist.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'product_id=' + productId })
            .then(r => r.json()).then(res => {
                el.classList.remove('loading');
                if (res.success) {
                    el.classList.toggle('active', res.action === 'added');
                    catToastMsg(res.action === 'added' ? 'Added to wishlist ❤️' : 'Removed from wishlist 💔');
                }
            }).catch(() => el.classList.remove('loading'));
    }).catch(() => el.classList.remove('loading'));
}

function catToastMsg(msg) {
    let t = document.getElementById('catWishToast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'catWishToast';
        t.style.cssText = 'position:fixed;bottom:30px;left:50%;transform:translateX(-50%) translateY(100px);background:rgba(255,255,255,0.96);backdrop-filter:blur(12px);color:#333;padding:14px 28px;border-radius:16px;border:1px solid rgba(255,139,167,0.2);box-shadow:0 10px 40px rgba(0,0,0,0.08);z-index:999999;opacity:0;transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);font-family:Poppins,sans-serif;font-size:14px;font-weight:500;white-space:nowrap;';
        document.body.appendChild(t);
    }
    t.innerHTML = msg;
    t.style.opacity = '1';
    t.style.transform = 'translateX(-50%) translateY(0)';
    clearTimeout(t._t);
    t._t = setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(20px)'; }, 2200);
}
</script>
