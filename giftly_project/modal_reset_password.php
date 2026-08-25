<!-- modal_reset_password.php -->
<div class="reset-modal-overlay" id="resetPasswordModal">
    <div class="reset-modal-box">
        <div class="reset-modal-close" onclick="closeResetModal()">&times;</div>
        
        <div class="reset-modal-split">
            <!-- LEFT: Reset Password Form -->
            <div class="reset-modal-form">
                <div style="text-align: center; margin-bottom: 15px;">
                    <div style="font-size: 40px; color: #ff8ba7; margin-bottom: 5px;">
                        <i class="fas fa-key" style="background: #fff0f5; padding: 12px; border-radius: 50%;"></i>
                    </div>
                    <h2 class="reset-modal-title">Reset Password</h2>
                    <p class="reset-modal-sub">Enter your new password below.</p>
                </div>

                <!-- SUCCESS / ERROR ALERTS -->
                <div id="resetAlertBox"></div>

<form id="resetPasswordForm" onsubmit="submitResetPassword(event)">
    <!-- Hidden token input -->
    <input type="hidden" name="token" id="resetTokenInput" value="">
                    
    <div class="reset-input-group">
        <label>New Password</label>
        <input type="password" name="password" class="reset-input" placeholder="Enter new password" required minlength="8">
    </div>
                    <div class="reset-input-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="reset-input" placeholder="Confirm new password" required minlength="8">
                    </div>
                    
                    <button type="submit" class="reset-submit-btn" id="resetSubmitBtn">Reset Password</button>
                    
                    <div class="reset-divider">or</div>
                    
                    <div class="reset-register-link">
                        <a href="javascript:void(0)" onclick="closeResetModal(); setTimeout(openLoginModal, 300);">Back to Login</a>
                    </div>
                </form>
            </div>

            <!-- RIGHT: Promotional Art -->
            <div class="reset-modal-art">
                <div style="font-size: 60px; color: #ff8ba7; margin-bottom: 15px;">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 style="font-size: 20px; font-weight: 700; color: #222;">Secure Your Account</h3>
                <p style="font-size: 14px; color: #666; line-height: 1.5; max-width: 200px; margin: 0 auto;">
                    Choose a strong, unique password to keep your account safe.
                </p>
            </div>
        </div>
    </div>
</div>

<style>
    /* --- RESET MODAL OVERRIDES --- */
    .reset-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(6px);
        z-index: 999998; display: none; justify-content: center; align-items: center; padding: 20px;
    }
    .reset-modal-box {
        background: #ffffff; border-radius: 35px; max-width: 800px; width: 100%;
        max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        position: relative; padding: 40px; animation: modalFadeIn 0.3s ease-out;
    }
    .reset-modal-close {
        position: absolute; top: 20px; right: 25px; font-size: 28px; color: #888; 
        cursor: pointer; transition: 0.2s;
    }
    .reset-modal-close:hover { color: #ff8ba7; transform: rotate(90deg); }

    .reset-modal-split { display: flex; gap: 40px; align-items: center; }
    .reset-modal-form { flex: 1; }
    .reset-modal-art {
        flex: 1; background: #fff0f5; border-radius: 24px; padding: 40px 20px;
        text-align: center; display: flex; flex-direction: column; justify-content: center; 
        align-items: center; min-height: 250px;
    }

    .reset-modal-title { font-size: 28px; font-weight: 700; color: #222; margin-bottom: 5px; text-align: center; }
    .reset-modal-sub { font-size: 14px; color: #888; margin-bottom: 25px; text-align: center; }
    
    .reset-input-group { margin-bottom: 18px; }
    .reset-input-group label { display: block; font-size: 14px; font-weight: 600; color: #444; margin-bottom: 6px; }
    .reset-input {
        width: 100%; padding: 14px 16px; border: 1.5px solid #eee; border-radius: 16px;
        font-size: 14px; font-family: 'Poppins'; background: #fafafa; transition: 0.3s; outline: none;
    }
    .reset-input:focus { border-color: #ffc1cc; background: #fff; box-shadow: 0 0 0 4px rgba(255, 193, 204, 0.1); }
    
    .reset-submit-btn {
        width: 100%; padding: 16px; border: none; border-radius: 50px;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; font-size: 16px; font-weight: 600; cursor: pointer;
        transition: 0.2s; font-family: 'Poppins'; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
    }
    .reset-submit-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4); }
    
    .reset-divider { text-align: center; color: #ccc; font-size: 14px; margin: 20px 0; font-weight: 500; }
    
    .reset-register-link { text-align: center; font-size: 14px; color: #666; }
    .reset-register-link a { color: #ff8ba7; font-weight: 600; text-decoration: none; }
    .reset-register-link a:hover { text-decoration: underline; }

    @media (max-width: 700px) {
        .reset-modal-split { flex-direction: column-reverse; gap: 20px; }
        .reset-modal-art { min-height: 150px; padding: 30px 20px; }
        .reset-modal-art i { font-size: 50px !important; }
    }
</style>