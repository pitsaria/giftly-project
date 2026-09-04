<?php
/**
 * Address autocomplete box. Before including, set:
 *   $maps_id   unique prefix for this instance (e.g. 'co', 'bx', 'pf')
 *
 * Backed by Photon (https://photon.komoot.io) — OpenStreetMap data, free,
 * no API key, no billing. On selecting a suggestion it dispatches a
 * `maps:address` event on #{$maps_id}_maps with
 *   detail = { street, barangay, city, province, region, zip, formatted }
 * The JS + CSS are emitted only once per page.
 */
$__mid = isset($maps_id) && $maps_id !== '' ? preg_replace('/[^a-z0-9_]/i', '', $maps_id) : 'maps';
?>
<div class="maps-address" id="<?php echo $__mid; ?>_maps" data-prefix="<?php echo $__mid; ?>">
    <label class="maps-label"><i class="fas fa-location-dot"></i> Search your address</label>
    <div class="maps-ac-wrap">
        <input type="text" id="<?php echo $__mid; ?>_search" class="maps-address-input"
               placeholder="Start typing your street, subdivision or barangay…" autocomplete="off">
        <div class="maps-ac-list" id="<?php echo $__mid; ?>_list"></div>
    </div>
    <div class="maps-hint">Pick a suggestion to fill the fields below, then adjust the house / unit number.</div>
</div>

<?php if (!defined('GIFTLY_MAPS_EMITTED')): define('GIFTLY_MAPS_EMITTED', true); ?>
<style>
    .maps-address { background: #f5f9ff; border: 1.5px dashed #bcd4ff; border-radius: 14px; padding: 14px 16px; margin-bottom: 14px; }
    .maps-label { display: block; font-size: 13px; font-weight: 700; color: #3f7fd6; margin-bottom: 8px; }
    .maps-ac-wrap { position: relative; }
    .maps-address-input { width: 100%; padding: 12px 14px; border: 1.5px solid #e2e2e2; border-radius: 10px; font-family: 'Poppins', sans-serif; font-size: 14px; outline: none; background: #fff; }
    .maps-address-input:focus { border-color: #bcd4ff; }
    .maps-ac-list { position: absolute; left: 0; right: 0; top: calc(100% + 4px); background: #fff; border: 1px solid #e2e2e2; border-radius: 10px; box-shadow: 0 12px 32px rgba(0,0,0,0.12); z-index: 100000; display: none; max-height: 260px; overflow-y: auto; }
    .maps-ac-item { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f2f2f2; }
    .maps-ac-item:last-child { border-bottom: none; }
    .maps-ac-item:hover { background: #f5f9ff; }
    .maps-ac-item strong { display: block; font-size: 13.5px; color: #222; font-weight: 600; }
    .maps-ac-item span { display: block; font-size: 12px; color: #888; margin-top: 1px; }
    .maps-ac-empty { padding: 12px 14px; font-size: 12.5px; color: #999; }
    .maps-hint { font-size: 11.5px; color: #8a8a8a; margin-top: 8px; }
</style>
<script src="maps_address.js" defer></script>
<?php endif; ?>
