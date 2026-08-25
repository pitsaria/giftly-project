<!-- LOGOUT CONFIRMATION MODAL -->
<div class="logout-modal-overlay" id="logoutModal">
    <div class="logout-modal-box">
        <span class="logout-modal-close" onclick="closeLogoutModal()">&times;</span>
        
        <div style="text-align: center; padding: 10px 0;">
            <!-- Warning Icon -->
            <div style="font-size: 60px; color: #ff8ba7; margin-bottom: 15px;">
                <i class="fas fa-sign-out-alt" style="background: #fff0f5; padding: 20px; border-radius: 50%;"></i>
            </div>
            
            <h3 style="font-size: 24px; font-weight: 700; color: #222; margin-bottom: 5px;">Logout?</h3>
            <p style="font-size: 15px; color: #888; margin-bottom: 25px; line-height: 1.5;">
                Are you sure you want to log out? <br> You will need to log in again to access your account.
            </p>

            <div style="display: flex; gap: 15px; justify-content: center;">
                <!-- Cancel Button -->
                <button onclick="closeLogoutModal()" style="flex: 1; padding: 14px 0; border: 2px solid #eee; border-radius: 50px; background: #fff; color: #555; font-weight: 600; font-size: 16px; cursor: pointer; transition: 0.2s; font-family: 'Poppins';">
                    Cancel
                </button>

                <!-- Yes, Logout Button -->
                <a href="logout.php" class="logout-trigger" style="flex: 1; text-decoration: none;">
                    <button style="width: 100%; padding: 14px 0; border: none; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: white; font-weight: 600; font-size: 16px; cursor: pointer; transition: 0.2s; font-family: 'Poppins'; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2);">
                        Yes, Logout
                    </button>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    /* --- LOGOUT MODAL STYLES --- */
    .logout-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(4px);
        z-index: 999999;
        display: none;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }
    .logout-modal-box {
        background: #ffffff;
        border-radius: 30px;
        padding: 40px 35px;
        max-width: 450px;
        width: 90%;
        box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        position: relative;
        animation: modalFadeIn 0.3s ease-out;
    }
    @keyframes modalFadeIn {
        from { transform: scale(0.95) translateY(20px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .logout-modal-close {
        position: absolute; top: 15px; right: 20px;
        font-size: 24px; color: #888; cursor: pointer; transition: 0.2s;
    }
    .logout-modal-close:hover { color: #ff8ba7; transform: rotate(90deg); }

    /* --- LOGOUT TOAST (Matches Login Toast) --- */
    .logout-toast {
        position: fixed; 
        top: 25px; 
        left: 50%; 
        transform: translateX(-50%) translateY(-20px);
        background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%);
        padding: 18px 32px;
        border-radius: 60px;
        box-shadow: 0 12px 35px rgba(254, 165, 182, 0.4);
        font-weight: 600;
        font-size: 16px;
        color: #ffffff;
        z-index: 9999999;
        display: none;
        opacity: 0;
        transition: opacity 0.4s ease, transform 0.4s ease;
        display: flex;
        align-items: center;
        gap: 12px;
        white-space: nowrap;
        letter-spacing: 0.5px;
    }
    .logout-toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    .logout-toast i {
        font-size: 24px;
        color: #ffffff;
    }
</style>

<script>
    function openLogoutModal() {
        document.getElementById('logoutModal').style.display = 'flex';
    }
    function closeLogoutModal() {
        document.getElementById('logoutModal').style.display = 'none';
    }
    // Close modal when clicking outside the white box
    document.getElementById('logoutModal').addEventListener('click', function(e) {
        if (e.target === this) closeLogoutModal();
    });

    // SHOW LOGOUT TOAST (Matches Login Toast)
    function showLogoutToast() {
        var toast = document.createElement('div');
        toast.className = 'logout-toast show';
        toast.innerHTML = '<i class="fas fa-sign-out-alt"></i> Successfully logged out!';
        document.body.appendChild(toast);
        
        setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.remove();
            }, 400);
        }, 3000);
    }

    // Override the "Yes, Logout" button to show toast then redirect
    document.addEventListener('DOMContentLoaded', function() {
        const logoutBtn = document.querySelector('.logout-trigger');
        if(logoutBtn) {
            logoutBtn.addEventListener('click', function(e) {
                e.preventDefault(); // Stop the immediate redirect
                
                // ✅ Set a flag in sessionStorage so we know the user just logged out
                sessionStorage.setItem('justLoggedOut', 'true');

                closeLogoutModal();
                showLogoutToast();
                
                setTimeout(function() {
                    window.location.href = 'logout.php';
                }, 800);
            });
        }
    });

    // ✅ After the page reloads, ONLY open if the flag exists
    window.addEventListener('load', function() {
        <?php if(!isset($_SESSION['user_id'])): ?>
            if(sessionStorage.getItem('justLoggedOut') === 'true') {
                setTimeout(openLoginModal, 300);
                sessionStorage.removeItem('justLoggedOut'); // Clear it so it never happens again
            }
        <?php endif; ?>
    });
</script>