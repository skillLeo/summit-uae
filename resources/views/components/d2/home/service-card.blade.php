@props([
    'service',            // content()->service() record: code, approved title, approved home_summary
    'audiences' => [],    // hero audience keys of config('audience_map.hero') this service belongs to
    'as' => 'li',         // li in the home grid; div for styleguide specimens
])
{{--
    One practice-area card of the home grid (Design 2.1 D21, D22; resources/css/d2/sections/services.css).
    The homepage DOCX (Section 02): "Each H3 is the card title; its paragraph is the complete homepage summary.
    The next link is its CTA." So: <x-d2.ui.card> whose H3 title is the card's ONE link (stretched over the
    card: one link, one accessible name, the title), the approved summary in full (never clamped), and the
    approved "Explore This Service" as the visible call to action. The whole card is that link, so the label is
    not a second link: it is hidden from assistive technology (the link already says where it goes), like the
    P02 cards' label. No icon, no corner arrow, no "N documents usually needed" line (D03, D04, D22). Hover and
    keyboard focus are the card's own: the edge turns ink and the title underlines, nothing moves.
    In the home grid each card's title, summary and label sit on the row's shared tracks (subgrid).
    data-svc / data-svc-audiences feed d2Services (the audience filter).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $code = (string) ($service['code'] ?? '');
@endphp
<x-d2.ui.card :as="$as" :href="SiteUrl::service($code)" :title="$service['title'] ?? ''" title-level="3"
    {{ $attributes->class(['svc-card']) }} :data-svc="$code" :data-svc-audiences="implode(' ', (array) $audiences)">
    <p class="svc-card__summary">{{ Bidi::isolate($service['home_summary'] ?? '') }}</p>
    <x-slot:footer class="svc-card__footer">
        <span class="svc-card__cta" aria-hidden="true" data-card-link>{{ ui('cta.explore_service') }}</span>
    </x-slot:footer>
</x-d2.ui.card>
