<?php

/*
| Design 2 live office-hours status: PROPOSED copy (Arabic draft). Same keys as lang/en/proposed-d2/hours.php.
| The times follow the approved homepage line "من 9 صباحًا إلى 6 مساءً" (12-hour clock). The digits follow the
| Arabic digits setting (App\Design\D2\Digits; the script reads <html data-digits>): 0-9 by default, ٠-٩ when
| the setting is "eastern". Plurals: zero|one|two|few (3 to 10)|many (11 to 99)|other (100 and up, and fractions
| such as 1.5). No-break spaces (\u{00A0}) keep "6 مساءً" and "بـ 4 ساعات" on one line.
*/

return [
    'open' => 'مفتوح الآن، يُغلق :time',
    'closed' => 'مغلق الآن، يفتح يوم :day :time',

    // Opens later today / tomorrow (only while the visitor's calendar day is the UAE's; see the English file).
    'closed_today' => 'مغلق الآن، يفتح اليوم :time',
    'closed_tomorrow' => 'مغلق الآن، يفتح غدًا :time',

    'days' => [
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
        7 => 'الأحد',
    ],

    'time' => [
        'am' => "الساعة :hour\u{00A0}صباحًا",
        'pm' => "الساعة :hour\u{00A0}مساءً",
        'am_minutes' => "الساعة :hour::minute\u{00A0}صباحًا",
        'pm_minutes' => "الساعة :hour::minute\u{00A0}مساءً",
    ],

    'ahead' => "توقيت الإمارات مطابق لتوقيتك|توقيت الإمارات متقدّم على توقيتك بساعة واحدة|توقيت الإمارات متقدّم على توقيتك بساعتين|توقيت الإمارات متقدّم على توقيتك بـ\u{00A0}:count\u{00A0}ساعات|توقيت الإمارات متقدّم على توقيتك بـ\u{00A0}:count\u{00A0}ساعة|توقيت الإمارات متقدّم على توقيتك بـ\u{00A0}:count\u{00A0}ساعة",
    'behind' => "توقيت الإمارات مطابق لتوقيتك|توقيت الإمارات متأخر عن توقيتك بساعة واحدة|توقيت الإمارات متأخر عن توقيتك بساعتين|توقيت الإمارات متأخر عن توقيتك بـ\u{00A0}:count\u{00A0}ساعات|توقيت الإمارات متأخر عن توقيتك بـ\u{00A0}:count\u{00A0}ساعة|توقيت الإمارات متأخر عن توقيتك بـ\u{00A0}:count\u{00A0}ساعة",
];
