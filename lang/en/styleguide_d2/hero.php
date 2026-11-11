<?php

/*
| Styleguide annotations for the Design 2 home hero (resources/views/designs/d2/styleguide/hero.blade.php).
| Internal, not site copy: read with __('styleguide_d2/hero.key'); Arabic and Russian fall back to English.
*/

return [
    'title' => 'Hero',
    'intro' => 'The home hero: the document stage (a pile of the six sample documents, the one on top with its callouts, the legend, the pager with its pause / play toggle) and the tool card. The specimens run the same scripts as the home page, in their final state: the entrance and the autoplay play only on the home page.',

    'stage' => 'Document stage',
    'stage_final' => 'Final state in the page direction (D15, the owner\'s option 3): all six sample documents in one pile, each in its own hand-placed pose (an offset and a few degrees, App\Design\D2\HeroStage::POSE) and depth; the one on top sits straight and shows its content, every sheet behind it shows only its paper (tint, texture, edge, the cheque\'s waves, memo and staple, the phone\'s frame), so nothing readable is ever cut off at a sheet\'s edge. Live: the segments, previous / next, ← / → while focus is in the pager (wrapping; Home / End), a click on a back sheet; with a mouse the pile follows the pointer by depth (up to 12 px, a 2° tilt), breathes open, and a hovered or focused segment\'s sheet peeks up. 620×510 design px from 768 px (620×650 on phones), scaled down to the column by CSS alone, aspect ratio reserved: CLS 0.',
    'stage_other' => 'Final state in the other direction (Arabic specimen on English and Russian pages, English on the Arabic page): the poses, the callout side (always the inline-end side of the pile, away from the H1), the leaders, the pager, the timer\'s fill and the tilt\'s x axis mirror; the documents are laid out in their own language and the callouts follow their measured fields.',
    'stage_phone' => 'Phone layout (below 768 px): a larger pile, a numbered 24 px marker beside the pile in place of each label, and the legend under the document (the same numbers). Markers keep 12 px of air on a 320 px canvas; when they would not, they spread apart and their leaders take a short step outside the pile. No pointer effects; the same switch.',

    'deal' => 'Motion',
    'deal_items' => [
        'Entrance, once (home page only): armed before the first paint by the boot script (html.hero-armed: motion allowed, no #section link); a 2.5 s timeout shows the final state if the hero script never runs. The six sheets are dealt onto the pile from the bottom up, from the inline-end side, on the paper spring (80 ms apart from 350 ms after the first paint, the whole deal about 1.2 s); the document on top lands last and its callouts draw in once it rests. The H1, paragraphs and CTAs never move.',
        'Switch (§6): the old top\'s callouts (120 ms) and ink (160 ms) fade out as it slides back to its own place in the pile on the spring {stiffness 170, damping 20}; the chosen sheet lifts out of its place (240 ms: towards the inline-end side, a little up and straighter, scale 1.035, its shadow growing), then, now the top of the pile, travels onto the top on the same spring, turns to 0 and settles, its ink fading in as it arrives. Once the paper has settled the leaders draw in with stroke-dashoffset (400 ms), the dots scale in and the labels fade in, 90 ms apart.',
        'Autoplay (the owner\'s decision; home page only): 2.8 s after the callouts are drawn the next document comes up (wrapping); the segment shown fills over that time (a scaleX). It pauses while the pointer is over the stage or the pager, while keyboard focus is in the hero, while the hero is off screen and while the tab is hidden; any manual choice stops it for good; the pause / play toggle (40 px, a Tab stop, named after what a press does) starts and stops it. Never under reduced motion (the toggle then reads Play), never on these specimens, never without JavaScript.',
        'Transform and opacity only, on a fixed stage: no layout shift. Pointer: fine pointer, from 768 px, motion allowed; every value spring-followed, back to rest on pointerleave. Reduced motion or no JavaScript: the composed pile at the first paint, callouts included (printed by the server); a switch is instant. After a bfcache restore everything is final. A phone whose stage is below the fold plays the entrance when a fifth of it scrolls into view above the tab bar.',
    ],

    'callouts' => 'Callouts (D16)',
    'callouts_note' => 'Laid out on the server (App\Design\D2\HeroStage) from every document\'s measured key fields and label heights (resources/js/d2/hero/callout-layout.json, by scripts/d2/callout-layout.mjs, which also checks the result in every language and width): the dot sits at the inline-end edge of its field, in the empty paper beside it; one leader runs out through the page margin and over the blank back sheets; the label sits in the clear margin on the inline-end side of the pile, never over any sheet, its first, middle or last text line on the leader, 12 px from its neighbours (a leader takes a short step beside the pile when two would come closer). Labels are the Small role (15/500, ink) on screen at every canvas scale. Everything is in inline coordinates: RTL mirrors.',
    'callouts_contract' => 'Employment contract on top of the pile: the parties, the wage and the notice period, all three labels beside the pile.',
    'callouts_notice' => 'Hearing notice on top: the case number\'s dot and leader sit in the gap under its row (the department follows it on the same line), the hearing date and the instructions beside them.',
    'parts' => 'Parts: a 1 px bronze leader with an 8 px dot (6 px on phones) and its 2 px paper ring, a label (Small, 500, ink, no box), a numbered 24 px marker with a 13 px figure (phones).',

    'pager' => 'Pager (D17)',
    'pager_first' => 'First document: one progress segment filled. Previous and next are always enabled: the pile wraps around (the owner\'s autoplay amends D17). No counter, no dots.',
    'pager_middle' => 'A document in the middle: segments filled up to it. The name is a polite live region (off while the samples play by themselves). On the home page the segment shown fills over the dwell while the samples play.',
    'pager_last' => 'Last document: every segment filled; next brings up the first one.',
    'pager_focus' => 'Forced focus: the next button, then the current segment (44 px tall targets, 48 px on phones), then the pause / play toggle. Two Tab stops: the segments (a roving tabindex; ← / → wrap, mirrored in RTL; Home and End bring another document up and move focus to its segment) and the toggle (WCAG 2.2.2). Previous / next (40 px, with a 44 px hit area) are for pointers and assistive technology (tabindex -1).',

    'tool' => 'Tool card "Start your matter"',
    'tool_note' => 'A card (white, 1 px --line edge, radius 10, no shadow; 16 px padding on phones, the card padding from 768 px). A GET form to the consultation form without JavaScript (?audience=…&situation=S06-2#consultation-form); with it, Continue (Ink, no arrow) calls prefill({service, situation, audience}). The result shows the empty well or the chosen service’s documents, one at a time (documents are never clamped, so the card grows only after a choice). Laid out by its own width (container queries): one column, then the situation and Continue side by side (420 px), then the form and the documents side by side (52rem: the home hero from 1024 px, where the card spans the container, and from 1280 px all 12 columns under the copy and the stage).',
    'tool_individual' => 'Individual (default): no situation chosen yet, the empty well.',
    'tool_business' => 'Business: the select lists only the services config(\'audience_map.hero\') maps to it.',
    'tool_investor' => 'Investor.',
    'tool_situation' => 'A situation chosen (S06-2): "Documents usually needed: 4" (trans_choice) and the four approved documents of the service, each in full (never clamped) and without icons.',
    'tool_unmapped' => 'No mapping for the audience: every situation, grouped by service.',
];
