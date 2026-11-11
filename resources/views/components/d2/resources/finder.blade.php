@props([
    'heading',              // approved H2 "Find the Topic That Matters to You" (P03 content.find.heading)
    'groups' => [],         // [['code' => 'gc-01', 'name' => approved category, 'guides' => [...]], …]
])
{{--
    <x-d2.resources.finder :heading="$c['find']['heading']" :groups="$groups" />
    Guide search and topic filter of the Legal Knowledge Centre (P03 designer note: "Optional progressive
    enhancement … Search all nine guide titles and their full content. Filter both cards and associated
    sections consistently, keep a result count announced accessibly, and restore the selected guide when
    following a deep link. With JavaScript disabled, all full guides and normal anchor links remain
    available." and "If the search enhancement is not implemented, omit its controls").
    The topic is a row of chips from 768 px and a native picker on phones (every approved category name shown
    whole in both: no sideways row whose fade cuts a label).
    The controls exist only with JavaScript (shown under html.js before first paint, so a deep link never
    moves): the behaviour lives in d2Resources (resources/js/d2/components/resources.js). Every label is
    approved (ui('resources.*')); the result count is proposed (trans_choice, rendered here for every
    possible count, so the script never builds a plural itself). The status row appears with the first
    filter; its text is announced through the layout's polite live region (lib/announce.js), which is
    always in the accessibility tree.
--}}
@php
    use App\Design\D2\Bidi;

    $total = array_sum(array_map(fn ($g) => count($g['guides'] ?? []), $groups));
    $results = [];
    for ($n = 0; $n <= $total; $n++) {
        $results[$n] = trans_choice('proposed.d2.resources_page.results', $n, ['count' => '__N__']);
    }
@endphp
<section {{ $attributes->class(['kc-find']) }} aria-labelledby="kc-find-title" data-kc-finder>
    <div class="container">
        <div class="kc-find__panel">
            <h2 class="kc-find__title" id="kc-find-title">{{ Bidi::isolate($heading) }}</h2>
            <form class="kc-find__form" role="search" aria-labelledby="kc-find-title" data-kc-form>
                {{-- D09, the FAQ search's solution (sections/faq.css .faq-browser__box): the approved placeholder is never
                     cut. The box draws the field's edge around the input and a copy of the placeholder that wraps
                     (aria-hidden: the input's own placeholder attribute stays the accessible one), so a phone shows the
                     whole line on two lines and the field grows with it; once a word is typed the copy goes and the
                     field is 48 px again (resources.css). A press anywhere in the box focuses the field (d2Resources). --}}
                <x-d2.ui.field id="kc-q" :label="ui('resources.search.label')" class="kc-find__search">
                    <div class="kc-find__box" data-kc-box>
                        <x-d2.ui.input type="search" name="q" icon="search" :placeholder="ui('resources.search.placeholder')" autocomplete="off" enterkeyhint="search" spellcheck="false" data-kc-query />
                        <span class="kc-find__hint" aria-hidden="true">{{ ui('resources.search.placeholder') }}</span>
                    </div>
                </x-d2.ui.field>
                {{-- One topic, two controls (resources.css shows one): from 768 px the chips, one line each, wrapping as a
                     row; below 768 px (D30 / D41 rule: no half-cut label) the native picker. No phone row can hold
                     every approved category name on one line (RU «Цифровые доказательства и организация бизнеса» is
                     wider than a 390 px phone's panel), so a sideways row always cut one under its fade. The picker's
                     box shows the chosen name in full, wrapping (.kc-find__pick-value, aria-hidden: the transparent
                     <select> over it is the control, named by the same "Topic" label); d2Resources keeps both in step. --}}
                <div class="kc-find__topics">
                    <p class="kc-find__label" id="kc-topic-label">{{ ui('resources.topic.label') }}</p>
                    <div class="kc-find__chips" role="group" aria-labelledby="kc-topic-label">
                        <x-d2.ui.chip :pressed="true" data-kc-topic-chip="">{{ ui('resources.topic.all') }}</x-d2.ui.chip>
                        @foreach ($groups as $group)
                            <x-d2.ui.chip :pressed="false" data-kc-topic-chip="{{ $group['code'] }}">{{ Bidi::isolate($group['name']) }}</x-d2.ui.chip>
                        @endforeach
                    </div>
                    <div class="kc-find__pick" data-kc-pick>
                        <select class="kc-find__select" id="kc-topic" aria-labelledby="kc-topic-label" data-kc-topic-select>
                            <option value="" selected>{{ ui('resources.topic.all') }}</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group['code'] }}">{{ $group['name'] }}</option>
                            @endforeach
                        </select>
                        <span class="kc-find__pick-value" aria-hidden="true" data-kc-topic-value>{{ ui('resources.topic.all') }}</span>
                        <x-d2.ui.icon name="chevron-down" size="20" class="kc-find__pick-icon" />
                    </div>
                </div>
            </form>
            <div class="kc-find__foot" data-kc-foot hidden>
                <p class="kc-find__status" data-kc-status @proposed></p>
                <x-d2.ui.button variant="text" icon="close" :disabled="true" class="kc-find__clear" data-kc-clear>{{ ui('resources.clear') }}</x-d2.ui.button>
            </div>
            <script type="application/json" data-kc-strings>@json(['results' => $results, 'none' => ui('resources.no_results')])</script>
        </div>
    </div>
</section>
