<?php

/*
| Design 2 Legal Knowledge Centre (P03, d2::inner.resources): PROPOSED COPY, not in the approved DOCX
| files; needs client approval in EN/AR/RU (docs/proposed-copy.md). Read as d2p('resources_page.<key>')
| or trans_choice('proposed.d2.resources_page.<key>', $n). Everything else on the page is approved copy:
| the search and topic controls (ui('resources.*')), the categories, the nine guides and every link label
| (content()). The guide cards show no reading time (Design 2.1 D27).
*/

return [
    // Number of guides in a category, under the category name in the guide directory.
    // trans_choice: one | other.
    'category_count' => ':count guide|:count guides',
    // Result of the search and topic filter, shown under the controls and announced politely.
    // trans_choice: one | other. With no result the approved resources.no_results message is shown instead.
    'results' => ':count guide found|:count guides found',
    // Phones (below 768 px, with JavaScript): each guide starts as its summary card; this disclosure opens the
    // full guide in place, and the second label closes it again.
    'guide_open' => 'Read the full guide',
    'guide_close' => 'Hide the full guide',
];
