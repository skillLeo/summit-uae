<?php

/*
| Design 2 Matter Navigator (home #navigator, night workbench): interface text the DOCX files do not
| cover. PROPOSED COPY, needs client approval in EN/AR/RU (docs/proposed-copy.md). Read as
| d2p('navigator.<key>'); the plural lines with trans_choice('proposed.d2.navigator.<key>', n).
| The situations, documents, first steps, mistakes, headings and guide links are approved copy
| (App\Content\NavigatorData), as are "Request a Legal Consultation", "Explore This Service",
| "Read the Guide", "This service may be relevant if:" and the failed-copy message (ui()).
*/

return [
    // H2 (config summit.features.navigator_heading = 'proposed'; 'approved' shows "Questions We Help You Answer").
    'heading' => 'Where Do You Stand?',
    // Under the H2. True with and without JavaScript: the ticks never leave the page (Design 1 review C2).
    'intro' => 'Choose the situation closest to yours to see the documents that help a first review, useful first steps and mistakes to avoid. The documents you tick stay on this device and are never sent to us.',
    // Legend of the situation radio group (screen readers).
    'list_label' => 'Choose your situation',
    // Local search field above the list: its name (hidden label) and its placeholder, one string, so the visible
    // text is the field's name and stays whole in the narrowest phone field (D09: never cut).
    'search_label' => 'Search situations',
    // Practice-area chips (toolbar name) and the first chip.
    'chips_label' => 'Filter by practice area',
    'all' => 'All',
    // Announced while a search or chip filter is active (polite status).
    'matches' => ':count situation|:count situations',
    'no_results' => 'No situation matches this word. Try another word or clear the filter.',
    'clear_filter' => 'Clear filter',
    // Submit button of the version without JavaScript (?navigator=S05-2).
    'show_file' => 'Show the starting file',
    // Label of the result card and title of the mobile sheet.
    'file_title' => 'Your starting file',
    // Empty card before a situation is chosen; the list under it uses the approved headings.
    'empty' => 'Choose a situation to see its starting file.',
    'empty_contents' => 'Each starting file shows',
    // Document checklist progress, e.g. "2 of 4 documents ready" (plural on the number of documents).
    'progress' => ':ready of :total document ready|:ready of :total documents ready',
    // Under the checklist (JavaScript only: the ticks are kept in this browser's storage).
    'saved_note' => 'Your ticks are saved on this device only.',
    'clear_ticks' => 'Clear ticks',
    // Icon buttons (accessible name and tooltip) and the toast after a successful copy.
    'print' => 'Print starting file',
    'copy_link' => 'Copy link',
    'link_copied' => 'Link copied',
    // Date line on the printed starting file.
    'print_date' => 'Prepared on :date',
    // Short polite announcement when a choice changes the starting file.
    'updated' => 'Starting file: :service',
];
