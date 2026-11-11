<?php

/*
| Design 2 mobile shell (menu sheet, carousels): PROPOSED copy, not in the approved DOCX files.
| Read as d2p('mobile.…'), shown with @proposed, listed in docs/proposed-copy.md for approval.
| Approved strings (Home, Practice Areas, the CTA, contact labels, office hours) come from ui() / content().
*/

return [
    // Design 2.1 (brief §6): the tab bar shows the four approved navigation labels (ui('nav.*'), wrapping to two
    // lines where needed, never shortened), so it has no proposed labels any more ("Services", "Request", "Tools"
    // and "Menu" are retired with their cells).
    'sheet' => [
        // Title of the menu sheet (opened by the header's menu button).
        'menu' => 'Menu',
    ],

    // Carousels (<x-d2.ui.carousel>, swipe rails of cards on phones). Screen readers only, except the
    // pager's position ('count').
    'carousel' => [
        // aria-roledescription of the carousel region (read as "<section name>, carousel").
        'roledescription' => 'carousel',
        // Accessible name of the row of position dots under the cards.
        'dots' => 'Choose a card',
        // Accessible name of one dot (:n = its position, :total = the number of cards); also what a screen
        // reader hears when the pager below moves to another card.
        'dot' => 'Card :n of :total',
        // Long rails (more cards than 48 px dots fit in the row, e.g. the twelve practice areas on a phone):
        // the position drawn between the previous and next buttons (:n = the card in view, :total = the
        // number of cards), written like the hero pager's "1/6".
        'count' => ':n/:total',
        // Accessible names of the pager's two buttons.
        'prev' => 'Previous card',
        'next' => 'Next card',
    ],
];
