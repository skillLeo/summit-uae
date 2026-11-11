<?php

/*
| Design 2 live office-hours status: PROPOSED copy (Russian draft). Same keys as lang/en/proposed-d2/hours.php.
| The times follow the approved "с 9:00 до 18:00" (24-hour clock, no leading zero). `days` read as they do after
| "откроется" (в понедельник, во вторник …). Plurals one|few|many|other: 1 час, 2 часа, 5 часов, and a fraction
| (1,5 часа) takes "other". No-break spaces (\u{00A0}) keep "в 18:00" and "на 4 часа" on one line.
*/

return [
    'open' => 'Сейчас открыто, закрывается :time',
    'closed' => 'Сейчас закрыто, откроется :day :time',

    // Opens later today / tomorrow (only while the visitor's calendar day is the UAE's; see the English file).
    'closed_today' => 'Сейчас закрыто, откроется сегодня :time',
    'closed_tomorrow' => 'Сейчас закрыто, откроется завтра :time',

    'days' => [
        1 => 'в понедельник',
        2 => 'во вторник',
        3 => 'в среду',
        4 => 'в четверг',
        5 => 'в пятницу',
        6 => 'в субботу',
        7 => 'в воскресенье',
    ],

    'time' => [
        'am' => "в\u{00A0}:hour_of_day::minute",
        'pm' => "в\u{00A0}:hour_of_day::minute",
        'am_minutes' => "в\u{00A0}:hour_of_day::minute",
        'pm_minutes' => "в\u{00A0}:hour_of_day::minute",
    ],

    'ahead' => "Время в ОАЭ опережает ваше на\u{00A0}:count\u{00A0}час|Время в ОАЭ опережает ваше на\u{00A0}:count\u{00A0}часа|Время в ОАЭ опережает ваше на\u{00A0}:count\u{00A0}часов|Время в ОАЭ опережает ваше на\u{00A0}:count\u{00A0}часа",
    'behind' => "Время в ОАЭ отстаёт от вашего на\u{00A0}:count\u{00A0}час|Время в ОАЭ отстаёт от вашего на\u{00A0}:count\u{00A0}часа|Время в ОАЭ отстаёт от вашего на\u{00A0}:count\u{00A0}часов|Время в ОАЭ отстаёт от вашего на\u{00A0}:count\u{00A0}часа",
];
