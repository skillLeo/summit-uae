@props([
    'pageKey' => null,     // the layout's $pageKey ('error' points the language links at each home page)
    'idPrefix' => 'd2',    // id namespace (the styleguide renders a second footer)
])
{{--
    Design 2 footer: the dark band at the end of every page (brief §3.3 text on dark, §3.8 the full lockup, §5.1
    item 11, §5.2; fix list D01, D02, D10, D11, D12, D47; the client's final changes, item 19). In the order of the
    homepage DOCX (01_Homepage, FOOTER):
      1. the brand: the hidden approved H2 (the official gold lockup, transparent, straight on the dark band with no
         tile, shows the name; the copyright line repeats it), the approved description, the contact controls of the
         homepage's section 09 ("Reuse the contact and language controls supplied above": phone, WhatsApp with the
         WA01 draft, e-mail), the website address (item 19: www.summitlegaluae.com, the canonical host, left to
         right) and that file's own hours sentence (owner decision: each place shows the hours sentence of the Word
         file that defines it; the footer is defined by the homepage file);
      2. the three link groups with their approved H3 headings in DOCX wording and case (16/600, white, never mono
         or capitals): Practice Areas (the services, then the approved "View All Practice Areas": the Practice Areas
         page, item 19), Legal Resources (Centre, FAQs, Case Studies: the DOCX order), Useful Links (About, Contact,
         Legal Notice, Privacy Policy, Terms and Conditions);
      3. the approved disclaimer;
      4. "Specialist Resources and Services Associated with Summit" (X01–X06, new tab): the DOCX red note keeps this
         compact group "in the expanded footer, separate from the primary service links"; owner decision: the last
         group inside the footer, directly above the copyright line. A two-column list (one column on phones):
         the name, then the domain in muted text, then the external-link icon (SVG). No boxed cards;
      5. the copyright line with the dynamic year, and the language links (the client's item 18: the flag, then the
         approved name in its own language, English, العربية, Русский; hreflang names the target).
    No address, no map, no live office-hours status (brief §3.7: the live status appears only in the contact block).
    The WhatsApp mark is <x-d2.ui.icon name="whatsapp"> (<x-d2.ui.brand-mark>): on this .on-dark surface Meta's
    official dark-background logo once resources/brand/whatsapp/whatsapp-logo-dark.* is there, the marked placeholder
    (data-placeholder="whatsapp-logo") until then; the components area owns that switch.
    Ids kept: {prefix}-footer-title, -nav (the tab bar's and the header's no-JavaScript menu target), -practice,
    -resources, -useful, -specialist, -languages (the language pill's no-JavaScript target), -languages-label.
    Styles: resources/css/d2/components/footer.css.
--}}
@php
    use App\Design\D2\Hours;
    use App\Design\D2\Shell;
    use App\Support\SiteUrl;

    $pageKey ??= request()->route() === null ? 'error' : null;
    $p = $idPrefix === 'd2' ? 'd2-footer' : $idPrefix.'-footer';
    // The homepage file's own hours line (P01 contact.hours: the footer's Word file); its digits follow the Arabic
    // digits setting at render time (App\Design\D2\Digits).
    $hours = Hours::sentence('footer');
    $year = now()->timezone((string) (content()->settings('hours')['timezone'] ?? config('summit.contact.timezone')))->format('Y');
    // Approved line "© 2026 Summit Legal Consultancy…": the year is rendered dynamically (DOCX red note, as in Design 1).
    $copyright = preg_replace('/\b(19|20)\d{2}\b/', $year, ui('footer.copyright'), 1);
    $languages = collect(Shell::languageLinks($pageKey))->keyBy('code');
    // The client's item 18: English, Arabic, Russian, as in the header's language menu.
    $order = array_values(array_intersect(['en', 'ar', 'ru'], $languages->keys()->all()));
    $newTab = proposed('external.new_tab');
    $channels = Shell::nav('footer_contact');
    // Item 19: the website address, the production host the canonical URLs use (config summit.canonical_host).
    $website = rtrim((string) config('summit.canonical_host'), '/');
    $websiteLabel = (string) preg_replace('#^https?://#', '', $website);

    // Official lockup (resources/brand/lockup.svg, fill="currentColor"): inlined, gold on dark.
    $lockup = trim((string) preg_replace('/<\?xml.*?\?>|<!--.*?-->/s', '', (string) @file_get_contents(resource_path('brand/lockup.svg'))));
    $lockup = (string) preg_replace('/<svg\b/', '<svg class="d2-footer__lockup" aria-hidden="true" focusable="false"', $lockup, 1);
