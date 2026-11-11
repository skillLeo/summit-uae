@props([
    'sections' => [],          // the prose sections (heading | label | title, optional id) or [['id', 'label'], …]
    'idPrefix' => 'section',   // = the prose id-prefix (ids of sections that carry no id of their own)
    'title' => null,           // heading of the list, the bar and the sheet (default: proposed "On this page")
    'mode' => 'both',          // nav (desktop list) | bar (mobile/tablet bar + sheet) | both (no side column)
    'sheetId' => 'toc',        // id of the TOC sheet (data-sheet-open target; one per page)
    'static' => false,         // styleguide specimen: no scroll-spy hooks, no sheet; `current` marks one link
    'current' => null,         // static only: id of the link drawn as current
])
{{--
    Design 2 table of contents (upgrade spec §7.4 guide and legal pages, §8 sheets, §12 Phase D).
    <x-d2.inner.layout :toc="$sections"> places both parts for you; use this component directly only for
    a TOC outside that layout.

    nav   ≥ 1280 px: "On this page" + ordered list of section links, sticky in the side column (it scrolls
          inside itself when taller than the window: a fade on the clipped edge and a "more" cue at the
          bottom, .toc__frame). modules/toc.js runs the scroll-spy: the link of the section being read gets
          aria-current="true" (a 2 px ink bar on the inline-start edge); the list keeps that link and the one
          before it in view and returns to its top while nothing or the first section is current.
    bar   < 1280 px: a sticky white bar under the header: a button "On this page" with a chevron that opens
          the TOC sheet (Phase A <x-d2.ui.sheet>, pushed to the 'sheets' stack; a bottom sheet below 768 px, a
          side panel from 768 px), where the section being read is marked (aria-current). Design 2.1: the bar
          no longer repeats the current section's heading after a middle dot (brief §3.6 rules 2 and 8: the
          dot, and an approved heading cut to one line with an ellipsis). A link in the sheet closes it,
          scrolls to the section and moves focus there (lib/sheet.js). Without JavaScript the bar is the plain
          list of links. With JavaScript the bar is laid out from the first paint (inner.css keys it on html.js,
          which the boot script sets before the first paint), so nothing below it moves when modules/toc.js
          mounts (§4b integration rule).
    Ids: section index in `sections` → {idPrefix}-01, -02 … unless the section has its own 'id' (the same
    rule as the prose). Sections without a heading are skipped; a section with 'proposed' => true has a
    proposed label (its links are marked @proposed). :static="true" (styleguide) draws the nav list only,
    without the scroll-spy hooks; `current` marks one link as aria-current.
--}}
@php
    use App\Design\D2\Bidi;

    $mode = in_array($mode, ['nav', 'bar', 'both'], true) ? $mode : 'both';
    $title = is_string($title) && trim($title) !== '' ? $title : d2p('inner.toc_title');
    $titleProposed = $title === d2p('inner.toc_title');
    $items = [];
    foreach (array_values((array) $sections) as $i => $section) {
        if (! is_array($section)) {
            continue;
        }
        $label = $section['label'] ?? $section['heading'] ?? $section['title'] ?? null;
        if (! is_string($label) || trim($label) === '') {
            continue;
        }
        $items[] = [
            'id' => is_string($section['id'] ?? null) && $section['id'] !== '' ? $section['id'] : sprintf('%s-%02d', $idPrefix, $i + 1),
            'label' => $label,
            'proposed' => ($section['proposed'] ?? false) === true,
        ];
    }
    $navTitleId = $sheetId.'-nav-title';
    $inlineTitleId = $sheetId.'-inline-title';
@endphp
@if ($items !== [])
    @if ($mode !== 'bar')
        <nav {{ $attributes->class(['toc', 'toc--nav', 'toc--both' => $mode === 'both', 'toc--static' => $static]) }} aria-labelledby="{{ $navTitleId }}" @unless ($static) data-toc @endunless>
            <h2 class="toc__title" id="{{ $navTitleId }}" @if ($titleProposed) @proposed @endif>{{ $title }}</h2>
            {{-- The frame holds the scrolling list and its "more" cue (inner.css .toc__frame; modules/toc.js marks the
                 clipped edges). The cue is a pointer convenience only: keyboard focus scrolls the list by itself. --}}
            <div class="toc__frame" @unless ($static) data-toc-frame @endunless>
                <ol class="toc__list" @unless ($static) data-toc-scroller @endunless>
                    @foreach ($items as $item)
                        <li class="toc__item"><a class="toc__link" href="#{{ $item['id'] }}" @if ($static && $current === $item['id']) aria-current="true" @endif @if ($item['proposed']) @proposed @endif>{{ Bidi::isolate($item['label']) }}</a></li>
                    @endforeach
                </ol>
                @unless ($static)
                    <span class="toc__more" aria-hidden="true" data-toc-more><x-d2.ui.icon name="chevron-down" size="20" /></span>
                @endunless
            </div>
        </nav>
    @endif

    @if ($mode !== 'nav' && ! $static)
        @if ($mode === 'bar')
        <div {{ $attributes->class(['toc-bar']) }} data-toc-bar>
        @else
        <div class="toc-bar toc-bar--both" data-toc-bar>
        @endif
            {{-- Drawn under html.js from the first paint (inner.css), never without JavaScript. No `hidden` attribute:
                 the reset's [hidden] rule (!important) could not be lifted before toc.js mounts. --}}
            <button type="button" class="toc-bar__button" data-sheet-open="{{ $sheetId }}">
                <span class="toc-bar__title" @if ($titleProposed) @proposed @endif>{{ $title }}</span>
                <x-d2.ui.icon name="chevron-down" size="20" class="toc-bar__chevron" />
            </button>
            {{-- Without JavaScript the bar is the plain list (hidden under html.js from the first paint). --}}
            <nav class="toc toc--inline" aria-labelledby="{{ $inlineTitleId }}" data-toc-inline>
                <h2 class="toc__title" id="{{ $inlineTitleId }}" @if ($titleProposed) @proposed @endif>{{ $title }}</h2>
                <ol class="toc__list">
                    @foreach ($items as $item)
                        <li class="toc__item"><a class="toc__link" href="#{{ $item['id'] }}" @if ($item['proposed']) @proposed @endif>{{ Bidi::isolate($item['label']) }}</a></li>
                    @endforeach
                </ol>
            </nav>
        </div>

        @pushOnce('sheets', 'd2-inner-toc-'.$sheetId)
            <x-d2.ui.sheet :id="$sheetId" :title="$title" :title-proposed="$titleProposed" class="toc-sheet">
                <nav class="toc toc--sheet" aria-labelledby="sheet-{{ $sheetId }}-title" data-toc>
                    <ol class="toc__list">
                        @foreach ($items as $item)
                            <li class="toc__item"><a class="toc__link" href="#{{ $item['id'] }}" @if ($item['proposed']) @proposed @endif>{{ Bidi::isolate($item['label']) }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            </x-d2.ui.sheet>
        @endPushOnce
    @endif
@endif
