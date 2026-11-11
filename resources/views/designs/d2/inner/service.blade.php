{{--
    Design 2 service page S01–S11 (route practice-areas/{service}/, PagePreviewController@service → d2::pages.preview).
    Upgrade spec §12 D, §7.4, §8. Content and order from 02_S0n_*.docx (ServicePagesMapper), built on the TEAM 2
    inner kit (<x-d2.inner.head | layout | toc | prose | related | cta-card>, pages/inner.css) and this page's
    own components (<x-d2.service.*>, pages/service.css):

    head           breadcrumb · approved H1 + intro · approved CTAs (consultation prefilled with the service
                   through Prefill::url, the page's one gold action: the hero primary, brief §3.5; WhatsApp with
                   the service's approved draft). Design 2.1: no service icon object (brief §3.6 rule 5), no
                   large-title collapse, no aside: the document checklist sat there before "Who This Service Is
                   For", out of the DOCX order (brief §5.3), and now follows "How We Handle Your Matter".
    reading column (720 px, DOCX order) "On this page" bar + sheet (< 1280 px, the page is long) · who it is
                   for · matters covered · practical situations (H3) · how we handle your matter (numbered steps)
                   · documents that help us get started (<x-d2.service.documents>: approved H2, the four
                   documents as one panel of rows, the approved note)
                   · before taking the next step (useful first steps | mistakes to avoid) · the related Matter
                   Navigator situations (proposed H2 "Start with Your Situation" over the approved "This service may
                   be relevant if:", as on P02) · FAQs (accordion, full answers
                   in the HTML, ids s01-q01 …) + the related FAQ centre category · related topics and services ·
                   official sources + the specialist site (new tab) · final consultation CTA · scope notice
    side (≥ 1280)  one sticky rail capped to the window: the kit's "On this page" list (scroll-spy; it scrolls
                   inside the rail when the window is short) above the kit's CTA card (approved heading and actions)
    phone (< 768)  the mobile shell's sticky service CTA bar (<x-d2.shell.sticky-cta>) above the tab bar, after the head

    Data: $serviceCode (d2::pages.preview), $h1, $crumbs (controller); everything else from content().
--}}
@php
    use App\Design\D2\Prefill;

    $code = (string) $serviceCode;
    $service = content()->service($code) ?? [];
    $s = $service['sections'] ?? [];
    $consult = Prefill::url(['service' => $code]);
    $draft = trim((string) ($service['whatsapp_draft'] ?? ''));
    $whatsapp = $draft !== ''
        ? 'https://wa.me/'.config('summit.contact.whatsapp_number').'?text='.rawurlencode($draft)
        : (string) setting('whatsapp_url');
    $cta = $s['cta'] ?? [];
    $situations = array_values(array_filter((array) ($service['situations'] ?? []), 'is_string'));
    $faqItems = array_values(array_filter((array) ($s['faq']['items'] ?? []), 'is_array'));

    // FAQ centre category that links to this service (cat-02 → S01 …) and its approved questions.
    $faqCategory = null;
    foreach (content()->faqCategories() as $category) {
        foreach ((array) ($category['links'] ?? []) as $link) {
            if (is_array($link) && ($link['target'] ?? null) === $code) {
                $faqCategory = $category;
                break 2;
            }
        }
    }
    $faqAnchor = $faqCategory ? (string) ($faqCategory['anchor'] ?? $faqCategory['code'] ?? '') : '';
    $relatedFaqs = $faqAnchor !== ''
        ? array_values(array_filter(content()->faqs(), fn ($faq) => ($faq['category'] ?? null) === $faqAnchor))
        : [];

    $specialistSite = ! empty($service['specialist']) ? content()->record('specialist_sites', (string) $service['specialist']) : null;

    // Approved related links → the kit's link rows: the destination's approved navigation label as the line
    // under each label. The FAQ centre (P05) and the hub (P02) get no type line: it would repeat their own label
    // ("All FAQs / FAQs", "Back to Practice Areas / Practice Areas"). Design 2.1: no object icon on a row.
    $related = [];
    foreach ((array) ($s['related']['links'] ?? []) as $link) {
        $target = (string) ($link['target'] ?? '');
        $type = match (true) {
            (bool) preg_match('/^S(0[1-9]|1[01])$/', $target) => ui('nav.practice_areas'),
            (bool) preg_match('/^G0[1-9]$/', $target), $target === 'P03' => ui('nav.resources'),
            (bool) preg_match('/^(A0[34]|case-\d{2})$/', $target), $target === 'P04' => ui('nav.case_studies'),
            in_array($target, ['P06', 'A02'], true) => ui('nav.about'),
            default => null,
        };
        $label = (string) ($link['label'] ?? '');
        // Never a type line that repeats the row's own label (e.g. the Case Studies row in Arabic, "Case Studies / Case Studies").
        if (is_string($type) && trim($type) !== '' && mb_stripos($label, trim($type)) !== false) {
            $type = null;
        }
        $related[] = ['label' => $label, 'target' => $target, 'text' => $type];
    }

    // The DOCX prose, in DOCX order, in the inner kit's canonical shape (ids = the TOC anchors).
    $proseTop = array_values(array_filter([
        ! empty($s['who_for']['items']) ? ['id' => 'who-for', 'heading' => $s['who_for']['heading'] ?? null, 'items' => $s['who_for']['items']] : null,
        ! empty($s['matters']['items']) ? ['id' => 'matters', 'heading' => $s['matters']['heading'] ?? null, 'items' => $s['matters']['items']] : null,
        ! empty($s['practical']['items']) ? ['id' => 'practical', 'heading' => $s['practical']['heading'] ?? null, 'items' => $s['practical']['items']] : null,
        ! empty($s['process']['steps']) ? ['id' => 'process', 'heading' => $s['process']['heading'] ?? null, 'intro' => $s['process']['intro'] ?? null, 'steps' => $s['process']['steps']] : null,
    ]));
    $proseFaq = $faqItems !== [] ? [[
        'id' => 'faq',
        'heading' => $s['faq']['heading'] ?? null,
        'blocks' => [['type' => 'qa', 'items' => array_map(fn ($faq) => [
            'question' => (string) ($faq['question'] ?? ''),
            'answer' => (string) ($faq['answer'] ?? ''),
            'id' => strtolower((string) ($faq['id'] ?? '')) ?: null,
        ], $faqItems)]],
    ]] : [];
    $proseScope = ! empty($s['scope']['heading']) ? [[
        'id' => 'scope',
        'heading' => $s['scope']['heading'],
        'variant' => 'note',
        'tone' => 'info',
        'blocks' => array_merge(
            array_map(fn ($p) => ['type' => 'p', 'text' => $p], array_values(array_filter((array) ($s['scope']['paragraphs'] ?? []), 'is_string'))),
            ! empty($s['scope']['link_label']) ? [['type' => 'links', 'items' => [['label' => $s['scope']['link_label'], 'target' => 'P08']]]] : [],
        ),
    ]] : [];

    $documents = array_values(array_filter((array) ($service['documents'] ?? []), fn ($doc) => is_string($doc) && trim($doc) !== ''));
    $documentsHeading = (string) ($s['documents']['heading'] ?? '');

    // "On this page": every H2 of the reading column, in page order (approved headings).
    $toc = array_values(array_filter([
        ...array_map(fn ($section) => ['id' => $section['id'], 'label' => $section['heading']], $proseTop),
        $documents !== [] ? ['id' => 'documents', 'label' => $documentsHeading] : null,
        ! empty($service['first_steps']) || ! empty($service['mistakes']) ? ['id' => 'next-steps', 'label' => $s['next_step']['heading'] ?? null] : null,
        $situations !== [] ? ['id' => 'situations', 'label' => d2p('service_page.situations_heading'), 'proposed' => true] : null,
        $proseFaq !== [] ? ['id' => 'faq', 'label' => $s['faq']['heading'] ?? null] : null,
        $related !== [] ? ['id' => 'related', 'label' => $s['related']['heading'] ?? null] : null,
        ! empty($s['sources']['links']) || $specialistSite ? ['id' => 'sources', 'label' => $s['sources']['heading'] ?? null] : null,
        ! empty($cta['heading']) ? ['id' => 'request', 'label' => $cta['heading']] : null,
        $proseScope !== [] ? ['id' => 'scope', 'label' => $s['scope']['heading']] : null,
    ], fn ($item) => is_array($item) && is_string($item['label'] ?? null) && trim($item['label']) !== ''));
    // A short page needs no contents.
    $hasToc = count($toc) > 3;
