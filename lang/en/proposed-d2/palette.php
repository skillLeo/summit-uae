<?php

/*
| Design 2 command palette (<x-d2.shell.palette>, opened by the header search, "/" and Ctrl/Cmd+K):
| PROPOSED copy, not in the approved DOCX files. Read as d2p('palette.…'), shown with @proposed, listed in
| docs/proposed-copy.md for approval. The field placeholder is d2p('shell.search_placeholder'); the group
| labels are the approved navigation labels (ui('nav.practice_areas'), ui('nav.resources'), ui('nav.faq'),
| ui('nav.case_studies')).
*/

return [
    // Name of the search dialog (its title is visually hidden: screen readers only).
    'title' => 'Search the site',

    // Accessible name of the search field.
    'input_label' => 'Search services, guides, FAQs and case studies',

    // Number of results, announced to screen readers (trans_choice: one|other).
    'results' => ':count result|:count results',

    // No results. :query is the visitor's text (never sent anywhere).
    'no_results' => 'No results for “:query”',
    'no_results_hint' => 'Try another word, or describe your matter in your own words: they go into the consultation form as a draft you can edit. Nothing is sent until you submit the form.',

    // The action offered with the visitor's words (last row of the results, and the no-results state).
    'describe' => 'Describe your matter',
    'describe_with' => 'Start a consultation request with “:query”',

    // The search index loads on first use (no "…": brief §3.6 rule 8 / lint rule 5, like the kit's "Loading").
    'loading' => 'Loading search',
    'error' => 'Search could not be loaded. Check your connection and try again.',
    'retry' => 'Try again',

    // Keyboard hints in the footer (shown with a fine pointer only).
    'hint_move' => 'to move',
    'hint_open' => 'to open',
    'hint_close' => 'to close',
    'key_up' => '↑',
    'key_down' => '↓',
    'key_enter' => 'Enter',
    'key_esc' => 'Esc',
];
