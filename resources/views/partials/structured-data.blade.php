@php
    use App\Support\SiteUrl;
    // Visible facts only: name, logo, contact channels, languages. No address, no ratings, no
    // law-firm type (Summit is a legal consultancy).
    $data = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => SiteUrl::canonical('#organization'),
                'name' => ui('brand.name', 'en'),
                'alternateName' => ui('brand.name', 'ar'),
                'url' => SiteUrl::canonical(''),
                'logo' => SiteUrl::canonical('icons/icon-512.png'),
                'email' => setting('email'),
                'telephone' => '+971524666191',
                'contactPoint' => [[
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer support',
                    'telephone' => '+971524666191',
                    'email' => setting('email'),
                    'availableLanguage' => ['ar', 'en', 'ru'],
                    'hoursAvailable' => [
                        '@type' => 'OpeningHoursSpecification',
                        'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                        'opens' => '09:00',
                        'closes' => '18:00',
                    ],
                ]],
            ],
            [
                '@type' => 'WebSite',
                '@id' => SiteUrl::canonical('#website'),
                'url' => SiteUrl::canonical(''),
                'name' => ui('brand.name'),
                'inLanguage' => app()->getLocale(),
                'publisher' => ['@id' => SiteUrl::canonical('#organization')],
            ],
        ],
    ];
@endphp
<script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
