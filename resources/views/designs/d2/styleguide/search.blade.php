{{--
    Styleguide: Design 2 search area (command palette, FAQ browser, office-hours status, normalisation).
    Annotations are internal: lang/en/styleguide_d2/search.php. Demo content is approved copy (ui(),
    content()) or this area's proposed copy (d2p('palette.…'), d2p('faq.…'), d2p('hours.…')).
    The palette states are static specimens (<x-d2.ui.modal :static="true">: inert, no ids) drawn with the
    real palette's classes; the button above them opens the real palette. The FAQ specimens are live
    browsers (three categories, ids prefixed sg-…); data-faq-demo-query starts one in a search state. The
    hours specimens are the markup modules/hours.js writes, built here for fixed situations with the office-hours
    module's own server helper (App\Design\D2\Hours::line() and offset(): each locale's approved time style,
    no middle dots); the first one is live.
    Design 2.1 (Phase 1, home-b): the palette specimens follow the real palette: text rows, group headings as
    plain text (round 1: no chips, brief 3.5), no arrows, titles and summaries in full (D03, D04, D09).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Hours;
    use App\Support\SiteUrl;

    $t = fn (string $key) => __('styleguide_d2/search.'.$key);
    $en = app()->isLocale('en') ? '' : 'lang=en';
    $locale = app()->getLocale();

    // ---------------------------------------------------------------- palette specimens
    $demo = (array) $t('demo_query');
    $query = (string) ($demo[$locale] ?? $demo['en'] ?? '');
    $none = (string) $t('demo_none');
    $markWords = function (string $text) use ($query) {
        $html = '';
        foreach (preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $piece) {
            $html .= trim($piece) !== '' && mb_stripos($piece, $query) !== false ? '<mark>'.e($piece).'</mark>' : e($piece);
        }

        return new \Illuminate\Support\HtmlString($html);
    };
    $faqs = collect(content()->faqs())->keyBy('anchor');
    $firstParagraph = fn (?array $faq) => collect($faq['answer'] ?? [])->firstWhere('type', 'p')['text'] ?? '';
    $s01 = content()->service('S01') ?? [];
    $g03 = content()->guide('G03') ?? [];
    $case = content()->caseStudy('case-01') ?? [];
    $results = [
        ['label' => ui('nav.practice_areas'), 'rows' => [[$s01['title'] ?? '', $s01['hub_summary'] ?? '']]],
        ['label' => ui('nav.resources'), 'rows' => [[$g03['card_title'] ?? '', $g03['card_summary'] ?? '']]],
        ['label' => ui('nav.faq'), 'rows' => [
            [$faqs['faq-08']['question'] ?? '', $firstParagraph($faqs['faq-08'] ?? null)],
            [$faqs['faq-09']['question'] ?? '', $firstParagraph($faqs['faq-09'] ?? null)],
        ]],
        ['label' => ui('nav.case_studies'), 'rows' => [[$case['card_title'] ?? '', $case['card_summary'] ?? '']]],
    ];
    $describeWith = fn (string $q) => str_replace(':query', $q, d2p('palette.describe_with'));

    // ---------------------------------------------------------------- FAQ specimens: three categories, prefixed ids
    $categories = array_slice(content()->faqCategories(), 0, 3);
    $codes = array_column($categories, 'anchor');
    $faqItems = fn (string $prefix) => collect(content()->faqs())
        ->filter(fn ($faq) => in_array($faq['category'] ?? '', $codes, true))
        ->map(fn ($faq) => ['id' => $prefix.$faq['anchor'], 'category' => $faq['category'], 'question' => $faq['question'], 'answer' => $faq['answer']])
        ->values()->all();

    // ---------------------------------------------------------------- hours specimens (the markup hours.js writes)
    // The lines come from the office-hours module's server helper, so they read exactly as the script writes them.
    $hours = Hours::sentence('home');
    $hourSpecimens = [
        'hours_open' => [true, Hours::line('open'), null],
        'hours_open_ahead' => [true, Hours::line('open'), Hours::offset(240)],
        'hours_closed_today' => [false, Hours::line('closed_today'), null],
        'hours_closed_tomorrow' => [false, Hours::line('closed_tomorrow'), null],
        'hours_closed' => [false, Hours::line('closed', 1), null],
        'hours_closed_behind' => [false, Hours::line('closed', 2), Hours::offset(-90)],
        'hours_short' => [true, Hours::line('open'), null],
    ];
@endphp
<section class="sg-section" id="sg-search">
    <h2 {{ $en }}>{{ $t('title') }}</h2>
    <p {{ $en }}>{{ $t('intro') }}</p>

    {{-- ------------------------------------------------------------------ command palette --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('palette') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('palette_note') }}</p>
    <div class="sg-row">
        <x-d2.ui.button variant="secondary" icon="search" data-d2-search data-sg="palette-open">{{ d2p('shell.search_placeholder') }}</x-d2.ui.button>
        <x-d2.ui.kbd>/</x-d2.ui.kbd>
        <x-d2.ui.kbd :keys="['Ctrl', 'K']" />
        <span class="sg-label" {{ $en }}>{{ $t('palette_live') }}</span>
    </div>

    <div class="sg-grid sg-grid--wide">
        {{-- empty --}}
        <div class="sg-specimen">
            <x-d2.ui.modal id="sg-pal-empty" :static="true" class="palette palette--specimen" :title="d2p('palette.title')" :title-hidden="true">
                <x-slot:head>
                    <div class="palette__box">
                        <x-d2.ui.input type="search" icon="search" wrap-class="palette__field" readonly tabindex="-1" />
                        <span class="palette__placeholder">{{ d2p('shell.search_placeholder') }}</span>
                    </div>
                </x-slot:head>
                <div class="palette__list">
                    <div class="palette__group">
                        <div class="palette__group-label">{{ ui('nav.practice_areas') }}</div>
                        @foreach (array_slice(content()->services(), 0, 6) as $i => $service)
                            <span @class(['palette-option', 'is-active' => $i === 0])>
                                <span class="palette-option__text">
                                    <span class="palette-option__title">{{ Bidi::isolate($service['title'] ?? '') }}</span>
                                </span>
                            </span>
                        @endforeach
                        <span class="palette-option palette-option--more">
                            <span class="palette-option__text"><span class="palette-option__title">{{ ui('cta.view_all_practice_areas') }}</span></span>
                        </span>
                    </div>
                </div>
                <x-slot:footer>
                    <p class="palette__hints">
                        <span class="palette__hint"><x-d2.ui.kbd :keys="[d2p('palette.key_up'), d2p('palette.key_down')]" /><span>{{ d2p('palette.hint_move') }}</span></span>
                        <span class="palette__hint"><x-d2.ui.kbd>{{ d2p('palette.key_enter') }}</x-d2.ui.kbd><span>{{ d2p('palette.hint_open') }}</span></span>
                        <span class="palette__hint"><x-d2.ui.kbd>{{ d2p('palette.key_esc') }}</x-d2.ui.kbd><span>{{ d2p('palette.hint_close') }}</span></span>
                    </p>
                </x-slot:footer>
            </x-d2.ui.modal>
            <p class="sg-label" {{ $en }}>{{ $t('palette_empty') }}</p>
        </div>

        {{-- results --}}
        <div class="sg-specimen">
            <x-d2.ui.modal id="sg-pal-results" :static="true" class="palette palette--specimen" data-nav="keyboard" :title="d2p('palette.title')" :title-hidden="true">
                <x-slot:head>
                    <x-d2.ui.input type="search" icon="search" wrap-class="palette__field" :value="$query" readonly tabindex="-1" />
                </x-slot:head>
                <div class="palette__list">
                    @foreach ($results as $g => $group)
                        <div class="palette__group">
                            <div class="palette__group-label">{{ $group['label'] }}</div>
                            @foreach ($group['rows'] as $r => [$title, $summary])
                                <span @class(['palette-option', 'is-active' => $g === 2 && $r === 0])>
                                    <span class="palette-option__text">
                                        <span class="palette-option__title">{{ $markWords($title) }}</span>
                                        <span class="palette-option__summary">{{ $summary }}</span>
                                    </span>
                                </span>
                            @endforeach
                        </div>
                    @endforeach
                    <span class="palette-option palette-option--describe">
                        <span class="palette-option__text">
                            <span class="palette-option__title">{{ d2p('palette.describe') }}</span>
                            <span class="palette-option__summary">{{ $describeWith($query) }}</span>
                        </span>
                    </span>
                </div>
            </x-d2.ui.modal>
            <p class="sg-label" {{ $en }}>{{ $t('palette_results') }}</p>
        </div>

        {{-- no results --}}
        <div class="sg-specimen">
            <x-d2.ui.modal id="sg-pal-none" :static="true" class="palette palette--specimen" data-nav="keyboard" :title="d2p('palette.title')" :title-hidden="true">
                <x-slot:head>
                    <x-d2.ui.input type="search" icon="search" wrap-class="palette__field" :value="$none" readonly tabindex="-1" />
                </x-slot:head>
                <div class="palette__notice">
                    <p class="palette__notice-title">{{ str_replace(':query', $none, d2p('palette.no_results')) }}</p>
                    <p class="palette__notice-text">{{ d2p('palette.no_results_hint') }}</p>
                </div>
                <div class="palette__list">
                    <span class="palette-option palette-option--describe is-active">
                        <span class="palette-option__text">
                            <span class="palette-option__title">{{ d2p('palette.describe') }}</span>
                            <span class="palette-option__summary">{{ $describeWith($none) }}</span>
                        </span>
                    </span>
                </div>
            </x-d2.ui.modal>
            <p class="sg-label" {{ $en }}>{{ $t('palette_none') }}</p>
        </div>

        {{-- loading --}}
        <div class="sg-specimen">
            <x-d2.ui.modal id="sg-pal-loading" :static="true" class="palette palette--specimen" :title="d2p('palette.title')" :title-hidden="true">
                <x-slot:head>
                    <x-d2.ui.input type="search" icon="search" wrap-class="palette__field" :value="$query" readonly tabindex="-1" />
                </x-slot:head>
                <div class="palette__loading">
                    <x-d2.ui.skeleton shape="row" size="40" :label="d2p('palette.loading')" />
                    <x-d2.ui.skeleton shape="row" size="40" :label="d2p('palette.loading')" class="palette__bone-2" />
                    <x-d2.ui.skeleton shape="row" size="40" :label="d2p('palette.loading')" class="palette__bone-3" />
                </div>
            </x-d2.ui.modal>
            <p class="sg-label" {{ $en }}>{{ $t('palette_loading') }}</p>
        </div>

        {{-- error --}}
        <div class="sg-specimen">
            <x-d2.ui.modal id="sg-pal-error" :static="true" class="palette palette--specimen" :title="d2p('palette.title')" :title-hidden="true">
                <x-slot:head>
                    <x-d2.ui.input type="search" icon="search" wrap-class="palette__field" :value="$query" readonly tabindex="-1" />
                </x-slot:head>
                <div class="palette__notice">
                    <p class="palette__notice-title">{{ d2p('palette.error') }}</p>
                    <x-d2.ui.button class="palette__retry" variant="secondary" size="40" tabindex="-1">{{ d2p('palette.retry') }}</x-d2.ui.button>
                </div>
                <div class="palette__list">
                    <span class="palette-option palette-option--describe">
                        <span class="palette-option__text">
                            <span class="palette-option__title">{{ d2p('palette.describe') }}</span>
                            <span class="palette-option__summary">{{ $describeWith($query) }}</span>
                        </span>
                    </span>
                </div>
            </x-d2.ui.modal>
            <p class="sg-label" {{ $en }}>{{ $t('palette_error') }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ FAQ browser --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('faq') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('faq_note') }}</p>

    <div class="sg-specimen sg-search-faq">
        <x-d2.faq.browser id="sg-faq-default" :categories="$categories" :items="$faqItems('sg-fd-')" panel-prefix="sg-fd-" :copy-links="true" :expand-all="true" />
        <p class="sg-label" {{ $en }}>{{ $t('faq_default') }}</p>
    </div>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen sg-search-faq">
            <x-d2.faq.browser id="sg-faq-search" :categories="$categories" :items="$faqItems('sg-fs-')" panel-prefix="sg-fs-" :links="false" data-faq-demo-query="{{ $query }}" />
            <p class="sg-label" {{ $en }}>{{ $t('faq_search') }}</p>
        </div>
        <div class="sg-specimen sg-search-faq">
            <x-d2.faq.browser id="sg-faq-none" :categories="$categories" :items="$faqItems('sg-fn-')" panel-prefix="sg-fn-" :links="false" data-faq-demo-query="{{ $none }}" />
            <p class="sg-label" {{ $en }}>{{ $t('faq_none') }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ office hours --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('hours') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('hours_note') }}</p>
    <div class="sg-grid">
        <div class="sg-specimen">
            <p class="t-meta">{{ $hours }} <span data-hours-status></span></p>
            <p class="sg-label" {{ $en }}>{{ $t('hours_live') }}</p>
        </div>
        @foreach ($hourSpecimens as $key => [$open, $text, $diff])
            <div class="sg-specimen">
                <p class="t-meta">
                    <span @class(['hours-status', 'is-open' => $open, 'is-closed' => ! $open]) @proposed>
                        <span class="hours-status__dot" aria-hidden="true"></span>
                        <span class="hours-status__text">{{ $text }}</span>
                        @if ($diff)<span class="hours-status__offset">{{ $diff }}</span>@endif
                    </span>
                </p>
                <p class="sg-label" {{ $en }}>{{ $t($key) }}</p>
            </div>
        @endforeach
        <div class="sg-night on-night">
            <p class="t-meta">
                <span class="hours-status is-open" @proposed>
                    <span class="hours-status__dot" aria-hidden="true"></span>
                    <span class="hours-status__text">{{ Hours::line('open') }}</span>
                    <span class="hours-status__offset">{{ Hours::offset(240) }}</span>
                </span>
            </p>
            <p class="sg-label" {{ $en }}>{{ $t('hours_night') }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ normalisation --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('normalise') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('normalise_note') }}</p>
    <div class="sg-specimen">
        <table class="sg-search-table" {{ $en }}>
            <thead><tr><th scope="col">{{ $t('normalise_in') }}</th><th scope="col"><code class="sg-code">{{ $t('normalise_out') }}</code></th></tr></thead>
            <tbody>
                @foreach ((array) $t('normalise_examples') as [$in, $out])
                    <tr><td dir="auto">{{ $in }}</td><td dir="auto"><code class="sg-code">{{ $out }}</code></td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
