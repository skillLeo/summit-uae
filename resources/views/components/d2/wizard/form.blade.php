@props([
    'idempotencyKey',          // a fresh UUID per render (HomeController): docs/enquiries.md §3
    'prefix' => 'wz',          // id namespace (the styleguide renders several wizards)
    'navServices' => null,     // App\Content\NavigatorData::services() (the home page passes its own)
    'copy' => 'contact',       // whose Word file supplies the form copy: 'home' (01_Homepage) or 'contact' (07_Contact_Us)
    'labelledby' => null,      // id of the page heading that names the form (home: section 09's H2; P07: its form H2)
    'title' => null,           // that heading's text: the accessible name of the step list (default ui('form.heading'))
    'specimen' => null,        // styleguide only: ['step' => 1–5, 'state' => …, 'values' => […]] (a live, offline demo)
    'plain' => false,          // styleguide only: the form exactly as it works without JavaScript
])
{{--
    Design 2 consultation wizard (upgrade spec §12 C14, §5.5) on the existing enquiry backend
    (POST /{locale}/enquiries, docs/enquiries.md; never changed). The wizard's own microcopy is proposed
    (d2p('wizard.*')).

    ONE component, the copy of each page's own Word file (owner decision; brief §5.2):
      copy="contact"  P07 and the styleguide: every label, help text, option, notice, error and delivery state of
                      07_Contact_Us (ui('form.*'), the Contact master).
      copy="home"     the home's section 09: the homepage file's own wording wherever it has one (P01
                      form_home_variant: "Full Name", "Phone or WhatsApp Number", … its two hints, its notices,
                      consent, submit, the states it lists; the matter options are the eleven Section 02 service
                      titles between "Choose a Matter Type" and "Other Matter"). The Contact page's help lines and
                      required note are not repeated on the home (the homepage file has none). Only what the form
                      needs to work and the homepage file does not supply comes from the Contact master: the
                      deadline details field, the invalid-date message, the error-summary title, rate limited,
                      uncertain delivery and "Send a New Enquiry". The homepage file's order puts its engagement
                      notice after the submit button; the Contact file puts both notices before the consent.
    The page renders the form's heading (home: section 09's H2 and intro; P07: "Consultation Request Details", its
    intro and the required note) and passes its id as `labelledby`.

    Step order (D38, the Contact file's form layout): name and contact details first, then the optional language
    and matter type, the summary, the deadline details, the documents (the optional attachment), and the review
    with the visible notices, the consent and the submit button:
      1 You        [audience, pending] name, phone, e-mail (one valid method; both checked when both are given),
                   preferred language
      2 Your matter  matter type, [closest situation], summary
      3 Deadline   nearest date, deadline details
      4 Documents  the checklist of the chosen service (JavaScript only) [+ the attachment: never today, see below]
      5 Review     the answers, the message as it will be sent, the notices, consent, submit
    Pending client approval (D39, App\Design\D2\Pending::visible()): "You are contacting us as" and "Keep a draft
    on this device" (and with it the local draft, its restore prompt and its toast) render only while pending copy
    is shown. The local draft also needs a Privacy Policy line before it goes live (a client question).
    Attachment (brief §5.2): App\Design\D2\Attachment::enabled() is false because the backend does not scan uploads,
    so the field and its hint are not rendered; the dormant path stays for when it does.

    Without JavaScript this is ONE plain form: the steps are its groups (Documents only while attachments are
    enabled), it posts the endpoint's own field names, and the no-JS redirects (enquiry_status flash, errors bag,
    old input) render every outcome here, each message in this page's own wording. With JavaScript (Alpine
    d2Wizard → wizard/lazy.js → the lazy wizard/controller.js) the same markup becomes five steps with one progress
    indicator (D37: <x-d2.ui.steps>, a step list, "Step 1 of 5: You" on phones), browser checks per step, the
    review, prefill and the fetch states, in the DOCX order: submitting (the busy label), received (only after the
    server accepted the request), the field errors, then the failures (failed, rate limited, uncertain, security
    check). Controls marked data-js-only exist only for the stepped wizard; controls with form="{prefix}-local"
    are never posted (the backend has no field for them).

    The stepped layout shows from the first paint (CSS keyed on html.js, components/wizard.css): the server
    renders the step it opens on ($start, the controller's own rule), marks the other steps and buttons
    data-wizard-later, and prints the context lines, so nothing moves when the controller chunk mounts.
    Design 2.1: every button is Ink, Secondary or Text (the submit is Ink: D06); Back and Next keep their arrows
    as the brief allows (form-back / form-next); no decorative icons (D04); no middle dot (D02).
--}}
@php
    use App\Content\NavigatorData;
    use App\Design\D2\Attachment;
    use App\Design\D2\Bidi;
    use App\Design\D2\Pending;
    use App\Design\D2\Prefill;
    use App\Http\Requests\StoreEnquiryRequest;
    use App\Support\SiteUrl;
    use Illuminate\Support\Facades\Vite;

    $locale = app()->getLocale();
    $p = $prefix;
    $id = fn (string $suffix): string => $p.'-'.$suffix;
    $local = $p.'-local';
    $demo = $specimen !== null || $plain;
    $proposed = app()->isProduction() ? null : 'proposed';
    $pending = Pending::visible();
    // The one optional document: off (the backend does not scan uploads; App\Design\D2\Attachment).
    $attachments = Attachment::enabled();
    // How long the draft stays in this browser: the copy says it and wizard/draft-meta.js applies it.
    $draftDays = 7;

    // ---------------------------------------------------------------- this page's own copy
    $home = $copy === 'home';
    $variant = $home ? (array) (content()->page('P01')['content']['form_home_variant'] ?? []) : [];
    // ui('form.<key>') role => its place in the homepage file's form (P01 form_home_variant).
    $homeKeys = [
        'name.label' => 'labels.name',
        'phone.label' => 'labels.phone',
        'email.label' => 'labels.email',
        'preferred_language.label' => 'labels.preferred_language',
        'preferred_language.placeholder' => 'language_placeholder',
        'preferred_language.option.ar' => 'language_options.ar',
        'preferred_language.option.en' => 'language_options.en',
        'preferred_language.option.ru' => 'language_options.ru',
        'matter_type.label' => 'labels.matter_type',
        'matter_type.placeholder' => 'matter_placeholder',
        'matter_type.option.other' => 'matter_other',
        'summary.label' => 'labels.summary',
        'deadline_date.label' => 'labels.deadline_date',
        'deadline_date.help' => 'deadline_hint',
        'attachment.label' => 'labels.attachment',
        'attachment.help' => 'attachment_hint',
        'notice.sensitive' => 'sensitive_notice',
        'notice.engagement' => 'engagement_notice',
        'consent.label' => 'consent',
        'submit' => 'submit',
        'state.busy' => 'states.submitting',
        'state.success' => 'states.success',
        'state.failure' => 'states.failure',
        'name.error' => 'states.missing_name',
        'contact_group.error' => 'states.invalid_contact',
        // The homepage file has one contact message: it also names an invalid phone or e-mail.
        'phone.error' => 'states.invalid_contact',
        'email.error' => 'states.invalid_contact',
        'summary.error' => 'states.missing_summary',
        'consent.error' => 'states.missing_consent',
    ];
    // The Contact page's guidance lines, which the homepage file does not have: not printed on the home.
    $contactOnly = ['name.help', 'phone.help', 'email.help', 'summary.help', 'contact_group.help', 'deadline_details.help'];
    $t = function (string $key) use ($home, $variant, $homeKeys, $contactOnly): ?string {
        if ($home) {
            $text = isset($homeKeys[$key]) ? data_get($variant, $homeKeys[$key]) : null;
            if (is_string($text) && trim($text) !== '') {
                return $text;
            }
            if (in_array($key, $contactOnly, true)) {
                return null;
            }
        }

        return ui('form.'.$key);
    };

    // ---------------------------------------------------------------- request state (no-JS round trip)
    $bag = (! $demo && isset($errors) && $errors instanceof \Illuminate\Support\ViewErrorBag) ? $errors->getBag('default') : new \Illuminate\Support\MessageBag;
    $e = fn (string $field): string => (string) ($bag->first($field) ?? '');
    // The endpoint sends one approved message per field (StoreEnquiryRequest::messages()); this page shows its own
    // wording for that field. The document keeps the server's (four different refusals).
    $pageError = fn (string $field): ?string => match ($field) {
        'name' => $t('name.error'),
        'phone' => $t('phone.error'),
        'email' => $t('email.error'),
        'contact' => $t('contact_group.error'),
        'summary' => $t('summary.error'),
        'deadline_date' => $t('deadline_date.error'),
        'enquiry_consent' => $t('consent.error'),
        default => null,
    };
    $err = fn (string $field): string => $e($field) === '' ? '' : ($pageError($field) ?? $e($field));
    // Old input is cast to strings: an array-shaped value must never break the page (Design 1 review SEC-02).
    $old = function (string $key, string $default = '') use ($demo): string {
        if ($demo) {
            return $default;
        }
        $value = old($key);

        return is_string($value) ? $value : $default;
    };
    $status = $demo ? null : session('enquiry_status');
    $status = in_array($status, ['invalid', 'received', 'failed', 'rate_limited'], true) ? $status : null;
    $reference = $demo ? null : session('enquiry_reference');
    $reference = is_string($reference) && preg_match('/^SLC-[0-9A-Z-]{6,24}$/', $reference) === 1 ? $reference : null;
    $received = $status === 'received' && $reference !== null;
    $hasOld = ! $demo && session()->hasOldInput();

    // ---------------------------------------------------------------- content
    $navServices ??= (new NavigatorData(content()))->services();
    $services = [];
    foreach ($navServices as $service) {
        $services[$service['code']] = [
            'title' => (string) ($service['title'] ?? ''),
            'situations' => array_values(array_map('strval', $service['situations'] ?? [])),
            'documents' => array_values(array_map('strval', $service['documents'] ?? [])),
            'heading' => (string) ($service['headings']['documents'] ?? ''),
            'note' => (string) ($service['documents_note'] ?? ''),
        ];
    }
    $docSlots = max([4, ...array_map(fn ($s) => count($s['documents']), $services)]);
    $docsHeading = collect($services)->pluck('heading')->filter()->first() ?? '';

    if ($home) {
        // Homepage note 0250: the eleven Section 02 service titles verbatim, then "Other Matter".
        $matterOptions = array_map(fn ($code) => [$code, (string) (content()->service($code)['title'] ?? ui('form.matter_type.option.'.$code))], array_keys((array) config('summit.services')));
        $matterOptions[] = ['other', $t('matter_type.option.other')];
    } else {
        $matterOptions = array_map(fn ($code) => [$code, ui('form.matter_type.option.'.$code)], StoreEnquiryRequest::matterTypes());
    }
    $matterCodes = array_column($matterOptions, 0);
    // Arabic, English, Russian: the order of the language switcher (Design 1 review LANG-02).
    $languageOptions = array_map(fn ($code) => [$code, $t('preferred_language.option.'.$code)], StoreEnquiryRequest::LANGUAGES);
    // "You are contacting us as" is pending client approval (D39): no choice, no context line, no review row.
    $audiences = [];
    if ($pending) {
        foreach (array_keys((array) config('audience_map.hero')) as $code) {
            $audiences[$code] = d2p('wizard.audience.'.$code);
        }
    }
    $consultants = [];
    foreach (content()->teamMembers() as $member) {
        $consultants[$member['code']] = (string) ($member['name'] ?? '');
    }
    $tools = ['gratuity' => d2p('wizard.tool.gratuity'), 'rent' => d2p('wizard.tool.rent')];

    // ---------------------------------------------------------------- prefill (validated codes only)
    $prefill = $demo ? [] : Prefill::fromRequest();
    if (! $pending) {
        unset($prefill['audience']);
    }
    $matter = $old('matter_type', $prefill['service'] ?? '');
    $matter = in_array($matter, $matterCodes, true) ? $matter : '';
    $situation = $prefill['situation'] ?? '';
    $situationText = '';
    if (preg_match('/^(S\d{2})-(\d)$/', $situation, $m) === 1) {
        $situationText = $services[$m[1]]['situations'][(int) $m[2] - 1] ?? '';
    }
    $audience = $prefill['audience'] ?? '';

    // Without JavaScript the context the visitor arrived with becomes visible, editable text in the summary
    // (the backend has no field for it). With JavaScript the controller moves it into the review message.
    $contextLines = array_values(array_filter([
        $audience !== '' ? d2p('wizard.context.audience', ['value' => $audiences[$audience] ?? '']) : null,
        $situationText !== '' ? d2p('wizard.context.situation', ['value' => $situationText]) : null,
        isset($prefill['consultant']) ? d2p('wizard.context.consultant', ['value' => $consultants[$prefill['consultant']] ?? '']) : null,
        isset($prefill['tool']) ? d2p('wizard.context.tool', ['value' => $tools[$prefill['tool']] ?? '']) : null,
    ]));
    $contextText = implode("\n", $contextLines);
    $summaryValue = $old('summary', $contextText);
    // With JavaScript the consultant and the calculator are shown as context lines on step 1 (Remove clears
    // them): printed here as the controller renders them, so the note is in place before it mounts.
    $contextFor = $demo ? (array) ($specimen['values'] ?? []) : $prefill;
    $contextJs = array_values(array_filter([
        isset($contextFor['consultant'], $consultants[$contextFor['consultant']]) ? d2p('wizard.context.consultant', ['value' => $consultants[$contextFor['consultant']]]) : null,
        isset($contextFor['tool'], $tools[$contextFor['tool']]) ? d2p('wizard.context.tool', ['value' => $tools[$contextFor['tool']]]) : null,
    ]));

    // ---------------------------------------------------------------- approved strings
    // Only the words "Privacy Policy" are a link (both files' red notes); the sentence is this page's own.
    $consent = (string) $t('consent.label');
    $linkText = ui('form.consent.link');
    $pos = $linkText !== '' ? mb_strpos($consent, $linkText) : false;
    $consentHtml = $pos === false
        ? e($consent)
        : e(mb_substr($consent, 0, $pos)).'<a class="t-link" href="'.e(SiteUrl::page('L01')).'">'.e($linkText).'</a>'.e(mb_substr($consent, $pos + mb_strlen($linkText)));

    $stepKeys = ['you', 'matter', 'deadline', 'documents', 'review'];
    $stepLabels = array_map(fn ($key) => d2p('wizard.steps.'.$key), $stepKeys);
    $listLabel = filled($title) ? (string) $title : ui('form.heading');

    // Server-rendered error summary (no-JS): ERROR_KEYS order, links to real ids (Design 1 review C6 / A11Y-13).
    $targetFor = ['name' => 'name', 'phone' => 'phone', 'email' => 'email', 'contact' => 'phone', 'summary' => 'summary',
        'deadline_date' => 'deadline-date', 'deadline_details' => 'deadline-details', 'attachment' => 'attachment', 'enquiry_consent' => 'consent'];
    $serverErrors = [];
    $initialErrors = [];
    foreach (StoreEnquiryRequest::ERROR_KEYS as $key) {
        if ($e($key) !== '') {
            $serverErrors[] = [isset($targetFor[$key]) ? $id($targetFor[$key]) : null, $err($key)];
            $initialErrors[$key] = [$err($key)];
        }
    }
    $contactError = $err('contact');

    // The step the stepped wizard opens on (wizard/controller.js mount(): the first step with an error after a
    // no-JS round trip, the review for a delivery notice, else step 1; FIELD_STEP in wizard/validate.js).
    $fieldStep = ['name' => 1, 'phone' => 1, 'email' => 1, 'contact' => 1, 'summary' => 2, 'deadline_date' => 3,
        'deadline_details' => 3, 'attachment' => 4, 'enquiry_consent' => 5];
    $errorSteps = [];
    foreach (StoreEnquiryRequest::ERROR_KEYS as $key) {
        if ($e($key) !== '') {
            $errorSteps[] = $fieldStep[$key] ?? 5;
        }
    }
    $specimenStep = is_array($specimen) ? max(1, min(5, (int) ($specimen['step'] ?? 1))) : 1;
    $start = match (true) {
        is_array($specimen) => $specimenStep,
        $received => 1,
        $errorSteps !== [] => min($errorSteps),
        in_array($status, ['failed', 'rate_limited'], true) => 5,
        default => 1,
    };
    // Load the controller at once (not when the wizard comes near): specimens, and after a no-JS round trip.
    $eager = $specimen !== null || $status !== null || $bag->isNotEmpty() || $hasOld;
    // The restore prompt may be offered (lazy.js checks this browser's draft at start-up): pending copy only.
    $offerDraft = $pending && ! $demo && $status === null && ! $hasOld && $bag->isEmpty();

    $turnstileKey = config('summit.turnstile.enabled') ? (string) config('summit.turnstile.site_key') : '';
    $servicesRef = 'd2-wizard-services-'.$locale;

    $data = [
        'locale' => $locale,
        'prefix' => $p,
        'endpoint' => route('enquiries.store'),
        'timeout' => (int) config('summit.enquiries.client_timeout_ms', 20000),
        'turnstile' => $turnstileKey,
        'draft' => $pending,
        'draftKey' => 'summit:draft:'.$locale,
        'draftDays' => $draftDays,
        'servicesRef' => $servicesRef,
        'labels' => [
            'matter' => array_column($matterOptions, 1, 0),
            'audience' => $audiences,
            'language' => array_column($languageOptions, 1, 0),
            'consultant' => $consultants,
            'tool' => $tools,
        ],
        'text' => [
            'context' => [
                'audience' => d2p('wizard.context.audience'),
                'situation' => d2p('wizard.context.situation'),
                'consultant' => d2p('wizard.context.consultant'),
                'tool' => d2p('wizard.context.tool'),
            ],
            'documents' => d2p('wizard.context.documents'),
            'notProvided' => d2p('wizard.review.not_provided'),
            'situationPlaceholder' => d2p('wizard.situation.placeholder'),
            'draftSaved' => d2p('wizard.draft.saved'),
            'draftRestored' => d2p('wizard.draft.restored'),
            'draftDiscarded' => d2p('wizard.draft.discarded'),
            'draftOff' => d2p('wizard.draft.off'),
            'prefillApplied' => d2p('wizard.prefill_applied'),
            'removed' => d2p('wizard.context.removed'),
            'messageLong' => d2p('wizard.message.too_long'),
            'errorsTitle' => ui('form.state.summary'),
            'busy' => $t('state.busy'),
            'turnstilePending' => d2p('wizard.turnstile.pending'),
        ],
        'initial' => [
            'status' => $status,
            'reference' => $reference,
            'errors' => $initialErrors,
            'old' => $hasOld,
        ],
        'prefill' => $prefill,
        'specimen' => $specimen,
    ];
    $json = fn ($value) => json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // "Keep a draft on this device": the lifetime in the visitor's language (plural forms), the number isolated.
    $draftHint = new \Illuminate\Support\HtmlString(str_replace('__N__', '<bdi>'.$draftDays.'</bdi>',
        e(trans_choice('proposed.d2.wizard.draft.keep_hint', $draftDays, ['count' => '__N__']))));
