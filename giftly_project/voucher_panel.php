<?php
/**
 * Checkout "Vouchers" panel — lists the live coded promos this customer can
 * still use, each with Copy / Claim / Apply. Shared by checkout_selected.php
 * (products) and box_checkout.php (gift boxes); included inside #promoBox.
 *
 * Expects: $conn, $user_id, $vp_scope ('products'|'box'), $vp_subtotal,
 * and optionally $vp_item_count. "Apply" reuses the page's own promo box
 * (#promoCodeInput + applyPromo()), so nothing about how a code is validated
 * or recorded changes — claiming only saves the voucher to the account.
 */
include_once __DIR__ . '/promo_lib.php';

$vp_list = promo_vouchers_for_user($conn, (int) $user_id, $vp_scope ?? 'products', (float) ($vp_subtotal ?? 0), $vp_item_count ?? null);
if (empty($vp_list)) return;

$vp_claimed_count = count(array_filter($vp_list, function ($v) { return $v['claimed']; }));
?>
<style>
    .voucher-panel { margin-top: 12px; border: 1px dashed #f0c9d3; border-radius: 14px; background: #fffafb; overflow: hidden; }
    .voucher-toggle { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 11px 14px; background: none; border: none; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 600; color: #d81b60; }
    .voucher-toggle .vt-sub { font-weight: 500; color: #999; font-size: 12px; }
    .voucher-toggle i.chev { transition: transform 0.2s; }
    .voucher-panel.open .voucher-toggle i.chev { transform: rotate(180deg); }
    .voucher-list { display: none; max-height: 300px; overflow-y: auto; padding: 4px 10px 10px; }
    .voucher-panel.open .voucher-list { display: block; }
    .v-row { display: flex; align-items: center; gap: 10px; padding: 10px; margin-top: 8px; background: #fff; border: 1px solid #f5e1e7; border-radius: 12px; }
    .v-row.v-off { opacity: 0.6; }
    .v-icon { flex: 0 0 auto; width: 34px; height: 34px; border-radius: 10px; background: #fff0f5; color: #ff8ba7; display: flex; align-items: center; justify-content: center; font-size: 15px; }
    .v-body { flex: 1; min-width: 0; }
    .v-head { font-size: 13px; font-weight: 700; color: #222; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .v-badge { font-size: 10px; font-weight: 700; color: #2e7d32; background: #e8f5e9; border-radius: 50px; padding: 2px 8px; }
    .v-cond { font-size: 11.5px; color: #888; margin-top: 1px; }
    .v-code { display: inline-block; margin-top: 3px; font-size: 11.5px; font-weight: 700; letter-spacing: 0.5px; color: #d81b60; }
    .v-reason { font-size: 11px; color: #d32f2f; margin-top: 3px; }
    .v-actions { flex: 0 0 auto; display: flex; flex-direction: column; gap: 4px; }
    .v-btn { border: 1.5px solid #ffc1cc; background: #fff; color: #d81b60; border-radius: 50px; padding: 4px 12px; font-family: 'Poppins', sans-serif; font-size: 11.5px; font-weight: 600; cursor: pointer; white-space: nowrap; }
    .v-btn.v-use { background: linear-gradient(135deg, #FEA5B6 0%, #ff8ba7 100%); border-color: transparent; color: #fff; }
    .v-btn:disabled { opacity: 0.5; cursor: default; }
</style>

<div class="voucher-panel" id="voucherPanel">
    <button type="button" class="voucher-toggle" onclick="document.getElementById('voucherPanel').classList.toggle('open')">
        <span><i class="fas fa-ticket" style="margin-right:6px;"></i> Vouchers <span class="vt-sub" id="vtSub"><?php echo count($vp_list); ?> available<?php echo $vp_claimed_count > 0 ? ' · ' . $vp_claimed_count . ' claimed' : ''; ?></span></span>
        <i class="fas fa-chevron-down chev"></i>
    </button>
    <div class="voucher-list" id="voucherList">
        <?php foreach ($vp_list as $v): ?>
            <div class="v-row<?php echo $v['usable'] ? '' : ' v-off'; ?>" data-id="<?php echo (int) $v['id']; ?>" data-code="<?php echo htmlspecialchars($v['code'], ENT_QUOTES); ?>">
                <div class="v-icon"><i class="fas <?php echo htmlspecialchars($v['icon']); ?>"></i></div>
                <div class="v-body">
                    <div class="v-head"><?php echo htmlspecialchars($v['headline']); ?><?php if ($v['claimed']): ?><span class="v-badge">Claimed</span><?php endif; ?></div>
                    <div class="v-cond"><?php echo htmlspecialchars($v['cond']); ?></div>
                    <div class="v-code"><?php echo htmlspecialchars($v['code']); ?></div>
                    <?php if (!$v['usable'] && $v['reason'] !== ''): ?><div class="v-reason"><?php echo htmlspecialchars($v['reason']); ?></div><?php endif; ?>
                </div>
                <div class="v-actions">
                    <?php if ($v['usable']): ?><button type="button" class="v-btn v-use" onclick="voucherUse(this)">Apply</button><?php endif; ?>
                    <button type="button" class="v-btn" onclick="voucherCopy(this)">Copy</button>
                    <?php if (!$v['claimed']): ?><button type="button" class="v-btn v-claim" onclick="voucherClaim(this)">Claim</button><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    function voucherCode(btn) { return btn.closest('.v-row').dataset.code; }

    // Fill the page's promo box and run its normal Apply flow.
    function voucherUse(btn) {
        var input = document.getElementById('promoCodeInput');
        if (input) input.value = voucherCode(btn);
        if (typeof applyPromo === 'function') applyPromo();
    }

    // Called with the `vouchers` list promo_apply.php returns after every cart change
    // (quantity +/-, add-ons, ...): turns a voucher's "Apply" button on or off and
    // updates its "spend at least..." note, so a voucher that becomes eligible can be
    // applied straight away instead of only copied.
    function voucherSync(list) {
        if (!list) return;
        list.forEach(function (v) {
            var row = document.querySelector('.v-row[data-id="' + v.id + '"]');
            if (!row) return;
            var actions = row.querySelector('.v-actions');
            var body = row.querySelector('.v-body');
            var useBtn = row.querySelector('.v-use');
            var reason = row.querySelector('.v-reason');
            row.classList.toggle('v-off', !v.usable);
            if (v.usable) {
                if (!useBtn) {
                    useBtn = document.createElement('button');
                    useBtn.type = 'button';
                    useBtn.className = 'v-btn v-use';
                    useBtn.textContent = 'Apply';
                    useBtn.onclick = function () { voucherUse(useBtn); };
                    actions.insertBefore(useBtn, actions.firstChild);
                }
                if (reason) reason.remove();
            } else {
                if (useBtn) useBtn.remove();
                if (v.reason) {
                    if (!reason) {
                        reason = document.createElement('div');
                        reason.className = 'v-reason';
                        body.appendChild(reason);
                    }
                    reason.textContent = v.reason;
                } else if (reason) {
                    reason.remove();
                }
            }
        });
    }

    function voucherCopy(btn) {
        var code = voucherCode(btn);
        var done = function () {
            var old = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(function () { btn.textContent = old; }, 1400);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(done).catch(done);
        } else {
            try {
                var t = document.createElement('textarea');
                t.value = code; document.body.appendChild(t); t.select();
                document.execCommand('copy'); document.body.removeChild(t);
            } catch (e) {}
            done();
        }
    }

    function voucherClaim(btn) {
        var row = btn.closest('.v-row');
        var fd = new FormData();
        fd.append('promo_id', row.dataset.id);
        btn.disabled = true;
        fetch('promo_claim.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.status === 'claimed' || d.status === 'already') {
                    var head = row.querySelector('.v-head');
                    if (head && !head.querySelector('.v-badge')) {
                        var b = document.createElement('span');
                        b.className = 'v-badge'; b.textContent = 'Claimed';
                        head.appendChild(b);
                    }
                    btn.remove();
                    var n = document.querySelectorAll('.v-row .v-badge').length;
                    var total = document.querySelectorAll('.v-row').length;
                    document.getElementById('vtSub').textContent = total + ' available' + (n ? ' · ' + n + ' claimed' : '');
                } else {
                    btn.disabled = false;
                    alert(d.message || "Couldn't claim this voucher.");
                }
            })
            .catch(function () { btn.disabled = false; alert("Couldn't claim this voucher. Please try again."); });
    }
</script>
