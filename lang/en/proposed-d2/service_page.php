<?php

/*
| Design 2 service pages S01–S11 (resources/views/designs/d2/inner/service.blade.php, upgrade spec §12 D,
| §7.4): interface text the DOCX files do not cover. PROPOSED COPY, needs client approval in EN/AR/RU
| (docs/proposed-copy.md). Read as d2p('service_page.<key>').
| Every heading, paragraph, list, FAQ, link label, document, first step, mistake, situation, the CTA labels,
| the phone, e-mail, hours sentence and WhatsApp draft are approved copy (content(), ui(), setting()); the
| breadcrumb label and "On this page" belong to the inner kit (d2p('inner.…')).
*/

return [
    // Footer of each "This service may be relevant if:" card: opens that situation's starting file in the
    // home Matter Navigator (the Navigator's own button says the same).
    // H2 (and "On this page" entry) of the related Matter Navigator situations; the approved lead-in
    // ui('service.situations_intro') ("This service may be relevant if:") stays as the line under it.
    'situations_heading' => 'Start with Your Situation',
    'situation_open' => 'Show the starting file',
];
