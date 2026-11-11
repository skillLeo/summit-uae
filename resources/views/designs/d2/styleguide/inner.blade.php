{{--
    Styleguide: Design 2 inner-page kit (<x-d2.inner.*>; upgrade spec §7.4, §12 D–E; Design 2.1 Phase 1).
    Annotations are internal: lang/en/styleguide_d2/inner.php. Demo content is approved copy (content(),
    ui(), setting()) and the proposed keys of proposed-d2/inner. Specimens never use the live hooks: the head
    renders its title as a <p> (one H1 per page), the TOC is static (no scroll-spy, no sheet), the progress bar
    is a fixed 40 % demo, the sheet is a static specimen, and every id carries the sg-inner prefix. The "other
    direction" specimens render the approved copy of the other script (Arabic on LTR pages, English on the
    Arabic page) with that locale active.
--}}
@php
    use App\Support\SiteUrl;

    $t = fn (string $key) => __('styleguide_d2/inner.'.$key);
    $en = app()->isLocale('en') ? '' : 'lang=en';

    // Approved demo content of one language (the page's, or the other direction's).
    $demo = function (): array {
        $legal = content()->page('P08');
        $p02 = content()->page('P02')['content'] ?? [];
        $p07 = content()->page('P07')['content'] ?? [];
        $s01 = content()->service('S01') ?? [];
        $sections = array_values((array) ($legal['content']['sections'] ?? []));
        $crumb = fn (int $i, ?string $fallback) => is_array($legal['breadcrumb'][$i] ?? null) ? ($legal['breadcrumb'][$i]['label'] ?? $fallback) : $fallback;

        return [
            'title' => (string) ($legal['h1'] ?? ''),
            'updated' => (string) ($legal['last_updated'] ?? ''),
            'intro' => (array) ($legal['content']['intro'] ?? []),
            'crumbs' => [
                ['label' => (string) $crumb(0, ui('nav.home')), 'href' => SiteUrl::home()],
                ['label' => (string) $crumb(1, $legal['h1'] ?? ''), 'href' => null],
            ],
            'sections' => $sections,
            'service' => $s01,
            'serviceCrumbs' => [
                ['label' => ui('nav.home'), 'href' => SiteUrl::home()],
                ['label' => ui('nav.practice_areas'), 'href' => SiteUrl::page('P02')],
                ['label' => (string) ($s01['title'] ?? ''), 'href' => null],
            ],
            'prose' => [
                ['heading' => $sections[0]['heading'] ?? null, 'blocks' => $sections[0]['blocks'] ?? []],
                ['heading' => $p07['helps']['heading'] ?? null, 'items' => $p07['helps']['items'] ?? [], 'note' => $p07['helps']['note'] ?? null],
                ['heading' => $p07['after']['heading'] ?? null, 'steps' => $p07['after']['steps'] ?? []],
                ['heading' => $s01['sections']['practical']['heading'] ?? null, 'items' => array_slice((array) ($s01['sections']['practical']['items'] ?? []), 0, 1)],
                ['heading' => $p02['faq']['heading'] ?? null, 'items' => array_slice((array) ($p02['faq']['items'] ?? []), 0, 2)],
                ['heading' => $p02['scope']['heading'] ?? null, 'paragraphs' => $p02['scope']['paragraphs'] ?? [], 'variant' => 'note'],
                ['blocks' => [['type' => 'links', 'items' => array_values(array_filter((array) ($legal['content']['links'] ?? []), fn ($l) => in_array($l['target'] ?? '', ['EMAIL', 'TEL', 'P07'], true)))]]],
            ],
            'cta' => $p02['cta'] ?? [],
            'related' => array_values(array_filter((array) ($legal['content']['links'] ?? []), fn ($l) => in_array($l['target'] ?? '', ['L01', 'L02'], true))),
            'guides' => array_map(fn ($code) => ['label' => content()->guide($code)['card_title'] ?? content()->guide($code)['title'] ?? '', 'text' => content()->guide($code)['card_summary'] ?? null, 'target' => $code], array_slice((array) ($s01['related_guides'] ?? []), 0, 2)),
        ];
    };
    $d = $demo();
    $other = app()->isLocale('ar') ? 'en' : 'ar';
    $otherDir = $other === 'ar' ? 'rtl' : 'ltr';
    $previousLocale = app()->getLocale();
    app()->setLocale($other);
    $o = $demo();
    app()->setLocale($previousLocale);

    $tocCurrent = 'sg-inner-prose-03';
