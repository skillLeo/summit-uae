{{--
    Design 2 home section 6: Matter Navigator, the night "workbench" (upgrade spec §12 C5, §7.3 item 7).
    Same logic and data as Design 1 (resources/views/pages/home/navigator.blade.php, ported, never included):
    the 44 approved situations from $navServices, the documents / first steps / mistakes / guides of each
    service, the localStorage key summit.navigator.v1, the deep link #navigator=S05-2 and the no-JavaScript
    GET form (?navigator=S05-2 renders that file on the server: $selected / $selectedService).

    Layout: ≥ 1280 two columns (the 420 px list column, which scrolls with the page | the "Your starting file"
    card, sticky 96 px from the top: Design 2.1 D29); below 1280 the list fills the width (with JavaScript each
    service is a row that opens its situations) and the card opens in the Phase A sheet (bottom sheet < 768, side
    panel 768–1279). The practice-area chips wrap from 768 px and scroll sideways below (D30).
    Without JavaScript the card follows the list and the submit button shows it; once a situation is chosen
    (?navigator=S05-2, from the form or a service page's link to #navigator) the file comes first, right under
    the heading, so a phone lands on it instead of 4,000–5,000 px of list above it (final pass, home-mobile).
    d2Navigator puts the slot back after the form at once (with JavaScript the slot is the second grid column
    from 1280 px and hidden below, so the move changes nothing on screen; the focus order follows the layout).
    Design 2.1 (Phase 1): the dark band stays (white, band or dark only: .section--dark with .on-dark, the light-gold
    focus ring and the kit's dark controls; the Design 2.0 names .section--night / .on-night are aliases); its Request buttons are Ink (the hero's is
    the page's one Gold button, D06); group headings are plain 16/600 text without mono numbers (D01); no
    decorative icons or arrows in the starting file (D03, D04). Phase 2: no scroll box around the list and a sticky
    card (D29), chips that are never cut under a button (D30).
    Behaviour: resources/js/d2/components/navigator.js (Alpine d2Navigator, lazy-loads navigator/controller.js).
--}}
@php
    use App\Design\D2\Prefill;
    use App\Support\SiteUrl;

    $services = $navServices ?? [];
    // HomeController sets $selected only when the situation exists ($selectedService), validated as a string.
    $selectedRef = (($selected ?? null) && ($selectedService ?? null)) ? $selected['service'].'-'.$selected['index'] : null;
    $approvedHeading = config('summit.features.navigator_heading') === 'approved';
    $heading = $approvedHeading ? ($c['review']['questions_heading'] ?? '') : d2p('navigator.heading');

    // Plural lines for the browser, built here with Laravel's own plural rules (en 2, ru 3, ar 6 forms).
    $num = fn (int $n) => "\u{2068}{$n}\u{2069}";
    $progress = [];
    foreach (array_unique(array_map(fn ($s) => count($s['documents'] ?? []), $services)) as $total) {
        foreach (range(0, $total) as $ready) {
            $progress[$total][$ready] = trans_choice('proposed.d2.navigator.progress', $total, ['ready' => $num($ready), 'total' => $num($total)]);
        }
    }
    $situationCount = array_sum(array_map(fn ($s) => count($s['situations'] ?? []), $services));
    $matches = array_map(fn ($n) => trans_choice('proposed.d2.navigator.matches', $n, ['count' => $n]), range(0, max($situationCount, 1)));

    $config = [
        'storageKey' => $navPayload['storageKey'] ?? 'summit.navigator.v1',
        'dateLocale' => config('summit.locales.'.app()->getLocale().'.date', 'en-GB'),
        'request' => Prefill::url(),
        'labels' => [
            'linkCopied' => d2p('navigator.link_copied'),
            'copyFailed' => ui('faq.copy_failed'),
            'noResults' => d2p('navigator.no_results'),
            'updated' => d2p('navigator.updated', ['service' => ':service']),
            'printDate' => d2p('navigator.print_date', ['date' => ':date']),
        ],
        'progress' => $progress,
        'matches' => $matches,
    ];
    $cs = app()->isProduction() ? null : 'proposed';
@endphp
<section class="section section--dark on-dark navigator" id="navigator" aria-labelledby="navigator-title" x-data="d2Navigator" data-navigator>
    <div class="container">
        <header class="section__head navigator__head">
            <h2 id="navigator-title" @unless ($approvedHeading) @proposed @endunless>{{ $heading }}</h2>
            <p @proposed>{{ d2p('navigator.intro') }}</p>
        </header>

        <div @class(['nav-bench', 'nav-bench--chosen' => $selectedRef]) data-nav-bench>
            @if ($selectedRef)
                <div class="nav-slot" data-nav-slot>
                    <x-d2.navigator.card :services="$services" :selected="$selected" />
                </div>
            @endif

            <form class="nav-pick" method="get" action="{{ SiteUrl::home() }}#navigator" data-nav-form novalidate>
                <x-d2.navigator.tools :services="$services" />
                <p class="sr-only" role="status" data-nav-status></p>
                <div class="nav-panel">
                    <x-d2.navigator.list :services="$services" :selected="$selectedRef" />
                </div>
                <x-d2.ui.button type="submit" variant="night" class="nav-submit" data-nav-submit :data-copy-status="$cs" full>{{ d2p('navigator.show_file') }}</x-d2.ui.button>
            </form>

            @unless ($selectedRef)
                <div class="nav-slot" data-nav-slot>
                    <x-d2.navigator.card :services="$services" />
                </div>
            @endunless
        </div>
    </div>
    <script type="application/json" data-navigator-config>{!! json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
</section>

@push('sheets')
    {{-- Below 1280 px the starting file opens here (navigator/controller.js moves the card in). --}}
    <x-d2.ui.sheet id="navigator" :title="d2p('navigator.file_title')" :title-proposed="true" class="nav-sheet">
        <div class="nav-sheet__slot" data-nav-sheet-slot></div>
        <x-slot:footer>
            {{-- The approved CTA on one full-width line (Ink: D06); Print and Copy link stay at the end of the file. --}}
            <div class="nav-sheet__actions" data-nav-sheet-actions>
                <x-d2.ui.button variant="ink" :href="Prefill::url(['service' => $selectedRef ? $selected['service'] : null, 'situation' => $selectedRef])"
                    full data-nav-action="request" data-nav-request>{{ ui('cta.request_consultation') }}</x-d2.ui.button>
            </div>
        </x-slot:footer>
    </x-d2.ui.sheet>
@endpush
