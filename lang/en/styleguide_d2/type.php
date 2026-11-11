<?php

// Internal annotations for the Design 2 styleguide "Type" area (noindex utility page). Not public website copy:
// Arabic and Russian pages fall back to these English labels. Every sample on the page is approved copy
// (content(), ui(), setting()). Contract: docs/d2.1/phase-1/tokens.md §4 and §5.
return [
    'title' => 'Type',
    'intro' => 'Onest for English and Russian, IBM Plex Sans Arabic for Arabic (the free pair B of brief §3.2, from the unmodified Fontsource files). Weights 400, 500 and 600 only; nothing is smaller than 13 px. Monospace, serif and naskh live only inside the sample documents.',

    'roles' => 'Roles',
    'roles_note' => 'Desktop sizes from 768 px, mobile below (stepped, never fluid, so every size is one of the scale). Headings balance their lines, paragraphs wrap "pretty" and stop at 64ch. Arabic: no tracking, body 1 px larger with line height 1.75, taller heading line heights, no italics. Russian: paragraphs hyphenate.',
    'spec' => [
        't-display' => ['Display (h1)', '56 / 40, line height 1.05, tracking −0.025em, weight 600', 'One per page. Arabic: line height 1.3, tracking 0.'],
        't-h2' => ['H2 (h2)', '36 / 28, 1.15, −0.02em, 600', 'Arabic: 1.35, tracking 0.'],
        't-h3' => ['H3 (h3, h4)', '22 / 20, 1.3, −0.01em, 600', 'Card and panel titles too (the legacy .t-h4). Arabic: 1.45.'],
        't-minor' => ['Minor heading (h5, h6)', '16, 1.4, 600', 'Group headings: the footer groups (D47) and list groups (D29), white on dark in the footer.'],
        't-lead' => ['Lead', '19 / 17, 1.55, 400', 'Section intros. Arabic: 1.75.'],
        't-body' => ['Body (body, p)', '17 / 16, 1.6, 400', 'Arabic: 18 / 17, 1.75.'],
        't-ui' => ['UI label', '15, 1.4, 500', 'Buttons, nav, field labels, chips. The legacy .t-eyebrow is this style in --ink-2.'],
        't-small' => ['Small (small)', '15 / 14, 1.5, 400', 'Help text, meta. The legacy .t-meta is this style in --ink-3.'],
        't-caption' => ['Caption', '13, 1.45, 400', 'The smallest text anywhere.'],
        't-tabular' => ['Tabular figures', 'tabular-nums lining-nums', 'Calculators, tables, prices, reference numbers (.u-tabular as a utility; the legacy .t-num).'],
    ],
    'legacy' => 'Design 2.0 classes still work as aliases: .t-h1 = Display, .t-h4 = H3, .t-body-l = Lead, .t-meta = Small in ink-3, .t-eyebrow = UI label in ink-2 (no monospace, no capitals, no tracking), .t-num = tabular figures, .t-mono = the sans outside the documents.',

    'links' => 'Links',
    'links_note' => 'Body links: ink, underline 1 px at a 3 px offset, 2 px on hover. Nav links: ink-2, ink on hover and when current.',
    'link_body' => 'running text',
    'link_nav' => 'navigation',

    'families' => 'Families and weights',
    'families_note' => 'One token, --font-sans, draws the interface; it resolves per language (lang="ar" puts the Arabic family first). Latin runs inside Arabic sentences (digits, codes, e-mail) come from IBM Plex Sans Arabic\'s own Latin, so a line keeps one design; text marked lang="en" or lang="ru" takes Onest. Graphik later: drop the files in resources/fonts/graphik/, run the font script, change --font-sans to var(--font-a).',
    'family' => [
        'sans' => 'D2 Sans, Onest (variable, declared 400–600), Latin + Cyrillic: English and Russian',
        'arabic' => 'D2 Sans Arabic, IBM Plex Sans Arabic 400 / 500 / 600, with its own Latin for digits and codes',
    ],
    'doc_families' => 'Sample documents only',
    'doc_families_note' => 'Typed fields, paper and naskh inside the illustrations (data-illustration). Never in the interface: there the legacy --font-mono, --font-doc and --font-doc-arabic are the sans.',
    'doc_family' => [
        'mono' => 'D2 Mono, IBM Plex Mono 400 / 500 (--font-doc-mono)',
        'serif' => 'D2 Serif, Source Serif 4 400–600 (--font-doc-serif)',
        'naskh' => 'D2 Naskh, Noto Naskh Arabic 400–600 (--font-doc-naskh)',
    ],

    'scripts' => 'Three scripts, one system',
    'scripts_note' => 'The same roles in English, Russian and Arabic, whatever this page\'s language: each sample carries its own lang, and the tokens resolve for it.',
    'script' => [
        'en' => 'Latin: Onest, H2 36/41 (28/32 on phones), tracking −0.02em, body 17/27 (16/26)',
        'ru' => 'Cyrillic: Onest, the same metrics; paragraphs hyphenate',
        'ar' => 'Arabic: IBM Plex Sans Arabic, H2 36/49 (28/38), tracking 0, body 18/31.5 (17/30), no italics',
    ],
];
