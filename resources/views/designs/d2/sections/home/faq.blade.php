{{--
    Design 2 home section 08: "Questions Before Requesting a Consultation" (shared contract A: id="questions").
    The homepage file (§5.1 item 9, §5.2; its Section 08 note: "Use accessible accordions with visible H3
    questions and complete readable answers. Keep text in HTML."): the approved H2, the eight approved questions
    as an accordion with H3 questions and complete answers in the HTML, then "View All FAQs" (P05). Nothing else:
    no category tabs and no search here (the FAQ page holds the twelve categories and the search).

    The questions keep their Design 1 anchors home-faq-1 … home-faq-8 (a link to /en/#home-faq-3 opens that
    question: accordion-hash.js). Arabic: Latin runs are isolated (Bidi::isolate).
    Design 2.1: no arrow on "View All FAQs" (D03).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $faq = ($c ?? content()->page('P01')['content'] ?? [])['faq'] ?? [];
    $items = array_values(array_filter((array) ($faq['items'] ?? []), fn ($item) => is_array($item) && trim((string) ($item['question'] ?? '')) !== ''));
@endphp
<section class="section faq-section" id="questions" aria-labelledby="faq-title">
    <div class="container">
        <div class="section__head">
            <h2 id="faq-title">{{ $faq['heading'] ?? '' }}</h2>
        </div>

        @if ($items !== [])
            <div class="faq-section__list">
                <x-d2.ui.accordion level="3" class="faq-list">
                    @foreach ($items as $i => $item)
                        <x-d2.ui.accordion-item :id="'home-faq-'.($i + 1)" :title="Bidi::isolate((string) $item['question'])">
                            @foreach (array_filter((array) ($item['answer'] ?? []), fn ($p) => is_string($p) && trim($p) !== '') as $paragraph)
                                <p>{{ Bidi::isolate($paragraph) }}</p>
                            @endforeach
                        </x-d2.ui.accordion-item>
                    @endforeach
                </x-d2.ui.accordion>
            </div>
        @endif

        <div class="faq-section__foot">
            <x-d2.ui.button variant="secondary" :href="SiteUrl::page('P05')">{{ ui('cta.view_all_faqs') }}</x-d2.ui.button>
        </div>
    </div>
</section>
