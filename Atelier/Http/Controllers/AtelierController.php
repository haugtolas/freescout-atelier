<?php

namespace Modules\Atelier\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Atelier\Providers\AtelierServiceProvider as P;

class AtelierController extends Controller
{
    /** accent => [light, dark], each [button, button hover, link, link hover, soft background] */
    public static $accents = [
        'azur'       => [['#2b63d9', '#2253b8', '#2557c7', '#1d46a0', 'rgba(43,99,217,.10)'],   ['#3b6fe0', '#255fdd', '#8fb0ff', '#b6ccff', 'rgba(143,176,255,.15)']],
        'indigo'     => [['#4f46e5', '#4338ca', '#4f46e5', '#3b32b8', 'rgba(79,70,229,.09)'],   ['#5b5bd6', '#4747d1', '#a5b4fc', '#c4cefd', 'rgba(129,140,248,.16)']],
        'violet'     => [['#6d4fd6', '#5b3fc0', '#6240cc', '#4e32ad', 'rgba(109,79,214,.09)'],  ['#7154dc', '#5f3fd8', '#c0b0ff', '#d6ccff', 'rgba(168,148,255,.16)']],
        'emerald'    => [['#0b7a58', '#0a6f50', '#0b7a58', '#085c43', 'rgba(15,143,104,.09)'],  ['#0f7f5c', '#0c684b', '#6ee7b7', '#a1efd0', 'rgba(52,211,153,.14)']],
        'bordeaux'   => [['#a8264d', '#8f1d40', '#a8264d', '#861838', 'rgba(168,38,77,.08)'],   ['#b92d58', '#a4284e', '#fda4af', '#fec4cb', 'rgba(251,113,133,.15)']],
        'terracotta' => [['#b9582f', '#9f4824', '#a8502a', '#8a3f1f', 'rgba(185,88,47,.10)'],   ['#b05430', '#9c4a2b', '#f0a98a', '#f5c7b3', 'rgba(240,169,138,.15)']],
        'gold'       => [['#946610', '#7d550c', '#946610', '#6b480a', 'rgba(148,102,16,.10)'],  ['#946610', '#7d560e', '#f0c36b', '#f5d89f', 'rgba(240,195,107,.15)']],
    ];

    /** Preset colours offered next to the hand-tuned accents (value stored as '#rrggbb'). */
    public static $presets = ['#0891b2', '#0d9488', '#65a30d', '#ea580c', '#dc2626', '#db2777', '#475569'];

    /** Accepts a hand-tuned key ("indigo") or a colour ("#3b82f6", "3B82F6"); returns the key, "#rrggbb" in lower case, or null. */
    public static function normAccent($v)
    {
        $v = is_string($v) ? strtolower(trim($v)) : '';
        if (isset(self::$accents[$v])) {
            return $v;
        }
        if (preg_match('/^#?([0-9a-f]{6})$/', $v, $m)) {
            return '#'.$m[1];
        }
        return null;
    }

    /** CSS attribute token of an accent: the key, or the six hex digits. */
    public static function accentToken($norm)
    {
        return $norm[0] === '#' ? substr($norm, 1) : $norm;
    }

    protected static function rgb2hsl($r, $g, $b)
    {
        $r /= 255; $g /= 255; $b /= 255;
        $mx = max($r, $g, $b); $mn = min($r, $g, $b); $l = ($mx + $mn) / 2;
        if ($mx == $mn) { return [0, 0, $l]; }
        $d = $mx - $mn;
        $s = $l > 0.5 ? $d / (2 - $mx - $mn) : $d / ($mx + $mn);
        if ($mx == $r) { $h = ($g - $b) / $d + ($g < $b ? 6 : 0); }
        elseif ($mx == $g) { $h = ($b - $r) / $d + 2; }
        else { $h = ($r - $g) / $d + 4; }
        return [$h * 60, $s, $l];
    }

