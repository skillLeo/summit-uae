{{--
    Design 2 legal pages (upgrade spec §7.4 "Legal Notice / Privacy / Terms: a readable 720 px column with
    TOC", §12 Phase D): P08 Legal Notice and Scope of Services, L01 Privacy Policy, L02 Terms and Conditions.
    PagePreviewController data: $h1, $intro, $crumbs, $pageCode; the full approved text comes from
    content()->page($pageCode) (LegalPagesMapper: last_updated, content{intro[], sections[{heading, blocks}],
    links[{label, target}]}).

    - One <article>: head (breadcrumbs, H1, the approved "Last updated" line, the approved introduction),
      then the sections in the 720 px column with the sticky TOC (desktop) / the TOC bar + sheet (mobile),
      and the 3 px reading-progress bar under the header.
    - The approved H2 numbering ("1. …", "١. …") is printed verbatim, without list counters.
    - The page's approved contact links (e-mail, telephone, "Contact Summit" → P07, which follows the
      contact release rule and lands on the home form) follow the last section, as in the DOCX; the
      cross-links to the other two legal pages close the article. Same tab, as the link notes require.
    - Print: only the article prints (legal.css @media print); the print button appears only with JavaScript.
      Its row is reserved under html.js from the first paint (legal.css), and the contents bar is laid out
      from the first paint too (pages/inner.css), so the first screen never moves when the scripts mount.
    - Design 2.1: no calendar, print or object glyphs (brief §3.6 rule 5: only the e-mail and telephone rows
      keep their functional glyph). Arabic digits (the H2 numbers "١." …, the "Last updated" date) follow the
      site setting at render time (App\Design\D2\Digits, brief §3.7, D11).
--}}
@php
    $page = content()->page($pageCode);
    $content = is_array($page['content'] ?? null) ? $page['content'] : [];
    $sections = array_values(array_filter((array) ($content['sections'] ?? []), 'is_array'));
    $introParagraphs = array_values(array_filter((array) ($content['intro'] ?? $intro), fn ($p) => is_string($p) && trim($p) !== ''));
    $lastUpdated = is_string($page['last_updated'] ?? null) && trim($page['last_updated']) !== '' ? $page['last_updated'] : null;

    $links = array_values(array_filter((array) ($content['links'] ?? []), fn ($l) => is_array($l) && ! empty($l['label']) && ! empty($l['target'])));
    $legalCodes = ['P08', 'L01', 'L02'];
    $contactLinks = [];
    $crossLinks = [];
    foreach ($links as $link) {
        if (in_array($link['target'], $legalCodes, true)) {
            $crossLinks[] = $link;
        } else {
            $contactLinks[] = $link;
        }
    }
    // The contact links belong to the last section (its text asks the reader to get in touch).
    if ($contactLinks !== [] && $sections !== []) {
        $last = array_key_last($sections);
        $sections[$last]['blocks'] = array_merge((array) ($sections[$last]['blocks'] ?? []), [['type' => 'links', 'items' => $contactLinks]]);
    }
@endphp
<x-d2.inner.progress target="legal-text" />

<article class="legal" aria-labelledby="page-title" data-legal="{{ $pageCode }}">
    <x-d2.inner.head :title="$h1" :crumbs="$crumbs" :intro="$introParagraphs" size="document" class="legal__head">
        <x-slot:meta>
            @if ($lastUpdated)
                <p class="legal__updated">{{ $lastUpdated }}</p>
            @endif
            {{-- Print needs JavaScript: without it the wrapper is not drawn; under html.js its row is reserved from the
                 first paint (visibility: hidden) and Alpine (d2InnerPrint) reveals the button with .is-ready, so the
                 head keeps its height when the script mounts (legal.css). --}}
            <div class="legal__tools" x-data="d2InnerPrint" x-bind:class="readyClass">
                <x-d2.ui.button variant="text" size="44" class="legal__print" x-on:click="print"><span @proposed>{{ d2p('inner.print') }}</span></x-d2.ui.button>
            </div>
        </x-slot:meta>
    </x-d2.inner.head>

    <x-d2.inner.layout :toc="$sections" toc-prefix="section" main-id="legal-text" class="legal__layout">
        <x-d2.inner.prose :sections="$sections" id-prefix="section" class="legal__prose" />

        <x-d2.inner.related :items="$crossLinks" class="legal__related" id="legal-related" />
    </x-d2.inner.layout>
</article>
