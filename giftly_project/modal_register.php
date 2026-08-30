<!-- REGISTER MODAL OVERLAY -->
<div class="register-modal-overlay" id="registerModal">
    <div class="register-modal-box">
        <div class="register-modal-close" onclick="closeRegisterModal()">&times;</div>
        
        <div class="register-modal-centered">
            <div class="register-modal-header">
                <div style="font-size: 50px; color: #ff8ba7; margin-bottom: 10px;">
                    <i class="fas fa-user-plus" style="background: #fff0f5; padding: 15px; border-radius: 50%;"></i>
                </div>
                <h2 class="register-modal-title">Create Account</h2>
                <p class="register-modal-sub">Join us and start gifting!</p>
            </div>

            <!-- PHP BACKEND ERROR/Success MESSAGES -->
            <?php 
            $reg_msg = '';
            $reg_type = '';
            if (isset($_GET['reg_msg'])) {
                if ($_GET['reg_msg'] == 'error') {
                    $reg_msg = $_GET['reg_error'] ?? 'Registration failed. Please try again.';
                    $reg_type = 'error';
                } elseif ($_GET['reg_msg'] == 'success') {
                    $reg_msg = 'Account created successfully! Please log in.';
                    $reg_type = 'success';
                }
            }
            if (!empty($reg_msg)): ?>
                <div class="reg-alert <?php echo $reg_type; ?>">
                    <i class="fas <?php echo ($reg_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>" style="margin-right: 8px;"></i> 
                    <?php echo $reg_msg; ?>
                </div>
            <?php endif; ?>

<form action="register.php" method="POST">                <input type="hidden" name="redirect_to" value="<?php echo isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php'; ?>">
                
                <!-- ROW 1: First & Last Name -->
                <div class="reg-row">
                    <div class="reg-input-group">
                        <label>First Name</label>
                        <input type="text" id="firstname" name="firstname" class="reg-input" placeholder="Enter your first name" required oninput="validateField('firstname')">
                        <div class="error-msg" id="firstname_error">First name is required.</div>
                    </div>
                    <div class="reg-input-group">
                        <label>Last Name</label>
                        <input type="text" id="lastname" name="lastname" class="reg-input" placeholder="Enter your last name" required oninput="validateField('lastname')">
                        <div class="error-msg" id="lastname_error">Last name is required.</div>
                    </div>
                </div>
                
                <!-- ROW 2: Email & Phone (Stacked) -->
                <div class="reg-input-group">
                    <label>Email Address</label>
                    <input type="email" id="email" name="email" class="reg-input" placeholder="Enter your email address" required oninput="validateField('email')">
                    <div class="error-msg" id="email_error">Please enter a valid email address.</div>
                </div>
                
               <div class="reg-input-group">
    <label>Phone Number</label>
    <input type="tel" id="phone" name="phone" class="reg-input" placeholder="Enter your 11-digit mobile number" required oninput="validateField('phone')">
    <div class="error-msg" id="phone_error">Please enter a valid 11-digit phone number.</div>
</div>
                
                <!-- ROW 3: Password & Confirm Password (Side by Side) -->
                <div class="reg-row">
                    <div class="reg-input-group password-group">
                        <label>Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="regPass" name="password" class="reg-input" placeholder="Create a password" required oninput="validatePassword()">
                            <span class="toggle-password" onclick="togglePasswordVisibility('regPass', this)">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <!-- Password Strength Meter -->
                        <div class="password-strength-meter">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-text" id="strengthText">Use 8 or more letters, numbers and symbols</div>
                        <div class="error-msg" id="password_error">Password must be at least 8 characters, include a letter, a number, and a special character.</div>
                    </div>
                    <div class="reg-input-group password-group">
                        <label>Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="regConfirmPass" name="confirm_password" class="reg-input" placeholder="Confirm your password" required oninput="validateConfirmPassword()">
                            <span class="toggle-password" onclick="togglePasswordVisibility('regConfirmPass', this)">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <div class="error-msg" id="confirm_error">Passwords do not match.</div>
                    </div>
                </div>
                
                <div class="reg-checkbox-group">
                    <input type="checkbox" id="termsCheck" name="terms" required onchange="validateTerms()">
                    <label for="termsCheck">
                        I agree to the <a href="#" style="color:#ff8ba7;">Terms and Conditions</a> and <a href="#" style="color:#ff8ba7;">Privacy Policy</a>.
                    </label>
                    <div class="error-msg" id="terms_error">Please agree to the Terms and Conditions.</div>
                </div>
                
