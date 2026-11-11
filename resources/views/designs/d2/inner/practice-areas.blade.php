{{--
    Design 2 practice-areas hub P02 (route practice-areas/, PagePreviewController@page → d2::pages.preview).
    Upgrade spec §12 D, §7.4. Content and order from 02_Services_Overview_*.docx (ServicesOverviewMapper):

    head        the inner kit head: breadcrumb · approved H1 + intro · approved CTAs (the request: the page's one
                gold action, the hero primary of brief §3.5) · aside: "We Start with the File" (approved H2, text,
                items as plain bullets) as the head's side card
    services    "Choose a Service Area" (approved H2) · audience filter (All + the three hero audiences of
                config('audience_map.hero'), labelled with the approved Who We Help titles; mapping proposed)
                · the eleven approved service cards (<x-d2.service.hub-card>: hub_title, hub_summary, the
                approved situations, hub_link_label) linking to every service page
    then        overlapping matters · before requesting a consultation · service-selection FAQ (accordion,
                full answers in the HTML) · resources (P03, P05; the kit's link rows) · final consultation CTA
                · scope notice (the kit's prose note)

    The filter works without JavaScript: the chips are links (?audience=business#services; the value is validated
    against the mapping, anything else shows every card) and the server hides the other cards.
    d2ServiceHub (resources/js/d2/components/service-hub.js) filters in place, keeps the URL in step with
    history.replaceState and announces the result politely.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Prefill;
    use App\Support\SiteUrl;

    $page = content()->page('P02');
    $c = $page['content'] ?? [];
    $services = content()->services();
    $map = array_filter((array) config('audience_map.hero', []), 'is_array');

    // Approved Who We Help titles (P01) name the three hero audiences.
    $who = [];
    foreach (content()->page('P01')['content']['who_we_help']['items'] ?? [] as $item) {
        $who[$item['key'] ?? ''] = $item['title'] ?? '';
    }
    $labels = array_filter(array_intersect_key([
        'individual' => $who['individuals'] ?? '',
        'business' => $who['businesses'] ?? '',
        'investor' => $who['investors'] ?? '',
    ], $map));
    $audiencesOf = fn (string $code) => array_keys(array_filter($map, fn (array $codes) => in_array($code, $codes, true)));

    // No-JavaScript filter: a validated ?audience= value (a plain string naming a known audience) or every card.
    $query = request()->query('audience');
    $active = is_string($query) && isset($labels[$query]) ? $query : 'all';
    $shown = fn (string $audience) => $audience === 'all'
        ? count($services)
        : count(array_filter($services, fn ($svc) => in_array($audience, $audiencesOf((string) $svc['code']), true)));
    $chipUrl = fn (string $audience) => SiteUrl::page('P02').($audience === 'all' ? '' : '?audience='.$audience).'#services';

    $draft = trim((string) ($page['whatsapp_draft'] ?? ''));
    $whatsapp = $draft !== ''
        ? 'https://wa.me/'.config('summit.contact.whatsapp_number').'?text='.rawurlencode($draft)
        : (string) setting('whatsapp_url');
    $start = $c['start'] ?? [];
    $cta = $c['cta'] ?? [];

    $resources = array_map(fn ($link) => [
        'label' => (string) ($link['label'] ?? ''),
        'target' => (string) ($link['target'] ?? ''),
        'text' => match ((string) ($link['target'] ?? '')) { 'P03' => ui('nav.resources'), 'P05' => ui('nav.faq'), default => null },
    ], array_values(array_filter((array) ($c['resources']['links'] ?? []), 'is_array')));

    $scope = ! empty($c['scope']['heading']) ? [[
        'id' => 'scope',
        'heading' => $c['scope']['heading'],
        'variant' => 'note',
        'tone' => 'info',
        'blocks' => array_merge(
            array_map(fn ($p) => ['type' => 'p', 'text' => $p], array_values(array_filter((array) ($c['scope']['paragraphs'] ?? []), 'is_string'))),
            ! empty($c['scope']['link_label']) ? [['type' => 'links', 'items' => [['label' => $c['scope']['link_label'], 'target' => 'P08']]]] : [],
        ),
    ]] : [];
@endphp

<div class="pa-page">
    <x-d2.inner.head size="document" class="pa-head" :title="$h1" :crumbs="$crumbs" :intro="$c['intro'] ?? []" title-id="page-title">
        <x-slot:actions>
            <x-d2.ui.button variant="gold" size="52" :href="Prefill::url()">{{ $cta['request_label'] ?? ui('cta.request_consultation') }}</x-d2.ui.button>
            <x-d2.ui.button variant="secondary" size="52" href="#services">{{ Bidi::isolate($c['choose']['heading'] ?? '') }}</x-d2.ui.button>
        </x-slot:actions>
        @if (! empty($start['heading']))
            <x-slot:aside>
                <x-d2.ui.card as="section" padding="lg" class="svc-docs pa-start" id="start" aria-labelledby="start-title">
                    <h2 class="svc-docs__title" id="start-title">{{ Bidi::isolate($start['heading']) }}</h2>
                    @if (! empty($start['text']))
                        <p class="pa-start__text">{{ Bidi::isolate($start['text']) }}</p>
                    @endif
                    <x-d2.service.list :items="$start['items'] ?? []" icon="dot" class="pa-start__list" />
                </x-d2.ui.card>
            </x-slot:aside>
        @endif
    </x-d2.inner.head>

    {{-- ------------------------------------------------------------------ the eleven services --}}
    <section class="pa-services" id="services" aria-labelledby="services-title" x-data="d2ServiceHub">
        <div class="container">
            <div class="pa-services__head">
                <h2 class="pa-services__title" id="services-title">{{ Bidi::isolate($c['choose']['heading'] ?? '') }}</h2>
                @if ($labels !== [])
                    <div class="pa-filter" role="group" aria-labelledby="pa-filter-label" data-pa-filter x-on:click="pick">
                        <span class="pa-filter__label" id="pa-filter-label" @proposed>{{ d2p('practice_areas.filter_label') }}</span>
                        <x-d2.ui.chip :href="$chipUrl('all')" :pressed="$active === 'all'" :count="$shown('all')" data-pa-audience="all"
                            :data-pa-result="trans_choice('proposed.d2.practice_areas.result', $shown('all'))"><span @proposed>{{ d2p('practice_areas.filter_all') }}</span></x-d2.ui.chip>
                        @foreach ($labels as $audience => $label)
                            <x-d2.ui.chip :href="$chipUrl($audience)" :pressed="$active === $audience" :count="$shown($audience)" :data-pa-audience="$audience"
                                :data-pa-result="trans_choice('proposed.d2.practice_areas.result', $shown($audience))">{{ $label }}</x-d2.ui.chip>
                        @endforeach
                    </div>
                @endif
            </div>

            <ul class="pa-grid" role="list" data-pa-grid>
                @foreach ($services as $service)
                    @php $aud = $audiencesOf((string) $service['code']); @endphp
                    <x-d2.service.hub-card :service="$service" :audiences="$aud" :intro="ui('service.situations_intro')"
                        :hidden="$active !== 'all' && ! in_array($active, $aud, true)" />
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ------------------------------------------------------------------ approved sections in DOCX order --}}
    <div class="container pa-rest">
        @if (! empty($c['overlap']['heading']))
            <section class="pa-split" id="overlap" aria-labelledby="overlap-title">
                <h2 class="prose__heading pa-split__title" id="overlap-title">{{ Bidi::isolate($c['overlap']['heading']) }}</h2>
                <div class="pa-split__body">
                    @foreach ((array) ($c['overlap']['paragraphs'] ?? []) as $paragraph)
                        <p class="pa-split__text">{{ Bidi::isolate($paragraph) }}</p>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! empty($c['before']['items']))
            <section class="pa-split" id="before" aria-labelledby="before-title">
                <h2 class="prose__heading pa-split__title" id="before-title">{{ Bidi::isolate($c['before']['heading'] ?? '') }}</h2>
                <div class="pa-split__body">
                    <x-d2.service.list :items="$c['before']['items']" icon="check" tone="success" variant="rows" />
                </div>
            </section>
        @endif

        @if (! empty($c['faq']['items']))
            <section class="pa-split" id="faq" aria-labelledby="faq-title">
                <h2 class="prose__heading pa-split__title" id="faq-title">{{ Bidi::isolate($c['faq']['heading'] ?? '') }}</h2>
                <div class="pa-split__body">
                    <x-d2.ui.accordion level="3" name="p02-faq" class="pa-faq">
                        @foreach ($c['faq']['items'] as $i => $faq)
                            <x-d2.ui.accordion-item :id="'p02-faq-'.($i + 1)" :title="Bidi::isolate($faq['question'] ?? '')">
                                <p>{{ Bidi::isolate($faq['answer'] ?? '') }}</p>
                            </x-d2.ui.accordion-item>
                        @endforeach
                    </x-d2.ui.accordion>
                </div>
            </section>
        @endif

        @if ($resources !== [])
            <section class="pa-split" id="resources" aria-labelledby="resources-title">
                <h2 class="prose__heading pa-split__title" id="resources-title">{{ Bidi::isolate($c['resources']['heading'] ?? '') }}</h2>
                <div class="pa-split__body">
                    <x-d2.inner.related class="pa-related" :label="$c['resources']['heading'] ?? null" :items="$resources" variant="links" id="resources-links" />
                </div>
            </section>
        @endif

        @if (! empty($cta['heading']))
            <x-d2.service.cta id="request" class="pa-cta" :cta="$cta" :consult="Prefill::url()" :whatsapp="$whatsapp" />
        @endif

        @if ($scope !== [])
            <x-d2.inner.prose :sections="$scope" class="pa-scope inner-scope inner-scope--wide" />
        @endif
    </div>
</div>
