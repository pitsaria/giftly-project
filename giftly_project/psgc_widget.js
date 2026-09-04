/* Cascading Philippine address picker: Region -> Province -> City/Municipality,
   plus a free-text Barangay field.

   Uses a single bundled dataset (ph_places.json — regions/provinces/cities from
   the PSGC). No external API, so nothing to time out or rate-limit.

   Each .psgc-widget dispatches `psgc:change` with
   detail = { region, province, city, barangay } (names). */
(function () {
    var DATA = null;
    var loading = null;

    function loadData() {
        if (DATA) return Promise.resolve(DATA);
        if (loading) return loading;
        loading = fetch('ph_places.json', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) { DATA = d; return d; })
            .catch(function () { return null; });
        return loading;
    }

    function opt(v, t) { var o = document.createElement('option'); o.value = v; o.textContent = t; return o; }

    function fill(sel, items, ph) {
        sel.innerHTML = '';
        sel.appendChild(opt('', ph));
        items.forEach(function (i) {
            var o = opt(i.c, i.n);
            o.dataset.name = i.n;
            sel.appendChild(o);
        });
        sel.disabled = items.length === 0;
    }
    function reset(sel, ph) { sel.innerHTML = ''; sel.appendChild(opt('', ph)); sel.disabled = true; }
    function selName(sel) {
        var o = sel.options[sel.selectedIndex];
        return (o && o.value) ? (o.dataset.name || o.textContent) : '';
    }

    function initWidget(root) {
        var p = root.dataset.prefix;
        var reg  = document.getElementById(p + '_reg');
        var prov = document.getElementById(p + '_prov');
        var city = document.getElementById(p + '_city');
        var brgy = document.getElementById(p + '_brgy');
        var errEl = root.querySelector('.psgc-err');
        if (!reg || !prov || !city) return;

        function showErr(msg) { if (errEl) { errEl.textContent = msg || ''; errEl.style.display = msg ? 'block' : 'none'; } }

        function dispatch() {
            var b = brgy ? brgy.value.trim().replace(/^(brgy\.?|barangay|bgy\.?)\s+/i, '') : '';
            root.dispatchEvent(new CustomEvent('psgc:change', {
                bubbles: true,
                detail: {
                    region:   selName(reg),
                    province: selName(prov),
                    city:     selName(city),
                    barangay: b
                }
            }));
        }

        loadData().then(function (d) {
            if (!d) { showErr('Couldn’t load the location list — please type your address in the fields below.'); return; }
            fill(reg, d.regions, 'Region…');
        });

        reg.addEventListener('change', function () {
            reset(prov, 'Province…'); reset(city, 'City / Municipality…');
            dispatch();
            if (!reg.value || !DATA) return;

            var provinces = DATA.provinces.filter(function (x) { return x.r === reg.value; });
            if (provinces.length === 0) {
                // NCR / provinceless region — cities hang directly off the region
                prov.disabled = true;
                var cs = DATA.cities.filter(function (x) { return x.r === reg.value; });
                fill(city, cs, 'City / Municipality…');
            } else {
                fill(prov, provinces, 'Province…');
            }
        });

        prov.addEventListener('change', function () {
            reset(city, 'City / Municipality…');
            dispatch();
            if (!prov.value || !DATA) return;
            var cs = DATA.cities.filter(function (x) { return x.p === prov.value; });
            fill(city, cs, 'City / Municipality…');
        });

        city.addEventListener('change', dispatch);
        if (brgy) brgy.addEventListener('input', dispatch);
    }

    window.PSGC = {
        init: function () {
            document.querySelectorAll('.psgc-widget').forEach(function (w) {
                if (!w.dataset.inited) { w.dataset.inited = '1'; initWidget(w); }
            });
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.PSGC.init);
    } else {
        window.PSGC.init();
    }
})();
