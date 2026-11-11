<?php

// Internal styleguide annotations for the Design 2 "Search and FAQ" area (noindex utility page). Not public
// website copy: Arabic and Russian pages fall back to these English labels. Demo content on the page is
// approved copy (ui(), content()) or proposed copy (d2p('palette.…'), d2p('faq.…'), d2p('hours.…')).
return [
    'title' => 'Search and FAQ',
    'intro' => 'The command palette, the FAQ browser with its local filter, the live office-hours status and the shared text normalisation.',

    // Sample queries typed into the specimens (per page language) and a query that finds nothing.
    'demo_query' => ['en' => 'cheque', 'ar' => 'شيك', 'ru' => 'чек'],
    'demo_none' => 'qzxqzx',

    'palette' => 'Command palette',
    'palette_note' => 'Header search, "/" and Ctrl/Cmd+K open it (d2:palette-open); any script can open it with a text already in the field and its results shown (detail.query, engineering §4a). The index (php artisan summit:search-index → public/search/{locale}.json) and MiniSearch load on first open; that JSON is the only request. ↑ ↓ move the active option, Enter opens it, Esc clears the field, then closes. Below 768 px the dialog fills the screen.',
    'palette_live' => 'Live: opens the real palette',
    'palette_empty' => 'Empty: the first six practice areas in the approved order, titles only (their summaries come with the results), + View All Practice Areas',
    'palette_results' => 'Results: groups under plain headings (approved navigation labels in the UI-label role, never chips), text rows (title and summary in full, never cut), matches marked, active row with the keyboard ring, "Describe your matter" last',
    'palette_none' => 'No results: message, hint and "Describe your matter" (prefill with the typed words). Words match exactly or as a word start; Latin and Cyrillic words of five letters or more also with one typo, Arabic words never (one dot turns an Arabic word into another: "space" into "judicial"), and a sentence that falls back to half of its words never counts a near-miss, so nonsense finds nothing in every language',
    'palette_loading' => 'Loading: only when a query waits for the first index load',
    'palette_error' => 'Error: the index could not load; retry, and "Describe your matter" still works',

    'faq' => 'FAQ browser',
    'faq_note' => '<x-d2.faq.browser>: approved category tabs (Phase A tabs), local search over every question and answer (normalise() + light stemming, every word must match), Phase A accordion. With JavaScript the tabbed layout is drawn from the first paint (html.js: only the initial category, or the one a #hash points into, with its tab marked), so nothing moves when the script mounts. Without JavaScript every category shows under its heading, and the search field, "Show all answers" and "Copy question link" are hidden. Specimens use three categories.',
    'faq_default' => 'Default, with the P05 options: "Show all answers" and "Copy question link"',
    'faq_search' => 'Searching: categories become group headings, the tab bar steps aside, matches are marked, count announced',
    'faq_none' => 'No results: the approved message, "Show all questions", and the tabs back below',

    'hours' => 'Office-hours status',
    'hours_note' => 'Any <span data-hours-status></span> next to the approved hours sentence (each place prints the sentence of its own Word file: App\\Design\\D2\\Hours::sentence()) is filled by modules/hours.js (Asia/Dubai, Monday to Friday, 9 to 6, Intl.DateTimeFormat in the browser), in each locale\'s approved time style (English "Open now, closes at 6 pm", Russian 24-hour, Arabic as the homepage line) and without middle dots; the time difference has a line of its own. The next opening reads "today" or "tomorrow" (UAE days) only while the visitor\'s own calendar day is the UAE\'s; otherwise the weekday is named. data-hours-status="short" leaves out the time difference. The specimens below are written by the same module\'s server helper (Hours::line(), Hours::offset()).',
    'hours_live' => 'Live, next to the approved sentence',
    'hours_open' => 'Open, visitor on UAE time (no difference shown)',
    'hours_open_ahead' => 'Open, the UAE 4 h ahead of the visitor',
    'hours_closed_today' => 'Closed early on a weekday: opens today',
    'hours_closed_tomorrow' => 'Closed on a weekday evening (Friday excepted) or on Sunday: opens tomorrow',
    'hours_closed' => 'Closed on Friday evening or Saturday: opens Monday',
    'hours_closed_behind' => 'Closed late on Monday, the UAE 1.5 h behind the visitor (India), where it is already Tuesday: the weekday is named',
    'hours_short' => 'Short variant',
    'hours_night' => 'On night',

    'normalise' => 'Text normalisation',
    'normalise_note' => 'resources/js/d2/lib/normalise.js (shared contract F), used by the palette, the FAQ filter and the Navigator on both the text and the query. Lower case; Latin and Cyrillic diacritics stripped, ё → е (й stays); Arabic أ إ آ ٱ → ا, ة → ه, ى → ي, tashkeel and tatweel removed; Arabic-Indic digits → 0–9.',
    'normalise_examples' => [
        ['Café, Müller, Straße', 'cafe, muller, strasse'],
        ['Расчёт выплат, Трудовой', 'расчет выплат, трудовой'],
        ['أَحْكَامُ الإِيجَارِ', 'احكام الايجار'],
        ['مُكافأة نهاية الخدمة', 'مكافاه نهايه الخدمه'],
        ['دعوى، عقــــد، ٢٠٢١', 'دعوي، عقد، 2021'],
    ],
    'normalise_in' => 'Text',
    'normalise_out' => 'normalise(text)',
];
