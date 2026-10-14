@php
    $opt = function ($k, $d) { return \Modules\Atelier\Providers\AtelierServiceProvider::opt($k, $d); };
    $accent = $opt('accent4', 'azur');
    $swatches = ['azur' => ['#2b63d9', __('Azure')], 'indigo' => ['#4f46e5', __('Indigo')], 'violet' => ['#6d4fd6', __('Violet')], 'emerald' => ['#0b7a58', __('Emerald')], 'bordeaux' => ['#a8264d', __('Burgundy')], 'terracotta' => ['#b9582f', __('Terracotta')], 'gold' => ['#946610', __('Gold')]];
    $palette = $opt('palette_default', 'graphite');
    $palettes = ['graphite' => [__('Graphite'), '#f1f2f4', '#1b1d21'], 'ardoise' => [__('Slate'), '#eef1f5', '#1c2127'], 'minuit' => [__('Midnight'), '#eff1f7', '#151a2b'], 'foret' => [__('Forest'), '#eff3f0', '#181e1b'], 'sable' => [__('Sand'), '#f4f0e8', '#211e1a']];
    $rev = (int)\Option::get('atelier.rev', 1);
    $mailboxes = \App\Mailbox::orderBy('name')->get();
    $mbxAccents = \Modules\Atelier\Providers\AtelierServiceProvider::mailboxAccents();
    $accentTable = \Modules\Atelier\Http\Controllers\AtelierController::$accents;
    // FreeScout's Content-Security-Policy only runs inline scripts that carry its nonce
    $nonce = (class_exists('\Helper') && method_exists('\Helper', 'cspNonceAttr')) ? \Helper::cspNonceAttr() : '';
@endphp
@php
    $upd = \Modules\Atelier\Services\Updater::class;
    $updRepo = $upd::repo();
    $updInfo = $updRepo ? $upd::latest(false, 4) : null;
    $updNew = $updRepo && $upd::hasUpdate($updInfo);
@endphp
<div class="at-update margin-top @if ($updNew) at-update-new @endif">
    <div class="at-update-main">
        <strong>Atelier {{ $upd::current() }}</strong>
        @if (!$updRepo)
            <span class="text-muted"> &middot; {{ __('Updates are not set up: no repository in module.json.') }}</span>
        @elseif ($updNew)
            <span> &middot; {{ __('Atelier :v is available.', ['v' => $updInfo['version']]) }}</span>
        @elseif (isset($updInfo['error']))
            <span class="text-muted"> &middot; {{ __('Could not reach GitHub: :e', ['e' => $updInfo['error']]) }}</span>
        @else
            <span class="text-muted"> &middot; {{ __('Atelier is up to date.') }}</span>
        @endif
        @if ($updRepo && !empty($updInfo['checked']))
            <small class="text-muted"> ({{ __('checked :t', ['t' => date('Y-m-d H:i', $updInfo['checked'])]) }})</small>
        @endif
    </div>
    @if ($updRepo)
        <div class="at-update-actions">
            @if ($updNew)
                @if ($upd::canWrite())
                    <form method="POST" action="{{ route('atelier.update.apply') }}" data-confirm="{{ __('Install the update now? The current version is backed up first.') }}" style="display:inline">
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Update now') }}</button>
                    </form>
                @else
                    <span class="text-warning">{{ __('The module folder is not writable: update it by hand.') }}</span>
                @endif
                <a href="{{ $updInfo['url'] }}" target="_blank" rel="noopener" class="btn btn-default btn-sm">{{ __('Release notes') }}</a>
            @endif
            <a href="{{ route('atelier.update.check') }}" class="btn btn-default btn-sm">{{ __('Check now') }}</a>
        </div>
    @endif
