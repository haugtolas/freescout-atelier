/* Atelier: light / dark / auto theme + e-mail display.
   Loaded in <head> (external file) so that the theme is applied before the first paint.
   Per-browser choices in localStorage: at-theme (auto|light|dark), at-mail (adapted|paper). */
(function () {
    var root = document.documentElement;
    var DARK_TOKEN = /(^|[^a-z0-9])(dark|night)([-_ ]?(mode|theme|skin|ui))?($|[^a-z0-9])/i;
    var DARK_SAFE = /(link|table|btn|text|bg|navbar|label)-dark|dark-header|dark-?(green|blue|red)/i;
    /* some dark-mode modules switch on a bare ".dm" class (".dm .navbar-default {...}"); FreeScout's own ".dm-scrollable" is not one */
    var DM_SEL = /(^|[\s,>+~(])\.dm(?![\w-])/;
    var ATTRS = ['data-theme', 'data-bs-theme', 'data-color-scheme', 'data-color-mode', 'data-mode', 'data-dark', 'theme', 'color-scheme'];
    var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function meta(name, def) {
        var m = document.querySelector('meta[name="' + name + '"]');
        return (m && m.content) || def;
    }
    function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function put(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

    /* A default changed in Settings > Atelier applies to everyone once: a personal choice made before that change is dropped */
    (function dropStale() {
        var raw = meta('atelier-stamps', ''); if (!raw) { return; }
        var st; try { st = JSON.parse(raw); } catch (e) { return; }
        var seen; try { seen = JSON.parse(get('at-stamps') || '{}') || {}; } catch (e) { seen = {}; }
        var keys = { theme: 'at-theme', mail: 'at-mail', palette: 'at-palette', density: 'at-density', glass: 'at-glass' };
        var changed = false;
        for (var k in keys) {
            if (st[k] && seen[k] !== st[k]) {
                try { localStorage.removeItem(keys[k]); } catch (e) {}
                seen[k] = st[k]; changed = true;
            }
        }
        if (changed) { put('at-stamps', JSON.stringify(seen)); }
    })();

    var ovr = null; /* live preview from Settings > Atelier: {theme, palette, density, accent} */
    function themeMode() { if (ovr && ovr.theme) { return ovr.theme; } var s = get('at-theme'); return (s === 'auto' || s === 'light' || s === 'dark') ? s : meta('atelier-theme', 'auto'); }
    function mailMode() { var s = get('at-mail'); return (s === 'adapted' || s === 'paper') ? s : meta('atelier-mail', 'adapted'); }

    var PALS = ['graphite', 'ardoise', 'minuit', 'foret', 'sable'];
    function palMode() { if (ovr && ovr.palette) { return ovr.palette; } var s = get('at-palette'); return PALS.indexOf(s) > -1 ? s : meta('atelier-palette', 'graphite'); }
    function glassMode() { var s = get('at-glass'); return (s === 'on' || s === 'off') ? s : (meta('atelier-glass', 'off') === 'on' ? 'on' : 'off'); }
    function denMode() { if (ovr && ovr.density) { return ovr.density; } var s = get('at-density'); return (s === 'comfortable' || s === 'compact') ? s : meta('atelier-density', 'comfortable'); }

    /* browser chrome on phones: status bar / address bar colour and form-control scheme follow the theme, not the phone */
    function setMeta(name, content) {
        var head = document.head || document.getElementsByTagName('head')[0];
        if (!head) { return; }
        var m = head.querySelector('meta[name="' + name + '"][data-at]');
        if (!m) { m = document.createElement('meta'); m.setAttribute('name', name); m.setAttribute('data-at', '1'); head.appendChild(m); }
        m.setAttribute('content', content);
    }
    function syncBrowserChrome(dark) {
        var fs = document.querySelectorAll('meta[name="color-scheme"]:not([data-at])');
        for (var f = 0; f < fs.length; f++) { fs[f].parentNode.removeChild(fs[f]); }
        setMeta('color-scheme', dark ? 'dark' : 'light');
        var old = document.querySelectorAll('meta[name="theme-color"]:not([data-at])');
        for (var i = 0; i < old.length; i++) { old[i].parentNode.removeChild(old[i]); }
        setMeta('theme-color', dark ? '#17181b' : '#fbfbfc');
        if (document.body) {
            var probe = document.createElement('i');
            probe.style.cssText = 'position:absolute;width:0;height:0;background:var(--at-nav)';
            document.body.appendChild(probe);
            var c = getComputedStyle(probe).backgroundColor;
            document.body.removeChild(probe);
            if (c && c.indexOf('rgb') === 0) { setMeta('theme-color', c); }
        }
    }

    /* printing: always light (white paper, least ink), whatever the screen theme. The print view of a conversation
       (?print=1) is light from the start; the browser's Print command flips the page just for the print job. */
    var printing = false, lastDark = null;
    /* Safari can leave parts of the page painted with the previous theme after the theme flips (the system switching
       at sunrise, a page restored from the back/forward cache): a short opacity change makes it repaint everything */
    function repaint() {
        var b = document.body;
        if (!b) { return; }
        b.style.setProperty('opacity', '0.995');
        void b.offsetHeight;
        var done = function () { b.style.removeProperty('opacity'); };
        if (window.requestAnimationFrame) { requestAnimationFrame(function () { requestAnimationFrame(done); }); } else { setTimeout(done, 50); }
    }
    function printMode() { return printing || /[?&]print=/.test(location.search || ''); }
    function apply() {
        var m = themeMode();
        var dark = !printMode() && (m === 'dark' || (m === 'auto' && mq && mq.matches));
        root.setAttribute('data-at-theme', dark ? 'dark' : 'light');
        if (lastDark !== null && lastDark !== dark) { repaint(); }
        lastDark = dark;
        root.setAttribute('data-at-mail', mailMode());
        root.setAttribute('data-at-palette', palMode());
        root.setAttribute('data-at-density', denMode());
        root.setAttribute('data-at-glass', glassMode());
        root.setAttribute('data-at-icons', meta('atelier-icons', '1') === '1' ? 'modern' : 'off');
        root.style.setProperty('color-scheme', dark ? 'dark' : 'light', 'important');
        stripForeign();
        applyAccentPreview(dark);
        mbxPage();
        syncBrowserChrome(dark);
        if (dark) { clearLight(); schedGuard(); } else { clearGuard(); schedLight(); }
        var items = document.querySelectorAll('.at-theme-menu a[data-at-theme], .at-theme-menu a[data-at-mail], .at-theme-menu a[data-at-pal], .at-theme-menu a[data-at-density], .at-theme-menu a[data-at-glass]');
        for (var i = 0; i < items.length; i++) {
            var a = items[i], on;
            if (a.hasAttribute('data-at-theme')) { on = a.getAttribute('data-at-theme') === m; }
            else if (a.hasAttribute('data-at-mail')) { on = a.getAttribute('data-at-mail') === mailMode(); }
            else if (a.hasAttribute('data-at-pal')) { on = a.getAttribute('data-at-pal') === palMode(); a.classList.toggle('at-sel', on); }
            else if (a.hasAttribute('data-at-glass')) { on = a.getAttribute('data-at-glass') === glassMode(); }
            else { on = a.getAttribute('data-at-density') === denMode(); }
            if (!a.hasAttribute('data-at-pal')) { a.parentNode.classList.toggle('at-on', on); }
        }
    }
    /* ---- per-mailbox accent: [data-at-mbx-accent] on <html> (mailbox pages) and on dashboard cards; colours come from vars.css ---- */
    var MBX = null;
    function mbxMap() {
        if (MBX === null) { try { MBX = JSON.parse(meta('atelier-mbx-map', '{}')) || {}; } catch (e) { MBX = {}; } }
        return MBX;
    }
    function mbxPage() {
        var map = mbxMap(), id = meta('atelier-mbx', '0');
        if (map[id]) { root.setAttribute('data-at-mbx-accent', map[id]); } else { root.removeAttribute('data-at-mbx-accent'); }
    }
    function mbxCards() {
        var map = mbxMap();
        if (!map || !Object.keys(map).length) { return; }
        var cards = document.querySelectorAll('.dash-card:not([data-at-mbx-done])');
        for (var i = 0; i < cards.length; i++) {
            var c = cards[i], a = c.querySelector('a[href*="/mailbox/"]'), m = a ? /\/mailbox\/(\d+)/.exec(a.getAttribute('href')) : null;
            if (!a) { continue; }
            c.setAttribute('data-at-mbx-done', '1');
            if (m && map[m[1]]) { c.setAttribute('data-at-mbx-accent', map[m[1]]); }
        }
    }
    function applyAccentPreview(dark) {
        var names = ['--at-accent', '--at-accent-h', '--at-link', '--at-link-h', '--at-soft'], i;
        if (!ovr || !ovr.accent) { for (i = 0; i < names.length; i++) { root.style.removeProperty(names[i]); } return; }
        var set = ovr.accent[dark ? 'dark' : 'light'];
        for (i = 0; i < names.length; i++) { root.style.setProperty(names[i], set[i]); }
    }
    window.Atelier = { preview: function (o) { ovr = o || null; apply(); } };

    /* ---- watchdog: the accent / mailbox colours live in <style id="atelier-vars"> and the theme in atelier.css. If another
       script (a second dark-mode module, an extension) removes or empties them, put them back at once. ---- */
    (function () {
        var savedCss = null, savedHref = null;
        function snap() {
            var s = document.getElementById('atelier-vars');
            if (s && s.textContent) { savedCss = s.textContent; }
            var l = document.querySelector('link[rel="stylesheet"][href*="atelier.css"]');
            if (l) { savedHref = l.getAttribute('href'); }
        }
        function restore() {
            if (!document.head) { return; }
            var s = document.getElementById('atelier-vars');
            if (savedCss && (!s || !s.textContent)) {
                if (!s) { s = document.createElement('style'); s.id = 'atelier-vars'; document.head.appendChild(s); }
                s.textContent = savedCss;
            }
            if (savedHref && !document.querySelector('link[rel="stylesheet"][href*="atelier.css"]')) {
                var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = savedHref; document.head.insertBefore(l, document.head.firstChild);
            }
        }
        function start() {
            snap();
            if (window.MutationObserver) {
                new MutationObserver(function () { snap(); restore(); }).observe(document.head, { childList: true, subtree: true, characterData: true });
            }
            window.addEventListener('pageshow', function () { snap(); restore(); });
        }
        if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
    })();

    apply();
    if (mq && mq.addEventListener) { mq.addEventListener('change', apply); }
    window.addEventListener('pageshow', function (e) { if (e.persisted) { apply(); repaint(); } });
    (function () {
        function on() { if (!printing) { printing = true; apply(); } }
        function off() { if (printing) { printing = false; apply(); } }
        window.addEventListener('beforeprint', on);
        window.addEventListener('afterprint', off);
        try { /* Safari / older engines: the print media query changes when the print preview opens */
            var pm = window.matchMedia('print');
            var h = function (e) { if (e.matches) { on(); } else { off(); } };
            if (pm.addEventListener) { pm.addEventListener('change', h); } else if (pm.addListener) { pm.addListener(h); }
        } catch (e) {}
    })();

    var L = {};
    function t(k) { return L[k] || k; }


    /* ---- contrast guard (dark theme) ----
       Third-party modules ship their own light boxes (white background, light-grey text). Their CSS is not known to the
       theme, so any neutral light background found outside the e-mail body is flipped to the dark surface, and dark text
       inside it is lightened. Coloured badges and pills (saturated backgrounds) are left alone. */
    function rgbOf(str) {
        var m = /rgba?\(([^)]+)\)/.exec(str || '');
        if (!m) { return null; }
        var p = m[1].split(/[ ,\/]+/).filter(Boolean).map(parseFloat);
        return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
    }
    function lum(c) { return (0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b) / 255; }
    function sat(c) { var mx = Math.max(c.r, c.g, c.b), mn = Math.min(c.r, c.g, c.b); return mx === 0 ? 0 : (mx - mn) / mx; }
    var guardTimer = null, SKIP = 'img, svg, video, canvas, iframe, script, style, .at-mail, .at-mail *, .note-editable, .note-editable *, .at-mail-toggle, .icon-bar, .navbar-toggle, .navbar-toggle *, .onoffswitch, .onoffswitch *, input, .caret, .at-keep, .at-keep *';
    function guardEl(el) {
        var cs = getComputedStyle(el), bg = rgbOf(cs.backgroundColor), flipped = false;
        if (bg && bg.a > 0.5 && lum(bg) > 0.72 && sat(bg) < 0.22 && cs.backgroundImage === 'none') {
            el.style.setProperty('background-color', 'var(--at-surface)', 'important');
            el.setAttribute('data-at-fix', '1'); flipped = true;
        }
        return flipped;
    }
    function guardText(root) {
        var all = root.querySelectorAll('*');
        for (var i = 0; i < all.length; i++) {
            var el = all[i];
            if (el.matches(SKIP) || el.hasAttribute('data-at-fc')) { continue; }
            var cs = getComputedStyle(el), c = rgbOf(cs.color), bg = rgbOf(cs.backgroundColor);
            if (c && lum(c) < 0.42 && !(bg && bg.a > 0.5 && sat(bg) >= 0.22)) {
                el.style.setProperty('color', 'var(--at-text)', 'important'); el.setAttribute('data-at-fc', '1');
            }
        }
    }
    function runGuard() {
        guardTimer = null;
        if (root.getAttribute('data-at-theme') !== 'dark') { return; }
        var app = document.getElementById('app') || document.body;
        if (!app) { schedGuard(); return; }
        var els = app.querySelectorAll('*:not([data-at-seen])');
        var flippedRoots = [];
        for (var i = 0; i < els.length && i < 6000; i++) {
            var el = els[i];
            el.setAttribute('data-at-seen', '1');
            if (el.matches(SKIP)) { continue; }
            if (guardEl(el)) { flippedRoots.push(el); }
        }
        for (var j = 0; j < flippedRoots.length; j++) { guardText(flippedRoots[j]); }
    }
    function schedGuard() { if (!guardTimer) { guardTimer = setTimeout(runGuard, 150); } }
    function clearGuard() {
        var f = document.querySelectorAll('[data-at-fix],[data-at-fc],[data-at-seen]');
        for (var i = 0; i < f.length; i++) {
            if (f[i].hasAttribute('data-at-fix')) { f[i].style.removeProperty('background-color'); }
            if (f[i].hasAttribute('data-at-fc')) { f[i].style.removeProperty('color'); }
            f[i].removeAttribute('data-at-fix'); f[i].removeAttribute('data-at-fc'); f[i].removeAttribute('data-at-seen');
        }
    }

    /* ---- hide the switch of another dark-mode module (the theme is Atelier's) ---- */
    function hideOtherToggle() {
        if (meta('atelier-hide-toggle', '1') !== '1') { return; }
        var lis = document.querySelectorAll('.navbar .navbar-nav > li:not(.at-theme-li):not(.at-hidden)');
        for (var i = 0; i < lis.length; i++) {
            var li = lis[i];
            if (li.querySelector('.dropdown-menu')) { continue; }
            var html = li.innerHTML;
            if (/mode sombre|dark.?mode|darkmode|dark-theme|theme-switch/i.test(html) || (li.querySelector('input[type="checkbox"]') && !li.querySelector('a[href^="http"], a[href^="/"]'))) {
                li.classList.add('at-hidden');
            }
        }
    }

    /* ---- theme menu in the top bar ---- */
    function addMenu() {
        var right = document.querySelector('.navbar .navbar-right');
        if (!right || right.querySelector('.at-theme-li')) { return; }
        var li = document.createElement('li');
        var palLinks = '';
        for (var p = 0; p < PALS.length; p++) {
            palLinks += '<a href="#" data-at-pal="' + PALS[p] + '" title="' + esc(t(PALS[p])) + '" aria-label="' + esc(t(PALS[p])) + '"><span class="at-pal-sw"></span></a>';
        }
        li.className = 'dropdown at-theme-li';
        li.innerHTML =
            '<a href="#" class="dropdown-toggle dropdown-toggle-icon" data-toggle="dropdown" role="button" aria-expanded="false" title="' + esc(t('theme')) + '"><i class="glyphicon glyphicon-adjust"></i></a>' +
            '<ul class="dropdown-menu at-theme-menu">' +
            '<li class="dropdown-header">' + esc(t('theme')) + '</li>' +
            '<li><a href="#" data-at-theme="auto">' + esc(t('auto')) + '</a></li>' +
            '<li><a href="#" data-at-theme="light">' + esc(t('light')) + '</a></li>' +
            '<li><a href="#" data-at-theme="dark">' + esc(t('dark')) + '</a></li>' +
            '<li class="divider"></li>' +
            '<li class="dropdown-header">' + esc(t('mail')) + '</li>' +
            '<li><a href="#" data-at-mail="adapted">' + esc(t('adapted')) + '</a></li>' +
            '<li><a href="#" data-at-mail="paper">' + esc(t('paper')) + '</a></li>' +
            '<li class="divider"></li>' +
            '<li class="dropdown-header">' + esc(t('palette')) + '</li>' +
            '<li class="at-pal">' + palLinks + '</li>' +
            '<li class="divider"></li>' +
            '<li class="dropdown-header">' + esc(t('density')) + '</li>' +
            '<li><a href="#" data-at-density="comfortable">' + esc(t('comfortable')) + '</a></li>' +
            '<li><a href="#" data-at-density="compact">' + esc(t('compact')) + '</a></li>' +
            '<li class="divider"></li>' +
            '<li class="dropdown-header">' + esc(t('effect')) + '</li>' +
            '<li><a href="#" data-at-glass="off">' + esc(t('standard')) + '</a></li>' +
            '<li><a href="#" data-at-glass="on">' + esc(t('glass')) + '</a></li>' +
            '</ul>';
        right.insertBefore(li, right.firstChild);
        li.addEventListener('click', function (e) {
            var a = e.target.closest ? e.target.closest('a[data-at-theme], a[data-at-mail], a[data-at-pal], a[data-at-density], a[data-at-glass]') : null;
            if (!a) { return; }
            e.preventDefault();
            if (a.hasAttribute('data-at-theme')) { put('at-theme', a.getAttribute('data-at-theme')); }
            else if (a.hasAttribute('data-at-pal')) { put('at-palette', a.getAttribute('data-at-pal')); }
            else if (a.hasAttribute('data-at-density')) { put('at-density', a.getAttribute('data-at-density')); }
            else if (a.hasAttribute('data-at-glass')) { put('at-glass', a.getAttribute('data-at-glass')); }
            else { put('at-mail', a.getAttribute('data-at-mail')); clearOverrides(); }
            apply();
        });
        apply();
    }
    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function clearOverrides() {
        var o = document.querySelectorAll('.thread-message.at-flip');
        for (var i = 0; i < o.length; i++) { o[i].classList.remove('at-flip'); }
    }

    /* ---- e-mail reader: wrap the customer's HTML so that only it is recoloured, and add a per-message switch ---- */
    var KEEP = '.thread-attachments, .alert, .thread-meta';
    function wrapMail(body) {
        if (body.getAttribute('data-at') || body.querySelector(':scope > .at-mail')) { body.setAttribute('data-at', '1'); return; }
        body.setAttribute('data-at', '1');
        var wrap = document.createElement('div');
        wrap.className = 'at-mail';
        var nodes = Array.prototype.slice.call(body.childNodes), move = [], i;
        for (i = 0; i < nodes.length; i++) {
            if (nodes[i].nodeType === 1 && nodes[i].matches && nodes[i].matches(KEEP)) { continue; }
            move.push(nodes[i]);
        }
        /* the wrapper takes the place of the first piece it receives: a notice a module put ABOVE the message
           (e.g. "external images are blocked, display them") stays above it, one put below stays below */
        /* ...but blank text between a leading notice (e.g. "This is a bounce message") and the message is not "a piece":
           anchoring on it would leave the notice BELOW the message */
        var anchor = null;
        for (i = 0; i < move.length; i++) {
            if (move[i].nodeType === 1 || (move[i].nodeType === 3 && /\S/.test(move[i].nodeValue || ''))) { anchor = move[i]; break; }
        }
        if (anchor) { body.insertBefore(wrap, anchor); } else { body.appendChild(wrap); }
        for (i = 0; i < move.length; i++) { wrap.appendChild(move[i]); }
        var msg = body.closest('.thread-message');
        if (msg && !msg.querySelector('.at-mail-toggle')) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'at-mail-toggle';
            b.title = t('toggle');
            b.setAttribute('aria-label', t('toggle'));
            b.innerHTML = '<i class="glyphicon glyphicon-adjust"></i>';
            b.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); msg.classList.toggle('at-flip'); });
            /* a zero-height strip right above the message frame: the button straddles the frame's top edge,
               clear of the header (date, options) and of the message text */
            var bar = document.createElement('div');
            bar.className = 'at-mail-bar';
            bar.appendChild(b);
            body.insertBefore(bar, wrap);
        }
    }

    /* ---- logo / banner ink: dark, light or coloured artwork? (decides if it must be inverted in a theme) ---- */
    function inkOf(img) {
        try {
            var w = img.naturalWidth || 0, h = img.naturalHeight || 0;
            if (!w || !h) { return null; }
            var k = Math.min(1, 64 / Math.max(w, h)), cw = Math.max(1, Math.round(w * k)), ch = Math.max(1, Math.round(h * k));
            var cv = document.createElement('canvas'); cv.width = cw; cv.height = ch;
            var cx = cv.getContext('2d'); cx.drawImage(img, 0, 0, cw, ch);
            var d = cx.getImageData(0, 0, cw, ch).data, n = 0, l = 0, sa = 0;
            for (var i = 0; i < d.length; i += 4) {
                if (d[i + 3] < 40) { continue; }
                var r = d[i], g = d[i + 1], b = d[i + 2], mx = Math.max(r, g, b), mn = Math.min(r, g, b);
                l += (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255; sa += mx === 0 ? 0 : (mx - mn) / mx; n++;
            }
            if (!n) { return null; }
            l /= n; sa /= n;
            // an opaque white / near-white background would hide the artwork: judge only what is not a flat backdrop
            if (sa > 0.45) { return 'color'; }
            if (l < 0.42) { return 'dark'; }
            if (l > 0.7) { return 'light'; }
            return 'color';
        } catch (e) { return null; }
    }
    function markInk(img) {
        if (img.getAttribute('data-at-ink-done')) { return; }
        var run = function () {
            if (img.getAttribute('data-at-ink-done')) { return; }
            img.setAttribute('data-at-ink-done', '1');
            var ink = inkOf(img);
            if (ink) { img.setAttribute('data-at-ink', ink); }
        };
        if (img.complete && img.naturalWidth) { run(); } else { img.addEventListener('load', run, { once: true }); }
    }
    function scanInk() {
        var sel = '.banner img' + (meta('atelier-logo-auto', '0') === '1' ? ', .navbar-brand img' : '');
        var imgs = document.querySelectorAll(sel);
        for (var i = 0; i < imgs.length; i++) { markInk(imgs[i]); }
    }

    /* ---- e-mail readability guard ----
       Customers' HTML can carry its own faint colours (light grey on white, grey on grey...). Contrast is checked on the
       original (pre-filter) colours, so it holds in every mode: light, dark adapted, dark original. Text that falls under
       4.5:1 is darkened / lightened just enough, keeping its hue. */
    function lumOf(c) {
        var f = function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
        return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b);
    }
    function ratio(a, b) { var la = lumOf(a), lb = lumOf(b); if (la < lb) { var t = la; la = lb; lb = t; } return (la + 0.05) / (lb + 0.05); }
    function blend(fg, bg, a) { return { r: fg.r * a + bg.r * (1 - a), g: fg.g * a + bg.g * (1 - a), b: fg.b * a + bg.b * (1 - a) }; }
    function backdrop(el) {
        for (var n = el; n && n.nodeType === 1; n = n.parentElement) {
            var st = getComputedStyle(n), c = rgbOf(st.backgroundColor);
            if (c && c.a > 0.5) { return c; }
            /* text on a gradient / picture: its real backdrop is unknown, leave the author's colours alone */
            if (st.backgroundImage && st.backgroundImage !== 'none') { return null; }
            if (n === document.documentElement) { break; }
        }
        return { r: 255, g: 255, b: 255, a: 1 };
    }
    function chainOpacity(el, stop) {
        var o = 1;
        for (var n = el; n && n !== stop && n.nodeType === 1; n = n.parentElement) { o *= parseFloat(getComputedStyle(n).opacity) || 1; }
        return o;
    }
    function fixMailEl(el, root) {
        var cs = getComputedStyle(el), c = rgbOf(cs.color);
        if (!c) { return; }
        var bg = backdrop(el);
        if (!bg) { return; }
        var op = chainOpacity(el, root.parentElement) * (c.a === undefined ? 1 : c.a);
        var eff = blend(c, bg, op);
        if (ratio(eff, bg) >= 5.5) { return; } /* the dark "adapted" filter flattens contrast a little: keep a margin over 4.5 */
        var dark = lumOf(bg) > 0.4, tgt = dark ? 0 : 255, out = null;
        for (var k = 1; k >= 0; k -= 0.05) {
            var t = { r: c.r * k + tgt * (1 - k), g: c.g * k + tgt * (1 - k), b: c.b * k + tgt * (1 - k) };
            if (ratio(t, bg) >= 8) { out = t; break; }
        }
        if (!out) { out = { r: tgt, g: tgt, b: tgt }; }
        el.style.setProperty('color', 'rgb(' + Math.round(out.r) + ',' + Math.round(out.g) + ',' + Math.round(out.b) + ')', 'important');
        el.setAttribute('data-at-mc', '1');
        if ((parseFloat(cs.opacity) || 1) < 0.95) { el.style.setProperty('opacity', '1', 'important'); }
    }
    function guardMail(root) {
        if (root.getAttribute('data-at-mc-done')) { return; }
        root.setAttribute('data-at-mc-done', '1');
        var els = root.querySelectorAll('*'), n = 0;
        for (var i = 0; i < els.length && n < 4000; i++) {
            var el = els[i];
            if (/^(SCRIPT|STYLE|IMG|SVG|HR|BR|TABLE|TBODY|TR)$/i.test(el.tagName)) { continue; }
            var has = false;
            for (var j = 0; j < el.childNodes.length; j++) {
                if (el.childNodes[j].nodeType === 3 && /\S/.test(el.childNodes[j].nodeValue)) { has = true; break; }
            }
            if (has) { fixMailEl(el, root); n++; }
        }
        fixMailEl(root, root);
    }
    /* ---- buttons made of an icon / symbol and a label: centre the content exactly, whatever the module's own padding or line-height ----
       Keeps the button's current height (min-height) and removes the vertical padding, so the label sits in the middle even when a
       third-party module gave the button uneven padding or a symbol glyph from a fallback font. Mixed rich content is left alone. */
    var CB_SKIP = '.navbar *, .btn-group-justified *, .dropdown-menu *, .note-toolbar *, .select2-container *, .at-theme-menu *, .at-mail *, .note-editable *, .btn-group-vertical *, .at-keep *, .pagination *';
    function centrable(el) {
        if (el.matches(CB_SKIP) || el.querySelector('.caret, .dropdown-toggle, input, select, textarea, br, p, div, ul, table')) { return false; }
        var kids = el.children, text = 0, i;
        if (kids.length > 3) { return false; }
        for (i = 0; i < kids.length; i++) { if (!/^(I|SVG|IMG|SPAN|B|STRONG|EM)$/i.test(kids[i].tagName)) { return false; } }
        for (i = 0; i < el.childNodes.length; i++) { if (el.childNodes[i].nodeType === 3 && /\S/.test(el.childNodes[i].nodeValue)) { text++; } }
        return text <= 2 && (text > 0 || el.textContent.replace(/\s/g, '').length > 0);
    }
    function centreButtons() {
        var btns = document.querySelectorAll('.btn:not([data-at-cb]):not(.btn-link):not(.btn-trans)');
        for (var i = 0; i < btns.length; i++) {
            var b = btns[i];
            if (b.tagName === 'INPUT') { continue; }
            if (!centrable(b)) { b.setAttribute('data-at-cb', '0'); continue; }
            var h = b.offsetHeight;
            if (!h) { continue; } /* hidden for now: look again later */
            b.style.setProperty('min-height', h + 'px', 'important');
            b.setAttribute('data-at-cb', '1');
        }
    }

    /* ---- "images are blocked, display them" notice (Block External Images module): it belongs ABOVE the message, as in
       plain FreeScout. Wherever the module put it (after the text, inside the message), bring it back to the top. ---- */
    var NOTICE_RE = /display\s+images|images?\s+(are|is)\s+not\s+displayed|afficher\s+(les\s+)?images|images?\s+(ne\s+sont|n.est)\s+pas\s+affich/i;
    function liftNotice(body) {
        var mail = body.querySelector(':scope > .at-mail');
        if (!mail) { return; }
        var prev = mail.previousElementSibling;
        if (prev && prev.classList && prev.classList.contains('at-mail-bar')) { mail = prev; } /* notices go above the button strip */
        var links = body.querySelectorAll('a');
        for (var i = 0; i < links.length; i++) {
            var a = links[i];
            if (!NOTICE_RE.test(a.textContent || '') || a.closest('[data-at-lifted]')) { continue; }
            var el = a;
            while (el.parentNode && el.parentNode !== body && el.parentNode !== mail && !(el.parentNode.classList && el.parentNode.classList.contains('thread-content'))
                   && (el.parentNode.textContent || '').replace(/\s+/g, ' ').length <= 400) { el = el.parentNode; }
            if (el === a || el === body || el === mail) { continue; }
            el.setAttribute('data-at-lifted', '1');
            el.classList.add('at-notice');
            if (el.parentNode !== body || el.nextElementSibling !== mail) {
                var before = mail;
                while (before.previousElementSibling && before.previousElementSibling !== el && before.previousElementSibling.matches && before.previousElementSibling.matches('.at-notice')) { before = before.previousElementSibling; }
                body.insertBefore(el, before);
            }
        }
    }

    /* ---- sender's logo as the avatar of a customer who has no photo (like Canary Mail) ----
       The server marks each customer message with its sender's domain (span.at-from) and serves the logo (it fetches it once
       and keeps it); only the default silhouette is replaced, a real photo never is. No logo: the silhouette stays. */
    var logoTried = {};
    function putLogo(box, dom, base) {
        var img = box.querySelector('img');
        if (!img || box.getAttribute('data-at-fav') || !/default-avatar/.test(img.getAttribute('src') || '')) { return; }
        var url = base.split('example.com').join(dom);
        var probe = logoTried[dom] || (logoTried[dom] = new Promise(function (ok) {
            var t = new Image();
            t.onload = function () { ok(t.naturalWidth >= 16); };
            t.onerror = function () { ok(false); };
            t.src = url;
        }));
        probe.then(function (good) {
            if (!good || box.getAttribute('data-at-fav')) { return; }
            box.setAttribute('data-at-fav', '1');
            img.setAttribute('data-at-orig', img.getAttribute('src'));
            img.removeAttribute('srcset');
            img.src = url;
            img.classList.add('at-fav');
        });
    }
    function senderLogos() {
        var base = meta('atelier-logos', '');
        if (!base) { return; }
        var threads = document.querySelectorAll('.thread-type-customer:not([data-at-logo])'), first = null, i;
        for (i = 0; i < threads.length; i++) {
            var th = threads[i], mk = th.querySelector('.at-from'), box = th.querySelector('.thread-photo');
            th.setAttribute('data-at-logo', '1');
            if (!mk) { continue; }
            var dom = mk.getAttribute('data-at-domain');
            if (!first) { first = dom; }
            if (box && dom) { putLogo(box, dom, base); }
        }
        /* the customer card on the right: the conversation's customer writes the first message */
        if (!first) { var any = document.querySelector('.thread-type-customer .at-from'); first = any && any.getAttribute('data-at-domain'); }
        var side = document.querySelectorAll('.conv-customer-block .customer-photo-container:not([data-at-logo])');
        for (i = 0; i < side.length && first; i++) { side[i].setAttribute('data-at-logo', '1'); putLogo(side[i], first, base); }
    }

    function scan() {
        senderLogos();
        centreButtons();
        mbxCards();
        scanInk();
        var bodies = document.querySelectorAll('.thread-body:not([data-at])');
        for (var i = 0; i < bodies.length; i++) { wrapMail(bodies[i]); }
        var done = document.querySelectorAll('.thread-body[data-at]');
        for (var d = 0; d < done.length; d++) { try { liftNotice(done[d]); } catch (e) { window.console && console.error('Atelier:', e); } }
        var mails = document.querySelectorAll('.at-mail:not([data-at-mc-done])');
        for (var m = 0; m < mails.length; m++) { try { guardMail(mails[m]); } catch (e) { window.console && console.error('Atelier:', e); } }
    }


    /* ---- other stylesheets must not follow the phone's own light / dark setting behind Atelier's back ----
       Another dark-mode module (or any CSS) can ship "@media (prefers-color-scheme: dark)" rules: on a phone set to dark
       they repaint titles, links and backgrounds on top of a light Atelier. Atelier owns the theme, so those media rules
       (and <link media="(prefers-color-scheme...)"> sheets) are switched off. Same-origin sheets only; cross-origin ones
       cannot be read and are left alone. */
    function offScheme(list) {
        for (var i = list.length - 1; i >= 0; i--) {
            var r = list[i];
            try {
                if (r.type === 4 && /prefers-color-scheme/i.test(r.media ? r.media.mediaText : '')) { var host = r.parentRule || r.parentStyleSheet; if (host && host.deleteRule) { host.deleteRule(i); } }
                else if (r.cssRules && (r.type === 4 || r.type === 12)) { offScheme(r.cssRules); }
            } catch (e) {}
        }
    }
    function neutraliseScheme() {
        try {
            var links = document.querySelectorAll('link[rel="stylesheet"][media*="prefers-color-scheme"], style[media*="prefers-color-scheme"]');
            for (var k = 0; k < links.length; k++) { links[k].setAttribute('media', 'not all'); }
            var sheets = document.styleSheets;
            for (var i = 0; i < sheets.length; i++) {
                var sh = sheets[i], rules = null;
                if (sh.href && sh.href.indexOf('atelier') > -1) { continue; }
                if (sh.ownerNode && sh.ownerNode.id === 'atelier-vars') { continue; }
                try { rules = sh.cssRules; } catch (e) { continue; }
                if (!rules) { continue; }
                for (var j = rules.length - 1; j >= 0; j--) {
                    var r = rules[j];
                    if (r.type === 4 && /prefers-color-scheme/i.test(r.media.mediaText)) { sh.deleteRule(j); }
                    else if (r.type === 12 && r.cssRules) { offScheme(r.cssRules); }
                }
            }
        } catch (e) {}
    }


    /* ---- Atelier owns the theme: classes, attributes and rules of another dark-mode module are removed ---- */
    function cleanNode(el) {
        if (!el || !el.classList) { return; }
        var cl = Array.prototype.slice.call(el.classList);
        for (var i = 0; i < cl.length; i++) {
            if ((cl[i] === 'dm' || (DARK_TOKEN.test(cl[i]) && !DARK_SAFE.test(cl[i]))) && cl[i].indexOf('at-') !== 0) { el.classList.remove(cl[i]); }
        }
        for (var j = 0; j < ATTRS.length; j++) {
            var v = el.getAttribute(ATTRS[j]);
            if (v !== null && /dark|night|black/i.test(v)) { el.removeAttribute(ATTRS[j]); }
        }
        if (el.style && el.style.filter && /invert|hue-rotate/i.test(el.style.filter) && !el.closest('.at-mail')) { el.style.removeProperty('filter'); }
    }
    function stripRules(list, sheet) {
        for (var j = list.length - 1; j >= 0; j--) {
            var r = list[j];
            try {
                if (r.cssRules && r.type !== 1) { stripRules(r.cssRules, r); }
                else if (r.type === 1 && r.selectorText && (/dark|night/i.test(r.selectorText) || DM_SEL.test(r.selectorText)) && r.selectorText.indexOf('data-at-') === -1) {
                    var keep = r.selectorText.split(',').filter(function (sel) { return !((DARK_TOKEN.test(sel) && !DARK_SAFE.test(sel)) || DM_SEL.test(sel)); });
                    if (keep.length === 0) { (sheet.deleteRule ? sheet : r.parentStyleSheet).deleteRule(j); }
                }
            } catch (e) {}
        }
    }
    function stripForeign() {
        try {
            cleanNode(root); cleanNode(document.body);
            var sheets = document.styleSheets;
            for (var i = 0; i < sheets.length; i++) {
                var sh = sheets[i];
                if (sh.href && sh.href.indexOf('atelier') > -1) { continue; }
                if (sh.ownerNode && sh.ownerNode.id === 'atelier-vars') { continue; }
                if (sh.ownerNode && sh.ownerNode.getAttribute && sh.ownerNode.getAttribute('data-at-clean') === String(sh.cssRules ? sh.cssRules.length : 0)) { continue; }
                var rules = null;
                try { rules = sh.cssRules; } catch (e) { continue; }
                if (!rules) { continue; }
                stripRules(rules, sh);
                if (sh.ownerNode && sh.ownerNode.setAttribute) { sh.ownerNode.setAttribute('data-at-clean', String(sh.cssRules.length)); }
            }
        } catch (e) {}
    }

    /* the other module's dark class is removed in the same tick it appears, before the browser paints (no flash of its dark blocks while a page loads) */
    (function () {
        try {
            cleanNode(root);
            if (!window.MutationObserver) { return; }
            var bodySeen = false;
            var fast = new MutationObserver(function () {
                cleanNode(root); cleanNode(document.body);
                if (document.body && !bodySeen) { bodySeen = true; fast.observe(document.body, { attributes: true, attributeFilter: ['class'] }); }
            });
            fast.observe(root, { attributes: true, attributeFilter: ['class'], childList: true });
            if (document.body) { bodySeen = true; fast.observe(document.body, { attributes: true, attributeFilter: ['class'] }); }
        } catch (e) {}
    })();

    /* ---- contrast guard (light theme): a dark panel or pale text left behind by another module on a light page ---- */
    var lightTimer = null;
    var LSKIP = '.navbar, .navbar *, img, svg, video, canvas, iframe, script, style, pre, code, .at-mail, .at-mail *, .note-editable, .note-editable *, .at-mail-toggle, .tooltip, .tooltip *, .popover, .popover *, .modal-backdrop, .featherlight, .featherlight *, .btn, .btn *, .label, .badge, .progress, .progress *, .navbar-toggle, .navbar-toggle *, .select2-selection__choice, .alert, .alert *, .caret, input, textarea, select, option, .conv-fader, .at-keep, .at-keep *';
    function effBg(el) {
        for (var n = el; n && n.nodeType === 1; n = n.parentNode) {
            var c = rgbOf(getComputedStyle(n).backgroundColor);
            if (c && c.a > 0.6) { return c; }
        }
        return { r: 255, g: 255, b: 255, a: 1 };
    }
    function relLum(c) {
        var f = function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
        return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b);
    }
    function ratio(a, b) { var x = relLum(a), y = relLum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
    function hasOwnText(el) {
        for (var n = el.firstChild; n; n = n.nextSibling) { if (n.nodeType === 3 && /\S/.test(n.nodeValue)) { return true; } }
        return false;
    }
    function runLight() {
        lightTimer = null;
        if (root.getAttribute('data-at-theme') !== 'light') { return; }
        var app = document.getElementById('app') || document.body;
        if (!app) { return; }
        var els = app.querySelectorAll('*:not([data-at-lseen])');
        for (var i = 0; i < els.length && i < 5000; i++) {
            var el = els[i];
            el.setAttribute('data-at-lseen', '1');
            if (el.matches(LSKIP)) { continue; }
            var cs = getComputedStyle(el);
            if (cs.display === 'none' || cs.visibility === 'hidden') { continue; }
            var bg = rgbOf(cs.backgroundColor);
            /* big flat dark panel on a light page */
            if (bg && bg.a > 0.5 && relLum(bg) < 0.12 && sat(bg) < 0.3 && cs.backgroundImage === 'none' && el.offsetWidth >= 120 && el.offsetHeight >= 40) {
                el.style.setProperty('background-color', el.matches('.layout-2col, .content-2col, .content, .content-full, .container, #app') ? 'var(--at-page)' : 'var(--at-surface)', 'important');
                el.setAttribute('data-at-lfix', '1');
            }
            /* pale text on a pale background */
            if (hasOwnText(el)) {
                var c = rgbOf(cs.color);
                if (c && c.a > 0.3) {
                    var b = effBg(el);
                    if (ratio(c, b) < 3 && relLum(b) > 0.5 && sat(c) < 0.35 && !el.closest('.empty-content, .text-help, .glyphicon')) {
                        var strong = /^(H[1-6]|STRONG|B)$/.test(el.tagName) || el.closest('h1,h2,h3,h4,h5,h6,.conv-subjtext,.heading') || parseInt(cs.fontSize, 10) >= 18 || parseInt(cs.fontWeight, 10) >= 600;
                        el.style.setProperty('color', strong ? 'var(--at-text)' : 'var(--at-muted)', 'important');
                        el.setAttribute('data-at-lfix', '1');
                    }
                }
            }
            if (cs.opacity !== '1' && parseFloat(cs.opacity) < 0.75 && hasOwnText(el) && !el.closest('.at-theme-menu, .disabled, [disabled], .dash-card-item-empty, .tooltip, .at-mail-toggle, .select2-container, .note-editor, .conv-row .conv-fader, .btn, .loader, .loading')) {
                el.style.setProperty('opacity', '1', 'important');
                el.setAttribute('data-at-lfix', '1');
            }
        }
    }
    function schedLight() { if (!lightTimer) { lightTimer = setTimeout(runLight, 180); } }
    function clearLight() {
        var f = document.querySelectorAll('[data-at-lfix],[data-at-lseen]');
        for (var i = 0; i < f.length; i++) {
            if (f[i].hasAttribute('data-at-lfix')) { f[i].style.removeProperty('background-color'); f[i].style.removeProperty('color'); f[i].style.removeProperty('opacity'); f[i].removeAttribute('data-at-lfix'); }
            f[i].removeAttribute('data-at-lseen');
        }
    }
    function recheck() {
        stripForeign(); neutraliseScheme();
        if (root.getAttribute('data-at-theme') === 'light') { clearLight(); schedLight(); } else { schedGuard(); }
    }

    /* ---- announcements: one small card, once per item, administrators only (the server decides who and what) ---- */
    function news() {
        try {
            var raw = meta('atelier-news', '');
            if (!raw || window.innerWidth < 768) { return; }
            var it = JSON.parse(raw);
            if (!it || !it.id || !it.title) { return; }
            var tries = 0;
            var show = function () {
                /* never on a conversation, never over a dialog or while someone is typing */
                if (document.getElementById('conv-layout') || document.querySelector('.at-news')) { return; }
                var ae = document.activeElement;
                if (document.querySelector('.modal.in, .modal.show') || (ae && /^(input|textarea|select)$/i.test(ae.tagName)) || (ae && ae.isContentEditable)) {
                    if (++tries < 6) { setTimeout(show, 4000); }
                    return;
                }
                var el = function (tag, cls, txt) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (txt) { n.textContent = txt; } return n; };
                var card = el('div', 'at-news');
                card.setAttribute('role', 'status');
                var head = el('div', 'at-news-head');
                head.appendChild(el('span', 'at-news-kind', it.kind === 'feedback' ? (L.newsFeedback || 'Your opinion') : (L.newsNews || 'Atelier news')));
                var x = el('button', 'at-news-x', '\u00d7');
                x.type = 'button'; x.setAttribute('aria-label', L.newsClose || 'Close');
                head.appendChild(x);
                card.appendChild(head);
                card.appendChild(el('div', 'at-news-title', it.title));
                if (it.body) { card.appendChild(el('div', 'at-news-body', it.body)); }
                var acts = el('div', 'at-news-acts');
                if (it.url && /^https:\/\//.test(it.url)) {
                    var more = el('a', 'at-news-more', it.kind === 'feedback' ? (L.newsGive || 'Give feedback') : (L.newsMore || 'Read more'));
                    more.href = it.url; more.target = '_blank'; more.rel = 'noopener noreferrer';
                    acts.appendChild(more);
                    /* the destination is always shown, so nobody opens a link blindly */
                    try { acts.appendChild(el('span', 'at-news-host', new URL(it.url).hostname.replace(/^www\./, ''))); } catch (e2) {}
                }
                if (it.settings) { var off = el('a', 'at-news-off', L.newsOff || 'Turn off announcements'); off.href = it.settings; acts.appendChild(off); }
                card.appendChild(acts);
                var close = function () { if (card.parentNode) { card.parentNode.removeChild(card); } document.removeEventListener('keydown', key); };
                var key = function (e) { if (e.key === 'Escape') { close(); } };
                x.addEventListener('click', close);
                document.addEventListener('keydown', key);
                document.body.appendChild(card);
                /* shown once: remembered on the server for this administrator */
                var tok = document.querySelector('meta[name="csrf-token"]');
                if (it.seen && window.fetch) {
                    fetch(it.seen, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': tok ? tok.content : '', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'id=' + encodeURIComponent(it.id) }).catch(function () {});
                }
            };
            setTimeout(show, 2500);
        } catch (e) {}
    }

    function init() {
        neutraliseScheme();
        stripForeign();
        window.addEventListener('load', recheck);
        window.addEventListener('pageshow', recheck);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { recheck(); } });
        setTimeout(recheck, 1200); setTimeout(recheck, 3500);
        if (window.MutationObserver) {
            var rt = null;
            var guardAttrs = new MutationObserver(function () { if (rt) { return; } rt = setTimeout(function () { rt = null; cleanNode(root); cleanNode(document.body); }, 30); });
            guardAttrs.observe(root, { attributes: true, attributeFilter: ['class', 'style'].concat(ATTRS) });
            if (document.body) { guardAttrs.observe(document.body, { attributes: true, attributeFilter: ['class', 'style'].concat(ATTRS) }); }
            if (document.head) {
                new MutationObserver(function () { setTimeout(function () { neutraliseScheme(); stripForeign(); }, 50); }).observe(document.head, { childList: true });
            }
        }
        syncBrowserChrome(root.getAttribute('data-at-theme') === 'dark');
        try { L = JSON.parse(meta('atelier-l10n', '{}')); } catch (e) { L = {}; }
        addMenu();
        hideOtherToggle();
        scan();
        news();
        schedGuard();
        schedLight();
        if (window.MutationObserver) {
            var timer = null;
            new MutationObserver(function () {
                if (timer) { return; }
                timer = setTimeout(function () { timer = null; addMenu(); hideOtherToggle(); scan(); schedGuard(); schedLight(); }, 120);
            }).observe(document.body, { childList: true, subtree: true });
        }
    }

    /* ---- drop-downs of the dashboard cards and of the mailbox sidebar: open where there is room ----
       FreeScout always opens them upwards; on the top row the long settings menu then ran under the top bar. */
    function fitMenu(e) {
        try {
            var t = e.target && e.target.closest ? e.target.closest('[data-toggle="dropdown"]') : null;
            if (!t || !t.closest('.dash-card-footer, .sidebar-buttons')) { return; }
            var grp = t.closest('.btn-group');
            var menu = grp && grp.querySelector('.dropdown-menu');
            if (!menu || grp.classList.contains('open')) { return; }
            if (grp.getAttribute('data-at-up') === null) { grp.setAttribute('data-at-up', grp.classList.contains('dropup') ? '1' : '0'); }
            var keep = menu.getAttribute('style');
            menu.style.cssText = 'display:block;visibility:hidden;max-height:none;overflow:visible';
            var h = menu.offsetHeight;
            if (keep === null) { menu.removeAttribute('style'); } else { menu.setAttribute('style', keep); }
            var top = 0, nav = document.querySelector('.navbar');
            if (nav) { var pos = window.getComputedStyle(nav).position; if (pos === 'sticky' || pos === 'fixed') { top = Math.max(0, nav.getBoundingClientRect().bottom); } }
            var r = t.getBoundingClientRect();
            var above = r.top - top - 10, below = window.innerHeight - r.bottom - 10;
            var up = grp.getAttribute('data-at-up') === '1';
            if (up && h > above && below > above) { up = false; } else if (!up && h > below && above > below) { up = true; }
            var room = up ? above : below;
            grp.classList.toggle('dropup', up);
            if (h > room) { menu.style.maxHeight = Math.max(160, room) + 'px'; menu.style.overflowY = 'auto'; }
            else { menu.style.maxHeight = ''; menu.style.overflowY = ''; }
        } catch (err) { }
    }
    document.addEventListener('click', fitMenu, true);
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
