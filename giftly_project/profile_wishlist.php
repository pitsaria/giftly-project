<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get wishlist items with product details
$wishlist_query = $conn->query("
    SELECT w.id as wishlist_id, w.created_at, p.* 
    FROM wishlist w 
    JOIN products p ON w.product_id = p.id 
    WHERE w.user_id = $user_id 
    ORDER BY w.created_at DESC
");

// Separate items by stock availability
$in_stock_items = [];
$out_of_stock_items = [];
while($item = $wishlist_query->fetch_assoc()) {
    if ($item['quantity'] > 0) {
        $in_stock_items[] = $item;
    } else {
        $out_of_stock_items[] = $item;
    }
}

$total_items = count($in_stock_items) + count($out_of_stock_items);
?>

<style>
    .wishlist-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .wishlist-item {
        background: #ffffff;
        border-radius: 16px;
        padding: 15px;
        border: 1px solid #f0f0f0;
        transition: all 0.3s ease;
        position: relative;
    }
    .wishlist-item:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(255, 139, 167, 0.1);
        border-color: #ffc1cc;
    }
    .wishlist-item-img {
        width: 100%;
        height: 150px;
        object-fit: contain;
        background: #fafafa;
        border-radius: 12px;
        padding: 10px;
        margin-bottom: 12px;
    }
    .wishlist-item-name {
        font-size: 14px;
        font-weight: 600;
        color: #222;
        margin-bottom: 5px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 40px;
    }
    .wishlist-item-price {
        font-size: 16px;
        font-weight: 700;
        color: #333;
        margin-bottom: 12px;
    }
    .wishlist-item-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .wishlist-item-actions button {
        flex: 1;
        padding: 8px 12px;
        border: none;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        font-family: 'Poppins', sans-serif;
        min-width: 60px;
    }
    .btn-add-cart {
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white;
    }
    .btn-add-cart:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.3);
    }
    .btn-add-cart:disabled {
        background: #ccc !important;
        cursor: not-allowed !important;
        transform: none !important;
        box-shadow: none !important;
    }
    .btn-remove-wishlist {
        background: #f5f5f5;
        color: #888;
        flex: 0.4 !important;
        min-width: 40px !important;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .btn-remove-wishlist:hover {
        background: #ffebee;
        color: #d32f2f;
    }
    .wishlist-empty {
        text-align: center;
        padding: 60px 20px;
        color: #888;
    }
    .wishlist-empty i {
        font-size: 60px;
        color: #ddd;
        margin-bottom: 20px;
    }
    .wishlist-item .stock-badge {
        font-size: 11px;
        padding: 2px 12px;
        border-radius: 50px;
        display: inline-block;
        margin-bottom: 8px;
    }
    .stock-badge.in-stock {
        background: #e8f5e9;
        color: #2e7d32;
    }
    .stock-badge.out-of-stock {
        background: #ffebee;
        color: #d32f2f;
    }

    /* --- OUT OF STOCK SECTION (Same as cart) --- */
    .out-of-stock-section {
        margin-top: 30px;
        border-top: 2px dashed #e0e0e0;
        padding-top: 20px;
    }
    .out-of-stock-title {
        font-size: 16px;
        font-weight: 600;
        color: #999;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .out-of-stock-title i {
        color: #d32f2f;
    }

    .wishlist-item.out-of-stock {
        opacity: 0.6;
        border-color: #e8e8e8;
        pointer-events: none;
        cursor: not-allowed;
    }
    .wishlist-item.out-of-stock .wishlist-item-img {
        filter: grayscale(0.6);
    }
    .wishlist-item.out-of-stock .btn-remove-wishlist {
        pointer-events: auto;
        cursor: pointer;
    }
    .wishlist-item.out-of-stock .btn-add-cart {
        background: #e8e8e8 !important;
        color: #999 !important;
        cursor: not-allowed !important;
        pointer-events: none !important;
    }
    .wishlist-item.out-of-stock .stock-badge.out-of-stock {
        background: #ffebee;
        color: #d32f2f;
    }
    .wishlist-item .out-of-stock-ribbon {
        display: none;
    }
    .wishlist-item.out-of-stock .out-of-stock-ribbon {
        display: inline-block;
        background: #d32f2f;
        color: white;
        font-size: 10px;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 50px;
        margin-left: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* --- DELETE CONFIRMATION MODAL --- */
    .delete-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(6px);
        display: none; justify-content: center; align-items: center;
        z-index: 999999; padding: 20px;
    }
    .delete-modal-box {
        background: #ffffff; border-radius: 30px; padding: 40px;
        max-width: 400px; width: 90%; text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        animation: fadeUp 0.3s ease;
    }
    @keyframes fadeUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .delete-modal-icon { 
        font-size: 50px; 
        color: #d32f2f; 
        margin-bottom: 15px;
    }
    .delete-modal-icon i {
        background: #fdeded;
        padding: 15px;
        border-radius: 50%;
    }
    .delete-modal-title { 
        font-size: 22px; 
        font-weight: 700; 
        color: #222; 
        margin-bottom: 5px; 
    }
    .delete-modal-sub { 
        font-size: 14px; 
        color: #888; 
        margin-bottom: 25px; 
        line-height: 1.5; 
    }
    .delete-modal-buttons { 
        display: flex; 
        gap: 15px; 
        justify-content: center; 
    }
    .btn-cancel-delete {
        flex: 1; 
        padding: 14px; 
        border: none; 
        border-radius: 50px;
        background: #eaeaea; 
        color: #555; 
        font-weight: 600; 
        font-size: 15px; 
        cursor: pointer; 
        transition: 0.2s; 
        font-family: 'Poppins';
    }
    .btn-cancel-delete:hover { 
        background: #d6d6d6; 
    }
    .btn-confirm-delete {
        flex: 1; 
        padding: 14px; 
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
    .btn-confirm-delete:hover { 
        background: linear-gradient(135deg, #ff8ba7 0%, #FEA5B6 100%); 
        transform: translateY(-2px); 
    }

    /* --- ALREADY IN CART MODAL --- */
    .cart-alert-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(6px);
        display: none; justify-content: center; align-items: center;
        z-index: 999999; padding: 20px;
    }
    .cart-alert-box {
        background: #ffffff; border-radius: 30px; padding: 40px;
        max-width: 400px; width: 90%; text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        animation: fadeUp 0.3s ease;
    }
    .cart-alert-icon {
        font-size: 50px;
        margin-bottom: 15px;
    }
    .cart-alert-icon i {
        display: inline-block;
    }
    .cart-alert-title {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 5px;
    }
    .cart-alert-sub {
        font-size: 14px;
        color: #888;
        margin-bottom: 25px;
        line-height: 1.5;
    }
    .cart-alert-btn {
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
    .cart-alert-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4);
    }

    /* --- TOAST NOTIFICATION --- */
    .wishlist-toast {
        position: fixed; 
        bottom: 30px; 
        left: 50%; 
        transform: translateX(-50%) translateY(100px);
        background: rgba(255, 255, 255, 0.95); 
        backdrop-filter: blur(12px);
        color: #333; 
        padding: 16px 30px; 
        border-radius: 16px;
        border: 1px solid rgba(255, 139, 167, 0.2);
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        z-index: 999999; 
        opacity: 0; 
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        font-family: 'Poppins', sans-serif; 
        font-size: 15px; 
        font-weight: 500;
        display: flex; 
        align-items: center; 
        gap: 10px;
        white-space: nowrap;
    }
    .wishlist-toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0px);
    }

    /* HIDE EMPTY STATE BY DEFAULT */
    #wishlistEmptyState {
        display: none;
    }

    /* --- MAKE DELETE BUTTON VISIBLE ON OUT OF STOCK ITEMS --- */
