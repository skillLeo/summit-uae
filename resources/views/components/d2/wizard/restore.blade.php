@props([
    'shown' => false,   // styleguide only: the controller shows it when this browser holds a draft
])
{{--
    Restore prompt for an unfinished draft kept on this device (localStorage summit:draft:{locale}). Shown at
    start-up by wizard/lazy.js (before the lazy controller loads, so nothing moves when it does) and confirmed
    by the controller. Proposed copy: it says where the draft is kept, and "Delete draft" removes it from the
    device. Typing anywhere in the form without choosing dismisses it (the new answers replace it).
--}}
@php
    $proposed = app()->isProduction() ? null : 'proposed';
@endphp
<div {{ $attributes->class(['wizard__restore']) }} data-wizard-restore data-js-only @unless ($shown) hidden @endunless>
    <x-d2.ui.callout tone="info" size="sm">
        <p @proposed>{{ d2p('wizard.draft.found') }}</p>
        <div class="wizard__inline-actions wizard__inline-actions--pair">
            <x-d2.ui.button variant="ink" size="40" data-wizard-restore-accept :data-copy-status="$proposed">{{ d2p('wizard.draft.restore') }}</x-d2.ui.button>
            <x-d2.ui.button variant="secondary" size="40" data-wizard-restore-discard :data-copy-status="$proposed">{{ d2p('wizard.draft.discard') }}</x-d2.ui.button>
        </div>
    </x-d2.ui.callout>
</div>
