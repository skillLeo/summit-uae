@props([
    'id' => 'request',      // section anchor
    'cta' => [],            // approved sections.cta / P02 cta {heading, text, note, request_label, whatsapp_label, phone, email}
    'consult' => null,      // consultation URL (App\Design\D2\Prefill::url([...]): the service prefilled)
    'whatsapp' => null,     // WhatsApp URL with this page's approved draft (opened, never sent)
    'service' => null,      // service code for the prefill hooks (null on the hub)
])
{{--
    Final consultation CTA of a service page and the P02 hub ("Tell Us About Your Matter"): a dark panel
    (.on-dark: the light focus ring) with the approved heading, text, the two approved actions, the approved
    phone and e-mail (the kit's contact channels, <x-d2.inner.channels>: left to right, <bdi>) and the approved note. Design 2.1 (brief §3.5, D03, D06): the
    request is the white button on dark (gold is the page hero's primary only, one per page) and carries no
    arrow; WhatsApp is the dark button with its glyph, in a new tab. Contact Us keeps the release rule: the
    consultation link goes to /xx/#consultation-form until the P07 page is released (Prefill::url()).
--}}
@php
    use App\Design\D2\Bidi;

    $consult ??= \App\Design\D2\Prefill::url($service ? ['service' => $service] : []);
    $whatsapp ??= setting('whatsapp_url');
    $phone = (string) ($cta['phone'] ?? '');
    $email = (string) ($cta['email'] ?? '');
@endphp
<section {{ $attributes->class(['svc-cta', 'on-dark']) }} id="{{ $id }}" aria-labelledby="{{ $id }}-title">
    <div class="svc-cta__copy">
        <h2 class="svc-cta__title" id="{{ $id }}-title">{{ Bidi::isolate($cta['heading'] ?? '') }}</h2>
        @if (! empty($cta['text']))
            <p class="svc-cta__text">{{ Bidi::isolate($cta['text']) }}</p>
        @endif
    </div>
    <div class="svc-cta__actions">
        <x-d2.ui.button variant="ink" size="48" :href="$consult" :data-prefill-service="$service">{{ $cta['request_label'] ?? ui('cta.request_consultation') }}</x-d2.ui.button>
        {{-- New tab, rel="noopener noreferrer" and the hidden "opens in a new tab", as every other WhatsApp link (DOCX S0n-L12). --}}
        <x-d2.ui.button variant="night" size="48" icon="whatsapp" :href="$whatsapp" :external="true">{{ $cta['whatsapp_label'] ?? ui('cta.whatsapp') }}</x-d2.ui.button>
    </div>
    {{-- The kit's contact channels (one pattern with the other CTA panels and the footer): glyph + the approved number
         and address, left to right, proportional figures. --}}
    <x-d2.inner.channels class="svc-cta__channels" :inline="true" :items="[
        ['kind' => 'phone', 'href' => 'tel:'.preg_replace('/[^\d+]/', '', $phone), 'text' => $phone],
        ['kind' => 'email', 'href' => 'mailto:'.$email, 'text' => $email],
    ]" />
    @if (! empty($cta['note']))
        <p class="svc-cta__note">{{ Bidi::isolate($cta['note']) }}</p>
    @endif
</section>
