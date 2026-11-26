<?php

/*
|--------------------------------------------------------------------------
| Summit Legal Consultancy: site registry
|--------------------------------------------------------------------------
| Stable IDs from 00_Site_Map_*.docx. Paths are identical in every language
| and always end with a slash. Copy never lives here: it comes from the
| imported DOCX content (database/content/*.json).
*/

return [

    'locales' => [
        'en' => ['dir' => 'ltr', 'hreflang' => 'en', 'og' => 'en_GB', 'date' => 'en-GB'],
        'ar' => ['dir' => 'rtl', 'hreflang' => 'ar', 'og' => 'ar_AE', 'date' => 'ar-AE'],
        'ru' => ['dir' => 'ltr', 'hreflang' => 'ru', 'og' => 'ru_RU', 'date' => 'ru-RU'],
    ],

    'default_locale' => 'en',

    // The language menus still write this cookie (resources/js/d2/modules/lang-switch.js); the server no longer reads
    // it: "/" is always a 301 to the default language (client PDF item 29).
    'locale_cookie' => 'summit_lang',

    // Canonical, hreflang and sitemap URLs always use the production www host.
    'canonical_host' => env('SUMMIT_CANONICAL_HOST', 'https://www.summitlegaluae.com'),

    // Staging protection (client PDF item 24): every page and response says noindex and robots.txt names no sitemap.
    // SUMMIT_NOINDEX=true on the test site; without it, APP_ENV=staging is noindex by default. Production: false (or
    // unset with APP_ENV=production). Keep false for Lighthouse runs.
    'noindex' => (bool) env('SUMMIT_NOINDEX', env('APP_ENV') === 'staging'),

    /*
    | Pages. Design 2 renders every one of them in full: each is a current,
    | indexable page with its canonical URL, hreflang links and a sitemap
    | entry (client PDF items 21-23; App\Support\SitePages).
    */
    'pages' => [
        'P01' => ['path' => '', 'route' => 'home'],
        'P02' => ['path' => 'practice-areas/', 'route' => 'practice-areas'],
        'P03' => ['path' => 'resources/', 'route' => 'resources'],
        'P04' => ['path' => 'case-studies/', 'route' => 'case-studies'],
        'P05' => ['path' => 'faq/', 'route' => 'faq'],
        'P06' => ['path' => 'about/', 'route' => 'about'],
        'P07' => ['path' => 'contact/', 'route' => 'contact'],
        'P08' => ['path' => 'legal-disclaimer/', 'route' => 'legal-disclaimer'],
        'L01' => ['path' => 'privacy-policy/', 'route' => 'privacy-policy'],
        'L02' => ['path' => 'terms-and-conditions/', 'route' => 'terms-and-conditions'],
    ],

    // S01–S11 in the approved order; the route of each published practice area. S10 Legal Translation is gone (client
    // PDF item 9, owner decision 6): the service is cancelled, its record is a draft (client layer) and its old address
    // /{locale}/practice-areas/legal-translation/ answers a temporary 302 to Practice Areas (routes/web.php,
    // 'retired_services'). The replacement S12 (Powers of Attorney) is a hidden draft: it gets its address here once
    // its approved content is published.
    'services' => [
        'S01' => 'debt-recovery-cheque-cases',
        'S02' => 'criminal-cases',
        'S03' => 'crypto-digital-currency-cases',
        'S04' => 'corporate-commercial-law',
        'S05' => 'real-estate-rental-disputes',
        'S06' => 'labor-law',
        'S07' => 'tax-disputes-compliance',
        'S08' => 'family-law-wills',
        'S09' => 'compliance-regulatory',
        'S11' => 'business-services',
    ],

    // Practice areas taken off the site: their slug (the record keeps it) and where the old address redirects (a temporary
    // 302, so it can point at the replacement page once that is live; client PDF item 9).
    'retired_services' => [
        'S10' => ['slug' => 'legal-translation', 'to' => 'P02'],
    ],

    // G01–G09 are sections of P03.
    'guides' => [
        'G01' => 'unpaid-salary',
        'G02' => 'commercial-debt',
        'G03' => 'bounced-cheques',
        'G04' => 'property-delay',
        'G05' => 'non-muslim-wills',
        'G06' => 'digital-fraud',
        'G07' => 'compliance-review',
        'G08' => 'legal-translation',
        'G09' => 'tax-records',
    ],

    // Shared anchors (A01–A04) from the site map.
    'anchors' => [
        'A01' => ['page' => 'P01', 'fragment' => 'consultation-form'],
        'A02' => ['page' => 'P06', 'fragment' => 'team'],
        'A03' => ['page' => 'P04', 'fragment' => 'case-01'],
        'A04' => ['page' => 'P04', 'fragment' => 'case-04'],
    ],

    /*
    | P07 release rule (00_Site_Map, 07_Contact_Us): until the standalone Contact page was built and tested, every
    | "Contact Us" control pointed to the homepage form (/xx/#consultation-form). Released 2026-11-25: with the form
    | gone (client PDF item 7) the Contact page is the place for the contact channels, so every "Contact Us" (header,
    | footer, mobile bar, 404, P07 links of the approved copy) opens /xx/contact/ (App\Support\SiteUrl::contact()).
    */
    'contact_page_released' => true,

    /*
    | WhatsApp only (client PDF item 7, owner 2026-11-25): there is no consultation form on the public site. Every
    | "Request a Legal Consultation" and every no-result message opens WhatsApp with the approved message (opened,
    | never sent); the enquiry endpoint answers 404; the admin hides the requests inbox, the request reports, the
    | Forms and Consent and Notifications settings and their scheduled jobs (App\Support\ConsultationForm). The
    | code and the enquiries table are kept (no data dropped): true brings the form and the inbox back.
    */
    'consultation_form' => false,

    'contact' => [
        'whatsapp_number' => '971524666191',
        'timezone' => 'Asia/Dubai',
        // Monday–Friday 09:00–18:00 UAE time (approved hours; used only by the proposed open/closed indicator).
        'hours' => ['days' => [1, 2, 3, 4, 5], 'open' => '09:00', 'close' => '18:00'],
    ],

    'features' => [
        // Attachments stay off until the backend validates, scans and restricts access (Contact DOCX rule 8).
        'attachments_enabled' => (bool) env('SUMMIT_ATTACHMENTS_ENABLED', false),
        // Navigator step 1 (audience) uses a proposed audience-to-service mapping; hidden until approved.
        'navigator_audience_step' => (bool) env('SUMMIT_NAVIGATOR_AUDIENCE_STEP', false),
        // 'proposed' = "Where do you stand?" (needs approval); 'approved' = existing approved heading.
        'navigator_heading' => env('SUMMIT_NAVIGATOR_HEADING', 'proposed'),
        // Proposed "Open now / Closed now · UAE time" indicator.
        'open_now_indicator' => (bool) env('SUMMIT_OPEN_NOW_INDICATOR', true),
        // Language suggestion bar (never redirects).
        'language_suggestion' => (bool) env('SUMMIT_LANGUAGE_SUGGESTION', true),
        // Service worker + offline page. Off locally so a worker never lingers on 127.0.0.1:8000.
        'pwa' => (bool) env('SUMMIT_PWA', env('APP_ENV') === 'production'),
    ],

    /*
    | Proposed audience → service mapping for Navigator step 1. Needs client
    | approval; only used when features.navigator_audience_step is true.
    */
    'navigator_audiences' => [
        'individuals' => ['S01', 'S02', 'S03', 'S05', 'S06', 'S08'],
        'businesses' => ['S01', 'S04', 'S06', 'S07', 'S09', 'S11'],
        'investors' => ['S03', 'S04', 'S05', 'S07', 'S09'],
        'residents' => ['S05', 'S06', 'S08'],
        'entrepreneurs' => ['S04', 'S07', 'S09', 'S11'],
    ],

    'enquiries' => [
        'inbox' => env('SUMMIT_ENQUIRY_INBOX', 'info@summitlegaluae.com'),
        'rate_limit' => ['max' => 5, 'decay_seconds' => 600],
        'client_timeout_ms' => 20000,
        'attachment_max_bytes' => 10_000_000,
        'attachment_mimes' => ['application/pdf', 'image/jpeg', 'image/png'],
    ],

    // Cloudflare Turnstile. Defaults are Cloudflare's public test keys (always pass).
    'turnstile' => [
        'enabled' => (bool) env('TURNSTILE_ENABLED', true),
        'site_key' => env('TURNSTILE_SITE_KEY', '1x00000000000000000000AA'),
        'secret_key' => env('TURNSTILE_SECRET_KEY', '1x0000000000000000000000000000000AA'),
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    ],

];
