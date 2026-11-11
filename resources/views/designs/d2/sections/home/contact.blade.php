{{--
    Design 2 home section 13: contact (upgrade spec §7.3 item 14, §12 C14). Anchor #consultation-form (the
    Design 1 anchor, A01, the target of every "Request a Legal Consultation" and of the no-JS enquiry
    redirects). Approved heading and intro (P01 contact), then the consultation wizard (8 columns, the homepage
    file's form copy) and the contact card (4 columns, the homepage file's contact details); stacked below
    1280 px. No address and no map.
--}}
@php
    $contact = $c['contact'] ?? [];
@endphp
<section class="section contact-section" id="{{ $sectionId ?? 'consultation-form' }}" aria-labelledby="contact-title" tabindex="-1">
    <div class="container">
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
    </div>
</section>
