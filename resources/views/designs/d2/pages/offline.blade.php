{{--
    Design 2 offline page (owner: system-pages), /xx/offline/ (SiteController@offline: $seo noindex without
    alternates, $pageKey 'offline'). Replaces Design 1's pages.offline by name. A service worker shows it in
    place of a page that cannot load, so everything here is server-rendered and works without JavaScript:
      - what still works without a connection (proposed d2p('offline.*'));
      - the contact details: TEAM 1's <x-d2.contact.card> (approved phone, WhatsApp with the approved draft,
        e-mail, office hours, languages; phone and e-mail in <bdi dir="ltr">; no address, no map);
      - "Try again": a plain link to "./", i.e. the address in the location bar (every site URL ends with a
        slash), so it reloads the page the visitor asked for once the connection is back, with or without JS.
    Heading order: h1, then the h2 "What you can still do" that holds the contact card (its title is an h3)
    and the three tips. Styles: resources/css/d2/pages/offline.css.
    Design 2.1 (brief §3.6 rules 3 and 5): the tips are text (no glyph tiles) and "Try again" carries no
    refresh glyph.
--}}
@extends('d2::layouts.app')

@php
    $tips = [
        ['title' => 'offline.call_title', 'text' => 'offline.call_text'],
        ['title' => 'offline.email_title', 'text' => 'offline.email_text'],
        ['title' => 'offline.docs_title', 'text' => 'offline.docs_text'],
    ];
@endphp

{{--
    Offline fallback. The site's service worker (Design 1's frozen /sw.js, also registered by Design 2's
    modules/pwa.js) precaches this page but caches only Design 1's /build/assets and the /fonts/, so offline
    this page may arrive without the Design 2 stylesheet. These few rules (no colours, no tokens: none are
    loaded then) keep it readable: the official emblem and wordmark at a sane size, the shell's menus, footer
    and tab bar hidden, one readable column. They apply only when the Design 2 tokens are missing
    (@container not style(--d2-tokens: 1); tokens.css defines the probe), so the styled page is never touched.
    Browsers without style queries simply skip them.
--}}
@push('head')
    <style nonce="{{ Vite::cspNonce() }}">@container not style(--d2-tokens: 1){body{max-inline-size:44rem;margin-inline:auto;padding:1rem 1.5rem 3rem;font-family:"D2 Sans","D2 Sans Arabic",system-ui,sans-serif;line-height:1.6}svg:not([width]){inline-size:2.5rem;block-size:auto}.d2-nav,.d2-header__tools,.d2-footer,.tabbar,.toast-region,[data-sheet]{display:none}.skip-link,.sr-only{position:absolute;inline-size:1px;block-size:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}main ul{padding-inline-start:0;list-style:none}main li{margin-block:.75rem}a{color:inherit}}</style>
@endpush

@section('content')
    <section class="section off" aria-labelledby="off-title">
        <div class="container off__grid">
            <h1 class="off__title" id="off-title" @proposed>{{ proposed('offline.title') }}</h1>
            <p class="t-lead off__lead" @proposed>{{ d2p('offline.lead') }}</p>

            <div class="off__actions">
                <x-d2.ui.button variant="ink" href="./" size="48"><span @proposed>{{ d2p('offline.retry') }}</span></x-d2.ui.button>
            </div>

            <h2 class="off__list-title" id="off-list-title" @proposed>{{ d2p('offline.list_title') }}</h2>

            <x-d2.contact.card class="off__card" id-prefix="off-contact" />

            <ul class="off-tips" role="list" aria-labelledby="off-list-title">
                @foreach ($tips as $tip)
                    <li class="off-tip">
                        <strong class="off-tip__title" @proposed>{{ d2p($tip['title']) }}</strong>
                        <span class="off-tip__body" @proposed>{{ d2p($tip['text']) }}</span>
                    </li>
                @endforeach
            </ul>

            <p class="t-small off__later" @proposed>{{ d2p('offline.later') }}</p>
        </div>
    </section>
@endsection
