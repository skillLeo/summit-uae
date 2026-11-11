@props([
    'href',                  // required
    'variant' => 'body',     // body (ink, underlined) | nav (ink-2, ink on hover and when current; no underline)
    'external' => null,      // another website: opens in a new tab with the ↗ icon and the hidden "opens in a new tab"; default: target="_blank"
    'current' => false,      // nav links: aria-current="page"
])
{{--
    <x-d2.ui.link :href="$url">Approved link text</x-d2.ui.link>
    <x-d2.ui.link :href="$site['url']" external>…</x-d2.ui.link>            (a link to another website: ↗ after the text)
    Design 2.1 links (brief §3.5, §3.6 rule 3; docs/d2.1/phase-1/components.md §2):
    - body: ink, underline 1px at a 3px offset, 2px on hover (base.css .link);
    - nav: ink-2, ink on hover and when current (base.css .nav-link); no underline;
    - external: target="_blank" rel="noopener noreferrer", the external-link icon (an SVG, never the "↗" character)
      after the text, and the proposed "(opens in a new tab)" for screen readers. No other arrow on a link, ever.
    On dark surfaces both turn to the on-dark colours (base.css). Every other attribute lands on the <a>.
    The ↗ sits in an inline-block (.link__external), so no trailing space inside the link is ever underlined.
--}}
@php
    $external ??= $attributes->get('target') === '_blank';
    $defaults = ['href' => $href] + ($external ? ['target' => '_blank', 'rel' => 'noopener noreferrer'] : []);
    if ($current) {
        $defaults['aria-current'] = 'page';
    }
    $classes = [$variant === 'nav' ? 'nav-link' : 'link', 'link--external' => $external];
@endphp
<a {{ $attributes->merge($defaults)->class($classes) }}>{{ $slot }}@if ($external)<span class="link__external"><x-d2.ui.icon name="external" size="16" /></span><span class="sr-only" @proposed>{{ d2p('controls.new_tab') }}</span>@endif</a>
