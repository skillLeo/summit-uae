<?php

/*
| Design 2 team (home #about, C8; the profile cards are reused by the About page). PROPOSED COPY, not in
| the approved DOCX files: needs client approval in EN/AR/RU (docs/proposed-copy.md).
| Read as d2p('team.<key>') / trans('proposed.d2.team.<key>').
*/

return [
    // Visible label of the button on each profile card (spec C8; it opens the consultation wizard with
    // this consultant pre-selected, ?consultant=team-…). One string per member, because Russian declines
    // the name.
    'cta' => [
        'team-ahmed' => 'Request a consultation with Ahmed',
        'team-mohamed' => 'Request a consultation with Dr Mohamed',
        'team-sarah' => 'Request a consultation with Sarah',
    ],
    // The button's label for a member who has no string of their own yet.
    'cta_generic' => 'Request a consultation',
    // Focus chips on each profile card: short forms of the member's approved focus line, without the
    // degree (no credentials on the cards). The list is named by the approved "Areas of Focus" /
    // "Areas of Expertise" heading. At most three per member (Design 2.1 D44; the card shows no more).
    'focus' => [
        'team-ahmed' => ['Executive management', 'Service development', 'Client experience'],
        'team-mohamed' => ['Civil matters', 'Commercial matters', 'Criminal matters'],
        'team-sarah' => ['UAE law', 'Contracts', 'Civil and commercial matters'],
    ],
];
