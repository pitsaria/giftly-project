<?php
include 'db_connect.php';
include 'build_a_box_lib.php';
include 'catalog_lib.php';
bab_ensure_schema($conn);
catalog_ensure_schema($conn);

if (isset($_POST['add_product'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $price = floatval($_POST['price']); // Convert to float
    $quantity = intval($_POST['quantity']); // Convert to integer
    $product_type = catalog_type_key($_POST['product_type'] ?? 'catalog');
    // Categories apply to shop products only; pre-made boxes/baskets use 0.
    $category_id = ($product_type === 'catalog') ? intval($_POST['category_id'] ?? 0) : 0;

    // 🚨 VALIDATION: shop products need a category
    if ($product_type === 'catalog' && $category_id <= 0) {
        $_SESSION['product_error'] = "Please select a category.";
        header("Location: admin_add_product.php");
        exit();
    }

    // 🚨 VALIDATION: regular shop products must be usable in at least one box size
    $box_size_ids = isset($_POST['box_sizes']) && is_array($_POST['box_sizes'])
        ? array_map('intval', $_POST['box_sizes']) : [];
    if ($product_type === 'catalog' && empty($box_size_ids)) {
        $_SESSION['product_error'] = "Please select at least one box size this product can go into.";
        header("Location: admin_add_product.php");
        exit();
    }
    
    // 🚨 VALIDATION: Check if price is negative or zero
    if ($price < 0) {
        $_SESSION['product_error'] = "Price cannot be negative.";
        header("Location: admin_add_product.php");
        exit();
    }
    
    // 🚨 VALIDATION: Check if price is empty or not a number
    if ($_POST['price'] === '' || !is_numeric($_POST['price'])) {
        $_SESSION['product_error'] = "Please enter a valid price.";
        header("Location: admin_add_product.php");
        exit();
    }
    
    // 🚨 VALIDATION: Check if quantity is negative
    if ($quantity < 0) {
        $_SESSION['product_error'] = "Quantity cannot be negative.";
        header("Location: admin_add_product.php");
        exit();
    }
    
    // 🚨 VALIDATION: Check if quantity is empty or not a number
    if ($_POST['quantity'] === '' || !is_numeric($_POST['quantity'])) {
        $_SESSION['product_error'] = "Please enter a valid quantity.";
        header("Location: admin_add_product.php");
        exit();
    }

    // 🚨 VALIDATION: Check if price exceeds maximum
if ($price > 9999.99) {
    $_SESSION['product_error'] = "Price cannot exceed 9,999.99.";
    header("Location: admin_add_product.php");
    exit();
}

// 🚨 VALIDATION: Check if quantity exceeds maximum
if ($quantity > 9999) {
    $_SESSION['product_error'] = "Quantity cannot exceed 9,999.";
    header("Location: admin_add_product.php");
    exit();
}
    
    // Upload the image to Supabase Storage; $new_filename holds the full public URL.
    $new_filename = supabase_upload_image($_FILES["image"] ?? []);

    if ($new_filename === null) {
        $_SESSION['product_error'] = "Image upload failed. Please try again with a JPG or PNG.";
        header("Location: admin_add_product.php");
        exit();
    }

    // Optional sale price + end date
    $sale_raw = trim($_POST['sale_price'] ?? '');
    $sale_sql = 'NULL';
    $sale_ends_sql = 'NULL';
    if ($sale_raw !== '') {
        if (!is_numeric($sale_raw) || floatval($sale_raw) < 0) {
            $_SESSION['product_error'] = "Sale price must be a valid amount.";
            header("Location: admin_add_product.php");
            exit();
        }
        $sale_val = floatval($sale_raw);
        if ($sale_val >= $price) {
            $_SESSION['product_error'] = "Sale price must be lower than the regular price.";
            header("Location: admin_add_product.php");
            exit();
        }
        $sale_sql = "'" . $sale_val . "'";
        $sale_ends_raw = trim($_POST['sale_ends'] ?? '');
        if ($sale_ends_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sale_ends_raw)) {
            // treat the picked day as inclusive — sale runs to end of that day
            $sale_ends_sql = "'" . $conn->real_escape_string($sale_ends_raw) . " 23:59:59'";
        }
    }

    // Occasion Boxes & Baskets: "What's Inside" (one item per line)
    $whats_inside = in_array($product_type, ['occasion_box', 'basket'], true)
        ? trim($_POST['whats_inside'] ?? '') : '';
    $whats_inside_sql = $whats_inside !== '' ? "'" . $conn->real_escape_string($whats_inside) . "'" : 'NULL';

    // Occasion Boxes only: color options (each needs its own image) + size options (each needs its own price)
    $color_names = $product_type === 'occasion_box' ? ($_POST['color_name'] ?? []) : [];
    $size_names  = $product_type === 'occasion_box' ? ($_POST['size_name'] ?? []) : [];
    $size_prices = $product_type === 'occasion_box' ? ($_POST['size_price'] ?? []) : [];

    $color_images = [];
    foreach ($color_names as $i => $cname) {
        if (trim($cname) === '') continue;
        $cfile = catalog_normalize_file($_FILES['color_image'] ?? [], $i);
        $curl = $cfile ? supabase_upload_image($cfile) : null;
        if ($curl === null) {
            $_SESSION['product_error'] = "Please add a product image for each color option.";
            header("Location: admin_add_product.php");
            exit();
        }
        $color_images[$i] = $curl;
    }

    $image_esc = mysqli_real_escape_string($conn, $new_filename);
    $is_active = isset($_POST['is_active']) ? 'TRUE' : 'FALSE';
    $sql = "INSERT INTO products (name, description, price, sale_price, sale_ends, quantity, category_id, image, product_type, is_active, whats_inside) VALUES ('$name', '$desc', '$price', $sale_sql, $sale_ends_sql, '$quantity', '$category_id', '$image_esc', '$product_type', $is_active, $whats_inside_sql)";
    if ($conn->query($sql) === TRUE) {
        // Resolve the new product id and record its allowed box sizes
        $new_pid = intval($conn->insert_id);
        if ($new_pid <= 0) {
            $img_esc = $conn->real_escape_string($new_filename);
            $pr = $conn->query("SELECT id FROM products WHERE image = '$img_esc' ORDER BY id DESC LIMIT 1");
            if ($pr && $pr->num_rows > 0) $new_pid = intval($pr->fetch_assoc()['id']);
        }
        if ($new_pid > 0) {
            foreach ($box_size_ids as $bsid) {
                $bsid = intval($bsid);
                $conn->query("INSERT INTO product_box_sizes (product_id, box_size_id)
                              VALUES ($new_pid, $bsid) ON CONFLICT DO NOTHING");
            }
            catalog_save_colors($conn, $new_pid, $color_names, $color_images);
            catalog_save_sizes($conn, $new_pid, $size_names, $size_prices);
        }
        $_SESSION['product_added'] = true;
        header("Location: admin_add_product.php");
        exit();
    }
}

$bab_all_sizes = bab_box_sizes($conn);

$show_success = false;
if (isset($_SESSION['product_added']) && $_SESSION['product_added'] === true) {
    $show_success = true;
    unset($_SESSION['product_added']); 
}

$show_error = '';
if (isset($_SESSION['product_error'])) {
    $show_error = $_SESSION['product_error'];
    unset($_SESSION['product_error']);
}

include 'admin_header.php'; 
?>

<style>
    .admin-form-container { background: #ffffff; border-radius: 30px; padding: 45px 40px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04); border: 1px solid rgba(255, 255, 255, 0.8); }
    .admin-form-group { margin-bottom: 24px; }
    .admin-form-group label { display: block; font-size: 14px; font-weight: 600; color: #444; margin-bottom: 8px; }
    .admin-input { width: 100%; padding: 16px 18px; border: 1.5px solid #eee; border-radius: 16px; font-size: 14px; background: #fafafa; transition: all 0.3s ease; outline: none; font-family: 'Poppins', sans-serif; }
    .admin-input:focus { border-color: #ffc1cc; background: #fff; box-shadow: 0 0 0 4px rgba(255, 193, 204, 0.15); }
    textarea.admin-input { resize: vertical; min-height: 110px; }
    .file-upload-wrapper { position: relative; width: 100%; border: 2px dashed #e0e0e0; border-radius: 16px; padding: 30px 20px; text-align: center; background: #fafafa; transition: all 0.3s ease; cursor: pointer; }
    .file-upload-wrapper:hover { border-color: #ffc1cc; background: #fff0f5; }
    .file-upload-wrapper.file-selected { border: 2px solid #ff8ba7; background: #fff0f5; }
    .file-upload-wrapper input[type="file"] { position: absolute; left: 0; top: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    .file-upload-icon { font-size: 32px; color: #ff8ba7; margin-bottom: 8px; transition: 0.3s; }
    .file-upload-text { font-size: 14px; color: #888; font-weight: 500; transition: 0.3s; }
    .file-upload-text span { color: #ff8ba7; font-weight: 600; }
    .file-upload-wrapper.file-selected .file-upload-text { color: #ff8ba7; font-weight: 600; }
    .admin-submit-btn { width: 100%; background: #ffc1cc; color: white; padding: 16px; border: none; border-radius: 50px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; margin-top: 10px; }
    .admin-submit-btn:hover { background: #ff8ba7; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(255, 139, 167, 0.3); }
    .admin-back-btn { display: inline-block; margin-top: 15px; color: #888; font-size: 14px; text-decoration: none; transition: 0.3s; }
    .admin-back-btn:hover { color: #ff8ba7; transform: translateX(-4px); }
    .admin-alert-success { background: linear-gradient(135deg, #e8f5e9, #c8e6c9); color: #2e7d32; padding: 15px 20px; border-radius: 16px; margin-bottom: 25px; text-align: center; font-weight: 500; border: 1px solid #a5d6a7; box-shadow: 0 4px 10px rgba(46, 125, 50, 0.05); }
    .wide-container { max-width: 750px; margin: 0 auto; padding: 40px 20px; width: 100%; flex: 1; }

.admin-alert-error {
    background: #fdeded;
    border: 1px solid #ffc1cc;
    color: #d32f2f;
    padding: 15px 20px;
    border-radius: 16px;
    margin-bottom: 25px;
    text-align: center;
    font-weight: 500;
}

.opt-add-btn { background: #fff0f5; color: #ff8ba7; border: 1.5px dashed #ffc1cc; padding: 10px 18px; border-radius: 30px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; }
.opt-add-btn:hover { background: #ff8ba7; color: #fff; border-style: solid; }
.opt-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
.opt-row input[type="text"], .opt-row input[type="number"] { flex: 1; padding: 12px 14px; border: 1.5px solid #eee; border-radius: 12px; font-size: 13px; font-family: 'Poppins'; outline: none; }
.opt-row input[type="file"] { flex: 1.3; font-size: 12px; }
.opt-row img.opt-preview { width: 40px; height: 40px; object-fit: contain; border-radius: 8px; background: #fafafa; }
.opt-remove-btn { background: #ffe4e4; color: #d32f2f; border: none; width: 32px; height: 32px; border-radius: 10px; cursor: pointer; flex-shrink: 0; }
.opt-remove-btn:hover { background: #d32f2f; color: #fff; }
</style>

<div class="wide-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
        <h2 class="section-title" style="margin-bottom: 0; font-size: 28px;">Add Product</h2>
        <a href="admin_dashboard.php" style="background: #f3f3f3; padding: 10px 24px; border-radius: 50px; font-size: 14px; font-weight: 500; color: #555; text-decoration: none; transition: 0.3s; display: flex; align-items: center; gap: 6px;">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
    </div>
    
    <?php if ($show_success === true) {
        echo '<div class="admin-alert-success"><i class="fas fa-check-circle" style="margin-right: 8px;"></i> Product Added Successfully!</div>';
    } ?>

    <?php if ($show_error): ?>
    <div class="admin-alert-error">
        <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> <?php echo $show_error; ?>
    </div>
<?php endif; ?>

    <div class="admin-form-container">
        <form action="" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
            <div class="admin-form-group">
                <label for="product_type">Product Type</label>
                <select id="product_type" name="product_type" class="admin-input" onchange="toggleBoxSizes()">
                    <?php foreach (catalog_types() as $tk => $tl): ?>
                        <option value="<?php echo $tk; ?>"><?php echo htmlspecialchars($tl); ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size: 12px; color: #888; margin-top: 4px;">
                    <i class="fas fa-info-circle"></i> "Shop product" appears in the shop &amp; Build-a-Box. "Occasion Box" / "Basket" are pre-made sets shown on their own page.
                </div>
            </div>
            <div class="admin-form-group">
                <label for="name">Product Name</label>
                <input type="text" id="name" name="name" class="admin-input" placeholder="e.g. Miss Dior Roses" required>
            </div>
            <div class="admin-form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="admin-input" placeholder="Write a short description..."></textarea>
            </div>
            <div class="admin-form-group">
    <label for="price">Price (PHP)</label>
    <input type="number" step="0.01" id="price" name="price" class="admin-input" placeholder="e.g. 500.00" min="0" max="9999.99" oninput="validatePrice(this); checkSalePrice();" required>
    <div style="font-size: 12px; color: #888; margin-top: 4px;">
        <i class="fas fa-info-circle"></i> Price must be between 0 and greater
    </div>
</div>
<div class="admin-form-group">
    <label for="sale_price">Sale Price (PHP) <span style="color:#bbb; font-weight:400;">(optional)</span></label>
    <input type="number" step="0.01" id="sale_price" name="sale_price" class="admin-input" placeholder="Leave blank for no sale" min="0" max="9999.99" oninput="checkSalePrice()">
    <label style="display:flex; align-items:center; gap:8px; margin-top:10px; font-weight:400; font-size:13px; color:#555;">
        Sale ends <input type="date" id="sale_ends" name="sale_ends" class="admin-input" style="width:auto; padding:8px 12px;">
        <span style="color:#999;">(optional — leave blank to run until you remove the sale price)</span>
    </label>
    <div style="font-size: 12px; color: #888; margin-top: 4px;">
        <i class="fas fa-tag"></i> Must be lower than the regular price. Shoppers see the old price struck through.
    </div>
    <div id="salePriceWarning" style="display:none; font-size:12.5px; color:#d32f2f; margin-top:6px; font-weight:600;">
        <i class="fas fa-exclamation-triangle"></i> Sale price must be lower than the regular price.
    </div>
</div>
<div class="admin-form-group">
    <label for="quantity">Stock Quantity</label>
    <input type="number" id="quantity" name="quantity" class="admin-input" placeholder="e.g. 10" value="0" min="0" max="9999" oninput="validateQuantity(this)" required>
    <div style="font-size: 12px; color: #888; margin-top: 4px;">
        <i class="fas fa-info-circle"></i> Quantity must be between 0 and greater
    </div>
</div>
            
            <!-- DYNAMIC CATEGORY DROPDOWN (shop products only) -->
            <div class="admin-form-group" id="categoryGroup">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" class="admin-input" required>
                    <option value="">Select a category</option>
                    <?php
                    $cat_sql = "SELECT * FROM categories ORDER BY name ASC";
                    $cat_result = $conn->query($cat_sql);
                    while($cat_row = $cat_result->fetch_assoc()) {
                        echo '<option value="'.$cat_row['id'].'">'.$cat_row['name'].'</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- ALLOWED BOX SIZES (Build-a-Box) -->
            <div class="admin-form-group" id="boxSizesGroup">
                <label>Allowed Box Sizes</label>
                <div style="font-size: 12px; color: #888; margin-bottom: 10px;">
                    <i class="fas fa-info-circle"></i> Which Build-a-Box sizes can this product be placed into? (Select at least one.)
                </div>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <?php foreach ($bab_all_sizes as $bs): ?>
                        <label style="flex: 1; min-width: 150px; display: flex; align-items: center; gap: 10px; padding: 14px 16px; border: 1.5px solid #eee; border-radius: 14px; background: #fafafa; cursor: pointer;">
                            <input type="checkbox" name="box_sizes[]" value="<?php echo $bs['id']; ?>" checked
                                   style="width: 18px; height: 18px; accent-color: #ff8ba7;">
                            <span style="font-size: 14px; font-weight: 500; color: #444;">
                                <?php echo htmlspecialchars($bs['name']); ?>
                                <small style="display: block; color: #999; font-weight: 400;">up to <?php echo $bs['max_items']; ?> items</small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- WHAT'S INSIDE (Occasion Boxes & Baskets) -->
            <div class="admin-form-group" id="whatsInsideGroup">
                <label for="whats_inside">What's Inside</label>
                <textarea id="whats_inside" name="whats_inside" class="admin-input" rows="5" placeholder="One item per line, e.g.&#10;Scented candle&#10;Handwritten card&#10;Box of chocolates"></textarea>
                <div style="font-size: 12px; color: #888; margin-top: 4px;">
                    <i class="fas fa-info-circle"></i> One item per line — shown to customers as a bulleted list.
                </div>
            </div>

            <!-- COLOR OPTIONS (Occasion Boxes only) -->
            <div class="admin-form-group" id="colorOptionsGroup">
                <label>Color Options</label>
                <div style="font-size: 12px; color: #888; margin-bottom: 10px;">
                    <i class="fas fa-info-circle"></i> Each color needs its own product image — it swaps in when the customer picks that color.
                </div>
                <div id="colorRows"></div>
                <button type="button" class="opt-add-btn" onclick="addColorRow()"><i class="fas fa-plus"></i> Add Color</button>
            </div>

            <!-- SIZE OPTIONS (Occasion Boxes only) -->
            <div class="admin-form-group" id="sizeOptionsGroup">
                <label>Size Options</label>
                <div style="font-size: 12px; color: #888; margin-bottom: 10px;">
                    <i class="fas fa-info-circle"></i> Each size has its own price — it replaces the base price when the customer picks that size.
                </div>
                <div id="sizeRows"></div>
                <button type="button" class="opt-add-btn" onclick="addSizeRow()"><i class="fas fa-plus"></i> Add Size</button>
            </div>

            <div class="admin-form-group">
                <label>Product Image</label>
                <div class="file-upload-wrapper" id="fileWrapper">
                    <div class="file-upload-icon" id="fileIcon"><i class="fas fa-cloud-upload-alt"></i></div>
                    <div class="file-upload-text" id="fileText">Drag & drop or <span>browse</span> to upload</div>
                    <input type="file" name="image" id="imageInput" required>
                </div>
            </div>

            <div class="admin-form-group">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:500;">
                    <input type="checkbox" name="is_active" value="1" checked style="width:18px; height:18px;">
                    Show this product on the customer site now
                </label>
                <div style="font-size:12px; color:#888; margin-top:4px;">Uncheck to add it hidden — you can flip it on later from the Products page.</div>
            </div>

            <button type="submit" name="add_product" class="admin-submit-btn">Add to Inventory</button>
        </form>
        <div style="text-align: center;">
            <a href="admin_dashboard.php" class="admin-back-btn"><i class="fas fa-arrow-left" style="margin-right: 6px;"></i> Back to Dashboard</a>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>

<script>
    function toggleBoxSizes() {
        var type = document.getElementById('product_type').value;
        var isShop = type === 'catalog';
        var isBox = type === 'occasion_box';
        var isBoxOrBasket = type === 'occasion_box' || type === 'basket';

        var boxGrp = document.getElementById('boxSizesGroup');
        boxGrp.style.display = isShop ? '' : 'none';
        boxGrp.querySelectorAll('input[type="checkbox"]').forEach(function(cb) { cb.disabled = !isShop; });

        var catGrp = document.getElementById('categoryGroup');
        var catSel = document.getElementById('category_id');
        catGrp.style.display = isShop ? '' : 'none';
        catSel.required = isShop;
        catSel.disabled = !isShop;

        document.getElementById('whatsInsideGroup').style.display = isBoxOrBasket ? '' : 'none';
        document.getElementById('colorOptionsGroup').style.display = isBox ? '' : 'none';
        document.getElementById('sizeOptionsGroup').style.display = isBox ? '' : 'none';
    }
    toggleBoxSizes();

    function addColorRow(name, imageUrl) {
        var row = document.createElement('div');
        row.className = 'opt-row';
        row.innerHTML =
            '<input type="text" name="color_name[]" placeholder="Color name (e.g. Red)" value="' + (name ? name.replace(/"/g, '&quot;') : '') + '">' +
            (imageUrl ? '<img class="opt-preview" src="' + imageUrl + '">' : '') +
            '<input type="file" name="color_image[]" accept="image/*">' +
            (imageUrl ? '<input type="hidden" name="color_existing_image[]" value="' + imageUrl + '">' : '<input type="hidden" name="color_existing_image[]" value="">') +
            '<button type="button" class="opt-remove-btn" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
        document.getElementById('colorRows').appendChild(row);
    }

    function addSizeRow(name, price) {
        var row = document.createElement('div');
        row.className = 'opt-row';
        row.innerHTML =
            '<input type="text" name="size_name[]" placeholder="Size name (e.g. Small)" value="' + (name ? name.replace(/"/g, '&quot;') : '') + '">' +
            '<input type="number" step="0.01" min="0" name="size_price[]" placeholder="Price (PHP)" value="' + (price !== undefined ? price : '') + '">' +
            '<button type="button" class="opt-remove-btn" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
        document.getElementById('sizeRows').appendChild(row);
    }
    // Start with one blank row of each so the admin sees the fields right away.
    addColorRow();
    addSizeRow();

    document.getElementById('imageInput').addEventListener('change', function(e) {
        var fileName = e.target.files[0].name;
        var wrapper = document.getElementById('fileWrapper');
        var text = document.getElementById('fileText');
        var icon = document.getElementById('fileIcon');

        if (fileName) {
            wrapper.classList.add('file-selected');
            text.innerHTML = '<i class="fas fa-check-circle" style="color: #ff8ba7;"></i> Selected: <strong>' + fileName + '</strong>';
            icon.innerHTML = '<i class="fas fa-check-circle" style="color: #ff8ba7; font-size: 32px;"></i>';
        }
    });

    function validateQuantity(input) {
    // If value is negative, set to 0
    if (parseInt(input.value) < 0) {
        input.value = 0;
    }
    // If value is empty, set to 0
    if (input.value === '') {
        input.value = 0;
    }
    // If value exceeds 9999, set to 9999
    if (parseInt(input.value) > 9999) {
        input.value = 9999;
        alert('Maximum quantity allowed is 9,999.');
    }
}

function validatePrice(input) {
    // If value is negative, set to 0
    if (parseFloat(input.value) < 0) {
        input.value = 0;
    }
    // If value is empty, set to 0
    if (input.value === '') {
        input.value = 0;
    }
    // If value exceeds 9999.99, set to 9999.99
    if (parseFloat(input.value) > 9999.99) {
        input.value = 9999.99;
        alert('Maximum price allowed is 9,999.99.');
    }
    // Ensure only 2 decimal places
    if (input.value.includes('.')) {
        var parts = input.value.split('.');
        if (parts[1] && parts[1].length > 2) {
            input.value = parseFloat(input.value).toFixed(2);
        }
    }
}

function validateForm() {
    var quantity = parseInt(document.getElementById('quantity').value);
    var price = parseFloat(document.getElementById('price').value);
    
    if (quantity < 0) {
        alert('Quantity cannot be negative.');
        return false;
    }
    if (isNaN(quantity)) {
        alert('Please enter a valid quantity.');
        return false;
    }
    if (quantity > 9999) {
        alert('Maximum quantity allowed is 9,999.');
        return false;
    }
    if (price < 0) {
        alert('Price cannot be negative.');
        return false;
    }
    if (isNaN(price) || price === '') {
        alert('Please enter a valid price.');
        return false;
    }
    if (price > 9999.99) {
        alert('Maximum price allowed is 9,999.99.');
        return false;
    }
    if (!checkSalePrice()) {
        document.getElementById('sale_price').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    return true;
}

/** Warns (without touching any field's value) when the sale price isn't lower than the regular price. */
function checkSalePrice() {
    var saleRaw = document.getElementById('sale_price').value.trim();
    var warning = document.getElementById('salePriceWarning');
    if (saleRaw === '') { warning.style.display = 'none'; return true; }
    var sale = parseFloat(saleRaw);
    var price = parseFloat(document.getElementById('price').value);
    var invalid = isNaN(sale) || sale < 0 || (!isNaN(price) && sale >= price);
    warning.style.display = invalid ? 'block' : 'none';
    return !invalid;
}
</script>