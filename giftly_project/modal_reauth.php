<!-- RE-AUTHENTICATE MODAL (Security Gate) -->
<div class="reauth-overlay" id="reauthModal">
    <div class="reauth-box">
        <div class="reauth-close" onclick="closeReauthModal()">&times;</div>
        
        <div style="text-align: center; padding: 10px 0;">
            <div style="font-size: 50px; color: #ff8ba7; margin-bottom: 15px;">
                <i class="fas fa-shield-alt" style="background: #fff0f5; padding: 15px; border-radius: 50%;"></i>
            </div>
            
            <h3 style="font-size: 22px; font-weight: 700; color: #222; margin-bottom: 5px;">Enter Password</h3>
            <p style="font-size: 14px; color: #888; margin-bottom: 20px;">Please re-enter your password to access the Admin Dashboard.</p>

            <form id="reauthForm" action="admin_dashboard.php" method="POST">
                <input type="password" name="reauth_password" id="reauthPassword" class="reauth-input" placeholder="Enter your password" required>
                <div id="reauthError" style="color: #d32f2f; font-size: 13px; margin-top: 8px; display: none;">Incorrect password. Please try again.</div>
                <button type="button" onclick="validateReauth()" class="reauth-btn">Verify & Continue</button>
            </form>

            <p style="margin-top: 15px; font-size: 13px; color: #888;">
                <a href="javascript:void(0)" onclick="closeReauthModal()" style="color: #ff8ba7;">Cancel</a>
            </p>
        </div>
    </div>
</div>

<style>
    /* --- REAUTH MODAL STYLES --- */
    .reauth-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(6px);
        z-index: 9999998;
        display: none;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }
    .reauth-box {
        background: #ffffff;
        border-radius: 30px;
        padding: 35px;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        position: relative;
        animation: modalFadeIn 0.3s ease-out;
    }
    .reauth-close {
        position: absolute; top: 15px; right: 20px;
        font-size: 24px; color: #888; cursor: pointer; transition: 0.2s;
    }
    .reauth-close:hover { color: #ff8ba7; transform: rotate(90deg); }
    .reauth-input {
        width: 100%; padding: 14px 16px; border: 1.5px solid #eee; border-radius: 16px;
        font-size: 14px; font-family: 'Poppins'; background: #fafafa; transition: 0.3s; outline: none;
    }
    .reauth-input:focus { border-color: #ffc1cc; background: #fff; box-shadow: 0 0 0 4px rgba(255, 193, 204, 0.1); }
    .reauth-btn {
        width: 100%; padding: 14px; border: none; border-radius: 50px;
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        color: white; font-size: 16px; font-weight: 600; cursor: pointer; transition: 0.2s; font-family: 'Poppins';
        box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2); margin-top: 10px;
    }
    .reauth-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(254, 165, 182, 0.4); }
</style>

<script>
    /* --- REAUTH MODAL CONTROLS --- */
    function openReauthModal() {
        document.getElementById('reauthModal').style.display = 'flex';
    }
    function closeReauthModal() {
        document.getElementById('reauthModal').style.display = 'none';
        document.getElementById('reauthError').style.display = 'none';
        document.getElementById('reauthPassword').value = '';
    }
    document.getElementById('reauthModal').addEventListener('click', function(e) {
        if (e.target === this) closeReauthModal();
    });

    /* --- REAUTH VALIDATION (AJAX) --- */
    function validateReauth() {
        let password = document.getElementById('reauthPassword').value;
        let errorBox = document.getElementById('reauthError');

        fetch('admin_reauth_check.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'password=' + encodeURIComponent(password)
        })
        .then(response => response.text())
        .then(data => {
            if(data.trim() === 'success') {
                // Password correct! Redirect to dashboard.
                window.location.href = 'admin_dashboard.php?show_success=1';            } else {
                // Password incorrect.
                errorBox.style.display = 'block';
                errorBox.innerText = 'Incorrect password. Please try again.';
            }
        });
    }

    // Support pressing "Enter" key in the password field
    document.getElementById('reauthPassword').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            validateReauth();
        }
    });
</script>