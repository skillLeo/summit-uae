@php
    use App\Support\SiteUrl;
    $year = now()->timezone(config('summit.contact.timezone'))->format('Y');
    // Approved line "© 2026 Summit Legal Consultancy…": the year is rendered dynamically.
    $copyright = preg_replace('/\b(19|20)\d{2}\b/', $year, ui('footer.copyright'), 1);
@endphp
<footer class="site-footer on-dark">
    <div class="container">
        <div class="site-footer__top">
            <div class="site-footer__brand">
                <a class="brand" href="{{ SiteUrl::home() }}">
                    <x-brand name="lockup" class="brand__lockup" />
                    <span class="sr-only">{{ ui('brand.logo_alt') }}</span>
                </a>
                {{-- The footer heading is the approved brand name; the lockup above shows it as the logo. --}}
                <h2 class="sr-only">{{ ui('footer.brand') }}</h2>
                <p>{{ ui('footer.description') }}</p>
            </div>

            <div class="site-footer__groups">
                <nav aria-labelledby="footer-practice">
                    <h3 class="footer-group__title" id="footer-practice">{{ ui('footer.practice_areas') }}</h3>
                    <ul class="footer-links footer-links--services">
                        @foreach (content()->services() as $service)
                            <li><a href="{{ SiteUrl::service($service['code']) }}">{{ $service['title'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
                <nav aria-labelledby="footer-resources">
                    <h3 class="footer-group__title" id="footer-resources">{{ ui('footer.resources') }}</h3>
                    <ul class="footer-links">
                        <li><a href="{{ SiteUrl::page('P03') }}">{{ ui('footer.resources_centre') }}</a></li>
                        <li><a href="{{ SiteUrl::page('P05') }}">{{ ui('footer.faq') }}</a></li>
                        <li><a href="{{ SiteUrl::page('P04') }}">{{ ui('footer.case_studies') }}</a></li>
                    </ul>
                </nav>
                <nav aria-labelledby="footer-useful">
                    <h3 class="footer-group__title" id="footer-useful">{{ ui('footer.useful_links') }}</h3>
                    <ul class="footer-links">
                        <li><a href="{{ SiteUrl::page('P06') }}">{{ ui('footer.about') }}</a></li>
                        <li><a href="{{ SiteUrl::contact() }}">{{ ui('footer.contact') }}</a></li>
                        <li><a href="{{ SiteUrl::page('P08') }}">{{ ui('footer.legal_notice') }}</a></li>
                        <li><a href="{{ SiteUrl::page('L01') }}">{{ ui('footer.privacy') }}</a></li>
                        <li><a href="{{ SiteUrl::page('L02') }}">{{ ui('footer.terms') }}</a></li>
                    </ul>
                </nav>
            </div>
        </div>

        {{-- Related specialist resources (X01–X06): a separate group, not substitute service links. --}}
        <section class="site-footer__specialist" aria-labelledby="footer-specialist">
            <h3 class="footer-group__title" id="footer-specialist">{{ ui('footer.specialist_heading') }}</h3>
            <ul class="specialist-list">
                @foreach (content()->specialistSites() as $site)
                    <li>
                        <a href="{{ $site['url'] }}" target="_blank" rel="noopener noreferrer">
                            <span class="specialist-list__label">{{ $site['label'] }}</span>
                            <bdi class="specialist-list__domain" dir="ltr">{{ $site['domain'] }}</bdi>
                            <x-icon name="external" />
                            <span class="sr-only">{{ proposed('external.new_tab') }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="site-footer__bottom">
            <p>{{ ui('footer.disclaimer') }}</p>
            <div class="site-footer__meta">
                <a class="text-link" href="{{ setting('whatsapp_url') }}" target="_blank" rel="noopener noreferrer">
                    <x-icon name="whatsapp" />
                    <span>{{ ui('footer.whatsapp') }}</span>
                    <span class="sr-only">{{ proposed('external.new_tab') }}</span>
                </a>
                <p>{{ $copyright }}</p>
            </div>
        </div>
    </div>
</footer>
