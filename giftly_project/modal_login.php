<?php
// Clear the 'just_logged_in' session flag immediately if it exists
if (isset($_SESSION['just_logged_in'])) {
    unset($_SESSION['just_logged_in']);
}
?>

<!-- LOGIN MODAL OVERLAY -->
<div class="login-modal-overlay" id="loginModal">
    <div class="login-modal-box">
        <div class="login-modal-close" onclick="closeLoginModal(false)">&times;</div>
        
        <div class="login-modal-split">
            <!-- LEFT: Login Form -->
            <div class="login-modal-form">
                <h2 class="login-modal-title">Welcome Back</h2>
                <p class="login-modal-sub">Sign in to access your account.</p>

                <!-- SUCCESS ALERT BLOCK -->
                <?php if (isset($_GET['reg_msg']) && $_GET['reg_msg'] == 'success'): ?>
                    <div style="background: #e8f5e9; color: #2e7d32; padding: 12px 15px; border-radius: 16px; margin-bottom: 15px; border: 1px solid #a5d6a7; text-align: center; font-weight: 500; font-size: 14px;">
                        <i class="fas fa-check-circle" style="margin-right: 8px;"></i> Account created successfully! Please log in.
                    </div>
                <?php endif; ?>

                <!-- ERROR ALERT BLOCK -->
<?php 
$error_msg = '';
if (isset($_GET['login_error'])) {
    if ($_GET['login_error'] == 'incorrect') {
        $error_msg = 'Incorrect email or password. Please try again.';
    } elseif ($_GET['login_error'] == 'notfound') {
        $error_msg = 'Email address not found. Please check your email.';
    }
}
if (!empty($error_msg)): ?>
    <div style="background: #fdeded; color: #d32f2f; padding: 12px 15px; border-radius: 16px; margin-bottom: 15px; border: 1px solid #ffc1cc; text-align: center; font-weight: 500; font-size: 14px;">
        <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> <?php echo $error_msg; ?>
    </div>
<?php endif; ?>

                <!-- SINGLE CLEAN FORM -->
                <form action="login.php" method="POST">
<input type="hidden" name="redirect_to" value="<?php 
    // Get the current page without query string
    $current_page = basename($_SERVER['PHP_SELF']);
    // If we're on shop.php or any page with login_error, stay on that page
    if (strpos($_SERVER['REQUEST_URI'], 'login_error') !== false) {
        // Remove the error parameter
        $clean_url = strtok($_SERVER['REQUEST_URI'], '?');
        echo $clean_url;
    } else {
        echo $_SERVER['REQUEST_URI'];
    }
?>">                    
                    <div class="login-input-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="login-input" placeholder="Enter your email" required>
                    </div>
                    
                    <div class="login-input-group password-group">
                        <label>Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="loginPass" name="password" class="login-input" placeholder="Enter your password" required>
                            <span class="toggle-password" onclick="togglePasswordVisibility('loginPass', this)">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                    </div>
                    
<a href="javascript:void(0)" onclick="openForgotModal()" class="login-forgot-link">Forgot your password?</a>                    
                    <button type="submit" name="login" class="login-submit-btn">Log in</button>
                    
                    <div class="login-divider">or</div>
                    
                    <div class="login-register-link">
                        Don't have an account? <a href="javascript:void(0)" onclick="closeLoginModal(false); setTimeout(openRegisterModal, 300);">Sign up</a>
                    </div>
                </form>
            </div>

            <!-- RIGHT: Promotional Art -->
            <div class="login-modal-art">
                <div style="font-size: 80px; color: #ff8ba7; margin-bottom: 15px;">
                    <i class="fas fa-gift"></i>
                </div>
                <h3 style="font-size: 22px; font-weight: 700; color: #222;">Login Instantly</h3>
                <p style="font-size: 14px; color: #666; line-height: 1.5; max-width: 250px; margin: 0 auto;">
                    Sign in to start building your perfect gift box.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- LOGIN SUCCESS MODAL -->
