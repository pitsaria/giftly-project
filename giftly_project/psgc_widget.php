<?php
/**
 * Cascading PH address picker. Before including, set:
 *   $psgc_id   unique prefix for this instance (e.g. 'co', 'bx', 'pf')
 * Dispatches a `psgc:change` event on #{$psgc_id}_psgc with
 * detail = { region, province, city, barangay }.
 * Region/Province/City come from a bundled dataset (ph_places.json) — no API.
 * The CSS + <script> are emitted only once per page.
 */
$__pid = isset($psgc_id) && $psgc_id !== '' ? preg_replace('/[^a-z0-9_]/i', '', $psgc_id) : 'psgc';
?>
<div class="psgc-widget" id="<?php echo $__pid; ?>_psgc" data-prefix="<?php echo $__pid; ?>">
    <div class="psgc-label"><i class="fas fa-map-location-dot"></i> Find your address</div>
    <div class="psgc-grid">
        <select id="<?php echo $__pid; ?>_reg"  class="psgc-sel"><option value="">Region…</option></select>
        <select id="<?php echo $__pid; ?>_prov" class="psgc-sel" disabled><option value="">Province…</option></select>
        <select id="<?php echo $__pid; ?>_city" class="psgc-sel" disabled><option value="">City / Municipality…</option></select>
        <input  id="<?php echo $__pid; ?>_brgy" class="psgc-sel" type="text" placeholder="Barangay">
    </div>
    <div class="psgc-err" style="display:none;"></div>
    <div class="psgc-hint">Pick your Region → Province → City, type your Barangay, then add your house / unit / street below.</div>
</div>

<?php if (!defined('GIFTLY_PSGC_EMITTED')): define('GIFTLY_PSGC_EMITTED', true); ?>
<style>
    .psgc-widget { background: #fff8fa; border: 1.5px dashed #ffc1cc; border-radius: 14px; padding: 14px 16px; margin-bottom: 14px; }
    .psgc-label { font-size: 13px; font-weight: 700; color: #ff8ba7; margin-bottom: 10px; }
    .psgc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .psgc-sel { width: 100%; padding: 11px 12px; border: 1.5px solid #eee; border-radius: 10px; font-family: 'Poppins', sans-serif; font-size: 13.5px; background: #fff; outline: none; color: #333; }
    .psgc-sel:focus { border-color: #ffc1cc; }
    select.psgc-sel:disabled { background: #f4f4f4; color: #999; }
    .psgc-hint { font-size: 11.5px; color: #8a8a8a; margin-top: 8px; }
    .psgc-err { font-size: 12px; color: #d32f2f; margin-top: 8px; background: #fdeded; border-radius: 8px; padding: 8px 10px; }
    @media (max-width: 560px) { .psgc-grid { grid-template-columns: 1fr; } }
</style>
<script src="psgc_widget.js" defer></script>
<?php endif; ?>
