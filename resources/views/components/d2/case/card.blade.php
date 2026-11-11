@props([
    'case',                   // content()->caseStudy() record
    'summary' => null,        // the card's paragraph (default: the approved P04 directory card summary, card_summary)
    'service' => null,        // service code of the category line (default: the first service in the case's related links; '' = none)
    'href' => null,           // default: the full story (SiteUrl::caseStudy); P04 passes the in-page #case-0N
    'level' => 3,
    'idPrefix' => 'card-',    // card id = {prefix}{case code} (P04: #card-case-0N)
])
{{--
    <x-d2.case.card :case="$case" href="#case-01" />                                   (P04 directory)
    A case-study directory card of the Case Studies page (P04 "Six story directory cards": "Each black title is a
    real link to the matching full story on this page; its following paragraph is the card summary"). A TEXT card
    (Design 2.1 D46, the pattern of the home's guide cards): the story's service as a plain category line, the
    approved title as the card's ONE link (stretched over the card: one link, one accessible name), and the approved
    card summary in full. No decorative numeral, no chip, no icon, no timeline, no badge, no corner chevron (D46;
    brief §3.6 rules 3, 4, 5, 10).
    The category prints the shorter of the service's two approved names, the practice-area title or the consultation
    form's short label (ui form.matter_type.option.Sxx), e.g. "Cryptocurrencies" for "Cryptocurrency and Digital
    Currency Cases": both are approved copy, printed unchanged. Source order: title, category, summary (the heading
    first for assistive technology); the category is drawn above the title. No outcome is stated on the card.
    Styles: sections/cases.css (.case-card), the P04 grid in pages/cases.css.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $code = (string) ($case['code'] ?? '');
    $title = (string) (($case['card_title'] ?? null) ?: ($case['title'] ?? ''));
    $summary = trim((string) ($summary ?? ($case['card_summary'] ?? '')));
    if ($service === null) {
        foreach ((array) ($case['body']['related']['links'] ?? []) as $link) {
            $target = is_array($link) ? ($link['target'] ?? null) : null;
            if (is_string($target) && preg_match('/^S(0[1-9]|1[01])$/', $target) === 1) {
                $service = $target;
                break;
            }
        }
    }
    $category = $service ? trim((string) (content()->service($service)['title'] ?? '')) : '';
    $shortKey = 'form.matter_type.option.'.$service;
    $shortTitle = $service && content()->hasUi($shortKey) ? trim(ui($shortKey)) : '';
    if ($shortTitle !== '' && ($category === '' || mb_strlen($shortTitle) < mb_strlen($category))) {
        $category = $shortTitle;
    }
    $href ??= SiteUrl::caseStudy($code);
    $level = max(2, min(6, (int) $level));
@endphp
<x-d2.ui.card {{ $attributes->class(['case-card']) }} :href="$href" :title="Bidi::isolate($title)" :title-level="$level" :id="$idPrefix.$code" data-case="{{ $code }}">
    @if ($category !== '')
        <p class="case-card__category">{{ Bidi::isolate($category) }}</p>
    @endif
    @if ($summary !== '')
        <p class="case-card__summary">{{ Bidi::isolate($summary) }}</p>
    @endif
</x-d2.ui.card>
