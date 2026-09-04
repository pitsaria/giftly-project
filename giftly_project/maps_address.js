/* Address autocomplete via Photon (photon.komoot.io) — free OpenStreetMap
   geocoder, no key, no billing. Each .maps-address element dispatches a
   `maps:address` CustomEvent with
   detail = { street, barangay, city, province, region, zip, formatted }. */
(function () {
    // rough Philippines bounding box, to bias results
    var PH_BBOX = '116.7,4.5,127.0,21.2';

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, ms);
        };
    }

    function parse(pr) {
        var street   = [pr.housenumber, pr.street].filter(Boolean).join(' ')
                        || (pr.type === 'street' ? pr.name : '')
                        || pr.name || '';
        var barangay = pr.locality || pr.suburb || pr.quarter || pr.neighbourhood || '';
        var city     = pr.city || pr.town || pr.municipality || '';
        var county   = pr.county || '';
        var region   = pr.state || '';
        var province = /district/i.test(county) ? region : (county || region);
        var zip      = pr.postcode || '';
        if (!city && barangay && county && !/district/i.test(county)) { city = county; }
        return { street: street, barangay: barangay, city: city, province: province, region: region, zip: zip };
    }

    function initWidget(root) {
        var p = root.dataset.prefix;
        var input = document.getElementById(p + '_search');
        var list  = document.getElementById(p + '_list');
        if (!input || !list) return;

        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });

        var run = debounce(function () {
            var q = input.value.trim();
            if (q.length < 3) { list.style.display = 'none'; list.innerHTML = ''; return; }
            fetch('https://photon.komoot.io/api/?limit=7&lang=en&bbox=' + PH_BBOX + '&q=' + encodeURIComponent(q),
                  { referrerPolicy: 'no-referrer' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var feats = (d.features || []).filter(function (f) {
                        return (f.properties || {}).countrycode === 'PH';
                    });
                    list._feats = feats;
                    if (!feats.length) {
                        list.innerHTML = '<div class="maps-ac-empty">No matches — type your address in the fields below.</div>';
                    } else {
                        list.innerHTML = feats.map(function (f, i) {
                            var pr = f.properties;
                            var main = [pr.name, pr.street].filter(function (v, idx, a) { return v && a.indexOf(v) === idx; }).join(', ')
                                       || pr.street || pr.name || pr.locality || 'Address';
                            var sub = [pr.locality, pr.city || pr.county, pr.state, pr.postcode].filter(Boolean).join(', ');
                            return '<div class="maps-ac-item" data-i="' + i + '"><strong>' + esc(main) + '</strong><span>' + esc(sub) + '</span></div>';
                        }).join('');
                    }
                    list.style.display = 'block';
                })
                .catch(function () { list.style.display = 'none'; });
        }, 300);

        input.addEventListener('input', run);
        input.addEventListener('focus', function () { if (list.innerHTML) list.style.display = 'block'; });

        list.addEventListener('click', function (e) {
            var item = e.target.closest('.maps-ac-item');
            if (!item || !list._feats) return;
            var d = parse(list._feats[+item.dataset.i].properties);
            input.value = [d.street, d.barangay, d.city].filter(Boolean).join(', ');
            list.style.display = 'none';
            root.dispatchEvent(new CustomEvent('maps:address', {
                bubbles: true,
                detail: {
                    street: d.street, barangay: d.barangay, city: d.city,
                    province: d.province, region: d.region, zip: d.zip,
                    formatted: [d.street, d.barangay, d.city, d.province, d.zip].filter(Boolean).join(', ')
                }
            }));
        });

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) list.style.display = 'none';
        });
    }

    function init() {
        document.querySelectorAll('.maps-address').forEach(function (w) {
            if (!w.dataset.inited) { w.dataset.inited = '1'; initWidget(w); }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
