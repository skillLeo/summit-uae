<?php

/*
| Styleguide annotations for the Design 2 home hero (resources/views/designs/d2/styleguide/hero.blade.php).
| Internal, not site copy: read with __('styleguide_d2/hero.key'); Arabic and Russian fall back to English.
*/

return [
    'title' => 'Hero',
    'intro' => 'The home hero: the document stage (one sample document on a file of two blank sheets, its callouts, the legend, the pager) and the tool card. The specimens run the same scripts as the home page, in their final state: the entrance plays only on the home page.',

    'stage' => 'Document stage',
    'stage_final' => 'Final state in the page direction (D15): one document at rotation 0 on two blank offset sheets, so nothing readable is ever cut off at a sheet\'s edge. Live: the segments, previous / next, ← / → while focus is in the pager (Home / End); a pointer tilt of at most 2° with a mouse. 620×440 design px from 768 px (620×540 on phones), scaled down to the column by CSS alone, aspect ratio reserved: CLS 0.',
    'stage_other' => 'Final state in the other direction (Arabic specimen on English and Russian pages, English on the Arabic page): the sheets\' offsets and rotations, the callout sides, the leaders, the pager and the tilt\'s x axis mirror; the documents are laid out in their own language and the callouts follow their measured fields.',
    'stage_phone' => 'Phone layout (below 768 px): the document larger, a numbered 24 px marker beside the paper in place of each label, and the legend under the document (the same numbers). Two markers on the same side keep 12 px of air on a 360 px phone; when they would not, they spread apart and their leaders take a short step outside the paper.',

    'deal' => 'Motion',
    'deal_items' => [
        'Entrance, once (home page only): armed before the first paint by the boot script (html.hero-armed: motion allowed, no #section link); a 2.5 s timeout shows the final state if the hero script never runs. The two sheets and then the document come in from the inline-end side on the paper spring (110 ms apart, from 350 ms after the first paint) and settle; the callouts draw in once the document rests. The H1, paragraphs and CTAs never move.',
        'Switch (§6): the old callouts fade out in 120 ms; the next paper slides in from the inline-end side (from the inline-start side when going back) with a 4° turn, both settling to 0 on the spring {stiffness 170, damping 20}; its shadow layers move with it (the lifted shadow fades into the resting one) and the old paper fades out beneath it. Once the paper has settled the leaders draw in with stroke-dashoffset (400 ms), the dots scale in and the labels fade in, 90 ms apart.',
        'Transform and opacity only, on a fixed stage: no layout shift. Pointer tilt: fine pointer, from 768 px, at most 2° (rotateX and rotateY together), spring-followed, back to rest on pointerleave.',
        'Reduced motion or no JavaScript: the composed final state at the first paint, callouts included (printed by the server); a switch is instant. After a bfcache restore everything is final. A phone whose stage is below the fold plays the entrance when 20 % of it scrolls into view.',
    ],

    'callouts' => 'Callouts (D16)',
    'callouts_note' => 'Laid out on the server (App\\Design\\D2\\HeroStage) from every document\'s measured key fields (resources/js/d2/hero/callout-layout.json, by scripts/d2/callout-layout.mjs, which also checks the result in every language and width): the dot sits at the edge of its field, in the empty paper beside it; one straight leader runs out through the page margin; the label sits in the clear margin beside the file, never over a paper edge, its first text line on the leader (the upper one of two close callouts grows upwards instead). Labels are the Small role (15/500, ink) on screen at every canvas scale; start-side labels near the top keep room for the Russian H1. Everything is in inline coordinates: RTL mirrors.',
    'callouts_contract' => 'Employment contract: two labels on the start side, one on the end side.',
    'callouts_notice' => 'Hearing notice: the case number and the instructions on the start side, the hearing date on the end side.',
    'parts' => 'Parts: a 1 px bronze leader with an 8 px dot (6 px on phones) and its 2 px paper ring, a label (Small, 500, ink, no box), a numbered 24 px marker with a 13 px figure (phones).',

    'pager' => 'Pager (D17)',
    'pager_first' => 'First document: one progress segment filled; previous disabled (aria-disabled, 40 %, focus stays). No counter, no dots, no autoplay, no wrap-around.',
    'pager_middle' => 'A document in the middle: segments filled up to it; both steps enabled. The name is a polite live region.',
    'pager_last' => 'Last document: every segment filled, next disabled.',
    'pager_focus' => 'Forced focus: the next button, then the current segment (44 px tall targets, 48 px on phones). The pager is one Tab stop: a roving tabindex on the segments, ← / → (mirrored in RTL), Home and End bring another document up and move focus to its segment; previous / next (40 px, with a 44 px hit area) are for pointers and assistive technology (tabindex -1).',

    'tool' => 'Tool card "Start your matter"',
    'tool_note' => 'A card (white, 1 px --line edge, radius 10, no shadow; 16 px padding on phones, the card padding from 768 px). A GET form to the consultation form without JavaScript (?audience=…&situation=S06-2#consultation-form); with it, Continue (Ink, no arrow) calls prefill({service, situation, audience}). The result shows the empty well or the chosen service’s documents, one at a time (documents are never clamped, so the card grows only after a choice). Laid out by its own width (container queries): one column, then the situation and Continue side by side (420 px), then the form and the documents side by side (52rem: the home hero from 1024 px, where the card spans the container, and from 1280 px all 12 columns under the copy and the stage).',
    'tool_individual' => 'Individual (default): no situation chosen yet, the empty well.',
    'tool_business' => 'Business: the select lists only the services config(\'audience_map.hero\') maps to it.',
    'tool_investor' => 'Investor.',
    'tool_situation' => 'A situation chosen (S06-2): "Documents usually needed: 4" (trans_choice) and the four approved documents of the service, each in full (never clamped) and without icons.',
    'tool_unmapped' => 'No mapping for the audience: every situation, grouped by service.',
];
