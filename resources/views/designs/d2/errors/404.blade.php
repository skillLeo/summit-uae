{{--
    Design 2 404 (owner: system-pages; upgrade spec §7.4 and §12 Phase D: "404 (misfiled sample document +
    search)"). Replaces Design 1's errors.404 by name: bootstrap/app.php renders "errors.404" with status 404,
    $locale and $dir from the URL's first segment (default English), for unmatched URLs and abort(404) alike.

    Content: the approved U01 record only (code, H1, message, the two actions, "Looking for specific
    information?" and its three links). The missing URL and its query are never echoed. noindex; no canonical
    and no alternates (Seo::make(..., index: false, alternates: false), as in Design 1).
    Search: a real search field (form role="search", components/inner-search.js d2NotFoundSearch): submitting
    it (Enter, the phone keyboard's search key, or the search button at its start) dispatches the d2:palette-open
    protocol (docs/d2/engineering.md §4a) with detail.query = the typed words, so the command palette opens
    with them in its field; unclaimed, it goes to the Knowledge Centre (the form's action, without the words).
    Without JavaScript it is replaced by the list of the main pages (the approved navigation labels).
    Illustration: a dark folder with the official emblem and the sample contract (<x-d2.doc.contract>, with its
    watermark) sticking out of it, slightly rotated: "misfiled". role="img" with a proposed name; the drawing
    itself is aria-hidden; data-illustration marks it for the Phase 2 lint (brief §7.1).
    Design 2.1 (brief §3.5, §3.6 rules 3 and 5): no home or arrow glyphs on the buttons; the search field is the
    site's 48 px search field with the magnifier at its start; the related pages are the kit's link rows (no
    object tiles, no arrows); the approved "404" is a plain label, not an eyebrow.
    Shell: $pageKey 'error' sends every language link of the header, the menu sheet and the footer to that
    language's home page (App\Design\D2\Shell::languageLinks; the layout passes the page key).
    Styles: resources/css/d2/pages/error.css.
--}}
@php
    use App\Support\Seo;
    use App\Support\SiteUrl;

    $u01 = content()->page('U01');
    // U01 keeps its fields at the top level of the record (NotFoundMapper), not under "content".
    $c = is_array($u01['content'] ?? null) && $u01['content'] !== [] ? $u01['content'] : $u01;
    $seo = Seo::make((string) ($u01['seo_title'] ?? ($u01['h1'] ?? ui('brand.name'))), null, index: false, alternates: false);
    $pageKey = 'error';
    $navCurrent = null;

    $code = (string) ($c['code_label'] ?? '404');
    $actions = is_array($c['actions'] ?? null) ? $c['actions'] : [];
    $links = array_values(array_filter(
        is_array($c['links'] ?? null) ? $c['links'] : [],
        fn ($link) => is_array($link) && filled($link['label'] ?? null) && filled($link['target'] ?? null),
    ));

    // No-JS replacement of the search field: the main pages, with their approved navigation labels.
    $pages = [
        ['label' => ui('nav.practice_areas'), 'href' => SiteUrl::page('P02')],
        ['label' => ui('nav.resources'), 'href' => SiteUrl::page('P03')],
        ['label' => ui('nav.case_studies'), 'href' => SiteUrl::page('P04')],
        ['label' => ui('nav.faq'), 'href' => SiteUrl::page('P05')],
        ['label' => ui('nav.about'), 'href' => SiteUrl::page('P06')],
        ['label' => ui('nav.contact'), 'href' => SiteUrl::contact()],
    ];

    // Official traced emblem (resources/brand/emblem.svg, fill="currentColor"), inlined as the header does.
    $emblem = trim((string) preg_replace('/<\?xml.*?\?>|<!--.*?-->/s', '', (string) @file_get_contents(resource_path('brand/emblem.svg'))));
    $emblem = (string) preg_replace('/<svg\b/', '<svg class="nf-art__emblem" aria-hidden="true" focusable="false"', $emblem, 1);
@endphp
@extends('d2::layouts.app')

@section('content')
    <section class="section nf" aria-labelledby="nf-title">
        <div class="container nf__grid">
            <div class="nf__copy">
                <p class="nf__code"><bdi dir="ltr">{{ $code }}</bdi></p>
                <h1 class="nf__title" id="nf-title">{{ $u01['h1'] ?? '' }}</h1>
                @if (filled($c['text'] ?? null))
                    <p class="t-body-l nf__text">{{ $c['text'] }}</p>
                @endif

                <div class="nf__find">
                    {{-- A real search field (JavaScript only, error.css): Enter, the keyboard's search key or the
                         search button open the command palette with what the visitor typed (d2NotFoundSearch
                         dispatches d2:palette-open with detail.query). Typing alone changes nothing on the page; the
                         words never reach an address (the field has no name). Unclaimed, the form goes to the
                         Knowledge Centre. Without JavaScript the main pages below replace it. The field is the site's
                         one search field (brief §3.5): 48 px, the magnifier at its inline start as on P03 and P05.
                         Here the magnifier is the submit control, a 44 px ghost icon button inside the field's edge
                         (its hit area stays 48 px on touch screens: the button's ::after), first in the reading and
                         focus order as it is on screen; never an arrow. The placeholder is the page's own short
                         proposed hint (errors.search_placeholder), so it shows in full on a 360 px phone in every
                         language (brief §3.6 rule 8). --}}
                    <form class="nf-search" role="search" action="{{ SiteUrl::page('P03') }}" x-data="d2NotFoundSearch" data-nf-search>
                        <x-d2.ui.icon-button icon="search" :label="d2p('errors.search_go')" type="submit" size="44" tip="top-start" class="nf-search__go" :data-copy-status="app()->isProduction() ? null : 'proposed'" />
                        <input class="nf-search__input" id="nf-q" type="search" placeholder="{{ d2p('errors.search_placeholder') }}"
                            aria-label="{{ d2p('palette.input_label') }}" autocomplete="off" enterkeyhint="search" spellcheck="false"
                            data-nf-query @proposed>
                    </form>
                    <nav class="nf-pages" aria-labelledby="nf-pages-title">
                        <p class="t-meta nf-pages__title" id="nf-pages-title" @proposed>{{ d2p('errors.pages_title') }}</p>
                        <ul class="nf-pages__list" role="list">
                            @foreach ($pages as $page)
                                <li><x-d2.ui.chip :href="$page['href']">{{ $page['label'] }}</x-d2.ui.chip></li>
                            @endforeach
                        </ul>
                    </nav>
                </div>

                <div class="nf__actions">
                    @if (filled($actions['home_label'] ?? null))
                        <x-d2.ui.button variant="ink" :href="SiteUrl::home()" size="48">{{ $actions['home_label'] }}</x-d2.ui.button>
                    @endif
                    @if (filled($actions['services_label'] ?? null))
                        <x-d2.ui.button variant="secondary" :href="SiteUrl::page('P02')" size="48">{{ $actions['services_label'] }}</x-d2.ui.button>
                    @endif
                </div>
            </div>

            <div class="nf__art nf-art" role="img" aria-label="{{ d2p('errors.art_alt') }}" data-illustration @proposed>
                <div class="nf-art__stage" aria-hidden="true">
                    <span class="nf-art__back"></span>
                    <span class="nf-art__sheet nf-art__sheet--1"></span>
                    <span class="nf-art__sheet nf-art__sheet--2"></span>
                    <span class="nf-art__front">{!! $emblem !!}</span>
                    <span class="nf-art__doc"><x-d2.doc.contract variant="still" /></span>
                </div>
            </div>

            @if ($links !== [])
                <div class="nf__more">
                    <h2 class="nf__more-title" id="nf-more-title">{{ $c['more_heading'] ?? '' }}</h2>
                    {{-- The kit's link rows (pages/inner.css .link-row): the one look of a link to a related page
                         (P02, P05, P06, P08, S01–S11); white, 1 px --line, UI label, the edge turns ink and the label
                         underlines on hover. --}}
                    <ul class="nf-links" role="list" aria-labelledby="nf-more-title">
                        @foreach ($links as $link)
                            <li>
                                <a class="link-row nf-link" href="{{ target_url((string) $link['target']) }}">
                                    <span class="link-row__label">{{ $link['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
@endsection
