@props([
    'code',                 // service code S01–S11 (the target of an approved related link)
    'label' => null,        // the approved label of that link: the card's link name and its visible cue
    'level' => 4,           // heading level of the service title (2–6)
])
{{--
    <x-d2.resources.service-card code="S06" :label="$item['label']" />
    "Related service" card of a guide (P03) and of a case file (P04), upgrade spec §7.4: the service's approved
    title as a heading, its approved Practice Areas summary (hub_summary) and the approved link label as the
    visible cue. The whole card is one link to the service page, named by the approved label (WCAG 2.5.3: the
    visible cue is the same text); the label is aria-hidden where it is drawn so it is not read twice.
    Design 2.1 (brief §3.5, §3.6 rules 3 and 5): a text card, no service object icon and no arrow after the
    cue; hover turns the edge ink and underlines the cue (card.css, resources.css).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $service = content()->service((string) $code) ?? [];
    $title = trim((string) ($service['title'] ?? ''));
    $summary = trim((string) (($service['hub_summary'] ?? null) ?: ($service['home_summary'] ?? '')));
    $label = trim((string) ($label ?? '')) ?: $title;
    $level = max(2, min(6, (int) $level));
@endphp
@if ($service !== [])
    <x-d2.ui.card {{ $attributes->class(['kc-service']) }} :href="SiteUrl::service((string) $code)" :label="$label" padding="none" data-service="{{ $code }}">
        <div class="kc-service__text">
            <h{{ $level }} class="kc-service__title">{{ Bidi::isolate($title) }}</h{{ $level }}>
            @if ($summary !== '')
                <p class="kc-service__summary">{{ Bidi::isolate($summary) }}</p>
            @endif
            <span class="kc-service__cue" aria-hidden="true">{{ Bidi::isolate($label) }}</span>
        </div>
    </x-d2.ui.card>
@endif