<div class="confirm-modal-overlay" id="loginSuccessModal">
    <div class="confirm-modal-box">
        <div class="confirm-icon"><i class="fas fa-check-circle" style="color: #66bb6a;"></i></div>

        <?php 
        // Check if the logged-in user is an Admin
        $is_admin = false;
        if (isset($_SESSION['user_id'])) {
            $check = $conn->query("SELECT role FROM users WHERE id = ".$_SESSION['user_id']);
            if ($check) {
                $data = $check->fetch_assoc();
                if ($data['role'] == 'admin') {
                    $is_admin = true;
                }
            }
        }
        
        if ($is_admin): ?>
            <!-- ADMIN SUCCESS TEXT -->
            <div class="confirm-title" style="color: #222;">Login Successful!</div>
            <div class="confirm-sub">Welcome back, Admin! Everything is ready for you.</div>
            <div class="confirm-buttons">
                <button class="btn-modal-cancel" onclick="closeLoginSuccessModal()" style="flex: 1;">Continue</button>
            </div>
        <?php else: ?>
            <!-- CUSTOMER SUCCESS TEXT -->
            <div class="confirm-title" style="color: #222;">Login Successful</div>
            <div class="confirm-sub">You have successfully signed into your account. Ready to make someone’s day a little sweeter?</div>
            <div class="confirm-buttons">
                <button class="btn-modal-cancel" onclick="closeLoginSuccessModal()" style="flex: 1;">Start Exploring</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- INCLUDE THE FORGOT PASSWORD MODAL HERE -->
<?php include 'modal_forgot_password.php'; ?>

<!-- INCLUDE THE RESET PASSWORD MODAL HERE -->
<?php include 'modal_reset_password.php'; ?>

