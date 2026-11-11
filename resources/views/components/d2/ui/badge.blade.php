@props([
    'tone' => 'neutral',     // neutral | info | success | warning | danger | brand | night; Design 2.0 alias: bronze = brand
    'icon' => null,          // leading icon; status tones default to theirs (info, check, warning, error); false: none
])
{{--
    <x-d2.ui.badge tone="warning">…</x-d2.ui.badge>
    Design 2.1 (brief §3.3, §3.5; docs/d2.1/phase-1/components.md §2): a status label, not a control. 24 px tall,
    radius 6, 13/500, the brief's badge pairs (text on its background, all ≥ 5.5:1): neutral #52525B on #F1F1F3,
    info #2F5DA0 on #EAF1FB, success #1E6B47 on #E8F5EE, warning #8A5300 on #FFF4E0, danger #B42318 on #FDECEA,
    brand #7A5C24 on #F7F1E6; night for dark surfaces. A status badge always shows an icon and text: the status
    tones (info, success, warning, danger) bring their icon unless :icon="false".
--}}
@php
    $tone = $tone === 'bronze' ? 'brand' : $tone;
    $tone = in_array($tone, ['neutral', 'info', 'success', 'warning', 'danger', 'brand', 'night'], true) ? $tone : 'neutral';
    $status = ['info' => 'info', 'success' => 'check', 'warning' => 'warning', 'danger' => 'error'];
    $icon = $icon === false ? null : ($icon ?? ($status[$tone] ?? null));
@endphp
<span {{ $attributes->class(['badge', 'badge--'.$tone]) }}>
    @if ($icon)<x-d2.ui.icon :name="$icon" size="16" class="badge__icon" />@endif
    <span class="badge__label">{{ $slot }}</span>
</span>
