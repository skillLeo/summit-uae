@props([
    'guide',                // content()->guide() record (KnowledgeCentreMapper): anchor, category, card_title, body …
    'category' => null,     // approved name of the guide's category
])
{{--
    <x-d2.resources.article :guide="$guide" :category="$name" />
    One complete guide of the Legal Knowledge Centre (P03), a section of the page (no route of its own),
    upgrade spec §7.4 / §12 Phase D "Guide page". Every approved block in the DOCX order:
      H2 title (id = the registered anchor, e.g. #unpaid-salary: the target of the cards, the contents and
      every related link; tabindex -1 so the link can move focus there)
      introduction (body-L) · then, through the inner kit's prose (<x-d2.inner.prose>, H3 sections):
      Who This Guide Is For · the substantive sections · Other Points to Consider (info callout) · Steps to
      Prepare Your File (numbered steps) · Documents to Help with the Review · Common Mistakes to Avoid
      (the service pages' band panel with a cross per item) · Questions About This Guide (H4 questions in an accordion, answers in the HTML) ·
      Official Sources for Reference (new tab, rel="noopener noreferrer")
      Related Services and Topics (H3): the related service card, the other related links, the consultation
      CTA (the wizard gets the guide's service, Prefill::url) and the way back to the directory.
    H3 section ids are "{anchor}-{part}" (audience, part-1…, considerations, steps, documents, mistakes,
    questions, sources, related). Search hooks: data-kc-guide, data-kc-topic (d2Resources).
    Design 2.1 (brief §3.6 rules 3, 5, 9 and 10): no generated cover banner (a fake thumbnail with a circled
    icon), no repeated "N min read" line with its clock, no object icons or arrows on the related links.

    Two parts: .kc-guide__card (category, H2 and the proposed "Read the full guide" disclosure) and
    .kc-guide__body (#{anchor}-body: the introduction, the sections and the related links). Below 768 px with
    JavaScript (html.js, set before the first paint) each guide starts as a compact card (category, title,
    disclosure: the directory's swipe rails above already show every approved summary, so the phone page
    never shows a summary twice): the body is hidden until the disclosure (d2Resources) or a link to the
    guide or to an anchor inside it opens it (.is-open). Without JavaScript, in print and from 768 px the body
    always shows and the disclosure does not (resources.css).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Cover;
    use App\Design\D2\Prefill;
    use App\Support\SiteUrl;

    $code = (string) ($guide['code'] ?? '');
    $anchor = (string) (($guide['anchor'] ?? null) ?: config("summit.guides.{$code}", strtolower($code)));
    $b = (array) ($guide['body'] ?? []);
    // The guide's practice area (its first related service): the consultation link prefills it.
    $service = Cover::serviceFor($guide);
    $heading = fn ($part) => is_array($part) ? trim((string) ($part['heading'] ?? '')) : '';
    $items = fn ($part) => is_array($part) ? array_values(array_filter((array) ($part['items'] ?? []), fn ($i) => (is_string($i) && trim($i) !== '') || (is_array($i) && $i !== []))) : [];

    // The guide body as canonical prose sections (inner kit), in the DOCX order.
    $sections = [];
    if ($heading($b['audience'] ?? null) !== '') {
        $sections[] = ['heading' => $heading($b['audience']), 'id' => $anchor.'-audience', 'blocks' => (array) ($b['audience']['blocks'] ?? [])];
    }
    foreach ((array) ($b['sections'] ?? []) as $i => $part) {
        if ($heading($part) !== '') {
            $sections[] = ['heading' => $heading($part), 'id' => $anchor.'-part-'.($i + 1), 'blocks' => (array) ($part['blocks'] ?? [])];
        }
    }
    if ($heading($b['considerations'] ?? null) !== '') {
        $sections[] = ['heading' => $heading($b['considerations']), 'id' => $anchor.'-considerations', 'variant' => 'note', 'tone' => 'info', 'blocks' => (array) ($b['considerations']['blocks'] ?? [])];
    }
    if ($heading($b['steps'] ?? null) !== '' && $items($b['steps']) !== []) {
        $sections[] = ['heading' => $heading($b['steps']), 'id' => $anchor.'-steps', 'blocks' => [['type' => 'steps', 'items' => $items($b['steps'])]]];
    }
    if ($heading($b['documents'] ?? null) !== '' && $items($b['documents']) !== []) {
        $sections[] = ['heading' => $heading($b['documents']), 'id' => $anchor.'-documents', 'blocks' => [['type' => 'ul', 'items' => $items($b['documents'])]]];
    }
    if ($heading($b['mistakes'] ?? null) !== '' && $items($b['mistakes']) !== []) {
        // The service pages' treatment (one look for the same list, D07 / §3.6 rule 5): the cool band panel with a cross per item.
        $sections[] = ['heading' => $heading($b['mistakes']), 'id' => $anchor.'-mistakes', 'variant' => 'panel', 'blocks' => [['type' => 'ul', 'marker' => 'cross', 'items' => $items($b['mistakes'])]]];
    }
    if ($heading($b['faqs'] ?? null) !== '' && $items($b['faqs']) !== []) {
        $qa = [];
        foreach ($items($b['faqs']) as $i => $item) {
            $qa[] = ['question' => (string) ($item['question'] ?? ''), 'answer' => (string) ($item['answer'] ?? ''), 'id' => $anchor.'-q'.($i + 1)];
        }
        $sections[] = ['heading' => $heading($b['faqs']), 'id' => $anchor.'-questions', 'blocks' => [['type' => 'qa', 'items' => $qa]]];
    }
    if ($heading($b['sources'] ?? null) !== '') {
        $links = array_values(array_filter(array_map(
            fn ($s) => is_array($s) && filled($s['url'] ?? null) ? ['label' => (string) ($s['label'] ?? $s['url']), 'url' => (string) $s['url']] : null,
            $items($b['sources'])
        )));
        $sections[] = ['heading' => $heading($b['sources']), 'id' => $anchor.'-sources', 'blocks' => array_merge(
            (array) ($b['sources']['blocks'] ?? []),
            $links !== [] ? [['type' => 'links', 'items' => $links]] : []
        )];
    }

    // Related links, in the approved order (service, guide, FAQs, consultation, back to the directory).
    $related = [];
    foreach ($items($b['related'] ?? null) as $item) {
        $target = (string) ($item['target'] ?? '');
        $label = trim((string) ($item['label'] ?? ''));
        if ($label === '' || $target === '') {
            continue;
        }
        $related[] = match (true) {
            preg_match('/^S(0[1-9]|1[01])$/', $target) === 1 => ['kind' => 'service', 'code' => $target, 'label' => $label],
            preg_match('/^G0[1-9]$/', $target) === 1 => ['kind' => 'link', 'href' => '#'.config("summit.guides.{$target}"), 'label' => $label],
            $target === 'P07' => ['kind' => 'consult', 'href' => Prefill::url(['service' => $service]), 'label' => $label],
            $target === 'knowledge-top' => ['kind' => 'back', 'href' => '#knowledge-top', 'label' => $label],
            default => ['kind' => 'link', 'href' => SiteUrl::target($target), 'label' => $label],
        };
    }
    $cards = array_values(array_filter($related, fn ($r) => in_array($r['kind'], ['service', 'link'], true)));
    $actions = array_values(array_filter($related, fn ($r) => in_array($r['kind'], ['consult', 'back'], true)));
@endphp
<article {{ $attributes->class(['kc-guide']) }} data-kc-guide="{{ $code }}" data-kc-topic="{{ $guide['category'] ?? '' }}" aria-labelledby="{{ $anchor }}">
    <div class="kc-guide__card">
        <header class="kc-guide__head">
            @if (filled($category))
                <p class="kc-guide__cat">{{ Bidi::isolate($category) }}</p>
            @endif
            <h2 class="kc-guide__title" id="{{ $anchor }}" tabindex="-1">{{ Bidi::isolate(($b['title'] ?? null) ?: ($guide['title'] ?? '')) }}</h2>
        </header>

        {{-- The phone disclosure (shown below 768 px with JavaScript only): named by its label, described by
             the guide's title, so each of the nine reads apart from the others. --}}
        <x-d2.ui.button variant="secondary" size="48" icon-end="chevron-down" :full="true" class="kc-guide__toggle"
            aria-expanded="false" aria-controls="{{ $anchor }}-body" aria-describedby="{{ $anchor }}" data-kc-toggle>
            <span class="kc-guide__toggle-open" @proposed>{{ d2p('resources_page.guide_open') }}</span>
            <span class="kc-guide__toggle-close" @proposed>{{ d2p('resources_page.guide_close') }}</span>
        </x-d2.ui.button>
    </div>

    <div class="kc-guide__body" id="{{ $anchor }}-body" data-kc-body>
        @if (! empty($b['intro']))
            <x-d2.inner.prose :blocks="$b['intro']" size="lg" class="kc-guide__intro" />
        @endif

        @if ($sections !== [])
            <x-d2.inner.prose :sections="$sections" :level="3" :id-prefix="$anchor" class="kc-guide__prose" />
        @endif

        @if ($heading($b['related'] ?? null) !== '' && $related !== [])
            <section class="kc-related" id="{{ $anchor }}-related" aria-labelledby="{{ $anchor }}-related-title">
                <h3 class="kc-related__title" id="{{ $anchor }}-related-title">{{ Bidi::isolate($heading($b['related'])) }}</h3>
                @if ($cards !== [])
                    <ul class="kc-related__list" role="list">
                        @foreach ($cards as $item)
                            @if ($item['kind'] === 'service')
                                <li class="kc-related__item kc-related__item--service">
                                    <x-d2.resources.service-card :code="$item['code']" :label="$item['label']" />
                                </li>
                            @else
                                <li class="kc-related__item">
                                    <a class="kc-link" href="{{ $item['href'] }}">
                                        <span class="kc-link__label">{{ Bidi::isolate($item['label']) }}</span>
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
                @if ($actions !== [])
                    <div class="kc-related__actions">
                        @foreach ($actions as $item)
                            @if ($item['kind'] === 'consult')
                                <x-d2.ui.button variant="ink" :href="$item['href']" class="kc-related__cta">{{ Bidi::isolate($item['label']) }}</x-d2.ui.button>
                            @else
                                <x-d2.ui.button variant="text" :href="$item['href']" class="kc-related__back">{{ Bidi::isolate($item['label']) }}</x-d2.ui.button>
                            @endif
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
</article>
