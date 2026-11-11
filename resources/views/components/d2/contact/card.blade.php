@props([
    'contact' => null,       // the page's contact lines {languages, hours}: home = P01 contact; P07 = its details
    'variant' => 'contact',  // whose Word file the card follows: 'home' (01_Homepage section 09) or 'contact' (07_Contact_Us)
    'titleLevel' => 3,       // the heading level of "Contact Details": P07 passes 2 (the Contact file's H2); 3 elsewhere
    'note' => null,          // P07: the approved after-hours paragraph, after the hours line
    'idPrefix' => 'cc',      // id namespace (the styleguide renders a second card)
    'whatsappLabel' => null, // the WhatsApp row's action text (approved copy): default ui('cta.whatsapp'); P07 passes its
                             // own approved "Message Us on WhatsApp" (details.whatsapp_cta)
    'force' => [],           // styleguide only: ['phone' => 'is-hover', 'email' => 'is-focus'] shows a row's state
])
{{--
    Contact card next to the consultation wizard (upgrade spec §7.3 item 14, §12 C14): telephone, WhatsApp
    (the approved per-language draft in setting('whatsapp_url'); it opens WhatsApp and is never sent),
    e-mail, then the page's approved languages line and hours sentence (both Word files list the languages
    first), the hours with the live status filled by modules/hours.js (the sentence itself never depends on
    JavaScript). No address, no map. Each page follows its own Word file (owner decision):
      variant="home"     section 09 of the homepage file: the number, "Contact Us on WhatsApp", the address,
                         "Languages: …", "Business hours: …"; no heading and no channel labels (the file has none)
      variant="contact"  P07's "Contact Details" (H2): Telephone, WhatsApp ("Message Us on WhatsApp"), Email,
                         "Communication Languages: …", "Office Hours: …" and the after-hours paragraph
    Design 2.1 (D40): every icon on one column (the phone, the WhatsApp mark, the e-mail and the clock share the
    first column of one grid; the rows are plain, not boxed); functional icons in ink-2 (brief 3.6 rule 5); the
    WhatsApp mark is <x-d2.ui.brand-mark> (Meta's official files, or the marked placeholder until they are
    supplied: D10); the time difference reads "UAE time is 1 hour behind yours" (modules/hours.js, proposed copy).
--}}
@php
    $home = $variant === 'home';
    $contact ??= content()->page('P01')['content']['contact'] ?? [];
    $details = $home ? [] : (content()->page('P07')['content']['details'] ?? []);
    $titleId = $idPrefix.'-title';
    $level = max(2, min(4, (int) $titleLevel));
    $whatsappLabel = is_string($whatsappLabel) && trim($whatsappLabel) !== '' ? $whatsappLabel : ui('cta.whatsapp');
    $label = fn (string $key): ?string => $home ? null : (filled($details[$key] ?? null) ? (string) $details[$key] : null);

    // Status reserve (shared contract E, as in the footer): an invisible copy of the longest status line
    // modules/hours.js can write, in the same grid cell as the live status, so filling it moves nothing (the card
    // and its section keep their height when the scripts mount). The office-hours module writes that line
    // (App\Design\D2\Hours::sizer(), in the locale's own time style; null while the live status is off). The
    // time difference after it is not reserved: it appears only for visitors outside UAE time, which the server
    // cannot know, and reserving it would leave an empty line for every visitor in the UAE.
    $statusSizer = \App\Design\D2\Hours::sizer();

    // An approved separator stays with the word before it, so no line starts with "·" (P07's approved languages
    // line "Communication Languages: Arabic · English · Russian" wraps at "· русский" in Russian otherwise): a
    // no-break space before the dot, the ordinary space after it. Whitespace only; the words are the approved line.
    $keepSeparators = fn (string $line): string => (string) (preg_replace('/\s+·\s+/u', "\u{00A0}· ", $line) ?? $line);
    $heading = $home ? null : ($details['heading'] ?? null);
@endphp
<div {{ $attributes->class(['contact-card', 'contact-card--'.($home ? 'home' : 'contact')]) }} @if (filled($heading)) role="group" aria-labelledby="{{ $titleId }}" @endif>
    @if (filled($heading))
        <h{{ $level }} class="contact-card__title" id="{{ $titleId }}">{{ $heading }}</h{{ $level }}>
    @endif

    <ul class="contact-card__channels">
        <li>
            <a @class(['contact-card__channel', $force['phone'] ?? null]) href="{{ setting('phone_href') }}">
                <span class="contact-card__icon" aria-hidden="true"><x-d2.ui.icon name="phone" size="20" /></span>
                <span class="contact-card__text">
                    @if ($label('telephone_label'))<span class="contact-card__label">{{ $label('telephone_label') }}</span>@endif
                    <bdi class="contact-card__value" dir="ltr">{{ setting('phone_display') }}</bdi>
                </span>
            </a>
        </li>
        <li>
            <a @class(['contact-card__channel', 'contact-card__channel--whatsapp', $force['whatsapp'] ?? null]) href="{{ setting('whatsapp_url') }}" target="_blank" rel="noopener noreferrer">
                <span class="contact-card__icon" aria-hidden="true"><x-d2.ui.brand-mark size="20" /></span>
                <span class="contact-card__text">
                    @if ($label('whatsapp_label'))<span class="contact-card__label">{{ $label('whatsapp_label') }}</span>@endif
                    <span class="contact-card__value">{{ $whatsappLabel }}</span>
                </span>
                <x-d2.ui.icon name="external" size="20" class="contact-card__external" />
                <span class="sr-only" @proposed>{{ d2p('controls.new_tab') }}</span>
            </a>
        </li>
        <li>
            <a @class(['contact-card__channel', $force['email'] ?? null]) href="{{ setting('email_href') }}">
                <span class="contact-card__icon" aria-hidden="true"><x-d2.ui.icon name="mail" size="20" /></span>
                <span class="contact-card__text">
                    @if ($label('email_label'))<span class="contact-card__label">{{ $label('email_label') }}</span>@endif
                    <bdi class="contact-card__value contact-card__email" dir="ltr">{{ setting('email') }}</bdi>
                </span>
            </a>
        </li>
    </ul>

    <div class="contact-card__facts">
        @if (! empty($contact['languages']))
            <p class="contact-card__fact contact-card__fact--plain">
                <span class="contact-card__fact-text">{{ $keepSeparators((string) $contact['languages']) }}</span>
            </p>
        @endif
        @if (! empty($contact['hours']))
            <p class="contact-card__fact">
                <x-d2.ui.icon name="clock" size="20" class="contact-card__fact-icon" />
                <span class="contact-card__fact-text">
                    <span>{{ \App\Design\D2\Hours::keepTimes((string) $contact['hours']) }}</span>
                    <span class="contact-card__status-slot">
                        <span class="contact-card__status" data-hours-status></span>
                        @if ($statusSizer)
                            <span class="contact-card__status-sizer hours-status" aria-hidden="true" @proposed><span class="hours-status__dot"></span><span class="hours-status__text">{!! $statusSizer !!}</span></span>
                        @endif
                    </span>
                </span>
            </p>
        @endif
        @if (filled($note))
            <p class="contact-card__fact contact-card__fact--plain contact-card__fact--note">
                <span class="contact-card__fact-text">{{ \App\Design\D2\Bidi::isolate((string) $note) }}</span>
            </p>
        @endif
    </div>
</div>
