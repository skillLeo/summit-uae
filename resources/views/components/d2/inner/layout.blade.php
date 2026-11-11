@props([
    'as' => 'div',             // main column element: div | article | section
    'toc' => null,             // sections for the table of contents (the same array as the prose); null: none
    'tocPrefix' => 'section',  // = the prose id-prefix
    'tocTitle' => null,        // TOC heading (default: proposed "On this page")
    'sticky' => true,          // the side column sticks under the header (≥ 1280 px)
    'mainId' => null,          // id of the main column (e.g. the reading-progress target)
])
{{--
    Design 2 reading layout (upgrade spec §7.4, §12 Phase D): a 720 px reading column with an optional side
    column on the inline-end side.

    <x-d2.inner.layout as="article" :toc="$sections" main-id="legal-text">
        <x-d2.inner.prose :sections="$sections" />
        <x-slot:aside><x-d2.inner.cta-card … /></x-slot:aside>
    </x-d2.inner.layout>

    ≥ 1280 px: two columns (main ≤ 720 px, side column 352 px, 96 px apart, centred in the container). With
    `toc` the side column starts with the sticky TOC; the aside slot follows it (both stick together).
    < 1280 px: one column; with `toc` a sticky "On this page" bar sits under the header (it opens the TOC
    sheet; without JavaScript it is the plain list), and the aside slot follows the main column.
    Source order: with `toc` the side column comes first (the grid places it on the inline-end side), so the
    keyboard and assistive technology reach "On this page" before the text it lists, not after every link of
    the reading column (QA round 1: on S01 the contents were Tab stops 46–56). Below 1280 px that side column
    is not drawn (the bar replaces the list; the service pages' card is the final panel's). Without `toc` the
    main column stays first.
--}}
@php
    $tag = in_array($as, ['div', 'article', 'section'], true) ? $as : 'div';
    $hasToc = is_array($toc) && $toc !== [];
    $hasAside = isset($aside) && trim((string) $aside) !== '';
@endphp
<div {{ $attributes->class([
        'inner-layout',
        'inner-layout--side' => $hasToc || $hasAside,
        'inner-layout--toc' => $hasToc,
        'inner-layout--sticky' => $sticky,
    ]) }}>
    <div class="container inner-layout__grid">
        @if ($hasToc)
            <div class="inner-layout__side">
                <div class="inner-layout__rail">
                    <x-d2.inner.toc :sections="$toc" :id-prefix="$tocPrefix" :title="$tocTitle" mode="nav" />
                    @if ($hasAside)
                        <div class="inner-layout__aside">{{ $aside }}</div>
                    @endif
                </div>
            </div>
        @endif

        <{{ $tag }} class="inner-layout__main" @if ($mainId) id="{{ $mainId }}" @endif>
            @if ($hasToc)
                <x-d2.inner.toc :sections="$toc" :id-prefix="$tocPrefix" :title="$tocTitle" mode="bar" />
            @endif
            {{ $slot }}
        </{{ $tag }}>

        @if (! $hasToc && $hasAside)
            <div class="inner-layout__side">
                <div class="inner-layout__rail">
                    <div class="inner-layout__aside">{{ $aside }}</div>
                </div>
            </div>
        @endif
    </div>
</div>
