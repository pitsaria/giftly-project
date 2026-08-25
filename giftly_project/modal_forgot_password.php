<!-- modal_forgot_password.php -->
<div class="forgot-modal-overlay" id="forgotPasswordModal">
    <div class="forgot-modal-box">
        <div class="forgot-modal-close" onclick="closeForgotModal()">&times;</div>
        
        <div class="forgot-modal-split">
            <!-- LEFT: Forgot Password Form -->
            <div class="forgot-modal-form">
                <div style="text-align: center; margin-bottom: 15px;">
                    <div style="font-size: 40px; color: #ff8ba7; margin-bottom: 5px;">
                        <i class="fas fa-lock" style="background: #fff0f5; padding: 12px; border-radius: 50%;"></i>
                    </div>
                    <h2 class="forgot-modal-title" style="font-size: 24px;">Forgot Password</h2>
                    <p class="forgot-modal-sub" style="margin-bottom: 15px;">Enter your email and we'll send a reset link.</p>
                </div>

                                <!-- AJAX FORM (NO PAGE RELOAD) -->
                
                <!-- 🚨 ADD THIS EMPTY DIV RIGHT HERE -->
                <div id="forgotAlertBox"></div>

                <form id="forgotPasswordForm" onsubmit="submitForgotPassword(event)">
                    <div class="forgot-input-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="forgot-input" placeholder="Enter your email" required>
                    </div>
                    <button type="submit" class="forgot-submit-btn" id="forgotSubmitBtn">Send Reset Link</button>
                    <div class="forgot-divider">or</div>
                    
                    <div class="forgot-register-link" style="text-align: center; font-size: 14px; color: #666; cursor: pointer;">
                        Remember your password? <a href="javascript:void(0)" onclick="closeForgotModal(); setTimeout(openLoginModal, 300);">Back to Login</a>
                    </div>
                </form>
            </div>

            <!-- RIGHT: Promotional Art -->
            <div class="forgot-modal-art">
                <div style="font-size: 60px; color: #ff8ba7; margin-bottom: 15px;">
                    <i class="fas fa-key"></i>
                </div>
                <h3 style="font-size: 20px; font-weight: 700; color: #222;">No worries!</h3>
                <p style="font-size: 14px; color: #666; line-height: 1.5; max-width: 200px; margin: 0 auto;">
                    We'll send you a secure link to reset your password.
                </p>
            </div>
        </div>
    </div>
</div>

<style>
    /* --- FORGOT MODAL OVERRIDES --- */
    .forgot-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(6px);
        z-index: 999998; display: none; justify-content: center; align-items: center; padding: 20px;
    }
    .forgot-modal-box {
        background: #ffffff; border-radius: 35px; max-width: 800px; width: 100%;
        max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        position: relative; padding: 40px; animation: modalFadeIn 0.3s ease-out;
    }
    .forgot-modal-close {
        position: absolute; top: 20px; right: 25px; font-size: 28px; color: #888; 
        cursor: pointer; transition: 0.2s;
    }
    .forgot-modal-close:hover { color: #ff8ba7; transform: rotate(90deg); }

    .forgot-modal-split { display: flex; gap: 40px; align-items: center; }
    .forgot-modal-form { flex: 1; }
    .forgot-modal-art {
        flex: 1; background: #fff0f5; border-radius: 24px; padding: 40px 20px;
        text-align: center; display: flex; flex-direction: column; justify-content: center; 
        align-items: center; min-height: 250px;
    }

    .forgot-modal-title { font-size: 28px; font-weight: 700; color: #222; margin-bottom: 5px; text-align: center; }
    .forgot-modal-sub { font-size: 14px; color: #888; margin-bottom: 25px; text-align: center; }
    
    .forgot-input-group { margin-bottom: 18px; }
    .forgot-input-group label { display: block; font-size: 14px; font-weight: 600; color: #444; margin-bottom: 6px; }
    .forgot-input {
        width: 100%; padding: 14px 16px; border: 1.5px solid #eee; border-radius: 16px;
        font-size: 14px; font-family: 'Poppins'; background: #fafafa; transition: 0.3s; outline: none;
    }
    .forgot-input:focus { border-color: #ffc1cc; background: #fff; box-shadow: 0 0 0 4px rgba(255, 193, 204, 0.1); }
    
    .forgot-submit-btn {
        width: 100%; padding: 16px; border: none; border-radius: 50px;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; font-size: 16px; font-weight: 600; cursor: pointer;
        transition: 0.2s; font-family: 'Poppins'; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);
    }
    .forgot-submit-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4); }
    
    .forgot-divider { text-align: center; color: #ccc; font-size: 14px; margin: 20px 0; font-weight: 500; }
    
    .forgot-register-link a { color: #ff8ba7; font-weight: 600; text-decoration: none; }
    .forgot-register-link a:hover { text-decoration: underline; }

    @media (max-width: 700px) {
        .forgot-modal-split { flex-direction: column-reverse; gap: 20px; }
        .forgot-modal-art { min-height: 150px; padding: 30px 20px; }
        .forgot-modal-art i { font-size: 50px !important; }
    }
</style>