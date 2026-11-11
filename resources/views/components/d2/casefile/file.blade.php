@props([
    'case',                 // content()->caseStudy() record (CaseStudiesMapper): title, matter, body{narrative, sections, outcome, takeaway, related, back_link}
])
{{--
    <x-d2.casefile.file :case="$case" />
    One full case story of the Case Studies page (P04), a section of the page (no route of its own), upgrade
    spec §7.4 "Case study page: file timeline, qualification block, related service", §12 Phase D "Case
    page: full file timeline + qualification block". Story layout as the designer note orders it: title
    (H2, id = the registered anchor case-0N, focus target of every link to it), subject line ("Matter: …"),
    opening paragraphs, the substantive H3 sections, the outcome with its limits (normal visible HTML, a
    marked block, never a badge or tooltip), the practical takeaway; then the related service and reading
    (H3), the consultation CTA (the wizard gets the case's service, Prefill::url) and the way back to the
    directory (#cases-top).
    The file: the story's service as a plain category line (the shorter approved name, as on its directory card),
    the title and the matter line. Story text: the inner kit prose.
    Phase 2 (brief §5.3): no qualification callout per story. It repeated sentences 2-4 of the P04 introduction
    paragraph six times, a shortened copy of approved text; the page head states that paragraph once, complete.
    Each story's own approved outcome section states its limits.
    Design 2.1 Phase 2 (D46, brief §3.6 rules 4 and 10): no folder tab with a case numeral, no service chip, and no
    "How We Work" stage timeline (the same four stages, all reached, repeated on every story: they described the
    firm's method, not the matter). Calm editorial design (designer note): no trophies, no success colours, no
    counters.
    Phones (final pass, home-mobile; the P03 guide pattern): below 768 px with JavaScript (html.js, set before
    the first paint) each story starts as its file card: the service line, title, matter and the approved card
    summary ($case['card_summary'], the directory card's paragraph), with the proposed
    "Read the full story" disclosure (aria-expanded / aria-controls #case-0N-body). The story (#case-0N-body)
    shows once it is open (.is-open: the disclosure, a directory card, any link to the
    story or into it, the page's inline script for an address with a #fragment; d2Casefiles). Without
    JavaScript, in print and from 768 px the whole story shows and the summary and the disclosure do not
    (pages/cases.css).
    Design 2.1 (Phase 1, home-b): the service line carries no decorative icon, the related links are text without
    icons or arrows, and the buttons have no arrows (D03, D04). The disclosure keeps its chevron (a functional icon).
    Round 1 (home-b, brief 3.5): the aside is the same in every story: the related heading, the approved related
    links in their DOCX order (service card, guide link; story 02's approved first link "Consultation on a sale
    contract or claim" to P07 is a related link like the others, in its own place), then the one Ink "Request a
    Legal Consultation" (prefilled with the story's service) and the way back. Story 02 used to promote its
    related link to that Ink button above the service card, so its CTA sat in another slot.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Prefill;
    use App\Support\SiteUrl;

    $code = (string) ($case['code'] ?? '');
    $b = (array) ($case['body'] ?? []);
    $title = (string) (($case['title'] ?? null) ?: ($case['card_title'] ?? ''));
    $matter = trim((string) ($case['matter'] ?? ''));
    $strings = fn ($v) => array_values(array_filter((array) $v, fn ($p) => is_string($p) && trim($p) !== ''));

    // Related links in the approved order; the first service is the file's service (chip, CTA prefill).
    $service = null;
    $related = [];
    foreach ((array) ($b['related']['links'] ?? []) as $link) {
        $target = (string) ($link['target'] ?? '');
        $label = trim((string) ($link['label'] ?? ''));
        if ($label === '' || $target === '') {
            continue;
        }
        if (preg_match('/^S(0[1-9]|1[01])$/', $target) === 1) {
            $service ??= $target;
            $related[] = ['kind' => 'service', 'code' => $target, 'label' => $label];
        } else {
            // A guide, or (story 02, P04-L011) the approved "Consultation on a sale contract or claim" to P07: a
            // related link in its approved place (SiteUrl::target keeps the P07 release rule).
            $related[] = ['kind' => 'link', 'label' => $label, 'href' => SiteUrl::target($target)];
        }
    }
    $serviceTitle = $service ? trim((string) (content()->service($service)['title'] ?? '')) : '';
    // The category line shows the shorter approved name of the service, as on the story's directory card
    // (<x-d2.case.card>); the related service card below keeps the full title.
    $shortKey = 'form.matter_type.option.'.$service;
    $shortTitle = $service && content()->hasUi($shortKey) ? trim(ui($shortKey)) : '';
    $chipTitle = $shortTitle !== '' && ($serviceTitle === '' || mb_strlen($shortTitle) < mb_strlen($serviceTitle)) ? $shortTitle : $serviceTitle;
    $consultUrl = Prefill::url(['service' => $service]);
    $back = (array) ($b['back_link'] ?? []);

    // Story sections (inner kit prose, H3): substantive sections, the outcome, the takeaway (a note).
    $story = [];
    foreach ((array) ($b['sections'] ?? []) as $i => $section) {
        if (filled($section['heading'] ?? null)) {
            $story[] = ['heading' => (string) $section['heading'], 'id' => $code.'-part-'.($i + 1), 'paragraphs' => $strings($section['paragraphs'] ?? [])];
        }
    }
    if (filled($b['outcome']['heading'] ?? null)) {
        $story[] = ['heading' => (string) $b['outcome']['heading'], 'id' => $code.'-outcome', 'paragraphs' => $strings($b['outcome']['paragraphs'] ?? [])];
    }
    if (filled($b['takeaway']['heading'] ?? null)) {
        $story[] = ['heading' => (string) $b['takeaway']['heading'], 'id' => $code.'-takeaway', 'variant' => 'note', 'tone' => 'info', 'paragraphs' => $strings($b['takeaway']['paragraphs'] ?? [])];
    }
    $relatedHeading = trim((string) ($b['related']['heading'] ?? ''));
    $summary = trim((string) ($case['card_summary'] ?? ''));
@endphp
<article {{ $attributes->class(['casefile']) }} data-case-file="{{ $code }}" aria-labelledby="{{ $code }}">
    <header class="casefile__head">
        <h2 class="casefile__title" id="{{ $code }}" tabindex="-1">{{ Bidi::isolate($title) }}</h2>
        @if ($chipTitle !== '')
            <p class="casefile__service">{{ Bidi::isolate($chipTitle) }}</p>
        @endif
        @if ($matter !== '')
            <p class="casefile__matter">{{ Bidi::isolate($matter) }}</p>
        @endif
    </header>

    {{-- Phones with JavaScript only (pages/cases.css): the approved card summary and the disclosure, described
         by the story's title so each of the six reads apart from the others. --}}
    @if ($summary !== '')
        <p class="casefile__summary">{{ Bidi::isolate($summary) }}</p>
    @endif
    <x-d2.ui.button variant="secondary" size="48" icon-end="chevron-down" :full="true" class="casefile__toggle"
        aria-expanded="false" aria-controls="{{ $code }}-body" aria-describedby="{{ $code }}" data-cf-toggle>
        <span class="casefile__toggle-open" @proposed>{{ d2p('cases_page.story_open') }}</span>
        <span class="casefile__toggle-close" @proposed>{{ d2p('cases_page.story_close') }}</span>
    </x-d2.ui.button>

    <div class="casefile__body" id="{{ $code }}-body" data-cf-body>
        <div class="casefile__story">
            @if ($strings($b['narrative'] ?? []) !== [])
                <x-d2.inner.prose :blocks="$strings($b['narrative'])" size="lg" class="casefile__narrative" />
            @endif
            @if ($story !== [])
                <x-d2.inner.prose :sections="$story" :level="3" :id-prefix="$code" class="casefile__sections" />
            @endif
        </div>

        <aside class="casefile__aside" @if ($relatedHeading !== '') aria-labelledby="{{ $code }}-related" @endif>
            @if ($relatedHeading !== '')
                <h3 class="casefile__aside-title" id="{{ $code }}-related">{{ Bidi::isolate($relatedHeading) }}</h3>
            @endif
            @if ($related !== [])
                <ul class="casefile__related" role="list">
                    @foreach ($related as $item)
                        <li class="casefile__related-item">
                            @if ($item['kind'] === 'service')
                                <x-d2.resources.service-card :code="$item['code']" :label="$item['label']" />
                            @else
                                <a class="kc-link" href="{{ $item['href'] }}">
                                    <span class="kc-link__label">{{ Bidi::isolate($item['label']) }}</span>
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            {{-- Every story ends alike: the Ink consultation CTA (the wizard gets the story's service), then the way back. --}}
            <div class="casefile__actions">
                <x-d2.ui.button variant="ink" :href="$consultUrl" :full="true" class="casefile__cta">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
                @if (filled($back['label'] ?? null))
                    <x-d2.ui.button variant="text" href="#cases-top" class="casefile__back">{{ Bidi::isolate((string) $back['label']) }}</x-d2.ui.button>
                @endif
            </div>
        </aside>
    </div>
</article>
