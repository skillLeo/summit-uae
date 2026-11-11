{{--
    Styleguide: Design 2.1 surfaces (brief §3.4, §3.5; docs/d2.1/phase-1/components.md §2 and §5): card, accordion,
    tabs, toast, tooltip, skeleton and callout, every state, on light and on dark. Annotations are internal:
    lang/en/styleguide_d2/surfaces.php. Demo content is approved copy only (ui(), setting(), content()). Forced states
    use the styleguide-only modifiers .is-hover / .is-focus / .is-selected / .is-static; everything else is live.
--}}
@php
    use App\Support\SiteUrl;

    $t = fn (string $key) => __('styleguide_d2/surfaces.'.$key);
    $service = fn (string $code) => content()->service($code) ?? [];
    $s01 = $service('S01');
    $s05 = $service('S05');
    $s06 = $service('S06');
    $s09 = $service('S09');
    $faqs = content()->faqs();
    $categories = content()->faqCategories();
    $byCategory = [];
    foreach ($faqs as $faq) {
        $byCategory[$faq['category'] ?? ''][] = $faq;
    }
    $answer = fn (array $faq) => array_values(array_filter($faq['answer'] ?? [], fn ($block) => ($block['type'] ?? '') === 'p' && ($block['text'] ?? '') !== ''));
    $p03 = content()->page('P03');
    $p05 = content()->page('P05')['content'] ?? [];
    $scope = $p05['scope'] ?? [];
    $tabItems = array_map(fn ($cat) => ['sg-'.$cat['anchor'], $cat['name']], $categories);
    $hours = content()->page('P01')['content']['contact']['hours'] ?? '';
    // Annotations are English on every language version: mark them so they are read as English.
    $en = app()->isLocale('en') ? '' : 'lang=en';
