/* Cascading Philippine address picker (Region -> Province -> City/Mun -> Barangay).
   Data via psgc.php (server-side proxy of the free PSGC API).
   Each .psgc-widget dispatches a `psgc:change` CustomEvent with
   detail = { region, province, city, barangay } (names). */
(function () {
    var NCR = '130000000';

    function opt(v, t) { var o = document.createElement('option'); o.value = v; o.textContent = t; return o; }

    function reset(sel, ph) {
        sel.innerHTML = '';
        sel.appendChild(opt('', ph));
        sel.disabled = true;
    }
    function fill(sel, items, ph) {
        sel.innerHTML = '';
        sel.appendChild(opt('', ph));
        (items || []).forEach(function (i) {
            var o = opt(i.code, i.name);
            o.dataset.name = i.name;
            sel.appendChild(o);
        });
        sel.disabled = !items || items.length === 0;
        return sel.disabled;
    }

    // fetch a list; one automatic retry; returns [] on failure
    function getList(qs, tries) {
        tries = tries || 0;
        return fetch('psgc.php?' + qs, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (Array.isArray(d)) return d;
                if (d && Array.isArray(d.list)) return d.list;
                return [];
            })
            .catch(function () { return []; })
            .then(function (list) {
                if (list.length === 0 && tries < 1) {
                    return new Promise(function (res) { setTimeout(res, 600); })
                        .then(function () { return getList(qs, tries + 1); });
                }
                return list;
            });
    }

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
        if (!reg || !prov || !city || !brgy) return;

        function showErr(msg) { if (errEl) { errEl.textContent = msg || ''; errEl.style.display = msg ? 'block' : 'none'; } }

        function dispatch() {
            root.dispatchEvent(new CustomEvent('psgc:change', {
                bubbles: true,
                detail: {
                    region: selName(reg), province: selName(prov),
                    city: selName(city), barangay: selName(brgy)
                }
            }));
        }

        getList('type=regions').then(function (d) {
            fill(reg, d, 'Region…');
            if (d.length === 0) showErr('Couldn’t load the location list right now — you can type your address in the fields below.');
        });

        function loadCitiesFor(kind, code) {
            city.disabled = true;
            return getList('type=' + kind + '&code=' + code).then(function (d) {
                var stuck = fill(city, d, 'City / Municipality…');
                showErr(stuck ? 'Couldn’t load cities — type your city/municipality below.' : '');
                return d;
            });
        }

        reg.addEventListener('change', function () {
            reset(prov, 'Province…'); reset(city, 'City / Municipality…'); reset(brgy, 'Barangay…');
            showErr('');
            dispatch();
            if (!reg.value) return;

            if (reg.value === NCR) {
                prov.disabled = true;
                loadCitiesFor('cities-region', reg.value);
                return;
            }
            getList('type=provinces&code=' + reg.value).then(function (d) {
                if (d.length === 0) {
                    // some regions expose cities directly
                    loadCitiesFor('cities-region', reg.value);
                } else {
                    fill(prov, d, 'Province…');
                }
            });
        });

        prov.addEventListener('change', function () {
            reset(city, 'City / Municipality…'); reset(brgy, 'Barangay…');
            showErr('');
            dispatch();
            if (!prov.value) return;
            loadCitiesFor('cities', prov.value).then(function (d) {
                if (d.length === 0) loadCitiesFor('cities-region', reg.value); // fallback
            });
        });

        city.addEventListener('change', function () {
            reset(brgy, 'Barangay…');
            dispatch();
            if (!city.value) return;
            getList('type=barangays&code=' + city.value).then(function (d) {
                fill(brgy, d, 'Barangay…');
            });
        });

        brgy.addEventListener('change', dispatch);
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
