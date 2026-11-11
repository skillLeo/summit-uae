<?php

// Design 2 proposed copy: home practice areas (upgrade spec §12 C2). Read as d2p('services.<key>') or, for
// the plurals, trans_choice('proposed.d2.services.<key>', $n); needs client approval (docs/proposed-copy.md).
// Card titles, summaries, "Explore This Service", the heading, intro and closing are approved copy (Design 2.1 D22
// removed the proposed "N documents usually needed" line); the audience chip labels are
// the approved Who We Help titles (the audience → service mapping in config/audience_map.php is proposed).
return [
    // Accessible name of the audience filter chips (visually hidden).
    'filter_label' => 'Filter practice areas by client',
    // First filter chip: every practice area.
    'filter_all' => 'All',
    // Announced (politely) after a filter chip is chosen.
    'result' => ':count practice area shown|:count practice areas shown',
];
