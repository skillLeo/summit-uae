@props([
    'as' => 'button',        // button | a | span (static label, no states)
    'href' => null,          // implies as="a"
    'pressed' => null,       // true/false: toggle chip (aria-pressed on buttons, aria-current on links); null: plain
    'icon' => null,          // retired (§3.5: no icons in chips, D04): accepted and ignored
    'count' => null,         // optional trailing number (ink-3, proportional lining figures)
    'variant' => 'filter',   // filter (light surfaces) | night (dark surfaces); Design 2.0 choice and hotspot draw the one chip too
    'type' => 'button',
    'disabled' => false,
])
{{--
    <x-d2.ui.chip :pressed="false" count="8">Label</x-d2.ui.chip>
    Design 2.1 (brief §3.5, D04; docs/d2.1/phase-1/components.md §2): ONE chip, 32 px tall, a pill, with a 44 px hit
    area (48 px on touch screens). Unselected: white with a 1px --line-strong edge, ink-2 label. Selected
    (aria-pressed / aria-current, .is-pressed on a span): ink fill, white label. The optional count is ink-3.
    No dots, no icons: `icon` renders nothing, and the Design 2.0 hotspot pin is gone. On dark surfaces use
    variant="night" (dark fill, hairline edge; selected = white with an ink label).
    Bind the state from Alpine with x-bind:aria-pressed on button chips. Disabled button chips use aria-disabled
    (focus stays); modules/disabled-guard.js blocks activation.
    Styleguide-only modifiers: .is-hover, .is-active, .is-focus, .is-pressed (also the pressed look of a span).
--}}
@php
    $tag = $href ? 'a' : (in_array($as, ['button', 'a', 'span'], true) ? $as : 'button');
    $variant = in_array($variant, ['filter', 'choice', 'hotspot', 'night'], true) ? $variant : 'filter';
    $classes = ['chip', 'chip--'.$variant, 'chip--static' => $tag === 'span', 'is-pressed' => $tag === 'span' && $pressed];
@endphp
<{{ $tag }} {{ $attributes->class($classes) }}
    @if ($tag === 'button') type="{{ $type }}" @endif
    @if ($tag === 'a' && $href && ! $disabled) href="{{ $href }}" @endif
    @if ($tag === 'button' && $pressed !== null) aria-pressed="{{ $pressed ? 'true' : 'false' }}" @endif
    @if ($tag === 'a' && $pressed) aria-current="true" @endif
    @if ($disabled && $tag !== 'span') aria-disabled="true" @if ($tag === 'a') role="link" tabindex="0" @endif @endif>
    <span class="chip__label">{{ $slot }}</span>
    @if ($count !== null && $count !== '')<span class="chip__count">{{ $count }}</span>@endif
</{{ $tag }}>