</div>
<form id="at-settings" class="form-horizontal margin-top margin-bottom" method="POST" action="{{ route('atelier.save') }}" enctype="multipart/form-data" autocomplete="off">
    {{ csrf_field() }}

    <h3 class="subheader">{{ __('Appearance') }}</h3>

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Default theme') }}</label>
        <div class="col-sm-6">
            <select name="theme_default" class="form-control input-sized-lg">
                @foreach (['auto' => __('Automatic (follows the device)'), 'light' => __('Light'), 'dark' => __('Dark')] as $k => $label)
                    <option value="{{ $k }}" @if ($opt('theme_default', 'auto') == $k) selected @endif>{{ $label }}</option>
                @endforeach
            </select>
            <p class="form-help">{{ __('Each agent can change it with the contrast button in the top bar.') }}</p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Ambiance') }}</label>
        <div class="col-sm-6">
            @foreach ($palettes as $k => $pl)
                <label class="radio-inline" style="margin-right:18px">
                    <input type="radio" name="palette_default" value="{{ $k }}" @if ($palette == $k) checked @endif>
                    <span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:linear-gradient(135deg,{{ $pl[1] }} 50%,{{ $pl[2] }} 50%);border:1px solid #bbb;vertical-align:-4px"></span> {{ $pl[0] }}
                </label>
            @endforeach
            <p class="form-help">{{ __('Neutral tones for the light and dark themes. Each agent can pick another one from the contrast button in the top bar.') }}</p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Accent colour') }}</label>
        <div class="col-sm-8">
            @include('atelier::picker', ['name' => 'accent', 'value' => $accent, 'default' => false])
            <p class="form-help">{{ __('Pick one of the colours or any colour you like with the rainbow circle. Contrast is adjusted automatically so buttons and links stay readable in both themes.') }}</p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Preview') }}</label>
        <div class="col-sm-8">
            <div class="at-pv-wrap">
                @foreach (['light' => __('Light'), 'dark' => __('Dark')] as $th => $thLabel)
                    <div class="at-pv at-keep" data-th="{{ $th }}">
                        <div class="at-pv-nav"><span class="at-pv-logo"></span><span class="at-pv-tab">{{ __('Inbox') }}</span><span class="at-pv-nt">{{ __('Manage') }}</span><span class="at-pv-th">{{ $thLabel }}</span></div>
                        <div class="at-pv-body">
                            <div class="at-pv-card">
                                <div class="at-pv-h"><b>Customer Care</b><small>care@example.com</small></div>
                                <div class="at-pv-row"><span>{{ __('Unassigned') }}</span><em>3</em></div>
                                <div class="at-pv-row at-pv-soft"><span>{{ __('Mine') }}</span><em>2</em></div>
                                <div class="at-pv-foot"><span class="at-pv-btn">{{ __('New') }}</span><span class="at-pv-link">{{ __('Open') }}</span></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Density') }}</label>
        <div class="col-sm-6">
            <select name="density_default" class="form-control input-sized-lg">
                <option value="comfortable" @if ($opt('density_default', 'comfortable') == 'comfortable') selected @endif>{{ __('Comfortable') }}</option>
                <option value="compact" @if ($opt('density_default', 'comfortable') == 'compact') selected @endif>{{ __('Compact') }}</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <div class="col-sm-6 col-sm-offset-2">
            <label class="checkbox-inline"><input type="checkbox" name="icons_modern" value="1" @if ($opt('icons_modern', '1') === '1') checked @endif> {{ __('Modern line icons') }}</label>
            <p class="form-help">{{ __('Replaces the standard FreeScout icons with fine line icons. Untick to get the original icons back.') }}</p>
        </div>
    </div>

    <div class="form-group">
        <div class="col-sm-6 col-sm-offset-2">
            <label class="checkbox-inline"><input type="checkbox" name="hide_other_toggle" value="1" @if ($opt('hide_other_toggle', '1') === '1') checked @endif> {{ __('Hide the switch of another dark-mode module') }}</label>
            <p class="form-help">{{ __('Atelier replaces it. For a clean result, also deactivate that module in Manage > Modules.') }}</p>
        </div>
    </div>

    <div class="form-group">
        <div class="col-sm-6 col-sm-offset-2">
            <label class="checkbox-inline"><input type="checkbox" name="sender_logos" value="1" @if ($opt('sender_logos', '1') === '1') checked @endif> {{ __("Show the sender's logo as the avatar of customers without a photo") }}</label>
            <p class="form-help">{{ __("Uses the logo of the sender's company website (personal addresses such as Gmail are skipped). The server fetches it once from a public logo service and keeps it for 30 days; only the domain name is sent, never the message.") }}</p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('E-mail display') }}</label>
        <div class="col-sm-6">
            <select name="mail_default" class="form-control input-sized-lg">
                <option value="adapted" @if ($opt('mail_default', 'adapted') == 'adapted') selected @endif>{{ __('Adapted to dark mode') }}</option>
                <option value="paper" @if ($opt('mail_default', 'adapted') == 'paper') selected @endif>{{ __('Original (light background)') }}</option>
            </select>
            <p class="form-help">{{ __('In dark mode, customers\' e-mails are recoloured to match, or shown on their original light background. Agents can switch each message with the sun button.') }}</p>
        </div>
    </div>

    @if (count($mailboxes) > 1)
    <h3 class="subheader">{{ __('Accent colour per mailbox') }}</h3>
    <div class="form-group">
        <div class="col-sm-8 col-sm-offset-2">
            <p class="form-help" style="margin-top:0">{{ __('Gives each mailbox its own accent colour: a coloured dot and edge on its dashboard card, and buttons and links inside the mailbox. Leave on "Default" to keep the global accent.') }}</p>
            <div class="checkbox" style="margin: 0 0 10px"><label><input type="checkbox" name="mbx_recolor" value="1" @if ($opt('mbx_recolor', '0') === '1') checked @endif> {{ __('Also use the mailbox colour for buttons and links inside the mailbox') }}</label>
                <p class="form-help" style="margin:2px 0 0 20px">{{ __('Off: the dot and the card keep the mailbox colour, the rest of the interface keeps the global accent.') }}</p></div>
            <div class="at-mbx-tools">
                <button type="button" class="btn btn-default btn-sm" id="at-mbx-auto">{{ __('Automatic colours') }}</button>
                <button type="button" class="btn btn-default btn-sm" id="at-mbx-reset">{{ __('Reset all') }}</button>
            </div>
            <div class="at-mbx-table">
                @foreach ($mailboxes as $mb)
                    <div class="at-mbx-row">
                        <div class="at-mbx-name"><strong>{{ $mb->name }}</strong><br><small class="text-muted">{{ $mb->email }}</small></div>
                        <div class="at-mbx-ctl">
                            @include('atelier::picker', ['name' => 'mbx['.$mb->id.']', 'value' => $mbxAccents[$mb->id] ?? '', 'default' => true, 'popover' => true])
                            <div class="at-chips"><span class="at-chip at-keep" data-th="light"><i></i>{{ $mb->name }}<b>{{ __('Open') }}</b></span><span class="at-chip at-keep" data-th="dark"><i></i>{{ $mb->name }}<b>{{ __('Open') }}</b></span></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <h3 class="subheader">{{ __('Logos') }}</h3>

    @foreach (['light' => __('Logo for the light theme (dark artwork)'), 'dark' => __('Logo for the dark theme (light artwork)')] as $variant => $label)
        <div class="form-group">
            <label class="col-sm-2 control-label">{{ $label }}</label>
            <div class="col-sm-6">
                @if (\Modules\Atelier\Providers\AtelierServiceProvider::logoPath($variant))
                    <div style="margin-bottom:8px;padding:10px;border-radius:8px;display:inline-block;background:{{ $variant == 'dark' ? '#101626' : '#ffffff' }};border:1px solid #cbd2e0">
                        <img src="{{ route('atelier.logo', ['variant' => $variant, 'v' => $rev]) }}" style="height:40px" alt="">
                    </div><br>
                    <label class="checkbox-inline"><input type="checkbox" name="remove_logo_{{ $variant }}" value="1"> {{ __('Remove') }}</label><br>
                @endif
                <input type="file" name="logo_{{ $variant }}" accept=".png,.jpg,.jpeg,.gif,.webp,.svg">
            </div>
        </div>
    @endforeach
    <div class="form-group">
        <div class="col-sm-6 col-sm-offset-2">
            <label class="checkbox-inline"><input type="checkbox" name="logo_keep" value="1" @if ($opt('logo_keep', '0') === '1') checked @endif> {{ __('Keep the default logo\'s own colours in the light theme') }}</label>
            <p class="form-help">{{ __('By default, without an uploaded logo, the standard white FreeScout logo is darkened on the light bar so that it stays visible.') }}</p>
            <p class="form-help">{{ __('PNG, JPG, GIF, WEBP or SVG, 1 MB maximum. With only one logo, it is inverted automatically in the other theme (fine for single-colour logos). Without any logo, the usual FreeScout logo is kept.') }}</p>
        </div>
    </div>

    <h3 class="subheader">{{ __('Advanced') }}</h3>

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Extra CSS') }}</label>
        <div class="col-sm-6">
            <textarea name="extra_css" rows="8" class="form-control input-sized-lg" style="font-family:monospace;font-size:12px" placeholder="html[data-at-theme=&quot;dark&quot;] .my-module-button { color: #fff; }">{{ $opt('extra_css', '') }}</textarea>
            <p class="form-help">{{ __('Loaded after the theme. To fix a third-party module in one theme only, prefix the selector with html[data-at-theme="dark"] or html[data-at-theme="light"].') }}</p>
        </div>
    </div>

    <div class="form-group margin-top">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </div>
