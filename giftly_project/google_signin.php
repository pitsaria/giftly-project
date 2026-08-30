<?php
/**
 * "Continue with Google" button — include inside a login / register modal.
 *
 * Before the include, optionally set:
 *   $g_slot_id   DOM id for THIS button container (default 'gbtn_login')
 *
 * The button container is emitted every time; the shared Google Identity
 * Services script + JS is emitted only once per page. If GOOGLE_CLIENT_ID is
 * not set the whole thing renders nothing, so the site works unchanged until
 * the env var is added.
 */

if (!function_exists('google_client_id')) {
    require_once __DIR__ . '/auth_lib.php';
}
$__g_cid = google_client_id();
if ($__g_cid === '') {
    return; // feature disabled until GOOGLE_CLIENT_ID is configured
}
$__g_slot = isset($g_slot_id) && $g_slot_id !== '' ? $g_slot_id : 'gbtn_login';
?>
<div class="g-signin-wrap">
    <div id="<?php echo htmlspecialchars($__g_slot); ?>" class="g-signin-slot"></div>
</div>

<?php if (!defined('GIFTLY_GSI_EMITTED')): define('GIFTLY_GSI_EMITTED', true); ?>
<style>
    .g-signin-wrap { display: flex; justify-content: center; margin: 14px 0 4px; }
    .g-signin-slot { min-height: 40px; }
</style>
<script>
    window.__giftlyGoogleClientId = <?php echo json_encode($__g_cid); ?>;

    function handleGoogleCredential(response) {
        fetch('google_auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'credential=' + encodeURIComponent(response.credential)
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.status === 'success') {
                    window.location.href = d.redirect || 'index.php';
                } else {
                    alert((d && d.message) || 'Google sign-in failed. Please try again.');
                }
            })
            .catch(function () { alert('Could not reach the server for Google sign-in.'); });
    }

    function giftlyGoogleRender() {
        if (!window.google || !window.google.accounts || !window.google.accounts.id) return;
        if (!window.__giftlyGsiInit) {
            google.accounts.id.initialize({
                client_id: window.__giftlyGoogleClientId,
                callback: handleGoogleCredential
            });
            window.__giftlyGsiInit = true;
        }
        document.querySelectorAll('.g-signin-slot').forEach(function (slot) {
            if (slot.dataset.rendered) return;
            slot.dataset.rendered = '1';
            google.accounts.id.renderButton(slot, {
                theme: 'outline', size: 'large', text: 'continue_with',
                shape: 'pill', logo_alignment: 'center', width: 300
            });
        });
    }
    window.giftlyGoogleRender = giftlyGoogleRender;
    document.addEventListener('DOMContentLoaded', giftlyGoogleRender);
</script>
<script src="https://accounts.google.com/gsi/client" onload="giftlyGoogleRender()" async defer></script>
<?php endif; ?>
