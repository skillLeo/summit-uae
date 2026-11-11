<?php

/*
| Styleguide annotations for the Design 2 mobile shell (resources/views/designs/d2/styleguide/mobile.blade.php).
| Internal, not site copy: read with __('styleguide_d2/mobile.key'); Arabic and Russian fall back to English.
*/

return [
    'title' => 'Mobile shell',
    'intro' => 'The native-app layer below 768 px (brief §6 mobile, §3.4 overlays): bottom sheets (menu, language, services), the modal dialog, the tab bar with the floating WhatsApp button, the sticky consultation bar, carousels and the installable app. Sheets and the modal are overlays: data-overlay, radius 16 and the one overlay shadow over the --scrim. The buttons open the real components; the specimens are drawn in place.',

    'sheets' => 'Sheets',
    'sheet_live' => 'Live: opens the global menu, language and services sheets and a demo sheet. The language sheet lists the three approved labels in the page language (a sheet, not a segmented group); the menu sheet opens it from its language row, and on phones with the design switcher on it also holds the Design 1 / Design 2 switch (same page, language and #anchor), so the floating switch never covers the tab bar. Below 768 px a bottom sheet (drag the handle or the header with a finger or the mouse, or pull the content down while it is at its top: past 120 px, or a flick faster than 800 px/s, closes; less springs back); from 768 px a side panel on the inline-end edge.',
    'sheet_static' => 'Specimen: --radius-overlay (16) on the top corners, 36×5 handle, white, --shadow-overlay, max-height 90svh, the --scrim backdrop; title in the H3 role; the check list is functional (useful documents).',
    'behaviour' => 'Behaviour',
    'behaviour_items' => [
        'Focus moves to the title (or to [autofocus]); Tab and Shift+Tab wrap inside the sheet.',
        'Everything outside is inert; the page stops scrolling without a layout shift.',
        'Esc anywhere, the backdrop, the close button and Back (Android gesture) close it; focus returns to the opener.',
        'Following a link inside replaces the sheet history entry, so Back never lands on a dead entry.',
        'Opens with --spring-sheet (spring 400 / 40, translateY); under reduced motion it appears without sliding.',
        'While dragged, the sheet follows the finger 1:1 (up resists) and the backdrop fades with the distance (--sheet-fade).',
    ],
    'sheet_api' => 'Markup data-sheet-open="id" (aria-haspopup, aria-controls and aria-expanded kept in sync) and data-sheet-close; JS openSheet(id, opener) and closeSheet() from resources/js/d2/lib/sheet.js. Page sheets render at body level with @push(\'sheets\'). [data-sheet-no-drag] keeps a touch inside the content from pulling the sheet.',

    'modal' => 'Modal',
    'modal_live' => 'Live: native dialog with showModal(); 640 px, --radius-overlay (16), --shadow-overlay, the --scrim; full screen below 768 px. Esc, the backdrop and Back close it; focus returns to the opener. The field has [autofocus].',
    'modal_static' => 'Specimen: title, body, footer actions.',
    'modal_api' => 'Markup data-modal-open="id" and data-modal-close; JS openModal(id, opener) and closeModal(id) from resources/js/d2/components/modal.js (the command palette builds on it).',

    'tabbar' => 'Tab bar',
    'tabbar_note' => 'Below 768 px only (components/tabbar.css): a solid white 64 px bar with a 1 px top line (no glass, no blur, no shadow). Four equal cells with the approved navigation labels of the homepage header, in its order: Home, Practice Areas (opens the services sheet), Legal Resources, Contact Us. Text only (no icon on a navigation item, §3.6 rule 5); labels in the UI role (15/500; Russian 14 below 400 px), wrapping to two balanced lines where needed, never cut or replaced. The current page in ink at 600 with a 2 px ink mark under its label, which slides to the new item on a page change (view transitions); pages without a cell mark nothing. The menu is the header\'s menu button. Hides on scroll down past 24 px and shows on scroll up; never hidden while a sheet or dialog is open or focus is inside it, under reduced motion or at the end of the page; steps aside while the on-screen keyboard is open. The floating WhatsApp button (48 px secondary icon button, the official mark, the approved "Contact Us on WhatsApp" as its name, the WA01 draft in a new tab) sits 16 px above the bar at the inline end and follows it; it steps aside while another WhatsApp control is on screen, a field is being typed in or a sheet is open.',
    'tab_home' => 'Home active (aria-current="page")',
    'tab_practice' => 'Practice Areas active on the Practice Areas page (aria-current="page") and on service pages ("true")',
    'tab_none' => 'No cell is current on Case Studies, FAQs, About and the legal pages',
    'tab_safe' => 'Contact Us current, with a simulated 32 px home-indicator inset (--safe-bottom, a spacing step) under the 64 px bar; the live bar and the floating button add the insets the device reports (env(safe-area-inset-*), 34 px at the bottom of an iPhone)',

    'cta' => 'Sticky consultation bar',
    'cta_note' => 'Design 2.1 (brief §6): one bottom bar on phones, so the sticky bar is never drawn where the tab bar exists (every page of the Design 2 layout); service pages keep their own consultation CTAs and the floating WhatsApp button. Elsewhere: <x-d2.shell.sticky-cta target="…" until="…" :prefill="[…]">, below 768 px with JavaScript only, a white bar on the bottom edge with one full-width ink button (the approved "Request a Legal Consultation" and the page\'s prefill). It appears once the page\'s hero has scrolled out under the header, hides once the until element is reached, while a text field has focus, while the on-screen keyboard is open and while a page\'s own bottom dock is on screen. Sheets and dialogs cover it; toasts rise above it.',
    'cta_low' => 'Specimen: the bar alone at the bottom edge, 8 px plus the safe area under the button.',
    'cta_live' => 'Rendered here as on the service pages (it observes this card and the installable-app heading), and not drawn: this page has the tab bar.',
    'cta_api' => 'Rendered at body level (pushed to the "sheets" stack). Link without JavaScript: App\Design\D2\Prefill::url($prefill); with JavaScript a plain click calls prefill() (resources/js/d2/lib/prefill.js). Behaviour: resources/js/d2/modules/sticky-cta.js.',

    'carousel' => 'Carousel',
    'carousel_note' => '<x-d2.ui.carousel labelledby="…" until="768" list-class="…">: CSS scroll-snap (x mandatory), cards 85 % of the track, 16 px gap, bleeding to the screen edges, no autoplay. JavaScript adds the position markers (thin segments, not dots) and the carousel semantics; a long rail gets the pager (carousel-prev / carousel-next and the position).',
    'carousel_always' => 'Always a carousel (no until), in a 390 px frame: the eleven practice areas, approved titles and summaries.',
    'carousel_items' => [
        'Position markers: one thin 24 × 2 segment per visible card, the current one in ink (aria-current); a marker scrolls its card to the start. One Tab stop: the arrow keys (reversed in RTL), Home and End.',
        'Hit areas: every marker is a 48 × 48 target (WCAG 2.5.8); swipe, Tab through the cards and the keys are the equivalents.',
        'RTL: cards start at the right and the markers run right to left; overscroll never becomes a Back gesture.',
        'Carousel semantics (role="group", aria-roledescription, the name) only while it is a carousel; without JavaScript the rail still swipes and the markers are not drawn.',
        'Filtered cards (hidden) lose their marker; the markers follow the slides.',
        'Keyboard focus on a card the edge cuts snaps that card in; a #fragment inside a card (#preview-case-04, #guide-g04-title, #gc-gc-02) snaps it to the start on load and on hashchange.',
        'Under html.js the markers\' row is reserved from the first paint (.carousel--ready once they are drawn), so nothing below the rail moves when the script starts.',
    ],
    'carousel_api' => 'Props: labelledby (heading id) or label, until 768 | 1280 (a plain list from that width, laid out by list-class), :list-attributes (the caller\'s own hooks on the <ul>), :dots. Knobs: --carousel-card, --carousel-gap, --carousel-bleed. Event d2-carousel-change { index, count }.',
    'carousel_until' => 'until="768": a carousel on phones, a plain grid (the caller\'s list-class) from 768 px. The six case studies, approved titles and matters.',

    'pwa' => 'Installable app (PWA)',
    'pwa_note' => 'Design 2 links its own manifest per language (public/d2/manifest-{locale}.webmanifest: approved brand name and home description, proposed short name, theme #0B0B0C, background #FFFFFF (the white canvas), start_url and scope /{locale}/) and the theme-color #0B0B0C. Generated with node scripts/d2/pwa-icons.mjs from package/brand/.',
    'pwa_icons' => 'Icons: the official gold emblem on night, purpose "any" (192, 512) and "maskable" (192, 512; shown here with a round launcher mask, the emblem stays inside the 80 % safe circle).',
    'pwa_icon_any' => 'App icon, purpose any',
    'pwa_icon_maskable' => 'App icon, purpose maskable, under a round mask',
    'pwa_manifest' => 'The manifest of this page\'s language, read from the file.',
    'pwa_items' => [
        'One service worker for both designs: Design 2 registers Design 1\'s frozen /sw.js on the same scope with the same options (resources/js/d2/modules/pwa.js), so it can never take over Design 1\'s pages.',
        'What it does: navigations network first, the offline page of the language when the network fails; hashed build assets and fonts cache first; POST requests are never touched; no push.',
        'Registered only when config(\'summit.features.pwa\') is on (production), never on 127.0.0.1.',
    ],
];
