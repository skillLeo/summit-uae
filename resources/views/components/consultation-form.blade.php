@props(['idempotencyKey', 'preset' => null, 'prefix' => 'cf'])
@php
    use App\Support\SiteUrl;
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $e = fn (string $field) => $errors->first($field);
    $p = $prefix;
    $matterValues = array_merge(array_keys(config('summit.services')), ['other', 'unsure']);
    $selectedMatter = old('matter_type', in_array($preset, $matterValues, true) ? $preset : '');
    $attachments = config('summit.features.attachments_enabled');
    $reference = session('enquiry_reference');
    // Outcome flashed by EnquiryController for the no-JavaScript path: received|failed|rate_limited|invalid.
    $status = session('enquiry_status');

    // Consent label: link only the approved "Privacy Policy" phrase to L01.
    $consent = ui('form.consent.label');
    $linkText = ui('form.consent.link');
    $pos = mb_strpos($consent, $linkText);
    $consentHtml = $pos === false
        ? e($consent)
        : e(mb_substr($consent, 0, $pos)).'<a href="'.e(SiteUrl::page('L01')).'">'.e($linkText).'</a>'.e(mb_substr($consent, $pos + mb_strlen($linkText)));

    $hasErrors = $errors->any();
    $contactError = $e('contact');
@endphp
<div class="consultation" x-data="consultationForm" data-consultation
    data-endpoint="{{ route('enquiries.store') }}"
    data-timeout="{{ config('summit.enquiries.client_timeout_ms') }}"
    data-turnstile="{{ config('summit.turnstile.enabled') ? config('summit.turnstile.site_key') : '' }}"
    data-locale="{{ app()->getLocale() }}">

    <div class="form__intro">
        <p>{{ ui('form.intro') }}</p>
        <p>{{ ui('form.required_note') }}</p>
    </div>

    {{-- Delivery states (only one is ever visible). Announced politely; the error summary takes focus. --}}
    <div class="stack stack-16" aria-live="polite">
        <div class="notice notice--error" id="{{ $p }}-errors" tabindex="-1" @unless ($hasErrors) hidden @endunless x-ref="summary" x-bind:hidden="state !== 'invalid'">
            <x-icon name="alert" />
            <div class="notice__body">
                <p class="notice__title">{{ ui('form.state.summary') }}</p>
                <ul x-ref="summaryList">
                    @foreach ($errors->getMessages() as $field => $messages)
                        <li><a href="#{{ $p }}-{{ $field === 'contact' ? 'phone' : str_replace('_', '-', $field) }}">{{ $messages[0] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="notice notice--success" tabindex="-1" @unless ($reference) hidden @endunless x-ref="success" x-bind:hidden="state !== 'success'">
            <x-icon name="check-circle" />
            <div class="notice__body">
                <p>{{ ui('form.state.success') }}</p>
                <p><span>{{ proposed('form.reference') }}</span> <bdi class="notice__ref" dir="ltr" x-text="reference">{{ $reference }}</bdi></p>
                <div><button class="btn btn--ghost" type="button" x-on:click="reset">{{ ui('form.state.new_enquiry') }}</button></div>
            </div>
        </div>

        <div class="notice notice--error" tabindex="-1" @unless ($status === 'failed') hidden @endunless x-bind:hidden="state !== 'failed'" x-ref="failed">
            <x-icon name="alert" />
            <div class="notice__body"><p>{{ ui('form.state.failure') }}</p>
                <p class="cluster">
                    <a href="{{ setting('phone_href') }}"><bdi dir="ltr">{{ setting('phone_display') }}</bdi></a>
                    <a href="{{ setting('whatsapp_url') }}" target="_blank" rel="noopener noreferrer">{{ ui('cta.whatsapp') }}<span class="sr-only">{{ proposed('external.new_tab') }}</span></a>
                </p>
            </div>
        </div>

        <div class="notice notice--warning" tabindex="-1" hidden x-bind:hidden="state !== 'uncertain'" x-ref="uncertain">
            <x-icon name="info" />
            <div class="notice__body"><p>{{ ui('form.state.uncertain') }}</p>
                <p class="cluster">
                    <a href="{{ setting('phone_href') }}"><bdi dir="ltr">{{ setting('phone_display') }}</bdi></a>
                    <a href="{{ setting('whatsapp_url') }}" target="_blank" rel="noopener noreferrer">{{ ui('cta.whatsapp') }}<span class="sr-only">{{ proposed('external.new_tab') }}</span></a>
                    <a href="{{ setting('email_href') }}"><bdi dir="ltr">{{ setting('email') }}</bdi></a>
                </p>
            </div>
        </div>

        <div class="notice notice--warning" tabindex="-1" @unless ($status === 'rate_limited') hidden @endunless x-bind:hidden="state !== 'rate_limited'" x-ref="rateLimited">
            <x-icon name="clock" />
            <div class="notice__body"><p>{{ ui('form.state.rate_limited') }}</p>
                <p class="cluster">
                    <a href="{{ setting('phone_href') }}"><bdi dir="ltr">{{ setting('phone_display') }}</bdi></a>
                    <a href="{{ setting('whatsapp_url') }}" target="_blank" rel="noopener noreferrer">{{ ui('cta.whatsapp') }}<span class="sr-only">{{ proposed('external.new_tab') }}</span></a>
                </p>
            </div>
        </div>
    </div>

    <form class="form" method="post" action="{{ route('enquiries.store') }}" novalidate x-ref="form" x-on:submit.prevent="submit"
        x-show="state !== 'success'" @if ($reference) hidden @endif
        x-bind:class="{ 'is-busy': state === 'busy' }" @if ($attachments) enctype="multipart/form-data" @endif>
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}" x-ref="key">
        <div class="form__trap" aria-hidden="true">
            <input type="text" name="website" tabindex="-1" autocomplete="off" value="" aria-label="website">
        </div>

        {{-- Name --}}
        <div class="field">
            <label class="field__label" for="{{ $p }}-name">{{ ui('form.name.label') }} <span class="field__req" aria-hidden="true">*</span></label>
            <p class="field__help" id="{{ $p }}-name-help">{{ ui('form.name.help') }}</p>
            <input class="input" id="{{ $p }}-name" name="name" type="text" autocomplete="name" maxlength="120" required
                value="{{ old('name') }}" aria-describedby="{{ $p }}-name-help {{ $p }}-name-error"
                @if ($e('name')) aria-invalid="true" @endif data-field="name">
            <p class="field__error" id="{{ $p }}-name-error" @unless ($e('name')) hidden @endunless data-error-for="name" data-message="{{ ui('form.name.error') }}"><x-icon name="alert" /><span>{{ $e('name') ?: ui('form.name.error') }}</span></p>
        </div>

        {{-- Contact group: phone OR email (both validated when both are given) --}}
        <fieldset class="form__group" aria-describedby="{{ $p }}-contact-help {{ $p }}-contact-error">
            <legend class="sr-only">{{ ui('form.phone.label') }} · {{ ui('form.email.label') }}</legend>
            <p class="field__help" id="{{ $p }}-contact-help">{{ ui('form.contact_group.help') }}</p>
            <div class="form__row">
                <div class="field">
                    <label class="field__label" for="{{ $p }}-phone">{{ ui('form.phone.label') }}</label>
                    <p class="field__help" id="{{ $p }}-phone-help">{{ ui('form.phone.help') }}</p>
                    <input class="input input--ltr" id="{{ $p }}-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" maxlength="40"
                        value="{{ old('phone', '+971 ') }}" aria-describedby="{{ $p }}-phone-help {{ $p }}-phone-error"
                        @if ($e('phone') || $contactError) aria-invalid="true" @endif data-field="phone">
                    <p class="field__error" id="{{ $p }}-phone-error" @unless ($e('phone')) hidden @endunless data-error-for="phone" data-message="{{ ui('form.phone.error') }}"><x-icon name="alert" /><span>{{ $e('phone') ?: ui('form.phone.error') }}</span></p>
                </div>
                <div class="field">
                    <label class="field__label" for="{{ $p }}-email">{{ ui('form.email.label') }}</label>
                    <p class="field__help" id="{{ $p }}-email-help">{{ ui('form.email.help') }}</p>
                    <input class="input input--ltr" id="{{ $p }}-email" name="email" type="email" inputmode="email" autocomplete="email" dir="ltr" maxlength="190"
                        value="{{ old('email') }}" aria-describedby="{{ $p }}-email-help {{ $p }}-email-error"
                        @if ($e('email') || $contactError) aria-invalid="true" @endif data-field="email">
                    <p class="field__error" id="{{ $p }}-email-error" @unless ($e('email')) hidden @endunless data-error-for="email" data-message="{{ ui('form.email.error') }}"><x-icon name="alert" /><span>{{ $e('email') ?: ui('form.email.error') }}</span></p>
                </div>
            </div>
            <p class="field__error" id="{{ $p }}-contact-error" @unless ($contactError) hidden @endunless data-error-for="contact" data-message="{{ ui('form.contact_group.error') }}"><x-icon name="alert" /><span>{{ $contactError ?: ui('form.contact_group.error') }}</span></p>
        </fieldset>

        <div class="form__row">
            <div class="field">
                <label class="field__label" for="{{ $p }}-preferred-language">{{ ui('form.preferred_language.label') }}</label>
                <div class="select-wrap">
                    <select class="select" id="{{ $p }}-preferred-language" name="preferred_language" data-field="preferred_language">
                        <option value="">{{ ui('form.preferred_language.placeholder') }}</option>
                        @foreach (['ar', 'en', 'ru'] as $code)
                            <option value="{{ $code }}" @selected(old('preferred_language') === $code)>{{ ui('form.preferred_language.option.'.$code) }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" />
                </div>
            </div>
            <div class="field">
                <label class="field__label" for="{{ $p }}-matter-type">{{ ui('form.matter_type.label') }}</label>
                <div class="select-wrap">
                    <select class="select" id="{{ $p }}-matter-type" name="matter_type" data-field="matter_type" x-ref="matter">
                        <option value="">{{ ui('form.matter_type.placeholder') }}</option>
                        @foreach ($matterValues as $value)
                            <option value="{{ $value }}" @selected($selectedMatter === $value)>{{ ui('form.matter_type.option.'.$value) }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" />
                </div>
            </div>
        </div>

        {{-- Matter summary --}}
        <div class="field">
            <label class="field__label" for="{{ $p }}-summary">{{ ui('form.summary.label') }} <span class="field__req" aria-hidden="true">*</span></label>
            <p class="field__help" id="{{ $p }}-summary-help">{{ ui('form.summary.help') }}</p>
            <textarea class="textarea" id="{{ $p }}-summary" name="summary" rows="7" maxlength="5000" required
                aria-describedby="{{ $p }}-summary-help {{ $p }}-summary-error" @if ($e('summary')) aria-invalid="true" @endif data-field="summary">{{ old('summary') }}</textarea>
            <p class="field__error" id="{{ $p }}-summary-error" @unless ($e('summary')) hidden @endunless data-error-for="summary" data-message="{{ ui('form.summary.error') }}"><x-icon name="alert" /><span>{{ $e('summary') ?: ui('form.summary.error') }}</span></p>
        </div>

        <div class="form__row">
            <div class="field">
                <label class="field__label" for="{{ $p }}-deadline-date">{{ ui('form.deadline_date.label') }}</label>
                <p class="field__help" id="{{ $p }}-deadline-date-help">{{ ui('form.deadline_date.help') }}</p>
                <input class="input" id="{{ $p }}-deadline-date" name="deadline_date" type="date" value="{{ old('deadline_date') }}"
                    aria-describedby="{{ $p }}-deadline-date-help {{ $p }}-deadline-date-error" @if ($e('deadline_date')) aria-invalid="true" @endif data-field="deadline_date">
                <p class="field__error" id="{{ $p }}-deadline-date-error" @unless ($e('deadline_date')) hidden @endunless data-error-for="deadline_date" data-message="{{ ui('form.deadline_date.error') }}"><x-icon name="alert" /><span>{{ $e('deadline_date') ?: ui('form.deadline_date.error') }}</span></p>
            </div>
            <div class="field">
                <label class="field__label" for="{{ $p }}-deadline-details">{{ ui('form.deadline_details.label') }}</label>
                <p class="field__help" id="{{ $p }}-deadline-details-help">{{ ui('form.deadline_details.help') }}</p>
                <input class="input" id="{{ $p }}-deadline-details" name="deadline_details" type="text" maxlength="500" value="{{ old('deadline_details') }}"
                    aria-describedby="{{ $p }}-deadline-details-help" data-field="deadline_details">
            </div>
        </div>

        @if ($attachments)
            {{-- Only rendered when the backend validates, scans and restricts uploads. --}}
            <div class="field">
                <label class="field__label" for="{{ $p }}-attachment">{{ ui('form.attachment.label') }}</label>
                <p class="field__help" id="{{ $p }}-attachment-help">{{ ui('form.attachment.help') }}</p>
                <input class="input" id="{{ $p }}-attachment" name="attachment" type="file" accept="application/pdf,image/jpeg,image/png" aria-describedby="{{ $p }}-attachment-help {{ $p }}-attachment-error" data-field="attachment">
                <p class="field__error" id="{{ $p }}-attachment-error" @unless ($e('attachment')) hidden @endunless data-error-for="attachment"><x-icon name="alert" /><span>{{ $e('attachment') }}</span></p>
            </div>
        @endif

        <div class="form__notices">
            <p>{{ ui('form.notice.sensitive') }}</p>
            <p>{{ ui('form.notice.engagement') }}</p>
        </div>

        {{-- Required, unchecked consent --}}
        <div class="field">
            <label class="check" for="{{ $p }}-consent">
                <input class="check__input" id="{{ $p }}-consent" name="enquiry_consent" type="checkbox" value="1" required
                    @checked(old('enquiry_consent')) aria-describedby="{{ $p }}-consent-error" @if ($e('enquiry_consent')) aria-invalid="true" @endif data-field="enquiry_consent">
                <span class="check__text">{!! $consentHtml !!} <span class="field__req" aria-hidden="true">*</span></span>
            </label>
            <p class="field__error" id="{{ $p }}-consent-error" @unless ($e('enquiry_consent')) hidden @endunless data-error-for="enquiry_consent" data-message="{{ ui('form.consent.error') }}"><x-icon name="alert" /><span>{{ $e('enquiry_consent') ?: ui('form.consent.error') }}</span></p>
        </div>

        <div class="cf-turnstile-slot" x-ref="turnstile"></div>

        <div class="form__actions" x-ref="actions">
            <button class="btn btn--lg" type="submit" x-bind:disabled="state === 'busy'" x-bind:aria-busy="state === 'busy'" data-submit
                data-busy-label="{{ ui('form.state.busy') }}" data-idle-label="{{ ui('form.submit') }}">
                <x-icon name="spinner" class="spin" x-show="state === 'busy'" x-cloak />
                <span x-ref="submitLabel">{{ ui('form.submit') }}</span>
            </button>
        </div>
    </form>
</div>
