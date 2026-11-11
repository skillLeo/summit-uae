@props(['name', 'duotone' => false, 'size' => 20, 'label' => null])
{{--
    <x-d2.ui.icon name="search" /> : a glyph of resources/svg/d2/icons/{name}.svg (24 grid, 1.5 pen), 20 px.
    Design 2.1 (brief §3.5, §3.6 rule 5; docs/d2.1/phase-1/components.md §3): one outline set, functional only.
    size     rendered px (width/height), default 20; the pen stays 1.5 px at every size (components/icon.css).
    label    accessible name for a meaningful standalone icon (role="img"); without it the icon is decorative
             (aria-hidden) and the control around it carries the name.
    duotone  retired in 2.1 (outline icons only): accepted and ignored, so old call sites keep rendering.
    Every icon prints data-icon="{name}" for the design lint (rule 13). App\Design\D2\Icons::ALLOWED lists the
    functional names; the explicit exceptions carousel-prev / carousel-next and form-back / form-next draw the
    chevron and arrow glyphs under their own data-icon (Icons::ALIASES).
    name="whatsapp" is not a glyph: it renders <x-d2.ui.brand-mark> (Meta's official logo files, unchanged, or
    the marked placeholder until they are in resources/brand/whatsapp/), so every WhatsApp control switches at once.
    Directional icons (arrow, chevron, external) mirror in RTL; phone, check and clock never do.
    Markup: <svg class="icon" …><use href="#d2i-{glyph}"/></svg>. Each glyph is printed once per page, as a
    <symbol> of the hidden sprite after <body> (App\Design\D2\Icons::use() records it, IconSprite prints it),
    so a <template> or script may clone the icon anywhere in the document. The symbol draws in currentColor;
    the pen width (--icon-stroke) reaches it by inheritance; nothing can style the shapes inside the <use>.
--}}
@php
    $px = is_numeric($size) && $size > 0 ? $size + 0 : 20;
    $brand = \App\Design\D2\Icons::isBrand($name);
@endphp
@if ($brand)
<x-d2.ui.brand-mark :name="$name" :size="$px" :label="$label" {{ $attributes }} />
@else
@php
    $symbol = \App\Design\D2\Icons::use($name);
    $mirrored = \App\Design\D2\Icons::mirrors($name);
@endphp
<svg {{ $attributes->class(['icon', 'mirror-rtl' => $mirrored]) }} width="{{ $px }}" height="{{ $px }}" fill="none" data-icon="{{ $name }}" @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif><use href="#{{ $symbol }}"/></svg>
@endif
