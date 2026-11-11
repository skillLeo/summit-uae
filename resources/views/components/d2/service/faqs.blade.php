@props([
    'category' => null,   // the FAQ centre category whose approved links include this service (cat-02 → S01 …)
    'items' => [],        // that category's approved FAQ records (code faq-NN, question)
])
{{--
    Related FAQs of a service page (upgrade spec §12 D: "related FAQs (same service category, if the data links
    them)"): the FAQ centre category that links to this service (faq_categories.links target S0n). The approved
    category name is the H3 and links to its section of the FAQ page (#cat-NN, Design 1's anchor); every approved
    question links to its own kept anchor (#faq-NN), which opens it there (accordion-hash.js).
    Design 2.1 (brief §3.6 rules 3 and 5): the heading is a plain text link (no info glyph, no chevron).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $items = array_values(array_filter((array) $items, fn ($f) => is_array($f) && trim((string) ($f['question'] ?? '')) !== ''));
    $anchor = is_array($category) ? (string) ($category['anchor'] ?? $category['code'] ?? '') : '';
@endphp
@if ($items !== [] && $anchor !== '')
    <div {{ $attributes->class(['svc-faqs']) }} data-faq-category="{{ $anchor }}">
        <h3 class="svc-faqs__title">
            <a class="svc-faqs__cat" href="{{ SiteUrl::page('P05', null, $anchor) }}">{{ Bidi::isolate($category['name'] ?? '') }}</a>
        </h3>
        <ul class="svc-faqs__list" role="list">
            @foreach ($items as $faq)
                <li>
                    <a class="svc-faqs__link" href="{{ SiteUrl::page('P05', null, (string) ($faq['anchor'] ?? $faq['code'])) }}">{{ Bidi::isolate($faq['question']) }}</a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