@endphp
<section class="sg-section sg-inner" id="sg-inner">
    <h2 {{ $en }}>{{ $t('title') }}</h2>
    <p {{ $en }}>{{ $t('intro') }}</p>

    {{-- ================================================================== head --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('head') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('head_note') }}</p>
    <div class="sg-grid sg-grid--full">
        <div class="sg-specimen sg-inner__wide">
            <x-d2.inner.head title-tag="p" title-id="sg-inner-head-1" size="document"
                :title="$d['title']" :crumbs="$d['crumbs']" :intro="$d['intro']">
                <x-slot:meta>
                    <p class="legal__updated">{{ $d['updated'] }}</p>
                </x-slot:meta>
            </x-d2.inner.head>
            <p class="sg-label" {{ $en }}>{{ $t('head_desktop') }}</p>
        </div>
        <div class="sg-specimen sg-inner__wide">
            <x-d2.inner.head title-tag="p" title-id="sg-inner-head-2"
                :title="$d['service']['h1'] ?? ''" :crumbs="$d['serviceCrumbs']" :intro="$d['service']['intro'] ?? []">
                <x-slot:actions>
                    <x-d2.ui.button variant="gold" size="52" :href="\App\Design\D2\Prefill::url(['service' => 'S01'])">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
                    <x-d2.ui.button variant="whatsapp" size="52" :href="setting('whatsapp_url')">{{ ui('cta.whatsapp') }}</x-d2.ui.button>
                </x-slot:actions>
            </x-d2.inner.head>
            <p class="sg-label" {{ $en }}>{{ $t('head_actions') }}</p>
        </div>
    </div>

    {{-- ================================================================== prose --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('prose') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('prose_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <x-d2.inner.prose :sections="$d['prose']" :level="4" id-prefix="sg-inner-prose" />
            <p class="sg-label" {{ $en }}>{{ $t('prose_label') }}</p>
        </div>
        @php app()->setLocale($other); @endphp
        <div class="sg-specimen" dir="{{ $otherDir }}" lang="{{ $other }}">
            <x-d2.inner.prose :sections="$o['prose']" :level="4" id-prefix="sg-inner-prose-o" />
            @php app()->setLocale($previousLocale); @endphp
            <p class="sg-label" {{ $en }}>{{ $t('prose_dir') }}</p>
        </div>
    </div>

    {{-- ================================================================== TOC --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('toc') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('toc_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <x-d2.inner.toc :sections="array_slice($d['sections'], 0, 6)" id-prefix="sg-inner-prose" mode="nav" :static="true" :current="$tocCurrent" sheet-id="sg-inner-toc" />
            <p class="sg-label" {{ $en }}>{{ $t('toc_desktop') }}</p>
        </div>
        <div class="sg-specimen">
            <div class="toc-bar is-enhanced sg-inner__bar">
                <span class="toc-bar__button">
                    <span class="toc-bar__title" @proposed>{{ d2p('inner.toc_title') }}</span>
                    <x-d2.ui.icon name="chevron-down" size="20" class="toc-bar__chevron" />
                </span>
            </div>
            <x-d2.ui.sheet id="sg-inner-toc-sheet" :title="d2p('inner.toc_title')" :title-proposed="true" :static="true" class="sg-inner__sheet">
                <nav class="toc toc--sheet" aria-label="{{ d2p('inner.toc_title') }}">
                    <ol class="toc__list">
                        @foreach (array_slice($d['sections'], 0, 5) as $i => $section)
                            <li class="toc__item"><a class="toc__link" href="#sg-inner" @if ($i === 2) aria-current="true" @endif>{{ $section['heading'] ?? '' }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            </x-d2.ui.sheet>
            <p class="sg-label" {{ $en }}>{{ $t('toc_sheet') }}</p>
        </div>
        @php app()->setLocale($other); @endphp
        <div class="sg-specimen" dir="{{ $otherDir }}" lang="{{ $other }}">
            <x-d2.inner.toc :sections="array_slice($o['sections'], 0, 6)" id-prefix="sg-inner-prose-o" mode="nav" :static="true" current="sg-inner-prose-o-03" sheet-id="sg-inner-toc-o" />
            @php app()->setLocale($previousLocale); @endphp
            <p class="sg-label" {{ $en }}>{{ $t('toc_dir') }}</p>
        </div>
        {{-- The service pages' side rail: the contents above the compact CTA card, capped (here to a fixed
             height, live to the window) so the list scrolls inside it and the card always shows in full. --}}
        <div class="sg-specimen">
            <div class="inner-layout__rail sg-inner__rail">
                <x-d2.inner.toc :sections="array_slice($d['sections'], 0, 6)" id-prefix="sg-inner-prose" mode="nav" :static="true" current="sg-inner-prose-02" sheet-id="sg-inner-toc-rail" />
                <div class="inner-layout__aside">
                    <x-d2.inner.cta-card id="sg-inner-cta-rail" :sticky="false" :heading="$d['service']['sections']['cta']['heading'] ?? null" service="S01"
                        :request-label="$d['service']['sections']['cta']['request_label'] ?? null" :whatsapp-label="$d['service']['sections']['cta']['whatsapp_label'] ?? null"
                        :phone="false" :email="false" :hours="false" />
                </div>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('toc_rail') }}</p>
        </div>
    </div>

    {{-- ================================================================== progress --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('progress') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('progress_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <div class="reading-progress reading-progress--demo" aria-hidden="true"><span class="reading-progress__bar"></span></div>
            <p class="sg-label" {{ $en }}>{{ $t('progress_ltr') }}</p>
        </div>
        <div class="sg-specimen" dir="rtl">
            <div class="reading-progress reading-progress--demo" aria-hidden="true"><span class="reading-progress__bar"></span></div>
            <p class="sg-label" {{ $en }} dir="ltr">{{ $t('progress_rtl') }}</p>
        </div>
    </div>

    {{-- ================================================================== CTA card --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('cta') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('cta_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <x-d2.inner.cta-card class="sg-inner__aside" id="sg-inner-cta-1" :sticky="false" :heading="$d['cta']['heading'] ?? null" :text="$d['cta']['text'] ?? null"
                :note="$d['cta']['note'] ?? null" service="S06" :request-label="$d['cta']['request_label'] ?? null"
                :whatsapp-label="$d['cta']['whatsapp_label'] ?? null" :email="true" />
            <p class="sg-label" {{ $en }}>{{ $t('cta_full') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.inner.cta-card class="sg-inner__aside" id="sg-inner-cta-2" :sticky="false" :hours="false" />
            <p class="sg-label" {{ $en }}>{{ $t('cta_minimal') }}</p>
        </div>
        @php
            app()->setLocale($other);
            ob_start();
        @endphp
        <x-d2.inner.cta-card class="sg-inner__aside" id="sg-inner-cta-3" :sticky="false" :heading="$o['cta']['heading'] ?? null" :text="$o['cta']['text'] ?? null"
            :request-label="$o['cta']['request_label'] ?? null" :whatsapp-label="$o['cta']['whatsapp_label'] ?? null" />
        @php
            app()->setLocale($previousLocale);
            // Brief 5.2 and 14.2 (every WhatsApp link of a page carries that page's WA01): this specimen speaks the
            // other language, so its WhatsApp button would open the other language's WA01. It is shown, not live:
            // the same markup without href, target and rel, marked data-lint-specimen (as the wizard's RTL specimen).
            $dirSpecimen = (string) preg_replace_callback(
                '#<a\b[^>]*\bhref="https://(?:wa\.me|api\.whatsapp\.com)/[^"]*"[^>]*>#',
                fn (array $tag): string => (string) preg_replace('#^<a\b#', '<a data-lint-specimen', (string) preg_replace('#\s(?:href|target|rel)="[^"]*"#', '', $tag[0])),
                (string) ob_get_clean(),
            );
        @endphp
        <div class="sg-specimen" dir="{{ $otherDir }}" lang="{{ $other }}">
            {!! $dirSpecimen !!}
            <p class="sg-label" {{ $en }}>{{ $t('cta_dir') }}</p>
        </div>
    </div>

    {{-- ================================================================== related --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('related') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('related_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <x-d2.inner.related id="sg-inner-related-1" :items="$d['related']" />
            <p class="sg-label" {{ $en }}>{{ $t('related_links') }}</p>
        </div>
    </div>
    {{-- Full width: two cards side by side at their real size; titles are h4 under this h3 (no level skipped). --}}
    <div class="sg-grid sg-grid--full">
        <div class="sg-specimen">
            <x-d2.inner.related id="sg-inner-related-2" variant="cards" :columns="2" :level="3" :items="$d['guides']" />
            <p class="sg-label" {{ $en }}>{{ $t('related_cards') }}</p>
        </div>
    </div>
</section>
