@props([
    'idPrefix' => 'rent',   // prefix of every id and field name inside (unique per tool on the page)
    'values' => [],         // initial values (styleguide states): current, average, expiry, notice
    'headingLevel' => 3,    // the tool title (visually hidden: the switch names it)
])
{{--
    <x-d2.tool.rent /> : Dubai rent increase check (upgrade spec §5.3, §12 C6). Bands and notice check:
    resources/js/d2/tools/rent.js (pure, Vitest); the page script: resources/js/d2/tools/rent-view.js
    (Alpine d2ToolRent). Styles: resources/css/d2/sections/tools.css.

    The visitor enters the average rent from the official DLD Rental Index (a link opens it in a new tab;
    Summit never fetches or scrapes it). The five-step band scale is server-rendered, so it explains the
    bands without JavaScript too; the script highlights the visitor's band, shows the maximum increase in
    % and AED and checks the 90-day notice. No <form>: nothing is ever submitted or put in a URL.
    "Request a Legal Consultation" calls prefill({ service: 'S05', tool: 'rent', summary }).
    Design 2.1 (Phase 1): no eyebrow over the result (D01), no decorative icon on the copy button (D04); the
    notice check shows only the allowed state icons (info while waiting, check, warning). Phase 2: the maximum
    increase reads "—" until both rents are valid (D31); the dates are <x-d2.ui.date> (D36).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Prefill;

    $p = $idPrefix;
    $t = fn (string $key, array $replace = []) => d2p('tools.rent.'.$key, $replace);
    $c = fn (string $key) => d2p('tools.common.'.$key);
    $e = fn (string $code) => d2p('tools.errors.'.$code);
    $val = fn (string $key) => isset($values[$key]) && $values[$key] !== '' && $values[$key] !== null ? (string) $values[$key] : null;
    $proposed = app()->isProduction() ? null : 'proposed';
    $level = max(2, min(5, (int) $headingLevel));
    $requestUrl = Prefill::url(['service' => 'S05', 'tool' => 'rent']);
    $empty = $c('empty_value');
    $bands = (array) __('proposed.d2.tools.rent.bands');
    // Figures keep their left-to-right order inside Arabic text ("10–20%", never "%20–10"), like the
    // Intl percentages the script prints: each run of digits, separators and % becomes a <bdi dir="ltr">.
    $figures = fn (string $text) => new \Illuminate\Support\HtmlString(
        preg_replace('/[0-9][0-9.,–\-\x{00A0}]*%?/u', '<bdi dir="ltr">$0</bdi>', e($text)) ?? e($text)
    );

    $errorKeys = ['date', 'not_number', 'negative', 'positive', 'too_large'];
    $config = [
        'tool' => 'rent',
        'service' => 'S05',
        'errors' => array_combine($errorKeys, array_map($e, $errorKeys)),
        'text' => [
            'empty' => $empty,
            'copied' => $c('copied'),
            'copyFailed' => $c('copy_failed'),
            'estimateOnly' => $c('estimate_only'),
            'notEntered' => $c('not_entered'),
            'perYear' => $t('result.per_year'),
            'newRent' => $t('result.new_rent'),
            'gapBelow' => $t('result.gap_below'),
            'gapNotBelow' => $t('result.gap_not_below'),
            'noticeOk' => $t('result.notice_ok'),
            'noticeShort' => $t('result.notice_short'),
            'noticeAfter' => $t('result.notice_after'),
            'noticeMissing' => $t('result.notice_missing'),
            'live' => $t('live'),
        ],
        'summary' => (array) __('proposed.d2.tools.rent.summary'),
    ];
    $field = fn (string $key, ?string $hint = null) => ['id' => $p.'-'.$key, 'label' => $t('fields.'.$key), 'hint' => $hint ? $t('fields.'.$hint) : null];
@endphp
<div {{ $attributes->class(['tool', 'tool--rent']) }} id="{{ $p }}" x-data="d2ToolRent" data-tool="rent">
    <x-d2.tool.config :data="$config" :plurals="['days' => 'tools.common.days']" />
    <h{{ $level }} class="sr-only" id="{{ $p }}-title" @proposed>{{ $t('title') }}</h{{ $level }}>

    <div class="tool__grid">
        <div class="tool__form" role="group" aria-labelledby="{{ $p }}-title">
            <div class="tool__fields">
                @php($f = $field('current', 'current_hint'))
                <x-d2.ui.field :id="$f['id']" :label="$f['label']" :hint="$f['hint']" :error="$e('positive')" :invalid="false" :data-copy-status="$proposed">
                    <x-d2.ui.input :name="$p.'_current'" inputmode="decimal" autocomplete="off" spellcheck="false" :value="$val('current')"
                        :placeholder="$t('placeholders.current')" data-input="currentAnnualRent" />
                </x-d2.ui.field>
                @php($f = $field('average', 'average_hint'))
                <x-d2.ui.field :id="$f['id']" :label="$f['label']" :hint="$f['hint']" :error="$e('positive')" :invalid="false" :data-copy-status="$proposed">
                    <x-d2.ui.input :name="$p.'_average'" inputmode="decimal" autocomplete="off" spellcheck="false" :value="$val('average')"
                        :placeholder="$t('placeholders.average')" data-input="indexAverageRent" />
                </x-d2.ui.field>
                {{-- Official DLD service, new tab (proposed link; nothing is fetched from it). --}}
                <div class="tool__dld">
                    <x-d2.ui.button variant="secondary" size="40" icon-end="external" :href="$t('dld.url')" target="_blank" :data-copy-status="$proposed">{{ $t('dld.label') }}</x-d2.ui.button>
                </div>
                @php($f = $field('expiry'))
                <x-d2.ui.field :id="$f['id']" :label="$f['label']" :error="$e('date')" :invalid="false" :data-copy-status="$proposed">
                    <x-d2.ui.date :name="$p.'_expiry'" :value="$val('expiry')" min="1900-01-01" max="2100-12-31" data-input="expiryDate" />
                </x-d2.ui.field>
                @php($f = $field('notice'))
                <x-d2.ui.field :id="$f['id']" :label="$f['label']" :error="$e('date')" :invalid="false" :optional-label="true" :data-copy-status="$proposed">
                    <x-d2.ui.date :name="$p.'_notice'" :value="$val('notice')" min="1900-01-01" max="2100-12-31" data-input="noticeReceivedDate" />
                </x-d2.ui.field>
            </div>
        </div>

        {{-- Design 2.1 (D01): no "Your result" eyebrow; the card opens with its state line, then the figure. --}}
        <div class="tool__result" id="{{ $p }}-result" data-result>
            <p class="tool__nojs" @proposed>{{ Bidi::isolate($c('nojs')) }}</p>
            <p class="sr-only" aria-live="polite" data-live></p>
            <p class="tool__state tool-js" data-state="empty" @proposed>{{ $t('result.empty') }}</p>
            <p class="tool__state tool__state--invalid tool-js" data-state="invalid" hidden @proposed>
                <x-d2.ui.icon name="warning" size="20" /><span>{{ $c('check_fields') }}</span>
            </p>

            {{-- D31: "—" until both rents are valid, under the helper line; the amounts appear with the result. --}}
            <div class="tool__figure tool-js" data-figure>
                <p class="tool__total-label" @proposed>{{ $t('result.max') }}</p>
                <p class="tool__total t-tabular"><bdi class="tool__amount" data-out="rate">{{ $empty }}</bdi></p>
                <p class="tool__figure-line t-tabular" data-out="increase" hidden @proposed></p>
                <p class="tool__figure-line tool__figure-line--quiet t-tabular" data-out="new-rent" hidden @proposed></p>
            </div>

            <p class="tool__gap tool-js" data-out="gap" @proposed hidden></p>

            <figure class="tool__scale">
                <figcaption class="tool__scale-caption" @proposed>{{ $t('result.scale') }}</figcaption>
                <ol class="tool__steps" role="list" data-steps>
                    @foreach ($bands as $i => $band)
                        <li class="tool__step" data-band="{{ $i }}">
                            <span class="tool__step-gap" @proposed><span class="sr-only">{{ $t('result.scale_gap') }}</span> {{ $figures($band['gap'] ?? '') }}</span>
                            <span class="tool__step-rate" @proposed><span class="sr-only">{{ $t('result.scale_increase') }}</span> {{ $figures($band['increase'] ?? '') }}</span>
                            <span class="sr-only" data-you hidden @proposed>{{ $t('result.your_band') }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="tool__scale-axis" aria-hidden="true" @proposed>
                    <span>{{ $t('result.scale_gap') }}</span>
                    <span>{{ $t('result.scale_increase') }}</span>
                </p>
            </figure>

            <div class="tool__notice tool-js" data-notice data-tone="neutral">
                <span class="tool__notice-icon" aria-hidden="true">
                    <x-d2.ui.icon name="check" size="20" class="tool__notice-ok" />
                    <x-d2.ui.icon name="warning" size="20" class="tool__notice-warn" />
                    <x-d2.ui.icon name="info" size="20" class="tool__notice-wait" />
                </span>
                <div class="tool__notice-body">
                    <p class="tool__notice-title" @proposed>{{ $t('result.notice_title') }}</p>
                    <p class="tool__notice-text" data-out="notice" @proposed>{{ $t('result.notice_missing') }}</p>
                </div>
            </div>

            <div class="tool__actions">
                <x-d2.ui.button variant="ink" :href="$requestUrl" data-action="request">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
                <x-d2.ui.button variant="secondary" class="tool-js" data-action="copy" :disabled="true" :data-copy-status="$proposed">{{ $c('copy') }}</x-d2.ui.button>
            </div>

            <p class="tool__refs" @proposed>{{ Bidi::isolate($t('result.scope')) }}</p>
            <p class="tool__refs" @proposed><span class="tool__refs-label">{{ $c('legal_basis') }}</span> {{ $t('result.references') }}</p>
            <p class="tool__disclaimer" @proposed>{{ $t('result.disclaimer') }}</p>
        </div>
    </div>
</div>
