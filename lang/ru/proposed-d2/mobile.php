<?php

/*
| Design 2 mobile shell (menu sheet, carousels): PROPOSED copy, not in the approved DOCX files.
| Read as d2p('mobile.…'), shown with @proposed, listed in docs/proposed-copy.md for approval.
| Russian: good-faith translation of the English proposal (lang/en/proposed-d2/mobile.php).
*/

return [
    'sheet' => [
        'menu' => 'Меню',
    ],

    // Screen readers only (aria-roledescription and the accessible names of the position dots).
    'carousel' => [
        'roledescription' => 'карусель',
        'dots' => 'Выбор карточки',
        // :n = position of the card, :total = number of cards (no plural forms: «из» takes the genitive
        // of the number, which the screen reader reads correctly for every value).
        'dot' => 'Карточка :n из :total',
        // Long rails: the position between the previous and next buttons (as the hero pager's "1/6").
        'count' => ':n/:total',
        'prev' => 'Предыдущая карточка',
        'next' => 'Следующая карточка',
    ],
];
