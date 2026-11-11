<?php

/*
| Design 2 FAQ browser (<x-d2.faq.browser>: the home FAQ section and, in Phase D, the P05 FAQ page):
| PROPOSED copy, not in the approved DOCX files. Read as d2p('faq.…'), shown with @proposed, listed in
| docs/proposed-copy.md for approval. The approved FAQ strings (search label and placeholder, "No matching
| question found…", "Show all questions", "Show all answers", "Copy question link" …) come from ui('faq.…').
*/

return [
    // Accessible name of the category tab list (screen readers).
    'categories_label' => 'FAQ categories',

    // Live count under the search field while the local filter runs (trans_choice: one|other).
    'results' => ':count question found|:count questions found',

    // End of each category (spec §2 row 18): the prompt, then the link that starts a consultation request
    // with that category's practice area already chosen.
    'start_question' => 'Was this about your matter?',
    'start_link' => 'Start here',
];
