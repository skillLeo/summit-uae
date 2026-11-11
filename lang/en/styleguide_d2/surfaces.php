<?php

// Internal styleguide annotations for the Design 2.1 "Surfaces" area (noindex utility page). Not public website
// copy: Arabic and Russian pages fall back to these English labels. Demo content on the page itself is approved
// copy (ui(), setting(), content()). Contract: docs/d2.1/phase-1/components.md §2 and §5.
return [
    'title' => 'Surfaces',
    'intro' => 'Cards, accordion, tabs, the overlays (toast, tooltip), skeleton and callout in every state, drawn with approved copy (brief §3.4, §3.5).',

    'card' => 'Card',
    'card_note' => 'Only for interactive containers (forms, calculators, result panels) and clickable listings. White, 1 px --line, radius 10, padding 24 / 32, no shadow. A clickable card is one link with one accessible name (the title, stretched over the card). Hover and keyboard focus: the edge turns ink and the title underlines; nothing moves.',
    'card_default' => 'clickable listing card, at rest',
    'card_hover' => 'hover: ink edge, underlined title (no lift, no shadow)',
    'card_focus' => 'keyboard focus on its link: the ring around the card',
    'card_link' => 'with a nested button (it stays its own target)',
    'card_as_link' => 'as="a": the card itself is the link (nothing interactive inside)',
    'card_static' => 'a container card (a form or a panel): no link, no hover',
    'card_sunken' => 'tone sunken: the --band panel',
    'card_padding' => 'padding sm (16 / 20), md (24 / 32), lg (32)',
    'card_night_default' => 'tone night on a dark section',
    'card_night_hover' => 'tone night, hover',
    'card_night_focus' => 'tone night, keyboard focus (light gold ring)',

    'accordion' => 'Accordion',
    'acc_note' => '<details>: works without JavaScript. Questions in the H3 role, a 20 px chevron-down that turns on open, the height springs open (240 ms; instant under reduced motion).',
    'acc_states' => 'closed, hover, keyboard focus, open',
    'acc_card' => 'variant card, exclusive group (name): one item open at a time',
    'acc_flush' => 'variant flush (inside a card or a column)',
    'acc_deeplink' => 'Deep link: open the third question',
    'acc_deeplink_note' => 'a link to the item\'s id: accordion-hash.js opens it and scrolls it into view',
    'acc_night' => 'on dark',

    'tabs' => 'Tabs',
    'tabs_note' => 'Underline tabs to switch between tools or views: labels 15/500 in ink-2, the selected one ink over a 2 px ink indicator that slides (240 ms).',
    'tabs_overflow' => 'the 12 approved FAQ categories: horizontal scroll with faded edges, arrow keys (reversed in RTL), Home and End',
    'tabs_states' => 'tab states: default, hover, keyboard focus, selected',
    'tabs_night' => 'on dark: a white indicator',

    'overlays' => 'Overlays: toast and tooltip',
    'overlays_note' => 'Every overlay (tooltip, popover, toast, modal, sheet) carries data-overlay, the --radius-overlay (16) and the one overlay shadow; nothing else on the page has a shadow.',
    'toast_static' => 'toasts at rest (neutral, success, danger): the words carry the tone, no coloured dot',
    'toast_live' => 'live: bottom centre, auto-hide after 4 s, pauses on hover, at most two visible',
    'toast_success' => 'Show a success toast',
    'toast_danger' => 'Show an error toast',
    'toast_neutral' => 'Show a neutral toast',
    'tip_live' => 'live tooltips: on an icon button (Esc hides it) and as extra information on a labelled control',
    'tip_static' => 'a tooltip at rest, and the attribute variant (data-tooltip)',

    'skeleton' => 'Skeleton',
    'skeleton_note' => 'Only for real loading: --band bones that breathe (opacity), no gradient sweep; still under reduced motion.',
    'skel_text' => 'text lines',
    'skel_card' => 'card',
    'skel_row' => 'rows',
    'skel_night' => 'on dark',

    'callout' => 'Callout',
    'callout_note' => 'A panel (radius 10) for scope notes, disclaimers and form notices: the tone\'s icon, an optional title, the text in ink-2. Info is the neutral band panel; success, warning and danger take their badge pair.',
    'callout_info' => 'info, with a title: the FAQ scope note',
    'callout_warning' => 'warning',
    'callout_success' => 'success',
    'callout_danger' => 'danger (the error icon)',
    'callout_sm' => 'size sm',
    'callout_night' => 'on dark',
];
