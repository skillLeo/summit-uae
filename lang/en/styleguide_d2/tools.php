<?php

// Internal styleguide annotations for the Design 2 "Tools" area (noindex utility page). Not public
// website copy: Arabic and Russian pages fall back to these English labels. The specimens themselves
// render the tools' proposed copy (lang/{locale}/proposed-d2/tools.php) and approved ui() strings.
return [
    'title' => 'Tools',
    'intro' => 'The calculators page (/{locale}/calculators/, brief 2.1 §5.1; pending client approval, noindex): the end-of-service gratuity estimator and the Dubai rent increase check. Every specimen below runs the real page script on its own fields; formulas, bands and validation live in resources/js/d2/tools/gratuity.js and rent.js (Vitest).',

    'section' => 'Section',
    'section_note' => 'The page H1 + intro, then underline tabs [Gratuity estimator | Rent increase check] (D35, x-d2.ui.tabs): deep links #tool-gratuity and #tool-rent, the address follows a switch. d2Tools brings the opened tool in from the side of its tab (data-enter) and, below 768 px, adds the swipe between the two tools (tools/swipe.js: touch-action pan-y pinch-zoom on the panels, so vertical scrolling and pinch zoom stay the browser\'s; a drag past a quarter of the panel or a flick opens the neighbour in the reading direction, RTL mirrored; never from a form control or a screen edge). The sector is a segmented control where its options fit and a select on phones and wherever they do not. Dates are x-d2.ui.date (D36): typed in the page language\'s order or picked from the calendar. Without JavaScript both tools show under the tabs\' jump links, the fields stay usable, the result card says the estimate needs JavaScript and keeps the consultation link (?service=…&tool=…, never a figure in the URL). Linked from home section 09, S06 (#tool-gratuity) and S05 (#tool-rent) while pending copy is shown.',
    'motion_note' => 'Numbers change with a 160 ms opacity cross-fade and never count up; the stacked bar widths move on the bar spring {200, 26}; under reduced motion everything changes at once. Results are announced in a polite live region after typing stops; field errors appear once a field is committed (change or blur).',
    'design_note' => 'Design 2.1: white cards with a 1px line and no shadow; no eyebrow over the result (D01), no icon tile or decorative button icons (D04); colour only on the data marks (bar segments, their table keys, the service timeline), every text in ink; nothing under the 13 px caption. Phase 2: "—" until the required fields are valid, under the helper line (D31); the breakdown appears with a result, amounts end-aligned in tabular figures (D32); every control fills its column (D33); the bar and its keys only with values (D34).',

    'gratuity' => 'Gratuity estimator',
    'g_empty' => 'Empty: example values greyed as placeholders; the result card says what is needed above "—"; no bar, no breakdown',
    'g_filled' => 'Filled: 3,147 days counted (20 days of unpaid absence), basic 15,000, total 21,000, leave, notice and unpaid wages. Stacked bar (gratuity, leave, notice, unpaid), service timeline (1-year pin, 5-year boundary "21 days/yr | 30 days/yr", hatched absence notch), "May apply" on the notice',
    'g_cap' => 'Badges: "Cap applied" and "Cap based on basic wage" (40 years on a basic 10,000, no total wage: limited to 24 months of the basic wage)',
    'g_under' => 'Under one year: no gratuity, with the explanation',
    'g_part' => 'Part-time (24 of 48 hours): every part pro-rated, with the share',
    'g_temporary' => 'Temporary work: no gratuity, leave still counted',
    'g_errors' => 'Validation errors: last working day before the start date, total wage below the basic wage, negative leave, notice outside 30–90 days; the result card asks to check the highlighted fields',
    'g_out' => 'Out of scope (DIFC, ADGM, Government, Domestic worker): the calm panel replaces the estimator, with the consultation button',
    'g_rtl' => 'RTL (Arabic, filled): the bar fills from the right, the timeline runs right to left, AED amounts and ranges are isolated with <bdi>. On English and Russian pages the Arabic web font is not loaded (fonts are subset per language), so this specimen shows the system Arabic fallback',

    'rent' => 'Rent increase check',
    'r_empty' => 'Empty: the band scale explains the five bands before any figure is entered',
    'r_ok' => 'Filled, notice on time: 22.34% below the average → up to 10% (AED 8,000.00 a year); notice 120 days before expiry',
    'r_short' => 'Notice too late: 75 days before expiry → warning',
    'r_after' => 'Notice after the expiry date → warning',
    'r_above' => 'Rent at or above the index average → no increase (first band)',
    'r_errors' => 'Validation errors: negative rent, average that is not a number',
    'r_rtl' => 'RTL (Arabic, filled)',
];
