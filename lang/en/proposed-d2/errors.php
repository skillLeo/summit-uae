<?php

/*
| Design 2 404 page (owner: system-pages; d2::errors.404, upgrade spec §7.4 and §12 Phase D): PROPOSED
| copy, not in the approved DOCX files. Read as d2p('errors.…'), shown with @proposed, listed in
| docs/proposed-copy.md for approval. The code, heading, message, both actions, the "Looking for specific
| information?" heading and its three links are the approved U01 copy (content()->page('U01')); the search
| field has its own short placeholder (search_placeholder below).
*/

return [
    // Without JavaScript the search field (the command palette) is replaced by the main pages: their name.
    'pages_title' => 'Main pages',

    // Placeholder of the 404 search field (it opens the command palette with the typed words). Shorter than the
    // header's d2p('shell.search_placeholder'), so it shows in full beside the search button on a 360 px phone in
    // every language (Design 2.1, brief §3.6 rule 8: no visible text is cut).
    'search_placeholder' => 'Search the site',

    // The arrow button at the end of the 404 search field (its accessible name and tooltip): opens the command
    // palette with the words typed in the field.
    'search_go' => 'Search',

    // Accessible name of the "misfiled" sample document illustration (role="img").
    'art_alt' => 'Illustration: a sample document filed in the wrong folder',

    /*
    | Design 2 pages 403, 419, 429, 500 and 503 (owner: error-pages; d2::errors.{status} → d2::errors.status).
    | Each status: the H1 (also the page title, "{H1} | {brand}") and the message under it. The actions reuse
    | the approved U01 "Back to Home" and the proposed d2p('offline.retry') "Try again"; the 500 and 503
    | contact details are the approved P07 heading and labels with the approved phone and e-mail.
    */
    // 403: no access (abort(403), a failed authorisation, a cross-origin form post).
    'title_403' => 'Access Restricted',
    'text_403' => 'You do not have permission to view this page. You can return to the homepage and continue from there.',

    // 419: the form token or the session expired before the form was sent.
    'title_419' => 'Page Expired',
    'text_419' => 'The page was open for too long, so your request could not be completed. Go back, reload the page and try again.',

    // 429: rate limited.
    'title_429' => 'Too Many Attempts',
    'text_429' => 'Too many requests were sent in a short time. Please wait a moment, then try again.',

    // 500: an error on the server (no details are ever shown).
    'title_500' => 'Something Went Wrong',
    'text_500' => 'An error on our side stopped this page from loading. Please try again. If the problem continues, you can contact us by phone or email.',

    // 503: maintenance mode or a service that is down.
    'title_503' => 'Page Temporarily Unavailable',
    'text_503' => 'This page cannot be shown right now. Please try again later. You can still contact us by phone or email.',

    // Catch-alls (errors/4xx, errors/5xx, 401, 402): statuses without a page of their own.
    'title_4xx' => 'This Request Could Not Be Completed',
    'text_4xx' => 'The page could not handle this request. Go back and try again, or return to the homepage and continue from there.',
    'title_5xx' => 'The Page Could Not Load',
    'text_5xx' => 'A problem on our side stopped this page from loading. Please try again in a moment. You can still contact us by phone or email.',

    // 419 action: back to the previous page (reloaded, so its form works again).
    'back' => 'Go back',
];
