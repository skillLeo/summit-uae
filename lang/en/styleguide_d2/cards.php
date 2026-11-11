<?php

// Internal styleguide annotations for the Design 2 "Cards" area (team profile, case preview, case directory
// and guide cards: home C8–C10, reused by the Phase D inner pages). Not public website copy: Arabic and Russian
// pages fall back to these English labels. Demo content on the page itself is approved copy (content(),
// ui()) or the proposed keys of lang/{locale}/proposed-d2/{team,guides}.php.
return [
    'title' => 'Cards',
    'intro' => 'Team profile, case and legal guide cards in every state. Home: #our-team (off by default), #case-studies, #guides; the About, Case Studies and Knowledge Centre pages reuse the same components.',

    'profile' => 'Profile card (<x-d2.team.profile>)',
    'profile_note' => 'Portrait in a 4:5 frame everywhere (AVIF / WebP 480 and 800 w, lazy, explicit size), placed so every face shares one eye line (D42: the focal point per photo in config design.d2.team, App\\Design\\D2\\Portraits; the admin sets it in Phase 4). Approved name (the card link to the About profile), approved title, at most three focus chips in the one chip style (proposed, D44), the approved working-languages line as plain text, and the proposed request link (?consultant=, prefill() with JavaScript). No credentials, years or memberships.',
    'profile_default' => 'Default: whole-card link + nested request link',
    'profile_hover' => 'Hover (forced): the card edge turns ink and the name underlines; no lift, no zoom (Design 2.1, D05)',
    'profile_focus' => 'Keyboard focus on the card link (forced ring)',
    'profile_pending' => 'Photo pending (D43): a neutral 4:5 panel of the same size, the initials of the approved name in the sans; no serif, no ring',
    'profile_static' => 'Static (About page): no link, no request link',
    'profile_wide' => 'Wide card (560 px and more): row layout, the tablet home layout; the portrait keeps its 4:5 frame',
    'profile_dir' => 'Other direction (approved copy of the other script)',
    'profile_rail' => 'Home "Our Team" block (off by default, D24): three columns with shared rows (subgrid) from a 900 px list; one wide card per row from 768 px; a swipe rail below 768 px.',

    'preview' => 'Case preview (<x-d2.case.preview>, home section 06)',
    'preview_note' => 'A text card (D25): the approved homepage title (the card\'s one link, to the story\'s anchor on the Case Studies page), the approved paragraph in full and the approved link label ("Read the Cheque Case Study") as the visible label of that link. No number, icon, chip, timeline or badge. The home shows the two previews side by side from 768 px, their tracks shared (subgrid).',
    'preview_default' => 'Default',
    'preview_hover' => 'Hover (forced): the card edge turns ink and the title underlines; nothing moves (D05)',
    'preview_focus' => 'Keyboard focus on the card link (forced ring)',
    'preview_dir' => 'Other direction (Latin runs isolated)',

    'case' => 'Case directory card (<x-d2.case.card>, Case Studies page)',
    'case_note' => 'A text card (D46): the story\'s service as a category line (small, bronze, drawn above the title), the approved directory title (the card\'s one link, to the full story on the page) and the approved card summary. No numeral, chip, icon or corner chevron; no outcome on the card.',
    'case_default' => 'Default',
    'case_hover' => 'Hover (forced): the card edge turns ink and the title underlines; nothing moves (D05)',
    'case_focus' => 'Keyboard focus on the card link (forced ring)',
    'case_empty' => 'No service: the category line is omitted',
    'case_dir' => 'Other direction (Latin runs isolated)',

    'guide' => 'Guide card (<x-d2.guide.card>)',
    'guide_note' => 'A text card (D27): the approved category (small, bronze, drawn above the title), the approved title (the card\'s one link, to the guide on the Knowledge Centre), the approved summary and the approved "Read the Guide" (the visible label of that link, not a second link), without icons or arrows. No generated cover and no reading time (brief §3.6 rules 9 and 10): an image area appears only when the admin sets a real cover (App\\Design\\D2\\Cover::image(), Phase 4; none exists yet).',
    'guide_default' => 'Default',
    'guide_hover' => 'Hover (forced): the card edge turns ink and the title underlines; no lift (D05)',
    'guide_focus' => 'Keyboard focus on the card link (forced ring)',
    'guide_dir' => 'Other direction',
    'guide_row' => 'A row of the home grid: two columns from 768 px, three from 1280 px, where the cards share their four row tracks (subgrid), so categories, titles, summaries and "Read the Guide" line up across the row (brief §7.1 rule 12) and a one-line category sits on the end of its track, 12 px above its title. On phones a swipe rail of cards of one height: each card keeps its own flow, the category reserves two lines (titles start level) and the label rests on the bottom',
];
