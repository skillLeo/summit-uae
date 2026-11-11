@props([
    'case',                   // content()->caseStudy() record with the homepage preview copy (home_title, home_summary, home_link_label)
    'level' => 3,             // heading level of the title
    'idPrefix' => 'preview-', // card id = {prefix}{case code}: the home keeps Design 1's #preview-case-01 / -04
    'href' => null,           // default: the story on the Case Studies page (SiteUrl::caseStudy: P04#case-0N)
])
{{--
    <x-d2.case.preview :case="content()->caseStudy('case-01')" />
    A homepage case-study text preview (Design 2.1 D25; homepage DOCX section 06: "Use two text based case previews.
    Link to the matching case anchors. These previews describe the issue and method without asserting a disputed
    outcome."). A text card in the pattern of the home's service and guide cards: the approved H3 title is the
    card's ONE link (stretched over the card: one link, one accessible name) to the story's anchor on P04, the
    approved paragraph in full, and the approved link label ("Read the Cheque Case Study") as the visible call to
    action. The whole card is that link, so the label is not a second link and is hidden from assistive technology.
    No number, no icon, no chip, no timeline, no badge (D25, D46; brief §3.6). Styles: sections/cases.css.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $code = (string) ($case['code'] ?? '');
    $title = trim((string) (($case['home_title'] ?? null) ?: ($case['card_title'] ?? ($case['title'] ?? ''))));
    $summary = trim((string) (($case['home_summary'] ?? null) ?: ($case['card_summary'] ?? '')));
    $label = trim((string) ($case['home_link_label'] ?? ''));
    $level = max(2, min(6, (int) $level));
@endphp
<x-d2.ui.card {{ $attributes->class(['case-preview']) }} :href="$href ?? SiteUrl::caseStudy($code)" :title="Bidi::isolate($title)" :title-level="$level"
    :id="$idPrefix.$code" data-case="{{ $code }}">
    @if ($summary !== '')
        <p class="case-preview__summary">{{ Bidi::isolate($summary) }}</p>
    @endif
    @if ($label !== '')
        <x-slot:footer class="case-preview__foot">
            <span class="case-preview__cta" aria-hidden="true" data-card-link>{{ Bidi::isolate($label) }}</span>
        </x-slot:footer>
    @endif
</x-d2.ui.card>
