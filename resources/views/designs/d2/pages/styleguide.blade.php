{{--
    Design 2 styleguide (replaces Design 1's pages.styleguide by name; SiteController@styleguide, noindex).
    Every component in every state, in the page language (EN / AR / RU). One partial per area in
    d2::styleguide.<area>, each a <section class="sg-section" id="sg-<area>">; the frame classes
    (.sg-section, .sg-sub, .sg-grid, .sg-row, .sg-specimen, .sg-label, .sg-night, .sg-code) live in
    resources/css/d2/styleguide.css, the second Vite entry, loaded on this page only.
    Annotations are internal (lang/en/styleguide_d2/<area>.php): Arabic and Russian fall back to English.
--}}
@extends('d2::layouts.app', ['d2Styles' => ['resources/css/d2/styleguide.css']])

@php
    $t = fn (string $key) => __('styleguide_d2/page.'.$key);
    $areas = ['foundations', 'type', 'icons', 'controls', 'surfaces', 'shell', 'mobile', 'documents', 'hero', 'sections', 'navigator', 'tools', 'cards', 'search', 'wizard', 'upload', 'inner'];
    // Only areas whose partial exists are listed, so the index never links to a missing anchor.
    $present = array_values(array_filter($areas, fn (string $area) => view()->exists('d2::styleguide.'.$area)));
    $labels = (array) $t('areas');
    $en = app()->isLocale('en') ? '' : 'lang="en"';
@endphp

@section('content')
    <div class="sg container">
        {{-- No eyebrow above the title (brief §3.6 rules 1 and 2, D01 and D02): the H1 opens the page. --}}
        <header class="sg-head">
            <h1 class="sg-head__title" {!! $en !!}>{{ $t('title') }}</h1>
            <p class="sg-head__intro" {!! $en !!}>{{ $t('intro') }}</p>
            @if ($en)
                <p class="sg-label" lang="en">{{ $t('locale_note') }}</p>
            @endif
            @if ($present)
                <nav class="sg-index" aria-label="{{ $t('index_label') }}" {!! $en !!}>
                    <ol class="sg-index__list" role="list">
                        @foreach ($present as $area)
                            <li><a class="sg-index__link" href="#sg-{{ $area }}">{{ $labels[$area] ?? $area }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            @endif
        </header>

        @foreach ($areas as $area)
            @includeIf('d2::styleguide.'.$area)
        @endforeach
    </div>
@endsection
