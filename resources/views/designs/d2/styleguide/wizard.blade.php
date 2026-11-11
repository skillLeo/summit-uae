{{--
    Styleguide: consultation wizard + contact card (Phase C, area "wizard"). Annotations are internal
    (lang/en/styleguide_d2/wizard.php, marked lang="en" on the Arabic and Russian pages); the wizard shows
    approved copy and its proposed microcopy. The step specimens are live wizards in "specimen" mode
    (wizard/controller.js): sample answers, no autosave, no prefill, no security check, never sent. The
    delivery states are the same components rendered with one state forced. Ids never repeat the home's
    (every wizard has its own prefix).
--}}
@php
    use Illuminate\Support\Str;

    $t = fn (string $key) => __('styleguide_d2/wizard.'.$key);
    $en = app()->isLocale('en') ? '' : 'lang=en';
    $uuid = fn (): string => (string) Str::uuid();

    $sample = [
        'audience' => 'individual',
        'name' => $t('sample_name'),
        'matter' => 'S06',
        'situation' => 'S06-2',
        'summary' => $t('sample_summary'),
        'phone' => $t('sample_phone'),
        'email' => $t('sample_email'),
        'language' => 'en',
        'deadlineDate' => '2026-12-15',
        'deadlineDetails' => $t('sample_deadline'),
        'docs' => ['S06' => [0, 1]],
    ];
    // The Contact DOCX order (D38): 1 You (name, phone, e-mail, language), 2 Your matter, 3 Deadline, 4 Documents, 5 Review.
    $specimens = [
        's1' => ['step' => 1, 'state' => 'errors', 'values' => ['consultant' => 'team-ahmed', 'tool' => 'gratuity', 'audience' => 'business', 'phone' => '+971 5', 'email' => 'a.example@']],
        's2' => ['step' => 2, 'values' => $sample],
        's3' => ['step' => 3, 'values' => $sample],
        's4' => ['step' => 4, 'values' => $sample],
        's5' => ['step' => 5, 'state' => 'stale', 'values' => $sample + ['message' => $t('sample_summary')."\n\n".$t('sample_edit'), 'messageEdited' => true]],
    ];
    $states = ['errors', 'failed', 'rate_limited', 'uncertain', 'turnstile_incomplete', 'turnstile_unavailable'];
    // The error-summary specimen links to real fields of the live specimens above.
    $summary = [['sg-wz-s1-name', ui('form.name.error')], ['sg-wz-s1-phone', ui('form.contact_group.error')], ['sg-wz-s5-consent', ui('form.consent.error')]];
