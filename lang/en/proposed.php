<?php

/*
| PROPOSED COPY: NOT in the approved DOCX files. Needs client approval in all
| three languages. Every key here is listed in docs/proposed-copy.md.
*/

return [
    'nav' => [
        'main' => 'Main navigation',
        'quick' => 'Quick navigation',
        'breadcrumb' => 'Breadcrumb',
    ],
    'external' => [
        'new_tab' => '(opens in a new tab)',
    ],
    'suggest' => [
        'message' => 'This page is available in English.',
        'action' => 'View in English',
        'dismiss' => 'Dismiss',
    ],
    'hours' => [
        'open' => 'Open now · UAE time',
        'closed' => 'Closed now · UAE time',
    ],
    'navigator' => [
        'heading' => 'Where do you stand?',
        'intro' => 'Choose the situation closest to yours to see the documents that help a first review, useful first steps and mistakes to avoid. Your choices stay in this browser and are not sent anywhere.',
        'step_audience' => 'Who are you?',
        'audience_all' => 'All',
        'step_pick' => 'Choose your situation',
        'step_file' => 'Your starting file',
        'filter_label' => 'Filter situations',
        'filter_placeholder' => 'Type a word, for example cheque',
        'no_results' => 'No situation matches this word. Try another word or clear the filter.',
        'clear_filter' => 'Clear filter',
        'show_file' => 'Show the starting file',
        'empty' => 'Choose a situation to see its starting file.',
        'your_situation' => 'Your situation',
        'saved_note' => 'Your ticks are saved on this device only.',
        'clear_ticks' => 'Clear ticks',
        'print' => 'Print checklist',
        'copy_link' => 'Copy link',
        'link_copied' => 'Link copied',
        'change' => 'Choose another situation',
        'progress' => ':current of :total',
        'print_date' => 'Prepared on :date',
        'close' => 'Close',
        'back' => 'Back',
    ],
    'team' => [
        'portrait_pending' => 'Portrait to follow',
    ],
    'sheet' => [
        'close' => 'Close',
    ],
    'form' => [
        'reference' => 'Reference',
    ],
    'preview' => [
        'notice' => 'Prototype 1 preview. The full approved content and design of this page arrive in Phase 2. Until then you can request a consultation or return to the homepage.',
    ],
    'offline' => [
        'title' => 'You are offline',
        'text' => 'This page is not available offline. You can still call us, or message us on WhatsApp or by email:',
    ],
] + [
    // Design 2 (docs/upgrade-plan.md): one file per area in proposed-d2/, read as proposed.d2.{area}.{key}.
    'd2' => (static function (string $dir): array {
        $areas = [];
        foreach (glob($dir.'/*.php') ?: [] as $file) {
            $areas[basename($file, '.php')] = require $file;
        }

        return $areas;
    })(__DIR__.'/proposed-d2'),
];
