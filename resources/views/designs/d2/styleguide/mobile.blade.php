{{--
    Styleguide: Design 2.1 mobile layer (brief §3.4 overlays, §6 mobile): sheets, the modal, the tab bar, the sticky
    consultation bar, carousels and the installable app (PWA). Annotations are internal: lang/en/styleguide_d2/mobile.php.
    Demo content is approved copy (ui(), content()). The live buttons open the real global sheets (menu, language,
    services) and the demo sheet / modal pushed below. The live sticky bar stays rendered (it observes this section's
    #sg-mobile-cta-start and #sg-mobile-cta-end) but never shows: the page has the tab bar (brief 2.1 §6, one bottom bar).
--}}
@php
    use App\Support\SiteUrl;

    $t = fn (string $key) => __('styleguide_d2/mobile.'.$key);
    // Annotations are English on every page: mark them so AR / RU pages read them as English.
    $en = app()->isLocale('en') ? '' : 'lang="en"';
    $s06 = content()->service('S06') ?? [];
    $documents = array_slice($s06['documents'] ?? [], 0, 4);
    $services = array_values(content()->services());
    $cases = array_values(content()->all('case_studies'));

    // The installable app: the real manifest of this language (public/d2, scripts/d2/pwa-icons.mjs).
    $manifestPath = public_path('d2/manifest-'.app()->getLocale().'.webmanifest');
    $manifest = is_file($manifestPath) ? (json_decode((string) file_get_contents($manifestPath), true) ?: []) : [];
    $manifestRows = array_filter([
        'name' => $manifest['name'] ?? null,
        'short_name' => $manifest['short_name'] ?? null,
        'start_url' => $manifest['start_url'] ?? null,
        'scope' => $manifest['scope'] ?? null,
        'display' => $manifest['display'] ?? null,
        'theme_color' => $manifest['theme_color'] ?? null,
        'background_color' => $manifest['background_color'] ?? null,
    ]);
@endphp
<section class="sg-section" id="sg-mobile">
    <h2 {!! $en !!}>{{ $t('title') }}</h2>
    <p {!! $en !!}>{{ $t('intro') }}</p>

    {{-- ------------------------------------------------------------------ sheets --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('sheets') }}</h3>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <div class="cluster">
                <x-d2.ui.button variant="secondary" icon="menu" data-sheet-open="menu">{{ ui('a11y.open_menu') }}</x-d2.ui.button>
                <x-d2.ui.button variant="secondary" data-sheet-open="lang">{{ ui('a11y.choose_language') }}</x-d2.ui.button>
                <x-d2.ui.button variant="secondary" data-sheet-open="services">{{ ui('nav.practice_areas') }}</x-d2.ui.button>
                <x-d2.ui.button variant="secondary" data-sheet-open="sg-demo">{{ $s06['title'] ?? '' }}</x-d2.ui.button>
            </div>
            <p class="sg-label" {!! $en !!}>{{ $t('sheet_live') }}</p>
            <p class="sg-label" {!! $en !!}>{{ $t('sheet_api') }}</p>
        </div>

        <div class="sg-specimen">
            <x-d2.ui.sheet id="sg-static" :static="true" :title="$s06['sections']['documents']['heading'] ?? ''" :close-label="ui('a11y.close_menu')">
                <ul class="sheet-checklist" role="list">
                    @foreach (array_slice($documents, 0, 3) as $document)
                        <li><x-d2.ui.icon name="check" /><span>{{ $document }}</span></li>
                    @endforeach
                </ul>
            </x-d2.ui.sheet>
            <p class="sg-label" {!! $en !!}>{{ $t('sheet_static') }}</p>
        </div>

        <div class="sg-specimen">
            <p class="sg-label" {!! $en !!}>{{ $t('behaviour') }}</p>
            <ul class="stack" {!! $en !!}>
                @foreach ($t('behaviour_items') as $item)
                    <li class="t-small">{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ modal --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('modal') }}</h3>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <div class="sg-row">
                <x-d2.ui.button variant="secondary" data-modal-open="sg-demo">{{ ui('form.heading') }}</x-d2.ui.button>
            </div>
            <p class="sg-label" {!! $en !!}>{{ $t('modal_live') }}</p>
            <p class="sg-label" {!! $en !!}>{{ $t('modal_api') }}</p>
        </div>

        <div class="sg-specimen">
            <x-d2.ui.modal id="sg-static" :static="true" :title="ui('form.heading')">
                <p class="t-body">{{ ui('form.required_note') }}</p>
                <x-slot:footer>
                    <x-d2.ui.button variant="secondary">{{ proposed('sheet.close') }}</x-d2.ui.button>
                    <x-d2.ui.button variant="ink">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
                </x-slot:footer>
            </x-d2.ui.modal>
            <p class="sg-label" {!! $en !!}>{{ $t('modal_static') }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ tab bar --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('tabbar') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('tabbar_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <x-d2.shell.tabbar current="home" :static="true" />
            <p class="sg-label" {!! $en !!}>{{ $t('tab_home') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.shell.tabbar current="practice" :static="true" :fab="false" />
            <p class="sg-label" {!! $en !!}>{{ $t('tab_practice') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.shell.tabbar current="faq" :static="true" :fab="false" />
            <p class="sg-label" {!! $en !!}>{{ $t('tab_none') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.shell.tabbar class="tabbar--safe" current="contact" :static="true" :fab="false" />
            <p class="sg-label" {!! $en !!}>{{ $t('tab_safe') }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ sticky consultation bar --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('cta') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('cta_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <x-d2.shell.sticky-cta :static="true" :prefill="['service' => 'S06']" />
            <p class="sg-label" {!! $en !!}>{{ $t('cta_low') }}</p>
        </div>
        <div class="sg-specimen" id="sg-mobile-cta-start">
            <p class="sg-label" {!! $en !!}>{{ $t('cta_live') }}</p>
            <p class="sg-label" {!! $en !!}>{{ $t('cta_api') }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ carousel --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('carousel') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('carousel_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <div class="carousel-demo">
                <h4 class="t-h3" id="sg-carousel-services">{{ ui('nav.practice_areas') }}</h4>
                <x-d2.ui.carousel class="sg-carousel" labelledby="sg-carousel-services">
                    @foreach ($services as $service)
                        <x-d2.ui.card as="li" :href="SiteUrl::service($service['code'])" :title="$service['title'] ?? ''" title-level="5">
                            <p class="carousel-demo__text">{{ $service['hub_summary'] ?? $service['home_summary'] ?? '' }}</p>
                        </x-d2.ui.card>
                    @endforeach
                </x-d2.ui.carousel>
            </div>
            <p class="sg-label" {!! $en !!}>{{ $t('carousel_always') }}</p>
        </div>
        <div class="sg-specimen">
            <ul class="stack" {!! $en !!}>
                @foreach ($t('carousel_items') as $item)
                    <li class="t-small">{{ $item }}</li>
                @endforeach
            </ul>
            <p class="sg-label" {!! $en !!}>{{ $t('carousel_api') }}</p>
        </div>
    </div>
    <div class="sg-grid sg-grid--full">
        <div class="sg-specimen">
            <h4 class="t-h3" id="sg-carousel-cases">{{ ui('nav.case_studies') }}</h4>
            <x-d2.ui.carousel class="carousel-demo__bleed" labelledby="sg-carousel-cases" until="768" list-class="carousel-demo__grid">
                @foreach ($cases as $case)
                    <x-d2.ui.card as="li" :href="SiteUrl::caseStudy((string) ($case['anchor'] ?? ''))" :title="$case['card_title'] ?? ''" title-level="5">
                        <p class="carousel-demo__text">{{ $case['matter'] ?? '' }}</p>
                    </x-d2.ui.card>
                @endforeach
            </x-d2.ui.carousel>
            <p class="sg-label" {!! $en !!}>{{ $t('carousel_until') }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ installable app (PWA) --}}
    <h3 class="sg-sub" id="sg-mobile-cta-end" {!! $en !!}>{{ $t('pwa') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('pwa_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <div class="sg-row">
                <img class="pwa-demo__icon" src="/d2/icons/icon-192.png" width="96" height="96" alt="{{ $t('pwa_icon_any') }}" {!! $en !!}>
                <img class="pwa-demo__icon pwa-demo__icon--mask" src="/d2/icons/maskable-192.png" width="96" height="96" alt="{{ $t('pwa_icon_maskable') }}" {!! $en !!}>
            </div>
            <p class="sg-label" {!! $en !!}>{{ $t('pwa_icons') }}</p>
        </div>
        <div class="sg-specimen">
            <dl class="pwa-demo__facts">
                @foreach ($manifestRows as $key => $value)
                    <div class="pwa-demo__fact">
                        <dt class="sg-code" {!! $en !!}>{{ $key }}</dt>
                        <dd @if (in_array($key, ['start_url', 'scope', 'display', 'theme_color', 'background_color'], true)) dir="ltr" @endif>{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="sg-label" {!! $en !!}>{{ $t('pwa_manifest') }}</p>
        </div>
        <div class="sg-specimen">
            <ul class="stack" {!! $en !!}>
                @foreach ($t('pwa_items') as $item)
                    <li class="t-small">{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

{{-- Live below 768 px only while this section's demos are on screen (never over the other areas). --}}
<x-d2.shell.sticky-cta target="#sg-mobile-cta-start" until="#sg-mobile-cta-end" :prefill="['service' => 'S06']" />

@push('sheets')
    <x-d2.ui.sheet id="sg-demo" :title="$s06['title'] ?? ''">
        <div class="sheet-group">
            <p class="t-body">{{ $s06['intro'] ?? '' }}</p>
        </div>
        <section class="sheet-group" aria-labelledby="sg-demo-docs">
            <h3 class="sheet-group__title" id="sg-demo-docs">{{ $s06['sections']['documents']['heading'] ?? '' }}</h3>
            <ul class="sheet-checklist" role="list">
                @foreach ($documents as $document)
                    <li><x-d2.ui.icon name="check" /><span>{{ $document }}</span></li>
                @endforeach
            </ul>
        </section>
        <div class="sheet-group">
            <x-d2.ui.field id="sg-demo-sheet-name" :label="ui('form.name.label')" :hint="ui('form.name.help')">
                <x-d2.ui.input name="sg_demo_sheet_name" autocomplete="name" />
            </x-d2.ui.field>
        </div>
        <x-slot:footer>
            <x-d2.ui.button variant="ink" :full="true" :href="SiteUrl::consultation()">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
        </x-slot:footer>
    </x-d2.ui.sheet>

    <x-d2.ui.modal id="sg-demo" :title="ui('form.heading')">
        <div class="stack">
            <p class="t-body">{{ ui('form.intro') }}</p>
            <x-d2.ui.field id="sg-demo-modal-name" :label="ui('form.name.label')" :hint="ui('form.name.help')">
                <x-d2.ui.input name="sg_demo_modal_name" autocomplete="name" autofocus />
            </x-d2.ui.field>
        </div>
        <x-slot:footer>
            <x-d2.ui.button variant="secondary" data-modal-close>{{ proposed('sheet.close') }}</x-d2.ui.button>
            <x-d2.ui.button variant="ink" :href="SiteUrl::consultation()">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
        </x-slot:footer>
    </x-d2.ui.modal>
@endpush
