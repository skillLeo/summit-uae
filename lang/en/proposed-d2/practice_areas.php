<?php

/*
| Design 2 practice-areas hub P02 (resources/views/designs/d2/inner/practice-areas.blade.php, upgrade spec
| §12 D, §7.4): interface text the DOCX files do not cover. PROPOSED COPY, needs client approval in EN/AR/RU
| (docs/proposed-copy.md). Read as d2p('practice_areas.<key>'); the plural with
| trans_choice('proposed.d2.practice_areas.result', n).
| The audience chip labels are the approved Who We Help titles (P01); the audience → service mapping in
| config/audience_map.php ('hero') is proposed. Card titles, summaries, situations and link labels are approved.
*/

return [
    // Visible label in front of the audience filter chips (also the chip group's accessible name).
    'filter_label' => 'Show services for',
    // First filter chip: every practice area.
    'filter_all' => 'All',
    // Announced (politely) after a filter chip is chosen.
    'result' => ':count practice area shown|:count practice areas shown',
];