<style>
    /* --- LOGIN MODAL STYLES --- */
    .login-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(6px);
        z-index: 999998;
        display: none;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }
    .login-modal-box {
        background: #ffffff;
        border-radius: 35px;
        max-width: 800px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        position: relative;
        padding: 40px;
        animation: modalFadeIn 0.3s ease-out;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .login-modal-box::-webkit-scrollbar { display: none; }
    @keyframes modalFadeIn {
        from { transform: scale(0.95) translateY(20px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .login-modal-close {
        position: absolute; top: 20px; right: 25px;
        font-size: 28px; color: #888; cursor: pointer; transition: 0.2s;
    }
    .login-modal-close:hover { color: #ff8ba7; transform: rotate(90deg); }

    /* --- SPLIT LAYOUT --- */
    .login-modal-split {
        display: flex;
        gap: 40px;
        align-items: center;
    }
    .login-modal-form { flex: 1; }
    .login-modal-art {
        flex: 1;
        background: #fff0f5;
        border-radius: 24px;
        padding: 40px 20px;
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        min-height: 320px;
    }

    /* --- LOGIN SUCCESS MODAL STYLES --- */
    .confirm-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(6px);
        display: none; justify-content: center; align-items: center;
        z-index: 999999;
    }
    .confirm-modal-box {
        background: #ffffff; border-radius: 30px; padding: 40px;
        max-width: 400px; width: 90%; text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        animation: fadeUp 0.3s ease;
    }
    @keyframes fadeUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .confirm-icon { font-size: 50px; margin-bottom: 15px; }
    .confirm-title { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
    .confirm-sub { font-size: 14px; color: #888; margin-bottom: 25px; line-height: 1.5; }
    .confirm-buttons { display: flex; gap: 15px; justify-content: center; }
    .btn-modal-cancel {
        flex: 1; padding: 14px; border: none; border-radius: 50px;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        font-weight: 600; font-size: 15px; color: #fff;
        cursor: pointer; transition: 0.2s; font-family: 'Poppins';
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
    }
    .btn-modal-cancel:hover { 
        background: linear-gradient(135deg, #ff8ba7 0%, #FEA5B6 100%); 
        transform: translateY(-2px); 
        box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4);
    }

    /* --- FORM STYLES --- */
    .login-modal-title { font-size: 28px; font-weight: 700; color: #222; margin-bottom: 5px; text-align: center; }
    .login-modal-sub { font-size: 14px; color: #888; margin-bottom: 25px; text-align: center; }
    
    .login-input-group { margin-bottom: 18px; }
    .login-input-group label { display: block; font-size: 14px; font-weight: 600; color: #444; margin-bottom: 6px; }
    .login-input {
        width: 100%; padding: 14px 16px;
        border: 1.5px solid #eee; border-radius: 16px;
        font-size: 14px; font-family: 'Poppins';
        background: #fafafa; transition: 0.3s; outline: none;
    }
    .login-input:focus { border-color: #ffc1cc; background: #fff; box-shadow: 0 0 0 4px rgba(255, 193, 204, 0.1); }
    
    .password-group { position: relative; }
    .password-wrapper {
        position: relative;
        width: 100%;
    }
    .toggle-password {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #888;
        font-size: 18px;
        transition: 0.2s;
    }
    .toggle-password:hover { color: #ff8ba7; }

    .login-forgot-link { display: block; text-align: right; font-size: 13px; color: #ff8ba7; margin-bottom: 20px; text-decoration: none; }
    .login-forgot-link:hover { text-decoration: underline; }
    
    .login-submit-btn {
        width: 100%; padding: 16px; border: none; border-radius: 50px;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; font-size: 16px; font-weight: 600; cursor: pointer;
        transition: 0.2s; font-family: 'Poppins';
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
    }
    .login-submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4);
    }
    
    .login-divider { text-align: center; color: #ccc; font-size: 14px; margin: 20px 0; font-weight: 500; }
    
    .login-register-link { text-align: center; font-size: 14px; color: #666; }
    .login-register-link a { color: #ff8ba7; font-weight: 600; text-decoration: none; }
    .login-register-link a:hover { text-decoration: underline; }

    /* --- RESPONSIVE --- */
    @media (max-width: 700px) {
        .login-modal-split { flex-direction: column-reverse; gap: 20px; }
        .login-modal-art { min-height: 150px; padding: 30px 20px; }
        .login-modal-art i { font-size: 50px !important; }
    }
</style>

<script>
/* --- LOGIN MODAL CONTROLS --- */
function openLoginModal() {
    document.getElementById('loginModal').style.display = 'flex';
    // Don't clear the error when opening
}

function closeLoginModal(clearError = false) {
    document.getElementById('loginModal').style.display = 'none';
    if (clearError) {
        if(window.location.search.includes('login_error')) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
}

document.getElementById('loginModal').addEventListener('click', function(e) {
    if (e.target === this) closeLoginModal(false);
});

// 🚨 AUTO-OPEN MODAL IF THERE WAS A LOGIN ERROR
function checkForLoginError() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('login_error')) {
        // Open the modal immediately
        openLoginModal();
        return true;
    }
    return false;
}

// Check on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    checkForLoginError();
});

// Check when the page fully loads
window.addEventListener('load', function() {
    checkForLoginError();
});

// 🚨 Also check after any navigation (for single-page app behavior)
window.addEventListener('popstate', function() {
    checkForLoginError();
});

// 🚨 Clear error only when user manually closes or successfully logs in
function clearLoginError() {
    if(window.location.search.includes('login_error')) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

// Call this when user successfully logs in (from login.php redirect)
// Or when user manually closes the modal after seeing the error

    /* --- TOGGLE PASSWORD VISIBILITY --- */
    function togglePasswordVisibility(inputId, iconSpan) {
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

    /* --- SUCCESS MODAL CONTROLS --- */
    function openLoginSuccessModal() {
        document.getElementById('loginSuccessModal').style.display = 'flex';
    }
    function closeLoginSuccessModal() {
        document.getElementById('loginSuccessModal').style.display = 'none';
    }
    document.getElementById('loginSuccessModal').addEventListener('click', function(e) {
        if (e.target === this) closeLoginSuccessModal();
    });

    // 🚀 SHOW MODAL ON FRESH LOGIN OR ADMIN DASHBOARD ENTRY
    <?php 
    $trigger_choice = false;
    $trigger_success = false;

    // 1. Check for fresh login from login.php
    if (isset($_SESSION['fresh_login_modal']) && $_SESSION['fresh_login_modal'] === true) {
        $trigger_choice = true;
        unset($_SESSION['fresh_login_modal']);
    }
    
    // 2. Check for URL trigger from clicking Dashboard button
    // This is the most reliable way to trigger it
    if (isset($_GET['show_success']) && $_GET['show_success'] == '1') {
        $trigger_success = true;
    }
    
    if ($trigger_choice): ?>
        document.addEventListener('DOMContentLoaded', function() {
            <?php 
            // Check if the user is an Admin
            $is_admin = false;
            if (isset($_SESSION['user_id'])) {
                $check = $conn->query("SELECT role FROM users WHERE id = ".$_SESSION['user_id']);
                if ($check) {
                    $data = $check->fetch_assoc();
                    if ($data['role'] == 'admin') {
                        $is_admin = true;
                    }
                }
            }
            
            if ($is_admin): ?>
                // FRESH LOGIN: Show the Admin Choice Modal
                setTimeout(openAdminChoiceModal, 300);
            <?php else: ?>
                // FRESH LOGIN: Show the Customer Login Success Modal
                setTimeout(openLoginSuccessModal, 300);
            <?php endif; ?>
        });
    <?php endif; ?>

    <?php if ($trigger_success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // DASHBOARD RE-ENTRY: Show the Admin Login Success Modal
            setTimeout(openLoginSuccessModal, 300);
        });
    <?php endif; ?>

    /* --- FORGOT PASSWORD MODAL CONTROLS --- */
    function openForgotModal() {
        closeLoginModal(); // Close login modal first
        setTimeout(() => {
            document.getElementById('forgotPasswordModal').style.display = 'flex';
            document.getElementById('forgotAlertBox').innerHTML = ''; // Clear old alerts
        }, 300);
    }

    function closeForgotModal() {
        document.getElementById('forgotPasswordModal').style.display = 'none';
    }

    document.getElementById('forgotPasswordModal').addEventListener('click', function(e) {
        if (e.target === this) closeForgotModal();
    });

            /* --- AJAX FORGOT PASSWORD SUBMISSION --- */
    function submitForgotPassword(e) {
        e.preventDefault(); // Stop page reload
        
        let form = document.getElementById('forgotPasswordForm');
        let formData = new FormData(form);
        let alertBox = document.getElementById('forgotAlertBox');
        let submitBtn = document.getElementById('forgotSubmitBtn');
        
        // 🚨 CHANGE BUTTON STATE
        let originalText = submitBtn.innerText;
        submitBtn.innerText = "Sending...";
        submitBtn.disabled = true;
        submitBtn.style.opacity = "0.8";

        fetch('forgot_password_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // 🚨 RESTORE BUTTON STATE
            submitBtn.innerText = originalText;
            submitBtn.disabled = false;
            submitBtn.style.opacity = "1";
            
            if(data.success) {
                // 🚨 SHOW THE GREEN BOX HERE
                                                               alertBox.innerHTML = `
                    <div style="background: #e8f5e9; color: #2e7d32; padding: 15px 15px; border-radius: 16px; margin-bottom: 18px; border: 1px solid #a5d6a7; text-align: center; font-weight: 500; font-size: 14px;">
                        <i class="fas fa-check-circle" style="margin-right: 8px;"></i> ${data.message}
                        <br><br>
                        <button onclick="swapToResetModal('${data.link}')" style="display: inline-block; background: #d1d1d1; color: #333; padding: 12px 30px; border: none; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.05);" 
                        onmouseover="this.style.background='#b8b8b8'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.1)';" 
                        onmouseout="this.style.background='#d1d1d1'; this.style.transform='translateY(0px)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.05)';">
                            <i class="fas fa-arrow-right" style="margin-right: 6px;"></i> Reset Password
                        </button>
                    </div>
                `;
                form.reset(); 
                   } else {
            // 🚨 THIS SHOWS THE RED ERROR BOX
            alertBox.innerHTML = `
                <div style="background: #fdeded; color: #d32f2f; padding: 12px; border-radius: 16px; margin-bottom: 18px; border: 1px solid #ffc1cc; text-align: center; font-weight: 500; font-size: 14px;">
                    <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> ${data.message}
                </div>
            `;
        }
        })
        .catch(error => {
            submitBtn.innerText = originalText;
            submitBtn.disabled = false;
            submitBtn.style.opacity = "1";
            alertBox.innerHTML = `<div style="color: #d32f2f; margin-bottom: 10px;">Something went wrong. Please try again.</div>`;
        });
    }

    /* ========================================================== */
    /* 🚨 THESE WERE THE MISSING FUNCTIONS! 🚨                    */
    /* ========================================================== */

    /* --- RESET PASSWORD MODAL CONTROLS --- */
    function openResetModal() {
        document.getElementById('resetPasswordModal').style.display = 'flex';
    }

    function closeResetModal() {
        document.getElementById('resetPasswordModal').style.display = 'none';
    }

    document.getElementById('resetPasswordModal').addEventListener('click', function(e) {
        if (e.target === this) closeResetModal();
    });

    /* --- AJAX RESET PASSWORD SUBMISSION --- */
    function submitResetPassword(e) {
        e.preventDefault();
        
        let form = document.getElementById('resetPasswordForm');
        let formData = new FormData(form);
        let alertBox = document.getElementById('resetAlertBox');
        let submitBtn = document.getElementById('resetSubmitBtn');
        
        submitBtn.innerText = "Resetting...";
        submitBtn.disabled = true;

        fetch('reset_password_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alertBox.innerHTML = `
                    <div style="background: #e8f5e9; color: #2e7d32; padding: 12px 15px; border-radius: 16px; margin-bottom: 15px; border: 1px solid #a5d6a7; text-align: center; font-weight: 500; font-size: 14px;">
                        <i class="fas fa-check-circle" style="margin-right: 8px;"></i> ${data.message}
                    </div>
                `;
                form.reset();
                submitBtn.style.display = 'none'; // Hide button after success
            } else {
                alertBox.innerHTML = `
                    <div style="background: #fdeded; color: #d32f2f; padding: 12px; border-radius: 16px; margin-bottom: 15px; text-align: center; font-weight: 500; font-size: 14px;">
                        <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> ${data.message}
                    </div>
                `;
                submitBtn.innerText = "Reset Password";
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            alertBox.innerHTML = `<div style="color: #d32f2f; margin-bottom: 10px;">Something went wrong. Please try again.</div>`;
            submitBtn.innerText = "Reset Password";
            submitBtn.disabled = false;
        });
    }

        /* --- SWAP TO RESET MODAL (NO PAGE RELOAD) --- */
    function swapToResetModal(link) {
        // 1. Extract the token from the link URL
        let token = link.split('=')[1];
        
        // 2. Close the Forgot Modal
        closeForgotModal();
        
        // 3. Wait a tiny bit for the animation, then inject the token
        setTimeout(() => {
            // Inject the token into the hidden input of the Reset Modal
            document.getElementById('resetTokenInput').value = token;
            
            // 4. Open the Reset Modal
            openResetModal();
        }, 300);
    }
</script>