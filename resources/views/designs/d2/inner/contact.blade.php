{{--
    Design 2 Contact page (P07; upgrade spec §7.4 "Contact: full wizard", §7.3 item 14, §12 C14 + Phase D).
    Rendered by PagePreviewController ($h1, $crumbs). Until config('summit.contact_page_released') is true the
    controller keeps this page noindex and every other page keeps linking to the home form; opened directly
    it works completely (same wizard, same endpoint). Approved P07 content, in the Contact DOCX order
    (Design 2.1 §5.3; the DOCX headings and levels):

      head               breadcrumb, H1, the approved lead and both paragraphs, "Send a Consultation Request"
                         (#consultation-form, this page) and "Contact Us on WhatsApp" (approved per-language
                         draft: WhatsApp opens, nothing is sent). Aside: "Contact Details" (H2), the contact card
                         in the Contact file's version (Telephone, WhatsApp "Message Us on WhatsApp", Email, the
                         languages line, the office hours with the live status, the after-hours paragraph).
      #contact-after     "What Happens After You Contact Us" (H2) and its three approved steps (H3; the approved
                         numbering starts each step's own heading, in its role and colour), side by side.
      #consultation-form "Consultation Request Details" (H2), its intro and the required-fields note, then the
                         consultation wizard (<x-d2.wizard.form copy="contact">: the Contact file's labels, help,
                         notices and states; same backend, field names, idempotency key and prefill as on the
                         home; ?consultant=… from a team card is applied here) beside "What Helps Us Understand
                         Your Matter" (H2: five items + note; sticky from 1280 px). No address, no map.
      #contact-questions "Questions Before Contacting Us": the five approved questions (accordion) and the
                         approved links (All FAQs, team, services) under the approved "Useful Links" label, in the
                         look of the FAQ browser's "Useful Links" (P05): a band panel with underlined text links.
      #contact-final     "Contact Us in the Way That Suits You": text, WhatsApp, "Call Us", "Email Us", the
                         approved scope sentence and the Legal Notice link.
    Without JavaScript the wizard is one plain form posting to /{locale}/enquiries (its 303 lands on the home
    form until the page is released), and telephone, WhatsApp and e-mail stay visible above it.
    Styles: resources/css/d2/pages/contact.css (+ the shared pp-* blocks in pages/about.css).
    Design 2.1: the hero's "Send a Consultation Request" is the page's one gold action (the hero primary, brief
    §3.5), no arrows, no decorative glyphs or timeline dots, plain bullets. Arabic digits (the approved hours line
    ٩ / ٦, the step numbers ١. …) follow the site setting at render time (App\Design\D2\Digits, brief §3.7,
    D11), so the page never mixes the two digit sets.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;
    use Illuminate\Support\Str;

    $c = content()->page('P07')['content'] ?? [];
    $texts = fn ($value) => array_values(array_filter((array) $value, fn ($p) => is_string($p) && trim($p) !== ''));
    $lead = is_string($c['lead'] ?? null) ? trim($c['lead']) : '';
    $details = $c['details'] ?? [];
    $after = $c['after'] ?? [];
    $afterSteps = array_values(array_filter((array) ($after['steps'] ?? []), fn ($s) => is_array($s) && trim((string) ($s['title'] ?? '')) !== ''));
    $helps = $c['helps'] ?? [];
    $faq = $c['faq'] ?? [];
    $faqItems = array_values(array_filter((array) ($faq['items'] ?? []), fn ($i) => is_array($i) && trim((string) ($i['question'] ?? '')) !== ''));
    $links = array_values(array_filter((array) ($c['links'] ?? []), fn ($l) => is_array($l) && trim((string) ($l['label'] ?? '')) !== ''));
    $final = $c['final'] ?? [];
    $splitStep = function (string $title): array {
        if (preg_match('/^\s*([0-9٠-٩۰-۹]+[.)]?)\s+(.+)$/u', $title, $m) === 1) {
            return [$m[1], $m[2]];
        }

        return [null, $title];
    };
    $heroTarget = (string) ($c['hero_cta_target'] ?? 'consultation-form');
    $heroHref = preg_match('/^[a-z][a-z0-9-]*$/', $heroTarget) === 1 && ! preg_match('/^(P0\d|S\d{2}|G\d{2})$/', $heroTarget) ? '#'.$heroTarget : SiteUrl::target($heroTarget);
@endphp

<x-d2.inner.head :title="$h1" :crumbs="$crumbs" :intro="$texts($c['paragraphs'] ?? [])" class="pp-head contact-head">
    @if ($lead !== '')
        <x-slot:meta><p class="pp-lead">{{ Bidi::isolate($lead) }}</p></x-slot:meta>
    @endif
    <x-slot:actions>
        @if (! empty($c['hero_cta_label']))
            <x-d2.ui.button variant="gold" size="52" :href="$heroHref">{{ $c['hero_cta_label'] }}</x-d2.ui.button>
        @endif
        @if (! empty($c['hero_whatsapp_label']))
            <x-d2.ui.button variant="whatsapp" size="52" :href="setting('whatsapp_url')">{{ $c['hero_whatsapp_label'] }}</x-d2.ui.button>
        @endif
    </x-slot:actions>
    <x-slot:aside>
        {{-- "Contact Details" (the Contact file's H2), right after the hero as in the DOCX. --}}
        <x-d2.contact.card class="contact-head__card" variant="contact" :title-level="2" id-prefix="contact-details"
            :contact="['hours' => (string) ($details['hours'] ?? ''), 'languages' => (string) ($details['languages'] ?? '')]"
            :whatsapp-label="$details['whatsapp_cta'] ?? null" :note="$details['after_hours'] ?? null" />
    </x-slot:aside>
</x-d2.inner.head>

@if ($afterSteps !== [])
    <section class="section section--surface contact-after" id="contact-after" aria-labelledby="contact-after-title">
        <div class="container">
            <div class="section__head">
                <h2 id="contact-after-title">{{ Bidi::isolate($after['heading'] ?? '') }}</h2>
            </div>
            <ol class="pp-steps pp-steps--row contact-after__steps" role="list" aria-labelledby="contact-after-title">
                @foreach ($afterSteps as $step)
                    @php [$number, $title] = $splitStep((string) $step['title']); @endphp
                    <li class="pp-step">
                        <div class="pp-step__card">
                            <h3 class="pp-step__title">@if ($number)<span class="pp-step__num">{{ $number }}</span> @endif<span class="pp-step__name">{{ Bidi::isolate($title) }}</span></h3>
                            <p class="pp-step__text">{{ Bidi::isolate((string) ($step['text'] ?? '')) }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif

{{-- The form section keeps the Design 1 anchor (#consultation-form: the hero CTA, prefill() and, once the page
     is released, every "Request a Legal Consultation" and the no-JS enquiry redirect land here). Its heading, intro
     and required-fields note are the Contact file's (the home's section 09 prints its own). --}}
@php $formHeading = (string) ($c['form_section']['heading'] ?? ui('form.heading')); @endphp
<section class="section contact-form" id="consultation-form" aria-labelledby="consultation-title" tabindex="-1">
    <div class="container">
        <div class="section__head contact-form__head">
            <h2 id="consultation-title">{{ $formHeading }}</h2>
            <p>{{ ui('form.intro') }}</p>
            <p class="contact-form__required">{{ ui('form.required_note') }}</p>
        </div>
        <div class="contact-form__grid">
            <div class="contact-form__main">
                <x-d2.wizard.form :idempotency-key="(string) Str::uuid()" copy="contact" labelledby="consultation-title" :title="$formHeading" />
            </div>
            @if (! empty($helps['heading']))
                <aside class="contact-form__aside contact-helps" aria-labelledby="contact-helps-title">
                    <h2 class="contact-helps__title" id="contact-helps-title">{{ Bidi::isolate($helps['heading']) }}</h2>
                    <ul class="pp-checklist" role="list" aria-labelledby="contact-helps-title">
                        @foreach ($texts($helps['items'] ?? []) as $item)
                            <li class="pp-checklist__item">
                                <span class="pp-checklist__mark" aria-hidden="true"></span>
                                <span>{{ Bidi::isolate($item) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if (! empty($helps['note']))
                        <p class="contact-helps__note">{{ Bidi::isolate($helps['note']) }}</p>
                    @endif
                </aside>
            @endif
        </div>
    </div>
</section>

@if ($faqItems !== [])
    <section class="section section--surface contact-questions" id="contact-questions" aria-labelledby="contact-questions-title">
        <div class="container">
            <h2 class="contact-questions__title" id="contact-questions-title">{{ Bidi::isolate($faq['heading'] ?? '') }}</h2>
            <div class="contact-questions__grid">
                <div class="contact-questions__main">
                    <x-d2.ui.accordion level="3" class="contact-questions__list">
                        @foreach ($faqItems as $i => $item)
                            <x-d2.ui.accordion-item :id="'contact-faq-'.($i + 1)" :title="Bidi::isolate((string) $item['question'])">
                                @foreach ($texts($item['answer'] ?? []) as $paragraph)
                                    <p>{{ Bidi::isolate($paragraph) }}</p>
                                @endforeach
                            </x-d2.ui.accordion-item>
                        @endforeach
                    </x-d2.ui.accordion>
                </div>
                @if ($links !== [])
                    {{-- "Useful Links" beside questions: the treatment of the FAQ browser's "Useful Links" on P05 and the home
                         (sections/faq.css: the band panel, the Minor heading, underlined text links), one look per role. --}}
                    <aside class="contact-questions__aside" aria-labelledby="contact-links-title">
                        <h3 class="faq-aside__title contact-questions__aside-title" id="contact-links-title">{{ ui('footer.useful_links') }}</h3>
                        <ul class="faq-links" role="list">
                            @foreach ($links as $link)
                                @php $target = (string) ($link['target'] ?? ''); @endphp
                                <li>
                                    <a class="faq-link" href="{{ SiteUrl::target($target) }}">
                                        <span class="faq-link__label">{{ Bidi::isolate($link['label']) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </aside>
                @endif
            </div>
        </div>
    </section>
@endif

@if (! empty($final['heading']))
    <section class="section pp-band" id="contact-final" aria-labelledby="contact-final-title">
        <div class="container">
            <div class="pp-cta">
                <div class="pp-cta__text">
                    <h2 class="pp-cta__title" id="contact-final-title">{{ Bidi::isolate($final['heading']) }}</h2>
                    @if (! empty($final['text']))
                        <p>{{ Bidi::isolate($final['text']) }}</p>
                    @endif
                </div>
                <div class="pp-cta__actions">
                    @if (! empty($final['whatsapp_label']))
                        <div class="pp-cta__buttons">
                            <x-d2.ui.button variant="whatsapp" size="48" :full="true" :href="setting('whatsapp_url')">{{ $final['whatsapp_label'] }}</x-d2.ui.button>
                        </div>
                    @endif
                    {{-- "Call Us" and "Email Us" (this page's approved labels) as the kit's contact channels: the glyph, then the label. --}}
                    <x-d2.inner.channels class="pp-cta__channels" :items="[
                        ['kind' => 'phone', 'href' => (string) setting('phone_href'), 'text' => (string) ($final['call_label'] ?? '')],
                        ['kind' => 'email', 'href' => (string) setting('email_href'), 'text' => (string) ($final['email_label'] ?? '')],
                    ]" />
                </div>
                {{-- The approved scope sentence and its Legal Notice link close the panel, after WhatsApp, "Call Us" and
                     "Email Us", as the Contact DOCX orders them (brief 5.3); full width under both columns. --}}
                @if (! empty($final['scope']))
                    <p class="pp-cta__scope pp-cta__scope--end">{{ Bidi::isolate($final['scope']) }}
                        @if (! empty($final['legal_link_label']))
                            <a class="t-link" href="{{ SiteUrl::target((string) ($final['legal_link_target'] ?? 'P08')) }}">{{ Bidi::isolate($final['legal_link_label']) }}</a>
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </section>
@endif
