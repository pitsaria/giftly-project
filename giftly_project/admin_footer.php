    <!-- ADMIN FOOTER -->
    <footer style="background: #fdeded; border-radius: 40px 40px 0 0; padding: 30px 0 20px; flex-shrink: 0; width: 100%;">
        <div style="text-align: center; font-size: 14px; color: #555; font-weight: 500;">
            &copy; Giftly Admin Panel 2026. All rights reserved.
        </div>
        <div style="text-align: center; font-size: 12px; color: #888; margin-top: 5px;">
            <i class="fas fa-shield-alt" style="color: #ff8ba7; margin-right: 5px;"></i> Secure Management System
        </div>
    </footer>

    <style>
        .at-switch { position: relative; display: inline-block; width: 42px; height: 24px; vertical-align: middle; }
        .at-switch input { opacity: 0; width: 0; height: 0; }
        .at-slider { position: absolute; inset: 0; cursor: pointer; background: #d6d6d6; border-radius: 50px; transition: 0.2s; }
        .at-slider::before { content: ""; position: absolute; height: 18px; width: 18px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        .at-switch input:checked + .at-slider { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); }
        .at-switch input:checked + .at-slider::before { transform: translateX(18px); }
        .at-switch input:disabled + .at-slider { opacity: 0.5; }
        .admin-card.is-inactive, tr.is-inactive { opacity: 0.55; }
        .at-cell { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #999; }
    </style>
    <script>
        /* activate / deactivate a product or category (visibility on the customer site) */
        function toggleActive(el, kind, id) {
            el.disabled = true;
            var wanted = el.checked;
            fetch('admin_toggle_active.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'kind=' + encodeURIComponent(kind) + '&id=' + id + '&active=' + (wanted ? 1 : 0),
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d || d.status !== 'success') { el.checked = !wanted; alert((d && d.message) || 'Could not update.'); return; }
                    var row = el.closest('.admin-card, tr');
                    if (row) row.classList.toggle('is-inactive', !wanted);
                    var lbl = el.closest('.at-cell') ? el.closest('.at-cell').querySelector('span:last-child') : null;
                    if (lbl) lbl.textContent = wanted ? (kind === 'category' ? 'Shown' : 'Visible on site') : (kind === 'category' ? 'Hidden' : 'Hidden from site');
                })
                .catch(function () { el.checked = !wanted; alert('Network error.'); })
                .finally(function () { el.disabled = false; });
        }
    </script>
</body>
</html>