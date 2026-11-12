<?php

/*
| Reports (docs/briefs/design-2.1-and-admin-cms.md §11.2 to §11.4): analysis with filters. Staff-facing admin labels,
| never shown on the public site, so they are not proposed copy. Keep the keys identical in lang/{en,ar,ru}/reports.php.
*/

return [
    'title' => 'Reports',
    'subheading' => 'Requests over any period, with filters. Spam is never counted except in the spam rate; times are Dubai time.',
    'loading' => 'Loading the report',

    'tabs' => [
        'label' => 'Report sections',
        'volume' => 'Volume',
        'mix' => 'Mix',
        'workflow' => 'Workflow',
        'sources' => 'Sources',
    ],

    'filter' => [
        'range' => 'Date range',
        'from' => 'From',
        'to' => 'To',
        'compare' => 'Compare with previous period',
        'matter_type' => 'Matter type',
        'locale' => 'Site language',
        'preferred_language' => 'Preferred language',
        'status' => 'Status',
        'assignee_id' => 'Assignee',
        'source_page' => 'Source page',
        'any' => 'All',
        'not_chosen' => 'Not chosen',
        'unassigned' => 'Unassigned',
        'reset' => 'Reset filters',
    ],

    'range' => [
        'today' => 'Today',
        '7d' => '7 days',
        '30d' => '30 days',
        '90d' => '90 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_quarter' => 'This quarter',
        'ytd' => 'Year to date',
        'custom' => 'Custom',
    ],

    'summary' => [
        'separator' => '; ',
        'label' => 'Showing',
        'period' => ':from to :to',
        'compare' => 'compared with :period',
        'no_filters' => 'all requests',
    ],

    'view' => [
        'toggle' => 'Show as chart or table',
        'chart' => 'Chart',
        'table' => 'Table',
        'csv' => 'Export CSV',
    ],

    'empty' => [
        'line' => 'No requests in this period',
        'no_reviews' => 'No request in this period has been reviewed yet',
        'no_closed' => 'No request was closed with an outcome in this period',
        'action' => 'Change range',
    ],

    'chart' => [
        'volume' => [
            'heading' => 'Requests over time',
            'description_day' => 'Received per day, Dubai time.',
            'description_week' => 'Received per week (weeks start on Monday), Dubai time.',
        ],
        'heatmap' => [
            'heading' => 'Weekday and hour',
            'description' => 'Requests received by weekday and hour, Dubai time.',
        ],
        'matter' => [
            'heading' => 'By matter type',
            'description' => 'Requests received in the period, most first; Other matter and Not chosen last.',
        ],
        'languages' => [
            'heading' => 'Site language and preferred language',
            'description' => 'Share of the requests received in the period, in %.',
        ],
        'contact' => [
            'heading' => 'Contact method given',
            'description' => 'What the sender gave: phone only, email only, or both.',
        ],
        'pipeline' => [
            'heading' => 'Status pipeline',
            'description' => 'Where the requests received in the period are now, in %.',
        ],
        'ttfr' => [
            'heading' => 'Time to first review',
            'description' => 'Business hours. Median :median h, P90 :p90 h. n = :n, :unreviewed not reviewed yet.',
            'none' => 'Business hours. No request in this period has been reviewed yet (:unreviewed waiting).',
        ],
        'target' => [
            'heading' => 'Reviewed within the internal target, over time',
            'description_day' => 'Share reviewed within :hours business hours, per day. Unreviewed requests past the target count as missed.',
            'description_week' => 'Share reviewed within :hours business hours, per week. Unreviewed requests past the target count as missed.',
        ],
        'workload' => [
            'heading' => 'Workload per assignee',
            'description' => 'Requests received in the period per assignee, those still open, and those closed in the period.',
        ],
        'outcomes' => [
            'heading' => 'Outcomes',
            'description' => 'Requests closed in the period, by outcome.',
        ],
        'source_pages' => [
            'heading' => 'Top source pages',
            'description' => 'The page the form was sent from. Top 10, then Other.',
        ],
        'referrers' => [
            'heading' => 'Top referrers',
            'description' => 'The site the visitor came from. Top 10, then Other.',
        ],
        'campaigns' => [
            'heading' => 'Top UTM campaigns',
            'description' => 'The utm_campaign of the visit. Top 10, then Other.',
        ],
    ],

    'column' => [
        'day' => 'Day',
        'week' => 'Week starting',
        'requests' => 'Requests',
        'previous' => 'Previous period',
        'share' => 'Share',
        'matter' => 'Matter type',
        'language' => 'Language',
        'site' => 'Site language',
        'site_share' => 'Site language, share',
        'preferred' => 'Preferred language',
        'preferred_share' => 'Preferred language, share',
        'contact' => 'Contact given',
        'status' => 'Status',
        'ttfr' => 'Time to first review',
        'within' => 'Within target',
        'assignee' => 'Assignee',
        'received' => 'Received',
        'open' => 'Still open',
        'closed' => 'Closed in the period',
        'outcome' => 'Outcome',
        'weekday' => 'Weekday',
        'source_pages' => 'Source page',
        'referrers' => 'Referrer',
        'campaigns' => 'UTM campaign',
    ],

    'value' => [
        'percent' => ':value%',
        'week_of' => 'Week of :date',
        'other' => 'Other',
        'none_recorded' => 'None recorded',
        'no_data' => 'No data',
        'phone_only' => 'Phone only',
        'email_only' => 'Email only',
        'both' => 'Phone and email',
    ],

    'series' => [
        'requests' => 'Requests',
        'previous' => 'Previous period',
        'received' => 'Received in the period',
        'reviewed' => 'Reviewed requests',
        'within' => 'Within target',
        'closed' => 'Closed requests',
    ],

    'histogram' => [
        'lt1' => 'Under 1 h',
        '1to4' => '1 to 4 h',
        '4to8' => '4 to 8 h',
        '1to2d' => '1 to 2 business days',
        'gt2d' => 'More than 2 business days',
    ],

    'tile' => [
        'target' => 'Reviewed within the internal target',
        'target_line' => ':met within, :missed missed (target :hours business hours)',
        'target_excluded' => '{1} 1 still inside the target is left out|[2,*] :count still inside the target are left out',
        'spam' => 'Spam rate',
        'spam_line' => ':spam spam of :all submissions',
        'spam_none' => 'No submissions in this period',
    ],

    'heatmap' => [
        'cell' => ':day, :hour: :count',
        'zero' => 'No requests',
        'fewer' => 'Fewer',
        'more' => 'More (busiest hour: :max)',
    ],
];
