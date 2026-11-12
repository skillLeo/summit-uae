<?php

/*
| Dashboard (docs/briefs/design-2.1-and-admin-cms.md §11.1): today's work, not analytics. Staff-facing admin labels,
| not public copy. Keep the keys identical in lang/{en,ar,ru}/dashboard.php.
*/

return [
    'title' => 'Dashboard',
    'subheading' => 'Today’s work. Spam is never counted; times are Dubai time.',

    'tile' => [
        'new' => 'New requests, last 7 days',
        'waiting' => 'Waiting for first review',
        'ttfr' => 'Median time to first review, last 30 days',
        'closed' => 'Closed, last 30 days',
    ],

    'line' => [
        'change' => ':change vs the previous 7 days',
        'change_percent' => ':change (:percent) vs the previous 7 days',
        'small_base' => ':change vs the previous 7 days (small base)',
        'no_earlier' => ':change vs the previous 7 days (no earlier data)',
        'oldest' => 'Oldest wait: :hours business hours',
        'none_waiting' => 'Nobody is waiting',
        'p90' => 'P90 :hours h. n = :n, :unreviewed not reviewed yet',
        'no_reviews' => 'No request received in the last 30 days has been reviewed yet (:unreviewed waiting)',
        'engagement' => 'Engagement rate :rate% (n = :n)',
        'engagement_hidden' => 'Engagement rate shown from 5 closed with an outcome (n = :n)',
        'hours' => ':hours h',
        'ttfr_change' => ':change h vs the previous 30 days',
        'up' => 'Up',
        'down' => 'Down',
        'same' => 'No change',
        'better' => 'better',
        'worse' => 'worse',
    ],

    'chart' => [
        'heading' => 'Requests, last 30 days',
        'description' => 'Received per day, Dubai time.',
        'series' => 'Requests',
        'empty' => 'No requests in this period',
        'change_range' => 'Change range',
        'reports' => 'Open the reports',
    ],

    'attention' => [
        'heading' => 'Needs attention',
        'over_target' => 'Over the internal target',
        'unassigned' => 'Unassigned',
        'deadline' => 'Deadline within 7 days',
        'none' => 'Nothing needs attention now.',
        'waited' => ':hours business hours',
        'more' => '{1} and 1 more|[2,*] and :count more',
        'open_inbox' => 'Open the inbox',
    ],

    'latest' => [
        'heading' => 'Latest requests',
        'all' => 'All requests',
    ],

    'content' => [
        'heading' => 'Content status',
        'drafts' => 'Drafts waiting to be published',
        'scheduled' => 'Scheduled to publish',
        'pending' => 'Pending client approval',
        'missing' => 'Missing translations',
        'missing_locale' => ':language',
        'open' => 'Open',
    ],
];
