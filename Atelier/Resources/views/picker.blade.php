@php
    // one palette, ordered by hue: the hand-tuned keys and the extra presets mixed together
    $palette = [
        'azur' => ['#2b63d9', __('Azure')], '#0891b2' => ['#0891b2', __('Cyan')], '#0d9488' => ['#0d9488', __('Teal')],
        'emerald' => ['#0b7a58', __('Emerald')], '#65a30d' => ['#65a30d', __('Lime')], 'gold' => ['#946610', __('Gold')],
        '#ea580c' => ['#ea580c', __('Orange')], 'terracotta' => ['#b9582f', __('Terracotta')], '#dc2626' => ['#dc2626', __('Red')],
        'bordeaux' => ['#a8264d', __('Burgundy')], '#db2777' => ['#db2777', __('Rose')], 'violet' => ['#6d4fd6', __('Violet')],
        'indigo' => ['#4f46e5', __('Indigo')], '#475569' => ['#475569', __('Slate')],
    ];
    $isCustom = $value !== '' && !isset($palette[$value]);
    $popover = !empty($popover);
@endphp
<div class="at-pick @if ($popover) at-pick-pop @endif" data-default-label="{{ __('Default') }}">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" class="at-pick-val">
    @if ($popover)
        <button type="button" class="at-pick-trigger" aria-haspopup="true" aria-expanded="false"><i class="at-pick-dot"></i><span class="at-pick-label"></span><span class="at-pick-caret">&#9662;</span></button>
        <div class="at-pop">
    @endif
    <div class="at-pick-grid">
        @if (!empty($default))
            <button type="button" class="at-sw at-sw-none" data-v="" data-label="{{ __('Default') }}" title="{{ __('Default') }}" aria-label="{{ __('Default') }}"></button>
        @endif
        @foreach ($palette as $k => $meta)
            <button type="button" class="at-sw" data-v="{{ $k }}" data-c="{{ $meta[0] }}" data-label="{{ $meta[1] }}" style="background:{{ $meta[0] }}" title="{{ $meta[1] }}" aria-label="{{ $meta[1] }}"></button>
        @endforeach
        <label class="at-sw at-sw-custom @if ($isCustom) on @endif" title="{{ __('Custom colour') }}" @if ($isCustom) style="background:{{ $value }}" @endif>
            <input type="color" class="at-pick-custom" value="{{ $isCustom ? $value : '#6366f1' }}" aria-label="{{ __('Custom colour') }}">
        </label>
    </div>
    @if ($popover)
        </div>
    @else
        <div class="at-pick-now"><i class="at-pick-dot"></i> {{ __('Selected colour') }} : <strong class="at-pick-label"></strong></div>
    @endif
</div>
