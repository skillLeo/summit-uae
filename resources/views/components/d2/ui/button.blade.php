@props([
    'variant' => 'primary',   // ink | gold | secondary | text | night | whatsapp; Design 2.0 aliases: primary = ink (the default), signature = gold, ghost = text
    'size' => 48,             // 44 px: 40 | 44 | 48 (default) · hero 52 px: 52 | 56
    'href' => null,           // renders <a> when set
    'type' => 'button',
    'icon' => null,           // leading icon (whatsapp: Meta's logo, the default of the whatsapp variant); a decorative arrow renders nothing
    'iconEnd' => null,        // trailing icon (external, chevron-down / -up for a disclosure, form-next); a decorative arrow renders nothing
    'loading' => false,
    'disabled' => false,
    'full' => false,          // full width
    'external' => null,       // opens in a new tab (+ hidden "opens in a new tab"); default: WhatsApp links and target="_blank"
])
{{--
    <x-d2.ui.button variant="gold" size="52" href="…">Label</x-d2.ui.button>      (the hero's primary button only)
    Design 2.1 (brief §3.5, D03, D06; docs/d2.1/phase-1/components.md §1):
    - ink (default, also the header CTA): ink fill, white label; on a dark surface it inverts (white, ink label).
      gold: flat --gold with an ink label, the hero's primary button ONLY, one per page. secondary: white, 1px
      --line-strong. text: an underlined label. night: ink on dark. whatsapp: the white secondary button with an
      ink label and the official WhatsApp logo (<x-d2.ui.brand-mark>, never green text or a green fill).
    - The class names follow the value you pass (btn--primary, btn--signature, btn--ghost keep working), so
      existing markup keeps its hooks; new code writes the new names. size 40 / 44 / 48 = the 44 px button, 52 / 56 =
      the 52 px hero button.
    - No arrows (D03): icon or icon-end "arrow", "arrow-start", "chevron" or "chevron-start" renders nothing. The
      allowed exceptions have their own names: form-back / form-next (the form's Back and Next), carousel-prev /
      carousel-next; a link to another website takes icon-end="external" (↗ as an SVG). Disclosure toggles keep
      chevron-down / chevron-up.
    States: hover = the fill 8% lighter or darker (a wash that fades, 180 ms), press = scale(.98) for 120 ms,
    focus-visible = 2px ring + 2px offset (light gold on dark), disabled = 40 %, loading = a 16px spinner in place
    of the icon (or over the label when there is none), same width either way.
    - Disabled and loading use aria-disabled, so focus stays on the control. modules/disabled-guard.js blocks
      every click (and so keyboard activation and form submission) while aria-disabled="true".
    - A disabled link loses its href (role="link" + aria-disabled, still focusable): it cannot navigate.
    - JS may toggle .is-loading + aria-disabled="true" + aria-busy="true" itself: the spinner is always in
      the markup, so the width never changes. Announce busy text yourself (ui('form.state.busy')).
    - Attributes you pass (target, rel, data-*, x-on:…) win over the defaults.
    - Styleguide-only modifiers .is-hover / .is-active / .is-focus show those states statically.
--}}
@php
    $variants = ['ink', 'primary', 'gold', 'signature', 'secondary', 'text', 'ghost', 'night', 'whatsapp'];
    $variant = in_array($variant, $variants, true) ? $variant : 'primary';
    $size = in_array((int) $size, [40, 44, 48, 52, 56], true) ? (int) $size : 48;
    $icon ??= $variant === 'whatsapp' ? 'whatsapp' : null;
    // D03: arrows and "go" chevrons are decoration in a button; the allowed ones have explicit names (Icons::ALIASES).
    $icon = is_string($icon) && $icon !== '' && ! \App\Design\D2\Icons::isArrow($icon) ? $icon : null;
    $iconEnd = is_string($iconEnd) && $iconEnd !== '' && ! \App\Design\D2\Icons::isArrow($iconEnd) ? $iconEnd : null;
    $external ??= $href && ($variant === 'whatsapp' || $attributes->get('target') === '_blank');
    $inert = $disabled || $loading;
    $tag = $href ? 'a' : 'button';
    $defaults = $href
        ? ($inert ? ['role' => 'link', 'tabindex' => '0'] : ['href' => $href] + ($external ? ['target' => '_blank', 'rel' => 'noopener noreferrer'] : []))
        : ['type' => $type];
    if ($inert) {
        $defaults['aria-disabled'] = 'true';
    }
    if ($loading) {
        $defaults['aria-busy'] = 'true';
    }
    $classes = ['btn', 'btn--'.$variant, 'btn--'.$size, 'btn--full' => $full, 'is-loading' => $loading];
@endphp
<{{ $tag }} {{ $attributes->merge($defaults)->class($classes) }}>
    @if ($icon)
        <span class="btn__icon" aria-hidden="true"><x-d2.ui.icon :name="$icon" /><span class="btn__spinner"></span></span>
    @else
        <span class="btn__spinner" aria-hidden="true"></span>
    @endif
    <span class="btn__label">{{ $slot }}</span>
    @if ($iconEnd)
        <span class="btn__icon btn__icon--end" aria-hidden="true"><x-d2.ui.icon :name="$iconEnd" /></span>
    @endif
    @if ($loading)<span class="sr-only" @proposed>{{ d2p('controls.loading') }}</span>@endif
    @if ($href && $external && ! $inert)<span class="sr-only" @proposed>{{ d2p('controls.new_tab') }}</span>@endif
</{{ $tag }}>
