@props([
    'id' => 'faq',            // root id; the search field is {id}-search
    'items' => null,          // [['id', 'category', 'question', 'answer' => [paragraph, …]], …]; default: every approved FAQ (faq-01 … faq-74)
    'override' => [],         // [category => items]: replaces one category's questions (home: the 8 approved home questions in cat-01)
    'categories' => null,     // default: content()->faqCategories() (cat-01 … cat-12, approved names and links)
    'active' => null,         // panel id shown first (default: the first category); a #hash inside a panel wins
    'panelPrefix' => '',      // panel ids = prefix + category anchor (P05 keeps cat-01 … cat-12)
    'panelLevel' => 3,        // heading level of the category titles (visible without JS and while searching)
    'questionLevel' => 4,     // heading level of the questions and of "Useful Links"
    'search' => true,         // the local search filter (JavaScript only)
    'links' => true,          // each category's approved links + "Was this about your matter? Start here"
    'copyLinks' => false,     // "Copy question link" in every answer (P05)
    'expandAll' => false,     // "Show all answers" / "Hide all answers" per category (P05)
    'pickerLabel' => null,    // the phone select's label (default: P05's approved "Choose a topic")
])
{{--
    FAQ browser (upgrade spec §12 C11; the P05 FAQ page): approved category tabs, a local search filter and the
    Phase A accordion. D41 (Design 2.1): the category tabs wrap onto more rows (never cut, no scroll buttons, no
    fade); below 1024 px (phones and tablets, with JavaScript) a select labelled with P05's approved "Choose a
    topic" replaces them and drives the same tabs (d2Faq). Every question and answer is in the HTML:
    without JavaScript the tabs are jump links, every category shows, and the search field, "Show all
    answers" and "Copy question link" (which need the script) are hidden.
    With JavaScript the tabbed layout is drawn from the first paint (html.js, set by the boot script; the
    integration rule of docs/d2/engineering.md §4b): only the initial category ([data-faq-initial], or the
    one a #hash points into) shows, its title is already the visually hidden heading and its tab is
    marked, so nothing moves when d2Faq and d2Tabs mount.
    Behaviour: resources/js/d2/components/faq.js (d2Faq). Styles: resources/css/d2/sections/faq.css.

      P05:   <x-d2.faq.browser id="faq-browser" :panel-level="2" :question-level="3" :copy-links="true" :expand-all="true" />
      (The home's section 08 is the eight approved questions as a plain accordion, not this browser.)

    Ids: questions keep their approved anchors (faq-01 … faq-74, or the ids passed in, e.g. home-faq-1 …),
    panels are {panelPrefix}{cat-NN}; #id opens a question (accordion-hash.js) after its tab is selected
    (d2Tabs). Arabic: Latin runs in questions and answers are isolated (Bidi::isolate).

    Design 2.1 (Phase 1, home-b): the Useful Links are text links without decorative icons or chevrons
    (D03, D04); a link to another website keeps the external mark (an SVG at the end) and its hidden "opens
    in a new tab". "Start here" has no arrow. "Copy question link" (P05) is a text button (the link glyph is
    not in the allowed icon set of brief 3.6 rule 5). Phase 2: the tabs (D41) and P05's levels (category H2,
    question H3: the FAQ file's note 7).
    Round 1 (home-b, D09): the approved search placeholder ("Enter a word such as: cheque, salary, will, tax")
    is never cut. A native placeholder cannot wrap, and no phone field holds it (English needs 367 px of text,
    Russian 484), so it is drawn inside the field instead of being an attribute: the box around the input shows
    it while the field is empty, wrapping (two lines at 390 px, Russian three at 320 px), the field growing by
    one line height per extra line; it is the input's description (aria-describedby), and it goes as soon as a
    word is typed (the field is 48 px again). A press anywhere in the box puts the caret in the field (d2Faq).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Prefill;
    use App\Support\SiteUrl;

    $locale = app()->getLocale();
    $mark = app()->isProduction() ? null : 'proposed';
    $panelLevel = max(2, min(5, (int) $panelLevel));
    $questionLevel = max(2, min(4, (int) $questionLevel));

    $paragraphs = fn ($answer) => array_values(array_filter(
        array_map(fn ($block) => is_string($block) ? $block : (is_array($block) && ($block['type'] ?? 'p') === 'p' ? (string) ($block['text'] ?? '') : ''), (array) $answer),
        fn (string $text) => trim($text) !== '',
    ));

    $items ??= array_map(fn (array $faq) => [
        'id' => (string) ($faq['anchor'] ?? $faq['code']),
        'category' => (string) ($faq['category'] ?? ''),
        'question' => (string) ($faq['question'] ?? ''),
        'answer' => $faq['answer'] ?? [],
    ], content()->faqs());

    $byCategory = [];
    foreach ((array) $items as $item) {
        $byCategory[(string) ($item['category'] ?? '')][] = $item;
    }
    foreach ((array) $override as $category => $list) {
        $byCategory[(string) $category] = array_values((array) $list);
    }

    $link = function (array $link) {
        $target = (string) ($link['target'] ?? '');
        $external = (bool) preg_match('#^https?://#', $target);
        $service = preg_match('/^S(0[1-9]|1[01])$/', $target) ? $target : null;

        return [
            'label' => (string) ($link['label'] ?? ''),
            'url' => $external ? $target : SiteUrl::target($target),
            'external' => $external,
            'service' => $service,
        ];
    };

    $panels = [];
    foreach ($categories ?? content()->faqCategories() as $category) {
        $anchor = (string) ($category['anchor'] ?? $category['code'] ?? '');
        $list = $byCategory[$anchor] ?? [];
        if ($anchor === '' || $list === []) {
            continue;
        }
        $related = array_values(array_filter(array_map($link, array_filter((array) ($category['links'] ?? []), 'is_array')), fn ($l) => $l['label'] !== ''));
        $panels[] = [
            'id' => $panelPrefix.$anchor,
            'name' => (string) ($category['name'] ?? ''),
            'items' => $list,
            'links' => $related,
            'service' => collect($related)->pluck('service')->filter()->first(),
        ];
    }
    $tabs = array_map(fn (array $panel) => [$panel['id'], $panel['name']], $panels);
    $active = in_array($active, array_column($tabs, 0), true) ? $active : ($tabs[0][0] ?? null);
    $pickerLabel = filled($pickerLabel) ? (string) $pickerLabel : (string) (content()->page('P05')['content']['category_menu']['heading'] ?? d2p('faq.categories_label'));

    // Plural forms keyed by CLDR category for the script (see <x-d2.shell.palette>).
    $samples = match ($locale) {
        'ar' => ['zero' => 0, 'one' => 1, 'two' => 2, 'few' => 3, 'many' => 11, 'other' => 100],
        'ru' => ['one' => 1, 'few' => 2, 'many' => 5],
        default => ['one' => 1, 'other' => 2],
    };
    $copy = [
        'locale' => $locale,
        'results' => array_map(fn (int $n) => trans_choice('proposed.d2.faq.results', $n, ['count' => ':count']), $samples),
        'noResults' => ui('faq.no_results'),
        'linkCopied' => ui('faq.link_copied'),
        'copyFailed' => ui('faq.copy_failed'),
        'expandAll' => ui('faq.expand_all'),
        'collapseAll' => ui('faq.collapse_all'),
    ];
@endphp
<div {{ $attributes->class(['faq-browser']) }} id="{{ $id }}" x-data="d2Faq" data-faq-browser>
    <script type="application/json" data-faq-copy>{!! json_encode($copy, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

    @if ($search)
        <div class="faq-browser__search" role="search">
            <x-d2.ui.field :id="$id.'-search'" :label="ui('faq.search_label')" class="faq-browser__field">
                {{-- D09: the approved placeholder is never cut. A native placeholder cannot wrap, so the example is
                     drawn inside the field by the box: it wraps (on a phone the whole example shows on two or three
                     lines and the field grows with it), it is the input's description (aria-describedby), and it goes
                     once a word is typed (.is-filled, d2Faq), the field 48 px again (faq.css). --}}
                <div class="faq-browser__box" data-faq-box>
                    <x-d2.ui.input type="search" icon="search" aria-describedby="{{ $id }}-search-example"
                        autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search" maxlength="80"
                        aria-controls="{{ $id }}-results" data-faq-search />
                    <span class="faq-browser__hint" id="{{ $id }}-search-example" data-faq-hint>{{ ui('faq.search_placeholder') }}</span>
                </div>
            </x-d2.ui.field>
            {{-- The approved "Search" control (05_FAQ_Centre, ui.faq.search_submit). The list already filters as the
                 visitor types; the button runs the same search and reads the result out (brief 5.3: every approved
                 control is present). --}}
            <x-d2.ui.button class="faq-browser__submit" variant="secondary" size="48" type="button" aria-controls="{{ $id }}-results" data-faq-submit>{{ ui('faq.search_submit') }}</x-d2.ui.button>
            <div class="faq-browser__state">
                <p class="faq-browser__status" role="status" data-faq-status></p>
                <x-d2.ui.button class="faq-browser__reset" variant="text" size="40" icon="close" data-faq-reset hidden>{{ ui('faq.search_reset') }}</x-d2.ui.button>
            </div>
        </div>
    @endif

    {{-- Below 1024 px (D41): one select instead of the tab rows; it selects the same tabs (d2Faq), so the deep links, the
         search and the panels behave the same. JavaScript only: without it the tabs are wrapped jump links. --}}
    @if (count($tabs) > 1)
        <div class="faq-browser__picker">
            <x-d2.ui.field :id="$id.'-category'" :label="$pickerLabel">
                <x-d2.ui.select :options="$tabs" :value="$active" aria-controls="{{ $id }}-results" data-faq-picker />
            </x-d2.ui.field>
        </div>
    @endif

    <x-d2.ui.tabs :tabs="$tabs" :label="d2p('faq.categories_label')" :active="$active" class="faq-browser__tabs" id="{{ $id }}-results">
        @foreach ($panels as $panel)
            <x-d2.ui.tab-panel :id="$panel['id']" :title="$panel['name']" :title-level="$panelLevel" class="faq-panel" data-faq-panel :data-faq-initial="$panel['id'] === $active">
                <div @class(['faq-panel__grid', 'faq-panel__grid--links' => $links])>
                    <div class="faq-panel__main">
                        @if ($expandAll)
                            <div class="faq-panel__tools">
                                <x-d2.ui.button variant="text" size="40" data-faq-expand>{{ ui('faq.expand_all') }}</x-d2.ui.button>
                            </div>
                        @endif
                        <x-d2.ui.accordion :level="$questionLevel" class="faq-list">
                            @foreach ($panel['items'] as $item)
                                <x-d2.ui.accordion-item :id="$item['id']" :title="Bidi::isolate($item['question'] ?? '')" data-faq-item>
                                    @foreach ($paragraphs($item['answer'] ?? []) as $text)
                                        <p>{{ Bidi::isolate($text) }}</p>
                                    @endforeach
                                    @if ($copyLinks)
                                        <div class="faq-item__tools">
                                            <x-d2.ui.button variant="text" size="40" data-faq-copy-link="{{ $item['id'] }}">{{ ui('faq.copy_link') }}</x-d2.ui.button>
                                        </div>
                                    @endif
                                </x-d2.ui.accordion-item>
                            @endforeach
                        </x-d2.ui.accordion>
                    </div>

                    @if ($links)
                        <aside class="faq-panel__aside" aria-labelledby="{{ $panel['id'] }}-aside">
                            <h{{ $questionLevel }} class="faq-aside__title" id="{{ $panel['id'] }}-aside">{{ ui('footer.useful_links') }}</h{{ $questionLevel }}>
                            @if ($panel['links'])
                                <ul class="faq-links" role="list">
                                    @foreach ($panel['links'] as $related)
                                        <li>
                                            <a class="faq-link" href="{{ $related['url'] }}" @if ($related['external']) target="_blank" rel="noopener noreferrer" @endif>
                                                <span class="faq-link__label">{{ Bidi::isolate($related['label']) }}</span>
                                                @if ($related['external'])
                                                    <span class="faq-link__external" aria-hidden="true"><x-d2.ui.icon name="external" size="20" /></span>
                                                    <span class="sr-only" @proposed>{{ d2p('controls.new_tab') }}</span>
                                                @endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="faq-start">
                                <p class="faq-start__question" @proposed>{{ d2p('faq.start_question') }}</p>
                                <x-d2.ui.button variant="ink" :href="Prefill::url(array_filter(['service' => $panel['service']]))"
                                    :data-copy-status="$mark" data-faq-start="{{ $panel['service'] }}">{{ d2p('faq.start_link') }}</x-d2.ui.button>
                            </div>
                        </aside>
                    @endif
                </div>
            </x-d2.ui.tab-panel>
        @endforeach
    </x-d2.ui.tabs>
</div>
