@props(['code'])
{{--
    <x-d2.ui.flag code="ar" />: the flag before a language's name in the language controls (the client's final changes,
    item 18, and the owner's decision of 2026-11-25): English = United Kingdom, Arabic = United Arab Emirates, Russian =
    Russia. The 4:3 SVGs of the flag-icons package (MIT, public/images/d2/flags/LICENSE.txt), copied unchanged and served
    from this site (no CDN, no emoji). Decorative: the language's name always stands next to it and names the control,
    so alt="" and aria-hidden. Styles: .flag in components/langmenu.css.
--}}
@php
    $file = ['en' => 'gb', 'ar' => 'ae', 'ru' => 'ru'][$code] ?? null;
@endphp
@if ($file)
<img {{ $attributes->class(['flag']) }} src="{{ asset("images/d2/flags/{$file}.svg") }}" alt="" aria-hidden="true" width="20" height="15" decoding="async" data-flag="{{ $file }}">
@endif
