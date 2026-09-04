<?php
$user_id = (int) $_SESSION['user_id'];
include_once 'address_lib.php';
addr_ensure_schema($conn);

// Handle Deletion
if (isset($_GET['delete_address'])) {
    $id = (int) $_GET['delete_address'];
    $was_default = false;
    $dr = $conn->query("SELECT is_default FROM addresses WHERE id = $id AND user_id = $user_id");
    if ($dr && $dr->num_rows) $was_default = addr_is_default($dr->fetch_assoc()['is_default']);

    $conn->query("DELETE FROM addresses WHERE id = $id AND user_id = $user_id");

    // if we removed the default, promote the newest remaining address
    if ($was_default) {
        $nx = $conn->query("SELECT id FROM addresses WHERE user_id = $user_id ORDER BY id DESC LIMIT 1");
        if ($nx && $nx->num_rows) addr_set_default($conn, $user_id, (int) $nx->fetch_assoc()['id']);
    }

    echo '<meta http-equiv="refresh" content="0; url=profile.php?tab=addresses">';
    exit();
}

// Handle "set as default"
if (isset($_POST['set_default_address'])) {
    addr_set_default($conn, $user_id, (int) $_POST['set_default_address']);
    echo '<meta http-equiv="refresh" content="0; url=profile.php?tab=addresses">';
    exit();
}

