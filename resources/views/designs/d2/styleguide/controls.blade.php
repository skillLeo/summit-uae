{{--
    Styleguide: Design 2.1 controls (brief §3.5; contract docs/d2.1/phase-1/components.md §1 and §2): buttons and
    their Design 2.0 aliases, links, icon buttons, chips, the segmented control, fields (input, select, textarea, date,
    stepper), checkbox, radio, badges, keys, progress and wizard steps, every state, on light and on dark.
    Annotations are internal: lang/en/styleguide_d2/controls.php (marked lang="en" on the Arabic and Russian pages).
    Demo content is approved copy (ui(), setting(), content()) or proposed copy marked @proposed. Forced states use
    the styleguide-only modifiers .is-hover / .is-active / .is-focus; everything else is live.
--}}
@php
    use App\Support\SiteUrl;

    $t = fn (string $key, array $replace = []) => __('styleguide_d2/controls.'.$key, $replace);
    $en = app()->isLocale('en') ? '' : 'lang=en';
    $states = ['default', 'hover', 'active', 'focus', 'disabled', 'loading'];
    $forced = fn (string $state) => ['hover' => 'is-hover', 'active' => 'is-active', 'focus' => 'is-focus'][$state] ?? '';

    $s01 = content()->service('S01') ?? [];
    $s05 = content()->service('S05') ?? [];
    $s06 = content()->service('S06') ?? [];
    $p01 = content()->page('P01')['content'] ?? [];
    $whoHeading = $p01['who_we_help']['heading'] ?? '';
    $audiences = array_map(fn ($item) => [$item['key'], $item['title']], $p01['who_we_help']['items'] ?? []);
    $languages = array_map(fn ($code) => [$code, ui('form.preferred_language.option.'.$code)], ['en', 'ar', 'ru']);
    $languageNames = array_map(fn ($code) => [$code, ui('lang.'.$code)], ['en', 'ar', 'ru']);
    $matters = array_map(fn ($code) => [$code, ui('form.matter_type.option.'.$code)], [...array_keys(config('summit.services')), 'other', 'unsure']);
    $site = content()->specialistSites()[0] ?? null;

    // Navigator situations by service (approved copy), for the option-group specimen.
    $situationGroups = [];
    foreach (['S01', 'S05', 'S06'] as $code) {
        $service = content()->service($code) ?? [];
        $list = array_values($service['situations'] ?? []);
        $situationGroups[$service['title'] ?? $code] = array_map(fn ($i) => [$code.'-'.($i + 1), $list[$i]], array_keys($list));
    }

    // FAQ categories with their question counts (filter chips).
    $faqCount = [];
    foreach (content()->faqs() as $faq) {
        $faqCount[$faq['category'] ?? ''] = ($faqCount[$faq['category'] ?? ''] ?? 0) + 1;
    }
    $faqCategories = array_slice(content()->faqCategories(), 0, 4);
    $firstCategory = $faqCategories[0] ?? ['name' => '', 'code' => ''];

    // Consent label: link only the approved "Privacy Policy" phrase to L01 (as the Design 1 form does).
    $consent = ui('form.consent.label');
    $linkText = ui('form.consent.link');
    $pos = mb_strpos($consent, $linkText);
    $consentHtml = $pos === false
        ? e($consent)
        : e(mb_substr($consent, 0, $pos)).'<a class="link" href="'.e(SiteUrl::page('L01')).'">'.e($linkText).'</a>'.e(mb_substr($consent, $pos + mb_strlen($linkText)));

    $documents = array_values($s06['documents'] ?? []);
    $documentsHeading = $s06['sections']['documents']['heading'] ?? '';
    $situations = array_values($s06['situations'] ?? []);
    $stepLabels = [ui('form.name.label'), ui('form.matter_type.label'), ui('form.attachment.label'), ui('form.preferred_language.label'), ui('form.heading')];

    // variant => [annotation, approved label]; the class follows the variant name (aliases: see the alias strip)
    $buttonRows = [
        'ink' => [$t('v_ink'), ui('nav.contact')],
        'gold' => [$t('v_gold'), ui('cta.request_consultation')],
        'secondary' => [$t('v_secondary'), ui('cta.view_all_practice_areas')],
        'text' => [$t('v_text'), ui('cta.view_all_faqs')],
        'whatsapp' => [$t('v_whatsapp'), ui('cta.whatsapp')],
    ];
