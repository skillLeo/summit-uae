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

    // Remembered language choice for the neutral root "/" (302 only when the cookie exists).
    'locale_cookie' => 'summit_lang',

    // Canonical, hreflang and sitemap URLs always use the production www host.
    'canonical_host' => env('SUMMIT_CANONICAL_HOST', 'https://www.summitlegaluae.com'),

    // Staging protection. Keep false for Lighthouse runs and production.
    'noindex' => (bool) env('SUMMIT_NOINDEX', false),

    /*
    | Pages. 'built' marks templates delivered in the current phase; other
    | registered routes render an honest, noindex preview of their approved
    | H1 and introduction until their template is built (Phase 2).
    */
    'pages' => [
        'P01' => ['path' => '', 'route' => 'home', 'built' => true],
        'P02' => ['path' => 'practice-areas/', 'route' => 'practice-areas', 'built' => false],
        'P03' => ['path' => 'resources/', 'route' => 'resources', 'built' => false],
        'P04' => ['path' => 'case-studies/', 'route' => 'case-studies', 'built' => false],
        'P05' => ['path' => 'faq/', 'route' => 'faq', 'built' => false],
        'P06' => ['path' => 'about/', 'route' => 'about', 'built' => false],
        'P07' => ['path' => 'contact/', 'route' => 'contact', 'built' => false],
        'P08' => ['path' => 'legal-disclaimer/', 'route' => 'legal-disclaimer', 'built' => false],
        'L01' => ['path' => 'privacy-policy/', 'route' => 'privacy-policy', 'built' => false],
        'L02' => ['path' => 'terms-and-conditions/', 'route' => 'terms-and-conditions', 'built' => false],
    ],

    // S01–S11 in the approved order.
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
        'S10' => 'legal-translation',
        'S11' => 'business-services',
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
    | P07 release rule (00_Site_Map, 07_Contact_Us): until the standalone
    | Contact page is built and tested, every "Contact Us" control points to
    | the homepage form (/xx/#consultation-form).
    */
    'contact_page_released' => false,

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
        'individuals' => ['S01', 'S02', 'S03', 'S05', 'S06', 'S08', 'S10'],
        'businesses' => ['S01', 'S04', 'S06', 'S07', 'S09', 'S10', 'S11'],
        'investors' => ['S03', 'S04', 'S05', 'S07', 'S09'],
        'residents' => ['S05', 'S06', 'S08', 'S10'],
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
