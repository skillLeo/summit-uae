{{--
    Design 2 home section 02: Our Legal Services in the UAE (brief §5.1 item 3; Design 2.1 D21, D22, D23).
    In the DOCX order: the approved heading and intro, the eleven cards in the supplied order (title, the
    complete summary, "Explore This Service"), then the approved multi-area paragraph with "View All Practice
    Areas" as one compact full-width row: the paragraph on the start side, the button on the end side (D23).
    - Cards: <x-d2.home.service-card>, an equal grid (resources/css/d2/sections/services.css): one column on
      phones, two from 768 px, three from 1280 px; the cards of a row share their row tracks (subgrid), so
      titles, summaries and labels line up across the row. No bento widths, no carousel (D21).
    - Audience filter chips: All + the three hero audiences of config('audience_map.hero') (proposed
      mapping). Labels are the approved Who We Help titles; "All", the group name and the announcement are
      proposed. Under .js their row is reserved from the first paint, and the chips become visible once
      d2Services runs (never a dead control); without JavaScript there are no chips and every card shows.
      d2Services (resources/js/d2/components/services.js) hides the other cards, moves the rest with a FLIP and
      announces the result politely.
--}}
@php
    use App\Support\SiteUrl;

    $svc = $c['services'] ?? [];
    $svcList = array_values($services ?? content()->services());
    $svcMap = array_filter((array) config('audience_map.hero', []), 'is_array');
    $whoTitles = [];
    foreach ($c['who_we_help']['items'] ?? [] as $item) {
        $whoTitles[$item['key'] ?? ''] = $item['title'] ?? '';
    }
    // Hero audience key → the approved Who We Help title that names it.
    $svcLabels = array_filter(array_intersect_key([
        'individual' => $whoTitles['individuals'] ?? '',
        'business' => $whoTitles['businesses'] ?? '',
        'investor' => $whoTitles['investors'] ?? '',
    ], $svcMap));
    $svcAudiences = fn (string $code) => array_keys(array_filter($svcMap, fn (array $codes) => in_array($code, $codes, true)));
    $svcTotal = count($svcList);
@endphp
<section class="section svc" id="{{ $sectionId ?? 'services' }}" aria-labelledby="services-title" x-data="d2Services">
    <div class="container">
        <header class="section__head">
            <h2 id="services-title">{{ $svc['heading'] ?? '' }}</h2>
            @if (! empty($svc['intro']))
                <p>{{ $svc['intro'] }}</p>
            @endif
        </header>

        @if ($svcLabels !== [])
            <div class="svc__filter" role="group" aria-labelledby="services-filter-label" data-svc-filter x-on:click="pick">
                <span class="sr-only" id="services-filter-label" @proposed>{{ d2p('services.filter_label') }}</span>
                <x-d2.ui.chip :pressed="true" :count="$svcTotal" data-svc-audience="all"
                    :data-svc-result="trans_choice('proposed.d2.services.result', $svcTotal)"><span @proposed>{{ d2p('services.filter_all') }}</span></x-d2.ui.chip>
                @foreach ($svcLabels as $audience => $label)
                    @php $svcShown = count(array_filter($svcList, fn ($s) => in_array($audience, $svcAudiences($s['code']), true))); @endphp
                    <x-d2.ui.chip :pressed="false" :count="$svcShown" :data-svc-audience="$audience"
                        :data-svc-result="trans_choice('proposed.d2.services.result', $svcShown)">{{ $label }}</x-d2.ui.chip>
                @endforeach
            </div>
        @endif

        <ul class="svc__grid" role="list" data-svc-grid>
            @foreach ($svcList as $service)
                <x-d2.home.service-card :service="$service" :audiences="$svcAudiences($service['code'])" />
            @endforeach
        </ul>

        <div class="svc__more">
            @if (! empty($svc['closing']))
                <p class="svc__more-text">{{ $svc['closing'] }}</p>
            @endif
            <x-d2.ui.button variant="ink" class="svc__more-action" :href="SiteUrl::page('P02')">{{ ui('cta.view_all_practice_areas') }}</x-d2.ui.button>
        </div>
    </div>
</section>