.wishlist-item.out-of-stock .btn-remove-wishlist {
    background: #ffebee !important;
    color: #d32f2f !important;
    border: 1px solid #ffcdd2 !important;
    pointer-events: auto !important;
    cursor: pointer !important;
    opacity: 1 !important;
}

.wishlist-item.out-of-stock .btn-remove-wishlist:hover {
    background: #d32f2f !important;
    color: white !important;
    transform: scale(1.05);
}

/* --- ADD A TOOLTIP/HINT --- */
.wishlist-item.out-of-stock .btn-remove-wishlist::after {
    content: "Remove";
    font-size: 10px;
    font-weight: 600;
    color: #d32f2f;
    margin-left: 4px;
}

.wishlist-item.out-of-stock .btn-remove-wishlist:hover::after {
    color: white;
}
</style>

<div class="page-title">My Wishlist <span id="wishlistCount" style="font-size: 14px; font-weight: 400; color: #888;">(<?php echo $total_items; ?> items)</span></div>

<!-- WISHLIST GRID - IN STOCK ITEMS -->
<div id="wishlistGrid" class="wishlist-grid">
    <?php if (!empty($in_stock_items)): ?>
        <?php foreach($in_stock_items as $item): ?>
        <div class="wishlist-item" id="wishlist_<?php echo $item['wishlist_id']; ?>">
            
            <img src="<?php echo htmlspecialchars(img_url($item['image'])); ?>" class="wishlist-item-img" alt="<?php echo $item['name']; ?>">

            <div class="wishlist-item-name"><?php echo $item['name']; ?></div>
            
            <span class="stock-badge in-stock">In Stock: <?php echo $item['quantity']; ?></span>
            
            <div class="wishlist-item-price">PHP <?php echo number_format($item['price'], 2); ?></div>
            
            <div class="wishlist-item-actions">
                <button class="btn-add-cart" onclick="addToCartFromWishlist(<?php echo $item['id']; ?>, <?php echo $item['wishlist_id']; ?>)">
                    <i class="fas fa-shopping-cart"></i> Add to Cart
                </button>
                <button class="btn-remove-wishlist" onclick="openWishlistDeleteModal(<?php echo $item['wishlist_id']; ?>, '<?php echo addslashes($item['name']); ?>')" title="Remove from wishlist">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- OUT OF STOCK ITEMS - SEPARATE SECTION (Same as cart) -->
