<!-- ADMIN CHOICE MODAL -->
<div class="admin-choice-overlay" id="adminChoiceModal">
    <div class="admin-choice-box">
        <div class="admin-choice-close" onclick="closeAdminChoiceModal()">&times;</div>
        
        <div style="text-align: center; padding: 10px 0;">
            <div style="font-size: 65px; color: #ff8ba7; margin-bottom: 15px;">
                <i class="fas fa-crown" style="background: #fff0f5; padding: 20px; border-radius: 50%; box-shadow: 0 4px 15px rgba(255, 139, 167, 0.1);"></i>
            </div>
            
            <h3 style="font-size: 26px; font-weight: 700; color: #222; margin-bottom: 5px;">Welcome back, Admin!</h3>
            <p style="font-size: 15px; color: #666; line-height: 1.5; margin-bottom: 25px;">
                Would you like to manage your store or browse the shop?
            </p>

            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="admin_dashboard.php?show_success=1" style="flex: 1; min-width: 160px; padding: 14px 20px; border: none; border-radius: 50px; background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); color: white; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Poppins'; box-shadow: 0 4px 12px rgba(254, 165, 182, 0.2); display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;" onclick="closeAdminChoiceModal()">
    <i class="fas fa-crown"></i> Go to Admin Dashboard
</a>

                <a href="shop.php" style="flex: 1; text-decoration: none; min-width: 160px;" onclick="closeAdminChoiceModal()">
                    <button style="width: 100%; padding: 14px 20px; border: 2px solid #eee; border-radius: 50px; background: #fff; color: #555; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; font-family: 'Poppins'; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fas fa-store"></i> Browse Shop
                    </button>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .admin-choice-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(6px);
        z-index: 9999999;
        display: none;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }
    .admin-choice-box {
        background: #ffffff;
        border-radius: 35px;
        padding: 40px 35px;
        max-width: 480px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        position: relative;
        animation: modalFadeIn 0.3s ease-out;
    }
    @keyframes modalFadeIn {
        from { transform: scale(0.95) translateY(20px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .admin-choice-close {
        position: absolute; top: 15px; right: 20px;
        font-size: 24px; color: #888; cursor: pointer; transition: 0.2s;
    }
    .admin-choice-close:hover { color: #ff8ba7; transform: rotate(90deg); }
</style>

<script>
    function openAdminChoiceModal() {
        document.getElementById('adminChoiceModal').style.display = 'flex';
    }
    function closeAdminChoiceModal() {
        document.getElementById('adminChoiceModal').style.display = 'none';
    }
    document.getElementById('adminChoiceModal').addEventListener('click', function(e) {
        if (e.target === this) closeAdminChoiceModal();
    });
</script>