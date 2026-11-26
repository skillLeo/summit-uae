{{--
    Design 2 home section 09: contact (upgrade spec §7.3 item 14, §12 C14). Anchor #consultation-form (the site map's
    A01 and the target of "Contact Us" until the Contact page is released). Approved heading and intro (P01 contact).

    WhatsApp only (client PDF items 6 and 7, App\Support\ConsultationForm off): no form. The heading, the intro and
    "Contact Us on WhatsApp" (the approved label; the approved message, opened in a new tab, never sent) on the start
    side, the contact card (the homepage file's contact details: the number, WhatsApp, the address, the languages
    line, the business hours) on the end side from 1024 px; stacked below.

    With the form switched on: the consultation wizard (8 columns, the homepage file's form copy) and the contact card
    (4 columns); stacked below 1280 px. No address and no map.
--}}
@php
    $contact = $c['contact'] ?? [];
    $form = \App\Support\ConsultationForm::enabled();
@endphp
<section @class(['section', 'contact-section', 'contact-section--whatsapp' => ! $form]) id="{{ $sectionId ?? 'consultation-form' }}" aria-labelledby="contact-title" tabindex="-1">
    <div class="container">
        @if ($form)
            <div class="section__head">
                <h2 id="contact-title">{{ $contact['heading'] ?? '' }}</h2>
                @if (! empty($contact['intro']))
                    <p>{{ $contact['intro'] }}</p>
                @endif
            </div>

            <div class="contact-section__grid">
                <div class="contact-section__main">
                    {{-- The homepage file's own form copy (copy="home"); the section's H2 names the form. --}}
                    <x-d2.wizard.form :idempotency-key="$idempotencyKey ?? (string) \Illuminate\Support\Str::uuid()" :nav-services="$navServices ?? null"
                        copy="home" labelledby="contact-title" :title="$contact['heading'] ?? null" />
                </div>
                <div class="contact-section__aside">
                    {{-- Section 09's contact details as the homepage file gives them: the number, "Contact Us on WhatsApp",
                         the address, the languages line, the business hours (no heading, no labels). --}}
                    <x-d2.contact.card :contact="$contact" variant="home" />
                    {{-- Calculators area (brief §5.1): the link to the calculators page, only while pending copy is shown. --}}
                    <x-d2.tool.link />
                </div>
            </div>
        @else
            <div class="contact-section__grid">
                <div class="contact-section__main">
                    <div class="section__head contact-section__head">
                        <h2 id="contact-title">{{ $contact['heading'] ?? '' }}</h2>
                        @if (! empty($contact['intro']))
                            <p>{{ $contact['intro'] }}</p>
                        @endif
                    </div>
                    <div class="contact-section__actions">
                        <x-d2.ui.button variant="whatsapp" size="52" :href="setting('whatsapp_url')">{{ ui('cta.whatsapp') }}</x-d2.ui.button>
                    </div>
                </div>
                <div class="contact-section__aside">
                    <x-d2.contact.card :contact="$contact" variant="home" />
                    {{-- Calculators area (brief §5.1): the link to the calculators page, only while pending copy is shown. --}}
                    <x-d2.tool.link />
                </div>
            </div>
        @endif
    </div>
</section>