<button type="submit" name="register" class="reg-submit-btn">Create Account</button>                <div class="reg-divider">or</div>

                <?php $g_slot_id = 'gbtn_register'; include 'google_signin.php'; ?>

                <div class="reg-login-link">
                    Already have an account? <a href="javascript:void(0)" onclick="closeRegisterModal(); setTimeout(openLoginModal, 300);">Log In</a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* --- MODAL OVERLAY --- */
    .register-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(6px);
        z-index: 999999;
        display: none;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }
    .register-modal-box {
        background: #ffffff;
        border-radius: 35px;
        max-width: 600px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        position: relative;
        padding: 40px 35px;
        animation: modalFadeIn 0.3s ease-out;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .register-modal-box::-webkit-scrollbar { display: none; }
    @keyframes modalFadeIn {
        from { transform: scale(0.95) translateY(20px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .register-modal-close {
        position: absolute; top: 15px; right: 20px;
        font-size: 24px; color: #888; cursor: pointer; transition: 0.2s;
    }
    .register-modal-close:hover { color: #ff8ba7; transform: rotate(90deg); }

    /* --- CENTERED LAYOUT --- */
    .register-modal-centered {
        max-width: 480px;
        margin: 0 auto;
        text-align: center;
    }
    .register-modal-header { margin-bottom: 20px; }
    .register-modal-title { font-size: 26px; font-weight: 700; color: #222; margin-bottom: 5px; }
    .register-modal-sub { font-size: 14px; color: #888; }

    /* --- FORM STYLES --- */
    .reg-row { display: flex; gap: 15px; }
    .reg-input-group { flex: 1; margin-bottom: 15px; text-align: left; }
    .reg-input-group label { display: block; font-size: 14px; font-weight: 600; color: #444; margin-bottom: 6px; }
    .reg-input {
        width: 100%; padding: 14px 16px;
        border: 1.5px solid #eee; border-radius: 16px;
        font-size: 14px; font-family: 'Poppins';
        background: #fafafa; transition: 0.3s; outline: none;
    }
    .reg-input:focus { border-color: #ffc1cc; background: #fff; box-shadow: 0 0 0 4px rgba(255, 193, 204, 0.1); }
    
    /* --- PASSWORD WRAPPER (For the Eye Icon) --- */
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

    /* --- PASSWORD STRENGTH METER --- */
    .password-strength-meter {
        width: 100%; height: 6px; background: #eee; border-radius: 10px;
        margin-top: 8px; overflow: hidden;
    }
    .strength-bar {
        height: 100%; width: 0%; border-radius: 10px;
        transition: width 0.3s ease, background 0.3s ease;
    }
    .strength-text {
        font-size: 12px; color: #888; margin-top: 4px; font-weight: 500; transition: color 0.3s;
    }

    /* --- ERROR MESSAGES --- */
    .error-msg {
        color: #d32f2f; font-size: 12px; font-weight: 500; margin-top: 4px;
        display: none;
    }
    .error-msg.visible { display: block; }
    .reg-alert { padding: 12px; border-radius: 16px; margin-bottom: 15px; text-align: center; font-weight: 500; font-size: 14px; border: 1px solid; }
    .reg-alert.success { background: #e8f5e9; color: #2e7d32; border-color: #a5d6a7; }
    .reg-alert.error { background: #fdeded; color: #d32f2f; border-color: #ffc1cc; }

    /* --- CHECKBOX --- */
    .reg-checkbox-group { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 20px; font-size: 14px; color: #555; text-align: left; }
    .reg-checkbox-group input[type="checkbox"] { width: 18px; height: 18px; accent-color: #ff8ba7; margin-top: 2px; cursor: pointer; }
    .reg-checkbox-group label { cursor: pointer; line-height: 1.4; }

    /* --- BUTTONS --- */
    .reg-submit-btn {
        width: 100%; padding: 16px; border: none; border-radius: 50px;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; font-size: 16px; font-weight: 600; cursor: pointer;
        transition: 0.2s; font-family: 'Poppins';
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
    }
    .reg-submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4);
    }
    
    .reg-divider { text-align: center; color: #ccc; font-size: 14px; margin: 20px 0; font-weight: 500; }
    .reg-login-link { text-align: center; font-size: 14px; color: #666; }
    .reg-login-link a { color: #ff8ba7; font-weight: 600; text-decoration: none; cursor: pointer; }
    .reg-login-link a:hover { text-decoration: underline; }

    /* --- RESPONSIVE --- */
    @media (max-width: 600px) {
        .reg-row { flex-direction: column; gap: 0; }
        .register-modal-box { padding: 30px 20px; }
        .register-modal-centered { max-width: 100%; }
    }
</style>

<script>
        /* --- VALIDATION FUNCTIONS --- */
    function validateField(fieldId) {
        let input = document.getElementById(fieldId);
        let error = document.getElementById(fieldId + '_error');
        
        if(fieldId === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if(!emailRegex.test(input.value)) {
                error.classList.add('visible');
            } else {
                error.classList.remove('visible');
            }
        } else if(fieldId === 'phone') {
            // STRICT: Check for letters and length
            const hasLetter = /[a-zA-Z]/.test(input.value);
            const phoneClean = input.value.replace(/\D/g, '');
            
            if(hasLetter) {
                error.innerText = 'Phone numbers cannot contain letters.';
                error.classList.add('visible');
            } else if(phoneClean.length !== 11) {
                error.innerText = 'Please enter a valid 11-digit phone number.';
                error.classList.add('visible');
            } else {
                error.classList.remove('visible');
            }
        } else {
            if(input.value.trim() === '') {
                error.classList.add('visible');
            } else {
                error.classList.remove('visible');
            }
        }
    }

    /* --- PASSWORD VALIDATION & STRENGTH (SECURE LOGIC) --- */
    function validatePassword() {
        let pass = document.getElementById('regPass').value;
        let error = document.getElementById('password_error');
        let strengthBar = document.getElementById('strengthBar');
        let strengthText = document.getElementById('strengthText');

        const hasLength = pass.length >= 8;
        const hasLetter = /[a-zA-Z]/.test(pass); // Must have a LETTER
        const hasNumber = /\d/.test(pass);
        const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(pass);

        // --- STRENGTH LOGIC ---
        if(pass.length === 0) {
            // Empty
            strengthBar.style.width = '0%';
            strengthBar.style.background = '#eee';
            strengthText.innerText = 'Use 8 or more letters, numbers and symbols';
            strengthText.style.color = '#888';
        } else if(!hasLength || !hasLetter) {
            // Too short or missing a letter
            strengthBar.style.width = '30%';
            strengthBar.style.background = '#d32f2f'; // Red
            strengthText.innerText = hasLength ? 'Add a letter' : 'Use at least 8 characters';
            strengthText.style.color = '#d32f2f';
        } else if(hasLength && hasLetter && !hasNumber) {
            // Missing number
            strengthBar.style.width = '60%';
            strengthBar.style.background = '#f9a825'; // Yellow
            strengthText.innerText = 'Add a number';
            strengthText.style.color = '#f9a825';
        } else if(hasLength && hasLetter && hasNumber && !hasSpecial) {
            // Missing special character
            strengthBar.style.width = '80%';
            strengthBar.style.background = '#f9a825'; // Yellow
            strengthText.innerText = 'Add a special character';
            strengthText.style.color = '#f9a825';
        } else if(hasLength && hasLetter && hasNumber && hasSpecial) {
            // ALL CRITERIA MET!
            strengthBar.style.width = '100%';
            strengthBar.style.background = '#2e7d32'; // Green
            strengthText.innerText = 'Strong password!';
            strengthText.style.color = '#2e7d32';
        }

        // Show error if they typed something but didn't meet secure criteria
        if(pass.length > 0 && (!hasLength || !hasLetter || !hasNumber || !hasSpecial)) {
            error.classList.add('visible');
        } else {
            error.classList.remove('visible');
        }
    }

    function validateConfirmPassword() {
        let pass = document.getElementById('regPass').value;
        let confirm = document.getElementById('regConfirmPass').value;
        let error = document.getElementById('confirm_error');

        if(confirm.length > 0 && pass !== confirm) {
            error.classList.add('visible');
        } else {
            error.classList.remove('visible');
        }
    }

    function validateTerms() {
        let check = document.getElementById('termsCheck');
        let error = document.getElementById('terms_error');
        if(!check.checked) {
            error.classList.add('visible');
        } else {
            error.classList.remove('visible');
        }
    }

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

    /* --- FINAL FORM VALIDATION ON SUBMIT --- */
    function validateForm() {
        let isValid = true;
        ['firstname', 'lastname', 'email', 'phone'].forEach(id => {
            validateField(id);
            if(document.getElementById(id + '_error').classList.contains('visible')) isValid = false;
        });

        validatePassword();
        if(document.getElementById('password_error').classList.contains('visible')) isValid = false;

        validateConfirmPassword();
        if(document.getElementById('confirm_error').classList.contains('visible')) isValid = false;

        validateTerms();
        if(document.getElementById('terms_error').classList.contains('visible')) isValid = false;

        if(!isValid) {
            let globalError = document.createElement('div');
            globalError.className = 'reg-alert error';
            globalError.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right:8px;"></i> Please complete all required fields.';
            
            let form = document.getElementById('registerForm');
            let existing = form.querySelector('.global-error');
            if(existing) existing.remove();
            form.prepend(globalError);
            setTimeout(() => { if(globalError) globalError.remove(); }, 4000);
            return false;
        }
        return true;
    }

    /* --- MODAL CONTROLS --- */
       function openRegisterModal() {
        document.getElementById('registerModal').style.display = 'flex';
    }
    function closeRegisterModal(forceClose = false) {
        document.getElementById('registerModal').style.display = 'none';
        // If forced closed, remove the URL parameter immediately
        if(forceClose || window.location.search.includes('reg_msg')) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
    document.getElementById('registerModal').addEventListener('click', function(e) {
        if (e.target === this) closeRegisterModal();
    });

       window.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('reg_msg') && urlParams.get('reg_msg') === 'success') {
            // Force close the Register modal permanently
            closeRegisterModal(true); 
            // Immediately open the Login modal
            setTimeout(openLoginModal, 100);
        } else if (urlParams.get('reg_msg') && urlParams.get('reg_msg') === 'error') {
            // If there's an error, show the Register modal so they can fix it
            openRegisterModal();
        }
    });
</script>