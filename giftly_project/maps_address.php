<?php
/**
 * Google Places address autocomplete. Before including, set:
 *   $maps_id   unique prefix for this instance (e.g. 'co', 'bx', 'pf')
 *
 * Renders a "search your address" box. On selection it dispatches a
 * `maps:address` event on #{$maps_id}_maps with
 *   detail = { street, barangay, city, province, region, zip, formatted }
 *
 * Renders nothing unless env GOOGLE_MAPS_API_KEY is set — so the plain
 * address fields keep working until the key is configured.
 * The API script + JS + CSS are emitted only once per page.
 */
$__mid  = isset($maps_id) && $maps_id !== '' ? preg_replace('/[^a-z0-9_]/i', '', $maps_id) : 'maps';
$__mkey = trim((string) getenv('GOOGLE_MAPS_API_KEY'));
if ($__mkey === '') {
    return;
}
?>
<div class="maps-address" id="<?php echo $__mid; ?>_maps">
    <label class="maps-label"><i class="fas fa-location-dot"></i> Search your address</label>
    <input type="text" id="<?php echo $__mid; ?>_search" class="maps-address-input"
           placeholder="Start typing your street, subdivision or barangay…" autocomplete="off">
    <div class="maps-hint">Pick a suggestion to fill the fields below, then adjust the house / unit number.</div>
</div>

<?php if (!defined('GIFTLY_MAPS_EMITTED')): define('GIFTLY_MAPS_EMITTED', true); ?>
<style>
    .maps-address { background: #f5f9ff; border: 1.5px dashed #bcd4ff; border-radius: 14px; padding: 14px 16px; margin-bottom: 14px; }
    .maps-label { display: block; font-size: 13px; font-weight: 700; color: #3f7fd6; margin-bottom: 8px; }
    .maps-address-input { width: 100%; padding: 12px 14px; border: 1.5px solid #e2e2e2; border-radius: 10px; font-family: 'Poppins', sans-serif; font-size: 14px; outline: none; background: #fff; }
    .maps-address-input:focus { border-color: #bcd4ff; }
    .maps-hint { font-size: 11.5px; color: #8a8a8a; margin-top: 8px; }
    .pac-container { z-index: 100000; border-radius: 10px; margin-top: 4px; font-family: 'Poppins', sans-serif; box-shadow: 0 10px 30px rgba(0,0,0,0.12); }
</style>
<script src="maps_address.js"></script>
<script async defer
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode($__mkey); ?>&libraries=places&callback=giftlyMapsInit&loading=async"></script>
<?php endif; ?>
