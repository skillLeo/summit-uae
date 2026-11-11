@props([
    'icon',                  // required: icon name (App\Design\D2\Icons: a functional icon; carousel-prev / carousel-next for a carousel)
    'label',                 // required: accessible name (aria-label) and the tooltip text
    'variant' => 'ghost',    // ghost | secondary | night
    'size' => 40,            // 40 (44 px hit area) | 44 | 48
    'href' => null,          // renders <a> when set
    'type' => 'button',
    'loading' => false,
    'disabled' => false,
    'tip' => 'top',          // built-in tooltip: top | bottom | top-start | top-end | bottom-start | bottom-end | none (bring your own)
    'external' => null,      // opens in a new tab (default: when target="_blank")
])
{{--
    <x-d2.ui.icon-button icon="close" :label="ui('a11y.close_menu')" variant="secondary" />
    Square button whose icon is its visible label (brief §3.5; docs/d2.1/phase-1/components.md §1). It shares the
    .btn states: hover = an 8% wash (ghost: the icon turns ink), press = scale(.98), the 2px focus ring,
    aria-disabled 40 %, loading = the spinner in place of the icon. No shadow, no lift. Radius 6.
    The label is the accessible name (aria-label); the tooltip (an overlay, data-overlay) repeats it on hover and
    keyboard focus after 120 ms and is hidden from assistive technology, so it is never the only label. Near the
    edge of the screen align it with the button (tip="bottom-end" for an icon button at the inline end of a bar).
    Targets: 40px buttons get an invisible 44px hit area (48px on touch screens).
--}}
@php
    $variant = in_array($variant, ['ghost', 'secondary', 'night'], true) ? $variant : 'ghost';
    $size = in_array((int) $size, [40, 44, 48], true) ? (int) $size : 40;
    $tip = in_array($tip, ['top', 'bottom', 'top-start', 'top-end', 'bottom-start', 'bottom-end', 'none'], true) ? $tip : 'top';
    $external ??= $href && $attributes->get('target') === '_blank';
    $inert = $disabled || $loading;
    $tag = $href ? 'a' : 'button';
    $defaults = ['aria-label' => $label.($href && $external && ! $inert ? ' '.d2p('controls.new_tab') : '')];
    $defaults += $href
        ? ($inert ? ['role' => 'link', 'tabindex' => '0'] : ['href' => $href] + ($external ? ['target' => '_blank', 'rel' => 'noopener noreferrer'] : []))
        : ['type' => $type];
    if ($inert) {
        $defaults['aria-disabled'] = 'true';
    }
    if ($loading) {
        $defaults['aria-busy'] = 'true';
    }
    $classes = ['btn', 'btn--'.$variant, 'btn--'.$size, 'icon-btn', 'is-loading' => $loading];
@endphp
<{{ $tag }} {{ $attributes->merge($defaults)->class($classes) }}>
    <span class="btn__icon" aria-hidden="true"><x-d2.ui.icon :name="$icon" /><span class="btn__spinner"></span></span>
    @if ($tip !== 'none')
        <span class="icon-btn__tip icon-btn__tip--{{ $tip }}" aria-hidden="true" data-overlay>{{ $label }}</span>
    @endif
</{{ $tag }}>
