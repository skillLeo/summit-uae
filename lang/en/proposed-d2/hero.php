<?php

/*
| Design 2 home hero "Case File + Start your matter" (upgrade spec §4.2, §12 B1–B5): PROPOSED COPY, not in
| the approved DOCX files. Needs client approval in EN/AR/RU (docs/proposed-copy.md). Read as
| d2p('hero.<key>'); plurals with trans_choice('proposed.d2.hero.<key>', $n). The H1, the two hero
| paragraphs, the CTA labels, the WhatsApp draft and the office hours are approved copy (content()).
*/

return [
    // Document stage: accessible name of the illustration (role="img") and the caption under the pager: one short
    // sentence on one line, also on a 360 px phone, without middle dots (Design 2.1 D02, D17). The eyebrow above the
    // H1 was deleted (D01: not in the DOCX).
    'stage_label' => 'Illustration: generic sample documents of the kinds Summit commonly reviews, stacked like a case file. They are not real documents.',
    'caption' => 'Samples of documents Summit commonly reviews',
    // Name of the list that repeats the highlighted fields of the sample shown (the legend under the document on
    // phones, numbered like its markers).
    'callouts_label' => 'Highlighted on this sample',

    // Pager under the stage (D17): the group of six progress segments, and the previous / next buttons. No counter.
    // The segments and the name are the sample documents' own short names (d2p('docs.name.<type>')).
    'prev' => 'Previous document',
    'next' => 'Next document',
    'segments_label' => 'Choose a sample document',
    // The owner's autoplay (the samples change every few seconds; WCAG 2.2.2): the toggle beside previous / next.
    // Its name says what pressing it does: pause while the samples play, play while they are stopped.
    'pause' => 'Pause the samples',
    'play' => 'Play the samples',

    // Tool card "Start your matter" (spec §12 B1).
    'tool' => [
        'title' => 'Start your matter',
        'audience_label' => 'Who is this for?',
        'audience' => [
            'individual' => 'Individual',
            'business' => 'Business',
            'investor' => 'Investor',
        ],
        'situation_label' => 'Your situation',
        'situation_placeholder' => 'Choose your situation',
        // Result row; :count is the number of documents listed for the chosen service. Before a situation
        // is chosen the row reads picks_label above the quick picks (D19: the most-chosen situations as chips).
        // Only when the chosen audience lists none of the quick picks does it read documents_label, with
        // documents_empty under it.
        'documents' => '{1} Document usually needed: :count|[2,*] Documents usually needed: :count',
        'picks_label' => 'Common situations',
        'documents_label' => 'Documents usually needed',
        'documents_empty' => 'Choose your situation to see them here.',
        'continue' => 'Continue',
    ],
];
