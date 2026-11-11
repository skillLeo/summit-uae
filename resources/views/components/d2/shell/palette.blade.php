{{--
    Design 2 command palette (upgrade spec §12 C12 and §5.5; docs/d2/engineering.md §4a). Rendered once per
    page by d2::layouts.app, next to the global sheets.

    - Opens through the d2:palette-open protocol (header search trigger, "/" and Ctrl/Cmd+K, or any script;
      claimed by resources/js/d2/components/palette.js). An optional detail.query opens it with that text in
      the field and its results shown (docs/d2/engineering.md §4a). Without JavaScript the triggers go to the
      Legal Resources Centre, so nothing here has to work without it.
    - Phase A modal (<x-d2.ui.modal>): 640 px, --r-lg, --shadow-4, full screen below 768 px; Esc, the
      backdrop and Back close it; focus returns to the opener.
    - Search field = ARIA combobox; results = listbox of groups (heading = approved navigation label)
      and options; ↑ ↓ move, Enter opens, Esc closes. Empty state: the first six practice areas in the
      approved order (no usage data is collected, so no "most used" ranking) + View All Practice Areas.
    - The index public/search/{locale}.json (php artisan summit:search-index) and MiniSearch load on first
      open (resources/js/d2/palette/engine.js): that JSON is the only request. Nothing is stored or sent;
      "Describe your matter" hands the typed words to the consultation wizard (lib/prefill.js).
    - Copy blocks for scripts: #d2-palette-copy (palette strings) and #d2-hours-copy (office hours for
      modules/hours.js, used by every [data-hours-status] on the page).
    - Design 2.1 (Phase 1, home-b): text rows only. No icon tiles on the options, no icons in the type chips,
      no arrows (D03, D04: menu items carry no icons); titles and summaries wrap instead of being cut with
      an ellipsis (D09). The empty state lists the six practice areas by their approved titles alone (a
      short menu; their summaries appear with the search results), so nothing has to be cut to keep it
      compact. The search field keeps its search icon. The #d2-hours-copy block belongs to the
      office-hours module.
    - Round 1 (home-b, brief 3.5): each group opens with a plain heading in the UI-label role (the approved
      navigation label as text, components/palette.css), not a chip: a chip is a control and these labels
      are not filters. The field's hint is never cut (the FAQ search's box: the hint is drawn in the field as
      wrapping text while it is empty, the field's description, so Russian and Arabic phones show all of it).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Prefill;
    use App\Support\SiteUrl;

    $locale = app()->getLocale();
    $file = public_path("search/{$locale}.json");
    $indexUrl = is_file($file) ? "/search/{$locale}.json?v=".substr((string) hash_file('xxh128', $file), 0, 12) : '';
    $mark = app()->isProduction() ? null : 'proposed';

    // Plural forms keyed by CLDR category (Intl.PluralRules in the browser), from trans_choice with a
    // whole number of each category; the placeholder :count stays for the script to fill.
    $samples = match ($locale) {
        'ar' => ['zero' => 0, 'one' => 1, 'two' => 2, 'few' => 3, 'many' => 11, 'other' => 100],
        'ru' => ['one' => 1, 'few' => 2, 'many' => 5],
        default => ['one' => 1, 'other' => 2],
    };
    $forms = fn (string $key) => array_map(fn (int $n) => trans_choice('proposed.d2.'.$key, $n, ['count' => ':count']), $samples);

    $featured = array_slice(content()->services(), 0, 6);
    $groups = [
        'service' => ['label' => ui('nav.practice_areas')],
        'guide' => ['label' => ui('nav.resources')],
        'faq' => ['label' => ui('nav.faq')],
        'case' => ['label' => ui('nav.case_studies')],
    ];

    $copy = [
        'locale' => $locale,
        'index' => $indexUrl,
        'results' => $forms('palette.results'),
        'noResults' => d2p('palette.no_results'),
        'describeWith' => d2p('palette.describe_with'),
        'loading' => d2p('palette.loading'),
        'error' => d2p('palette.error'),
    ];

    // Office hours for modules/hours.js (time-digits, Design 2.1 §3.7): the live-status switch, the schedule and the
    // proposed templates with their plural forms by CLDR category, built by App\Design\D2\Hours::copy().
    $hoursCopy = \App\Design\D2\Hours::copy($locale);
    $json = fn (array $data) => json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp
<x-d2.ui.modal id="palette" class="palette" :title="d2p('palette.title')" :title-hidden="true" :title-proposed="true"
    data-index="{{ $indexUrl }}" data-state="idle">
    <x-slot:head>
        {{-- The search box (round 1): the field's edge around the input, and the hint drawn inside it as text that
             wraps, so a phone shows the whole hint instead of cutting it ("Поиск по услугам, материалам…"; a native
             placeholder cannot wrap). It is the field's description and goes once a word is typed (.is-filled,
             components/palette.js; components/palette.css). --}}
        <div class="palette__box" data-palette-box>
            <x-d2.ui.input id="palette-input" type="search" icon="search" wrap-class="palette__field"
                :aria-label="d2p('palette.input_label')" aria-describedby="palette-hint"
                role="combobox" aria-expanded="true" aria-controls="palette-list" aria-autocomplete="list" aria-haspopup="listbox"
                autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="go" maxlength="120"
                :data-copy-status="$mark" autofocus data-palette-input />
            <span class="palette__placeholder" id="palette-hint" @proposed>{{ d2p('shell.search_placeholder') }}</span>
        </div>
    </x-slot:head>

    <p class="sr-only" role="status" data-palette-status></p>

    {{-- No results / error: shown above the list (the "Describe your matter" option stays in the list). --}}
    <div class="palette__notice" data-palette-notice hidden>
        <p class="palette__notice-title" data-palette-notice-title></p>
        <p class="palette__notice-text" data-palette-notice-text @proposed>{{ d2p('palette.no_results_hint') }}</p>
        <x-d2.ui.button class="palette__retry" variant="secondary" size="40" :data-copy-status="$mark" data-palette-retry hidden>{{ d2p('palette.retry') }}</x-d2.ui.button>
    </div>

    {{-- First open with a query typed before the index arrived. --}}
    {{-- The skeletons' "Loading search" labels are proposed copy (marked on their group). --}}
    <div class="palette__loading" data-palette-loading hidden @proposed>
        <x-d2.ui.skeleton shape="row" size="40" :label="d2p('palette.loading')" />
        <x-d2.ui.skeleton shape="row" size="40" :label="d2p('palette.loading')" class="palette__bone-2" />
        <x-d2.ui.skeleton shape="row" size="40" :label="d2p('palette.loading')" class="palette__bone-3" />
    </div>

    <div class="palette__list" role="listbox" id="palette-list" aria-label="{{ d2p('palette.input_label') }}" data-palette-list>
        <div class="palette__group" role="group" aria-labelledby="palette-g-featured" data-palette-featured>
            <div class="palette__group-label" id="palette-g-featured">{{ $groups['service']['label'] }}</div>
            @foreach ($featured as $service)
                <a class="palette-option" role="option" id="palette-o-{{ $service['code'] }}" href="{{ SiteUrl::service($service['code']) }}" tabindex="-1" aria-selected="false" data-palette-option>
                    <span class="palette-option__text">
                        <span class="palette-option__title">{{ Bidi::isolate($service['title'] ?? '') }}</span>
                    </span>
                </a>
            @endforeach
            <a class="palette-option palette-option--more" role="option" id="palette-o-all" href="{{ SiteUrl::page('P02') }}" tabindex="-1" aria-selected="false" data-palette-option>
                <span class="palette-option__text">
                    <span class="palette-option__title">{{ ui('cta.view_all_practice_areas') }}</span>
                </span>
            </a>
        </div>
        <a class="palette-option palette-option--describe" role="option" id="palette-o-describe" href="{{ Prefill::url() }}" tabindex="-1" aria-selected="false" hidden data-palette-option data-palette-describe>
            <span class="palette-option__text">
                <span class="palette-option__title" @proposed>{{ d2p('palette.describe') }}</span>
                <span class="palette-option__summary" data-palette-describe-text @proposed></span>
            </span>
        </a>
    </div>

    {{-- Markup the script clones (no HTML strings in JavaScript). --}}
    @foreach ($groups as $type => $group)
        <template data-palette-group="{{ $type }}">
            <div class="palette__group" role="group">
                <div class="palette__group-label">{{ $group['label'] }}</div>
            </div>
        </template>
    @endforeach
    <template data-palette-option-template>
        <a class="palette-option" role="option" tabindex="-1" aria-selected="false" data-palette-option>
            <span class="palette-option__text">
                <span class="palette-option__title" data-slot="title"></span>
                <span class="palette-option__summary" data-slot="summary"></span>
            </span>
        </a>
    </template>

    <x-slot:footer>
        <p class="palette__hints" aria-hidden="true" @proposed>
            <span class="palette__hint"><x-d2.ui.kbd :keys="[d2p('palette.key_up'), d2p('palette.key_down')]" /><span>{{ d2p('palette.hint_move') }}</span></span>
            <span class="palette__hint"><x-d2.ui.kbd>{{ d2p('palette.key_enter') }}</x-d2.ui.kbd><span>{{ d2p('palette.hint_open') }}</span></span>
            <span class="palette__hint"><x-d2.ui.kbd>{{ d2p('palette.key_esc') }}</x-d2.ui.kbd><span>{{ d2p('palette.hint_close') }}</span></span>
        </p>
    </x-slot:footer>
</x-d2.ui.modal>
<script type="application/json" id="d2-palette-copy">{!! $json($copy) !!}</script>
<script type="application/json" id="d2-hours-copy">{!! $json($hoursCopy) !!}</script>
