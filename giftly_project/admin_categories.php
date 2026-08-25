<?php
include 'db_connect.php'; 

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$user_check = $conn->query("SELECT role FROM users WHERE id = $user_id");
$user_data = $user_check->fetch_assoc();
if ($user_data['role'] !== 'admin') {
    header("Location: shop.php");
    exit();
}

// Handle ADD category
if (isset($_POST['add_category'])) {
    $cat_name = mysqli_real_escape_string($conn, $_POST['cat_name']);
    $sql = "INSERT INTO categories (name) VALUES ('$cat_name')";
    $conn->query($sql);
    header("Location: admin_categories.php?msg=added");
    exit();
}

// Handle DELETE category
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM categories WHERE id = $id");
    header("Location: admin_categories.php?msg=deleted");
    exit();
}

// Handle UPDATE (RENAME) category
if (isset($_POST['update_category'])) {
    $id = $_POST['cat_id'];
    $new_name = mysqli_real_escape_string($conn, $_POST['cat_name']);
    $conn->query("UPDATE categories SET name = '$new_name' WHERE id = $id");
    header("Location: admin_categories.php?msg=updated");
    exit();
}

include 'admin_header.php'; 
?>

<style>
    .wide-container { max-width: 800px; margin: 0 auto; padding: 40px 20px; width: 100%; flex: 1; }
    .admin-table-card { background: #fff; border-radius: 24px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); }
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th { text-align: left; padding: 15px 10px; border-bottom: 2px solid #f0f0f0; color: #444; font-weight: 600; font-size: 14px; }
    .admin-table td { padding: 20px 10px; border-bottom: 1px solid #f5f5f5; font-size: 14px; color: #333; vertical-align: middle; }
    .admin-table tr:last-child td { border-bottom: none; }

    /* BUTTONS */
    .btn-edit-cat { background: #e3f2fd; color: #1976d2; border: none; padding: 6px 14px; border-radius: 30px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; margin-right: 5px; }
    .btn-edit-cat:hover { background: #1976d2; color: white; }

    .btn-delete-cat { background: #ffe4e4; color: #d32f2f; border: none; padding: 6px 14px; border-radius: 30px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .btn-delete-cat:hover { background: #d32f2f; color: white; }

    .alert-box { padding: 12px; border-radius: 16px; text-align: center; font-weight: 500; margin-bottom: 20px; }
    .alert-green { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
    .alert-red { background: #fdeded; color: #d32f2f; border: 1px solid #ffc1cc; }
    .alert-blue { background: #e3f2fd; color: #1976d2; border: 1px solid #90caf9; }
    
    .action-btn-primary { background: #ff8ba7; color: white; padding: 10px 20px; border: none; border-radius: 50px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .action-btn-primary:hover { transform: scale(1.05); }
    .pink-input { width: 100%; padding: 12px; border: 1.5px solid #eee; border-radius: 12px; font-size: 14px; font-family: 'Poppins'; outline: none; }
    .pink-input:focus { border-color: #ffc1cc; }

    /* EDIT MODAL */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px); display: none; justify-content: center; align-items: center; z-index: 9999; }
    .modal-box { background: #fff; border-radius: 30px; padding: 40px; max-width: 400px; width: 90%; box-shadow: 0 20px 50px rgba(0,0,0,0.2); position: relative; }
    .modal-close { position: absolute; top: 15px; right: 20px; font-size: 24px; color: #888; cursor: pointer; transition: 0.2s; }
    .modal-close:hover { color: #ff8ba7; transform: rotate(90deg); }
    .modal-input { width: 100%; padding: 12px; border: 1.5px solid #eee; border-radius: 12px; margin-bottom: 15px; font-family: 'Poppins'; outline: none; }
    .modal-input:focus { border-color: #ffc1cc; }
    .modal-btn { width: 100%; background: #ffc1cc; color: white; padding: 14px; border: none; border-radius: 50px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .modal-btn:hover { background: #ff8ba7; transform: translateY(-2px); }
</style>

<div class="wide-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h2 style="font-size: 26px; font-weight: 600; color: #222; margin-bottom: 5px;">Manage Categories</h2>
        <a href="admin_dashboard.php" style="background: #f3f3f3; padding: 8px 20px; border-radius: 50px; font-size: 14px; font-weight: 500; color: #555; text-decoration: none; transition: 0.3s;">&larr; Dashboard</a>
    </div>

    <?php 
    if (isset($_GET['msg']) && $_GET['msg'] == 'added') {
        echo '<div class="alert-box alert-green"><i class="fas fa-check-circle" style="margin-right: 8px;"></i> Category added successfully!</div>';
    }
    if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') {
        echo '<div class="alert-box alert-red"><i class="fas fa-trash-alt" style="margin-right: 8px;"></i> Category deleted successfully!</div>';
    }
    if (isset($_GET['msg']) && $_GET['msg'] == 'updated') {
        echo '<div class="alert-box alert-blue"><i class="fas fa-pen" style="margin-right: 8px;"></i> Category renamed successfully!</div>';
    }
    ?>

    <div class="admin-table-card">
        <!-- Add Category Form -->
        <div style="display: flex; gap: 10px; margin-bottom: 25px; align-items: center;">
            <input type="text" id="newCatInput" class="pink-input" placeholder="Enter new category name..." style="flex: 1;">
            <button onclick="addCategory()" class="action-btn-primary"><i class="fas fa-plus"></i> Add</button>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT * FROM categories ORDER BY name ASC";
                $result = $conn->query($sql);
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo '
                        <tr>
                            <td><strong>'.$row['name'].'</strong></td>
                            <td style="text-align: right;">
                                <button class="btn-edit-cat" onclick="openEditModal('.$row['id'].', \''.addslashes($row['name']).'\')"><i class="fas fa-pen"></i> Edit</button>
                                <a href="admin_categories.php?delete='.$row['id'].'" onclick="return confirm(\'Delete this category?\');"><button class="btn-delete-cat"><i class="fas fa-trash"></i> Delete</button></a>
                            </td>
                        </tr>
                        ';
                    }
                } else {
                    echo "<tr><td colspan='2' style='padding: 30px; text-align:center; color:#888;'>No categories yet. Add one above!</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- EDIT CATEGORY MODAL -->
<div class="modal-overlay" id="editCatModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeCatModal()">&times;</span>
        <h3 style="margin-bottom: 20px; color: #222; text-align: center;">Rename Category</h3>
        <form action="admin_categories.php" method="POST">
            <input type="hidden" name="cat_id" id="edit_cat_id">
            
            <label style="font-weight: 500; font-size: 14px; color: #555; display: block; margin-bottom: 6px;">New Category Name</label>
            <input type="text" name="cat_name" id="edit_cat_name" class="modal-input" required>
            
            <button type="submit" name="update_category" class="modal-btn">Save Changes</button>
        </form>
    </div>
</div>

<script>
    function openEditModal(id, name) {
        document.getElementById('edit_cat_id').value = id;
        document.getElementById('edit_cat_name').value = name;
        document.getElementById('editCatModal').style.display = 'flex';
    }

    function closeCatModal() {
        document.getElementById('editCatModal').style.display = 'none';
    }

    document.getElementById('editCatModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCatModal();
        }
    });

    function addCategory() {
        var input = document.getElementById('newCatInput');
        var val = input.value.trim();
        if(val === '') {
            alert("Please enter a category name.");
            return;
        }
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'admin_categories.php';
        var hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.name = 'add_category';
        hiddenField.value = '1';
        form.appendChild(hiddenField);
        var nameField = document.createElement('input');
        nameField.type = 'hidden';
        nameField.name = 'cat_name';
        nameField.value = val;
        form.appendChild(nameField);
        document.body.appendChild(form);
        form.submit();
    }
</script>

<?php include 'admin_footer.php'; ?>