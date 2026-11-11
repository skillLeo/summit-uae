<?php

/*
|--------------------------------------------------------------------------
| Switchable designs (docs/upgrade-plan.md)
|--------------------------------------------------------------------------
| Design 1 ("The File") is frozen at the git tag design-1. Design 2 lives in
| separate files (resources/views/designs/d2, resources/css/d2, resources/js/d2)
| and is built by vite.d2.config.js into public/build-d2.
*/

return [

    // The design visitors see until they choose one with ?design=1 / ?design=2.
    'default' => (int) env('SUMMIT_DEFAULT_DESIGN', 1),

    // Floating "Design 1 | Design 2" switch (Alt+1 / Alt+2). Internal demo tool: keep it off in production.
    'switcher' => (bool) env('SUMMIT_DESIGN_SWITCHER', false),

    'cookie' => 'summit_design',

    'cookie_minutes' => 60 * 24 * 365,

    'session_key' => 'summit.design',

    // Requests carrying this cookie (=1) or header never get the switcher injected (QA screenshots, Lighthouse).
    'qa_cookie' => 'summit_qa',
    'qa_header' => 'X-Summit-QA',

    'd2' => [
        'views' => resource_path('views/designs/d2'),
        // qa-harness: environment overrides (defaults unchanged) so a production QA build can be served on
        // its own port without the dev server: scripts/qa/lighthouse-d2.mjs runs php -S with
        // SUMMIT_D2_BUILD_DIR=build-d2-qa and SUMMIT_D2_HOT_FILE pointing at a file that does not exist.
        'build_directory' => env('SUMMIT_D2_BUILD_DIR', 'build-d2'),
        'hot_file' => env('SUMMIT_D2_HOT_FILE', 'hot-d2'),

        // Design 2.1 brief §3.7 (settings arrive in a later phase; these are the defaults until then).
        // Digits on Arabic pages: 'western' (0-9, owner decision) or 'eastern' (٠-٩), applied at render time to every
        // Design 2 Arabic page and to the Arabic blocks (lang="ar") of the English and Russian ones
        // (App\Design\D2\Digits), so Arabic text never mixes the two. A question for the client.
        'arabic_digits' => env('SUMMIT_ARABIC_DIGITS', 'western'),
        // The proposed live office-hours status next to the approved hours sentence, in the contact block only
        // (App\Design\D2\Hours, resources/js/d2/modules/hours.js). Design 1 keeps its own switch,
        // summit.features.open_now_indicator (SUMMIT_OPEN_NOW_INDICATOR), which was Design 2's switch before 2.1:
        // while SUMMIT_LIVE_STATUS is unset, that older flag decides, so an environment that turned the status off
        // with it keeps it off. Both are documented in docs/d2/running.md §6 and .env.example.
        'live_status' => (bool) env('SUMMIT_LIVE_STATUS', env('SUMMIT_OPEN_NOW_INDICATOR', true)),

        // Brief §8.7 "Show pending copy on the public site": on in local and staging, off in production. When off,
        // pending blocks and fields are hidden (App\Design\D2\Pending). null = on everywhere except production.
        // Phase 3 moves this to the CMS settings.
        'show_pending' => env('SUMMIT_SHOW_PENDING') === null ? null : (bool) env('SUMMIT_SHOW_PENDING'),

        // Team portraits (Design 2.1 D42, D43), keyed by the member code of content team_members. The admin sets these
        // per photo in Phase 4; until then they are measured on the files in public/images/d2/team/ (480 × 600).
        //   eye_line       where the eyes sit in every portrait frame, in % of its height from the top. Every portrait
        //                  is drawn 4:5, and each photo is placed so that its focal point lands on this line,
        //                  centred across (App\Design\D2\Portraits), so all faces share one eye line.
        //   focus          [x, y] in % of the photo: the midpoint between the pupils (the focal point; also the photo's
        //                  object-position).
        //   photo_pending  true while the member has no portrait: a neutral 4:5 panel with the initials of the
        //                  approved name in the sans is drawn instead (Ms Sarah Zaki, client question 5). A member
        //                  whose photo files are missing gets the same panel.
        'team' => [
            'eye_line' => 35,
            'portraits' => [
                'team-ahmed' => ['focus' => [49.7, 33.2], 'photo_pending' => false],
                'team-mohamed' => ['focus' => [49.5, 34.3], 'photo_pending' => false],
                'team-sarah' => ['focus' => null, 'photo_pending' => true],
            ],
        ],

        // The home page (brief §5.1). The approved sections 01-09 are fixed and always in the DOCX order; these
        // blocks are switchable and sit between them, never inside one (Phase 4 moves the switches to the admin):
        //   start      "Start your matter", directly under the hero (pending copy)
        //   anatomy    "Anatomy of a Document", after 02 Our Legal Services (pending copy)
        //   navigator  "Where Do You Stand?", after 04 What a Review of Your Matter Can Clarify (pending copy)
        //   team       the "Our Team" grid, after 05 About; off by default (D24: the team stays on About unless
        //              the client approves it on the home, client question 7)
        'home' => [
            'blocks' => [
                'start' => ['enabled' => true, 'pending' => true],
                'anatomy' => ['enabled' => true, 'pending' => true],
                'navigator' => ['enabled' => true, 'pending' => true],
                // SUMMIT_HOME_TEAM=1 switches the team block on for one environment (QA captures) until Phase 4.
                'team' => ['enabled' => (bool) env('SUMMIT_HOME_TEAM', false), 'pending' => false],
            ],
            // "Start your matter" quick picks (D19): the three most-chosen situations, shown as chips before the
            // visitor chooses one (then the card lists that service's documents). Navigator situation codes
            // (service code + the 1-based index of the situation on its P02 card, App\Content\NavigatorData::parse);
            // unknown codes are ignored, at most three are shown, in this order. Defaults until real figures exist:
            // a returned cheque, a landlord or tenant demand, final employment entitlements. The admin sets them in
            // Phase 4.
            'quick_picks' => ['S01-2', 'S05-3', 'S06-2'],
        ],
    ],

];
