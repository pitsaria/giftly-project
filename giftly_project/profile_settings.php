<?php
// profile_settings.php
if (!isset($user_id)) { exit('Direct access not allowed.'); }

// --- FETCH USER DATA ---
$user = $conn->query("SELECT name, email, phone, profile_pic FROM users WHERE id = $user_id")->fetch_assoc();
$nameParts = explode(' ', $user['name']);
$firstname = $nameParts[0];
$lastname = isset($nameParts[1]) ? $nameParts[1] : '';

// --- FETCH ORDERS & ADDRESS COUNT FOR STATS ---
$orderCount = $conn->query("SELECT COUNT(*) as total FROM orders WHERE user_id = $user_id")->fetch_assoc()['total'];
$addressCount = $conn->query("SELECT COUNT(*) as total FROM addresses WHERE user_id = $user_id")->fetch_assoc()['total'];

// --- HANDLE PROFILE UPDATE ---
$message = '';
$msg_type = '';

if (isset($_POST['update_profile'])) {
    // 🛑 SAFE GRAB: Always grab these from the POST, or fall back to the database if they are missing
    $submitted_firstname = isset($_POST['firstname']) ? mysqli_real_escape_string($conn, $_POST['firstname']) : '';
    $submitted_lastname = isset($_POST['lastname']) ? mysqli_real_escape_string($conn, $_POST['lastname']) : '';
    $submitted_email = isset($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : '';
    $submitted_phone = isset($_POST['phone']) ? mysqli_real_escape_string($conn, $_POST['phone']) : '';

    // If text fields are sent, use them. If not (like when just uploading a pic), keep the DB values.
    $firstname = !empty($submitted_firstname) ? $submitted_firstname : $firstname;
    $lastname = !empty($submitted_lastname) ? $submitted_lastname : $lastname;
    $email = !empty($submitted_email) ? $submitted_email : $user['email'];
    $phone = !empty($submitted_phone) ? $submitted_phone : $user['phone'];
    
$profile_pic = $_SESSION['user_profile_pic'] ?? $user['profile_pic'] ?? '';

// 🚨 KEEP THIS: It forces the HTML to read the current session pic.
$user['profile_pic'] = $profile_pic; 

    // 🖼️ HANDLE PROFILE PICTURE UPLOAD
    if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $target_dir = "uploads/profile_pics/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        
        $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if(move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
            $profile_pic = $new_filename;
        }

            // 🚨 ADD THIS LINE RIGHT HERE, BEFORE THE FINAL CLOSING BRACE:
    $user['profile_pic'] = $profile_pic; 
    }

        // 🚨 NEW: HANDLE REMOVING PROFILE PICTURE
    if(isset($_POST['remove_profile_pic'])) {
        $profile_pic = ''; // Set to empty string
        // Optionally, delete the physical file from the server to save space
        if(!empty($user['profile_pic']) && file_exists("uploads/profile_pics/" . $user['profile_pic'])) {
            unlink("uploads/profile_pics/" . $user['profile_pic']);
        }
    }

    
    
    $current_pass = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_pass = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    
    $is_changing_password = !empty($new_pass);
    $fullname = $firstname . ' ' . $lastname;
    
    if ($is_changing_password) {
        $check = $conn->query("SELECT password FROM users WHERE id = $user_id");
        $row = $check->fetch_assoc();
        if (password_verify($current_pass, $row['password'])) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET name = '$fullname', email = '$email', phone = '$phone', profile_pic = '$profile_pic', password = '$hashed' WHERE id = $user_id");
            $_SESSION['user_name'] = $fullname;
            $_SESSION['user_profile_pic'] = $profile_pic;
            $message = "Profile updated successfully!";
            $msg_type = "success";
        } else {
            $message = "Current password is incorrect.";
            $msg_type = "error";
        }
    } else {
                $conn->query("UPDATE users SET name = '$fullname', email = '$email', phone = '$phone', profile_pic = '$profile_pic' WHERE id = $user_id");
        $_SESSION['user_name'] = $fullname;
        $_SESSION['user_profile_pic'] = $profile_pic;
        $message = "Profile updated successfully!";
        $msg_type = "success";
    }

    // 🚨 ADD THESE 2 LINES RIGHT HERE (Before the final closing brace below):
    $user['name'] = $fullname;
    $user['email'] = $email;
}
?>

