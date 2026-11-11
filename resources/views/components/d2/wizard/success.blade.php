@props([
    'prefix' => 'wz',
    'reference' => null,   // SLC-… from the endpoint (no-JS flash or the JSON response)
    'shown' => false,
    'message' => null,     // the page's own success sentence (the home form: the homepage file's; default ui('form.state.success'))
])
{{--
    Received (status "received" only: never on a click, a timer or a browser check). The page's approved message
    (ui('form.state.success'), or the homepage file's on the home), the reference in its own direction, and the approved "Send a New Enquiry",
    which without JavaScript is a plain link back to the form.
--}}
<div {{ $attributes->class(['wizard__success']) }} id="{{ $prefix }}-success" tabindex="-1" data-wizard-success @unless ($shown) hidden @endunless>
    <span class="wizard__success-mark" aria-hidden="true"><x-d2.ui.icon name="check" size="28" /></span>
    <div class="wizard__success-body">
        <p class="wizard__success-text">{{ filled($message) ? $message : ui('form.state.success') }}</p>
        <p class="wizard__reference"><span class="wizard__reference-label" @proposed>{{ d2p('wizard.reference') }}</span> <bdi class="wizard__reference-code" dir="ltr" data-wizard-reference>{{ $reference }}</bdi></p>
        <x-d2.ui.button variant="secondary" :href="\App\Support\SiteUrl::consultation()" data-wizard-new>{{ ui('form.state.new_enquiry') }}</x-d2.ui.button>
    </div>
</div>
