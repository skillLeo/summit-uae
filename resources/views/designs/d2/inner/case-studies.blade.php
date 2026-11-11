{{--
    Design 2 P04: Case Studies (upgrade spec §7.4 "Case study page", §5.5 "Case study file timeline", §12
    Phase D), rendered by PagePreviewController through d2::pages.preview. One canonical page that carries
    the six full stories as anchored sections case-01 … case-06 (no case routes: 04_Case_Studies designer
    notes; case-01 and case-04 are linked from the home and the service pages), in the approved order:

      head        breadcrumb, H1 #cases-top (every story's "Back to the case study directory" target), the
                  approved subtitle as the lead under the H1 (.pp-lead, the head pattern of P05, P06 and P07) and
                  the first introduction paragraph; the second paragraph (it carries the
                  qualification) is the head's aside, a quiet note panel (Design 2.1: no shield glyph), so the
                  head is balanced from 1280 px like P02 / P03; below 1280 it follows the intro
      directory   "Choose the story closest to your situation" (H2): six <x-d2.case.card> text cards (Design 2.1
                  D46: the story's service as a plain category line, the approved title as the card's one link
                  to its story on this page, #case-0N, card id card-case-0N, and the approved card summary; no
                  numeral, chip, icon or corner chevron); a swipe rail on phones (<x-d2.ui.carousel until="768">)
      files       the six stories (<x-d2.casefile.file>): the service line, title, matter, the story, the
                  outcome with its limits, the takeaway, related service and reading, consultation CTA, back to
                  the directory (no numeral and no stage timeline: D46, brief §3.6 rules 4 and 10; no
                  qualification callout repeating part of the introduction in every story: brief §5.3).
                  Phones with JavaScript: each story starts as its file card with the approved card summary
                  and the "Read the full story" disclosure; the inline script after the files opens the story an address's #fragment names
                  before the browser scrolls to it
      faq         "Questions about the published case studies" (H2, H3 questions) + the FAQ centre link
      contact     "Does your matter need a closer review?" (H2): consultation, WhatsApp (approved draft,
                  opened, never sent), telephone, e-mail, the approved notice
      scope       "Scope of the case studies and services" (H2) with the Legal Notice link

    The qualification ("Identifying details have been removed or condensed … A previous outcome does not
    guarantee a similar result in another matter.") is part of the last P04 introduction paragraph: the head's
    note states it once, complete. Calm editorial design: no success labels, trophies or counters.
    Behaviour (deep links, focus): d2Casefiles; the rail: d2Carousel.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $page = content()->page('P04');
    $c = (array) ($page['content'] ?? []);
    $strings = fn ($v) => array_values(array_filter((array) $v, fn ($p) => is_string($p) && trim($p) !== ''));

    $introBlock = (array) ($c['intro'] ?? []);
    $paragraphs = $strings($introBlock['paragraphs'] ?? []);
    // The head: the approved subtitle as the lead under the H1 (.pp-lead: the one head pattern of P05, P06, P07)
    // and the first paragraph; the last paragraph (the qualification) is the aside note.
    $lead = trim((string) ($introBlock['lead'] ?? ''));
    $headNote = count($paragraphs) > 1 ? end($paragraphs) : null;
    $intro = $headNote !== null ? array_slice($paragraphs, 0, -1) : $paragraphs;

    $cases = content()->all('case_studies');

    $faq = (array) ($c['faq'] ?? []);
    $faqSections = [];
    if (filled($faq['heading'] ?? null)) {
        $qa = [];
        foreach ((array) ($faq['items'] ?? []) as $i => $item) {
            if (filled($item['question'] ?? null)) {
                $qa[] = ['question' => (string) $item['question'], 'answer' => (string) ($item['answer'] ?? ''), 'id' => 'case-faq-'.($i + 1)];
            }
        }
        $faqSections[] = ['heading' => (string) $faq['heading'], 'id' => 'case-faq', 'blocks' => array_merge(
            $qa !== [] ? [['type' => 'qa', 'items' => $qa]] : [],
            filled($faq['link']['label'] ?? null) ? [['type' => 'links', 'items' => [(array) $faq['link']]]] : []
        )];
    }

    $cta = (array) ($c['cta'] ?? []);
    $scope = (array) ($c['scope'] ?? []);
    $scopeSections = filled($scope['heading'] ?? null) ? [[
        'heading' => (string) $scope['heading'],
        'id' => 'case-scope',
        'variant' => 'note',
        'tone' => 'info',
        'blocks' => array_merge(
            $strings([$scope['body'] ?? null]) !== [] ? [['type' => 'p', 'text' => (string) $scope['body']]] : [],
            filled($scope['link']['label'] ?? null) ? [['type' => 'links', 'items' => [(array) $scope['link']]]] : []
        ),
    ]] : [];
@endphp
<div class="cs" x-data="d2Casefiles">
    <x-d2.inner.head :title="$h1" :crumbs="$crumbs" :intro="$intro" title-id="cases-top" class="pp-head cs-head">
        @if ($lead !== '')
            <x-slot:meta><p class="pp-lead">{{ Bidi::isolate($lead) }}</p></x-slot:meta>
        @endif
        @if ($headNote !== null)
            <x-slot:aside>
                <div class="cs-note">
                    <p class="cs-note__text">{{ Bidi::isolate($headNote) }}</p>
                </div>
            </x-slot:aside>
        @endif
    </x-d2.inner.head>

    <section class="section cs-dir" aria-labelledby="cs-dir-title">
        <div class="container">
            <h2 class="cs-dir__title" id="cs-dir-title">{{ Bidi::isolate($c['directory']['heading'] ?? '') }}</h2>
            <x-d2.ui.carousel class="cs-dir__rail" labelledby="cs-dir-title" until="768" list-class="cs-dir__grid">
                @foreach ($cases as $case)
                    <li class="cs-dir__item">
                        <x-d2.case.card :case="$case" :href="'#'.($case['code'] ?? '')" id-prefix="card-" />
                    </li>
                @endforeach
            </x-d2.ui.carousel>
        </div>
    </section>

    <div class="cs-files">
        <div class="container cs-files__list">
            @foreach ($cases as $case)
                <x-d2.casefile.file :case="$case" />
            @endforeach
        </div>
        {{-- Phones with JavaScript start every story as its card (pages/cases.css): an address naming a story or
             an anchor inside it (/xx/case-studies/#case-04) opens that story before the browser's own fragment
             scroll, so it lands on the target. d2Casefiles takes over from here (later links, hashchange). --}}
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            (function () {
                try {
                    var id = decodeURIComponent(window.location.hash.slice(1));
                    var target = id ? document.getElementById(id) : null;
                    var file = target ? target.closest('[data-case-file]') : null;
                    if (!file) return;
                    file.classList.add('is-open');
                    var toggle = file.querySelector('[data-cf-toggle]');
                    if (toggle) toggle.setAttribute('aria-expanded', 'true');
                } catch (e) {}
            })();
        </script>
    </div>

    @if ($faqSections !== [])
        <div class="section cs-faq">
            <div class="container">
                <x-d2.inner.prose :sections="$faqSections" :level="2" id-prefix="case-faq" class="cs-faq__prose" />
            </div>
        </div>
    @endif

    @if (filled($cta['heading'] ?? null))
        <x-d2.resources.contact-panel
            heading-id="cs-contact-title"
            :heading="$cta['heading']"
            :text="$cta['body'] ?? null"
            :cta="['label' => $cta['consultation']['label'] ?? ui('cta.request_consultation'), 'href' => SiteUrl::consultation()]"
            :whatsapp="$cta['whatsapp']['label'] ?? null"
            :phone="$cta['phone']['label'] ?? null"
            :email="$cta['email']['label'] ?? null"
            :notice="$cta['notice'] ?? null" />
    @endif

    @if ($scopeSections !== [])
        <div class="section cs-scope">
            <div class="container">
                <x-d2.inner.prose :sections="$scopeSections" :level="2" id-prefix="case-scope" class="cs-scope__prose inner-scope inner-scope--wide" />
            </div>
        </div>
    @endif
</div>
