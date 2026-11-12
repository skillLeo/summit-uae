<?php

/*
| Admin CMS switches (docs/briefs/design-2.1-and-admin-cms.md §8, §12.1).
*/
return [
    // Rebuild the command-palette search index (public/search/*.json) after a publish.
    'search_index_on_publish' => (bool) env('CMS_SEARCH_INDEX_ON_PUBLISH', false),

    // Requests inbox (brief §10). The internal review target until the client sets one in Office Hours (business
    // hours; never public), and the spam score from which a request is stored as spam (App\Requests\SpamCheck).
    'requests' => [
        'default_review_target_hours' => (int) env('CMS_REVIEW_TARGET_HOURS', 8),
        'spam_threshold' => 60,
        // Waiting-time badge in the inbox turns to a warning from this share of the target.
        'soon_share' => 0.75,
    ],
];