<?php if (!empty($out_of_stock_items)): ?>
<div class="out-of-stock-section">
    <div class="out-of-stock-title">
        <i class="fas fa-exclamation-triangle"></i>
        Out of Stock Items
        <span style="font-size: 12px; font-weight: 400; color: #999;">(<?php echo count($out_of_stock_items); ?> items)</span>
    </div>
    <div class="wishlist-grid">
        <?php foreach($out_of_stock_items as $item): ?>
        <div class="wishlist-item out-of-stock" id="wishlist_<?php echo $item['wishlist_id']; ?>">
            
            <img src="<?php echo htmlspecialchars(img_url($item['image'])); ?>" class="wishlist-item-img" alt="<?php echo $item['name']; ?>" style="filter: grayscale(0.5);">
            
            <div class="wishlist-item-name"><?php echo $item['name']; ?></div>
            
            <span class="stock-badge out-of-stock">Out of Stock</span>
            
            <div class="wishlist-item-price" style="color: #999;">PHP <?php echo number_format($item['price'], 2); ?></div>
            
            <div class="wishlist-item-actions">
                <button class="btn-add-cart" disabled>
                    <i class="fas fa-times-circle"></i> Out of Stock
                </button>
                <button class="btn-remove-wishlist" onclick="openWishlistDeleteModal(<?php echo $item['wishlist_id']; ?>, '<?php echo addslashes($item['name']); ?>')" title="Remove from wishlist">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- EMPTY STATE -->
