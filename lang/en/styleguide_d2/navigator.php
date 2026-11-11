<?php

// Internal annotations for the Design 2 styleguide, Matter Navigator area (noindex utility page). Not
// public website copy: Arabic and Russian pages fall back to these English labels. Demo content is
// approved copy (NavigatorData) and the navigator area's proposed copy (lang/*/proposed-d2/navigator.php).
return [
    'title' => 'Matter Navigator',
    'intro' => 'The dark workbench of the home page (#navigator): the 44 approved situations as one radio group under sticky practice-area headings, search and chips that filter it, and "Your starting file" (white, radius 10, no shadow) with the document checklist, aligned first steps and mistakes (subgrid), text link cards and the actions. Specimens here are static; the live one is on the home page.',

    'list' => 'Situation list on the dark band',
    'list_note' => 'One radio per row (the kit’s ring; the dot scales in on the snappy spring, white on dark); the whole row is the target. Rest rows read in --on-dark-2, the hovered, focused and chosen rows in --on-dark on a --dark-3 veil (opacity only). Group headings: the approved service titles in the minor heading role (16/600, white; no mono, capitals, gold or numbers: Design 2.1 D01), sticky under the header. Design 2.1 D29: from 1280 px the list scrolls with the page (no scroll box) and the starting file beside it is sticky 96 px from the top; a file taller than the screen moves with the scroll so its end and its start can both be read (navigator/sticky.js).',
    'list_states' => 'rest, hover, focus-visible, selected (top to bottom)',
    'filtered' => 'filtered: chip "Debts and Cheques" pressed (white fill, ink label), one group left',
    'empty_search' => 'empty search result: visible message + Clear filter, also sent to the polite status',
    'tools' => 'search (a --dark-2 field, radius 6; its placeholder is its own name, whole on one line from 320 px; below 768 px these frames are as wide as the live band) and the chip toolbar: one Tab stop, ← → move (mirrored in RTL), Home / End, Enter / Space press; Design 2.1 D30: the chips wrap from 768 px (every label whole, no buttons); below 768 px one sideways rail across the screen that scrolls by touch, rests with a chip starting where the faded edge ends (mandatory snap) and fades where more chips wait (alpha masks)',
    'chip_states' => 'chips: pressed, focus-visible, hover',

    'controls' => 'Radio and checkbox states',
    'radio_rest' => 'radio: rest',
    'radio_hover' => 'radio: hover',
    'radio_focus' => 'radio: focus-visible (light gold ring on dark)',
    'radio_checked' => 'radio: checked',
    'check_rest' => 'checkbox in the card (.on-light): rest',
    'check_hover' => 'checkbox: hover',
    'check_focus' => 'checkbox: focus-visible (ink ring on white)',
    'check_checked' => 'checkbox: ticked (the tick draws in)',

    'progress' => 'Checklist progress',
    'progress_note' => 'Native <progress> (the kit’s 4 px ink bar on --line in the light card) with the proposed trans_choice line; the numbers are isolated (<bdi>) so the Arabic sentence keeps its direction. The bar grows on --spring-bar; numbers never count up.',
    'progress_none' => '0 of N',
    'progress_some' => 'partial',
    'progress_all' => 'all ready',

    'card' => 'Your starting file',
    'card_note' => 'Header: service (H3), the approved "This service may be relevant if:" and the chosen situation as a quote in the lead role (the interface sans, upright). Checklist with progress, the approved note, the device-only note and Clear ticks (shown once something is ticked). First steps (check marks) and mistakes (cross marks) share rows through subgrid, so row n starts at the same height in both columns; their headings are the minor heading role. Guide and service links are text cards (no icon, no arrow). Actions: Request (Ink, prefill), Print and Copy link (labelled Secondary buttons: print and link glyphs are not in the brief’s icon list). No "Your starting file" kicker (D01): the sheet title names the file below 1280 px.',
    'card_filled' => 'chosen situation, two documents ticked (Clear ticks shown), forced: Copy link focus-visible',
    'card_empty' => 'empty: before a choice (the approved headings list what a file contains, as plain text)',
    'card_mobile' => 'below 1280 px the same card opens in the Phase A sheet (bottom sheet < 768, side panel from 768): the sheet footer holds Request, Print and Copy stay at the end of the file',
];
