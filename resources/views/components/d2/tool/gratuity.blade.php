@props([
    'idPrefix' => 'gr',     // prefix of every id and field name inside (unique per tool on the page)
    'values' => [],         // initial values (styleguide states): scope, start, end, contract, absence, hours,
                            // full_hours, basic, total, leave, unpaid, notice, served
    'headingLevel' => 3,    // the tool title (visually hidden: the switch names it); the out-of-scope title is one below
    'dock' => true,         // phones / tablets: the total pinned above the tab bar (off for styleguide specimens)
])
{{--
    <x-d2.tool.gratuity /> : UAE end-of-service gratuity and final-settlement estimator (upgrade spec §5.2,
    §12 C6). Formula and validation: resources/js/d2/tools/gratuity.js (pure, Vitest); the page script:
    resources/js/d2/tools/gratuity-view.js, mounted by Alpine d2ToolGratuity when the tool nears the viewport.
    Styles: resources/css/d2/sections/tools.css.

    - Scope (segmented): DIFC, ADGM, government and domestic work show the out-of-scope panel instead of the
      estimator (CSS :has() without JavaScript, data-scope with it).
    - No <form>: nothing can ever be submitted, so no figure reaches a URL or a server log. The fields work
      without JavaScript; the result card then says the estimate needs JavaScript and keeps the
      consultation button (a plain link with ?service=S06&tool=gratuity).
    - With JavaScript: every change recalculates; errors show once a field is committed (change / blur);
      the result is announced politely after typing stops; numbers swap with a 160 ms cross-fade (never
      counting up); the stacked bar grows on the bar spring; the service timeline is drawn in SVG.
    - "Request a Legal Consultation" calls prefill({ service: 'S06', tool: 'gratuity', summary }); "Copy
      summary" copies the same plain-text recap (toast).
    - Design 2.1 (Phase 1): no eyebrow over the result (D01), no icon tile on the out-of-scope panel and no
      decorative icons on buttons (D04); the info note takes the callout's own info icon. The coloured keys of
      the table are the composition bar's legend (data colours on the marks only).
    - Design 2.1 (Phase 2): the total reads "—" until the required fields are valid (D31); the breakdown table
      appears with a result, its amounts end-aligned in tabular figures (D32); the bar and the keys of the parts
      with a value appear only when there are values, text stays ink (D34); the sector is a segmented control that
      becomes a select on phones and wherever its options do not fit (D35); the dates are <x-d2.ui.date> (D36).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Prefill;

    $p = $idPrefix;
    $t = fn (string $key, array $replace = []) => d2p('tools.gratuity.'.$key, $replace);
    $c = fn (string $key) => d2p('tools.common.'.$key);
    $e = fn (string $code) => d2p('tools.errors.'.$code);
    $val = fn (string $key) => isset($values[$key]) && $values[$key] !== '' && $values[$key] !== null ? (string) $values[$key] : null;
    $proposed = app()->isProduction() ? null : 'proposed';
    $level = max(2, min(5, (int) $headingLevel));

    $scopes = ['mainland', 'freezone', 'difc', 'adgm', 'government', 'domestic'];
    $scope = in_array($values['scope'] ?? null, $scopes, true) ? $values['scope'] : 'mainland';
    $scopeOptions = array_map(fn ($s) => [$s, $t('scope.'.$s)], $scopes);
    $contracts = ['full' => $t('fields.contract_full'), 'part' => $t('fields.contract_part'), 'temporary' => $t('fields.contract_temporary')];
    $contract = array_key_exists($values['contract'] ?? '', $contracts) ? $values['contract'] : 'full';
    $requestUrl = Prefill::url(['service' => 'S06', 'tool' => 'gratuity']);
    $empty = $c('empty_value');

    $errorKeys = ['date', 'end_before_start', 'not_number', 'negative', 'positive', 'too_large', 'whole_days', 'absence_exceeds_service', 'total_below_basic', 'hours', 'hours_exceed', 'notice_range'];
    $config = [
        'tool' => 'gratuity',
        'service' => 'S06',
        'errors' => array_combine($errorKeys, array_map($e, $errorKeys)),
        'scopes' => array_combine($scopes, array_map(fn ($s) => $t('scope.'.$s), $scopes)),
        'contracts' => $contracts,
        'text' => [
            'empty' => $empty,
            'emptyFields' => $t('result.empty'),
            'emptyHours' => $t('result.empty_hours'),
            'copied' => $c('copied'),
            'copyFailed' => $c('copy_failed'),
            'estimateOnly' => $c('estimate_only'),
            'notEntered' => $c('not_entered'),
            'capNote' => $t('result.cap_note'),
            'partTime' => $t('result.part_time'),
            'service' => $t('timeline.value'),
            'years' => $t('timeline.years'),
            'zone21' => $t('timeline.zone21'),
            'zone30' => $t('timeline.zone30'),
            'absence' => $t('timeline.absence'),
            'live' => $t('live'),
            'outSummary' => $t('out.summary'),
            'underYear' => $t('result.under_year'),
            'temporary' => $t('result.temporary'),
        ],
        'summary' => (array) __('proposed.d2.tools.gratuity.summary'),
    ];

    // One field: [key, label, hint, default error code] — the error text is swapped by the script.
    $field = fn (string $key, ?string $hint = null) => ['id' => $p.'-'.str_replace('_', '-', $key), 'label' => $t('fields.'.$key), 'hint' => $hint ? $t('fields.'.$hint) : null];
@endphp
<div {{ $attributes->class(['tool', 'tool--gratuity']) }} id="{{ $p }}" x-data="d2ToolGratuity" data-tool="gratuity"
    data-scope="{{ in_array($scope, ['mainland', 'freezone'], true) ? 'in' : 'out' }}" data-contract="{{ $contract }}">
    <x-d2.tool.config :data="$config" :plurals="['days' => 'tools.common.days']" />
    <h{{ $level }} class="sr-only" id="{{ $p }}-title" @proposed>{{ $t('title') }}</h{{ $level }}>

    {{-- D35: the sector is a segmented control where its six options fit, a select on phones and wherever they do not
         (the segmented control marks itself .is-overflow); one cell, so switching never moves the page. The radios
         stay the value (data-input="scope", and the no-JS path); d2ToolGratuity keeps the select in step. --}}
    <div class="tool__scope" data-scope-control>
        <x-d2.ui.segmented :name="$p.'_scope'" :id="$p.'-scope'" class="tool__scope-seg" :options="$scopeOptions"
            :value="$scope" :label="$t('scope.label')" :label-visible="true" data-input="scope" :data-copy-status="$proposed" />
        <div class="tool__scope-pick">
            <x-d2.ui.field :id="$p.'-scope-pick'" :label="$t('scope.label')" :data-copy-status="$proposed">
                <x-d2.ui.select :options="$scopeOptions" :value="$scope" data-scope-select />
            </x-d2.ui.field>
        </div>
    </div>

    {{-- DIFC, ADGM, Government, Domestic worker (spec §5.2 step 0). Design 2.1: no icon tile (brief 3.6 rule 5). --}}
    <div class="tool__out" data-scope-out>
        <div class="tool__out-body">
            <h{{ $level + 1 }} class="tool__out-title" @proposed>{{ $t('out.title') }}</h{{ $level + 1 }}>
            <p class="tool__out-text" @proposed>{{ $t('out.text') }}</p>
            <x-d2.ui.button variant="ink" :href="$requestUrl" data-action="request" class="tool__out-cta">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
        </div>
    </div>

    <div class="tool__grid" data-scope-in>
        <div class="tool__form" role="group" aria-labelledby="{{ $p }}-title">
            {{-- Service --}}
            <fieldset class="tool__group">
                <legend class="tool__legend" @proposed>{{ $t('groups.service') }}</legend>
                <div class="tool__fields">
                    @php($f = $field('start'))
                    <x-d2.ui.field :id="$f['id']" :label="$f['label']" :error="$e('date')" :invalid="false" :data-copy-status="$proposed">
                        <x-d2.ui.date :name="$p.'_start'" :value="$val('start')" min="1900-01-01" max="2100-12-31" data-input="startDate" />
                    </x-d2.ui.field>
                    @php($f = $field('end'))
                    <x-d2.ui.field :id="$f['id']" :label="$f['label']" :error="$e('end_before_start')" :invalid="false" :data-copy-status="$proposed">
                        <x-d2.ui.date :name="$p.'_end'" :value="$val('end')" min="1900-01-01" max="2100-12-31" data-input="lastWorkingDay" />
                    </x-d2.ui.field>
                    @php($f = $field('contract'))
                    <x-d2.ui.field :id="$f['id']" :label="$f['label']" :data-copy-status="$proposed">
                        <x-d2.ui.select :name="$p.'_contract'" :options="$contracts" :value="$contract" data-input="contractType" />
                    </x-d2.ui.field>
                    @php($f = $field('absence', 'absence_hint'))
                    <x-d2.ui.field :id="$f['id']" :label="$f['label']" :hint="$f['hint']" :error="$e('negative')" :invalid="false" :data-copy-status="$proposed">
                        <x-d2.ui.stepper :name="$p.'_absence'" min="0" max="36500" :value="$val('absence')" :placeholder="$t('placeholders.days')" data-input="unpaidAbsenceDays" />
                    </x-d2.ui.field>
                </div>
                <div class="tool__fields tool__part" data-part>
                    @php($f = $field('hours', 'hours_hint'))
                    <x-d2.ui.field :id="$f['id']" :label="$f['label']" :hint="$f['hint']" :error="$e('hours')" :invalid="false" :data-copy-status="$proposed">
                        <x-d2.ui.stepper :name="$p.'_hours'" min="1" max="168" :value="$val('hours')" :placeholder="$t('placeholders.hours')" data-input="hoursPerWeek" />
                    </x-d2.ui.field>
                    @php($f = $field('full_hours', 'full_hours_hint'))
                    <x-d2.ui.field :id="$f['id']" :label="$f['label']" :hint="$f['hint']" :error="$e('hours')" :invalid="false" :data-copy-status="$proposed">
                        <x-d2.ui.stepper :name="$p.'_full_hours'" min="1" max="168" :value="$val('full_hours')" :placeholder="$t('placeholders.full_hours')" data-input="fullTimeHoursPerWeek" />
                    </x-d2.ui.field>
                </div>
            </fieldset>

            {{-- Wage --}}
            <fieldset class="tool__group">
                <legend class="tool__legend" @proposed>{{ $t('groups.wage') }}</legend>
                <div class="tool__fields">
                    @php($f = $field('basic', 'basic_hint'))
                    <x-d2.ui.field :id="$f['id']" :label="$f['label']" :hint="$f['hint']" :error="$e('not_number')" :invalid="false" :data-copy-status="$proposed">
                        <x-d2.ui.input :name="$p.'_basic'" inputmode="decimal" autocomplete="off" spellcheck="false" :value="$val('basic')"
                            :placeholder="$t('placeholders.basic')" data-input="basicMonthly" />
                    </x-d2.ui.field>
                    @php($f = $field('total', 'total_hint'))
                    <x-d2.ui.field :id="$f['id']" :label="$f['label']" :hint="$f['hint']" :error="$e('total_below_basic')" :invalid="false" :optional-label="true" :data-copy-status="$proposed">
                        <x-d2.ui.input :name="$p.'_total'" inputmode="decimal" autocomplete="off" spellcheck="false" :value="$val('total')"
                            :placeholder="$t('placeholders.total')" data-input="totalMonthlyWage" />
                    </x-d2.ui.field>
                </div>
            </fieldset>

            {{-- Leave, notice and unpaid wages --}}
            <fieldset class="tool__group">
                <legend class="tool__legend" @proposed>{{ $t('groups.other') }}</legend>
                <div class="tool__fields">
                    @php($f = $field('leave', 'leave_hint'))
                    <x-d2.ui.field :id="$f['id']" :label="$f['label']" :hint="$f['hint']" :error="$e('negative')" :invalid="false" :data-copy-status="$proposed">
                        <x-d2.ui.stepper :name="$p.'_leave'" min="0" max="3650" :value="$val('leave')" :placeholder="$t('placeholders.days')" data-input="unusedLeaveDays" />
                    </x-d2.ui.field>
                    @php($f = $field('unpaid', 'unpaid_hint'))
                    <x-d2.ui.field :id="$f['id']" :label="$f['label']" :hint="$f['hint']" :error="$e('not_number')" :invalid="false" :data-copy-status="$proposed">
                        <x-d2.ui.input :name="$p.'_unpaid'" inputmode="decimal" autocomplete="off" spellcheck="false" :value="$val('unpaid')"
                            :placeholder="$t('placeholders.amount')" data-input="unpaidWages" />
                    </x-d2.ui.field>
                    @php($f = $field('notice', 'notice_hint'))
                    <x-d2.ui.field :id="$f['id']" :label="$f['label']" :hint="$f['hint']" :error="$e('notice_range')" :invalid="false" :data-copy-status="$proposed">
                        <x-d2.ui.stepper :name="$p.'_notice'" min="30" max="90" :value="$val('notice')" :placeholder="$t('placeholders.notice')" data-input="noticeDaysInContract" />
                    </x-d2.ui.field>
                    @php($f = $field('served', 'served_hint'))
                    <x-d2.ui.field :id="$f['id']" :label="$f['label']" :hint="$f['hint']" :error="$e('negative')" :invalid="false" :data-copy-status="$proposed">
                        <x-d2.ui.stepper :name="$p.'_served'" min="0" max="90" :value="$val('served')" :placeholder="$t('placeholders.days')" data-input="noticeDaysServed" />
                    </x-d2.ui.field>
                </div>
            </fieldset>
        </div>

        {{-- Phones and tablets: the total pinned above the tab bar while the fields are on screen (script). --}}
        @if ($dock)
            <a class="tool__dock tool-js" href="#{{ $p }}-result" data-dock hidden>
                <span class="tool__dock-text">
                    <span class="tool__dock-label" @proposed>{{ $t('result.total') }}</span>
                    <bdi class="tool__dock-value t-tabular" data-out="dock">{{ $empty }}</bdi>
                </span>
                <span class="tool__dock-more" @proposed>{{ $t('result.see_breakdown') }}<x-d2.ui.icon name="chevron-down" size="20" /></span>
            </a>
        @endif

        {{-- Design 2.1 (D01): no "Your estimate" eyebrow; the card opens with its state line, then the total. --}}
        <div class="tool__result" id="{{ $p }}-result" data-result>
            <p class="tool__nojs" @proposed>{{ Bidi::isolate($c('nojs')) }}</p>
            <p class="sr-only" aria-live="polite" data-live></p>
            <p class="tool__state tool-js" data-state="empty" @proposed>{{ $t('result.empty') }}</p>
            <p class="tool__state tool__state--invalid tool-js" data-state="invalid" hidden @proposed>
                <x-d2.ui.icon name="warning" size="20" /><span>{{ $c('check_fields') }}</span>
            </p>

            {{-- D31: "—" until the required fields are valid, under the helper line; never a grey "AED 0.00". --}}
            <div class="tool__figure tool-js" data-figure>
                <p class="tool__total-label" @proposed>{{ $t('result.total') }}</p>
                <p class="tool__total t-tabular"><bdi class="tool__amount" data-out="total">{{ $empty }}</bdi></p>
                {{-- D34: the composition bar (and the keys in the table, its legend) only once there are values. --}}
                <div class="tool__bar" aria-hidden="true" data-bar hidden>
                    @foreach (['gratuity', 'leave', 'notice', 'unpaid'] as $part)
                        <span class="tool__seg tool__seg--{{ $part }}" data-seg="{{ $part }}"></span>
                    @endforeach
                </div>
            </div>

            {{-- D32: the breakdown shows with a result only (no column of "—" that reads like toggles); its amounts sit
                 at the inline end in tabular figures. --}}
            <table class="tool__table tool-js" data-breakdown hidden @proposed>
                <caption class="sr-only">{{ $t('result.caption') }}</caption>
                <thead class="sr-only">
                    <tr><th scope="col">{{ $t('result.col_item') }}</th><th scope="col">{{ $t('result.col_amount') }}</th></tr>
                </thead>
                <tbody>
                    <tr class="tool__row tool__row--main">
                        <th scope="row">
                            <span class="tool__label"><span class="tool__key tool__key--gratuity" aria-hidden="true" data-key="gratuity"></span>{{ $t('result.gratuity') }}</span>
                            <span class="tool__badges">
                                <x-d2.ui.badge tone="warning" data-flag="cap" hidden>{{ $t('result.cap') }}</x-d2.ui.badge>
                                <x-d2.ui.badge tone="neutral" data-flag="cap-basic" hidden>{{ $t('result.cap_basic') }}</x-d2.ui.badge>
                            </span>
                            <span class="tool__note" data-note="cap" hidden></span>
                            <span class="tool__note" data-note="cap-basic" hidden>{{ $t('result.cap_basic_note') }}</span>
                            <span class="tool__note" data-note="eligibility" hidden></span>
                            <span class="tool__note" data-note="part-time" hidden></span>
                        </th>
                        <td><bdi class="tool__amount" data-out="gratuity">{{ $empty }}</bdi></td>
                    </tr>
                    <tr class="tool__row tool__row--sub">
                        <th scope="row"><span class="tool__label">{{ $t('result.part21') }}</span></th>
                        <td><bdi class="tool__amount" data-out="part21">{{ $empty }}</bdi></td>
                    </tr>
                    <tr class="tool__row tool__row--sub">
                        <th scope="row"><span class="tool__label">{{ $t('result.part30') }}</span></th>
                        <td><bdi class="tool__amount" data-out="part30">{{ $empty }}</bdi></td>
                    </tr>
                    <tr class="tool__row">
                        <th scope="row"><span class="tool__label"><span class="tool__key tool__key--leave" aria-hidden="true" data-key="leave"></span>{{ $t('result.leave') }}</span></th>
                        <td><bdi class="tool__amount" data-out="leave">{{ $empty }}</bdi></td>
                    </tr>
                    <tr class="tool__row">
                        <th scope="row">
                            <span class="tool__label"><span class="tool__key tool__key--notice" aria-hidden="true" data-key="notice"></span>{{ $t('result.notice') }}</span>
                            <span class="tool__badges"><x-d2.ui.badge tone="bronze">{{ $t('result.may_apply') }}</x-d2.ui.badge></span>
                            <span class="tool__note">{{ $t('result.notice_note') }}</span>
                        </th>
                        <td><bdi class="tool__amount" data-out="notice">{{ $empty }}</bdi></td>
                    </tr>
                    <tr class="tool__row">
                        <th scope="row"><span class="tool__label"><span class="tool__key tool__key--unpaid" aria-hidden="true" data-key="unpaid"></span>{{ $t('result.unpaid') }}</span></th>
                        <td><bdi class="tool__amount" data-out="unpaid">{{ $empty }}</bdi></td>
                    </tr>
                </tbody>
            </table>

            <div class="tool__service tool-js" data-service hidden>
                <p class="tool__service-head">
                    <span class="tool__service-label" @proposed>{{ $t('timeline.service') }}</span>
                    <span class="tool__service-value t-tabular" data-out="service"></span>
                </p>
                <div class="tl" aria-hidden="true" data-timeline>
                    <div class="tl__labels" data-tl-labels></div>
                    <svg class="tl__svg" data-tl-svg width="100%" height="28" focusable="false" aria-hidden="true"></svg>
                    <div class="tl__ticks" data-tl-ticks></div>
                </div>
                <ul class="tl__legend" role="list" @proposed>
                    <li class="tl__legend-item tl__legend-item--one">{{ $t('timeline.one_year') }}</li>
                    <li class="tl__legend-item tl__legend-item--absence" data-tl-absence hidden></li>
                </ul>
            </div>

            <x-d2.ui.callout tone="info" size="sm" class="tool__payment" :data-copy-status="$proposed">{{ $t('result.payment') }}</x-d2.ui.callout>

            <div class="tool__actions">
                <x-d2.ui.button variant="ink" :href="$requestUrl" data-action="request">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
                <x-d2.ui.button variant="secondary" class="tool-js" data-action="copy" :disabled="true" :data-copy-status="$proposed">{{ $c('copy') }}</x-d2.ui.button>
            </div>

            <p class="tool__refs" @proposed><span class="tool__refs-label">{{ $c('legal_basis') }}</span> {{ $t('result.references') }}</p>
            <p class="tool__disclaimer" @proposed>{{ $t('result.disclaimer') }}</p>
        </div>
    </div>
</div>
