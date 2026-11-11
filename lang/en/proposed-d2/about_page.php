<?php

/*
| Design 2 About page (P06, d2::inner.about): PROPOSED COPY, not in the approved DOCX files; needs client
| approval in EN/AR/RU (docs/proposed-copy.md). Read as d2p('about_page.<key>').
| Everything else on the page is approved copy (the P06 sections, the three team members).
*/

return [
    // Phones (below 768 px, with JavaScript): each team member shows the profile card and the first
    // paragraph of the approved biography; this disclosure opens the rest of the biography and the approved
    // "Areas of Focus / Expertise" in place, and closes them again. A link to the member (#team-ahmed)
    // opens it by itself.
    'profile_open' => 'Read the full profile',
    'profile_close' => 'Hide the full profile',
];
