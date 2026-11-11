<?php

/*
| Design 2 mobile shell (menu sheet, carousels): PROPOSED copy, not in the approved DOCX files.
| Read as d2p('mobile.…'), shown with @proposed, listed in docs/proposed-copy.md for approval.
| Arabic: good-faith translation of the English proposal (lang/en/proposed-d2/mobile.php).
*/

return [
    'sheet' => [
        'menu' => 'القائمة',
    ],

    // Screen readers only (aria-roledescription and the accessible names of the position dots).
    'carousel' => [
        'roledescription' => 'شريط بطاقات',
        'dots' => 'اختر بطاقة',
        // :n = position of the card, :total = number of cards.
        'dot' => 'البطاقة :n من :total',
        // Long rails: the position between the previous and next buttons (numbers left to right, as the hero pager).
        'count' => ':n/:total',
        'prev' => 'البطاقة السابقة',
        'next' => 'البطاقة التالية',
    ],
];
