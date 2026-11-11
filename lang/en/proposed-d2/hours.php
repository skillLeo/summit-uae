<?php

/*
| Design 2 live office-hours status (Design 2.1 brief §3.7; fixes D02, D11, D40). resources/js/d2/modules/hours.js
| writes it beside an approved hours sentence, which stays plain text (each place prints the sentence of the Word
| file that defines it: App\Design\D2\Hours::sentence()), so nothing depends on JavaScript. Office hours: Monday to
| Friday 09:00 to 18:00 Asia/Dubai (config/summit.php contact.hours). PROPOSED copy: d2p('hours.…'), listed in
| docs/proposed-copy.md for client approval. No response-time promise, no middle dots.
|
| :time is one of the `time` forms below, :day one of `days`, :count the hour difference (1, 4, 1.5 …).
*/

return [
    // The office is open: "Open now, closes at 6 pm".
    'open' => 'Open now, closes :time',
    // Closed, and the next opening is not today or tomorrow: "Closed now, opens Monday at 9 am".
    'closed' => 'Closed now, opens :day :time',

    // The next opening is later today or tomorrow. Used only while the visitor's calendar day is the UAE's
    // (otherwise "today" would name a different day for them), so `closed` with the weekday stays the fallback.
    'closed_today' => 'Closed now, opens today :time',
    'closed_tomorrow' => 'Closed now, opens tomorrow :time',

    // Weekday names as they read after "opens" (ISO weekday numbers: 1 = Monday … 7 = Sunday).
    'days' => [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ],

    // How :time reads, in the style of the approved hours sentence ("9 am to 6 pm"). :hour is the hour on a
    // 12-hour clock (1 to 12), :hour_of_day on a 24-hour clock (0 to 23), :minute two digits; the *_minutes forms
    // are used when a time is not on the hour. A no-break space (\u{00A0}) keeps a number with its unit on one line.
    'time' => [
        'am' => "at :hour\u{00A0}am",
        'pm' => "at :hour\u{00A0}pm",
        'am_minutes' => "at :hour::minute\u{00A0}am",
        'pm_minutes' => "at :hour::minute\u{00A0}pm",
    ],

    // Difference between UAE time and the visitor's device time, on its own line (hidden when it is 0).
    // Plural forms one|other; a fraction such as 1.5 takes "other".
    'ahead' => "UAE time is :count\u{00A0}hour ahead of yours|UAE time is :count\u{00A0}hours ahead of yours",
    'behind' => "UAE time is :count\u{00A0}hour behind yours|UAE time is :count\u{00A0}hours behind yours",
];