@endphp

<div class="svc-page" x-data="d2ServicePage" data-service="{{ $code }}">
    <x-d2.inner.head size="document" class="svc-head" id="service-head" :title="$h1" :crumbs="$crumbs" :intro="$service['intro'] ?? []" title-id="page-title">
        <x-slot:actions>
            <x-d2.ui.button variant="gold" size="52" :href="$consult" :data-prefill-service="$code">{{ $cta['request_label'] ?? ui('cta.request_consultation') }}</x-d2.ui.button>
            <x-d2.ui.button variant="whatsapp" size="52" :href="$whatsapp">{{ $cta['whatsapp_label'] ?? ui('cta.whatsapp') }}</x-d2.ui.button>
        </x-slot:actions>
    </x-d2.inner.head>

    {{-- Mobile shell (Phase E): the sticky service CTA bar above the tab bar (< 768 px, JS), shown once the head has
         scrolled away and hidden again at the page's own final CTA (#request); prefilled with the service. --}}
    <x-d2.shell.sticky-cta target="#service-head" until="#request" :prefill="['service' => $code]" :label="$cta['request_label'] ?? null" />

    {{-- The kit places the contents twice: the "On this page" bar + sheet at the top of the reading column (< 1280 px)
         and the sticky list at the top of the side rail (≥ 1280 px), above the CTA card; the rail is capped to the
         window and the list scrolls inside it (inner.css), so the card always shows in full. --}}
    <x-d2.inner.layout :toc="$hasToc ? $toc : null" :class="$hasToc ? 'svc-layout' : 'inner-layout--toc svc-layout'" main-id="service-content">
        <div class="svc-flow">
            @if ($proseTop !== [])
                <x-d2.inner.prose :sections="$proseTop" class="svc-prose" />
            @endif

            @if ($documents !== [])
                {{-- DOCX order: the document checklist follows "How We Handle Your Matter". --}}
                <x-d2.service.documents id="documents" :heading="$documentsHeading" :documents="$documents" :note="$service['documents_note'] ?? null" />
            @endif

            @if (! empty($service['first_steps']) || ! empty($service['mistakes']))
                <x-d2.service.section id="next-steps" :heading="$s['next_step']['heading'] ?? ''">
                    <div class="svc-next">
                        {{-- Design 2.1: the headings carry no icon (brief §3.6 rule 5); the check and cross marks stay on the
                             items (useful steps, mistakes to avoid). --}}
                        @if (! empty($service['first_steps']))
                            <div class="svc-next__card svc-next__card--do">
                                <h3 class="svc-next__title">{{ \App\Design\D2\Bidi::isolate($s['next_step']['first_steps_heading'] ?? '') }}</h3>
                                <x-d2.service.list :items="$service['first_steps']" icon="check" tone="success" />
                            </div>
                        @endif
                        @if (! empty($service['mistakes']))
                            <div class="svc-next__card svc-next__card--avoid">
                                <h3 class="svc-next__title">{{ \App\Design\D2\Bidi::isolate($s['next_step']['mistakes_heading'] ?? '') }}</h3>
                                <x-d2.service.list :items="$service['mistakes']" icon="close" tone="danger" />
                            </div>
                        @endif
                    </div>
                    {{-- Calculators area (brief §5.1): S06 links the gratuity estimator, S05 the rent increase check
                         (App\Design\D2\Calculators::SERVICES; only while pending copy is shown). --}}
                    @if ($calculator = \App\Design\D2\Calculators::forService($code))
                        <x-d2.tool.link :tool="$calculator" />
                    @endif
                </x-d2.service.section>
            @endif

            @if ($situations !== [])
                {{-- A proposed H2 (a heading and a contents entry never end in a colon); the approved lead-in stays under it. --}}
                <x-d2.service.section id="situations" :heading="d2p('service_page.situations_heading')" :heading-proposed="true" :intro="ui('service.situations_intro')">
                    <x-d2.service.situations :code="$code" :situations="$situations" />
                </x-d2.service.section>
            @endif

            @if ($proseFaq !== [])
                <div class="svc-faq-group">
                    <x-d2.inner.prose :sections="$proseFaq" class="svc-prose svc-prose--faq" />
                    <x-d2.service.faqs :category="$faqCategory" :items="$relatedFaqs" />
                </div>
            @endif

            @if ($related !== [])
                <x-d2.inner.related id="related" class="svc-related" :heading="$s['related']['heading'] ?? null" :items="$related" variant="links" />
            @endif

            <x-d2.service.sources id="sources" :heading="$s['sources']['heading'] ?? null" :links="$s['sources']['links'] ?? []"
                :specialist="$s['specialist'] ?? null" :site="$specialistSite" />

            @if (! empty($cta['heading']))
                <x-d2.service.cta id="request" :cta="$cta" :consult="$consult" :whatsapp="$whatsapp" :service="$code" />
            @endif

            @if ($proseScope !== [])
                <x-d2.inner.prose :sections="$proseScope" class="svc-prose svc-prose--scope inner-scope" />
            @endif
        </div>

        <x-slot:aside>
            {{-- Beside the contents the card keeps the approved heading and the two actions (the telephone and the
                 e-mail are in the final panel #request and the footer, the hours with their live status in the footer);
                 without contents it is the full card. Its title is a label, not a second H2: the final panel #request
                 carries the approved heading "Tell Us About Your Matter" (heading navigation lists it once). --}}
            <x-d2.inner.cta-card id="svc-cta-card" class="svc-side" :heading="$cta['heading'] ?? null" :heading-level="0" :service="$code"
                :request-label="$cta['request_label'] ?? null" :whatsapp-label="$cta['whatsapp_label'] ?? null"
                :whatsapp-draft="$draft !== '' ? $draft : null" :phone="! $hasToc" :email="! $hasToc" :hours="! $hasToc" />
        </x-slot:aside>
    </x-d2.inner.layout>
</div>
