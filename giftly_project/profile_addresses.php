<?php
$user_id = $_SESSION['user_id'];

// Handle Deletion
if (isset($_GET['delete_address'])) {
    $id = $_GET['delete_address'];
    $conn->query("DELETE FROM addresses WHERE id = $id AND user_id = $user_id");
    
    // 🚨 REPLACE header() WITH THIS:
    echo '<meta http-equiv="refresh" content="0; url=profile.php?tab=addresses">';
    exit();
}

// Handle Adding
if (isset($_POST['add_address'])) {
    $label = mysqli_real_escape_string($conn, $_POST['label']); // Grab the label
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $province = mysqli_real_escape_string($conn, $_POST['province']);
    $zip = mysqli_real_escape_string($conn, $_POST['zip']);
    
    // ✅ Update the SQL to include 'label'
    $conn->query("INSERT INTO addresses (user_id, label, address, city, province, zip) VALUES ($user_id, '$label', '$address', '$city', '$province', '$zip')");
    
    echo '<meta http-equiv="refresh" content="0; url=profile.php?tab=addresses">';
    exit();
}

$addresses = $conn->query("SELECT * FROM addresses WHERE user_id = $user_id ORDER BY id DESC");
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
        
        <!-- ✅ CORRECT PLACEMENT: Label is now INSIDE the form -->
        <div class="form-group" style="margin-bottom: 15px;">
            <label>Address Label (e.g. Home, Office, Mom's House)</label>
            <input type="text" name="label" class="form-input" placeholder="e.g. Home" required>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label>Street Address</label>
            <input type="text" name="address" class="form-input" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>City</label>
                <input type="text" name="city" class="form-input" required>
            </div>
            <div class="form-group">
                <label>Province</label>
                <input type="text" name="province" class="form-input" required>
            </div>
            <div class="form-group">
                <label>ZIP Code</label>
                <input type="text" name="zip" class="form-input" required>
            </div>
        </div>
        <button type="submit" name="add_address" class="btn-save" style="padding: 10px 24px; font-size:14px; margin:0;">Save Address</button>
        <button type="button" onclick="document.getElementById('addAddressForm').style.display='none'" style="padding: 10px 24px; border-radius: 50px; border: 1px solid #eee; background: #fff; cursor: pointer; font-weight: 500; margin-left: 10px;">Cancel</button>
    </form>
</div>

<?php if ($addresses->num_rows > 0): ?>
    <?php while($row = $addresses->fetch_assoc()): ?>
        <div class="address-card">
    <div>
        <h4><?php echo $row['label']; ?> <!-- 🚨 SHOW THE LABEL HERE --></h4>
        <p><?php echo $row['address'] . ', ' . $row['city'] . ', ' . $row['province'] . ' ' . $row['zip']; ?></p>
    </div>
                       <a href="javascript:void(0)" onclick="openDeleteModal(<?php echo $row['id']; ?>)">
                <button class="btn-small btn-danger"><i class="fas fa-trash"></i> Delete</button>
            </a>
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