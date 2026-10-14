/* Atelier: Settings > Atelier page. Colour pickers, live preview panel (light and dark side by side), live preview of the
   whole admin page, and per-mailbox colour chips. Self-contained: it works even if theme.js is missing. */
(function () {
    var cfgEl = document.getElementById('at-cfg'), form = document.getElementById('at-settings'), bar = document.getElementById('at-preview-bar');
    if (!cfgEl || !form) { return; }
    var cfg;
    try { cfg = JSON.parse(cfgEl.textContent); } catch (e) { return; }

    /* ---- colour maths: keep identical to AtelierController::accentSet() in PHP ---- */
    function rgb2hsl(r, g, b) {
        r /= 255; g /= 255; b /= 255;
        var mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2, h, s, d;
        if (mx === mn) { return [0, 0, l]; }
        d = mx - mn;
        s = l > 0.5 ? d / (2 - mx - mn) : d / (mx + mn);
        if (mx === r) { h = (g - b) / d + (g < b ? 6 : 0); } else if (mx === g) { h = (b - r) / d + 2; } else { h = (r - g) / d + 4; }
        return [h * 60, s, l];
    }
    function hsl2rgb(h, s, l) {
        var c = (1 - Math.abs(2 * l - 1)) * s, hp = (h % 360) / 60, x = c * (1 - Math.abs((hp % 2) - 1)), r = 0, g = 0, b = 0;
        if (hp < 1) { r = c; g = x; } else if (hp < 2) { r = x; g = c; } else if (hp < 3) { g = c; b = x; }
        else if (hp < 4) { g = x; b = c; } else if (hp < 5) { r = x; b = c; } else { r = c; b = x; }
        var m = l - c / 2;
        return [Math.round((r + m) * 255), Math.round((g + m) * 255), Math.round((b + m) * 255)];
    }
    function lum(c) {
        var f = function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
        return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]);
    }
    function hex(c) { return '#' + c.map(function (v) { return ('0' + v.toString(16)).slice(-2); }).join(''); }
    function fit(h, s, l, bgLum, min, dir) {
        for (var i = 0; i < 60; i++) {
            var lm = lum(hsl2rgb(h, s, l)), ratio = (Math.max(lm, bgLum) + 0.05) / (Math.min(lm, bgLum) + 0.05);
            if (ratio >= min) { break; }
            l = Math.max(0.04, Math.min(0.96, l + dir * 0.01));
        }
        return l;
    }
    function norm(v) {
        v = String(v || '').toLowerCase().replace(/^\s+|\s+$/g, '');
        if (cfg.accents[v]) { return v; }
        var m = /^#?([0-9a-f]{6})$/.exec(v);
        return m ? '#' + m[1] : null;
    }
    function derive(value) {
        var n = norm(value) || 'azur';
        if (n.charAt(0) !== '#') { return cfg.accents[n]; }
        var rgb = [parseInt(n.substr(1, 2), 16), parseInt(n.substr(3, 2), 16), parseInt(n.substr(5, 2), 16)];
        var hsl = rgb2hsl(rgb[0], rgb[1], rgb[2]), h = hsl[0], s = hsl[1], l = hsl[2];
        s = s < 0.12 ? s : Math.max(0.35, Math.min(0.9, s));
        var bl = fit(h, s, Math.min(l, 0.5), 1.0, 4.6, -1), btn = hsl2rgb(h, s, bl), hov = hsl2rgb(h, s, Math.max(0.08, bl - 0.07));
        var ll = fit(h, s, bl, 0.9, 4.6, -1), lnk = hsl2rgb(h, s, ll), lnh = hsl2rgb(h, s, Math.max(0.08, ll - 0.09));
        var db = fit(h, s, Math.max(0.38, Math.min(l, 0.5)), 1.0, 4.5, -1), dbtn = hsl2rgb(h, s, db), dhov = hsl2rgb(h, s, Math.min(0.9, db + 0.05));
        var dlnk = hsl2rgb(h, s, 0.74), dlnh = hsl2rgb(h, s, 0.84);
        return {
            light: [hex(btn), hex(hov), hex(lnk), hex(lnh), 'rgba(' + btn.join(',') + ',.10)'],
            dark: [hex(dbtn), hex(dhov), hex(dlnk), hex(dlnh), 'rgba(' + dlnk.join(',') + ',.15)']
        };
    }
    window.__atDerive = derive; /* used by the parity test */

    /* ---- form helpers ---- */
    function val(n) {
        var e = form.querySelector('[name="' + n + '"]');
        if (!e) { return null; }
        if (e.type === 'radio') { var c = form.querySelector('[name="' + n + '"]:checked'); return c ? c.value : null; }
        return e.value;
    }
    var initial = { theme: val('theme_default'), palette: val('palette_default'), density: val('density_default'), accent: norm(val('accent')) };

    /* ---- preview panel and chips: colours computed here, so they do not depend on the page's own theme ---- */
    function tokens(th, pal, acc, dot) {
        var h = pal[0], s = pal[th === 'dark' ? 2 : 1] + '%', c = function (L) { return 'hsl(' + h + ' ' + s + ' ' + L + '%)'; }, a = acc[th];
        var T = th === 'dark'
            ? { page: c(8.5), surface: c(12), raised: c(15.5), line: c(19), text: c(93), muted: c(68), nav: c(10.5) }
            : { page: c(95.5), surface: c(99), raised: c(97), line: c(90.5), text: c(13), muted: c(37), nav: c(99) };
        T.accent = a[0]; T.link = a[2]; T.soft = a[4]; T.dot = dot || a[0];
        return T;
    }
    function paintEl(el, T) { for (var k in T) { if (T.hasOwnProperty(k)) { el.style.setProperty('--p-' + k, T[k]); } } }
    function paint() {
        var pal = cfg.palettes[val('palette_default')] || cfg.palettes.graphite, acc = derive(val('accent')), i;
        var pv = document.querySelectorAll('.at-pv');
        for (i = 0; i < pv.length; i++) { paintEl(pv[i], tokens(pv[i].getAttribute('data-th'), pal, acc)); }
        var rows = document.querySelectorAll('.at-mbx-row');
        var recolor = !!form.querySelector('[name="mbx_recolor"]:checked');
        for (i = 0; i < rows.length; i++) {
            var v = rows[i].querySelector('.at-pick-val').value, own = norm(v), set = own ? derive(own) : acc, chips = rows[i].querySelectorAll('.at-chip');
            for (var j = 0; j < chips.length; j++) {
                var th = chips[j].getAttribute('data-th'), dot = own ? (own.charAt(0) === '#' ? own : set[th][0]) : null;
                /* the button follows the mailbox colour only when "recolour inside the mailbox" is on */
                paintEl(chips[j], tokens(th, pal, own && recolor ? set : acc, dot));
                if (own) { chips[j].removeAttribute('data-none'); } else { chips[j].setAttribute('data-none', '1'); }
            }
        }
    }

    /* ---- pickers ---- */
    var AUTO = ['azur', 'terracotta', 'emerald', 'violet', 'gold', '#db2777', '#0d9488', 'bordeaux', 'indigo', '#ea580c', '#65a30d', '#0891b2', '#475569'];
    function colourOf(v) { var n = norm(v); if (!n) { return ''; } return n.charAt(0) === '#' ? n : (derive(n).light[0]); }
    function initPicker(box) {
        var hidden = box.querySelector('.at-pick-val'), custom = box.querySelector('.at-pick-custom'), cw = custom ? custom.parentNode : null;
        var sws = box.querySelectorAll('button.at-sw'), trig = box.querySelector('.at-pick-trigger'), dflt = box.getAttribute('data-default-label') || '';
        var labels = box.querySelectorAll('.at-pick-label'), dots = box.querySelectorAll('.at-pick-dot');
        function nameOf(cur) {
            for (var i = 0; i < sws.length; i++) { if (norm(sws[i].getAttribute('data-v')) === cur && cur !== null) { return sws[i].getAttribute('data-label'); } }
            return cur ? cur.toUpperCase() : dflt;
        }
        function mark() {
            var cur = norm(hidden.value), any = false, i;
            for (i = 0; i < sws.length; i++) {
                var dv = sws[i].getAttribute('data-v'), on = dv === '' ? hidden.value === '' : (norm(dv) === cur && cur !== null);
                sws[i].classList.toggle('on', on); if (on) { any = true; }
            }
            if (cw) {
                var isCustom = !any && cur !== null;
                cw.classList.toggle('on', isCustom);
                cw.style.background = isCustom ? cur : '';
                if (isCustom) { custom.value = cur; }
            }
            var c = colourOf(hidden.value), t = nameOf(cur);
            for (i = 0; i < labels.length; i++) { labels[i].textContent = t; }
            for (i = 0; i < dots.length; i++) { dots[i].style.background = c || ''; }
        }
        function close() { box.classList.remove('at-open'); if (trig) { trig.setAttribute('aria-expanded', 'false'); } }
        function set(v, quiet) {
            hidden.value = v; mark();
            if (!quiet) { hidden.dispatchEvent(new Event('change', { bubbles: true })); }
        }
        box.__atSet = set;
        for (var i = 0; i < sws.length; i++) {
            sws[i].addEventListener('click', (function (b) { return function (e) { e.preventDefault(); set(b.getAttribute('data-v')); if (trig) { close(); } }; })(sws[i]));
        }
        if (custom) { custom.addEventListener('input', function () { set(custom.value); }); }
        if (trig) {
            trig.addEventListener('click', function (e) {
                e.preventDefault(); e.stopPropagation();
                var open = !box.classList.contains('at-open');
                [].forEach.call(document.querySelectorAll('.at-pick.at-open'), function (o) { o.classList.remove('at-open'); });
                if (open) { box.classList.add('at-open'); trig.setAttribute('aria-expanded', 'true'); }
            });
            box.addEventListener('click', function (e) { e.stopPropagation(); });
        }
        mark();
    }
    [].forEach.call(document.querySelectorAll('.at-pick'), initPicker);
    document.addEventListener('click', function () { [].forEach.call(document.querySelectorAll('.at-pick.at-open'), function (o) { o.classList.remove('at-open'); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { [].forEach.call(document.querySelectorAll('.at-pick.at-open'), function (o) { o.classList.remove('at-open'); }); } });

    /* ---- mailbox tools: automatic distinct colours / reset ---- */
    function eachMbx(fn) {
        var rows = document.querySelectorAll('.at-mbx-row .at-pick'), i;
        for (i = 0; i < rows.length; i++) { fn(rows[i], i); }
        paint(); if (bar) { bar.style.display = 'flex'; }
    }
    var au = document.getElementById('at-mbx-auto'), rs = document.getElementById('at-mbx-reset');
    if (au) { au.addEventListener('click', function () { eachMbx(function (box, i) { box.__atSet(AUTO[i % AUTO.length], true); }); }); }
    if (rs) { rs.addEventListener('click', function () { eachMbx(function (box) { box.__atSet('', true); }); }); }

    /* ---- confirmations without inline handlers (FreeScout's CSP blocks them): any form with data-confirm ---- */
    [].forEach.call(document.querySelectorAll('form[data-confirm]'), function (f) {
        f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm') || '')) { e.preventDefault(); } });
    });

    /* ---- live preview of the whole page (theme.js applies it), only for what was actually changed ---- */
    function pagePreview() {
        if (!window.Atelier) { return; }
        var cur = { theme: val('theme_default'), palette: val('palette_default'), density: val('density_default'), accent: norm(val('accent')) };
        window.Atelier.preview({
            theme: cur.theme !== initial.theme ? cur.theme : null,
            palette: cur.palette !== initial.palette ? cur.palette : null,
            density: cur.density !== initial.density ? cur.density : null,
            accent: cur.accent !== initial.accent ? derive(cur.accent) : null
        });
    }
    function onChange() { paint(); pagePreview(); if (bar) { bar.style.display = 'flex'; } }
    form.addEventListener('change', onChange);
    form.addEventListener('input', function (e) { if (e.target && e.target.type === 'color') { onChange(); } });
    var sv = document.getElementById('at-pv-save'), cn = document.getElementById('at-pv-cancel');
    if (sv) { sv.addEventListener('click', function () { form.submit(); }); }
    if (cn) { cn.addEventListener('click', function () { window.location.reload(); }); }
    paint();
})();
