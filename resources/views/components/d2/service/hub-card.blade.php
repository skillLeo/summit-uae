@props([
    'service',            // content()->service() record: hub_title, hub_summary, situations, hub_link_label
    'audiences' => [],    // hero audience keys (config('audience_map.hero')) this service belongs to
    'intro' => null,      // approved ui('service.situations_intro') ("This service may be relevant if:")
    'hidden' => false,    // filtered out on the server (?audience=…, the no-JavaScript filter)
])
{{--
    One P02 practice-area card (designer note: "an H3 title, complete summary, practical examples and one
    detail-page link"): <x-d2.ui.card> whose H3 (approved hub_title) is the one stretched link to the service
    page, the complete approved hub_summary, the approved situations under the approved intro line and the
    approved hub_link_label as the visible call to action (the whole card is the link, so it is not a second
    link). data-audiences feeds d2ServiceHub. Design 2.1 (brief §3.6 rules 3 and 5): a text card, no service
    object icon and no arrow after the label; hover turns the edge ink and underlines the title (card.css).
    Phones (below 768 px, with JavaScript): the situations fold behind the approved intro line, a disclosure
    button above the card's link (eleven open lists made P02 14 600–16 600 px tall at 390, and every service
    page repeats them); the list stays in the HTML. From 768 px the last card takes the rest of its row as a
    wide card, its situations beside the text (service.css), so the grid never shows a hole.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $code = (string) ($service['code'] ?? '');
    $situations = array_values(array_filter((array) ($service['situations'] ?? []), fn ($s) => is_string($s) && trim($s) !== ''));
    $title = (string) (($service['hub_title'] ?? null) ?: ($service['title'] ?? ''));
@endphp
<x-d2.ui.card as="li" padding="md" {{ $attributes->class(['pa-card']) }} :href="SiteUrl::service($code)" :title="Bidi::isolate($title)" title-level="3"
    :data-service="$code" :data-audiences="implode(' ', (array) $audiences)" :hidden="$hidden">
    @if (! empty($service['hub_summary']))
        <p class="pa-card__summary">{{ Bidi::isolate($service['hub_summary']) }}</p>
    @endif
    @if ($situations !== [])
        <div class="pa-card__fit" data-pa-fit>
            @if ($intro)
                <p class="pa-card__fit-intro">{{ Bidi::isolate($intro) }}</p>
                {{-- Phones with JavaScript (html.js, from the first paint): the same approved line is the disclosure of the
                     situations, folded until asked for (d2ServiceHub); without JavaScript and from 768 px the list shows. --}}
                <button type="button" class="pa-card__fit-toggle" aria-expanded="false" aria-controls="pa-fit-{{ strtolower($code) }}" data-pa-fit-toggle>
                    <span class="pa-card__fit-label">{{ Bidi::isolate($intro) }}</span>
                    <x-d2.ui.icon name="chevron-down" size="20" class="pa-card__fit-chevron" />
                </button>
            @endif
            <ul class="pa-card__fit-list" id="pa-fit-{{ strtolower($code) }}" role="list">
                @foreach ($situations as $situation)
                    <li>{{ Bidi::isolate($situation) }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <x-slot:footer class="pa-card__foot">
        <span class="pa-card__cta" aria-hidden="true">{{ Bidi::isolate($service['hub_link_label'] ?? ui('cta.explore_service')) }}</span>
    </x-slot:footer>
</x-d2.ui.card>
