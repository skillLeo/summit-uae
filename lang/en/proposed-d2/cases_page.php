<?php

/*
| Design 2 Case Studies page (P04, d2::inner.case-studies): PROPOSED COPY, not in the approved DOCX files;
| needs client approval in EN/AR/RU (docs/proposed-copy.md). Read as d2p('cases_page.<key>').
| Everything else on the page is approved copy (the P04 head, directory, six full stories, FAQ,
| consultation and scope sections; the approved qualification wording of the P04 introduction). New keys go here in
| all three languages.
*/

return [
    // Phones (below 768 px, with JavaScript): each of the six stories starts as its file card (service, title,
    // matter, the approved qualification and the approved card summary); this disclosure opens the full story in
    // place and closes it again. A link to a story (the directory cards, #case-04) opens it by itself.
    'story_open' => 'Read the full story',
    'story_close' => 'Hide the full story',
];