<div id="wishlistEmptyState" class="wishlist-empty" style="<?php echo ($total_items == 0) ? 'display: block;' : 'display: none;'; ?>">
    <i class="far fa-heart"></i>
    <p style="font-size: 18px; margin-bottom: 10px;">Your wishlist is empty</p>
    <p style="font-size: 14px; color: #bbb;">Start adding your favorite products by clicking the heart icon ❤️ on any product</p>
    <a href="shop.php" style="display: inline-block; margin-top: 20px; padding: 12px 30px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: white; border-radius: 50px; text-decoration: none; font-weight: 600; transition: 0.2s;">
        Browse Products
    </a>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div class="delete-modal-overlay" id="deleteWishlistModal">
    <div class="delete-modal-box">
        <div class="delete-modal-icon"><i class="fas fa-heart"></i></div>
        <div class="delete-modal-title">Remove from Wishlist?</div>
        <div class="delete-modal-sub">
            Are you sure you want to remove "<strong id="deleteWishlistName"></strong>" from your wishlist?
        </div>
        <div class="delete-modal-buttons">
            <button class="btn-cancel-delete" onclick="closeWishlistDeleteModal()">Cancel</button>
            <button class="btn-confirm-delete" onclick="confirmWishlistDelete()">Remove</button>
        </div>
    </div>
</div>

<!-- CART ALERT MODAL -->
<div class="cart-alert-overlay" id="cartAlertModal">
    <div class="cart-alert-box">
        <div class="cart-alert-icon" id="cartAlertIcon">
            <i class="fas fa-shopping-cart" style="color: #f9a825; background: #fff8e1; padding: 15px; border-radius: 50%;"></i>
        </div>
        <div class="cart-alert-title" id="cartAlertTitle">Already in Cart!</div>
        <div class="cart-alert-sub" id="cartAlertMessage">
            This product is already in your shopping cart.
        </div>
        <button class="cart-alert-btn" onclick="closeCartAlert()">
            <i class="fas fa-check" style="margin-right: 8px;"></i> Got it!
        </button>
    </div>
</div>

<!-- TOAST NOTIFICATION -->
<div id="wishlistToast" class="wishlist-toast"></div>

<script>
let wishlistDeleteId = 0;

function openWishlistDeleteModal(wishlistId, productName) {
    wishlistDeleteId = wishlistId;
    document.getElementById('deleteWishlistName').innerHTML = productName;
    document.getElementById('deleteWishlistModal').style.display = 'flex';
}

function closeWishlistDeleteModal() {
    document.getElementById('deleteWishlistModal').style.display = 'none';
    wishlistDeleteId = 0;
}