@endphp
<section class="sg-section" id="sg-surfaces">
    <h2 {{ $en }}>{{ $t('title') }}</h2>
    <p {{ $en }}>{{ $t('intro') }}</p>

    {{-- ------------------------------------------------------------------ card --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('card') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('card_note') }}</p>
    <div class="sg-grid sg-grid--wide" data-sg="cards">
        <div class="sg-specimen">
            <x-d2.ui.card :href="SiteUrl::service('S05')" :title="$s05['title'] ?? ''" title-level="4">
                <p class="card__text">{{ $s05['home_summary'] ?? '' }}</p>
            </x-d2.ui.card>
            <p class="sg-label" {{ $en }}>{{ $t('card_default') }}</p>
        </div>

        <div class="sg-specimen">
            <x-d2.ui.card :href="SiteUrl::service('S05')" class="is-hover" :title="$s05['title'] ?? ''" title-level="4">
                <p class="card__text">{{ $s05['home_summary'] ?? '' }}</p>
            </x-d2.ui.card>
            <p class="sg-label" {{ $en }}>{{ $t('card_hover') }}</p>
        </div>

        <div class="sg-specimen">
            <x-d2.ui.card :href="SiteUrl::service('S06')" class="is-focus" :title="$s06['title'] ?? ''" title-level="4">
                <p class="card__text">{{ $s06['home_summary'] ?? '' }}</p>
            </x-d2.ui.card>
            <p class="sg-label" {{ $en }}>{{ $t('card_focus') }}</p>
        </div>

        <div class="sg-specimen">
            <x-d2.ui.card :href="SiteUrl::service('S06')" :title="$s06['title'] ?? ''" title-level="4" data-sg="card-link">
                <p class="card__text">{{ $s06['home_summary'] ?? '' }}</p>
                <x-slot:footer>
                    <x-d2.ui.button variant="secondary" :href="SiteUrl::consultation()" data-sg="card-nested">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
                </x-slot:footer>
            </x-d2.ui.card>
            <p class="sg-label" {{ $en }}>{{ $t('card_link') }}</p>
        </div>

        <div class="sg-specimen">
            <x-d2.ui.card as="a" :href="SiteUrl::page('P03')" :title="$p03['h1'] ?? ''" title-level="4" data-sg="card-as-a">
                <p class="card__text">{{ $p03['content']['lead'] ?? '' }}</p>
            </x-d2.ui.card>
            <p class="sg-label" {{ $en }}>{{ $t('card_as_link') }}</p>
        </div>

        <div class="sg-specimen">
            <x-d2.ui.card as="div" :title="$s01['title'] ?? ''" title-level="4">
                <p class="card__text">{{ $s01['home_summary'] ?? '' }}</p>
            </x-d2.ui.card>
            <p class="sg-label" {{ $en }}>{{ $t('card_static') }}</p>
        </div>

        <div class="sg-specimen">
            <x-d2.ui.card tone="sunken" as="div" :title="$s01['title'] ?? ''" title-level="4">
                <p class="card__text">{{ $s01['home_summary'] ?? '' }}</p>
            </x-d2.ui.card>
            <p class="sg-label" {{ $en }}>{{ $t('card_sunken') }}</p>
        </div>

        <div class="sg-specimen">
            <div class="stack">
                @foreach (['sm' => 'S08', 'md' => 'S09', 'lg' => 'S10'] as $pad => $code)
                    <x-d2.ui.card as="div" :padding="$pad" :title="$service($code)['title'] ?? ''" title-level="4" />
                @endforeach
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('card_padding') }}</p>
        </div>
    </div>

    <div class="sg-night on-dark on-night">
        <div class="sg-grid sg-grid--wide">
            @foreach (['default' => '', 'hover' => 'is-hover', 'focus' => 'is-focus'] as $state => $class)
                <div>
                    <x-d2.ui.card tone="night" :href="SiteUrl::service('S09')" :class="$class" :title="$s09['title'] ?? ''" title-level="4">
                        <p class="card__text">{{ $s09['home_summary'] ?? '' }}</p>
                    </x-d2.ui.card>
                    <p class="sg-label" {{ $en }}>{{ $t('card_night_'.$state) }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ------------------------------------------------------------------ accordion --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('accordion') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('acc_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <x-d2.ui.accordion level="4">
                @foreach (array_slice($faqs, 0, 4) as $i => $faq)
                    <x-d2.ui.accordion-item :id="'sg-state-'.$faq['anchor']" :title="$faq['question']" :open="$i === 3"
                        :class="[1 => 'is-hover', 2 => 'is-focus'][$i] ?? ''">
                        @foreach ($answer($faq) as $block)
                            <p>{{ $block['text'] }}</p>
                        @endforeach
                    </x-d2.ui.accordion-item>
                @endforeach
            </x-d2.ui.accordion>
            <p class="sg-label" {{ $en }}>{{ $t('acc_states') }}</p>
        </div>

        <div class="sg-specimen">
            <x-d2.ui.accordion name="sg-faq" level="4">
                @foreach (array_slice($faqs, 4, 3) as $i => $faq)
                    <x-d2.ui.accordion-item :id="'sg-'.$faq['anchor']" :title="$faq['question']" :open="$i === 0">
                        @foreach ($answer($faq) as $block)
                            <p>{{ $block['text'] }}</p>
                        @endforeach
                    </x-d2.ui.accordion-item>
                @endforeach
            </x-d2.ui.accordion>
            <p class="sg-label" {{ $en }}>{{ $t('acc_card') }}</p>
            @isset($faqs[6])
                <p class="sg-label" {{ $en }}><a class="link" href="#sg-{{ $faqs[6]['anchor'] }}" data-sg="acc-deeplink">{{ $t('acc_deeplink') }}</a>: {{ $t('acc_deeplink_note') }}</p>
            @endisset
        </div>

        <div class="sg-specimen">
            <x-d2.ui.accordion variant="flush" name="sg-flush" level="4">
                @foreach (array_slice($faqs, 7, 3) as $faq)
                    <x-d2.ui.accordion-item :id="'sg-flush-'.$faq['anchor']" :title="$faq['question']">
                        @foreach ($answer($faq) as $block)
                            <p>{{ $block['text'] }}</p>
                        @endforeach
                    </x-d2.ui.accordion-item>
                @endforeach
            </x-d2.ui.accordion>
            <p class="sg-label" {{ $en }}>{{ $t('acc_flush') }}</p>
        </div>
    </div>

    <div class="sg-night on-dark on-night">
        <div class="sg-grid sg-grid--wide">
            <div>
                <x-d2.ui.accordion level="4">
                    @foreach (array_slice($faqs, 10, 2) as $i => $faq)
                        <x-d2.ui.accordion-item :id="'sg-night-'.$faq['anchor']" :title="$faq['question']" :open="$i === 0">
                            @foreach ($answer($faq) as $block)
                                <p>{{ $block['text'] }}</p>
                            @endforeach
                        </x-d2.ui.accordion-item>
                    @endforeach
                </x-d2.ui.accordion>
                <p class="sg-label" {{ $en }}>{{ $t('acc_night') }}</p>
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ tabs --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('tabs') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('tabs_note') }}</p>
    <div class="sg-grid sg-grid--full">
        <div class="sg-specimen">
            <x-d2.ui.tabs :tabs="$tabItems" :label="$p05['category_menu']['heading'] ?? null" data-sg="tabs">
                @foreach ($categories as $cat)
                    <x-d2.ui.tab-panel :id="'sg-'.$cat['anchor']" :title="$cat['name']" title-level="4">
                        <x-d2.ui.accordion variant="flush" :name="'sg-tab-'.$cat['anchor']" level="4">
                            @foreach (array_slice($byCategory[$cat['anchor']] ?? [], 0, 2) as $faq)
                                <x-d2.ui.accordion-item :id="'sg-tab-'.$faq['anchor']" :title="$faq['question']">
                                    @foreach ($answer($faq) as $block)
                                        <p>{{ $block['text'] }}</p>
                                    @endforeach
                                </x-d2.ui.accordion-item>
                            @endforeach
                        </x-d2.ui.accordion>
                    </x-d2.ui.tab-panel>
                @endforeach
            </x-d2.ui.tabs>
            <p class="sg-label" {{ $en }}>{{ $t('tabs_overflow') }}</p>
        </div>

        <div class="sg-specimen">
            <div class="tabs is-enhanced is-static" aria-hidden="true">
                <div class="tabs__bar">
                    <div class="tabs__list">
                        @foreach (array_slice($categories, 1, 4) as $i => $cat)
                            <span @class(['tabs__tab', 'is-hover' => $i === 1, 'is-focus' => $i === 2, 'is-selected' => $i === 3])><span class="tabs__label">{{ $cat['name'] }}</span></span>
                        @endforeach
                    </div>
                </div>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('tabs_states') }}</p>
        </div>
    </div>

    <div class="sg-night on-dark on-night">
        <div class="sg-grid sg-grid--wide">
            <div>
                <x-d2.ui.tabs :tabs="[['sg-night-s01', $s01['title'] ?? ''], ['sg-night-s05', $s05['title'] ?? ''], ['sg-night-s06', $s06['title'] ?? '']]" :label="ui('nav.practice_areas')">
                    @foreach (['S01' => $s01, 'S05' => $s05, 'S06' => $s06] as $code => $svc)
                        <x-d2.ui.tab-panel :id="'sg-night-'.strtolower($code)">
                            <p class="t-body">{{ $svc['home_summary'] ?? '' }}</p>
                        </x-d2.ui.tab-panel>
                    @endforeach
                </x-d2.ui.tabs>
                <p class="sg-label" {{ $en }}>{{ $t('tabs_night') }}</p>
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ overlays: toast, tooltip --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('overlays') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('overlays_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <div class="stack">
                <div class="toast toast--neutral is-static" data-overlay><p class="toast__text">{{ ui('cookies.saved') }}</p></div>
                <div class="toast toast--success is-static" data-overlay><p class="toast__text">{{ ui('faq.link_copied') }}</p></div>
                <div class="toast toast--danger is-static" data-overlay><p class="toast__text">{{ ui('faq.copy_failed') }}</p></div>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('toast_static') }}</p>
        </div>

        <div class="sg-specimen">
            <div class="cluster">
                <x-d2.ui.button variant="secondary" :data-toast="ui('faq.link_copied')" data-toast-tone="success" data-sg="toast-success" :lang="$en ? 'en' : null">{{ $t('toast_success') }}</x-d2.ui.button>
                <x-d2.ui.button variant="secondary" :data-toast="ui('faq.copy_failed')" data-toast-tone="danger" data-sg="toast-danger" :lang="$en ? 'en' : null">{{ $t('toast_danger') }}</x-d2.ui.button>
                <x-d2.ui.button variant="secondary" :data-toast="ui('cookies.saved')" data-sg="toast-neutral" :lang="$en ? 'en' : null">{{ $t('toast_neutral') }}</x-d2.ui.button>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('toast_live') }}</p>
        </div>

        <div class="sg-specimen">
            <div class="sg-row">
                <x-d2.ui.tooltip :text="ui('a11y.close_menu')">
                    <x-d2.ui.icon-button icon="close" :label="ui('a11y.close_menu')" variant="secondary" tip="none" data-sg="tip-icon" />
                </x-d2.ui.tooltip>
                <x-d2.ui.tooltip :text="setting('phone_display')" dir="ltr">
                    <x-d2.ui.button variant="whatsapp" :href="setting('whatsapp_url')" data-sg="tip-describe">{{ ui('cta.whatsapp') }}</x-d2.ui.button>
                </x-d2.ui.tooltip>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('tip_live') }}</p>
        </div>

        <div class="sg-specimen">
            <div class="stack">
                <span class="tooltip is-static" aria-hidden="true" data-overlay>{{ ui('a11y.close_menu') }}</span>
                <x-d2.ui.button variant="secondary" icon="phone" :href="setting('phone_href')" :data-tooltip="$hours" data-sg="tip-attr"><bdi dir="ltr">{{ setting('phone_display') }}</bdi></x-d2.ui.button>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('tip_static') }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ skeleton --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('skeleton') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('skeleton_note') }}</p>
    <div class="sg-grid">
        <div class="sg-specimen">
            <x-d2.ui.skeleton lines="3" />
            <p class="sg-label" {{ $en }}>{{ $t('skel_text') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.skeleton shape="card" />
            <p class="sg-label" {{ $en }}>{{ $t('skel_card') }}</p>
        </div>
        <div class="sg-specimen">
            <div class="stack">
                <x-d2.ui.skeleton shape="row" size="40" />
                <x-d2.ui.skeleton shape="row" size="40" />
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('skel_row') }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ callout --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('callout') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('callout_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <x-d2.ui.callout tone="info" :title="$scope['heading'] ?? null">
                @foreach ($scope['paragraphs'] ?? [] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </x-d2.ui.callout>
            <p class="sg-label" {{ $en }}>{{ $t('callout_info') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.callout tone="warning"><p>{{ ui('form.notice.sensitive') }}</p></x-d2.ui.callout>
            <p class="sg-label" {{ $en }}>{{ $t('callout_warning') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.callout tone="success"><p>{{ ui('form.state.success') }}</p></x-d2.ui.callout>
            <p class="sg-label" {{ $en }}>{{ $t('callout_success') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.callout tone="danger"><p>{{ ui('form.state.failure') }}</p></x-d2.ui.callout>
            <p class="sg-label" {{ $en }}>{{ $t('callout_danger') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.callout size="sm"><p>{{ ui('form.notice.engagement') }}</p></x-d2.ui.callout>
            <p class="sg-label" {{ $en }}>{{ $t('callout_sm') }}</p>
        </div>
    </div>

    {{-- dark: skeleton and callout --}}
    <div class="sg-night on-dark on-night">
        <div class="sg-grid sg-grid--wide">
            <div>
                <x-d2.ui.skeleton shape="card" />
                <p class="sg-label" {{ $en }}>{{ $t('skel_night') }}</p>
            </div>
            <div>
                <x-d2.ui.callout tone="info"><p>{{ ui('footer.disclaimer') }}</p></x-d2.ui.callout>
                <p class="sg-label" {{ $en }}>{{ $t('callout_night') }}</p>
            </div>
        </div>
    </div>
</section>
