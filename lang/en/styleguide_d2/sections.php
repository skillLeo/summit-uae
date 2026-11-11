<?php

// Internal annotations for the styleguide area "Home sections" (practice areas, how we work, who
// we help). Not public website copy: Arabic and Russian pages fall back to these English labels. Demo
// content is approved copy (content(), ui()); proposed strings are marked data-copy-status="proposed".
return [
    'title' => 'Home sections',
    'intro' => 'Trust row, practice areas, how we work and who we help (spec §12 C1, C2, C4, C7), built from the Phase A card, chip and segmented control. Approved copy in this page’s language; proposed strings carry the proposed marker.',


    'card' => 'Practice-area card',
    'card_note' => '<x-d2.home.service-card>: the H3 title is the card’s one link, stretched over it (one link, one accessible name); the approved summary in full (Body, never clamped: D09); the approved “Explore This Service” as the visible label (UI label, ink; not a second link, hidden from assistive technology). No icon, no corner arrow, no documents line (D03, D04, D22).',
    'card_rest' => 'Rest',
    'card_hover' => 'Hover (.is-hover): the card’s own hover (the edge turns ink, the title underlines; no lift, no shadow)',
    'card_focus' => 'Keyboard focus (.is-focus): 2 px ring around the whole card',
    'card_row' => 'A row of the grid (D21): equal columns (one on phones, two from 768 px, three from 1280 px), no bento widths, no swipe rail; from 768 px the cards share their row tracks (subgrid), so titles, summaries and labels line up across the row',
    'closing' => 'Closing row (D23): the approved multi-area paragraph on the start side, the Ink “View All Practice Areas” on the end side, one compact row under a hairline (stacked on phones)',

    'filter' => 'Audience filter',
    'filter_note' => 'Toggle chips (aria-pressed). Under .js their row is reserved from the first paint and they appear once d2Services runs (never a dead control); without JavaScript there are no chips. A choice hides the other cards ([hidden]); the equal grid closes up in reading order, the rest slide into place (FLIP on the ui spring; instant under reduced motion) and the count is announced politely. Focus stays on the chip.',
    'filter_live' => 'Live: five practice areas',
    'filter_filtered' => 'Filtered out (server snapshot, Investors pressed): the other cards leave the grid, the rest close up in reading order',

    'steps' => 'Workflow steps and their line',
    'steps_note' => '<x-d2.home.steps> (D49): an ordered list of the four approved steps, each its marker 01–04 in the interface sans, 15/500 tabular, in an outlined tag (hidden from assistive technology: the list numbers the steps; numbers are allowed on the four workflow steps), its title (H4 in the H3 role) and its text (Body, ink-2). Not cards: nothing is interactive. One static 1 px --line-strong line joins the markers. No mono, no bronze ring or glow, no scroll-driven fill.',
    'steps_row' => 'One row of four from 1024 px (2 x 2 from 768 px); side by side the steps share their rows (subgrid), so every text starts on one line; the line runs along the markers of a row',
    'steps_stacked' => 'Phones: stacked, the marker at the start, the line runs vertically (.method__steps--stacked keeps this layout at every width)',

    'questions' => 'Review: paragraphs and questions',
    'questions_note' => 'D49: the two approved paragraphs (Lead, ink-2) on the start side, the approved “Questions We Help You Answer” and its five questions on the end side (columns 1–6 and 8–12 from 1024 px, stacked below). The questions have no approved answers, so they form a plain list between hairlines, not an accordion, without check marks (D04). The sample-document still-life is gone (decoration only).',
    'questions_layout' => 'The review section’s two columns (the home renders the H3 “Questions We Help You Answer”; an H4 here)',

    'who' => 'Who we help',
    'who_note' => '<x-d2.home.who-list>: the five approved audience blocks of the homepage DOCX (“Use five audience blocks”), all visible, each its title (H3 role) and text (Body, ink-2) in one row between hairlines: columns 1–4 and 5–11 from 1024 px, 3 + 5 of 8 from 768 px on one baseline, stacked on phones. Not cards and no service chips (they repeated the service cards). Each block keeps its Design 1 anchor (#who-individuals …).',
    'who_list' => 'The five audiences',

    'start' => 'Start your matter: the result half',
    'start_note' => '<x-d2.hero.tool> (D19): never an empty box. Before a choice, “Common situations” and up to three quick picks (config design.d2.home.quick_picks, set in the admin in Phase 4), the one chip without icon or dot, holding the complete approved situation (a wrapped pick keeps a radius of half the chip height). A pick follows the audience like the select; it selects its situation, shows the documents and moves focus to the select. Each pick is a submit button, so it works without JavaScript.',
    'start_picks' => 'Before a choice: the quick picks',
    'start_documents' => 'After a choice (here sent back with ?situation=S01-2): the documents usually needed',
    'start_none' => 'No quick pick for the chosen audience (or none configured): the plain label and one quiet line, never a box',
];