@endphp
<section class="sg-section" id="sg-wizard">
    <h2 {{ $en }}>{{ $t('title') }}</h2>
    <p {{ $en }}>{{ $t('intro') }}</p>

    {{-- ================================================================== steps --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('steps') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('steps_note') }}</p>
    <div class="stack" data-sg="wizard-steps">
        @foreach ($specimens as $key => $specimen)
            <div class="contact-section__grid" data-sg="wizard-{{ $key }}">
                <div class="contact-section__main">
                    <x-d2.wizard.form :idempotency-key="$uuid()" :prefix="'sg-wz-'.$key" :specimen="$specimen" />
                </div>
                <p class="sg-label contact-section__aside" {{ $en }}>{{ $t($key) }}</p>
            </div>
        @endforeach
    </div>

    {{-- ================================================================== without JavaScript --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('plain') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('plain_note') }}</p>
    <div class="contact-section__grid" data-sg="wizard-plain">
        <div class="contact-section__main">
            <x-d2.wizard.form :idempotency-key="$uuid()" prefix="sg-wz-plain" :plain="true" />
        </div>
        <div class="contact-section__aside">
            <x-d2.contact.card id-prefix="sg-cc-plain" variant="home" />
        </div>
    </div>

    {{-- ================================================================== delivery states --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('states') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('states_note') }}</p>
    <div class="sg-grid sg-grid--wide" data-sg="wizard-states">
        @foreach ($states as $state)
            <div class="sg-specimen">
                <x-d2.wizard.notices :prefix="'sg-wz-state-'.$state" :show="[$state]" :summary="$state === 'errors' ? $summary : []" />
                <p class="sg-label" {{ $en }}><code class="sg-code">{{ $state }}</code> {{ $t('state_'.$state) }}</p>
            </div>
        @endforeach
        <div class="sg-specimen">
            <x-d2.wizard.success prefix="sg-wz-state-received" :reference="$t('sample_reference')" :shown="true" />
            <p class="sg-label" {{ $en }}><code class="sg-code">received</code> {{ $t('state_received') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.wizard.restore :shown="true" />
            <p class="sg-label" {{ $en }}><code class="sg-code">restore</code> {{ $t('state_restore') }}</p>
        </div>
    </div>

    {{-- ================================================================== actions --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('buttons') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('buttons_note') }}</p>
    <div class="sg-grid sg-grid--wide" data-sg="wizard-actions">
        @foreach (['default' => '', 'hover' => 'is-hover', 'focus' => 'is-focus'] as $state => $class)
            <div class="sg-specimen">
                <div class="cluster">
                    <x-d2.ui.button variant="secondary" icon="form-back" :class="$class">{{ d2p('wizard.back') }}</x-d2.ui.button>
                    <x-d2.ui.button variant="ink" icon-end="form-next" :class="$class">{{ d2p('wizard.next') }}</x-d2.ui.button>
                    <x-d2.ui.button variant="ink" :class="$class">{{ ui('form.submit') }}</x-d2.ui.button>
                </div>
                <p class="sg-label" {{ $en }}>{{ $t('btn_'.$state) }}</p>
            </div>
        @endforeach
        <div class="sg-specimen">
            {{-- The real actions row (components/wizard.css): the spinner sits before the approved busy label. --}}
            <div class="wizard__actions">
                <x-d2.ui.button variant="secondary" icon="form-back" :disabled="true">{{ d2p('wizard.back') }}</x-d2.ui.button>
                <x-d2.ui.button variant="ink" :loading="true" data-wizard-submit>{{ ui('form.state.busy') }}</x-d2.ui.button>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('btn_sending') }}</p>
        </div>
    </div>

    {{-- ================================================================== documents step without a list --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('docs_empty') }}</h3>
    <div class="sg-grid sg-grid--wide" data-sg="wizard-docs-empty">
        <div class="sg-specimen">
            <x-d2.ui.callout tone="info" size="sm"><p @proposed>{{ d2p('wizard.documents.none') }}</p></x-d2.ui.callout>
            <p class="sg-label" {{ $en }}>{{ $t('docs_none') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.ui.callout tone="info" size="sm"><p @proposed>{{ d2p('wizard.documents.generic') }}</p></x-d2.ui.callout>
            <p class="sg-label" {{ $en }}>{{ $t('docs_generic') }}</p>
        </div>
    </div>

    {{-- ================================================================== contact card --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('card') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('card_note') }}</p>
    <div class="sg-grid sg-grid--wide" data-sg="wizard-card">
        <div class="sg-specimen">
            <x-d2.contact.card id-prefix="sg-cc-default" variant="home" />
            <p class="sg-label" {{ $en }}>{{ $t('card_home') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.contact.card id-prefix="sg-cc-contact" :contact="['hours' => (string) (content()->page('P07')['content']['details']['hours'] ?? ''), 'languages' => (string) (content()->page('P07')['content']['details']['languages'] ?? '')]"
                :whatsapp-label="content()->page('P07')['content']['details']['whatsapp_cta'] ?? null" :note="content()->page('P07')['content']['details']['after_hours'] ?? null" />
            <p class="sg-label" {{ $en }}>{{ $t('card_default') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.contact.card id-prefix="sg-cc-states" variant="home" :force="['phone' => 'is-hover', 'email' => 'is-focus']" />
            <p class="sg-label" {{ $en }}>{{ $t('card_states') }}</p>
        </div>
    </div>

    {{-- ================================================================== Arabic (on the English and Russian pages) --}}
    @unless (app()->isLocale('ar'))
        <h3 class="sg-sub" {{ $en }}>{{ $t('rtl') }}</h3>
        <p class="sg-note" {{ $en }}>{{ $t('rtl_note') }}</p>
        @php
            $pageLocale = app()->getLocale();
            app()->setLocale('ar');
            ob_start();
        @endphp
        <div class="contact-section__grid" lang="ar" dir="rtl" data-sg="wizard-rtl">
            <div class="contact-section__main">
                <x-d2.wizard.form :idempotency-key="$uuid()" prefix="sg-wz-rtl" :specimen="['step' => 5, 'values' => $sample]" />
            </div>
            <div class="contact-section__aside">
                <x-d2.contact.card id-prefix="sg-cc-rtl" variant="home" />
            </div>
        </div>
        @php
            app()->setLocale($pageLocale);
            // Round 1 (brief 5.2 and 14.2: every WhatsApp link of a page carries that page's WA01): this specimen is
            // Arabic on an English or Russian page, so its WhatsApp controls (the contact card's row and the delivery
            // notices' buttons) are shown, not live: the same markup without href, target and rel, marked
            // data-lint-specimen (their external mark is shown out of its context, lint rule 13).
            $rtlSpecimen = (string) preg_replace_callback(
                '#<a\b[^>]*\bhref="https://(?:wa\.me|api\.whatsapp\.com)/[^"]*"[^>]*>#',
                fn (array $tag): string => (string) preg_replace('#^<a\b#', '<a data-lint-specimen', (string) preg_replace('#\s(?:href|target|rel)="[^"]*"#', '', $tag[0])),
                (string) ob_get_clean(),
            );
        @endphp
        {!! $rtlSpecimen !!}
    @endunless
</section>
