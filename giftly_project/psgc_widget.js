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
    }
    function getJSON(url) {
        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () { return []; });
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
        if (!reg || !prov || !city || !brgy) return;

        function dispatch() {
            var detail = {
                region:   selName(reg),
                province: selName(prov),
                city:     selName(city),
                barangay: selName(brgy)
            };
            root.dispatchEvent(new CustomEvent('psgc:change', { bubbles: true, detail: detail }));
        }

        getJSON('psgc.php?type=regions').then(function (d) { fill(reg, d, 'Region…'); });

        reg.addEventListener('change', function () {
            reset(prov, 'Province…'); reset(city, 'City / Municipality…'); reset(brgy, 'Barangay…');
            dispatch();
            if (!reg.value) return;
            if (reg.value === NCR) {
                getJSON('psgc.php?type=cities-region&code=' + reg.value)
                    .then(function (d) { fill(city, d, 'City / Municipality…'); });
                return;
            }
            getJSON('psgc.php?type=provinces&code=' + reg.value).then(function (d) {
                if (!d || d.length === 0) {
                    getJSON('psgc.php?type=cities-region&code=' + reg.value)
                        .then(function (c) { fill(city, c, 'City / Municipality…'); });
                } else {
                    fill(prov, d, 'Province…');
                }
            });
        });

        prov.addEventListener('change', function () {
            reset(city, 'City / Municipality…'); reset(brgy, 'Barangay…');
            dispatch();
            if (prov.value) {
                getJSON('psgc.php?type=cities&code=' + prov.value)
                    .then(function (d) { fill(city, d, 'City / Municipality…'); });
            }
        });

        city.addEventListener('change', function () {
            reset(brgy, 'Barangay…');
            dispatch();
            if (city.value) {
                getJSON('psgc.php?type=barangays&code=' + city.value)
                    .then(function (d) { fill(brgy, d, 'Barangay…'); });
            }
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
