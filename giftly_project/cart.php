<?php 
include 'db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'header.php'; 
$user_id = $_SESSION['user_id'];
?>

<style>
    /* --- 2-COLUMN CART LAYOUT --- */
    .cart-wrapper { 
        max-width: 1100px; 
        margin: 0 auto; 
        padding-top: 130px; 
        padding-bottom: 60px; 
        display: flex; 
        gap: 40px; 
        flex-wrap: wrap;
    }
    
    /* LEFT COLUMN: ITEMS LIST */
    .cart-left { flex: 1; min-width: 350px; }
    .cart-title { font-size: 26px; font-weight: 700; color: #222; margin-bottom: 25px; }

    .cart-title { 
    font-size: 26px; 
    font-weight: 700; 
    color: #222; 
    margin-bottom: 25px; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
}

.cart-badge {
    font-size: 16px;
    font-weight: 500;
    color: #888;
    background: #f5f5f5;
    padding: 2px 12px;
    border-radius: 50px;
    line-height: 1.4;
}
    /* --- SELECT ALL (Aligned perfectly) --- */
        /* --- SELECT ALL + ACTION BUTTONS ROW --- */
    .cart-top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0 15px 15px;
    border-bottom: 1px solid #f0f0f0;
    margin-bottom: 20px;
}
    .select-all-wrapper {
        display: flex; align-items: center; gap: 12px;
    }
    .select-all-wrapper input { 
        width: 18px; height: 18px; accent-color: #ff8ba7; cursor: pointer; margin: 0;
    }
    .select-all-wrapper label { font-size: 14px; font-weight: 500; color: #555; cursor: pointer; }

    /* --- TOP RIGHT ACTIONS (WISHLIST & REMOVE) --- */
    .top-actions {
        display: flex; align-items: center; gap: 15px;
    }
    .top-action-btn {
        background: transparent; border: none;
        font-size: 13px; font-weight: 500; color: #888;
        cursor: pointer; transition: 0.2s; font-family: 'Poppins';
        display: flex; align-items: center; gap: 5px;
    }
    .top-action-btn i { font-size: 14px; }
    .top-action-btn:hover { color: #ff8ba7; }
    .top-action-btn.delete:hover { color: #d32f2f; }
    .select-all-wrapper input { 
        width: 18px; height: 18px; accent-color: #ff8ba7; cursor: pointer; margin: 0;
    }
    .select-all-wrapper label { font-size: 14px; font-weight: 500; color: #555; cursor: pointer; }

    /* --- CART ITEM CARD --- */
    .cart-item-card {
        background: #ffffff; border-radius: 20px; padding: 16px 20px;
        margin-bottom: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.03);
        border: 1px solid #f5f5f5; display: flex; align-items: center; gap: 15px;
        transition: all 0.2s ease;
    }
    .cart-item-card:hover { 
        box-shadow: 0 6px 20px rgba(255, 139, 167, 0.08); 
        transform: translateY(-2px); 
        border-color: #ffc1cc;
    }
    
    .ci-check { 
        flex-shrink: 0; 
        display: flex; align-items: center; justify-content: center;
        width: 24px; height: 24px;
    }
    .ci-check input[type="checkbox"] { 
        width: 18px; height: 18px; accent-color: #ff8ba7; cursor: pointer; margin: 0;
    }
    
    .ci-img { 
        width: 70px; height: 70px; object-fit: contain; background: #fafafa; 
        border-radius: 14px; padding: 8px; flex-shrink: 0; 
    }
    .ci-details { flex: 1; }
    .ci-name { font-size: 16px; font-weight: 600; color: #222; }
    .ci-price { font-size: 14px; color: #888; }
    
    .ci-actions { 
        display: flex; align-items: center; gap: 12px; flex-shrink: 0; 
    }
    .ci-qty-wrapper { 
        display: flex; align-items: center; border: 1px solid #eee; 
        border-radius: 50px; padding: 2px 12px; background: #fff; 
    }
    .ci-qty-btn { 
        background: transparent; border: none; font-size: 16px; 
        cursor: pointer; padding: 0 6px; color: #888; transition: 0.2s; 
    }
    .ci-qty-btn:hover { color: #ff8ba7; }
    .ci-qty-display { 
        font-size: 15px; font-weight: 600; min-width: 20px; text-align: center; color: #222; 
    }
    
    .ci-remove-btn { 
        background: transparent; border: none; color: #ccc; cursor: pointer; 
        transition: 0.2s; font-size: 18px; display: flex; align-items: center; justify-content: center; 
    }
    .ci-remove-btn:hover { color: #d32f2f; transform: scale(1.1); }

    .ci-subtotal { 
        font-size: 15px; font-weight: 700; color: #222; min-width: 100px; text-align: right; 
    }

    /* --- RIGHT COLUMN: ORDER SUMMARY --- */
.cart-right { 
    width: 360px; 
    flex-shrink: 0;
    margin-top: 65px; /* <--- This moves the whole column down to align with the items */
}

.summary-card {
    background: #ffffff; 
    border-radius: 24px; 
    padding: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03); 
    border: 1px solid #f5f5f5;
    position: sticky; 
    top: 120px;
}
    .summary-card h4 { font-size: 18px; font-weight: 700; color: #222; margin-bottom: 20px; }
    
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 15px; color: #666; }
    .summary-row.grand { 
        border-top: 1px solid #f0f0f0; padding-top: 15px; margin-top: 15px; 
        font-size: 18px; font-weight: 700; color: #222; 
    }
    
    .btn-checkout {
        width: 100%; padding: 16px; border: none; border-radius: 50px;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; font-size: 16px; font-weight: 600; cursor: pointer;
        transition: 0.2s; margin-top: 20px; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
    }
    .btn-checkout:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4); }
    .btn-checkout:disabled { background: #ccc !important; cursor: not-allowed !important; transform: none !important; }

    .empty-cart { text-align: center; padding: 60px 20px; background: #fff; border-radius: 24px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); grid-column: 1 / -1; }
    .empty-cart i { font-size: 60px; color: #ddd; margin-bottom: 20px; }

    @media (max-width: 850px) {
        .cart-wrapper { flex-direction: column; }
        .cart-right { width: 100%; }
        .summary-card { position: static; }
        .cart-item-card { flex-wrap: wrap; }
        .ci-subtotal { min-width: auto; }
    }

        /* --- CUTE DELETE CONFIRMATION MODAL --- */
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
    .delete-icon { font-size: 50px; color: #d32f2f; margin-bottom: 15px; }
    .delete-title { font-size: 22px; font-weight: 700; color: #222; margin-bottom: 5px; }
    .delete-sub { font-size: 14px; color: #888; margin-bottom: 25px; line-height: 1.5; }
    .delete-buttons { display: flex; gap: 15px; justify-content: center; }
    .btn-delete-cancel {
        flex: 1; padding: 14px; border: none; border-radius: 50px;
        background: #eaeaea; color: #555; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Poppins';
    }
    .btn-delete-cancel:hover { background: #d6d6d6; }
    .btn-delete-confirm {
        flex: 1; padding: 14px; border: none; border-radius: 50px;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Poppins';
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
    }
    .btn-delete-confirm:hover { background: linear-gradient(135deg, #ff8ba7 0%, #FEA5B6 100%); transform: translateY(-2px); }

/* --- OUT OF STOCK SECTION --- */
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
.cart-item-card.out-of-stock {
    opacity: 0.5;
    background: #fafafa;
    border-color: #e0e0e0;
    pointer-events: none;
    cursor: not-allowed;
}
.cart-item-card.out-of-stock .ci-check input[type="checkbox"] {
    display: none;
}
.cart-item-card.out-of-stock .ci-qty-wrapper {
    opacity: 0.5;
}
.cart-item-card.out-of-stock .ci-remove-btn {
    pointer-events: auto;
    cursor: pointer;
}
.cart-item-card.out-of-stock .out-of-stock-badge {
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
.out-of-stock-badge {
    display: none;
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

/* Already in Wishlist Modal - Single button */
#alreadyWishlistModal .delete-buttons {
    justify-content: center !important;
}

#alreadyWishlistModal .btn-delete-confirm {
    flex: 0.5 !important;
    min-width: 120px !important;
}
</style>

<div class="cart-wrapper">
    
    <!-- LEFT COLUMN: ITEMS -->
    <div class="cart-left">
        <h2 class="cart-title">
            Your Cart 
            <span id="cartTitleCount" class="cart-badge">(0)</span>
        </h2>

        <!-- 🚀 STOCK WARNING - Place this right after the cart title -->
<?php if (!empty($stock_warnings)): ?>
<div style="background: #fff8e1; border: 1px solid #ffd54f; border-radius: 16px; padding: 15px 20px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 12px; box-shadow: 0 2px 8px rgba(255, 193, 7, 0.1);">
    <i class="fas fa-exclamation-triangle" style="color: #f9a825; font-size: 20px; margin-top: 2px;"></i>
    <div style="flex: 1;">
        <strong style="color: #222; font-size: 15px;">Stock Updated!</strong>
        <div style="margin-top: 6px; font-size: 14px; color: #555;">
            Some items in your cart were adjusted because other customers purchased them first.
            <?php foreach($stock_warnings as $warning): ?>
                <span style="display: block; padding: 3px 0; font-size: 13px; color: #666;">
                    • <strong><?php echo $warning['name']; ?></strong>: 
                    Requested <?php echo $warning['requested']; ?>, 
                    now <strong><?php echo $warning['available']; ?></strong> available
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

        <!-- 🚨 CART TOP ROW (Select All + Actions) - THIS WAS MISSING -->
        <div class="cart-top-row">
            
            <!-- LEFT: Select All -->
            <div class="select-all-wrapper">
                <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
                <label for="selectAll"><strong>Select All</strong> <span id="selectedCountLabel" style="font-weight: normal; color: #888; font-size: 13px;"></span></label>
            </div>

            <!-- RIGHT: Move to Wishlist | Remove -->
<div class="top-actions">
    <button class="top-action-btn" onclick="moveAllSelectedToWishlist()">
        <i class="fas fa-heart" style="color: #ff8ba7;"></i> Move to wishlist
    </button>
    <span style="color: #ddd;">|</span>
    <button class="top-action-btn delete" onclick="removeAllSelected()">
        <i class="fas fa-trash-alt"></i> Remove
    </button>
</div>

        </div>

        <div id="cartItemsList">
        <?php
    // ✅ DIRECT DATABASE QUERY - NO API CALL
    $sql = "SELECT c.id as cart_id, c.quantity, p.name, p.price, p.image, p.quantity as stock_quantity 
            FROM carts c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = $user_id";
    $result = $conn->query($sql);
    
    $in_stock_items = [];
    $out_of_stock_items = [];
    $totalQuantity = 0;
    $stock_warnings = [];
    
    while($row = $result->fetch_assoc()) {
        $totalQuantity += $row['quantity'];
        
        // Check for stock issues
        if ($row['quantity'] > $row['stock_quantity']) {
            $stock_warnings[] = [
                'name' => $row['name'],
                'requested' => $row['quantity'],
                'available' => $row['stock_quantity']
            ];
            // Auto-correct quantity
            $new_qty = $row['stock_quantity'];
            $conn->query("UPDATE carts SET quantity = $new_qty WHERE id = {$row['cart_id']} AND user_id = $user_id");
            $row['quantity'] = $new_qty;
        }
        
        if ($row['stock_quantity'] <= 0) {
            $out_of_stock_items[] = $row;
        } else {
            $in_stock_items[] = $row;
        }
    }
        
    if (!empty($in_stock_items) || !empty($out_of_stock_items)) {
        // Display In Stock items
        foreach($in_stock_items as $row) {
            $subtotal = $row['price'] * $row['quantity'];
            ?>
            <div class="cart-item-card" id="row_<?php echo $row['cart_id']; ?>" data-stock="<?php echo $row['stock_quantity']; ?>">
                
                <!-- Checkbox -->
                <div class="ci-check">
                    <input type="checkbox" name="selected_items[]" value="<?php echo $row['cart_id']; ?>" class="item-checkbox" onchange="updateTotal()" data-id="<?php echo $row['cart_id']; ?>">
                </div>

                <!-- Image -->
                <img src="uploads/<?php echo $row['image']; ?>" class="ci-img" alt="Product">
                
                <!-- Name & Price -->
                <div class="ci-details">
                    <div class="ci-name"><?php echo $row['name']; ?></div>
                    <div class="ci-price">PHP <?php echo number_format($row['price'], 2); ?> each</div>
                    <div style="font-size: 11px; color: #888; margin-top: 2px;">
                        Stock: <?php echo $row['stock_quantity']; ?> available
                    </div>
                </div>

                <!-- Quantity & Delete -->
                <div class="ci-actions">
                    <div class="ci-qty-wrapper">
                        <button class="ci-qty-btn" onclick="updateQty(<?php echo $row['cart_id']; ?>, 'decrease')">−</button>
                        <span class="ci-qty-display" id="qty_<?php echo $row['cart_id']; ?>"><?php echo $row['quantity']; ?></span>
                        <button class="ci-qty-btn" onclick="updateQty(<?php echo $row['cart_id']; ?>, 'increase')">+</button>
                    </div>
                    <button class="ci-remove-btn" onclick="openDeleteModal(<?php echo $row['cart_id']; ?>)"><i class="fas fa-trash-alt" style="color: #ff8ba7; transition: 0.2s;"></i></button>
                </div>

                <!-- Subtotal -->
                <div class="ci-subtotal" id="sub_<?php echo $row['cart_id']; ?>">PHP <?php echo number_format($subtotal, 2); ?></div>
            </div>
            <?php
        }
        
        // Display Out of Stock items
        if (!empty($out_of_stock_items)) {
            ?>
            <div class="out-of-stock-section">
                <div class="out-of-stock-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Out of Stock Items
                    <span style="font-size: 12px; font-weight: 400; color: #999;">(<?php echo count($out_of_stock_items); ?> items)</span>
                </div>
            <?php
            foreach($out_of_stock_items as $row) {
                $subtotal = $row['price'] * $row['quantity'];
                ?>
                <div class="cart-item-card out-of-stock" id="row_<?php echo $row['cart_id']; ?>" data-stock="0">
                    
                    <!-- Checkbox (hidden for out of stock) -->
                    <div class="ci-check">
                        <input type="checkbox" name="selected_items[]" value="<?php echo $row['cart_id']; ?>" class="item-checkbox" disabled>
                    </div>

                    <!-- Image -->
                    <img src="uploads/<?php echo $row['image']; ?>" class="ci-img" alt="Product" style="filter: grayscale(0.5);">
                    
                    <!-- Name & Price -->
                    <div class="ci-details">
                        <div class="ci-name"><?php echo $row['name']; ?></div>
                        <div class="ci-price">PHP <?php echo number_format($row['price'], 2); ?> each</div>
                    </div>

                    <!-- Quantity & Delete (quantity controls disabled) -->
                    <div class="ci-actions">
                        <div class="ci-qty-wrapper" style="opacity: 0.5;">
                            <button class="ci-qty-btn" style="cursor: not-allowed;">−</button>
                            <span class="ci-qty-display" id="qty_<?php echo $row['cart_id']; ?>"><?php echo $row['quantity']; ?></span>
                            <button class="ci-qty-btn" style="cursor: not-allowed;">+</button>
                        </div>
                        <button class="ci-remove-btn" onclick="openDeleteModal(<?php echo $row['cart_id']; ?>)"><i class="fas fa-trash-alt" style="color: #ff8ba7; transition: 0.2s;"></i></button>
                    </div>

                    <!-- Subtotal -->
                    <div class="ci-subtotal" id="sub_<?php echo $row['cart_id']; ?>" style="color: #999;">PHP <?php echo number_format($subtotal, 2); ?></div>
                </div>
                <?php
            }
            ?>
            </div>
            <?php
        }
        
    } else {
        $totalQuantity = 0;
        echo '<div class="empty-cart">
                <i class="fas fa-shopping-bag"></i>
                <p style="font-size: 18px; color: #888; margin-bottom: 20px;">Your cart is empty.</p>
                <a href="shop.php" style="background: #ffc1cc; color: white; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: 600;">Start Shopping</a>
              </div>';
    }
    ?>
</div>

        <!-- Hidden input to store total quantity for JavaScript -->
        <input type="hidden" id="totalCartQuantity" value="<?php echo $totalQuantity; ?>">
    </div>

    <!-- RIGHT COLUMN: ORDER SUMMARY -->
    <div class="cart-right">
        <div class="summary-card">
            <h4>Order Summary</h4>
            
            <!-- Selected Items List -->
            <div style="margin-bottom: 15px; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px;">
                <div style="font-size: 14px; font-weight: 600; color: #333; margin-bottom: 8px;">
                    <span id="summaryItemCount">0</span> item<span id="summaryItemPlural">s</span>
                </div>
                <div id="selectedItemsList" style="font-size: 13px; color: #666; line-height: 1.6;">
                    <span style="color: #bbb;">No items selected</span>
                </div>
            </div>

            <div class="summary-row">
                <span>Subtotal</span>
                <span id="summarySubtotal">PHP 0.00</span>
            </div>
            <div class="summary-row" style="margin-bottom: 5px;">
                <span>Shipping</span>
                <span id="summaryShipping">PHP 0.00</span>
            </div>
            
            <!-- NEW: Small Shipping Note -->
            <div style="font-size: 11px; color: #999; margin-bottom: 10px; border-bottom: 1px solid #f0f0f0; padding-bottom: 12px;">
                <i class="fas fa-truck" style="margin-right: 4px;"></i> 
                Free shipping on orders over <strong>PHP 300</strong>
            </div>

            <div class="summary-row grand">
                <span>Total</span>
                <span id="summaryGrandTotal">PHP 0.00</span>
            </div>

            <button class="btn-checkout" id="btnProceed" onclick="proceedToCheckout()">
                <i class="fas fa-lock" style="margin-right: 8px;"></i> Proceed to Checkout
            </button>
        </div>
    </div>

</div>

<?php
/* =========================================================
   YOUR GIFT BOXES  (Build-a-Box) — self-contained section
   ========================================================= */
include_once 'build_a_box_lib.php';
bab_ensure_schema($conn);

$bab_cart_boxes = [];
$bab_res = $conn->query("SELECT id FROM boxes WHERE user_id = " . intval($user_id) . "
                         AND status = 'in_cart' ORDER BY updated_at DESC, id DESC");
while ($bab_res && $bab_row = $bab_res->fetch_assoc()) {
    $bab_b = bab_load_box($conn, $bab_row['id'], $user_id);
    if ($bab_b) $bab_cart_boxes[] = $bab_b;
}

if (!empty($bab_cart_boxes)):
?>
<style>
    .babc-wrap { max-width: 1100px; margin: 0 auto 40px; padding: 0 20px; }
    .babc-title { font-size: 22px; font-weight: 700; color: #222; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
    .babc-title .fa-gift { color: #ff8ba7; }
    .babc-card { background: #fff; border: 1px solid #f0f0f0; border-radius: 20px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.03); }
    .babc-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
    .babc-name { font-size: 16px; font-weight: 700; color: #222; }
    .babc-meta { font-size: 13px; color: #888; margin-top: 2px; }
    .babc-thumbs { display: flex; gap: 8px; margin: 14px 0; flex-wrap: wrap; }
    .babc-thumbs img { width: 46px; height: 46px; object-fit: contain; background: #fafafa; border-radius: 10px; padding: 4px; border: 1px solid #f0f0f0; }
    .babc-thumbs .more { width: 46px; height: 46px; border-radius: 10px; background: #f5f5f5; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #888; font-weight: 600; }
    .babc-issue { background: #fff8e1; border: 1px solid #ffd54f; color: #7a5c00; font-size: 12px; padding: 8px 12px; border-radius: 10px; margin: 10px 0; }
    .babc-foot { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; border-top: 1px solid #f5f5f5; padding-top: 14px; }
    .babc-total { font-size: 16px; font-weight: 700; color: #222; }
    .babc-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .babc-btn { padding: 9px 18px; border-radius: 50px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-family: 'Poppins'; }
    .babc-btn.edit { background: #eef4ff; color: #1976d2; }
    .babc-btn.co { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: #fff; }
    .babc-btn.rm { background: #ffe4e4; color: #d32f2f; }
    .babc-btn.disabled { opacity: .5; pointer-events: none; }
</style>

<div class="babc-wrap">
    <div class="babc-title"><i class="fas fa-gift"></i> Your Gift Boxes</div>
    <?php foreach ($bab_cart_boxes as $d):
        $box = $d['box'];
        $bad = count($d['issues']) > 0;
    ?>
    <div class="babc-card" id="babcCard<?php echo $box['id']; ?>">
        <div class="babc-top">
            <div>
                <div class="babc-name"><?php echo htmlspecialchars($box['size_name']); ?></div>
                <div class="babc-meta"><?php echo $d['item_count']; ?> / <?php echo $box['max_items']; ?> items</div>
            </div>
        </div>
        <div class="babc-thumbs">
            <?php
            $bshown = 0;
            foreach ($d['items'] as $bi) {
                if ($bi['unavailable'] === 'removed') continue;
                if ($bshown >= 6) break;
                echo '<img src="uploads/' . htmlspecialchars($bi['image']) . '" alt="">';
                $bshown++;
            }
            $brem = count($d['items']) - $bshown;
            if ($brem > 0) echo '<div class="more">+' . $brem . '</div>';
            ?>
        </div>
        <?php if ($bad): ?>
            <div class="babc-issue"><i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($d['issues'][0]); ?> Edit the box before checkout.
            </div>
        <?php endif; ?>
        <div class="babc-foot">
            <div class="babc-total">PHP <?php echo number_format($d['total'], 2); ?></div>
            <div class="babc-actions">
                <a href="build-a-box.php?box_id=<?php echo $box['id']; ?>" class="babc-btn edit"><i class="fas fa-pen"></i> Edit</a>
                <a href="box_checkout.php?box_id=<?php echo $box['id']; ?>" class="babc-btn co <?php echo $bad ? 'disabled' : ''; ?>"><i class="fas fa-lock"></i> Checkout box</a>
                <button class="babc-btn rm" onclick="babcRemove(<?php echo $box['id']; ?>)"><i class="fas fa-trash"></i></button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
function babcRemove(id) {
    if (!confirm('Remove this gift box from your cart?')) return;
    fetch('box_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'delete', box_id: id })
    }).then(r => r.json()).then(d => {
        if (d.status === 'success') {
            const c = document.getElementById('babcCard' + id);
            if (c) c.remove();
            const wrap = document.querySelector('.babc-wrap');
            if (wrap && !wrap.querySelector('.babc-card')) wrap.remove();
        } else {
            alert(d.message || 'Could not remove box');
        }
    });
}
</script>
<?php endif; ?>

<!-- CUTE DELETE CONFIRMATION MODAL -->
<div class="delete-modal-overlay" id="deleteModal">
    <div class="delete-modal-box">
        <div class="delete-icon"><i class="fas fa-trash-alt" style="background: #fdeded; padding: 15px; border-radius: 50%;"></i></div>
        <div class="delete-title" id="deleteModalTitle">Remove Item?</div>
        <div class="delete-sub" id="deleteModalSub">Are you sure you want to remove this item from your cart?</div>
        <div class="delete-buttons">
            <button class="btn-delete-cancel" onclick="closeDeleteModal()">Cancel</button>
            <!-- This button now calls confirmDeleteAction() instead -->
            <button class="btn-delete-confirm" onclick="confirmDeleteAction()">Yes, Remove</button>
        </div>
    </div>
</div>

<!-- MOVE TO WISHLIST CONFIRMATION MODAL -->
<div class="delete-modal-overlay" id="moveWishlistModal">
    <div class="delete-modal-box">
        <div class="delete-icon" style="color: #ff8ba7;">
            <i class="fas fa-heart" style="background: #fff0f5; padding: 15px; border-radius: 50%; color: #ff8ba7;"></i>
        </div>
        <div class="delete-title" id="moveWishlistTitle" style="color: #ff8ba7;">Move to Wishlist?</div>
        <div class="delete-sub" id="moveWishlistSub">Are you sure you want to move this item to your wishlist?</div>
        <div class="delete-buttons">
            <button class="btn-delete-cancel" onclick="closeMoveWishlistModal()">Cancel</button>
            <button class="btn-delete-confirm" onclick="confirmMoveWishlist()" style="background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);">
                Yes, Move
            </button>
        </div>
    </div>
</div>

<!-- ALREADY IN WISHLIST MODAL -->
<div class="delete-modal-overlay" id="alreadyWishlistModal">
    <div class="delete-modal-box">
        <div class="delete-icon" style="color: #f9a825;">
            <i class="fas fa-heart" style="background: #fff8e1; padding: 15px; border-radius: 50%; color: #f9a825;"></i>
        </div>
        <div class="delete-title" style="color: #f9a825;">Already in Wishlist! ❤️</div>
        <div class="delete-sub" id="alreadyWishlistMessage">
            This item is already in your wishlist.
        </div>
        <div class="delete-buttons" style="justify-content: center;">
            <button class="btn-delete-confirm" onclick="closeAlreadyWishlistModal()" style="background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); flex: 0.5; min-width: 120px;">
                Got it!
            </button>
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
            Sorry, only <strong>22</strong> items available in stock.
        </div>
        <button class="stock-alert-btn" onclick="closeStockAlert()">
            <i class="fas fa-check" style="margin-right: 8px;"></i> Got it!
        </button>
    </div>
</div>



<script>
    /* --- SAVE SELECTION --- */
    function saveSelection() {
        let checkedIds = [];
        document.querySelectorAll('.item-checkbox:checked').forEach(function(cb) {
            checkedIds.push(cb.value);
        });
        sessionStorage.setItem('giftly_selected_items', JSON.stringify(checkedIds));
    }

    /* --- RESTORE SELECTION --- */
    function restoreSelection() {
        let saved = sessionStorage.getItem('giftly_selected_items');
        if(saved) {
            let ids = JSON.parse(saved);
            document.querySelectorAll('.item-checkbox').forEach(function(cb) {
                if(ids.includes(cb.value)) {
                    cb.checked = true;
                } else {
                    cb.checked = false;
                }
            });
            let allBoxes = document.querySelectorAll('.item-checkbox');
            let checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
            document.getElementById('selectAll').checked = (allBoxes.length === checkedBoxes.length && allBoxes.length > 0);
            updateTotal();
        }
    }

    /* --- TOGGLE ALL --- */
    function toggleAll(source) {
        let checkboxes = document.querySelectorAll('.item-checkbox');
        for(let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
        updateTotal();
        saveSelection();
    }

/* --- UPDATE QUANTITY (FIXED: Minimum is 1, Maximum is stock) --- */
function updateQty(cartId, action) {
    // First, check what the current quantity is in the DOM
    let currentQty = parseInt(document.getElementById('qty_' + cartId).innerText);
    
    // Get max stock from data attribute
    let row = document.getElementById('row_' + cartId);
    let maxStock = parseInt(row.dataset.stock) || 999;

    // If action is 'decrease' and current quantity is 1, DO NOTHING.
    if(action === 'decrease' && currentQty <= 1) {
        return;
    }

    // Proceed with the fetch
    fetch('update_cart_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'cart_id=' + cartId + '&action=' + action
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            if(data.new_qty <= 0) {
                document.getElementById('row_' + cartId).style.display = 'none';
            } else {
                document.getElementById('qty_' + cartId).innerText = data.new_qty;
                document.getElementById('sub_' + cartId).innerText = 'PHP ' + data.new_subtotal;
            }
            updateTotal();
            saveSelection();
        } else if(data.error) {
            // 🚨 USE CUSTOM MODAL INSTEAD OF ALERT
            showStockAlert(data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
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

           /* --- CUTE DELETE MODAL CONTROLS (For Both Single & Multiple) --- */
    let deleteTargetId = 0;

    function openDeleteModal(cartId) {
        deleteTargetId = cartId;
        document.getElementById('deleteModalTitle').innerText = 'Remove Item?';
        document.getElementById('deleteModalSub').innerText = 'Are you sure you want to remove this item from your cart?';
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
        deleteTargetId = 0;
        window.removeAllIds = null;
    }

    function confirmDeleteAction() {
        // SCENARIO 1: Removing All Selected Items
        if(window.removeAllIds && window.removeAllIds.length > 0) {
            let ids = window.removeAllIds;
            // Delete all items sequentially
            let deletions = ids.map(id => {
                return fetch('update_cart_ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'cart_id=' + id + '&action=delete'
                }).then(res => res.json());
            });

            Promise.all(deletions).then(() => {
                closeDeleteModal();
                location.reload(); // Refresh cart
            });
            return;
        }

        // SCENARIO 2: Removing a Single Item
        if(deleteTargetId > 0) {
            fetch('update_cart_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'cart_id=' + deleteTargetId + '&action=delete'
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('row_' + deleteTargetId).style.display = 'none';
                    let visibleItems = document.querySelectorAll('.cart-item-card:not([style*="display: none"])');
                    if(visibleItems.length === 0) {
                        location.reload();
                    } else {
                        updateTotal();
                        saveSelection();
                    }
                    closeDeleteModal();
                }
            });
        }
    }
    
    // Close modal when clicking outside
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
    });
    
    // Close modal when clicking outside
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
    });

 /* --- UPDATE TOTALS (SUBTOTAL, SHIPPING, GRAND TOTAL, & ITEM LIST) --- */
function updateTotal() {
    let checkboxes = document.querySelectorAll('.item-checkbox:not([disabled])'); // Only enabled checkboxes
    let checkedBoxes = document.querySelectorAll('.item-checkbox:checked:not([disabled])');
    let total = 0;
    let itemNames = [];
    let totalQuantity = 0;
    
    let countLabel = document.getElementById('selectedCountLabel');
    if(checkedBoxes.length > 0) {
        countLabel.innerText = '(' + checkedBoxes.length + ' selected)';
    } else {
        countLabel.innerText = '';
    }

    checkedBoxes.forEach(function(cb) {
        let row = cb.closest('.cart-item-card');
        if(row.style.display === 'none') return;
        
        // Get subtotal
        let subtotalText = row.querySelector('.ci-subtotal').innerText;
        let cleanNum = subtotalText.replace(/[^0-9.]/g, '');
        let subtotalNum = parseFloat(cleanNum);
        if(!isNaN(subtotalNum)) {
            total += subtotalNum;
        }

        // Get Item Name and Quantity for the Summary List
        let name = row.querySelector('.ci-name').innerText.replace('Out of Stock', '').trim();
        let qty = parseInt(row.querySelector('.ci-qty-display').innerText);
        itemNames.push(qty + ' x ' + name);
    });

    // --- UPDATE THE SELECTED ITEMS LIST ---
    let listContainer = document.getElementById('selectedItemsList');
    let itemCountDisplay = document.getElementById('summaryItemCount');
    let pluralDisplay = document.getElementById('summaryItemPlural');

    itemCountDisplay.innerText = itemNames.length;
    pluralDisplay.style.display = (itemNames.length === 1) ? 'none' : 'inline';

    if(itemNames.length > 0) {
        listContainer.innerHTML = itemNames.join('<br>');
    } else {
        listContainer.innerHTML = '<span style="color: #bbb;">No items selected</span>';
    }

    // --- UPDATE THE CART TITLE BADGE WITH TOTAL QUANTITY ---
    // Calculate total quantity by summing all quantities from visible items (including out of stock)
    let allItemRows = document.querySelectorAll('.cart-item-card:not([style*="display: none"])');
    let totalCartQuantity = 0;
    allItemRows.forEach(function(row) {
        let qtyDisplay = row.querySelector('.ci-qty-display');
        if(qtyDisplay) {
            let qty = parseInt(qtyDisplay.innerText);
            if(!isNaN(qty)) {
                totalCartQuantity += qty;
            }
        }
    });
    
    // Update the badge
    document.getElementById('cartTitleCount').innerText = '(' + totalCartQuantity + ')';

    // --- UPDATED SHIPPING LOGIC (Minimum fee: PHP 50) ---
    let shippingFee = 0;
    if(total > 0 && total < 300) {
        shippingFee = 50;
    } else if(total >= 300) {
        shippingFee = 0;
    }
    let grandTotal = total + shippingFee;

    // Update Summary Card Totals
    document.getElementById('summarySubtotal').innerText = 'PHP ' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    document.getElementById('summaryShipping').innerText = shippingFee === 0 ? 'FREE' : 'PHP ' + shippingFee.toFixed(2);
    document.getElementById('summaryGrandTotal').innerText = 'PHP ' + grandTotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    // Toggle Checkout Button
    document.getElementById('btnProceed').disabled = (total === 0);
}

/* --- PROCEED TO CHECKOUT - WITH API STOCK VERIFICATION --- */
function proceedToCheckout() {
    let checkboxes = document.querySelectorAll('.item-checkbox:checked:not([disabled])');
    if(checkboxes.length === 0) {
        alert("Please select at least one available item to checkout.");
        return;
    }
    
    let ids = [];
    checkboxes.forEach(cb => ids.push(cb.value));
    
    let btn = document.getElementById('btnProceed');
    let originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking stock...';
    btn.disabled = true;
    
    fetch('api/index.php?route=cart/verify-stock', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify({ cart_ids: ids })
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.status === 'success') {
            if (data.data.can_proceed) {
                window.location.href = 'checkout_selected.php?items=' + ids.join(',');
            } else if (data.data.has_issues) {
                let messages = data.data.issues.map(issue => {
                    if (issue.action === 'removed') {
                        return `<strong>${issue.product_name}</strong> is out of stock and has been removed from your cart.`;
                    } else {
                        return `<strong>${issue.product_name}</strong>: Requested ${issue.requested}, only ${issue.available} available. Quantity adjusted to ${issue.new_quantity}.`;
                    }
                });
                
                showStockAlert(messages.join('<br>'));
                setTimeout(() => location.reload(), 3000);
            }
        } else {
            alert('Error checking stock: ' + (data.message || 'Please try again.'));
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        console.error('Error:', error);
        alert('An error occurred while checking stock. Please try again.');
    });
}

        /* --- REMOVE ALL SELECTED ITEMS (Uses Cute Modal) --- */
    function removeAllSelected() {
        let checkboxes = document.querySelectorAll('.item-checkbox:checked');
        if(checkboxes.length === 0) {
            alert("Please select at least one item to remove.");
            return;
        }

        // Store the IDs in a global variable for the modal to use
        window.removeAllIds = [];
        checkboxes.forEach(cb => window.removeAllIds.push(cb.value));

        // Show the cute delete modal (Change the text to say "items")
        document.getElementById('deleteModalTitle').innerText = 'Remove Items?';
        document.getElementById('deleteModalSub').innerText = 'Are you sure you want to remove all selected items from your cart?';
        document.getElementById('deleteModal').style.display = 'flex';
    }

    /* --- INITIALIZE --- */
document.addEventListener('DOMContentLoaded', function() {
    // 1. Restore saved selections (checks boxes from previous session)
    restoreSelection();
    
    // 2. FORCE UPDATE THE TOTAL AND COUNTS ON PAGE LOAD
    updateTotal(); 

    // 3. Attach event listeners to all checkboxes
    document.querySelectorAll('.item-checkbox').forEach(function(cb) {
        cb.addEventListener('change', function() {
            saveSelection();
            updateTotal();
        });
    });
    
    // 4. Attach event listener to Select All
    document.getElementById('selectAll').addEventListener('change', function() {
        saveSelection();
    });
});

//* --- MOVE TO WISHLIST MODAL CONTROLS --- */
let moveTargetIds = [];
let moveIsSingle = true;

function openMoveWishlistModal(cartId) {
    moveTargetIds = [cartId];
    moveIsSingle = true;
    document.getElementById('moveWishlistTitle').innerText = 'Move to Wishlist?';
    document.getElementById('moveWishlistSub').innerHTML = 'Are you sure you want to move this item to your wishlist?';
    document.getElementById('moveWishlistModal').style.display = 'flex';
}

function openMoveAllWishlistModal(ids) {
    moveTargetIds = ids;
    moveIsSingle = false;
    document.getElementById('moveWishlistTitle').innerText = 'Move to Wishlist?';
    document.getElementById('moveWishlistSub').innerHTML = `Are you sure you want to move <strong>${ids.length}</strong> item(s) to your wishlist?`;
    document.getElementById('moveWishlistModal').style.display = 'flex';
}

function closeMoveWishlistModal() {
    document.getElementById('moveWishlistModal').style.display = 'none';
    moveTargetIds = [];
}

function confirmMoveWishlist() {
    if (moveTargetIds.length === 0) return;
    
    // Show loading state
    document.querySelector('#moveWishlistModal .btn-delete-confirm').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Moving...';
    document.querySelector('#moveWishlistModal .btn-delete-confirm').disabled = true;
    
    let ids = moveTargetIds;
    
    // If single item, use single endpoint
    if (moveIsSingle && ids.length === 1) {
        fetch('move_to_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'cart_id=' + ids[0]
        })
        .then(response => response.json())
        .then(data => {
            closeMoveWishlistModal();
            if (data.success) {
                document.getElementById('row_' + ids[0]).style.display = 'none';
                showToast(data.message);
                // ✅ UPDATE SELECTION STATE
                refreshCartDisplay();
                checkIfCartEmpty();
            } else {
                alert(data.message || 'Error moving item to wishlist');
            }
            resetMoveButton();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            resetMoveButton();
        });
    } else {
        // Multiple items
        fetch('move_all_to_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'cart_ids[]=' + ids.join('&cart_ids[]=')
        })
        .then(response => response.json())
        .then(data => {
            closeMoveWishlistModal();
            if (data.success) {
                // Hide all moved items
                ids.forEach(id => {
                    let row = document.getElementById('row_' + id);
                    if (row) row.style.display = 'none';
                });
                showToast(data.message);
                // ✅ UPDATE SELECTION STATE
                refreshCartDisplay();
                checkIfCartEmpty();
            } else {
                alert(data.message || 'Error moving items to wishlist');
            }
            resetMoveButton();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            resetMoveButton();
        });
    }
}

function resetMoveButton() {
    let btn = document.querySelector('#moveWishlistModal .btn-delete-confirm');
    btn.innerHTML = 'Yes, Move';
    btn.disabled = false;
}

/* --- CHECK IF CART IS EMPTY AND SHOW EMPTY STATE --- */
function checkIfCartEmpty() {
    let visibleItems = document.querySelectorAll('.cart-item-card:not([style*="display: none"])');
    let emptyCartDiv = document.querySelector('.empty-cart');
    let cartItemsList = document.getElementById('cartItemsList');
    
    if (visibleItems.length === 0) {
        // Hide the out of stock section if it exists
        let outOfStockSection = document.querySelector('.out-of-stock-section');
        if (outOfStockSection) {
            outOfStockSection.style.display = 'none';
        }
        
        // Show empty cart message
        if (cartItemsList) {
            cartItemsList.innerHTML = `
                <div class="empty-cart">
                    <i class="fas fa-shopping-bag"></i>
                    <p style="font-size: 18px; color: #888; margin-bottom: 20px;">Your cart is empty.</p>
                    <a href="shop.php" style="background: #ffc1cc; color: white; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: 600;">Start Shopping</a>
                </div>
            `;
        }
        
        // Update badge
        document.getElementById('cartTitleCount').innerText = '(0)';
    }
}

/* --- UPDATE EXISTING FUNCTIONS --- */

/* --- MOVE ALL SELECTED TO WISHLIST (with check) --- */
/* --- MOVE ALL SELECTED TO WISHLIST (with check) --- */
function moveAllSelectedToWishlist() {
    let checkboxes = document.querySelectorAll('.item-checkbox:checked');
    if (checkboxes.length === 0) {
        alert("Please select at least one item to move.");
        return;
    }
    
    let ids = [];
    checkboxes.forEach(cb => ids.push(cb.value));
    
    // Show loading on button
    let btn = document.querySelector('.top-action-btn .fa-heart').closest('.top-action-btn');
    let originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    btn.disabled = true;
    
    // Check which items are already in wishlist
    fetch('check_wishlist_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'cart_ids[]=' + ids.join('&cart_ids[]=')
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.success) {
            if (data.already_in_wishlist.length > 0) {
                // Show modal with items already in wishlist
                // This will auto-move items not in wishlist when closed
                showAlreadyWishlistModal(data.already_in_wishlist, data.not_in_wishlist);
            } else {
                // All items can be moved
                openMoveAllWishlistModal(ids);
            }
        } else {
            alert(data.message || 'Error checking wishlist status');
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

/* --- MOVE SINGLE ITEM TO WISHLIST (with check) --- */
function moveToWishlist(cartId) {
    // Check if item is already in wishlist
    fetch('check_wishlist_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'cart_ids[]=' + cartId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.already_in_wishlist.length > 0) {
                // Item is already in wishlist - show modal
                let item = data.already_in_wishlist[0];
                document.getElementById('alreadyWishlistMessage').innerHTML = 
                    `<strong>${item.name}</strong> is already in your wishlist! ❤️<br><br>
                     It will stay in your wishlist.`;
                document.getElementById('alreadyWishlistModal').style.display = 'flex';
                
                // Store data - item stays in wishlist, nothing to move
                alreadyWishlistData = {
                    already: data.already_in_wishlist,
                    not: []
                };
                alreadyWishlistIds = [];
            } else {
                // Item can be moved
                openMoveWishlistModal(cartId);
            }
        } else {
            alert(data.message || 'Error checking wishlist status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

/* --- TOAST NOTIFICATION --- */
function showToast(message) {
    let toast = document.getElementById('cartToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'cartToast';
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

/* --- UPDATE SELECT ALL STATE --- */
function updateSelectAllState() {
    let checkboxes = document.querySelectorAll('.item-checkbox:not([disabled])');
    let checkedBoxes = document.querySelectorAll('.item-checkbox:checked:not([disabled])');
    let selectAll = document.getElementById('selectAll');
    
    if (checkboxes.length > 0 && checkedBoxes.length === checkboxes.length) {
        selectAll.checked = true;
    } else {
        selectAll.checked = false;
    }
    
    // Update selected count label
    let countLabel = document.getElementById('selectedCountLabel');
    if (checkedBoxes.length > 0) {
        countLabel.innerText = '(' + checkedBoxes.length + ' selected)';
    } else {
        countLabel.innerText = '';
    }
}

/* --- REFRESH CART DISPLAY AFTER MOVING ITEMS --- */
function refreshCartDisplay() {
    // Update totals
    updateTotal();
    
    // Update select all state
    updateSelectAllState();
    
    // Save selection
    saveSelection();
}

/* --- ALREADY IN WISHLIST MODAL CONTROLS --- */
let alreadyWishlistData = [];
let alreadyWishlistIds = [];

function showAlreadyWishlistModal(alreadyItems, notItems) {
    let message = '';
    
    if (alreadyItems.length > 0) {
        message += '<strong>Already in wishlist:</strong><br>';
        alreadyItems.forEach(item => {
            message += `• ${item.name}<br>`;
        });
    }
    
    if (notItems.length > 0) {
        message += '<br><strong>Will be moved to wishlist:</strong><br>';
        notItems.forEach(item => {
            message += `• ${item.name}<br>`;
        });
    }
    
    document.getElementById('alreadyWishlistMessage').innerHTML = message;
    document.getElementById('alreadyWishlistModal').style.display = 'flex';
    
    // Store data for confirmation
    alreadyWishlistData = {
        already: alreadyItems,
        not: notItems
    };
    alreadyWishlistIds = notItems.map(item => item.cart_id);
}

function closeAlreadyWishlistModal() {
    document.getElementById('alreadyWishlistModal').style.display = 'none';
    alreadyWishlistData = [];
    alreadyWishlistIds = [];
}


/* --- MOVE ITEMS TO WISHLIST (After validation) --- */
function moveItemsToWishlist(cartIds) {
    if (cartIds.length === 0) return;
    
    // Show loading on the move button
    let btn = document.querySelector('#moveWishlistModal .btn-delete-confirm');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Moving...';
        btn.disabled = true;
    }
    
    let ids = cartIds;
    
    // If single item
    if (ids.length === 1) {
        fetch('move_to_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'cart_id=' + ids[0]
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('row_' + ids[0]).style.display = 'none';
                showToast(data.message);
                refreshCartDisplay();
                checkIfCartEmpty();
            } else {
                alert(data.message || 'Error moving item to wishlist');
            }
            resetMoveButton();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            resetMoveButton();
        });
    } else {
        // Multiple items
        fetch('move_all_to_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'cart_ids[]=' + ids.join('&cart_ids[]=')
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                ids.forEach(id => {
                    let row = document.getElementById('row_' + id);
                    if (row) row.style.display = 'none';
                });
                showToast(data.message);
                refreshCartDisplay();
                checkIfCartEmpty();
            } else {
                alert(data.message || 'Error moving items to wishlist');
            }
            resetMoveButton();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            resetMoveButton();
        });
    }
}
</script>


<?php include 'footer.php'; ?>