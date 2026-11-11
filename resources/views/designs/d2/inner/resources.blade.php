{{--
    Design 2 P03: the Legal Knowledge Centre (upgrade spec §7.4 "Guide page", §5.5 guides, §12 Phase D),
    rendered by PagePreviewController through d2::pages.preview. One canonical page that carries all nine
    complete guides as anchored sections (no guide routes: 03_Knowledge_Centre designer notes), in the
    approved DOCX order:

      head        breadcrumb, H1 #knowledge-top (every guide's "Back to the Guide Directory" target), the
                  approved lead and introduction; "How to Use the Knowledge Centre" (H2) beside it ≥ 1280
      finder      "Find the Topic That Matters to You" (H2): approved search + topic controls (JS only)
      directory   "Legal Guides" (H2): the approved categories (H3) with their guide cards (the cards area's
                  <x-d2.guide.card>), each linking to its guide anchor
      help        "Need a Specific Answer?" (H2) with the FAQ and Practice Areas links
      guides      the nine guides (<x-d2.resources.article>, H2 = registered anchor) in the inner kit's
                  reading layout: the sticky "On this page" list of the nine guides from 1280 px, the bar
                  with the guide in view + the contents sheet below (<x-d2.inner.layout :toc>, scroll-spy in
                  modules/toc.js), the 3 px ink reading progress bar under the header (<x-d2.inner.progress>).
                  Below 768 px (with JavaScript) each guide starts as a compact card (category and title: the
                  directory above already shows every summary, so nothing is shown twice)
                  with a proposed "Read the full guide" disclosure that opens it in place; a
                  link to a guide or to any anchor inside it opens that guide first (the inline script below
                  for the address on arrival, d2Resources afterwards). Without JavaScript every guide is open
      contact     "From Reading a Guide to Reviewing Your Situation" (H2): consultation, WhatsApp
                  (approved draft, opened, never sent), telephone, e-mail, the approved notice
      scope       "Scope of Information and Services" (H2) with the Legal Notice link

    Behaviour: search filter, deep links and the phone rails in d2Resources (resources/js/d2/components/
    resources.js, which also hides the contents entries of filtered guides); contents and progress in the
    inner kit's modules. Without JavaScript every guide, card and anchor works as plain HTML.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;
    use Illuminate\Support\HtmlString;

    $page = content()->page('P03');
    $c = (array) ($page['content'] ?? []);

    // Categories in the approved order, each with its guide records.
    $groups = [];
    foreach (content()->guideCategories() as $category) {
        $members = [];
        foreach ((array) ($category['guides'] ?? []) as $code) {
            $guide = content()->guide((string) $code);
            if ($guide !== null) {
                $members[] = $guide;
            }
        }
        if ($members !== []) {
            $groups[] = ['code' => (string) $category['code'], 'name' => (string) ($category['name'] ?? ''), 'guides' => $members];
        }
    }
    $categoryNames = array_column($groups, 'name', 'code');
    // The complete guides in the approved order (position); a guide outside every category still renders.
    $articles = content()->guides();

    // "Back to the Guide Directory" (the same approved label on every guide; the first one is used).
    $backLabel = null;
    foreach ($articles as $guide) {
        foreach ((array) ($guide['body']['related']['items'] ?? []) as $item) {
            if (($item['target'] ?? null) === 'knowledge-top' && filled($item['label'] ?? null)) {
                $backLabel = (string) $item['label'];
                break 2;
            }
        }
    }

    // Contents (inner kit TOC): the nine guides, each linking to its registered anchor (its H2).
    $toc = array_map(fn ($guide) => [
        'id' => (string) (($guide['anchor'] ?? null) ?: config('summit.guides.'.$guide['code'])),
        'label' => (string) (($guide['card_title'] ?? null) ?: ($guide['title'] ?? '')),
    ], $articles);

    $howTo = (array) ($c['how_to'] ?? []);
    $howToItems = array_values(array_filter((array) ($howTo['items'] ?? []), fn ($i) => is_string($i) && trim($i) !== ''));
    $help = (array) ($c['faq_services'] ?? []);
    $consultation = (array) ($c['consultation'] ?? []);
    $scope = (array) ($c['scope'] ?? []);
    // "Scope of Information and Services": the inner kit's note section (callout with the H2) + the Legal Notice link.
    $scopeSections = filled($scope['heading'] ?? null) ? [[
        'heading' => (string) $scope['heading'],
        'id' => 'kc-scope',
        'variant' => 'note',
        'tone' => 'info',
        'blocks' => array_merge(
            array_map(fn ($p) => ['type' => 'p', 'text' => $p], array_values(array_filter((array) ($scope['paragraphs'] ?? []), fn ($p) => is_string($p) && trim($p) !== ''))),
            filled($scope['link']['label'] ?? null) ? [['type' => 'links', 'items' => [(array) $scope['link']]]] : []
        ),
    ]] : [];
    $count = fn (int $n) => new HtmlString(str_replace('__N__', '<bdi>'.$n.'</bdi>', e(trans_choice('proposed.d2.resources_page.category_count', $n, ['count' => '__N__']))));
@endphp
<div class="kc" x-data="d2Resources">
    <x-d2.inner.head :title="$h1" :crumbs="$crumbs" :intro="[$c['lead'] ?? null, $c['intro'] ?? null]" title-id="knowledge-top" class="kc-head inner-head--rail">
        @if (filled($howTo['heading'] ?? null) && $howToItems !== [])
            <x-slot:aside>
                <section class="kc-howto" aria-labelledby="kc-howto-title">
                    <h2 class="kc-howto__title" id="kc-howto-title">{{ Bidi::isolate($howTo['heading']) }}</h2>
                    <ul class="kc-howto__list" role="list">
                        @foreach ($howToItems as $item)
                            <li class="kc-howto__item">{{ Bidi::isolate($item) }}</li>
                        @endforeach
                    </ul>
                </section>
            </x-slot:aside>
        @endif
    </x-d2.inner.head>

    @if (filled($c['find']['heading'] ?? null))
        <x-d2.resources.finder :heading="$c['find']['heading']" :groups="$groups" />
    @endif

    <section class="section kc-dir" aria-labelledby="kc-dir-title" data-kc-directory>
        <div class="container">
            <h2 class="kc-dir__title" id="kc-dir-title">{{ Bidi::isolate($c['directory']['heading'] ?? '') }}</h2>
            @foreach ($groups as $group)
                <div class="kc-dir__group" data-kc-group="{{ $group['code'] }}">
                    <div class="kc-dir__label">
                        <h3 class="kc-dir__name" id="kc-cat-{{ $group['code'] }}">{{ Bidi::isolate($group['name']) }}</h3>
                        <p class="kc-dir__count" @proposed>{{ $count(count($group['guides'])) }}</p>
                    </div>
                    <div class="kc-dir__rail">
                        <ul class="kc-dir__grid" role="list" aria-labelledby="kc-cat-{{ $group['code'] }}" data-kc-rail>
                            @foreach ($group['guides'] as $guide)
                                <li class="kc-dir__item" data-kc-card="{{ $guide['code'] }}">
                                    <x-d2.guide.card :guide="$guide" copy="card" category="" :level="4" :href="'#'.(($guide['anchor'] ?? null) ?: config('summit.guides.'.$guide['code']))" id-prefix="kc-" />
                                </li>
                            @endforeach
                        </ul>
                        {{-- One dot per card, server-rendered so the row is there from the first paint (phones, html.js);
                             d2Resources (resources-kit.js rail()) marks the card in view and rebuilds them after a filter. --}}
                        <div class="kc-dir__dots" aria-hidden="true" data-kc-dots>@if (count($group['guides']) > 1)@foreach ($group['guides'] as $i => $member)<span @class(['kc-dir__dot', 'is-active' => $i === 0])></span>@endforeach @endif</div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    @if (filled($help['heading'] ?? null))
        <section class="section kc-help" aria-labelledby="kc-help-title" data-kc-help>
            <div class="container">
                <div class="kc-help__panel">
                    <div class="kc-help__text">
                        <h2 class="kc-help__title" id="kc-help-title">{{ Bidi::isolate($help['heading']) }}</h2>
                        @if (filled($help['text'] ?? null))
                            <p class="kc-help__lead">{{ Bidi::isolate($help['text']) }}</p>
                        @endif
                    </div>
                    @if (! empty($help['links']))
                        <div class="kc-help__links">
                            @foreach ((array) $help['links'] as $link)
                                @continue(! filled($link['label'] ?? null) || ! filled($link['target'] ?? null))
                                <x-d2.ui.button variant="secondary" :href="SiteUrl::target((string) $link['target'])">{{ Bidi::isolate((string) $link['label']) }}</x-d2.ui.button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <x-d2.inner.progress target="kc-guides" />
    <x-d2.inner.layout :toc="$toc" toc-prefix="kc-guide" main-id="kc-guides" class="kc-read" data-kc-read>
        @foreach ($articles as $guide)
            <x-d2.resources.article :guide="$guide" :category="$categoryNames[$guide['category'] ?? ''] ?? null" />
        @endforeach
        {{-- Phones start each guide as its card (resources.css, html.js). An address naming a guide or an anchor
             inside one (/xx/resources/#unpaid-salary-steps) opens that guide here, before the first paint, so the
             browser's own fragment scroll lands on the target; d2Resources handles every later link. --}}
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            (function () {
                try {
                    var id = decodeURIComponent(window.location.hash.slice(1));
                    var target = id ? document.getElementById(id) : null;
                    var guide = target ? target.closest('[data-kc-guide]') : null;
                    if (!guide) return;
                    guide.classList.add('is-open');
                    var toggle = guide.querySelector('[data-kc-toggle]');
                    if (toggle) toggle.setAttribute('aria-expanded', 'true');
                } catch (e) {}
            })();
        </script>
    </x-d2.inner.layout>

    @if (filled($consultation['heading'] ?? null))
        <x-d2.resources.contact-panel
            heading-id="kc-contact-title"
            :heading="$consultation['heading']"
            :text="$consultation['text'] ?? null"
            :cta="['label' => $consultation['cta']['label'] ?? ui('cta.request_consultation'), 'href' => SiteUrl::consultation()]"
            :whatsapp="$consultation['whatsapp']['label'] ?? null"
            :phone="$consultation['phone']['label'] ?? null"
            :email="$consultation['email']['label'] ?? null"
            :notice="$consultation['notice'] ?? null" />
    @endif

    @if ($scopeSections !== [])
        <div class="section kc-scope">
            <div class="container">
                <x-d2.inner.prose :sections="$scopeSections" :level="2" id-prefix="kc-scope" class="kc-scope__prose inner-scope inner-scope--wide" />
            </div>
        </div>
    @endif
</div>