    protected static function hsl2rgb($h, $s, $l)
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $hp = fmod($h, 360) / 60;
        $x = $c * (1 - abs(fmod($hp, 2) - 1));
        $r = $g = $b = 0;
        if ($hp < 1) { $r = $c; $g = $x; } elseif ($hp < 2) { $r = $x; $g = $c; } elseif ($hp < 3) { $g = $c; $b = $x; }
        elseif ($hp < 4) { $g = $x; $b = $c; } elseif ($hp < 5) { $r = $x; $b = $c; } else { $r = $c; $b = $x; }
        $m = $l - $c / 2;
        return [(int)floor(($r + $m) * 255 + 0.5), (int)floor(($g + $m) * 255 + 0.5), (int)floor(($b + $m) * 255 + 0.5)];
    }

    protected static function lum($rgb)
    {
        $f = function ($v) { $v /= 255; return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4); };
        return 0.2126 * $f($rgb[0]) + 0.7152 * $f($rgb[1]) + 0.0722 * $f($rgb[2]);
    }

    protected static function hex($rgb)
    {
        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }

    /** Lowers (or raises, $dir = 1) the lightness until the colour has $min contrast against a surface of luminance $bgLum. */
    protected static function fit($h, $s, $l, $bgLum, $min, $dir)
    {
        for ($i = 0; $i < 60; $i++) {
            $rgb = self::hsl2rgb($h, $s, $l);
            $lm = self::lum($rgb);
            $ratio = (max($lm, $bgLum) + 0.05) / (min($lm, $bgLum) + 0.05);
            if ($ratio >= $min) { break; }
            $l = max(0.04, min(0.96, $l + $dir * 0.01));
        }
        return $l;
    }

    /**
     * [light, dark], each [button, button hover, link, link hover, soft background], for a hand-tuned key or any colour.
     * A colour is only a starting point: lightness is moved until white button text and links stay readable (4.5:1) on the light
     * surfaces, and links reach 7:1 on the dark ones. Must stay identical to deriveAccent() in views/settings.blade.php.
     */
    public static function accentSet($value)
    {
        $n = self::normAccent($value);
        if ($n === null) { $n = 'azur'; }
        if ($n[0] !== '#') { return self::$accents[$n]; }
        $rgb = [hexdec(substr($n, 1, 2)), hexdec(substr($n, 3, 2)), hexdec(substr($n, 5, 2))];
        list($h, $s, $l) = self::rgb2hsl($rgb[0], $rgb[1], $rgb[2]);
        $s = $s < 0.12 ? $s : max(0.35, min(0.9, $s));
        // light theme
        $bl = self::fit($h, $s, min($l, 0.5), 1.0, 4.6, -1);
        $btn = self::hsl2rgb($h, $s, $bl);
        $hov = self::hsl2rgb($h, $s, max(0.08, $bl - 0.07));
        $ll = self::fit($h, $s, $bl, 0.9, 4.6, -1);
        $lnk = self::hsl2rgb($h, $s, $ll);
        $lnh = self::hsl2rgb($h, $s, max(0.08, $ll - 0.09));
        $light = [self::hex($btn), self::hex($hov), self::hex($lnk), self::hex($lnh), 'rgba('.implode(',', $btn).',.10)'];
        // dark theme
        $db = self::fit($h, $s, max(0.38, min($l, 0.5)), 1.0, 4.5, -1);
        $dbtn = self::hsl2rgb($h, $s, $db);
        $dhov = self::hsl2rgb($h, $s, min(0.9, $db + 0.05));
        $dlnk = self::hsl2rgb($h, $s, 0.74);
        $dlnh = self::hsl2rgb($h, $s, 0.84);
        $dark = [self::hex($dbtn), self::hex($dhov), self::hex($dlnk), self::hex($dlnh), 'rgba('.implode(',', $dlnk).',.15)'];
        return [$light, $dark];
    }

    /** Dynamic variables: accent, logos, admin's extra CSS. Cached by the browser (?v=revision). */
    /** Stylesheet response (kept for pages or caches that still link to it). */
    public function vars()
    {
        return response(self::css(), 200, [
            'Content-Type'  => 'text/css; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /** Accent colours, per-mailbox colours, logo rules and extra CSS, as one string. Inlined in <head> by the provider so it can never be served stale. */
    public static function css()
    {
        $set = self::accentSet(P::opt('accent4', 'azur'));
        $css = '';
        foreach (['light', 'dark'] as $i => $theme) {
            list($btn, $hover, $link, $linkh, $soft) = $set[$i];
            $css .= 'html[data-at-theme="'.$theme.'"]{--at-accent:'.$btn.';--at-accent-h:'.$hover.';--at-link:'.$link.';--at-link-h:'.$linkh.';--at-soft:'.$soft."}\n";
        }
        // Per-mailbox colour. [data-at-mbx-accent="token"] is set on <html> (mailbox pages) or on a dashboard card by theme.js.
        // The dot and the soft tint of the card header always follow the mailbox; buttons and links inside the mailbox only
        // when "mbx_recolor" is on (off by default: a red mailbox does not have to turn every button red).
        $recolor = P::opt('mbx_recolor', '0') === '1';
        foreach (array_unique(array_values(P::mailboxAccents())) as $norm) {
            $set = self::accentSet($norm);
            $tok = self::accentToken($norm);
            foreach (['light', 'dark'] as $i => $theme) {
                list($btn, $hover, $link, $linkh, $soft) = $set[$i];
                $dot = $theme === 'dark' ? $link : ($norm[0] === '#' ? $norm : $btn);
                $base = '--at-dot:'.$dot.';--at-mbx-soft:'.$soft;
                $css .= 'html[data-at-theme="'.$theme.'"] [data-at-mbx-accent="'.$tok.'"]{'.$base."}\n";
                $css .= 'html[data-at-theme="'.$theme.'"][data-at-mbx-accent="'.$tok.'"]{'.$base
                    .($recolor ? ';--at-accent:'.$btn.';--at-accent-h:'.$hover.';--at-link:'.$link.';--at-link-h:'.$linkh.';--at-soft:'.$soft : '')."}\n";
            }
        }
        // Logos. L = logo for the light theme (dark artwork), D = logo for the dark theme (light artwork).
        // FreeScout's own CSS changes the logo's filter on hover (a.navbar-brand:hover img); every rule below therefore
        // covers the hover / focus / active states too and uses !important, so the logo can never vanish when hovered.
        $rev = (int)\Option::get('atelier.rev', 1);
        $L = P::logoPath('light');
        $D = P::logoPath('dark');
        $inv = 'invert(1) hue-rotate(180deg)';
        if ($L && $D) {
            $css .= 'html[data-at-theme="dark"] .navbar-brand img{content:url("'.route('atelier.logo', ['variant' => 'dark', 'v' => $rev])."\")}\n";
            $css .= self::logoRule('dark', 'none');
            $css .= self::logoRule('light', 'none');
        } elseif ($L) {
            $css .= self::logoRule('dark', $inv);
            $css .= self::logoRule('light', 'none');
        } elseif ($D) {
            $css .= self::logoRule('light', $inv);
            $css .= self::logoRule('dark', 'none');
        } elseif (P::opt('logo_keep', '0') !== '1') {
            // FreeScout's own logo is white (made for a blue bar): without it the logo disappears on the light bar
            $css .= self::logoRule('light', 'brightness(0) opacity(.85)');
            $css .= self::logoRule('dark', 'none');
        }
        $extra = \Option::get('atelier.extra_css', '');
        $extra = is_scalar($extra) ? (string)$extra : '';
        if ($extra !== '') {
            $css .= "/* extra CSS (Settings > Atelier) */\n".$extra."\n";
        }

        return $css;
    }

    protected static function logoRule($theme, $filter)
    {
        $t = 'html[data-at-theme="'.$theme.'"] ';
        $sel = [];
        foreach (['.navbar-brand img', 'a.navbar-brand:hover img', 'a.navbar-brand:focus img', 'a.navbar-brand.active img'] as $s) {
            $sel[] = $t.$s;
        }
        return implode(',', $sel).'{-webkit-filter:'.$filter.' !important;filter:'.$filter.' !important}'."\n";
    }

    public function logo($variant)
    {
        $path = P::logoPath($variant);
        if (!$path) {
            abort(404);
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];

        return response()->file($path, [
            'Content-Type'            => isset($types[$ext]) ? $types[$ext] : 'application/octet-stream',
            'Cache-Control'           => 'public, max-age=31536000',
            // an SVG opened directly must not run scripts
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options'  => 'nosniff',
        ]);
    }

    public function save(Request $request)
    {
        $theme = in_array($request->input('theme_default'), ['auto', 'light', 'dark']) ? $request->input('theme_default') : 'auto';
        $mail = in_array($request->input('mail_default'), ['adapted', 'paper']) ? $request->input('mail_default') : 'adapted';
        $accent = self::normAccent($request->input('accent')) ?: 'azur';
        $palette = in_array($request->input('palette_default'), P::PALETTES) ? $request->input('palette_default') : 'graphite';
        $density = in_array($request->input('density_default'), ['comfortable', 'compact']) ? $request->input('density_default') : 'comfortable';
        \Option::set('atelier.palette_default', $palette);
        \Option::set('atelier.density_default', $density);
        \Option::set('atelier.icons_modern', $request->input('icons_modern') ? '1' : '0');
        \Option::set('atelier.hide_other_toggle', $request->input('hide_other_toggle') ? '1' : '0');
        \Option::set('atelier.logo_keep', $request->input('logo_keep') ? '1' : '0');
        \Option::set('atelier.theme_default', $theme);
        \Option::set('atelier.mail_default', $mail);
        \Option::set('atelier.accent4', $accent);
        \Option::set('atelier.mbx_recolor', $request->input('mbx_recolor') ? '1' : '0');
        \Option::set('atelier.extra_css', self::cleanCss((string)$request->input('extra_css', '')));
        $this->saveMailboxAccents((array)$request->input('mbx', []));

        $error = null;
        foreach (['light', 'dark'] as $variant) {
            if ($request->input('remove_logo_'.$variant)) {
                $this->removeLogo($variant);
            }
            $field = 'logo_'.$variant;
            if (!$request->hasFile($field)) {
                continue;
            }
            $file = $request->file($field);
            $ext = strtolower($file->getClientOriginalExtension());
            if (!$file->isValid() || !in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg']) || $file->getSize() > 1024 * 1024) {
                $error = __('Logo: PNG, JPG, GIF, WEBP or SVG, 1 MB maximum.');
                continue;
            }
            $this->removeLogo($variant);
            $dir = storage_path('app/atelier');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $name = 'logo-'.$variant.'.'.$ext;
            $file->move($dir, $name);
            \Option::set('atelier.logo_'.$variant, $name);
        }
        \Option::set('atelier.rev', (int)\Option::get('atelier.rev', 1) + 1);

        if ($error) {
            \Session::flash('flash_error_floating', $error);
        } else {
            \Session::flash('flash_success_floating', __('Settings saved'));
        }

        return redirect()->route('settings', ['section' => 'atelier']);
    }

    /** never allow a closing style tag or an @import of a remote sheet */
    protected static function cleanCss($css)
    {
        return mb_substr(str_ireplace(['</style', '@import'], '', $css), 0, 20000);
    }

    /** $input: [mailbox id => accent key or '']. Unknown mailboxes and unknown accents are dropped. */
    protected function saveMailboxAccents(array $input)
    {
        $ids = \App\Mailbox::pluck('id')->all();
        $out = [];
        foreach ($input as $id => $acc) {
            $norm = self::normAccent($acc);
            if (in_array((int)$id, $ids, true) && $norm !== null) {
                $out[(int)$id] = $norm;
            }
        }
        \Option::set('atelier.mbx_accents', $out ? json_encode($out) : '');
    }

    /** One file with everything: options, mailbox accents (by e-mail, so they survive another installation), both logos. */
    public function export()
    {
        $keys = ['theme_default', 'mail_default', 'palette_default', 'density_default', 'icons_modern', 'hide_other_toggle', 'logo_keep', 'accent4', 'mbx_recolor', 'extra_css'];
        $options = [];
        foreach ($keys as $k) {
            $ov = \Option::get('atelier.'.$k, '');
            $options[$k] = is_scalar($ov) ? (string)$ov : '';
        }
        $mailboxes = [];
        $map = P::mailboxAccents();
        foreach (\App\Mailbox::whereIn('id', array_keys($map) ?: [0])->get() as $mb) {
            $mailboxes[] = ['id' => $mb->id, 'name' => $mb->name, 'email' => $mb->email, 'accent' => $map[$mb->id]];
        }
        $logos = [];
        foreach (['light', 'dark'] as $variant) {
            $path = P::logoPath($variant);
            if ($path) {
                $logos[$variant] = ['name' => basename($path), 'data' => base64_encode(file_get_contents($path))];
            }
        }
        $json = json_encode([
            'atelier'   => 1,
            'exported'  => date('c'),
            'options'   => $options,
            'mailboxes' => $mailboxes,
            'logos'     => $logos,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type'        => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="atelier-settings-'.date('Ymd').'.json"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    public function import(Request $request)
    {
        $back = redirect()->route('settings', ['section' => 'atelier']);
        $bad = function () use ($back) {
            \Session::flash('flash_error_floating', __('This file is not an Atelier settings file.'));
            return $back;
        };
        if (!$request->hasFile('settings_file') || !$request->file('settings_file')->isValid() || $request->file('settings_file')->getSize() > 3 * 1024 * 1024) {
            return $bad();
        }
        $data = json_decode(file_get_contents($request->file('settings_file')->getRealPath()), true);
        if (!is_array($data) || empty($data['atelier']) || !isset($data['options']) || !is_array($data['options'])) {
            return $bad();
        }
        $o = $data['options'];
        $pick = function ($k, array $allowed, $def) use ($o) {
            return (isset($o[$k]) && in_array($o[$k], $allowed, true)) ? $o[$k] : $def;
        };
        $flag = function ($k, $def) use ($o) {
            return (isset($o[$k]) && in_array((string)$o[$k], ['0', '1'], true)) ? (string)$o[$k] : $def;
        };
        \Option::set('atelier.theme_default', $pick('theme_default', ['auto', 'light', 'dark'], 'auto'));
        \Option::set('atelier.mail_default', $pick('mail_default', ['adapted', 'paper'], 'adapted'));
        \Option::set('atelier.palette_default', $pick('palette_default', P::PALETTES, 'graphite'));
        \Option::set('atelier.density_default', $pick('density_default', ['comfortable', 'compact'], 'comfortable'));
        \Option::set('atelier.accent4', self::normAccent(isset($o['accent4']) ? $o['accent4'] : '') ?: 'azur');
        \Option::set('atelier.icons_modern', $flag('icons_modern', '1'));
        \Option::set('atelier.hide_other_toggle', $flag('hide_other_toggle', '1'));
        \Option::set('atelier.logo_keep', $flag('logo_keep', '0'));
        \Option::set('atelier.mbx_recolor', $flag('mbx_recolor', '0'));
        \Option::set('atelier.extra_css', self::cleanCss(isset($o['extra_css']) ? (string)$o['extra_css'] : ''));

        // mailbox accents: matched by e-mail address, then by name
        $out = [];
        $local = \App\Mailbox::all();
        foreach ((isset($data['mailboxes']) && is_array($data['mailboxes'])) ? $data['mailboxes'] : [] as $row) {
            if (!is_array($row) || self::normAccent(isset($row['accent']) ? $row['accent'] : '') === null) {
                continue;
            }
            $mb = null;
            if (!empty($row['email'])) {
                $mb = $local->first(function ($m) use ($row) { return strtolower($m->email) === strtolower($row['email']); });
            }
            if (!$mb && !empty($row['name'])) {
                $mb = $local->first(function ($m) use ($row) { return $m->name === $row['name']; });
            }
            if ($mb) {
                $out[$mb->id] = self::normAccent($row['accent']);
            }
        }
        \Option::set('atelier.mbx_accents', $out ? json_encode($out) : '');

        // logos
        $dir = storage_path('app/atelier');
        foreach (['light', 'dark'] as $variant) {
            if (empty($data['logos'][$variant]['data']) || empty($data['logos'][$variant]['name'])) {
                continue;
            }
            $ext = strtolower(pathinfo((string)$data['logos'][$variant]['name'], PATHINFO_EXTENSION));
            $bin = base64_decode((string)$data['logos'][$variant]['data'], true);
            if ($bin === false || strlen($bin) > 1024 * 1024 || !in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'])) {
                continue;
            }
            if ($ext !== 'svg' && @getimagesizefromstring($bin) === false) {
                continue;
            }
            if ($ext === 'svg' && stripos($bin, '<svg') === false) {
                continue;
            }
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $this->removeLogo($variant);
            $name = 'logo-'.$variant.'.'.$ext;
            file_put_contents($dir.'/'.$name, $bin);
            \Option::set('atelier.logo_'.$variant, $name);
        }
        \Option::set('atelier.rev', (int)\Option::get('atelier.rev', 1) + 1);
        \Session::flash('flash_success_floating', __('Settings imported'));

        return $back;
    }

    protected function removeLogo($variant)
    {
        $path = P::logoPath($variant);
        if ($path) {
            @unlink($path);
        }
        \Option::set('atelier.logo_'.$variant, '');
    }

    /** Settings > Atelier > Updates: ask GitHub now. */
    public function updateCheck()
    {
        $info = \Modules\Atelier\Services\Updater::latest(true, 10);
        if (isset($info['error'])) {
            \Session::flash('flash_error_floating', $info['error'] === 'no-repo' ? __('Updates are not set up: no repository in module.json.') : __('Could not reach GitHub: :e', ['e' => $info['error']]));
        } elseif (\Modules\Atelier\Services\Updater::hasUpdate($info)) {
            \Session::flash('flash_success_floating', __('Atelier :v is available.', ['v' => $info['version']]));
        } else {
            \Session::flash('flash_success_floating', __('Atelier is up to date.'));
        }
        return redirect()->route('settings', ['section' => 'atelier']);
    }

    /** Settings > Atelier > Updates: download and install the latest release. */
    public function updateApply()
    {
        $r = \Modules\Atelier\Services\Updater::apply();
        if ($r['ok']) {
            \Session::flash('flash_success_floating', __('Atelier :v installed.', ['v' => $r['version']]));
        } else {
            \Session::flash('flash_error_floating', $r['message']);
        }
        return redirect()->route('settings', ['section' => 'atelier']);
    }
}