function confirmWishlistDelete() {
    if (wishlistDeleteId <= 0) {
        alert('Error: No item selected to remove.');
        return;
    }
    
    const item = document.getElementById('wishlist_' + wishlistDeleteId);
    if (item) {
        item.style.opacity = '0.5';
        item.style.pointerEvents = 'none';
    }
    
    fetch('remove_wishlist_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'wishlist_id=' + wishlistDeleteId
    })
    .then(response => response.json())
    .then(data => {
        closeWishlistDeleteModal();
        
        if (data.success) {
            if (item) {
                item.style.display = 'none';
            }
            
            const countSpan = document.getElementById('wishlistCount');
            let currentCount = parseInt(countSpan.textContent.replace(/[^0-9]/g, ''));
            
            if (currentCount > 0) {
                currentCount--;
                countSpan.textContent = '(' + currentCount + ' items)';
            }
            
            // Check if there are no more items left (including out of stock)
const remainingItems = document.querySelectorAll('.wishlist-item:not([style*="display: none"])');
if (remainingItems.length === 0) {
    document.getElementById('wishlistGrid').style.display = 'none';
    document.getElementById('wishlistEmptyState').style.display = 'block';
    countSpan.textContent = '(0 items)';
}

// ✅ NEW: Check if there are no more OUT OF STOCK items left
const remainingOutOfStock = document.querySelectorAll('.wishlist-item.out-of-stock:not([style*="display: none"])');
const outOfStockSection = document.querySelector('.out-of-stock-section');
if (remainingOutOfStock.length === 0 && outOfStockSection) {
    outOfStockSection.style.display = 'none';
}
            
            showWishlistToast('Removed from wishlist 💔');
            updateShopPageHearts();
            
        } else {
            if (item) {
                item.style.opacity = '1';
                item.style.pointerEvents = 'auto';
            }
            alert('Error: ' + (data.message || 'Could not remove item from wishlist.'));
        }
    })
    .catch(error => {
        if (item) {
            item.style.opacity = '1';
            item.style.pointerEvents = 'auto';
        }
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function addToCartFromWishlist(productId, wishlistId) {
    fetch('check_cart_quantity.php?product_id=' + productId)
    .then(response => response.json())
    .then(cartData => {
        if (cartData.quantity > 0) {
            showCartAlert('This product is already in your shopping cart.', 'already-in-cart');
            return;
        }
        
        fetch('get_product_stock.php?product_id=' + productId)
        .then(response => response.json())
        .then(stockData => {
            if (stockData.stock <= 0) {
                showCartAlert('This product is currently <strong>out of stock</strong>.', 'out-of-stock');
                return;
            }
            
            fetch('add_to_cart_modal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + productId + '&quantity=1'
            })
            .then(response => response.text())
            .then(data => {
                if (data.trim() === 'login_required') {
                    alert('Please login first');
                } else if (data.trim() === 'stock_limit_reached') {
                    showCartAlert('Not enough stock available.', 'out-of-stock');
                } else {
                    showWishlistToast('Added to cart! 🛒');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding to cart.');
            });
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function showCartAlert(message, type) {
    const modal = document.getElementById('cartAlertModal');
    const icon = document.getElementById('cartAlertIcon');
    const title = document.getElementById('cartAlertTitle');
    const msg = document.getElementById('cartAlertMessage');
    
    if (type === 'out-of-stock') {
        icon.innerHTML = '<i class="fas fa-times-circle" style="color: #d32f2f; background: #ffebee; padding: 15px; border-radius: 50%;"></i>';
        title.textContent = 'Out of Stock!';
        title.style.color = '#d32f2f';
    } else if (type === 'already-in-cart') {
        icon.innerHTML = '<i class="fas fa-shopping-cart" style="color: #f9a825; background: #fff8e1; padding: 15px; border-radius: 50%;"></i>';
        title.textContent = 'Already in Cart!';
        title.style.color = '#f9a825';
    } else {
        icon.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #f9a825; background: #fff8e1; padding: 15px; border-radius: 50%;"></i>';
        title.textContent = 'Notice';
        title.style.color = '#f9a825';
    }
    
    msg.innerHTML = message;
    modal.style.display = 'flex';
}

function closeCartAlert() {
    document.getElementById('cartAlertModal').style.display = 'none';
}

function showWishlistToast(message) {
    const toast = document.getElementById('wishlistToast');
    toast.innerHTML = message;
    toast.classList.add('show');
    
    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => {
        toast.classList.remove('show');
    }, 2500);
}

function updateShopPageHearts() {
    localStorage.setItem('wishlist_updated', Date.now());
}

document.getElementById('deleteWishlistModal').addEventListener('click', function(e) {
    if (e.target === this) closeWishlistDeleteModal();
});

document.getElementById('cartAlertModal').addEventListener('click', function(e) {
    if (e.target === this) closeCartAlert();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeWishlistDeleteModal();
        closeCartAlert();
    }
});

console.log('Wishlist page loaded with ' + document.querySelectorAll('.wishlist-item').length + ' items');
</script>