</form>

<form id="at-import" class="form-horizontal margin-bottom" data-confirm="{{ __('Importing replaces the current settings.') }}" method="POST" action="{{ route('atelier.import') }}" enctype="multipart/form-data" autocomplete="off">
    {{ csrf_field() }}
    <h3 class="subheader">{{ __('Backup') }}</h3>
    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Export settings') }}</label>
        <div class="col-sm-6">
            <a href="{{ route('atelier.export') }}" class="btn btn-default"><i class="glyphicon glyphicon-download-alt"></i> {{ __('Export settings') }}</a>
            <p class="form-help">{{ __('Exports every Atelier setting, both logos and the accent of each mailbox, in one file. Mailboxes are matched by e-mail address on import.') }}</p>
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Import settings') }}</label>
        <div class="col-sm-6">
            <input type="file" name="settings_file" accept=".json,application/json" required>
            <button type="submit" class="btn btn-default margin-top-10"><i class="glyphicon glyphicon-upload"></i> {{ __('Import') }}</button>
            <p class="form-help">{{ __('Importing replaces the current settings.') }}</p>
        </div>
    </div>
</form>

<div id="at-preview-bar" style="display:none">
    <span>{{ __('Preview: changes are not saved yet') }}</span>
    <button type="button" class="btn btn-primary btn-sm" id="at-pv-save">{{ __('Save') }}</button>
    <button type="button" class="btn btn-default btn-sm" id="at-pv-cancel">{{ __('Cancel preview') }}</button>