@endphp
<div {{ $attributes->class(['wizard', 'is-plain' => $plain]) }} id="{{ $id('wizard') }}" data-wizard data-copy="{{ $home ? 'home' : 'contact' }}"
    data-state="{{ $received ? 'received' : 'idle' }}" data-step="{{ $start }}"
    @unless ($plain) x-data="d2Wizard" data-wizard-mount="{{ $eager ? 'eager' : 'lazy' }}" @endunless
    @if ($specimen !== null) data-wizard-specimen @endif
    @if ($offerDraft && ! $plain) data-wizard-offer-draft data-draft-key="summit:draft:{{ $locale }}" data-draft-days="{{ $draftDays }}" @endif>
    @once('d2-wizard-services-'.$locale)
        <script type="application/json" id="{{ $servicesRef }}" nonce="{{ Vite::cspNonce() }}">{!! $json($services) !!}</script>
    @endonce
    @unless ($plain)
        <script type="application/json" data-wizard-data nonce="{{ Vite::cspNonce() }}">{!! $json($data) !!}</script>
    @endunless

    {{-- D37: the one progress indicator (a step list; "Step 1 of 5: You" on phones). One template per step; the
         controller swaps in the list of the step it shows. --}}
    <div class="wizard__steps" data-wizard-steps data-js-only @if ($received) hidden @endif>
        <x-d2.ui.steps :steps="$stepLabels" :current="$start" :interactive="true" :label="$listLabel" :proposed="true" />
    </div>
    @unless ($plain)
        @foreach (range(1, count($stepKeys)) as $n)
            <template data-wizard-steps-for="{{ $n }}"><x-d2.ui.steps :steps="$stepLabels" :current="$n" :interactive="true" :label="$listLabel" :proposed="true" /></template>
        @endforeach
    @endunless

    {{-- Restore an unfinished draft from this browser (shown by the controller): pending copy only (D39). --}}
    @if ($pending)
        <x-d2.wizard.restore />
    @endif

    {{-- The states in the Word files' order: received (only after the server accepted the request), then the
         error summary, then the failures. One at a time; each takes focus when it appears. --}}
    <x-d2.wizard.success :prefix="$p" :reference="$reference" :shown="$received" :message="$t('state.success')" />
    <x-d2.wizard.notices :prefix="$p" :status="$status" :summary="$serverErrors" :failure="$t('state.failure')" />

    <form class="wizard__form" method="post" action="{{ route('enquiries.store') }}" novalidate data-wizard-form
        @if ($attachments) enctype="multipart/form-data" @endif
        @if ($labelledby) aria-labelledby="{{ $labelledby }}" @else aria-label="{{ $listLabel }}" @endif @if ($received) hidden @endif>
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}" autocomplete="off" data-wizard-key>
        {{-- Honeypot: people never see or reach it; anything typed here marks the enquiry as spam. --}}
        <div class="wizard__trap" aria-hidden="true">
            <input type="text" name="website" id="{{ $id('website') }}" tabindex="-1" autocomplete="off" value="">
        </div>

        {{-- ============================================================ 1 You: name and contact details --}}
        <x-d2.wizard.step :n="1" step-key="you" :prefix="$p" :hidden="$specimen !== null && $specimenStep !== 1" :later="$start !== 1">
            <div class="wizard__context" data-wizard-context data-js-only @if ($contextJs === []) hidden @endif>
                <x-d2.ui.callout tone="info" size="sm">
                    <p @proposed>{{ d2p('wizard.context.note') }}</p>
                    <ul class="wizard__context-list" data-wizard-context-list>@foreach ($contextJs as $line)<li @proposed>{{ $line }}</li>@endforeach</ul>
                    <div class="wizard__inline-actions">
                        <x-d2.ui.button variant="text" size="40" icon="close" data-wizard-context-remove :data-copy-status="$proposed">{{ d2p('wizard.context.remove') }}</x-d2.ui.button>
                    </div>
                </x-d2.ui.callout>
            </div>

            @if ($audiences !== [])
                <x-d2.ui.field as="fieldset" :id="$id('audience')" :label="d2p('wizard.audience.legend')" :optional-label="true" data-js-only data-pending :data-copy-status="$proposed">
                    <div class="wizard__choices">
                        @foreach ($audiences as $code => $label)
                            <x-d2.ui.radio name="{{ $p }}_audience" :value="$code" :id="$id('audience-'.$code)" :form="$local" :checked="$audience === $code"
                                :hint="d2p('wizard.audience.'.$code.'_hint')" wrap-class="wizard__choice" data-wizard-audience>
                                <span class="wizard__choice-title">{{ $label }}</span>
                            </x-d2.ui.radio>
                        @endforeach
                    </div>
                </x-d2.ui.field>
            @endif

            <x-d2.ui.field :id="$id('name')" :label="$t('name.label')" :hint="$t('name.help')"
                :error="$err('name') ?: $t('name.error')" :invalid="$err('name') !== ''" :required="true">
                <x-d2.ui.input name="name" autocomplete="name" maxlength="120" :value="$old('name')" data-wizard-field="name" />
            </x-d2.ui.field>

            {{-- Phone OR e-mail (both validated when both are given); the group carries the "contact" error. Its
                 hidden name joins the two labels with the language's comma (no middle dot: D02). --}}
            <x-d2.ui.field as="fieldset" :id="$id('contact')" :label="$t('phone.label').($locale === 'ar' ? '، ' : ', ').$t('email.label')" :label-hidden="true"
                :hint="$t('contact_group.help')" :error="$contactError ?: $t('contact_group.error')" :invalid="$contactError !== ''">
                <div class="wizard__row">
                    <x-d2.ui.field :id="$id('phone')" :label="$t('phone.label')" :hint="$t('phone.help')"
                        :error="$err('phone') ?: $t('phone.error')" :invalid="$err('phone') !== ''">
                        <x-d2.ui.input name="phone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" maxlength="40"
                            :value="$old('phone', '+971 ')" :aria-invalid="($contactError !== '' || $err('phone') !== '') ? 'true' : null" data-wizard-field="phone" />
                    </x-d2.ui.field>
                    <x-d2.ui.field :id="$id('email')" :label="$t('email.label')" :hint="$t('email.help')"
                        :error="$err('email') ?: $t('email.error')" :invalid="$err('email') !== ''">
                        <x-d2.ui.input name="email" type="email" inputmode="email" autocomplete="email" dir="ltr" maxlength="254"
                            :value="$old('email')" :aria-invalid="($contactError !== '' || $err('email') !== '') ? 'true' : null" data-wizard-field="email" />
                    </x-d2.ui.field>
                </div>
            </x-d2.ui.field>

            <x-d2.ui.field :id="$id('language')" :label="$t('preferred_language.label')">
                <x-d2.ui.select name="preferred_language" :options="$languageOptions" :value="$old('preferred_language')"
                    :placeholder="$t('preferred_language.placeholder')" data-wizard-field="preferred_language" />
            </x-d2.ui.field>
        </x-d2.wizard.step>

        {{-- ============================================================ 2 Your matter: type and summary --}}
        <x-d2.wizard.step :n="2" step-key="matter" :prefix="$p" :hidden="$specimen !== null && $specimenStep !== 2" :later="$start !== 2">
            <x-d2.ui.field :id="$id('matter')" :label="$t('matter_type.label')">
                <x-d2.ui.select name="matter_type" :options="$matterOptions" :value="$matter" :placeholder="$t('matter_type.placeholder')" data-wizard-field="matter_type" />
            </x-d2.ui.field>

            <x-d2.ui.field :id="$id('situation')" :label="d2p('wizard.situation.label')" :optional-label="true" data-js-only data-wizard-situation-field
                :data-copy-status="$proposed" :hidden="$matter === '' || ! isset($services[$matter])">
                <x-d2.ui.select :form="$local" :placeholder="d2p('wizard.situation.placeholder')" :value="$situation"
                    :options="isset($services[$matter]) ? array_map(fn ($i, $text) => [$matter.'-'.($i + 1), $text], array_keys($services[$matter]['situations']), $services[$matter]['situations']) : []"
                    data-wizard-situation />
            </x-d2.ui.field>

            <x-d2.ui.field :id="$id('summary')" :label="$t('summary.label')" :hint="$t('summary.help')"
                :error="$err('summary') ?: $t('summary.error')" :invalid="$err('summary') !== ''" :required="true">
                <x-d2.ui.textarea name="summary" rows="7" maxlength="5000" :value="$summaryValue" data-context="{{ $contextText }}" data-wizard-field="summary" />
            </x-d2.ui.field>
        </x-d2.wizard.step>

        {{-- ============================================================ 3 Deadline --}}
        <x-d2.wizard.step :n="3" step-key="deadline" :prefix="$p" :hidden="$specimen !== null && $specimenStep !== 3" :later="$start !== 3">
            <div class="wizard__row">
                <x-d2.ui.field :id="$id('deadline-date')" :label="$t('deadline_date.label')" :hint="$t('deadline_date.help')"
                    :error="$err('deadline_date') ?: $t('deadline_date.error')" :invalid="$err('deadline_date') !== ''">
                    <x-d2.ui.date name="deadline_date" :value="$old('deadline_date')" min="1900-01-01" max="2100-12-31" data-wizard-field="deadline_date" />
                </x-d2.ui.field>
                <x-d2.ui.field :id="$id('deadline-details')" :label="ui('form.deadline_details.label')" :hint="$t('deadline_details.help')"
                    :error="$err('deadline_details') ?: ui('form.state.summary')" :invalid="$err('deadline_details') !== ''">
                    <x-d2.ui.input name="deadline_details" maxlength="500" :value="$old('deadline_details')" data-wizard-field="deadline_details" />
                </x-d2.ui.field>
            </div>
        </x-d2.wizard.step>

        {{-- ============================================================ 4 Documents (the checklist; the attachment never today)
             The checklist needs JavaScript. While attachments are enabled the step exists without it too (the
             one document posts with the plain form); otherwise the whole step is JavaScript-only. --}}
        <x-d2.wizard.step :n="4" step-key="documents" :prefix="$p" :js-only="! $attachments" :intro-js-only="$attachments" :later="$start !== 4"
            :hidden="$specimen !== null ? $specimenStep !== 4 : ! $attachments">
            <x-d2.ui.field as="fieldset" :id="$id('documents')" :label="$docsHeading" data-wizard-docs data-js-only :hidden="! isset($services[$matter])">
                <div class="wizard__docs">
                    @for ($i = 0; $i < $docSlots; $i++)
                        @php $doc = $services[$matter]['documents'][$i] ?? null; @endphp
                        <div class="wizard__doc" data-wizard-doc-row="{{ $i }}" @if ($doc === null) hidden @endif>
                            <x-d2.ui.checkbox :id="$id('doc-'.($i + 1))" :form="$local" :value="$i" data-wizard-doc="{{ $i }}">
                                <span data-wizard-doc-label>{{ Bidi::isolate($doc) }}</span>
                            </x-d2.ui.checkbox>
                        </div>
                    @endfor
                </div>
                <p class="wizard__docs-note" data-wizard-docs-note>{{ $services[$matter]['note'] ?? '' }}</p>
            </x-d2.ui.field>
            <x-d2.ui.callout tone="info" size="sm" data-wizard-docs-empty="none" data-js-only :hidden="$matter !== ''">
                <p @proposed>{{ d2p('wizard.documents.none') }}</p>
            </x-d2.ui.callout>
            <x-d2.ui.callout tone="info" size="sm" data-wizard-docs-empty="generic" data-js-only :hidden="$matter === '' || isset($services[$matter])">
                <p @proposed>{{ d2p('wizard.documents.generic') }}</p>
            </x-d2.ui.callout>
            @if ($attachments)
                {{-- A styleguide specimen never posts: its control has no name (and runs only when stepped). --}}
                @if ($demo)
                    <x-d2.upload.dropzone :prefix="$p" :specimen="$plain ? 'plain' : 'live'" />
                @else
                    <x-d2.upload.dropzone :prefix="$p" :error="$e('attachment')" />
                @endif
            @endif
        </x-d2.wizard.step>

        {{-- ============================================================ 5 Review: notices, consent, submit --}}
        <x-d2.wizard.step :n="5" step-key="review" :prefix="$p" :hidden="$specimen !== null && $specimenStep !== 5" :later="$start !== 5">
            <div class="wizard__summary" data-js-only>
                @php
                    $groups = [
                        1 => array_filter([
                            'audience' => $audiences !== [] ? d2p('wizard.audience.legend') : null,
                            'name' => $t('name.label'),
                            'phone' => $t('phone.label'),
                            'email' => $t('email.label'),
                            'language' => $t('preferred_language.label'),
                        ]),
                        2 => ['matter' => $t('matter_type.label'), 'situation' => d2p('wizard.situation.label')],
                        3 => ['deadline_date' => $t('deadline_date.label'), 'deadline_details' => ui('form.deadline_details.label')],
                        4 => [],
                    ];
                @endphp
                @foreach ($groups as $n => $rows)
                    <div class="wizard__group" role="group" aria-labelledby="{{ $id('review-'.$n) }}">
                        <div class="wizard__group-head">
                            <h4 class="wizard__group-title" id="{{ $id('review-'.$n) }}" @proposed>{{ $stepLabels[$n - 1] }}</h4>
                            <x-d2.ui.button variant="text" size="40" data-goto-step="{{ $n }}" :data-copy-status="$proposed">{{ d2p('wizard.review.change') }}<span class="sr-only">: {{ $stepLabels[$n - 1] }}</span></x-d2.ui.button>
                        </div>
                        @if ($n === 4)
                            <ul class="wizard__answer-docs" data-review="documents"></ul>
                            @if ($attachments)
                                <dl class="wizard__answers">
                                    <div class="wizard__answer" data-review-row="attachment">
                                        <dt class="wizard__answer-label" @proposed>{{ d2p('wizard.review.attachment') }}</dt>
                                        <dd class="wizard__answer-value"><span class="wizard__answer-text" data-review="attachment"></span></dd>
                                    </div>
                                </dl>
                            @endif
                        @else
                            <dl class="wizard__answers">
                                @foreach ($rows as $key => $label)
                                    <div class="wizard__answer" data-review-row="{{ $key }}">
                                        {{-- the audience question and the situation are proposed copy (D39): marked like their fields --}}
                                        <dt class="wizard__answer-label" @if (in_array($key, ['audience', 'situation'], true)) @proposed @endif>{{ $label }}</dt>
                                        <dd class="wizard__answer-value"><span class="wizard__answer-text" data-review="{{ $key }}" @if (in_array($key, ['phone', 'email'], true)) dir="ltr" @endif></span></dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                @endforeach

                <div class="wizard__stale" data-wizard-stale hidden>
                    <x-d2.ui.callout tone="info" size="sm">
                        <p @proposed>{{ d2p('wizard.message.stale') }}</p>
                        <div class="wizard__inline-actions">
                            <x-d2.ui.button variant="secondary" size="40" data-wizard-message-update :data-copy-status="$proposed">{{ d2p('wizard.message.update') }}</x-d2.ui.button>
                        </div>
                    </x-d2.ui.callout>
                </div>

                <x-d2.ui.field :id="$id('message')" :label="d2p('wizard.message.label')" :hint="d2p('wizard.message.hint')"
                    :error="$t('summary.error')" :invalid="false" :required="true" :data-copy-status="$proposed">
                    <x-d2.ui.textarea :form="$local" rows="9" maxlength="5000" data-wizard-message />
                </x-d2.ui.field>
            </div>

            {{-- The visible notices before the consent: both in the Contact file; on the home its sensitive-documents
                 notice (its engagement notice follows the submit button, as in the homepage file). --}}
            <div class="wizard__notes">
                <p>{{ $t('notice.sensitive') }}</p>
                @unless ($home)
                    <p>{{ $t('notice.engagement') }}</p>
                @endunless
            </div>

            {{-- Required and unchecked: aria-required (not required), so it is never reported invalid before a submit. --}}
            <x-d2.ui.checkbox name="enquiry_consent" value="1" :id="$id('consent')" aria-required="true" :checked="$old('enquiry_consent') !== ''"
                :error="$err('enquiry_consent') ?: $t('consent.error')" :invalid="$err('enquiry_consent') !== ''" wrap-class="wizard__consent" data-wizard-field="enquiry_consent">
                {!! $consentHtml !!}<span class="field__req" aria-hidden="true">*</span>
            </x-d2.ui.checkbox>

            <div class="wizard__turnstile" data-wizard-turnstile></div>
        </x-d2.wizard.step>

        {{-- The autosave choice first (pending copy, D39: the draft lives on this device only; unticking deletes it at
             once), then Back (type=button) and Next, the first submit button, so Enter in a field means "Next". The
             same order on screen and for the keyboard at every width (components/wizard.css). --}}
        <div class="wizard__actions">
            @if ($pending)
                <div class="wizard__draft" data-js-only data-pending @proposed>
                    <x-d2.ui.checkbox :id="$id('autosave')" :form="$local" :checked="true" :hint="$draftHint" wrap-class="wizard__autosave" data-wizard-autosave>{{ d2p('wizard.draft.keep') }}</x-d2.ui.checkbox>
                </div>
            @endif
            <x-d2.ui.button variant="secondary" icon="form-back" data-wizard-back data-js-only :hidden="$start === 1" :data-copy-status="$proposed">{{ d2p('wizard.back') }}</x-d2.ui.button>
            <x-d2.ui.button variant="ink" type="submit" icon-end="form-next" data-wizard-next data-js-only :hidden="$start === 5" :data-copy-status="$proposed">{{ d2p('wizard.next') }}</x-d2.ui.button>
            {{-- Ink (D06). No decorative icon: while sending, components/wizard.css puts the spinner before the
                 page's busy label, so the label stays readable. --}}
            <x-d2.ui.button variant="ink" type="submit" data-wizard-submit :hidden="$specimen !== null && $specimenStep !== 5"
                :data-wizard-later="$start !== 5 ? true : null">{{ $t('submit') }}</x-d2.ui.button>
        </div>

        @if ($home)
            {{-- The homepage file: "Submitting this form does not mean …" follows "Submit Consultation Request". With
                 JavaScript it shows on the review step only, beside the button it explains (components/wizard.css). --}}
            <p class="wizard__after" data-wizard-after>{{ $t('notice.engagement') }}</p>
        @endif
    </form>
</div>
