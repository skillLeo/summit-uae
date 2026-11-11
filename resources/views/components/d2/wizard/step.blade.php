@props([
    'n',                  // step number (1–5)
    'stepKey',            // you | matter | deadline | documents | review (wizard.steps.* / wizard.intro.*)
    'prefix',             // the wizard's id prefix
    'hidden' => false,    // not the current step (server-rendered stepped specimens; the JS-only Documents step)
    'jsOnly' => false,    // the whole step needs JavaScript (Documents without attachments): hidden in the plain form
    'introJsOnly' => false, // the intro line describes the stepped version only (Documents with attachments)
    'later' => false,     // not the step the wizard opens on: hidden with JavaScript until the controller takes over
    'total' => 5,
])
{{--
    One wizard step: a group named by its heading (h3, under the H2 of the form's section on the home and on P07:
    the focus target on every step change, with a "Step n of 5:" prefix for screen readers while the wizard runs
    as steps), a one-line intro and the step's fields. Without JavaScript every step but the JS-only ones is a group of the one plain form.
    (A div with role="group", not a fieldset: a legend would sit on the separator line of the plain form.)
    data-wizard-later: with JavaScript only the opening step shows from the first paint (components/wizard.css),
    before the lazy controller mounts and switches steps with the hidden attribute.
--}}
<div {{ $attributes->class(['wizard__step', 'wizard__step--'.$stepKey]) }} id="{{ $prefix }}-step-{{ $n }}" role="group"
    aria-labelledby="{{ $prefix }}-step-{{ $n }}-title" data-step-panel="{{ $n }}" @if ($jsOnly) data-js-only @endif @if ($later) data-wizard-later @endif @if ($hidden) hidden @endif>
    <div class="wizard__step-head">
        <h3 class="wizard__step-title" id="{{ $prefix }}-step-{{ $n }}-title" tabindex="-1" data-step-title>
            <span class="wizard__step-pos sr-only" data-js-only @proposed>{{ d2p('controls.step_of', ['current' => $n, 'total' => $total]) }}: </span><span @proposed>{{ d2p('wizard.steps.'.$stepKey) }}</span>
        </h3>
        <p class="wizard__step-intro" @if ($introJsOnly) data-js-only @endif @proposed>{{ d2p('wizard.intro.'.$stepKey) }}</p>
    </div>
    <div class="wizard__fields">
        {{ $slot }}
    </div>
</div>
