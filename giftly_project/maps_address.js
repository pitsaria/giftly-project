/* Google Places Autocomplete for PH addresses.
   Loaded by maps_address.php; the Maps JS API calls giftlyMapsInit() when ready.
   Each .maps-address element dispatches a `maps:address` CustomEvent with
   detail = { street, barangay, city, province, region, zip, formatted }. */

function giftlyMapsInit() {
    if (!(window.google && google.maps && google.maps.places)) return;

    document.querySelectorAll('.maps-address-input').forEach(function (input) {
        if (input.dataset.acInited) return;
        input.dataset.acInited = '1';

        // don't let Enter (choosing a suggestion) submit the form
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') e.preventDefault();
        });

        var ac = new google.maps.places.Autocomplete(input, {
            componentRestrictions: { country: 'ph' },
            fields: ['address_components', 'formatted_address'],
            types: ['geocode']
        });

        ac.addListener('place_changed', function () {
            var place = ac.getPlace();
            if (!place || !place.address_components) return;

            function get(type) {
                var c = place.address_components.find(function (x) { return x.types.indexOf(type) > -1; });
                return c ? c.long_name : '';
            }

            var street   = [get('street_number'), get('route')].filter(Boolean).join(' ');
            var barangay = get('sublocality_level_1') || get('neighborhood') || get('sublocality');
            var city     = get('locality') || get('administrative_area_level_3') || get('administrative_area_level_2');
            var province = get('administrative_area_level_2');
            var region   = get('administrative_area_level_1');
            var zip      = get('postal_code');

            if (province && province === city) province = '';   // Google sometimes repeats the city

            var root = input.closest('.maps-address');
            if (!root) return;
            root.dispatchEvent(new CustomEvent('maps:address', {
                bubbles: true,
                detail: {
                    street: street, barangay: barangay, city: city,
                    province: province, region: region, zip: zip,
                    formatted: place.formatted_address || ''
                }
            }));
        });
    });
}
window.giftlyMapsInit = giftlyMapsInit;

// if the API script finished before this file (cached), init now
if (window.google && google.maps && google.maps.places) giftlyMapsInit();
