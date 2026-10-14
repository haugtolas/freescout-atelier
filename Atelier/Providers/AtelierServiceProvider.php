<?php

namespace Modules\Atelier\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Atelier: complete light / dark theme for FreeScout.
 * Visual only (one stylesheet + one small script). Built so that it can never take the site down:
 *  - kill switch: while the file storage/app/atelier.disabled exists, the module does nothing at all;
 *  - every hook is wrapped: an error inside the theme is logged and ignored;
 *  - the stylesheet is linked directly (it does not go through FreeScout's CSS minifier).
 */
class AtelierServiceProvider extends ServiceProvider
{
    protected $defer = false;

    const ACCENTS = ['azur', 'indigo', 'violet', 'emerald', 'bordeaux', 'terracotta', 'gold'];
    const PALETTES = ['graphite', 'ardoise', 'minuit', 'foret', 'sable'];

    /** Real folder of the module (not the compiled-view folder). */
    public static function modulePath()
    {
        return dirname(__DIR__);
    }

    public static function disabled()
    {
        return is_file(storage_path('app/atelier.disabled'));
    }

    public function boot()
    {
        if (self::disabled()) {
            return;
        }
        try {
            $this->loadViewsFrom(__DIR__.'/../Resources/views', 'atelier');
            $this->loadJsonTranslationsFrom(__DIR__.'/../Resources/lang');
            $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');
            $this->registerHooks();
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    protected function registerHooks()
    {
        // <head>: defaults, strings for the script, stylesheet, dynamic variables (accent, logos, extra CSS), theme script
        \Eventy::addAction('layout.head', function () {
            try {
                $l10n = [
                    'theme'   => __('Theme'),
                    'auto'    => __('Automatic'),
                    'light'   => __('Light'),
                    'dark'    => __('Dark'),
                    'mail'    => __('E-mail display'),
                    'adapted' => __('Adapted to dark mode'),
                    'paper'   => __('Original (light background)'),
                    'toggle'  => __('Original / adapted'),
                    'palette' => __('Ambiance'),
                    'density' => __('Density'),
                    'comfortable' => __('Comfortable'),
                    'compact' => __('Compact'),
                    'graphite' => __('Graphite'),
                    'ardoise' => __('Slate'),
                    'minuit' => __('Midnight'),
                    'foret' => __('Forest'),
                    'sable' => __('Sand'),
                ];
                $rev = (int)\Option::get('atelier.rev', 1);
                $mbx = self::mailboxAccents();
                $base = \Module::getPublicPath('atelier');
                $css = @filemtime(__DIR__.'/../Public/css/atelier.css');
                $js = @filemtime(__DIR__.'/../Public/js/theme.js');
                $out = '<meta name="atelier-theme" content="'.e(self::opt('theme_default', 'auto')).'">'."\n"
                    .'<meta name="atelier-mail" content="'.e(self::opt('mail_default', 'adapted')).'">'."\n"
                    .'<meta name="atelier-palette" content="'.e(self::opt('palette_default', 'graphite')).'">'."\n"
                    .'<meta name="atelier-density" content="'.e(self::opt('density_default', 'comfortable')).'">'."\n"
                    .'<meta name="atelier-icons" content="'.(self::opt('icons_modern', '1') === '1' ? '1' : '0').'">'."\n"
                    .'<meta name="atelier-logo-auto" content="'.((!self::logoPath('light') && !self::logoPath('dark') && self::opt('logo_keep', '0') !== '1') ? '1' : '0').'">'."\n"
                    .(self::opt('sender_logos', '1') === '1' ? '<meta name="atelier-logos" content="'.e(route('atelier.favicon', ['domain' => 'example.com'])).'">'."\n" : '')
                    .'<meta name="atelier-hide-toggle" content="'.(self::opt('hide_other_toggle', '1') === '1' ? '1' : '0').'">'."\n"
                    .($mbx ? '<meta name="atelier-mbx-map" content="'.e(json_encode(array_map(['Modules\\Atelier\\Http\\Controllers\\AtelierController', 'accentToken'], $mbx))).'">'."\n"
                        .'<meta name="atelier-mbx" content="'.(int)self::currentMailboxId().'">'."\n" : '')
                    .'<meta name="atelier-l10n" content="'.e(json_encode($l10n, JSON_UNESCAPED_UNICODE)).'">'."\n"
                    .'<script src="'.asset($base.'/js/theme.js').'?v='.$js.'"></script>'."\n"
                    .'<link rel="stylesheet" href="'.asset($base.'/css/atelier.css').'?v='.$css.'">'."\n"
                    .'<style id="atelier-vars">'.str_ireplace('</style', '', \Modules\Atelier\Http\Controllers\AtelierController::css()).'</style>'."\n";
                echo $out;
            } catch (\Throwable $e) {
                self::fail($e);
            }
        });

        // Marks each customer message with the domain of its sender (the script shows that domain's logo as the avatar)
        \Eventy::addAction('thread.after_person_action', function ($thread) {
            try {
                if (self::opt('sender_logos', '1') !== '1' || $thread->type != \App\Thread::TYPE_CUSTOMER || !$thread->customer_cached) {
                    return;
                }
                $d = self::senderDomain($thread->customer_cached->getMainEmail());
                if ($d !== '') {
                    echo '<span class="at-from" data-at-domain="'.e($d).'" hidden></span>';
                }
            } catch (\Throwable $e) {
                self::fail($e);
            }
        }, 20, 5);

        // Logo of the top bar: light-theme logo, else dark-theme logo, else FreeScout's / Customization module's own
        \Eventy::addFilter('layout.header_logo', function ($url) {
            try {
                $has = self::logoPath('light') ? 'light' : (self::logoPath('dark') ? 'dark' : null);
                if ($has) {
                    return route('atelier.logo', ['variant' => $has, 'v' => (int)\Option::get('atelier.rev', 1)]);
                }
            } catch (\Throwable $e) {
                self::fail($e);
            }
            return $url;
        }, 30);

        // Manage > Settings > Atelier
        \Eventy::addFilter('settings.sections', function ($sections) {
            $sections['atelier'] = ['title' => 'Atelier', 'icon' => 'adjust', 'order' => 160];
            return $sections;
        }, 40);
        // FreeScout answers 404 for a settings section whose settings array is empty
        \Eventy::addFilter('settings.section_settings', function ($settings, $section) {
            return $section == 'atelier' ? ['atelier' => 1] : $settings;
        }, 20, 2);
        \Eventy::addFilter('settings.view', function ($view, $section) {
            return $section == 'atelier' ? 'atelier::settings' : $view;
        }, 20, 2);
    }

    public static function fail($e)
    {
        try {
            \Log::error('Atelier: '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')');
        } catch (\Throwable $ignored) {
        }
    }

    /** Personal mailbox providers: their logo says nothing about the person, so no logo is looked up */
    public static $freemail = ['gmail.com', 'googlemail.com', 'outlook.com', 'outlook.fr', 'hotmail.com', 'hotmail.fr', 'hotmail.it', 'hotmail.co.uk', 'live.com', 'live.fr', 'msn.com', 'yahoo.com', 'yahoo.fr', 'yahoo.co.uk', 'ymail.com', 'icloud.com', 'me.com', 'mac.com', 'aol.com', 'orange.fr', 'wanadoo.fr', 'free.fr', 'sfr.fr', 'neuf.fr', 'laposte.net', 'bbox.fr', 'numericable.fr', 'club-internet.fr', 'online.fr', 'gmx.com', 'gmx.de', 'gmx.fr', 'gmx.net', 'web.de', 't-online.de', 'libero.it', 'proton.me', 'protonmail.com', 'pm.me', 'hey.com', 'fastmail.com', 'zoho.com', 'tutanota.com', 'mail.com', 'yandex.com', 'yandex.ru', 'qq.com', '163.com', '126.com', 'skynet.be', 'btinternet.com', 'comcast.net', 'verizon.net', 'att.net', 'sbcglobal.net'];

    /** Two-part public suffixes, so that "mail.shop.co.uk" becomes "shop.co.uk" */
    public static $suffix2 = ['co.uk', 'org.uk', 'ac.uk', 'gov.uk', 'com.au', 'net.au', 'org.au', 'co.nz', 'co.jp', 'co.kr', 'co.in', 'co.za', 'com.br', 'com.mx', 'com.ar', 'com.tr', 'com.cn', 'com.hk', 'com.sg', 'com.tw', 'com.pl', 'com.es'];

    /** Domain whose logo stands for an e-mail address ("" when none: personal provider, invalid address) */
    public static function senderDomain($email)
    {
        $email = strtolower(trim((string)$email));
        $at = strrpos($email, '@');
        if ($at === false) {
            return '';
        }
        $d = trim(substr($email, $at + 1), ". \t>");
        if (!self::validDomain($d)) {
            return '';
        }
        $parts = explode('.', $d);
        $keep = (count($parts) > 2 && in_array(implode('.', array_slice($parts, -2)), self::$suffix2, true)) ? 3 : 2;
        if (count($parts) > $keep) {
            $d = implode('.', array_slice($parts, -$keep));
        }
        return in_array($d, self::$freemail, true) ? '' : $d;
    }

    public static function validDomain($d)
    {
        return (bool)preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', (string)$d);
    }

    public static function opt($key, $default)
    {
        $v = \Option::get('atelier.'.$key, null);
        return ($v === null || $v === '') ? $default : $v;
    }

    /** [mailbox id => accent] chosen in Settings > Atelier (only valid, non-default entries). */
    public static function mailboxAccents()
    {
        // FreeScout's Option::get() already turns a stored JSON string into an array
        $raw = \Option::get('atelier.mbx_accents', '');
        if (!is_array($raw)) {
            $raw = json_decode((string)$raw, true);
        }
        $out = [];
        if (is_array($raw)) {
            foreach ($raw as $id => $acc) {
                $norm = \Modules\Atelier\Http\Controllers\AtelierController::normAccent($acc);
                if ((int)$id > 0 && $norm !== null) {
                    $out[(int)$id] = $norm;
                }
            }
        }
        return $out;
    }

    /** Mailbox the current page belongs to (mailbox pages, conversation, new conversation), or 0. */
    public static function currentMailboxId()
    {
        try {
            $route = request()->route();
            if (!$route) {
                return 0;
            }
            $name = (string)$route->getName();
            if (strpos($name, 'mailboxes.') === 0 && (int)$route->parameter('id') > 0 && $name !== 'mailboxes.oauth_callback') {
                return (int)$route->parameter('id');
            }
            if ($name === 'conversations.create') {
                return (int)$route->parameter('mailbox_id');
            }
            if ($name === 'conversations.view') {
                return (int)\App\Conversation::where('id', (int)$route->parameter('id'))->value('mailbox_id');
            }
        } catch (\Throwable $e) {
            self::fail($e);
        }
        return 0;
    }

    /** Absolute path of the uploaded logo ('light' = logo for the light theme, 'dark' = for the dark theme), or null. */
    public static function logoPath($variant)
    {
        $name = \Option::get('atelier.logo_'.$variant, '');
        if (!$name) {
            return null;
        }
        $path = storage_path('app/atelier/'.basename($name));
        return is_file($path) ? $path : null;
    }

    public function register()
    {
    }
}
