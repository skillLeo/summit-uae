@props([
    'heading',              // approved H2 of the page's final consultation section
    'text' => null,         // approved paragraph
    'cta' => null,          // ['label' => approved consultation label, 'href' => SiteUrl::consultation()/Prefill::url()]
    'whatsapp' => null,     // approved WhatsApp label (the link is setting('whatsapp_url'): the approved draft)
    'phone' => null,        // approved telephone label (the number itself)
    'email' => null,        // approved e-mail label (the address itself)
    'notice' => null,       // approved notice ("Sending an enquiry does not mean …")
    'headingId' => 'contact-title',
])
{{--
    <x-d2.resources.contact-panel :heading="…" :text="…" :cta="['label' => …, 'href' => …]" … />
    Final consultation section of the Legal Knowledge Centre (P03) and the Case Studies page (P04): a white
    panel, the text at the inline start and the actions at the inline end from 1024 px (stacked below), then
    the approved notice under both, in the DOCX order (after the e-mail: phones and screen readers reach the
    channels first). The consultation link is the ink button (Design 2.1, D06: gold is the hero primary only;
    Contact Us goes to the home form until contact_page_released: SiteUrl::consultation). WhatsApp opens the
    approved per-language draft in a new tab and never sends it. Telephone and e-mail are the kit's contact
    channels (<x-d2.inner.channels>: their functional glyphs, the values left to right in a <bdi>, the same rows
    as the other CTA panels and the footer). No address, no map, no response-time promise.
--}}
@php
    use App\Design\D2\Bidi;
@endphp
<section {{ $attributes->class(['section', 'kc-contact']) }} aria-labelledby="{{ $headingId }}">
    <div class="container">
        <div class="kc-contact__panel">
            <div class="kc-contact__text">
                <h2 class="kc-contact__title" id="{{ $headingId }}">{{ Bidi::isolate($heading) }}</h2>
                @if (filled($text))
                    <p class="kc-contact__lead">{{ Bidi::isolate($text) }}</p>
                @endif
            </div>
            <div class="kc-contact__actions">
                {{-- The two buttons share one height when a long approved label wraps on a phone (resources.css). --}}
                <div class="kc-contact__buttons">
                    @if (! empty($cta['label']) && ! empty($cta['href']))
                        <x-d2.ui.button variant="ink" size="48" :full="true" :href="$cta['href']">{{ Bidi::isolate($cta['label']) }}</x-d2.ui.button>
                    @endif
                    @if (filled($whatsapp))
                        <x-d2.ui.button variant="whatsapp" size="48" :full="true" :href="setting('whatsapp_url')" :external="true">{{ Bidi::isolate($whatsapp) }}</x-d2.ui.button>
                    @endif
                </div>
                {{-- The kit's contact channels (one pattern with the other CTA panels and the footer). --}}
                <x-d2.inner.channels class="kc-contact__channels" :items="[
                    ['kind' => 'phone', 'href' => (string) setting('phone_href'), 'text' => (string) $phone],
                    ['kind' => 'email', 'href' => (string) setting('email_href'), 'text' => (string) $email],
                ]" />
            </div>
            @if (filled($notice))
                <x-d2.ui.callout tone="info" size="sm" class="kc-contact__notice">{{ Bidi::isolate($notice) }}</x-d2.ui.callout>
            @endif
        </div>
    </div>
</section>
