@props([
    'guide',                  // content()->guide() record
    'copy' => 'home',         // home: the approved homepage title + summary · card: the Knowledge Centre card copy
    'category' => null,       // approved category name (null: looked up from the guide's category; '' for none)
    'level' => 3,             // heading level of the title
    'idPrefix' => '',         // ids: {prefix}guide-g01-title / -cta (Design 1's home ids)
    'href' => null,           // default: the guide on the Knowledge Centre (SiteUrl::guide)
])
{{--
    <x-d2.guide.card :guide="$guide" :category="$name" />
    Legal guide card, a TEXT card (Design 2.1 D27): the approved category, the approved title (the card's one
    link: the whole card opens the guide), the approved summary and the approved "Read the Guide" as the visible
    call to action (the whole card is the link, so the label is not a second link and is hidden from assistive
    technology, like the P02 cards' label). No generated cover and no reading time (brief §3.6 rules 9 and 10).
    An image area appears only when the guide has a real cover (App\Design\D2\Cover::image(): the admin's upload,
    Phase 4; none exists yet). Keeps Design 1's ids guide-g0N-title / guide-g0N-cta on the home.
    Source order: title, category, summary, label (the heading first for assistive technology); the category is
    drawn above the title (sections/guides.css). On the home each card is a subgrid of the row's tracks, so
    categories, titles, summaries and labels line up across a row (brief §7.1 rule 12).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Cover;
    use App\Support\SiteUrl;

    $code = (string) ($guide['code'] ?? '');
    $pick = fn (string $first, string $second) => (string) (($guide[$first] ?? null) ?: ($guide[$second] ?? ''));
    $title = $copy === 'card' ? $pick('card_title', 'home_title') : $pick('home_title', 'card_title');
    $summary = $copy === 'card' ? $pick('card_summary', 'home_summary') : $pick('home_summary', 'card_summary');
    if ($category === null) {
        foreach (content()->guideCategories() as $group) {
            if (($group['code'] ?? null) === ($guide['category'] ?? null)) {
                $category = $group['name'] ?? null;
                break;
            }
        }
    }
    $cover = Cover::image($guide);
    $gid = $idPrefix.'guide-'.strtolower($code);
    $level = max(2, min(6, (int) $level));
@endphp
<div class="guide-frame">
<x-d2.ui.card {{ $attributes->class(['guide-card', 'guide-card--cover' => $cover !== null]) }} :href="$href ?? SiteUrl::guide($code)" :title-level="$level" data-guide="{{ $code }}">
    @if ($cover !== null)
        <x-slot:media class="guide-card__media">
            <x-d2.guide.cover :image="$cover" />
        </x-slot:media>
    @endif
    <x-slot:title><span id="{{ $gid }}-title">{{ Bidi::isolate($title) }}</span></x-slot:title>

    @if (filled($category))
        <p class="guide-card__category"><span>{{ Bidi::isolate($category) }}</span></p>
    @endif
    @if ($summary !== '')
        <p class="guide-card__summary">{{ Bidi::isolate($summary) }}</p>
    @endif

    <x-slot:footer class="guide-card__foot">
        <span class="guide-card__read" id="{{ $gid }}-cta" aria-hidden="true" data-card-link>{{ ui('cta.read_guide') }}</span>
    </x-slot:footer>
</x-d2.ui.card>
</div>