// Handle Adding
if (isset($_POST['add_address'])) {
    $label_choice = $_POST['label_choice'] ?? 'Home';
    if ($label_choice === 'Other') {
        $label_raw = trim($_POST['label_other'] ?? '');
        if ($label_raw === '') $label_raw = 'Other';
    } else {
        $label_raw = in_array($label_choice, addr_labels(), true) ? $label_choice : 'Home';
    }
    $label    = mysqli_real_escape_string($conn, mb_substr($label_raw, 0, 50));
    $address  = mysqli_real_escape_string($conn, $_POST['address']);
    $city     = mysqli_real_escape_string($conn, $_POST['city']);
    $province = mysqli_real_escape_string($conn, $_POST['province']);
    $zip      = mysqli_real_escape_string($conn, $_POST['zip']);

    // first address for this user, or "make default" ticked -> becomes default
    $existing = (int) ($conn->query("SELECT COUNT(*) AS c FROM addresses WHERE user_id = $user_id")->fetch_assoc()['c'] ?? 0);
    $make_default = ($existing === 0) || !empty($_POST['make_default']);

    $conn->query("INSERT INTO addresses (user_id, label, address, city, province, zip)
                  VALUES ($user_id, '$label', '$address', '$city', '$province', '$zip')");
    if ($make_default) {
        $new_id = (int) $conn->insert_id;
        if ($new_id <= 0) {
            $new_id = (int) ($conn->query("SELECT id FROM addresses WHERE user_id = $user_id ORDER BY id DESC LIMIT 1")->fetch_assoc()['id'] ?? 0);
        }
        if ($new_id > 0) addr_set_default($conn, $user_id, $new_id);
    }

    echo '<meta http-equiv="refresh" content="0; url=profile.php?tab=addresses">';
    exit();
}

$addresses = $conn->query("SELECT * FROM addresses WHERE user_id = $user_id ORDER BY is_default DESC, id DESC");
?>

<style>

        /* --- DELETE CONFIRM MODAL STYLES --- */
    .delete-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(6px);
        z-index: 999999; display: none; justify-content: center; align-items: center; padding: 20px;
    }
    .delete-modal-box {
        background: #ffffff; border-radius: 30px; padding: 40px; max-width: 400px; width: 90%;
        text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        animation: fadeUp 0.3s ease;
    }
    @keyframes fadeUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .delete-icon { font-size: 50px; color: #d32f2f; margin-bottom: 15px; }
    .delete-modal-title { font-size: 22px; font-weight: 700; color: #222; margin-bottom: 5px; }
    .delete-modal-sub { font-size: 14px; color: #888; margin-bottom: 25px; line-height: 1.5; }
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
    .btn-delete-confirm:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4); }

    .btn-small { padding: 8px 16px; border: none; border-radius: 50px; font-weight: 600; font-size: 13px; cursor: pointer; transition: 0.2s; }
    .btn-pink { background: #ffc1cc; color: white; }
    .btn-pink:hover { background: #ff8ba7; }
    .btn-danger { background: #fdeded; color: #d32f2f; }
    .btn-danger:hover { background: #d32f2f; color: white; }
    .address-card { 
        border: 1px solid #eee; 
        border-radius: 16px; 
        padding: 15px 20px; 
        margin-bottom: 15px; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        gap: 15px; /* 🚨 Adds space between text and button */
    }        
    .address-card h4 { 
        margin: 0 0 5px 0; 
        color: #222; 
        flex: 1; /* 🚨 Stops text from pushing the button */
    }
    .address-card p { 
        margin: 0; 
        color: #888; 
        font-size: 14px; 
        line-height: 1.5; 
        flex: 1; /* 🚨 Stops text from pushing the button */
    }
    .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
    .form-group { flex: 1; display: flex; flex-direction: column; }
    .form-group label { font-size: 14px; font-weight: 600; color: #444; margin-bottom: 6px; }
    .form-input { padding: 14px 16px; border: 1.5px solid #eee; border-radius: 16px; font-size: 14px; font-family: 'Poppins'; background: #fafafa; transition: 0.3s; outline: none; width: 100%; }
    .btn-save { padding: 16px 40px; border: none; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: white; font-size: 16px; font-weight: 600; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2); margin-top: 10px; }
    .btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4); }
    @media (max-width: 850px) { .form-row { flex-direction: column; gap: 15px; } }
</style>


<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div class="page-title" style="margin-bottom:0;">My Addresses</div>
    <button onclick="document.getElementById('addAddressForm').style.display='block'" class="btn-save" style="padding: 10px 24px; font-size:14px; margin:0;">+ Add New Address</button>
</div>


<!-- Add Address Form -->
<div id="addAddressForm" style="display: none; ...">
    <form action="profile.php?tab=addresses" method="POST">
        
        <div class="form-group" style="margin-bottom: 15px;">
            <label>Address Label</label>
            <select name="label_choice" id="labelChoice" class="form-input" onchange="document.getElementById('labelOtherWrap').style.display = this.value === 'Other' ? 'block' : 'none';">
                <option value="Home">🏠 Home</option>
                <option value="Office">🏢 Office</option>
                <option value="Other">✏️ Other…</option>
            </select>
        </div>
        <div class="form-group" id="labelOtherWrap" style="margin-bottom: 15px; display: none;">
            <label>Custom label</label>
            <input type="text" name="label_other" class="form-input" placeholder="e.g. Mom's House">
        </div>

        <?php $psgc_id = 'pf'; include 'psgc_widget.php'; ?>

        <div class="form-group" style="margin-bottom: 15px;">
            <label>House / Unit / Street</label>
            <input type="text" name="address" id="pf_street" class="form-input" placeholder="e.g. Blk 1 Lot 2, Rizal St." required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>City / Municipality</label>
                <input type="text" name="city" id="pf_city" class="form-input" placeholder="filled by the picker" required>
            </div>
            <div class="form-group">
                <label>Province</label>
                <input type="text" name="province" id="pf_prov_out" class="form-input" placeholder="filled by the picker" required>
            </div>
            <div class="form-group">
                <label>ZIP Code</label>
                <input type="text" name="zip" class="form-input" inputmode="numeric" maxlength="4" required>
            </div>
        </div>
        <script>
            document.getElementById('pf_psgc').addEventListener('psgc:change', function (e) {
                var d = e.detail;
                if (d.city) document.getElementById('pf_city').value = d.barangay ? 'Brgy. ' + d.barangay + ', ' + d.city : d.city;
                var prov = d.province || (d.region && d.region.indexOf('NCR') > -1 ? 'Metro Manila' : '');
                if (prov) document.getElementById('pf_prov_out').value = prov;
            });
        </script>
        <label style="display:flex; align-items:center; gap:8px; font-size:13.5px; color:#555; margin-bottom:14px; cursor:pointer;">
            <input type="checkbox" name="make_default" value="1"> Set as my default address
        </label>
        <button type="submit" name="add_address" class="btn-save" style="padding: 10px 24px; font-size:14px; margin:0;">Save Address</button>
        <button type="button" onclick="document.getElementById('addAddressForm').style.display='none'" style="padding: 10px 24px; border-radius: 50px; border: 1px solid #eee; background: #fff; cursor: pointer; font-weight: 500; margin-left: 10px;">Cancel</button>
    </form>
</div>

<?php if ($addresses->num_rows > 0): ?>
    <?php while($row = $addresses->fetch_assoc()):
        $is_def = addr_is_default($row['is_default']);
    ?>
        <div class="address-card" style="<?php echo $is_def ? 'border-color:#ffc1cc; background:#fff8fa;' : ''; ?>">
            <div>
                <h4>
                    <?php echo htmlspecialchars($row['label'] ?: 'Address'); ?>
                    <?php if ($is_def): ?>
                        <span style="display:inline-block; margin-left:6px; background:linear-gradient(135deg,#FEA5B6 0%,#ff8ba7 100%); color:#fff; font-size:10px; font-weight:700; padding:2px 9px; border-radius:50px; vertical-align:middle;">DEFAULT</span>
                    <?php endif; ?>
                </h4>
                <p><?php echo htmlspecialchars($row['address'] . ', ' . $row['city'] . ', ' . $row['province'] . ' ' . $row['zip']); ?></p>
            </div>
            <div style="display:flex; gap:8px; flex-shrink:0;">
                <?php if (!$is_def): ?>
                <form method="POST" action="profile.php?tab=addresses" style="margin:0;">
                    <input type="hidden" name="set_default_address" value="<?php echo (int) $row['id']; ?>">
                    <button type="submit" class="btn-small btn-pink" style="white-space:nowrap;"><i class="fas fa-star"></i> Set default</button>
                </form>
                <?php endif; ?>
                <a href="javascript:void(0)" onclick="openDeleteModal(<?php echo (int) $row['id']; ?>)">
                    <button class="btn-small btn-danger"><i class="fas fa-trash"></i></button>
                </a>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p style="color:#888; text-align:center; padding:20px;">You haven't added any addresses yet.</p>
<?php endif; ?>

<!-- 🚨 DELETE CONFIRMATION MODAL -->
<div class="delete-modal-overlay" id="deleteConfirmModal">
    <div class="delete-modal-box">
        <div class="delete-icon"><i class="fas fa-trash-alt" style="background: #fdeded; padding: 15px; border-radius: 50%;"></i></div>
        <div class="delete-modal-title">Delete this address?</div>
        <div class="delete-modal-sub">Are you sure you want to remove this saved address? This action cannot be undone.</div>
        <div class="delete-buttons">
            <button class="btn-delete-cancel" onclick="closeDeleteModal()">Cancel</button>
            <button class="btn-delete-confirm" id="confirmDeleteBtn">Yes, Delete</button>
        </div>
    </div>
</div>

<script>
    let deleteTargetId = 0;

    function openDeleteModal(id) {
        deleteTargetId = id;
        document.getElementById('deleteConfirmModal').style.display = 'flex';
    }

    function closeDeleteModal() {
        document.getElementById('deleteConfirmModal').style.display = 'none';
        deleteTargetId = 0;
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if(deleteTargetId > 0) {
            // Redirect to the delete URL
            window.location.href = 'profile.php?tab=addresses&delete_address=' + deleteTargetId;
        }
    });

    // Close modal when clicking outside the white box
    document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
    });
</script>