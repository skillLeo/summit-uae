@props(['name' => 'whatsapp', 'size' => 20, 'label' => null])
{{--
    <x-d2.ui.brand-mark name="whatsapp" size="20" /> : the official WhatsApp logo (brief §3.8, D10). Every WhatsApp
    control reaches it through <x-d2.ui.icon name="whatsapp"> (also the default icon of <x-d2.ui.button
    variant="whatsapp">), so dropping Meta's files in switches all of them at once, with no code change.

    Meta's files are shown UNCHANGED in an <img> (App\Design\D2\WhatsAppLogo): never redrawn, recoloured through
    CSS or fill, re-pathed or merged into the icon sprite. Two variants, as Meta supplies them:
      .brand-mark__light  resources/brand/whatsapp/whatsapp-logo-light.svg (or .png / .webp): on light surfaces
      .brand-mark__dark   resources/brand/whatsapp/whatsapp-logo-dark.svg  (or .png / .webp): on dark surfaces
    CSS shows the one that fits the surface it stands on (components/icon.css: .on-dark / .on-night and the
    night button are dark; the page, .on-light and the white buttons are light): the innermost surface wins.

    PLACEHOLDER (until the owner drops Meta's files into resources/brand/whatsapp/): an obviously temporary,
    neutral dashed ring of the same size, marked data-placeholder="whatsapp-logo", in grey (never green, never
    gold). It is NOT a WhatsApp logo and must not ship to production: see docs/d2.1/phase-1/components.md §4.
    A variant whose file is missing stays the placeholder on its surfaces; the other one switches on its own.

    Every mark carries data-brand="whatsapp" (the design lint's rule 14 checks it on every wa.me link) and
    data-icon="whatsapp". Decorative by default (aria-hidden: the control around it carries the name); with
    label="…" it is an image with that accessible name.
--}}
@php
    $px = is_numeric($size) && $size > 0 ? $size + 0 : 20;
    $files = ['light' => \App\Design\D2\WhatsAppLogo::variant('light'), 'dark' => \App\Design\D2\WhatsAppLogo::variant('dark')];
    // Both missing: one placeholder that takes its grey from the surface. Otherwise one slot per surface.
    $slots = array_filter($files) === [] ? ['any' => null] : $files;
    $a11y = filled($label) ? ['role' => 'img', 'aria-label' => $label] : ['aria-hidden' => 'true'];
@endphp
<span {{ $attributes->merge(['data-brand' => 'whatsapp', 'data-icon' => 'whatsapp'] + $a11y)->class(['brand-mark', 'brand-mark--whatsapp']) }}>
    @foreach ($slots as $surface => $file)
        @if ($file)
            <img class="brand-mark__img brand-mark__{{ $surface }}" src="{{ $file['src'] }}" width="{{ $px }}" height="{{ $px }}" alt="" decoding="async" draggable="false">
        @else
            {{-- TEMPORARY placeholder, not a logo: Meta's WhatsApp file for this surface is not in resources/brand/whatsapp/ yet. --}}
            <svg class="brand-mark__placeholder brand-mark__{{ $surface }}" width="{{ $px }}" height="{{ $px }}" viewBox="0 0 24 24" fill="none" focusable="false" data-placeholder="whatsapp-logo"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.5 2.5" vector-effect="non-scaling-stroke"/></svg>
        @endif
    @endforeach
</span>
