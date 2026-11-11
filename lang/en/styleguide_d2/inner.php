<?php

// Internal styleguide annotations for the Design 2 "Inner pages" area (the inner-page kit used by every
// Phase D page). Not public website copy: Arabic and Russian pages fall back to these English labels. Demo
// content on the page itself is approved copy (content(), ui(), setting()) or the proposed keys of
// lang/{locale}/proposed-d2/inner.php.
return [
    'title' => 'Inner pages',
    'intro' => 'The kit every inner page is built from: <x-d2.inner.head>, prose, layout, toc, progress, cta-card and related. Live on /legal-disclaimer/, /privacy-policy/ and /terms-and-conditions/; the generic template falls back to the same parts. Design 2.1: no icon object, eyebrow, large-title collapse, arrow or decorative glyph; headings take the role of their level.',

    'head' => 'Head: <x-d2.inner.head>',
    'head_note' => 'Breadcrumbs (Small, ink-3; long labels wrap, the current page is never cut; 44 / 48 px link targets), the approved H1 (Display, 56 / 40: a title whose longest word cannot fit a phone may hyphenate, never shrink below the scale), a meta slot (the last-updated line), the approved intro (Lead, ink-2) and an actions slot (the hero primary is the page’s one gold action). size="document" keeps the text to the 720 px reading measure; an aside slot adds the 352 px side column from 1280 px, on the same tracks as the reading layout below it.',
    'head_desktop' => 'Document size with the meta slot (legal pages)',
    'head_actions' => 'Display size with the actions slot (service hero: gold hero primary, WhatsApp)',

    'prose' => 'Prose: <x-d2.inner.prose>',
    'prose_note' => 'Approved DOCX structures in their stored order: section headings (the role of their level, numbering printed verbatim), paragraphs in ink, lists (ink-3 markers), numbered steps (1, 2, 3 in ink-3 tabular figures), sub-sections, notes as callouts, Q&A as a flush accordion, link rows (no glyph; the ↗ of a link to another website) and the e-mail and telephone as contact channels (<x-d2.inner.channels>: the glyph, then the value left to right; the rows of every CTA panel and the footer). A section with variant "note" is drawn as a callout; a page’s closing scope note is that callout with class inner-scope, one layout on every page (the icon beside the H3-role heading, the text from the panel’s padding; container-wide notes, inner-scope--wide, put the text on the page grid’s column line from 1024 px); variant "panel" is the band panel of "Mistakes to Avoid", with a cross per item (a list with marker "cross"; "check" for useful steps).',
    'prose_label' => 'Every block type (section headings at level 4 here)',
    'prose_dir' => 'Other direction (approved copy of the other script)',

    'toc' => 'Table of contents: <x-d2.inner.toc>',
    'toc_note' => 'From 1280 px a sticky list in the side column (scrolls inside itself when taller than the window); below, a sticky white "On this page" bar under the header that opens the TOC sheet. Scroll-spy (modules/toc.js) marks the section being read with aria-current="true" (a 2 px ink bar, ink, semibold). Without JavaScript the bar is the plain list.',
    'toc_desktop' => 'Desktop list, third section current',
    'toc_sheet' => 'Mobile bar and the TOC sheet (static specimen; live on the legal pages below 1280 px)',
    'toc_dir' => 'Other direction: the current bar sits on the inline-start edge',
    'toc_rail' => 'Side rail with an aside (service pages, from 1280 px): the list above the CTA, here an action group under a hairline, not a card in the column (approved heading, here a label: the page’s final panel carries it as its H2, so heading navigation lists it once; and the actions at the rail’s full width, the standard 44 px buttons, one line in every language; the telephone and e-mail are in the final panel). The rail is capped to the window (here to a fixed height) and the list scrolls inside it, the current link kept in view, so the actions always show in full. On the Knowledge Centre below 768 px each guide starts as its card (category and title) with the proposed "Read the full guide" disclosure; a link to the guide or to an anchor inside it opens it first.',

    'progress' => 'Reading progress: <x-d2.inner.progress>',
    'progress_note' => '3 px ink bar fixed under the header, scaleX from the inline-start edge: 0 when the article’s top reaches the header, 1 when its end reaches the bottom of the window. Decorative (aria-hidden), hidden without JavaScript and in print.',
    'progress_ltr' => 'Left to right, 40 %',
    'progress_rtl' => 'Right to left, 40 %',

    'cta' => 'Side CTA card: <x-d2.inner.cta-card>',
    'cta_note' => 'Sticky in the layout’s side column from 1280 px. Request (ink: gold is the page hero’s primary only), WhatsApp with the approved draft (opens, never sends), telephone and e-mail as the kit’s contact channels (glyph + number or address, normal figures), the approved hours sentence with the live status (its dot centred on the first line), the approved note. White, 1 px line, radius 10, no shadow. The request link pre-fills the wizard (Prefill::url) and follows the contact release rule.',
    'cta_full' => 'With an approved heading, text, note, e-mail row and the S06 pre-fill',
    'cta_minimal' => 'Minimal: default labels, no heading',
    'cta_dir' => 'Other direction',

    'related' => 'Related: <x-d2.inner.related>',
    'related_note' => 'Whole-row links (two columns from 768 px) or a text card grid; targets are site-map IDs resolved by SiteUrl::target (P07 follows the release rule). Hover turns the edge ink and underlines the label. Without a heading the block is a <nav> named by the proposed "Related pages".',
    'related_links' => 'Rows: the legal cross-links',
    'related_cards' => 'Cards: two related guides of S01',
];
