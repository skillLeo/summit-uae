@props([
    'email' => false,   // also offer e-mail (the uncertain-delivery notice lists all three channels)
])
{{--
    Direct channels next to a delivery notice (failed, uncertain, rate limited, security check unavailable):
    docs/enquiries.md §4 "phone / WhatsApp stay visible". Approved values only: setting('phone_*'),
    setting('whatsapp_url') (the approved per-language draft; opens WhatsApp, never sends), setting('email*').
    Numbers and addresses keep their own direction (<bdi dir="ltr">).
--}}
<ul {{ $attributes->class(['wizard__channels']) }} aria-label="{{ content()->page('P07')['content']['details']['heading'] ?? '' }}">
    <li>
        <x-d2.ui.button variant="secondary" size="40" icon="phone" :href="setting('phone_href')"><bdi dir="ltr">{{ setting('phone_display') }}</bdi></x-d2.ui.button>
    </li>
    <li>
        <x-d2.ui.button variant="whatsapp" size="40" :href="setting('whatsapp_url')">{{ ui('cta.whatsapp') }}</x-d2.ui.button>
    </li>
    @if ($email)
        <li>
            <x-d2.ui.button variant="secondary" size="40" icon="mail" :href="setting('email_href')"><bdi dir="ltr">{{ setting('email') }}</bdi></x-d2.ui.button>
        </li>
    @endif
</ul>
