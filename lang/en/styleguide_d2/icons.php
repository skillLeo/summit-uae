<?php

// Internal styleguide annotations for the Design 2.1 "Icons" area (noindex utility page). Not public website copy:
// Arabic and Russian pages fall back to these English labels. Demo content on the page itself is approved copy
// (ui(), setting(), content()). Contract: docs/d2.1/phase-1/components.md §3 (icons) and §4 (the WhatsApp logo).
return [
    'title' => 'Icons',
    'intro' => 'One outline set on a 24-unit grid: 20 px, a 1.5 px pen at every size, round caps and joins, ink-2 when it stands alone, the label\'s colour inside a control. Functional only (brief §3.6 rule 5): every icon prints data-icon="name" for the design lint. The galleries that show icons out of their context carry data-lint-specimen: the lint checks each name there, not its place.',

    'set' => 'The functional set',
    'set_note' => ':count glyphs the lint accepts (App\Design\D2\Icons::ALLOWED; it also holds six alias names: the four allowed arrows below, cross and email): search, menu, close, the chevrons, external link, phone, email, clock (the contact block only), check and cross, upload, info, warning, error, and the stepper\'s minus / plus and the date field\'s calendar.',
    'mirrors' => 'mirrors in RTL',

    'arrows' => 'The allowed arrows have their own names',
    'arrows_note' => 'No arrows in buttons, links or card corners (rule 3): <x-d2.ui.button> drops icon="arrow", "arrow-start", "chevron" and "chevron-start". The exceptions are explicit names that draw an existing glyph: the carousel\'s previous and next, the form\'s Back and Next.',
    'alias_of' => 'draws :glyph',
    'decorative' => 'Decorative glyphs still in the set (never drawn here; the areas remove their last uses, D04): :names.',

    'sizes' => 'One pen at every size',
    'sizes_note' => 'vector-effect: non-scaling-stroke keeps the line at 1.5 px from 16 to 48 px; shapes and gaps scale, the pen does not.',
    'size_row' => ':name at 16, 20, 24 and 48 px',

    'rtl' => 'Direction',
    'rtl_note' => 'Chevrons, external and the explicit arrows point along the reading direction and mirror in RTL. Objects never mirror: phone, check, clock, search.',
    'rtl_page' => 'page direction',
    'rtl_forced' => 'inside dir="rtl"',
    'rtl_ltr' => 'inside dir="ltr" (an LTR island keeps the LTR form)',

    'context' => 'In context',
    'context_note' => 'Inside a control an icon takes the label\'s colour; phone and e-mail are isolated with <bdi>.',
    'in_buttons' => 'functional icons in a secondary and a text button',
    'in_links' => 'a link to another website: the ↗ mark is an SVG after the text, never a character',
    'inline' => 'icon--inline: an icon inside running text, centred on the x-height (clock: contact block only)',

    'whatsapp' => 'The official WhatsApp logo',
    'whatsapp_note' => 'Meta\'s own files only, shown UNCHANGED in an <img> (never redrawn, recoloured, re-pathed or merged into the sprite): the variant for light backgrounds on light surfaces, the one for dark backgrounds on dark surfaces; the innermost surface decides. Every WhatsApp control renders through <x-d2.ui.icon name="whatsapp">, so all of them switch at once. Each mark carries data-brand="whatsapp".',
    'whatsapp_light' => 'light-surface file',
    'whatsapp_dark' => 'dark-surface file',
    'whatsapp_missing' => 'not in resources/brand/whatsapp/ yet: the grey dashed placeholder (data-placeholder="whatsapp-logo") stands in',
    'whatsapp_inline' => ':file, shown inline (run node scripts/d2/whatsapp-logo.mjs to publish it)',
    'whatsapp_published' => ':file, published unchanged to public/d2/brand/whatsapp/',
    'wa_light' => 'on a light surface: 20, 24 and 48 px',
    'wa_buttons' => 'the WhatsApp button (white, ink label) and an icon button',
    'wa_dark' => 'on a dark surface',
    'wa_dark_buttons' => 'the night button takes the dark-surface file; the white WhatsApp button keeps the light one',
    'whatsapp_drop' => 'To switch to the official logo: copy Meta\'s two files, unchanged, to resources/brand/whatsapp/whatsapp-logo-light.svg (for light backgrounds) and whatsapp-logo-dark.svg (for dark backgrounds); .png or .webp work too. Every WhatsApp control switches at once; then run node scripts/d2/whatsapp-logo.mjs to publish cacheable copies.',
];