<style>
    .profile-header { display: flex; align-items: center; gap: 20px; background: #fafafa; padding: 25px; border-radius: 20px; margin-bottom: 30px; border: 1px solid #eee; }
    .profile-avatar { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 700; flex-shrink: 0; }
    .profile-info h3 { margin: 0; font-size: 20px; font-weight: 700; color: #222; }
    .profile-info p { margin: 5px 0 0 0; color: #888; font-size: 14px; }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 30px; }
    .stat-card { background: #fff; border: 1px solid #f0f0f0; border-radius: 16px; padding: 20px 15px; text-align: center; transition: 0.2s; }
    .stat-card:hover { border-color: #ffc1cc; transform: translateY(-3px); box-shadow: 0 5px 15px rgba(255, 139, 167, 0.08); }
    .stat-card .stat-number { font-size: 24px; font-weight: 700; color: #222; display: block; }
    .stat-card .stat-label { font-size: 13px; color: #888; margin-top: 4px; display: block; }

    .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
    @media (max-width: 850px) { .settings-grid { grid-template-columns: 1fr; } .profile-header { flex-direction: column; text-align: center; } }

    .settings-box { background: #fff; border: 1px solid #f0f0f0; border-radius: 20px; padding: 25px; display: flex; flex-direction: column; height: fit-content; }
    .settings-box h4 { font-size: 16px; font-weight: 600; color: #222; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #f5f5f5; }

    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
    .form-input { width: 100%; padding: 12px 16px; border: 1.5px solid #eee; border-radius: 12px; font-size: 14px; font-family: 'Poppins'; background: #fafafa; transition: 0.3s; outline: none; }
    .form-input:focus { border-color: #ffc1cc; background: #fff; box-shadow: 0 0 0 4px rgba(255, 193, 204, 0.1); }

    .btn-save-pink { width: 100%; padding: 14px; border: none; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: white; font-size: 15px; font-weight: 600; cursor: pointer; transition: 0.2s; margin-top: 15px; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2); font-family: 'Poppins'; }
    .btn-save-pink:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4); }

    .alert-box { padding: 14px 18px; border-radius: 12px; margin-bottom: 25px; font-weight: 500; font-size: 14px; border: 1px solid; display: flex; align-items: center; gap: 10px; }
    .alert-box.success { background: #e8f5e9; color: #2e7d32; border-color: #a5d6a7; }
    .alert-box.error { background: #fdeded; color: #d32f2f; border-color: #ffc1cc; }

    .input-hint { font-size: 12px; color: #999; margin-top: 4px; }

    .confirm-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(6px); display: none; justify-content: center; align-items: center; z-index: 999999; padding: 20px; }
    .confirm-modal-box { background: #ffffff; border-radius: 30px; padding: 40px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15); animation: fadeUp 0.3s ease; }
    @keyframes fadeUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .confirm-icon { font-size: 50px; color: #ff8ba7; margin-bottom: 15px; }
    .confirm-title { font-size: 22px; font-weight: 700; color: #222; margin-bottom: 5px; }
    .confirm-sub { font-size: 14px; color: #888; margin-bottom: 25px; line-height: 1.5; }
    .confirm-buttons { display: flex; gap: 15px; justify-content: center; }
    .btn-modal-cancel { flex: 1; padding: 14px; border: none; border-radius: 50px; background: #eaeaea; color: #555; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Poppins'; }
    .btn-modal-confirm { flex: 1; padding: 14px; border: none; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: white; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Poppins'; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2); }

    /* --- PASSWORD WRAPPER & STRENGTH METER --- */
    .password-wrapper { position: relative; width: 100%; }
    .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888; font-size: 18px; transition: 0.2s; }
    .toggle-password:hover { color: #ff8ba7; }
    .password-strength-meter { width: 100%; height: 6px; background: #eee; border-radius: 10px; margin-top: 8px; overflow: hidden; }
    .strength-bar { height: 100%; width: 0%; border-radius: 10px; transition: width 0.3s ease, background 0.3s ease; }
    .strength-text { font-size: 12px; color: #888; margin-top: 4px; font-weight: 500; transition: color 0.3s; }

    /* --- PENCIL HOVER EFFECT --- */
    .profile-avatar-wrapper { position: relative; width: 80px; height: 80px; cursor: pointer; flex-shrink: 0; }
        .profile-avatar-container {
        width: 80px; height: 80px;
        flex-shrink: 0;
        display: flex;        /* 🚨 ADD THIS LINE */
        align-items: center;  /* 🚨 ADD THIS LINE */
        justify-content: center; /* 🚨 ADD THIS LINE */
    }
    .profile-avatar { 
        width: 80px; height: 80px; border-radius: 50%; 
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); 
        color: white; display: flex; align-items: center; justify-content: center; 
        font-size: 32px; font-weight: 700; flex-shrink: 0; 
    }
    .profile-img { 
        width: 100%; height: 100%; border-radius: 50%; 
        object-fit: cover; display: block; 
    }    .avatar-edit-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 139, 167, 0.8); display: flex; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; border-radius: 50%; color: white; font-size: 24px; }
    .profile-avatar-wrapper:hover .avatar-edit-overlay { opacity: 1; }

    /* --- PICTURE UPLOAD MODAL --- */
    .profile-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(6px); z-index: 999999; display: none; justify-content: center; align-items: center; padding: 20px; }
    .profile-modal-box { background: #ffffff; border-radius: 35px; max-width: 450px; width: 100%; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); position: relative; animation: modalFadeIn 0.3s ease-out; }
    .file-upload-wrapper-pic { border: 2px dashed #e0e0e0; border-radius: 16px; padding: 30px 20px; text-align: center; background: #fafafa; transition: all 0.3s ease; cursor: pointer; }
    .file-upload-wrapper-pic:hover { border-color: #ffc1cc; background: #fff0f5; }
    .file-upload-icon-pic { font-size: 32px; color: #ff8ba7; margin-bottom: 8px; }
    .file-upload-text-pic { font-size: 14px; color: #888; font-weight: 500; }
    .file-upload-text-pic span { color: #ff8ba7; font-weight: 600; }
</style>

<?php if($message): ?>
    <div class="alert-box <?php echo $msg_type; ?>" id="successAlert">
        <i class="fas <?php echo ($msg_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <span><?php echo $message; ?></span>
    </div>
<?php endif; ?>

<div class="profile-header">
    <div class="profile-avatar-wrapper" onclick="openProfilePicModal()">
        <div class="profile-avatar-container" id="profileAvatarDisplay">
            <?php 
            $pic = $user['profile_pic'] ?? '';
            if($pic && file_exists("uploads/profile_pics/" . $pic)): ?>
                <img src="uploads/profile_pics/<?php echo $pic; ?>" class="profile-img" id="profileImg">
            <?php else: ?>
                <div class="profile-avatar" id="profileAvatarLetter">
                    <?php echo strtoupper(substr($firstname, 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="avatar-edit-overlay"><i class="fas fa-pencil-alt"></i></div>
        </div>
    </div>
    <div class="profile-info">
        <h3><?php echo htmlspecialchars($user['name']); ?></h3>
        <p><i class="fas fa-envelope" style="margin-right: 6px; color: #ccc;"></i> <?php echo htmlspecialchars($user['email']); ?></p>
    </div>
</div>

<div class="profile-modal-overlay" id="profilePicModal">
    <div class="profile-modal-box">
        <div class="register-modal-close" onclick="closeProfilePicModal()">&times;</div>
        <div style="text-align: center; padding: 10px 0;">
            <div style="font-size: 50px; color: #ff8ba7; margin-bottom: 10px;">
                <i class="fas fa-camera" style="background: #fff0f5; padding: 15px; border-radius: 50%;"></i>
            </div>
            <h3 style="font-size: 22px; font-weight: 700; color: #222; margin-bottom: 5px;">Update Profile Photo</h3>
            <p style="color: #888; font-size: 14px; margin-bottom: 20px;">Upload a new photo to personalize your account.</p>
            
            <form action="profile.php?tab=profile" method="POST" enctype="multipart/form-data" id="profilePicForm">
                <div class="file-upload-wrapper-pic" id="picFileWrapper" onclick="document.getElementById('picInput').click()">
                    <div class="file-upload-icon-pic" id="picFileIcon"><i class="fas fa-cloud-upload-alt"></i></div>
                    <div class="file-upload-text-pic" id="picFileText">Drag & drop or <span>click to browse</span></div>
                    <input type="file" name="profile_pic" id="picInput" accept="image/*" style="display: none;" onchange="previewProfileImage(event)">
                </div>
                
                <div id="picPreviewContainer" style="display: none; margin: 15px auto; width: 100px; height: 100px; border-radius: 50%; overflow: hidden; border: 3px solid #ffc1cc;">
                    <img id="picPreview" src="" style="width: 100%; height: 100%; object-fit: cover;">
                </div>

                                <div style="display: flex; gap: 10px; margin-top: 15px; justify-content: center; flex-wrap: wrap;">
                    <!-- Cancel Button -->
                    <button type="button" class="btn-modal-cancel" onclick="closeProfilePicModal()" style="flex: 1; min-width: 80px; padding: 14px; border-radius: 50px; background: #eaeaea; color:#555; border:none; font-weight:600; cursor:pointer;">Cancel</button>
                    
                    <!-- Upload Button -->
                    <button type="submit" name="update_profile" class="btn-modal-confirm" style="flex: 1; min-width: 80px; padding: 14px; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color:white; border:none; font-weight:600; cursor:pointer;">Upload & Save</button>

                    <!-- 🚨 NEW: Remove Photo Button -->
                    <button type="button" onclick="removeProfilePhoto()" style="flex: 1; min-width: 80px; padding: 14px; border-radius: 50px; background: #fdeded; color: #d32f2f; border: none; font-weight: 600; cursor: pointer; transition: 0.2s;"
                    onmouseover="this.style.background='#d32f2f'; this.style.color='white';" onmouseout="this.style.background='#fdeded'; this.style.color='#d32f2f';">
                        <i class="fas fa-trash-alt"></i> Remove
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><span class="stat-number"><?php echo $orderCount; ?></span><span class="stat-label">Total Orders</span></div>
    <div class="stat-card"><span class="stat-number"><?php echo $addressCount; ?></span><span class="stat-label">Saved Addresses</span></div>
    <div class="stat-card"><span class="stat-number"><?php echo date('Y'); ?></span><span class="stat-label">Member Since</span></div>
</div>

<form id="giftlyProfileForm" action="profile.php?tab=profile" method="POST">
    <div class="settings-grid">
        <div class="settings-box">
            <h4><i class="fas fa-user-edit" style="color: #ff8ba7; margin-right: 8px;"></i> Personal Information</h4>
            <div class="form-group"><label>First Name</label><input type="text" name="firstname" class="form-input" value="<?php echo htmlspecialchars($firstname); ?>" required></div>
            <div class="form-group"><label>Last Name</label><input type="text" name="lastname" class="form-input" value="<?php echo htmlspecialchars($lastname); ?>" required></div>
            <div class="form-group"><label>Email Address</label><input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
            <div class="form-group"><label>Mobile Number</label><input type="text" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone']); ?>" required placeholder="+63 912 345 6789"></div>
        </div>

        <div class="settings-box">
            <h4><i class="fas fa-shield-alt" style="color: #ff8ba7; margin-right: 8px;"></i> Security & Password</h4>
            <div class="form-group">
                <label>Current Password <span style="color: #d32f2f;">*</span></label>
                <div class="password-wrapper">
                    <input type="password" name="current_password" id="profileCurrentPass" class="form-input" placeholder="Enter current password">
                    <span class="toggle-password" onclick="toggleProfilePassword('profileCurrentPass', this)"><i class="fas fa-eye"></i></span>
                </div>
                <div class="input-hint">Required if changing password.</div>
            </div>

            <div class="form-group">
                <label>New Password</label>
                <div class="password-wrapper">
                    <input type="password" name="new_password" id="profileNewPass" class="form-input" placeholder="Enter new password" oninput="validateProfilePassword()">
                    <span class="toggle-password" onclick="toggleProfilePassword('profileNewPass', this)"><i class="fas fa-eye"></i></span>
                </div>
                <div class="password-strength-meter"><div class="strength-bar" id="profileStrengthBar"></div></div>
                <div class="strength-text" id="profileStrengthText">Use 8 or more letters, numbers and symbols</div>
                <div class="input-hint">Leave blank if you don't want to change it.</div>
            </div>
        </div>
    </div>

    <div style="margin-top: 30px; text-align: center;">
        <button type="button" class="btn-save-pink" onclick="openProfileConfirmModal()" style="width: auto; padding: 16px 60px;">
            <i class="fas fa-save" style="margin-right: 8px;"></i> Save Changes
        </button>
    </div>

    <div class="confirm-modal-overlay" id="profileAlertModal">
        <div class="confirm-modal-box">
            <div class="confirm-icon" style="color: #d32f2f;"><i class="fas fa-exclamation-circle" style="background: #fdeded; padding: 15px; border-radius: 50%;"></i></div>
            <div class="confirm-title">Oops! Missing Fields</div>
            <div class="confirm-sub">Please fill in all required fields before saving your profile.</div>
            <div class="confirm-buttons"><button class="btn-modal-confirm" onclick="closeProfileAlertModal()" style="width: 100%;">Got it!</button></div>
        </div>
    </div>

    <div class="confirm-modal-overlay" id="profileConfirmModal">
        <div class="confirm-modal-box">
            <div class="confirm-icon"><i class="fas fa-user-check" style="background: #fff0f5; padding: 15px; border-radius: 50%;"></i></div>
            <div class="confirm-title">Save Changes?</div>
            <div class="confirm-sub">Are you sure you want to update your profile information?</div>
            <div class="confirm-buttons">
                <button class="btn-modal-cancel" onclick="closeProfileConfirmModal()">Cancel</button>
                <button class="btn-modal-confirm" onclick="submitProfileForm()">Yes, Save</button>
            </div>
        </div>
    </div>
</form>

<script>
    function openProfileConfirmModal() {
        let form = document.getElementById('giftlyProfileForm');
        let requiredFields = form.querySelectorAll('[required]');
        let currentPass = document.querySelector('input[name="current_password"]');
        let newPass = document.querySelector('input[name="new_password"]');
        let isValid = true;

        requiredFields.forEach(function(field) {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = "#d32f2f"; 
                field.style.background = "#fff5f5";
            } else {
                field.style.borderColor = "";
                field.style.background = "";
            }
        });

        if (newPass.value.trim() !== "" && currentPass.value.trim() === "") {
            isValid = false;
            currentPass.style.borderColor = "#d32f2f"; 
            currentPass.style.background = "#fff5f5";
        } else {
            currentPass.style.borderColor = "";
            currentPass.style.background = "";
        }

        if (isValid) {
            document.getElementById('profileConfirmModal').style.display = 'flex';
        } else {
            document.getElementById('profileAlertModal').style.display = 'flex';
        }
    }

    function closeProfileConfirmModal() { 
        document.getElementById('profileConfirmModal').style.display = 'none'; 
    }
    
    function closeProfileAlertModal() {
        document.getElementById('profileAlertModal').style.display = 'none';
        let allInputs = document.getElementById('giftlyProfileForm').querySelectorAll('input');
        allInputs.forEach(function(field) {
            field.style.borderColor = "";
            field.style.background = "";
        });
    }

    function submitProfileForm() {
        document.getElementById('profileConfirmModal').style.display = 'none'; 
        let form = document.getElementById('giftlyProfileForm');
        
        let hiddenInput = document.createElement('input');
        hiddenInput.setAttribute('type', 'hidden');
        hiddenInput.setAttribute('name', 'update_profile');
        hiddenInput.setAttribute('value', '1');
        form.appendChild(hiddenInput);
        
        form.submit();
    }

    document.getElementById('profileConfirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeProfileConfirmModal();
    });
    document.getElementById('profileAlertModal').addEventListener('click', function(e) {
        if (e.target === this) closeProfileAlertModal();
    });

    document.getElementById('giftlyProfileForm').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault(); 
            openProfileConfirmModal(); 
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        let alertBox = document.getElementById('successAlert');
        if(alertBox) {
            setTimeout(function() {
                alertBox.style.transition = "opacity 0.5s";
                alertBox.style.opacity = "0";
                setTimeout(function() {
                    alertBox.style.display = "none";
                }, 500);
            }, 4000);
        }
    });

    function toggleProfilePassword(inputId, iconSpan) {
        let input = document.getElementById(inputId);
        let icon = iconSpan.querySelector('i');
        if(input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function validateProfilePassword() {
        let pass = document.getElementById('profileNewPass').value;
        let strengthBar = document.getElementById('profileStrengthBar');
        let strengthText = document.getElementById('profileStrengthText');

        const hasLength = pass.length >= 8;
        const hasLetter = /[a-zA-Z]/.test(pass);
        const hasNumber = /\d/.test(pass);
        const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(pass);

        if(pass.length === 0) {
            strengthBar.style.width = '0%';
            strengthBar.style.background = '#eee';
            strengthText.innerText = 'Use 8 or more letters, numbers and symbols';
            strengthText.style.color = '#888';
        } else if(!hasLength || !hasLetter) {
            strengthBar.style.width = '30%';
            strengthBar.style.background = '#d32f2f';
            strengthText.innerText = hasLength ? 'Add a letter' : 'Use at least 8 characters';
            strengthText.style.color = '#d32f2f';
        } else if(hasLength && hasLetter && !hasNumber) {
            strengthBar.style.width = '60%';
            strengthBar.style.background = '#f9a825';
            strengthText.innerText = 'Add a number';
            strengthText.style.color = '#f9a825';
        } else if(hasLength && hasLetter && hasNumber && !hasSpecial) {
            strengthBar.style.width = '80%';
            strengthBar.style.background = '#f9a825';
            strengthText.innerText = 'Add a special character';
            strengthText.style.color = '#f9a825';
        } else if(hasLength && hasLetter && hasNumber && hasSpecial) {
            strengthBar.style.width = '100%';
            strengthBar.style.background = '#2e7d32';
            strengthText.innerText = 'Strong password!';
            strengthText.style.color = '#2e7d32';
        }
    }

    function openProfilePicModal() {
        document.getElementById('profilePicModal').style.display = 'flex';
    }
    function closeProfilePicModal() {
        document.getElementById('profilePicModal').style.display = 'none';
        document.getElementById('picPreviewContainer').style.display = 'none';
        document.getElementById('picInput').value = '';
        document.getElementById('picFileText').innerHTML = 'Drag & drop or <span>click to browse</span>';
        document.getElementById('picFileIcon').innerHTML = '<i class="fas fa-cloud-upload-alt"></i>';
        document.getElementById('picFileWrapper').classList.remove('file-selected');
    }
    document.getElementById('profilePicModal').addEventListener('click', function(e) {
        if (e.target === this) closeProfilePicModal();
    });

    function previewProfileImage(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('picPreview').src = e.target.result;
                document.getElementById('picPreviewContainer').style.display = 'block';
                document.getElementById('picFileText').innerHTML = '<i class="fas fa-check-circle" style="color:#ff8ba7;"></i> Ready to upload';
                document.getElementById('picFileIcon').innerHTML = '<i class="fas fa-check-circle" style="color:#ff8ba7; font-size:32px;"></i>';
                document.getElementById('picFileWrapper').classList.add('file-selected');
            }
            reader.readAsDataURL(file);
        }
    }

        /* --- REMOVE PROFILE PHOTO --- */
    function removeProfilePhoto() {
        if(confirm("Are you sure you want to remove your profile picture?")) {
            // Create a hidden form and submit it to delete the photo
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = 'profile.php?tab=profile';
            
            // Hidden input to tell PHP to delete the picture
            let hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'remove_profile_pic';
            hiddenInput.value = '1';
            
            // Hidden input to trigger the update (so PHP knows to run)
            let updateInput = document.createElement('input');
            updateInput.type = 'hidden';
            updateInput.name = 'update_profile';
            updateInput.value = '1';
            
            form.appendChild(hiddenInput);
            form.appendChild(updateInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>