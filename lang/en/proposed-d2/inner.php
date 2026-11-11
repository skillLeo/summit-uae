<?php

/*
| Design 2 inner-page kit (<x-d2.inner.*>: head, prose, layout, toc, progress, cta-card, related):
| interface text the kit renders itself. PROPOSED COPY, not in the approved DOCX files: needs client
| approval in EN/AR/RU (docs/proposed-copy.md). Read as d2p('inner.<key>').
*/

return [
    // Table of contents: heading of the desktop list, the mobile trigger bar and the TOC sheet title.
    'toc_title' => 'On this page',
    // Accessible name of the list of related links when the block has no visible heading.
    'related_label' => 'Related pages',
    // Print button on long reading pages (legal pages); shown only when JavaScript can print.
    'print' => 'Print this page',
];
