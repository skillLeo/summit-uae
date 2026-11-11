@props([
    'prefix' => 'wz',      // the wizard's id prefix
    'status' => null,      // no-JS outcome flashed by the endpoint: failed | rate_limited (others stay hidden)
    'summary' => [],       // server-rendered error summary: [[target id or null, approved message], …]
    'show' => [],          // styleguide only: notice kinds to show now (errors, failed, rate_limited, uncertain, …)
    'failure' => null,     // the page's own failure sentence (the home form: the homepage file's; default ui('form.state.failure'))
])
{{--
    The wizard's error summary and delivery notices (docs/enquiries.md §4), after the received panel and in the
    Word files' order (the field errors, then the failures). Approved copy (ui('form.state.*'); the failure in the
    page's own wording)
    with the direct channels next to every failure; the security-check notices are proposed copy. One at a
    time is visible; the controller focuses the one it shows (tabindex -1). Without JavaScript the endpoint's
    flash decides: the error summary (invalid), failed or rate limited.
    Design 2.1 (Phase 1): each callout shows its tone's own icon (warning, error) or info; no clock or shield
    (brief 3.6 rule 5).
--}}
@php
    $visible = fn (string $kind, bool $server = false): bool => $server || in_array($kind, (array) $show, true);
@endphp
<div {{ $attributes->class(['wizard__notices']) }}>
    <div class="wizard__notice" id="{{ $prefix }}-errors" tabindex="-1" data-wizard-notice="errors" @unless ($visible('errors', $summary !== [])) hidden @endunless>
        <x-d2.ui.callout tone="danger" :title="ui('form.state.summary')">
            <ul class="wizard__error-list" data-wizard-error-list>
                @foreach ($summary as [$target, $message])
                    <li>@if ($target)<a class="t-link" href="#{{ $target }}">{{ $message }}</a>@else{{ $message }}@endif</li>
                @endforeach
            </ul>
        </x-d2.ui.callout>
    </div>

    <div class="wizard__notice" id="{{ $prefix }}-failed" tabindex="-1" data-wizard-notice="failed" @unless ($visible('failed', $status === 'failed')) hidden @endunless>
        <x-d2.ui.callout tone="danger">
            <p>{{ filled($failure) ? $failure : ui('form.state.failure') }}</p>
            <x-d2.wizard.channels />
        </x-d2.ui.callout>
    </div>

    <div class="wizard__notice" id="{{ $prefix }}-rate-limited" tabindex="-1" data-wizard-notice="rate_limited" @unless ($visible('rate_limited', $status === 'rate_limited')) hidden @endunless>
        <x-d2.ui.callout tone="warning">
            <p>{{ ui('form.state.rate_limited') }}</p>
            <x-d2.wizard.channels />
        </x-d2.ui.callout>
    </div>

    <div class="wizard__notice" id="{{ $prefix }}-uncertain" tabindex="-1" data-wizard-notice="uncertain" data-js-only @unless ($visible('uncertain')) hidden @endunless>
        <x-d2.ui.callout tone="warning" icon="info">
            <p>{{ ui('form.state.uncertain') }}</p>
            <x-d2.wizard.channels :email="true" />
        </x-d2.ui.callout>
    </div>

    <div class="wizard__notice" id="{{ $prefix }}-turnstile" tabindex="-1" data-wizard-notice="turnstile_incomplete" data-js-only @unless ($visible('turnstile_incomplete')) hidden @endunless>
        <x-d2.ui.callout tone="warning">
            <p @proposed>{{ d2p('wizard.turnstile.incomplete') }}</p>
        </x-d2.ui.callout>
    </div>

    <div class="wizard__notice" id="{{ $prefix }}-turnstile-error" tabindex="-1" data-wizard-notice="turnstile_unavailable" data-js-only @unless ($visible('turnstile_unavailable')) hidden @endunless>
        <x-d2.ui.callout tone="warning">
            <p @proposed>{{ d2p('wizard.turnstile.unavailable') }}</p>
            <x-d2.wizard.channels />
        </x-d2.ui.callout>
    </div>
</div>