@endphp
<section class="sg-section" id="sg-controls">
    <h2 {{ $en }}>{{ $t('title') }}</h2>
    <p {{ $en }}>{{ $t('intro') }}</p>

    {{-- ================================================================== buttons --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('buttons') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('buttons_note') }}</p>

    <div class="sg-grid sg-grid--full" data-sg="buttons">
        @foreach ($buttonRows as $variant => [$note, $text])
            <div class="sg-specimen" data-sg="btn-{{ $variant }}">
                <p class="sg-label" {{ $en }}><code class="sg-code">btn--{{ $variant }}</code> {{ $note }}</p>
                <div class="sg-states">
                    @foreach ($states as $state)
                        <div>
                            <x-d2.ui.button :variant="$variant" :class="$forced($state)" :disabled="$state === 'disabled'" :loading="$state === 'loading'">{{ $text }}</x-d2.ui.button>
                            <span class="sg-label" {{ $en }}>{{ $t('state_'.$state) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <p class="sg-label" {{ $en }}>{{ $t('sizes') }}</p>
    <div class="sg-grid sg-grid--wide" data-sg="btn-sizes">
        <div class="sg-specimen">
            <div class="sg-states">
                <div>
                    <x-d2.ui.button variant="ink" :href="SiteUrl::consultation()">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
                    <span class="sg-label"><code class="sg-code">btn--ink btn--48</code></span>
                </div>
                <div>
                    <x-d2.ui.button variant="secondary" size="44" :href="SiteUrl::page('P02')">{{ ui('cta.view_all_practice_areas') }}</x-d2.ui.button>
                    <span class="sg-label"><code class="sg-code">btn--secondary btn--44</code></span>
                </div>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('size_default') }}</p>
        </div>
        <div class="sg-specimen">
            <div class="sg-states">
                <div>
                    <x-d2.ui.button variant="gold" size="52" :href="SiteUrl::consultation()">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
                    <span class="sg-label"><code class="sg-code">btn--gold btn--52</code></span>
                </div>
                <div>
                    <x-d2.ui.button variant="whatsapp" size="52" :href="setting('whatsapp_url')">{{ ui('cta.whatsapp') }}</x-d2.ui.button>
                    <span class="sg-label"><code class="sg-code">btn--whatsapp btn--52</code></span>
                </div>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('size_hero') }}</p>
        </div>
    </div>

    <p class="sg-label" {{ $en }}>{{ $t('slots') }}</p>
    <div class="sg-grid sg-grid--wide" data-sg="btn-slots">
        <div class="sg-specimen">
            <div class="cluster">
                <x-d2.ui.button variant="secondary" icon="search">{{ ui('faq.search_submit') }}</x-d2.ui.button>
                <x-d2.ui.button variant="secondary" icon="phone" :href="setting('phone_href')"><bdi dir="ltr">{{ setting('phone_display') }}</bdi></x-d2.ui.button>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('slot_leading') }}</p>
        </div>
        @if ($site)
            <div class="sg-specimen">
                <x-d2.ui.button variant="secondary" icon-end="external" :href="$site['url']" target="_blank">{{ $site['label'] }}</x-d2.ui.button>
                <p class="sg-label" {{ $en }}>{{ $t('slot_external') }}</p>
            </div>
        @endif
        <div class="sg-specimen">
            <div class="cluster">
                <x-d2.ui.button variant="secondary" icon="form-back" data-sg="btn-form-back"><span @proposed>{{ d2p('wizard.back') }}</span></x-d2.ui.button>
                <x-d2.ui.button variant="ink" icon-end="form-next" data-sg="btn-form-next"><span @proposed>{{ d2p('wizard.next') }}</span></x-d2.ui.button>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('slot_form') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.button variant="text" icon-end="chevron-down" aria-expanded="false">{{ ui('faq.expand_all') }}</x-d2.ui.button>
            <p class="sg-label" {{ $en }}>{{ $t('slot_disclosure') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.button variant="ink" icon-end="arrow" :href="SiteUrl::service('S06')" data-sg="btn-no-arrow">{{ ui('cta.explore_service') }}</x-d2.ui.button>
            <p class="sg-label" {{ $en }}>{{ $t('slot_no_arrow') }}</p>
        </div>
        <div class="sg-specimen">
            <div class="cluster">
                <x-d2.ui.button variant="secondary" icon="search">{{ ui('faq.search_submit') }}</x-d2.ui.button>
                <x-d2.ui.button variant="secondary" icon="search" :loading="true">{{ ui('faq.search_submit') }}</x-d2.ui.button>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('slot_loading') }}</p>
        </div>
        <div class="sg-specimen" data-sg="btn-full">
            <div class="stack">
                <x-d2.ui.button variant="gold" size="52" :full="true" :href="SiteUrl::consultation()">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
                <x-d2.ui.button variant="whatsapp" size="52" :full="true" :href="setting('whatsapp_url')">{{ ui('cta.whatsapp') }}</x-d2.ui.button>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('full') }}</p>
        </div>
    </div>

    <p class="sg-label" {{ $en }}>{{ $t('aliases') }}</p>
    <div class="sg-grid sg-grid--full" data-sg="btn-aliases">
        <div class="sg-specimen">
            <div class="sg-states">
                @foreach (['primary' => 'ink', 'signature' => 'gold', 'ghost' => 'text'] as $old => $new)
                    <div>
                        <x-d2.ui.button :variant="$old">{{ ui('nav.contact') }}</x-d2.ui.button>
                        <span class="sg-label"><code class="sg-code">{{ $old }} = {{ $new }}</code></span>
                    </div>
                @endforeach
                <div>
                    <x-d2.ui.button variant="secondary" size="56">{{ ui('nav.contact') }}</x-d2.ui.button>
                    <span class="sg-label"><code class="sg-code">56 = 52</code></span>
                </div>
                <div>
                    <x-d2.ui.button variant="secondary" size="40">{{ ui('nav.contact') }}</x-d2.ui.button>
                    <span class="sg-label"><code class="sg-code">40 = 44</code></span>
                </div>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('aliases_note') }}</p>
        </div>
    </div>

    {{-- ================================================================== links --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('links') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('links_note') }}</p>
    <div class="sg-grid" data-sg="links">
        <div class="sg-specimen">
            <p class="t-body"><x-d2.ui.link :href="SiteUrl::page('L01')">{{ $linkText }}</x-d2.ui.link></p>
            <p class="sg-label" {{ $en }}>{{ $t('link_body') }}</p>
        </div>
        <div class="sg-specimen">
            <div class="cluster">
                <x-d2.ui.link variant="nav" :href="SiteUrl::home()" :current="true">{{ ui('nav.home') }}</x-d2.ui.link>
                <x-d2.ui.link variant="nav" :href="SiteUrl::page('P06')">{{ ui('nav.about') }}</x-d2.ui.link>
                <x-d2.ui.link variant="nav" :href="SiteUrl::page('P07')">{{ ui('nav.contact') }}</x-d2.ui.link>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('link_nav') }}</p>
        </div>
        @if ($site)
            <div class="sg-specimen">
                <p class="t-body"><x-d2.ui.link :href="$site['url']" :external="true">{{ $site['label'] }}</x-d2.ui.link></p>
                <p class="sg-label" {{ $en }}>{{ $t('link_external') }}</p>
            </div>
        @endif
    </div>

    {{-- ================================================================== icon buttons --}}
    {{-- The forced tooltips start at the button's inline start (top-start), so they stay inside their frames at 390 px. --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('icon_buttons') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('icon_buttons_note') }}</p>
    @foreach (['ghost' => ['close', ui('a11y.close_menu')], 'secondary' => ['search', ui('faq.search_submit')]] as $variant => [$icon, $label])
        <p class="sg-label" {{ $en }}><code class="sg-code">icon-btn btn--{{ $variant }}</code> {{ $t('ib_'.$variant) }}</p>
        <div class="sg-grid sg-grid--full" data-sg="ib-{{ $variant }}">
            <div class="sg-specimen">
                <div class="sg-states">
                    @foreach ($states as $state)
                        <div>
                            <x-d2.ui.icon-button :icon="$icon" :label="$label" :variant="$variant" size="44" :class="$forced($state)" :disabled="$state === 'disabled'" :loading="$state === 'loading'" :tip="$state === 'hover' ? 'top-start' : 'none'" />
                            <span class="sg-label" {{ $en }}>{{ $t('state_'.$state) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
    <div class="sg-grid sg-grid--full">
        <div class="sg-specimen">
            <div class="sg-row" data-sg="ib-live">
                <x-d2.ui.icon-button icon="menu" :label="ui('a11y.open_menu')" variant="secondary" />
                <x-d2.ui.icon-button icon="close" :label="ui('a11y.close_menu')" size="44" />
                <x-d2.ui.icon-button icon="search" :label="ui('faq.search_submit')" size="48" variant="secondary" />
                <x-d2.ui.icon-button icon="carousel-prev" :label="d2p('mobile.carousel.prev')" variant="secondary" :data-copy-status="app()->isProduction() ? null : 'proposed'" />
                <x-d2.ui.icon-button icon="carousel-next" :label="d2p('mobile.carousel.next')" variant="secondary" :data-copy-status="app()->isProduction() ? null : 'proposed'" />
                <x-d2.ui.icon-button icon="whatsapp" :label="ui('cta.whatsapp')" variant="secondary" size="48" :href="setting('whatsapp_url')" target="_blank" tip="top-end" />
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('ib_live') }}</p>
        </div>
    </div>

    {{-- ================================================================== chips --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('chips') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('chips_note') }}</p>
    <div class="sg-grid sg-grid--full">
        <div class="sg-specimen" data-sg="chip-filter">
            <div class="sg-states">
                @foreach (['default', 'hover', 'active', 'focus', 'pressed', 'pressed_hover', 'disabled'] as $state)
                    <div>
                        <x-d2.ui.chip :pressed="str_starts_with($state, 'pressed')" :count="$faqCount[$firstCategory['code']] ?? null" :disabled="$state === 'disabled'"
                            :class="$state === 'pressed_hover' ? 'is-hover' : $forced($state)">{{ $firstCategory['name'] }}</x-d2.ui.chip>
                        <span class="sg-label" {{ $en }}>{{ $t('state_'.$state) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="sg-specimen">
            <div class="cluster" data-sg="chip-row">
                @foreach ($faqCategories as $i => $category)
                    <x-d2.ui.chip :pressed="$i === 0" :count="$faqCount[$category['code']] ?? null">{{ $category['name'] }}</x-d2.ui.chip>
                @endforeach
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('c_live') }}</p>
        </div>
        <div class="sg-specimen">
            <div class="cluster" data-sg="chip-static">
                <x-d2.ui.chip as="span" icon="contract">{{ $s06['title'] ?? '' }}</x-d2.ui.chip>
                <x-d2.ui.chip as="span" :pressed="true">{{ $s05['title'] ?? '' }}</x-d2.ui.chip>
                @foreach (array_slice($languageNames, 0, 3) as [$code, $name])
                    <x-d2.ui.chip :href="SiteUrl::home($code)" :pressed="$code === app()->getLocale()" hreflang="{{ $code }}">{{ $name }}</x-d2.ui.chip>
                @endforeach
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('c_static') }}</p>
        </div>
    </div>

    {{-- ================================================================== segmented --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('segmented') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('segmented_note') }}</p>
    <div class="sg-grid sg-grid--wide" data-sg="segmented">
        <div class="sg-specimen">
            <x-d2.ui.segmented name="sg_seg_audience" :options="array_slice($audiences, 0, 3)" :value="$audiences[0][0] ?? null" :label="$whoHeading" data-sg="seg-live" />
            <p class="sg-label" {{ $en }}>{{ $t('seg_live') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.segmented name="sg_seg_two" :options="array_slice($languageNames, 0, 2)" value="ar" :label="ui('a11y.choose_language')" size="40" />
            <p class="sg-label" {{ $en }}>{{ $t('seg_40') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.segmented name="sg_seg_hover" :options="array_slice($audiences, 0, 3)" :value="$audiences[1][0] ?? null" :label="$whoHeading" class="is-hover" />
            <p class="sg-label" {{ $en }}>{{ $t('state_hover') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.segmented name="sg_seg_focus" :options="array_slice($audiences, 0, 3)" :value="$audiences[2][0] ?? null" :label="$whoHeading" class="is-focus" />
            <p class="sg-label" {{ $en }}>{{ $t('state_focus') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.segmented name="sg_seg_disabled" :options="[$languages[0], [$languages[1][0], $languages[1][1], true], $languages[2]]" value="en" :label="ui('form.preferred_language.label')" :label-visible="true" />
            <p class="sg-label" {{ $en }}>{{ $t('seg_disabled') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.segmented name="sg_seg_full" :options="$languages" value="ru" :label="ui('form.preferred_language.label')" :full="true" />
            <p class="sg-label" {{ $en }}>{{ $t('seg_full') }}</p>
        </div>
    </div>

    {{-- ================================================================== fields --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('fields') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('fields_note') }}</p>
    <p class="t-small sg-note">{{ ui('form.required_note') }}</p>

    <p class="sg-label" {{ $en }}><code class="sg-code">x-d2.ui.field + x-d2.ui.input</code> {{ $t('input') }}</p>
    <div class="sg-grid" data-sg="input">
        @foreach (['default', 'hover', 'focus', 'filled', 'disabled', 'readonly'] as $state)
            <div class="sg-specimen">
                <x-d2.ui.field :id="'sg-in-'.$state" :label="ui('form.name.label')" :hint="ui('form.name.help')" :required="true">
                    <x-d2.ui.input :name="'sg_in_'.$state" autocomplete="off" :class="$forced($state)"
                        :value="in_array($state, ['filled', 'disabled', 'readonly'], true) ? $t('sample_name') : null"
                        :disabled="$state === 'disabled'" :readonly="$state === 'readonly'" />
                </x-d2.ui.field>
                <p class="sg-label" {{ $en }}>{{ $t('state_'.$state) }}</p>
            </div>
        @endforeach
        <div class="sg-specimen">
            <x-d2.ui.field id="sg-in-error" :label="ui('form.name.label')" :hint="ui('form.name.help')" :error="ui('form.name.error')" :required="true">
                <x-d2.ui.input name="sg_in_error" autocomplete="off" />
            </x-d2.ui.field>
            <p class="sg-label" {{ $en }}>{{ $t('state_error') }}</p>
        </div>
    </div>

    {{-- Values in full (D09, brief §3.6 rule 8): the approved e-mail and telephone sit in columns wide enough for them
         (fields fill their column, §3.5), and the long approved search example wraps inside its field instead of being
         cut at the edge (wrap-placeholder: on a phone two or three lines, the field growing while it is empty). --}}
    <div class="sg-grid sg-grid--wide" data-sg="input-values">
        <div class="sg-specimen">
            <x-d2.ui.field id="sg-in-success" :label="ui('form.email.label')" :hint="ui('form.email.help')">
                <x-d2.ui.input name="sg_in_success" type="email" dir="ltr" :value="setting('email')" :valid="true" autocomplete="off" />
            </x-d2.ui.field>
            <p class="sg-label" {{ $en }}>{{ $t('state_success') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.field id="sg-in-optional" :label="ui('form.phone.label')" :hint="ui('form.phone.help')" :optional-label="true">
                <x-d2.ui.input name="sg_in_optional" type="tel" inputmode="tel" dir="ltr" :value="setting('phone_display')" autocomplete="off" />
            </x-d2.ui.field>
            <p class="sg-label" {{ $en }}>{{ $t('f_optional') }}</p>
        </div>
        <div class="sg-specimen span-full" data-sg="input-wrap">
            <x-d2.ui.field id="sg-in-search" :label="ui('faq.search_label')" :label-hidden="true">
                <x-d2.ui.input name="sg_in_search" type="search" icon="search" :placeholder="ui('faq.search_placeholder')" wrap-placeholder />
            </x-d2.ui.field>
            <p class="sg-label" {{ $en }}>{{ $t('f_icon') }}; <code class="sg-code">wrap-placeholder</code> {{ $t('f_wrap') }}</p>
        </div>
    </div>

    <p class="sg-label" {{ $en }}>{{ $t('columns') }}</p>
    <div class="sg-grid" data-sg="field-columns">
        <div class="sg-specimen">
            <x-d2.ui.field id="sg-col-input" :label="ui('form.deadline_details.label')">
                <x-d2.ui.input name="sg_col_input" autocomplete="off" />
            </x-d2.ui.field>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.field id="sg-col-step" :label="$t('stepper_label')" :lang="$en ? 'en' : null">
                <x-d2.ui.stepper name="sg_col_step" min="0" max="5" value="2" />
            </x-d2.ui.field>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.field id="sg-col-select" :label="ui('form.preferred_language.label')">
                <x-d2.ui.select name="sg_col_select" :options="$languages" :placeholder="ui('form.preferred_language.placeholder')" />
            </x-d2.ui.field>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.field id="sg-col-date" :label="ui('form.deadline_date.label')">
                <x-d2.ui.date name="sg_col_date" value="2026-12-15" />
            </x-d2.ui.field>
        </div>
    </div>

    <p class="sg-label" {{ $en }}><code class="sg-code">x-d2.ui.select</code> {{ $t('select') }}</p>
    <div class="sg-grid" data-sg="select">
        @foreach (['default', 'hover', 'focus', 'filled', 'disabled'] as $state)
            <div class="sg-specimen">
                <x-d2.ui.field :id="'sg-sel-'.$state" :label="ui('form.preferred_language.label')">
                    <x-d2.ui.select :name="'sg_sel_'.$state" :options="$languages" :placeholder="ui('form.preferred_language.placeholder')"
                        :value="in_array($state, ['filled', 'disabled'], true) ? 'ar' : null" :class="$forced($state)" :disabled="$state === 'disabled'" />
                </x-d2.ui.field>
                <p class="sg-label" {{ $en }}>{{ $t('state_'.$state) }}</p>
            </div>
        @endforeach
        <div class="sg-specimen">
            <x-d2.ui.field id="sg-sel-error" :label="ui('form.matter_type.label')" :error="ui('form.state.summary')">
                <x-d2.ui.select name="sg_sel_error" :options="$matters" :placeholder="ui('form.matter_type.placeholder')" />
            </x-d2.ui.field>
            <p class="sg-label" {{ $en }}>{{ $t('state_error') }}</p>
        </div>
        <div class="sg-specimen">
            {{-- the English specimen label carries lang="en" (the options are approved copy in the page language) --}}
            <x-d2.ui.field id="sg-sel-groups" :label="new \Illuminate\Support\HtmlString('<span '.$en.'>'.e($t('select_groups')).'</span>')">
                <x-d2.ui.select name="sg_sel_groups" :options="$situationGroups" value="S06-2" />
            </x-d2.ui.field>
            <p class="sg-label"><code class="sg-code">optgroup</code></p>
        </div>
    </div>

    <p class="sg-label" {{ $en }}><code class="sg-code">x-d2.ui.textarea</code> {{ $t('textarea') }}, <code class="sg-code">x-d2.ui.date</code> {{ $t('date') }}</p>
    <div class="sg-grid" data-sg="textarea-date">
        <div class="sg-specimen">
            <x-d2.ui.field id="sg-ta-default" :label="ui('form.summary.label')" :hint="ui('form.summary.help')" :required="true">
                <x-d2.ui.textarea name="sg_ta_default" rows="4" />
            </x-d2.ui.field>
            <p class="sg-label" {{ $en }}>{{ $t('state_default') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.field id="sg-ta-error" :label="ui('form.summary.label')" :error="ui('form.summary.error')" :required="true">
                <x-d2.ui.textarea name="sg_ta_error" rows="4" class="is-focus" />
            </x-d2.ui.field>
            <p class="sg-label" {{ $en }}>{{ $t('state_error_focus') }}</p>
        </div>
        @foreach (['empty', 'filled', 'focus', 'error', 'disabled'] as $state)
            <div class="sg-specimen">
                <x-d2.ui.field :id="'sg-date-'.$state" :label="ui('form.deadline_date.label')" :hint="$state === 'empty' ? ui('form.deadline_date.help') : null"
                    :error="$state === 'error' ? ui('form.deadline_date.error') : null">
                    <x-d2.ui.date :name="'sg_date_'.$state" :value="in_array($state, ['filled', 'error', 'disabled'], true) ? '2026-12-15' : null"
                        :class="$forced($state)" :disabled="$state === 'disabled'" />
                </x-d2.ui.field>
                <p class="sg-label" {{ $en }}>{{ $t('state_'.$state) }}</p>
            </div>
        @endforeach
    </div>

    <p class="sg-label" {{ $en }}><code class="sg-code">x-d2.ui.stepper</code> {{ $t('stepper_note') }}</p>
    <div class="sg-grid" data-sg="stepper">
        @foreach (['default' => 2, 'min' => 0, 'max' => 5, 'focus' => 3, 'disabled' => 1, 'error' => 7] as $state => $number)
            <div class="sg-specimen">
                <x-d2.ui.field :id="'sg-step-'.$state" :label="$t('stepper_label')" :lang="$en ? 'en' : null">
                    <x-d2.ui.stepper :name="'sg_step_'.$state" min="0" max="5" :value="$number" :class="$forced($state)"
                        :disabled="$state === 'disabled'" :invalid="$state === 'error'" />
                </x-d2.ui.field>
                <p class="sg-label" {{ $en }}>{{ $t('state_'.$state) }}</p>
            </div>
        @endforeach
    </div>

    {{-- ================================================================== checkbox + radio --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('checks') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('checks_note') }}</p>
    <div class="sg-grid" data-sg="checkbox">
        @foreach (['unchecked' => false, 'hover' => false, 'focus' => false, 'checked' => true, 'indeterminate' => false, 'disabled' => true] as $state => $checked)
            <div class="sg-specimen">
                <x-d2.ui.checkbox :id="'sg-cb-'.$state" :name="'sg_cb_'.$state" value="1" :checked="$checked" :label="$documents[0] ?? ''"
                    :class="$state === 'indeterminate' ? 'is-indeterminate' : $forced($state)" :disabled="$state === 'disabled'" />
                <p class="sg-label" {{ $en }}>{{ $t('state_'.$state) }}</p>
            </div>
        @endforeach
        <div class="sg-specimen">
            <x-d2.ui.checkbox id="sg-cb-error" name="sg_cb_error" value="1" :error="ui('form.consent.error')">{!! $consentHtml !!}</x-d2.ui.checkbox>
            <p class="sg-label" {{ $en }}>{{ $t('state_error') }}</p>
        </div>
    </div>

    <div class="sg-grid sg-grid--wide" data-sg="checks-live">
        <div class="sg-specimen">
            <div class="stack" role="group" aria-labelledby="sg-cl-heading">
                <p class="t-ui" id="sg-cl-heading">{{ $documentsHeading }}</p>
                <div>
                    @foreach ($documents as $i => $document)
                        <x-d2.ui.checkbox :id="'sg-cl-'.$i" name="sg_cl[]" :value="$i" :checked="$i < 2" :label="$document" />
                    @endforeach
                </div>
                <x-d2.ui.progress :value="2" :max="count($documents)" :aria-label="$documentsHeading" value-text="2 / {{ count($documents) }}" />
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('check_list') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.field as="fieldset" id="sg-situation" :label="ui('service.situations_intro')">
                <div>
                    @foreach ($situations as $i => $situation)
                        <x-d2.ui.radio :id="'sg-situation-'.$i" name="sg_situation" :value="'S06-'.($i + 1)" :checked="$i === 0" :label="$situation" />
                    @endforeach
                </div>
            </x-d2.ui.field>
            <p class="sg-label" {{ $en }}>{{ $t('radio_group') }}</p>
        </div>
    </div>

    <div class="sg-grid" data-sg="radio">
        @foreach (['unchecked' => false, 'hover' => false, 'focus' => false, 'checked' => true, 'disabled' => true] as $state => $checked)
            <div class="sg-specimen">
                <x-d2.ui.radio :id="'sg-rd-'.$state" :name="'sg_rd_'.$state" value="ar" :checked="$checked" :label="$languageNames[1][1]"
                    :class="$forced($state)" :disabled="$state === 'disabled'" />
                <p class="sg-label" {{ $en }}>{{ $t('state_'.$state) }}</p>
            </div>
        @endforeach
    </div>

    {{-- ================================================================== badges, keys, progress --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('badges_keys_progress') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('badges_note') }}</p>
    <div class="sg-grid" data-sg="badges">
        <div class="sg-specimen">
            <div class="cluster">
                @foreach (['info', 'success', 'warning', 'danger'] as $tone)
                    <x-d2.ui.badge :tone="$tone" :lang="$en ? 'en' : null">{{ $t('tone_'.$tone) }}</x-d2.ui.badge>
                @endforeach
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('badges_status') }}</p>
        </div>
        <div class="sg-specimen">
            <div class="cluster">
                <x-d2.ui.badge tone="neutral" :lang="$en ? 'en' : null">{{ $t('tone_neutral') }}</x-d2.ui.badge>
                <x-d2.ui.badge tone="brand" :lang="$en ? 'en' : null">{{ $t('tone_brand') }}</x-d2.ui.badge>
                <x-d2.ui.badge tone="bronze" :lang="$en ? 'en' : null">{{ $t('tone_bronze') }}</x-d2.ui.badge>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('badges_plain') }}</p>
        </div>
        <div class="sg-specimen">
            <div class="cluster">
                <x-d2.ui.kbd>/</x-d2.ui.kbd>
                <x-d2.ui.kbd :keys="['Ctrl', 'K']" />
                <x-d2.ui.kbd :keys="['⌘', 'K']" />
                <x-d2.ui.kbd>Esc</x-d2.ui.kbd>
                <x-d2.ui.kbd>Enter</x-d2.ui.kbd>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('kbd_note') }}</p>
        </div>
        <div class="sg-specimen">
            <div class="stack">
                @foreach ([0, 2, 4] as $done)
                    <x-d2.ui.progress :value="$done" :max="4" :label="$documentsHeading" value-text="{{ $done }} / 4" />
                @endforeach
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('progress_note') }}</p>
        </div>
    </div>

    {{-- ================================================================== wizard steps --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('steps') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('steps_note') }}</p>
    <div class="sg-grid sg-grid--full" data-sg="steps">
        @foreach (['steps_first' => [1, false], 'steps_middle' => [3, false], 'steps_interactive' => [4, true], 'steps_done' => [6, false]] as $key => [$current, $interactive])
            <div class="sg-specimen">
                <x-d2.ui.steps :steps="$stepLabels" :current="$current" :interactive="$interactive" :label="ui('form.heading')" />
                <p class="sg-label" {{ $en }}>{{ $t($key) }}</p>
            </div>
        @endforeach
    </div>

    {{-- ================================================================== on dark --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('dark') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('dark_note') }}</p>
    <div class="sg-night on-dark on-night" data-sg="controls-dark">
        <div class="sg-grid sg-grid--full">
            @foreach (['ink' => [$t('v_ink_dark'), ui('cta.request_consultation')], 'night' => [$t('v_night'), ui('cta.whatsapp')], 'secondary' => [$t('v_secondary_dark'), ui('cta.view_all_case_studies')], 'text' => [$t('v_text'), ui('cta.view_all_faqs')], 'whatsapp' => [$t('v_whatsapp_dark'), ui('cta.whatsapp')]] as $variant => [$note, $text])
                <div class="sg-specimen" data-sg="dark-btn-{{ $variant }}">
                    <p class="sg-label" {{ $en }}><code class="sg-code">btn--{{ $variant }}</code> {{ $note }}</p>
                    <div class="sg-states">
                        @foreach (['default', 'hover', 'focus', 'disabled'] as $state)
                            <div>
                                <x-d2.ui.button :variant="$variant" :class="$forced($state)" :disabled="$state === 'disabled'" :icon="$variant === 'night' ? 'whatsapp' : null">{{ $text }}</x-d2.ui.button>
                                <span class="sg-label" {{ $en }}>{{ $t('state_'.$state) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="sg-specimen" data-sg="dark-ib-chips">
                <p class="sg-label" {{ $en }}><code class="sg-code">icon-btn btn--night</code>, <code class="sg-code">chip--night</code></p>
                <div class="sg-states">
                    @foreach (['default', 'hover', 'focus', 'disabled'] as $state)
                        <div>
                            <div class="cluster">
                                <x-d2.ui.icon-button icon="close" :label="ui('a11y.close_menu')" variant="night" size="44" :class="$forced($state)" :disabled="$state === 'disabled'" tip="none" />
                                <x-d2.ui.chip variant="night" :class="$forced($state)" :disabled="$state === 'disabled'" :pressed="false">{{ $s01['title'] ?? '' }}</x-d2.ui.chip>
                            </div>
                            <span class="sg-label" {{ $en }}>{{ $t('state_'.$state) }}</span>
                        </div>
                    @endforeach
                    <div>
                        <div class="cluster">
                            <x-d2.ui.chip variant="night" :pressed="true" :count="count($documents)">{{ $s06['title'] ?? '' }}</x-d2.ui.chip>
                        </div>
                        <span class="sg-label" {{ $en }}>{{ $t('state_pressed') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="sg-grid sg-grid--wide" data-sg="dark-fields">
            <div>
                <x-d2.ui.segmented name="sg_seg_dark" :options="array_slice($audiences, 0, 3)" :value="$audiences[0][0] ?? null" :label="$whoHeading" />
                <p class="sg-label" {{ $en }}>{{ $t('segmented') }}</p>
            </div>
            <div>
                <x-d2.ui.field id="sg-dark-search" :label="ui('faq.search_label')">
                    <x-d2.ui.input name="sg_dark_search" type="search" icon="search" :placeholder="ui('faq.search_placeholder')" wrap-placeholder />
                </x-d2.ui.field>
                <p class="sg-label" {{ $en }}>{{ $t('state_default') }}, <code class="sg-code">wrap-placeholder</code></p>
            </div>
            <div>
                <x-d2.ui.field id="sg-dark-select" :label="ui('form.matter_type.label')" :error="ui('form.state.summary')">
                    <x-d2.ui.select name="sg_dark_select" :options="$matters" :placeholder="ui('form.matter_type.placeholder')" class="is-focus" />
                </x-d2.ui.field>
                <p class="sg-label" {{ $en }}>{{ $t('state_error_focus') }}</p>
            </div>
            <div>
                <x-d2.ui.field id="sg-dark-step" :label="$t('stepper_label')" :lang="$en ? 'en' : null">
                    <x-d2.ui.stepper name="sg_dark_step" min="0" max="5" value="2" />
                </x-d2.ui.field>
                <p class="sg-label" {{ $en }}>{{ $t('stepper') }}</p>
            </div>
            <div>
                @foreach (array_slice($documents, 0, 2) as $i => $document)
                    <x-d2.ui.checkbox :id="'sg-dark-cb-'.$i" name="sg_dark_cb[]" :value="$i" :checked="$i === 0" :label="$document" :class="$i === 1 ? 'is-focus' : null" />
                @endforeach
                <p class="sg-label" {{ $en }}>{{ $t('state_checked_focus') }}</p>
            </div>
            <div>
                @foreach (array_slice($situations, 0, 2) as $i => $situation)
                    <x-d2.ui.radio :id="'sg-dark-rd-'.$i" name="sg_dark_rd" :value="$i" :checked="$i === 0" :label="$situation" />
                @endforeach
                <p class="sg-label" {{ $en }}>{{ $t('radio_group') }}</p>
            </div>
            <div>
                <div class="stack">
                    <x-d2.ui.progress :value="3" :max="4" :label="$documentsHeading" value-text="3 / 4" />
                    <div class="cluster">
                        <x-d2.ui.badge tone="night" :lang="$en ? 'en' : null">{{ $t('tone_night') }}</x-d2.ui.badge>
                        <x-d2.ui.kbd>Esc</x-d2.ui.kbd>
                        <x-d2.ui.kbd :keys="['Ctrl', 'K']" />
                    </div>
                </div>
                <p class="sg-label" {{ $en }}>{{ $t('progress_badge_kbd') }}</p>
            </div>
            <div>
                <x-d2.ui.card as="div" class="on-light">
                    <div>
                        @foreach (array_slice($documents, 0, 2) as $i => $document)
                            <x-d2.ui.checkbox :id="'sg-light-cb-'.$i" name="sg_light_cb[]" :value="$i" :checked="$i === 0" :label="$document" />
                        @endforeach
                    </div>
                    <x-d2.ui.progress :value="1" :max="2" :aria-label="$documentsHeading" value-text="1 / 2" />
                    <div class="cluster">
                        <x-d2.ui.button variant="ink" :href="SiteUrl::consultation()">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
                        <x-d2.ui.icon-button icon="close" :label="ui('a11y.close_menu')" variant="secondary" class="is-focus" tip="none" />
                    </div>
                </x-d2.ui.card>
                <p class="sg-label" {{ $en }}>{{ $t('dark_light_card') }}</p>
            </div>
        </div>
    </div>
</section>