@endphp
<footer {{ $attributes->class(['d2-footer', 'on-dark']) }} aria-labelledby="{{ $p }}-title">
    <div class="container">
        <h2 class="sr-only" id="{{ $p }}-title">{{ ui('footer.brand') }}</h2>

        <div class="d2-footer__top" id="{{ $p }}-nav">
            <div class="d2-footer__col d2-footer__brand">
                <div class="d2-footer__intro">
                    <a class="d2-footer__logo" href="{{ SiteUrl::home() }}" aria-label="{{ ui('brand.logo_alt') }}">{!! $lockup !!}</a>
                    <p class="d2-footer__desc">{{ ui('footer.description') }}</p>
                </div>

                <div class="d2-footer__contact">
                    {{-- The CMS menu "footer_contact": H-L040 TEL, H-L041 WA01, H-L042 EMAIL. --}}
                    <ul class="d2-footer__channels" role="list">
                        @foreach ($channels as $channel)
                            <li>
                                @if ($channel['channel'] === 'WA01')
                                    <a class="d2-footer__channel" href="{{ $channel['href'] }}" target="_blank" rel="noopener noreferrer">
                                        <x-d2.ui.icon name="whatsapp" size="20" class="d2-footer__channel-icon" />
                                        <span class="d2-footer__channel-text">{{ $channel['label'] }}</span>
                                        <span class="sr-only" @proposed>{{ $newTab }}</span>
                                    </a>
                                @elseif ($channel['channel'] === 'EMAIL')
                                    <a class="d2-footer__channel" href="{{ $channel['href'] }}">
                                        <x-d2.ui.icon name="mail" size="20" class="d2-footer__channel-icon" />
                                        <bdi class="d2-footer__channel-text d2-footer__email" dir="ltr">{{ $channel['label'] }}</bdi>
                                    </a>
                                @else
                                    <a class="d2-footer__channel" href="{{ $channel['href'] }}">
                                        <x-d2.ui.icon name="phone" size="20" class="d2-footer__channel-icon" />
                                        <bdi class="d2-footer__channel-text" dir="ltr">{{ $channel['label'] }}</bdi>
                                    </a>
                                @endif
                            </li>
                        @endforeach
                        @if ($websiteLabel !== '')
                            {{-- Item 19: the website address, after the e-mail; the site itself, so the same tab. --}}
                            <li>
                                <a class="d2-footer__channel" href="{{ $website }}/" data-footer-website>
                                    <x-d2.ui.icon name="website" size="20" class="d2-footer__channel-icon" />
                                    <bdi class="d2-footer__channel-text" dir="ltr">{{ $websiteLabel }}</bdi>
                                </a>
                            </li>
                        @endif
                    </ul>
                    @if ($hours !== '')
                        <p class="d2-footer__hours">{{ $hours }}</p>
                    @endif
                </div>
            </div>

            <nav class="d2-footer__col d2-footer__group d2-footer__group--practice" aria-labelledby="{{ $p }}-practice">
                <h3 class="d2-footer__title t-minor" id="{{ $p }}-practice">{{ ui('footer.practice_areas') }}</h3>
                <ul class="d2-footer__links" role="list">
                    @foreach (Shell::nav('footer_practice') as $link)
                        <li><a class="d2-footer__link" href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
                    @endforeach
                    {{-- Item 19: the Practice Areas page itself (the approved "View All Practice Areas"). --}}
                    <li><a class="d2-footer__link d2-footer__link--all" href="{{ SiteUrl::page('P02') }}">{{ ui('cta.view_all_practice_areas') }}</a></li>
                </ul>
            </nav>

            <nav class="d2-footer__col d2-footer__group d2-footer__group--resources" aria-labelledby="{{ $p }}-resources">
                <h3 class="d2-footer__title t-minor" id="{{ $p }}-resources">{{ ui('footer.resources') }}</h3>
                <ul class="d2-footer__links" role="list">
                    @foreach (Shell::nav('footer_resources') as $link)
                        <li><a class="d2-footer__link" href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <nav class="d2-footer__col d2-footer__group d2-footer__group--useful" aria-labelledby="{{ $p }}-useful">
                <h3 class="d2-footer__title t-minor" id="{{ $p }}-useful">{{ ui('footer.useful_links') }}</h3>
                <ul class="d2-footer__links" role="list">
                    @foreach (Shell::nav('footer_useful') as $link)
                        <li><a class="d2-footer__link" href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
                    @endforeach
                    {{-- The approved "Privacy Settings" button (L01 cookies.footer_button): reopens the analytics consent bar
                         (d2::partials.consent); only where the GA4 tag is on the page. --}}
                    @if (\App\Support\Analytics::active())
                        <li><button type="button" class="d2-footer__link consent-open" data-consent-open aria-controls="consent" aria-expanded="false">{{ ui('cookies.footer_button') }}</button></li>
                    @endif
                </ul>
            </nav>
        </div>

        <p class="d2-footer__disclaimer">{{ ui('footer.disclaimer') }}</p>

        {{-- Related specialist resources (X01–X06): their own compact group, never a substitute for the service links. --}}
        <section class="d2-footer__specialist" aria-labelledby="{{ $p }}-specialist">
            <h3 class="d2-footer__title t-minor" id="{{ $p }}-specialist">{{ ui('footer.specialist_heading') }}</h3>
            <ul class="d2-footer__sites" role="list">
                @foreach (Shell::nav('footer_specialist') as $link)
                    @continue($link['record'] === null)
                    @php $site = $link['record']; @endphp
                    <li>
                        <a class="d2-footer__site" href="{{ $link['href'] }}" target="_blank" rel="noopener noreferrer">
                            <span class="d2-footer__site-label">{{ $link['label'] }}</span>
                            <span class="d2-footer__site-meta">
                                <bdi class="d2-footer__site-domain" dir="ltr">{{ $site['domain'] ?? '' }}</bdi>
                                <x-d2.ui.icon name="external" size="20" class="d2-footer__site-icon" />
                            </span>
                            <span class="sr-only" @proposed>{{ $newTab }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="d2-footer__bottom">
            <p class="d2-footer__copyright">{{ $copyright }}</p>
            <div class="d2-footer__langs" id="{{ $p }}-languages" role="group" aria-labelledby="{{ $p }}-languages-label">
                <span class="d2-footer__langs-label" id="{{ $p }}-languages-label">{{ ui('a11y.choose_language') }}</span>
                <ul class="d2-footer__langs-list" role="list">
                    @foreach ($order as $code)
                        @php $link = $languages[$code]; @endphp
                        <li>
                            {{-- Item 18: the flag (decorative), then the approved name in its own language (lang), as in the
                                 header's language menu; hreflang names the target. --}}
                            <a class="d2-footer__lang" href="{{ $link['href'] }}" hreflang="{{ $code }}" lang="{{ $code }}"
                                data-lang-switch="{{ $code }}" @if ($link['current']) aria-current="true" @endif><x-d2.ui.flag :code="$code" /><span class="d2-footer__lang-name" dir="{{ $link['dir'] }}">{{ $link['label'] }}</span></a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</footer>
