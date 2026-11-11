{{--
    Design 2 FAQ page (P05; upgrade spec §7.4 "FAQ page: search + categories + deep links (anchors kept)",
    §12 Phase D). Rendered by PagePreviewController ($h1, $crumbs; noindex until Phase 2 publishes it).
    Approved P05 content only, in the DOCX order:

      head        breadcrumb, H1, the approved lead and both hero paragraphs, the hero CTA (target P07: the
                  home form until contact_page_released; the page's one gold action, the hero primary of brief
                  §3.5). Aside (≥ 1280 beside the text, below it before): the approved category menu "Choose a
                  topic" (#faq-topics): its twelve labels link to #cat-01 … #cat-12, each with the number of
                  questions (proposed). Design 2.1: no practice-area glyph and no chevron on the rows, no
                  arrows on buttons (brief §3.6 rules 3 and 5).
      #faq-search "Find your question" (approved H2) and the FAQ browser (<x-d2.faq.browser>, TEAM 1): the
                  local search, the twelve category tabs (panels cat-01 … cat-12) and all 74 approved
                  questions with their anchors faq-01 … faq-74, "Copy question link" and "Show all answers".
                  /xx/faq/#faq-17 selects the tab and opens that answer (d2Tabs + accordion-hash.js);
                  #cat-07 selects the tab. Without JavaScript every category shows, the tabs are jump links.
                  Then the approved "Back to FAQ categories" link to the topic index.
      #faq-ask    "Can't find an answer to your question?": both paragraphs, the consultation CTA, WhatsApp
                  (approved per-language draft, opened never sent), telephone and e-mail (the kit's contact
                  channels, <x-d2.inner.channels>).
      #faq-scope  "Limits of the Answers and Scope of Services": the paragraph and the three approved links.
    Styles: resources/css/d2/pages/faq.css (+ the shared people-pages kit in pages/about.css).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $c = content()->page('P05')['content'] ?? [];
    $hero = $c['hero'] ?? [];
    $lead = is_string($hero['lead'] ?? null) ? trim($hero['lead']) : '';
    $paragraphs = array_values(array_filter((array) ($hero['paragraphs'] ?? []), fn ($p) => is_string($p) && trim($p) !== ''));
    $heroCta = is_array($hero['cta'] ?? null) ? $hero['cta'] : [];

    // Topic index: the approved category menu with each category's number of approved questions.
    $categories = collect(content()->faqCategories())->keyBy(fn ($cat) => (string) ($cat['anchor'] ?? $cat['code'] ?? ''));
    $counts = collect(content()->faqs())->countBy(fn ($faq) => (string) ($faq['category'] ?? ''));
    $menu = $c['category_menu'] ?? [];
    $topics = [];
    foreach ((array) ($menu['items'] ?? []) as $item) {
        $anchor = (string) ($item['target'] ?? '');
        if (preg_match('/^cat-\d{2}$/', $anchor) !== 1 || ! $categories->has($anchor)) {
            continue;
        }
        $topics[] = [
            'anchor' => $anchor,
            'label' => (string) ($item['label'] ?? $categories[$anchor]['name'] ?? ''),
            'count' => (int) ($counts[$anchor] ?? 0),
        ];
    }
    // "6 questions": the number isolated, so it keeps its place in Arabic sentences.
    $countText = function (int $n) {
        $text = e(trans_choice('proposed.d2.faq_page.topic_count', $n, ['count' => '%%n%%']));

        return new \Illuminate\Support\HtmlString(str_replace('%%n%%', '<bdi class="t-num">'.$n.'</bdi>', $text));
    };

    $ask = $c['consultation'] ?? [];
    $askParagraphs = array_values(array_filter((array) ($ask['paragraphs'] ?? []), fn ($p) => is_string($p) && trim($p) !== ''));
    $scope = $c['scope'] ?? [];
    $scopeParagraphs = array_values(array_filter((array) ($scope['paragraphs'] ?? []), fn ($p) => is_string($p) && trim($p) !== ''));
    $scopeLinks = array_values(array_filter((array) ($scope['links'] ?? []), fn ($l) => is_array($l) && trim((string) ($l['label'] ?? '')) !== ''));
@endphp

<x-d2.inner.head :title="$h1" :crumbs="$crumbs" :intro="$paragraphs" class="pp-head faq-head">
    @if ($lead !== '')
        <x-slot:meta><p class="pp-lead">{{ Bidi::isolate($lead) }}</p></x-slot:meta>
    @endif
    @if (! empty($heroCta['label']))
        <x-slot:actions>
            <x-d2.ui.button variant="gold" size="52" :href="SiteUrl::target((string) ($heroCta['target'] ?? 'P07'))">{{ $heroCta['label'] }}</x-d2.ui.button>
        </x-slot:actions>
    @endif
    @if ($topics !== [])
        <x-slot:aside>
            <nav class="faq-topics" id="faq-topics" aria-labelledby="faq-topics-title" tabindex="-1">
                <h2 class="faq-topics__title" id="faq-topics-title">{{ $menu['heading'] ?? '' }}</h2>
                <ul class="faq-topics__list" role="list">
                    @foreach ($topics as $topic)
                        <li>
                            <a class="faq-topic" href="#{{ $topic['anchor'] }}" data-faq-topic="{{ $topic['anchor'] }}">
                                <span class="faq-topic__label">{{ Bidi::isolate($topic['label']) }}</span>
                                <span class="faq-topic__count" @proposed>{{ $countText($topic['count']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </x-slot:aside>
    @endif
</x-d2.inner.head>

<section class="section faq-browse" id="faq-search" aria-labelledby="faq-search-title">
    <div class="container">
        <div class="section__head faq-browse__head">
            <h2 id="faq-search-title">{{ $c['search']['heading'] ?? '' }}</h2>
        </div>

        {{-- The FAQ file's note 7: twelve category H2 headings and question H3 headings. --}}
        <x-d2.faq.browser id="faq-browser" :panel-level="2" :question-level="3" :copy-links="true" :expand-all="true" :picker-label="$menu['heading'] ?? null" />

        <p class="faq-browse__back">
            <x-d2.ui.button variant="text" size="48" href="#faq-topics">{{ ui('faq.back_to_categories') }}</x-d2.ui.button>
        </p>
    </div>
</section>

@if (! empty($ask['heading']))
    <section class="section pp-band" id="faq-ask" aria-labelledby="faq-ask-title">
        <div class="container">
            <div class="pp-cta">
                <div class="pp-cta__text">
                    <h2 class="pp-cta__title" id="faq-ask-title">{{ Bidi::isolate($ask['heading']) }}</h2>
                    @foreach ($askParagraphs as $paragraph)
                        <p>{{ Bidi::isolate($paragraph) }}</p>
                    @endforeach
                </div>
                <div class="pp-cta__actions">
                    <div class="pp-cta__buttons">
                        @if (! empty($ask['cta']['label']))
                            <x-d2.ui.button variant="ink" size="48" :full="true" :href="SiteUrl::target((string) ($ask['cta']['target'] ?? 'P07'))">{{ $ask['cta']['label'] }}</x-d2.ui.button>
                        @endif
                        @if (! empty($ask['whatsapp']['label']))
                            <x-d2.ui.button variant="whatsapp" size="48" :full="true" :href="setting('whatsapp_url')">{{ $ask['whatsapp']['label'] }}</x-d2.ui.button>
                        @endif
                    </div>
                    {{-- The approved telephone and e-mail labels (the number and the address) as the kit's contact channels. --}}
                    <x-d2.inner.channels class="pp-cta__channels" :items="[
                        ['kind' => 'phone', 'href' => (string) setting('phone_href'), 'text' => (string) ($ask['phone']['label'] ?? '')],
                        ['kind' => 'email', 'href' => (string) setting('email_href'), 'text' => (string) ($ask['email']['label'] ?? '')],
                    ]" />
                </div>
            </div>
        </div>
    </section>
@endif

@if (! empty($scope['heading']))
    <section class="section pp-scope-band" id="faq-scope" aria-labelledby="faq-scope-title">
        <div class="container">
            <div class="pp-scope">
                <div class="pp-scope__text">
                    <div class="pp-scope__body">
                        <h2 class="pp-scope__title" id="faq-scope-title">{{ Bidi::isolate($scope['heading']) }}</h2>
                        @foreach ($scopeParagraphs as $paragraph)
                            <p>{{ Bidi::isolate($paragraph) }}</p>
                        @endforeach
                    </div>
                </div>
                @if ($scopeLinks !== [])
                    <ul class="pp-links pp-links--grid" role="list">
                        @foreach ($scopeLinks as $link)
                            @php $target = (string) ($link['target'] ?? ''); @endphp
                            <li>
                                <a class="link-row link-row--page" href="{{ SiteUrl::target($target) }}">
                                    <span class="link-row__body"><span class="link-row__label">{{ Bidi::isolate($link['label']) }}</span></span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>
@endif