</div>
<style>
    .at-update { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px; padding: 12px 16px; border-radius: 12px; border: 1px solid var(--at-line-2, #ddd); background: var(--at-surface, #fff); max-width: 960px; }
    .at-update-new { border-color: var(--at-accent, #2b63d9); background: var(--at-soft, #eef3ff); }
    .at-update-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    #at-preview-bar { position: fixed; left: 50%; bottom: 22px; transform: translateX(-50%); z-index: 1050; display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 14px; background: var(--at-surface, #fff); color: var(--at-text, #222); border: 1px solid var(--at-line-2, #ccc); box-shadow: 0 14px 34px rgba(0,0,0,.25); font-size: 14px; max-width: calc(100vw - 24px); flex-wrap: wrap; }
    #at-preview-bar[style*="display: none"] { display: none !important; }
    #at-preview-bar span { flex: 1 1 auto; }
    @media (max-width: 767px) { #at-preview-bar { left: 12px; right: 12px; bottom: 12px; transform: none; max-width: none; } #at-preview-bar span { flex-basis: 100%; } #at-preview-bar .btn { flex: 1 1 0; min-height: 40px; } }
    .at-pick { position: relative; max-width: 560px; }
    .at-pick-grid { display: flex; flex-wrap: wrap; align-items: center; }
    .at-sw { width: 30px; height: 30px; min-width: 30px; border-radius: 50%; border: 2px solid transparent; box-shadow: inset 0 0 0 1px rgba(128,128,128,.45); margin: 3px; padding: 0; display: inline-block; cursor: pointer; position: relative; background-clip: padding-box; outline-offset: 2px; transition: transform .1s; }
    .at-sw:hover { transform: scale(1.12); }
    .at-sw.on { border-color: var(--at-text, #222); box-shadow: 0 0 0 2px var(--at-surface, #fff) inset; transform: scale(1.12); }
    .at-sw.on::after { content: "\2713"; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 15px; font-weight: 700; text-shadow: 0 0 3px rgba(0,0,0,.75); line-height: 1; }
    .at-sw-none { background: repeating-linear-gradient(45deg, transparent 0 4px, rgba(128,128,128,.5) 4px 5px) !important; }
    .at-sw-none.on::after { color: var(--at-text, #222); text-shadow: none; }
    .at-sw-custom { background: conic-gradient(#f43f5e, #f59e0b, #84cc16, #06b6d4, #6366f1, #d946ef, #f43f5e); overflow: hidden; }
    .at-sw-custom input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; border: 0; padding: 0; }
    .at-pick-now { margin: 6px 3px 0; display: flex; align-items: center; gap: 8px; }
    .at-pick-dot { display: inline-block; width: 16px; height: 16px; border-radius: 50%; box-shadow: inset 0 0 0 1px rgba(128,128,128,.5); background: var(--at-accent, #888); }
    .at-pick-trigger { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px 6px 8px; min-height: 40px; border-radius: 10px; border: 1px solid var(--at-line-2, #ccc); background: var(--at-surface, #fff); color: var(--at-text, #222); cursor: pointer; font-size: 14px; }
    .at-pick-trigger .at-pick-caret { color: var(--at-muted, #888); font-size: 11px; }
    .at-pop { display: none; position: absolute; left: 0; top: calc(100% + 6px); z-index: 40; padding: 10px; border-radius: 14px; background: var(--at-surface, #fff); border: 1px solid var(--at-line-2, #ccc); box-shadow: 0 16px 38px rgba(0,0,0,.28); width: 290px; max-width: calc(100vw - 32px); }
    .at-pick.at-open .at-pop { display: block; }
    .at-mbx-tools { display: flex; gap: 8px; flex-wrap: wrap; margin: 4px 0 6px; }
    .at-mbx-row { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 6px 18px; padding: 12px 0; border-top: 1px solid rgba(128,128,128,.28); max-width: 640px; }
    .at-mbx-name { flex: 1 1 200px; min-width: 0; overflow-wrap: anywhere; }
    .at-mbx-ctl { flex: 1 1 240px; min-width: 0; }
    @media (max-width: 767px) { .at-sw { width: 36px; height: 36px; min-width: 36px; } .at-pop { width: 100%; } .at-pick-trigger { width: 100%; justify-content: flex-start; } .at-pick-trigger .at-pick-caret { margin-left: auto; } .at-mbx-ctl { flex-basis: 100%; } }
    .at-pv-wrap { display: flex; gap: 14px; flex-wrap: wrap; position: sticky; top: 8px; z-index: 5; }
    .at-pv { flex: 1 1 240px; max-width: 300px; border-radius: 12px; overflow: hidden; border: 1px solid var(--p-line); background: var(--p-page); color: var(--p-text); font-size: 12px; box-shadow: 0 6px 18px rgba(0,0,0,.12); }
    .at-pv-nav { display: flex; align-items: center; gap: 10px; padding: 8px 10px; background: var(--p-nav); border-bottom: 1px solid var(--p-line); }
    .at-pv-logo { width: 14px; height: 14px; border-radius: 50%; background: var(--p-text); }
    .at-pv-tab { padding: 3px 8px; border-radius: 6px; background: var(--p-soft); color: var(--p-link); font-weight: 600; }
    .at-pv-nt { color: var(--p-muted); }
    .at-pv-th { margin-left: auto; color: var(--p-muted); font-size: 11px; }
    .at-pv-body { padding: 10px; }
    .at-pv-card { background: var(--p-surface); border: 1px solid var(--p-line); border-radius: 10px; overflow: hidden; }
    .at-pv-h { background: var(--p-raised); padding: 7px 10px; display: flex; flex-direction: column; }
    .at-pv-h small { color: var(--p-muted); }
    .at-pv-row { display: flex; justify-content: space-between; padding: 5px 10px; color: var(--p-muted); }
    .at-pv-row em { font-style: normal; font-weight: 600; color: var(--p-text); }
    .at-pv-soft { background: var(--p-soft); color: var(--p-link); }
    .at-pv-soft em { color: var(--p-link); }
    .at-pv-foot { display: flex; align-items: center; gap: 12px; padding: 8px 10px; }
    .at-pv-btn { background: var(--p-accent); color: #fff; border-radius: 7px; padding: 4px 10px; font-weight: 600; }
    .at-pv-link { color: var(--p-link); font-weight: 600; }
    .at-chips { display: flex; gap: 8px; flex-wrap: wrap; margin: 2px 3px 4px; }
    .at-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 8px; border-radius: 8px; font-size: 12px; background: var(--p-surface); color: var(--p-text); border: 1px solid var(--p-line); }
    .at-chip i { width: 9px; height: 9px; border-radius: 50%; background: var(--p-dot, var(--p-accent)); box-shadow: 0 0 0 3px var(--p-soft); }
    .at-chip b { background: var(--p-accent); color: #fff; border-radius: 5px; padding: 1px 7px; font-weight: 600; }
    .at-chip[data-none] { border-style: dashed; }
</style>
<script type="application/json" id="at-cfg">{!! json_encode(['accents' => array_map(function ($p) { return ['light' => $p[0], 'dark' => $p[1]]; }, $accentTable), 'palettes' => ['graphite' => [220, 6, 8], 'ardoise' => [208, 12, 14], 'minuit' => [230, 14, 20], 'foret' => [150, 7, 9], 'sable' => [40, 14, 9]]]) !!}</script>
<script{!! $nonce !!}>{!! @file_get_contents(\Modules\Atelier\Providers\AtelierServiceProvider::modulePath().'/Public/js/settings.js') !!}</script>
