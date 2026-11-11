@props([
    'heading' => null,         // approved heading of the page's CTA block (e.g. a service's sections.cta.heading); null: none
    'text' => null,            // approved paragraph(s): a string or a list
    'note' => null,            // approved small print under the actions (e.g. sections.cta.note)
    'headingLevel' => 2,       // 2–4; 0: the title is a label (<p>), not a heading (the page already has this heading as its own H2)
    'service' => null,         // S01–S11: the consultation link pre-fills the wizard (Prefill::url)
    'situation' => null,       // S06-2 …
    'audience' => null,        // individual | business | investor
    'consultant' => null,      // team-ahmed …
    'tool' => null,            // gratuity | rent
    'requestLabel' => null,    // approved request label (default ui('cta.request_consultation'))
    'whatsapp' => true,        // WhatsApp button (approved per-language draft; opens WhatsApp, never sends)
    'whatsappLabel' => null,   // approved label (default ui('cta.whatsapp'))
    'whatsappDraft' => null,   // approved page-specific draft (e.g. a service's whatsapp_draft); default setting('whatsapp_url')
    'phone' => true,           // telephone row (tel:+971524666191)
    'email' => false,          // e-mail row (mailto:info@summitlegaluae.com)
    'hours' => true,           // the approved office-hours sentence + live status ([data-hours-status], modules/hours.js)
    'sticky' => true,          // sticks with the side column (≥ 1280 px) when placed in the layout's aside slot
    'id' => 'cta-card',
])
{{--
    Design 2 side CTA card (upgrade spec §7.4 service page "sticky side CTA card on desktop", §12 Phase D).

    <x-d2.inner.layout> <x-slot:aside>
        <x-d2.inner.cta-card :heading="$cta['heading']" :text="$cta['text']" :note="$cta['note']" service="S06"
            :request-label="$cta['request_label']" :whatsapp-label="$cta['whatsapp_label']" :whatsapp-draft="$record['whatsapp_draft']" />
    </x-slot:aside> </x-d2.inner.layout>

    White card (1 px --line, --radius-card, no shadow; under the contents in the service pages' rail, an action
    group under a hairline instead: pages/inner.css): optional approved heading + text, the request button
    (ink, full width: gold is the page hero's primary only, brief §3.5), the WhatsApp button (secondary +
    glyph, new tab, the approved draft is opened, never sent), the telephone / e-mail as the kit's contact
    channels (<x-d2.inner.channels>: glyph + number or address in <bdi dir="ltr">, the rows of every CTA panel
    and the footer), the approved hours sentence (Arabic digits follow the site setting at render time,
    App\Design\D2\Digits) with the live open/closed status, and the approved note. No address, no map, no
    response-time promise.
    The request link uses Prefill::url(), so it follows the contact-page release rule (home form until
    P07 is released) and carries only validated codes; the visitor's own words never go into a URL.
    Rendered as <aside aria-labelledby> when it has a heading, else a plain group. heading-level 0 (the service
    pages' rail card, which repeats the final panel's approved H2 "Tell Us About Your Matter"): the title is a
    plain label that still names the aside, so heading navigation lists that section once (brief §5.2).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Prefill;

    $details = content()->page('P07')['content']['details'] ?? [];
    $headingLevel = (int) $headingLevel === 0 ? 0 : max(2, min(4, (int) $headingLevel));
    $titleTag = $headingLevel === 0 ? 'p' : 'h'.$headingLevel;
    $paragraphs = array_values(array_filter(is_array($text) ? $text : [$text], fn ($p) => is_string($p) && trim($p) !== ''));
    $notes = array_values(array_filter(is_array($note) ? $note : [$note], fn ($p) => is_string($p) && trim($p) !== ''));
    $hasHeading = is_string($heading) && trim($heading) !== '';
    $requestUrl = Prefill::url(array_filter([
        'service' => is_string($service) && array_key_exists($service, config('summit.services')) ? $service : null,
        'situation' => $situation, 'audience' => $audience, 'consultant' => $consultant, 'tool' => $tool,
    ], fn ($v) => is_string($v) && $v !== ''));
    $whatsappUrl = is_string($whatsappDraft) && trim($whatsappDraft) !== ''
        ? 'https://wa.me/'.setting('whatsapp_number').'?text='.rawurlencode($whatsappDraft)
        : (string) setting('whatsapp_url');
    $titleId = $id.'-title';
    $tag = $hasHeading ? 'aside' : 'div';
@endphp
<{{ $tag }} {{ $attributes->class(['cta-card', 'cta-card--sticky' => $sticky, 'on-light']) }} id="{{ $id }}"
    @if ($hasHeading) aria-labelledby="{{ $titleId }}" @endif>
    @if ($hasHeading)
        <{{ $titleTag }} class="cta-card__title" id="{{ $titleId }}">{{ Bidi::isolate($heading) }}</{{ $titleTag }}>
    @endif
    @foreach ($paragraphs as $paragraph)
        <p class="cta-card__text">{{ Bidi::isolate($paragraph) }}</p>
    @endforeach

    <div class="cta-card__actions">
        <x-d2.ui.button variant="ink" size="48" :href="$requestUrl" full>
            {{ is_string($requestLabel) && $requestLabel !== '' ? $requestLabel : ui('cta.request_consultation') }}
        </x-d2.ui.button>
        @if ($whatsapp)
            <x-d2.ui.button variant="whatsapp" size="48" :href="$whatsappUrl" full external class="cta-card__whatsapp">
                {{ is_string($whatsappLabel) && $whatsappLabel !== '' ? $whatsappLabel : ui('cta.whatsapp') }}
            </x-d2.ui.button>
        @endif
    </div>

    @if ($phone || $email)
        {{-- The kit's contact channels (one pattern with the CTA panels and the footer): glyph + the number or address. --}}
        <x-d2.inner.channels class="cta-card__channels" :items="array_values(array_filter([
            $phone ? ['kind' => 'phone', 'href' => (string) setting('phone_href'), 'text' => (string) setting('phone_display')] : null,
            $email ? ['kind' => 'email', 'href' => (string) setting('email_href'), 'text' => (string) setting('email')] : null,
        ]))" />
    @endif

    @if ($hours && ! empty($details['hours']))
        <p class="cta-card__hours">
            <x-d2.ui.icon name="clock" size="20" class="cta-card__icon" />
            <span class="cta-card__hours-text">
                <span>{{ \App\Design\D2\Hours::keepTimes((string) $details['hours']) }}</span>
                <span class="cta-card__status" data-hours-status></span>
            </span>
        </p>
    @endif

    @foreach ($notes as $line)
        <p class="cta-card__note">{{ Bidi::isolate($line) }}</p>
    @endforeach
</{{ $tag }}>
