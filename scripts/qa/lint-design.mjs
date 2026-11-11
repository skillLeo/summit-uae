#!/usr/bin/env node
/**
 * design lint (docs/briefs/design-2.1-and-admin-cms.md §7.1): the sixteen rules of the brief, measured in
 * Google Chrome on every public route (the route list of scripts/qa/text-snapshot.mjs: the language chooser,
 * home, P02-P08, L01, L02, S01-S11, the styleguide, the offline page and a 404), in EN, AR and RU, at 1440,
 * 768 and 390 px. "A phase is done only at zero."
 *
 *   node scripts/qa/lint-design.mjs                       the full matrix of Design 2 (npm run qa:lint)
 *   node scripts/qa/lint-design.mjs --routes=home,s06* --locales=ar --widths=390 --rules=3,13,15
 *   node scripts/qa/lint-design.mjs --self-test           prove every rule fires on a fixture (no app needed)
 *
 * Options
 *   --routes=<list>      route slugs as text-snapshot.mjs names them, "*" is a wildcard (home, p02-practice-areas,
 *                        p03-resources, p04-case-studies, p05-faq, p06-about, p07-contact, p08-legal-disclaimer,
 *                        l01-privacy-policy, l02-terms-and-conditions, s01-… … s11-…, calculators (Design 2
 *                        only), styleguide, offline, 404, chooser); default all
 *   --locales=<list>     en, ar, ru and root (the chooser "/"); default all four
 *   --widths=<list>      CSS px, default 1440,768,390 (below 768 a touch phone, 768 a touch tablet)
 *   --rules=<list>       rule numbers or slugs (below), default all sixteen
 *   --base=<url>         site under test, default http://127.0.0.1:8042 (a server of this working tree)
 *   --design=1|2         default 2 (cookie summit_design); 1 lints Design 1 for comparison (areas: design-1)
 *   --concurrency=<n>    pages in parallel, default 4
 *   --states=on|off      default on: the states phase (see "States"): on the first selected route of each locale
 *                        and width (normally home) every overlay of the header and the tab bar with its menu
 *                        previews and the palette's results, the shell's tooltips and the compact header; on every
 *                        page the interactive states of main (details, disclosures, tabs, pressed groups and filter
 *                        chips, pagers, choices, selects, calculators with values, the form wizard, in-page searches
 *                        with and without results, in-page sheets, toasts, tooltips). off: the first render only
 *   --illustrations=art|strict
 *                        default art: hits of rules 3, 5, 9, 10, 13 and 16 on the paper of an illustration are
 *                        recorded as exempt and reported apart with their counts; strict counts them (see
 *                        "Illustrations")
 *   --clock=<ISO>|real   page clock, default 2026-11-10T10:30:00+04:00 (office hours, so the live status reads the
 *                        same in every run); real leaves Date alone
 *   --out=<prefix>       report files, default storage/qa/lint-design (.json and .md)
 *   --self-test          serve the fixtures of qa/lint/fixture/ from a temporary folder on a free local port and
 *                        prove each rule fires exactly as often as the fixture expects (every rule once on the
 *                        main fixture), each hit on its planted element, in its state and area (and its "set by"
 *                        rule and file), the illustration art counts, each advisory, the states linted, a violation
 *                        planted only in each kind of state (STATE_KINDS; states.html), the safe-area exemption, and
 *                        the main fixture again under --illustrations=strict; writes nothing under storage/ (unit
 *                        tests of the pure helpers: node --test qa/lint/)
 *   --verbose            with --self-test: also list every violation found
 *   --help
 * Exit 0: zero violations and every page linted; 1: violations or a page that could not be linted; 2: bad
 * arguments, the site (or its stylesheets) cannot be reached, or the self-test failed to start.
 *
 * Rules (§7.1; "outside illustrations" = outside [data-illustration], the brief's marker for the sample-document
 * art; "overlay" = [data-overlay]: mega menu, popovers, dialogs, sheets)
 *    1 mono      computed font-family whose first family is monospace, outside illustrations
 *    2 caps      text-transform: uppercase (or small caps, or text authored in capitals) with letter-spacing above
 *                0.02em, outside illustrations
 *    3 dots      " · " or a bullet (• ∙ ⋅ ‧ ● ◦ ・) used as a separator: words on both sides on the same line (a
 *                separator element between two links counts; a list's leading bullet does not), in a text node
 *                or in generated content (a ::before string such as attr() "SAMPLE · ILLUSTRATION")
 *    4 arrows    arrow characters (→ ← › ‹ » « -> <- ↗ and kin; guillemets that pair up are quotation marks) in the
 *                text or generated content of a, button, [role=button|link|tab|menuitem], summary; carousel and
 *                pager steps ([data-carousel-step], [data-pager-step]) and the form's Back/Next
 *                ([data-wizard-back], [data-wizard-next]) are allowed
 *    5 ellipsis  a text node in main that ends with "…" or "...", any element whose text CSS cuts
 *                (-webkit-line-clamp or text-overflow: ellipsis, measured as overflowing), and text a field's edge
 *                cuts: an input's placeholder (or a value the page set) is put in as its value for a moment and its
 *                scroll width compared with the field (a placeholder that is not painted, ::placeholder opacity 0
 *                while a wrapped copy shows, is not measured); a closed select's chosen option is measured with the
 *                select's font against its content box (a select that wraps its text: against its height). An
 *                approved string (database/content) that itself ends with an ellipsis, the form's "Submitting your
 *                request...", is complete, not cut: reported apart. Proposed and interface strings get no such
 *                exemption, and a field cut has none at all (approved copy is never cut)
 *    6 shadow    box-shadow on any element or pseudo-element outside overlays and illustrations; inside an overlay
 *                only the --shadow-overlay value; also text-shadow and filter: drop-shadow (glows) and
 *                backdrop-filter (glass blur), §3.6 rule 6; also a shadow that appears when a card is hovered
 *    7 gradient  a CSS gradient in background-image, border-image-source or list-style-image (element or
 *                pseudo-element), or an SVG linearGradient/radialGradient, outside illustrations. mask-image is
 *                not checked: the edge fades of scrollers are alpha masks (tokens.md §2)
 *    8 lift      a card (class card, *-card, card--*, or [data-card]) whose bounding box moves or resizes when
 *                hovered (transitions finished, prefers-reduced-motion: no-preference), or whose media scales
 *    9 type      a font size outside the §3.2 scale for the width (from 768 px: 56 36 22 19 17 16 15 13, Arabic
 *                text also 18; below 768: 40 28 20 17 16 15 14 13), any weight but 400/500/600, any text below
 *                13 px; text that is visible (not visually hidden), outside illustrations. The size as drawn on
 *                screen: the computed size times the scale its box is drawn at (see "As drawn")
 *   10 spacing   a margin, padding, row-gap or column-gap outside the §3.4 scale (0 4 8 12 16 20 24 32 48 64 96 128,
 *                either sign), as drawn; a side whose winning declaration uses a safe-area inset (var(--safe-*),
 *                env(safe-area-inset-*)) is device geometry and not counted; see "Spacing decisions" below
 *   11 edges     the inner edges (content box) of the outermost .container of the banner header, of every
 *                full-width section band of main and of the footer differ from the header's by more than 1 px; a
 *                band with content but no .container also fails
 *   12 rows      cards that share a row (same grid or list, same top) whose title tops or link rows (the card's
 *                [data-card-link], footer, CTA or last link) differ by more than 1 px, like compared with like (same
 *                tag and first class: a CTA tile's 44 px button is not measured against text links); a styleguide
 *                specimen grid (class sg-*) is not a card row
 *   13 icons     an icon ([data-icon], svg.icon, an svg using a sprite symbol) whose data-icon is missing or not
 *                allowed by §3.6 rule 5 and App\Design\D2\Icons::ALLOWED (search, menu, close/cross, chevron*,
 *                external on links to another site, phone, mail/email, clock in the contact block only, check,
 *                upload, info, warning, error; carousel-prev/-next and form-back/-next, the arrows rule 3
 *                allows; minus/plus only in a stepper, calendar only in a date field; a bare arrow only in a
 *                carousel/pager step or the form's Back/Next; a "go" chevron never on a link or card), a drawn
 *                "whatsapp" glyph (the mark is the brand file, rule 14), or any icon inside a card title, a
 *                chip, a KPI tile or a menu item (mega menu, language menu, header nav, menu sheet nav,
 *                [role=menuitem]); the ↗ of an external link and the chevron of a disclosure trigger stay allowed.
 *                Inside [data-lint-specimen] (a styleguide gallery showing icons out of context) the name must be
 *                allowed and the place is not checked
 *   14 whatsapp  an a[href] to wa.me / api.whatsapp.com / whatsapp.com whose href is not the approved WA01 of its
 *                language (the page's, or a lang="…" island's; read from database/content/settings.json,
 *                "whatsapp_url", never hard-coded; compared after URL parsing: host,
 *                number, draft text, no extra parameters), or that does not show the one WhatsApp mark: a
 *                descendant [data-brand="whatsapp"] (Meta's official logo files, owner decision; the brief's sprite
 *                "#brand-whatsapp" is replaced by this marker). A mark showing data-placeholder="whatsapp-logo"
 *                (until Meta's files are in resources/brand/whatsapp/) passes and is counted (links whose shown
 *                slot is the placeholder; hidden links that hold one). Hidden links (closed sheets) are checked
 *                too, the mark's visibility only when the link shows
 *   15 digits    on Arabic pages a text node that mixes 0-9 and ٠-٩ (or ۰-۹); also a block whose inline pieces mix
 *                them (left-to-right islands such as <bdi dir="ltr">, tel: and mailto: links excluded); the number
 *                of Western and Eastern digit nodes per page is reported as advice (§3.7: a page never mixes)
 *   16 contrast  axe-core color-contrast (WCAG 2 AA) on the page and on each overlay state
 *
 * Illustrations. The brief notes the [data-illustration] exemption for rules 1, 2, 6 and 7: they are never
 * measured inside an illustration. Rules 3, 5, 9, 10, 13 and 16 are measured there too, but by default (art)
 * their hits on the sample paper (.doc or [data-doc] inside the illustration, and SVG drawings; for rule 10 also a
 * drawing box that shows no text and is no control) are recorded as exempt and reported apart with counts, not as
 * violations: the sample documents imitate paper (document sizes and spacing scaled with the paper, the
 * "SAMPLE · ILLUSTRATION" watermark, faint typed text; WCAG 1.4.3 exempts text that is part of a picture), and zero
 * would otherwise be unreachable without removing the illustrations the brief keeps (§3.1, §6). Interface text an
 * illustration holds outside its paper (the hero's callout labels and pin numbers) counts like any text.
 * --illustrations=strict counts the paper too (the brief's literal wording). Rules 4, 8, 11, 12, 14 and 15 apply
 * inside illustrations as everywhere. Overlay exemption: rule 6 only, and only the one --shadow-overlay value.
 *
 * As drawn (rules 9 and 10). A length is checked as it shows on screen: the computed value times the scale its box is
 * drawn at, the product of its own and its ancestors' `scale`, `rotate` and `transform` (a box that cannot be
 * transformed, such as an inline span, adds nothing) and its CSS zoom (currentCSSZoom; computed lengths are
 * unzoomed). Font size, padding and gaps take the scale of the element's own box, margins that of its parent (a
 * margin is laid out in the parent's space); top and bottom the vertical axis, left and right the horizontal one.
 * So the hero's callout labels, sized ÷ --k inside a canvas drawn at --k, read their 15 px and 8/12 px, while a
 * label left at 15 px in that canvas reads 14.2 px. A scaled value is reported with what it is made of: "font-size
 * 13.5px (15px at scale 0.9)". SVG <text> takes its scale from getScreenCTM (viewBox and HTML transforms included).
 * A styleguide specimen that zooms a component to fit its card (CSS zoom inside .sg-specimen or [data-lint-specimen]:
 * the docked header specimen, zoom 0.875-1) shows the component at its own tokens: a value off the scale only because
 * of that zoom, on the scale without it and, for text, still 13 px or more on screen, is recorded apart (exempt
 * "specimen-zoom", counted in the report); components scale with transform or scale, which always count.
 *
 * Set by (rules 6, 7, 9, 10): the first violating element of each record is looked up in Chrome's cascade (DevTools
 * protocol, CSS.getMatchedStylesForNode) and the record gets "source": the winning declaration with its layer and
 * selector (".x { gap: 10px }", nested rules with their parents), "user agent: td { … }" for a browser default,
 * "inherited: …" for a font size or weight set on an ancestor, or "inline style" set by script; and "file": the
 * stylesheet under resources/css/d2 that holds that rule (the Vite dev server and a build both serve one bundled
 * sheet, so the rule's selector, nesting, layer and declarations are looked up in an index of the source files:
 * buildCssIndex, findRuleFile). The brief's "report the selector" of rule 10: the element's selector is in
 * "selector", the CSS rule's in "source".
 *
 * Spacing decisions (rule 10)
 *   - Values are the drawn pixels (the computed value as drawn: "As drawn"); 0 and every step of the scale pass, with
 *     either sign (a rail that bleeds to the screen edge with a negative --pad-x margin passes) and ±0.25 px
 *     tolerance.
 *   - auto margins (the container's centring margin-inline: auto, flex pushes) and percentages (and calc() with a
 *     percentage) are ignored: they are proportions, not spacing steps. The computed value is checked through
 *     CSS Typed OM (computedStyleMap) only when the pixel value is off the scale; the run counts what it ignored.
 *   - The container's side padding (--pad-x: 20 / 24 / 32) is on the scale at every breakpoint and is checked
 *     like any other padding; so are the band paddings (--section-y 64 / 96 / 128) and card paddings (24 / 32).
 *   - row-gap / column-gap only where they apply (flex, grid, multi-column); "normal" is ignored.
 *   - Visually hidden elements (clip / clip-path inset(50%) / 1 px boxes: .sr-only's -1px margin) and SVG
 *     internals are not checked; UA defaults count (an unreset ul's 40 px padding is off the scale).
 *   - calc() results count like any value: an optical offset such as calc((var(--lh-small) - 8px) / 2) that
 *     centres a dot on a line is off the scale (the "set by" source shows the calc, so the owner can decide).
 *
 * Areas (where a violation is fixed). Rules 6, 7, 9 and 10 go to the area that maintains the file of the winning
 * declaration (AREA_FILES: "inherited: @layer base body" is base.css, platform; a value set in pages/inner.css is
 * inner, wherever the element sits). Without a known file (a browser default, an inline style, the shared
 * styleguide.css) and for the other rules, the element's location decides ("areaFor" below): time-digits (rule 15,
 * the live hours status),
 * components (the CSS rules 1, 2, 6-10 and 16 when the element or its parent carries a UI-kit class: btn,
 * chip, card, field, badge, segmented, tabs, acc, callout, steps, kbd, sheet, modal, carousel, stepper,
 * icon, doc …), platform (base elements such as strong, code, small without a class; anything outside header,
 * main and footer), header (banner header, mega menu, language menu), footer-shell (footer, tab bar, sticky CTA,
 * menu and services sheets), home-b (home sections tools, who-we-help, about, case-studies, guides, questions,
 * consultation-form, and the search palette), home-a (every other home section: hero, trust, services, anatomy,
 * review, navigator, and new ones), inner (main of every other page and the TOC sheet). On the styleguide each
 * section goes to the area that maintains it in Phase 1: foundations, type and the frame (page head) platform;
 * controls, surfaces, icons, upload and the mobile specimens components; documents, hero, sections, navigator
 * home-a; tools, cards, search, wizard home-b; inner inner; shell header; the footer, tab bar, sheet and
 * sticky-CTA specimens of shell and mobile footer-shell.
 *
 * States. A page shows more than its first render. With --states=on (default) the lint drives, through the page's own
 * controls, the states it can reach and lints each one (pageLint on what changed, the "set by" lookup and axe;
 * rules 1-7, 9, 10 and 13-16): on the first route of each locale and width, every overlay of the header and the tab
 * bar, each menu preview ([data-mega-item], [data-preview-item]), the search overlay with the locale's query typed in,
 * each tooltip of the header, tab bar and footer, and the compact header (the page scrolled down) with the overlays
 * only its triggers open; on every page: every <details> open and every in-page disclosure expanded, each other tab
 * of a tablist, each other button of a pressed group (aria-pressed with aria-controls, with the groups its panel
 * holds, or without: filter chips), each other button of a pager (aria-current: the hero's document dots; not a
 * carousel's), each other option of a radio group (a group of more than 8 once), one other option of each select,
 * each calculator ([data-tool]) filled with sample values, the form wizard ([data-wizard], [data-wizard-next]: Next on
 * the empty first step, then step by step with sample values; never sent), each in-page search field (input
 * type=search) with a query that has results, one that has none (SEARCH_QUERY, NO_MATCH_QUERY) and cleared again,
 * each in-page sheet or dialog trigger, each control that shows a toast ([data-toast] and the copy controls) and each
 * tooltip trigger ([data-tooltip], [data-tooltip-host], a control holding its own [data-overlay] tip: focused after a
 * key press, so :focus-visible shows the tip). Pressed groups, pagers and searches lint the control's region and
 * every region of main they changed (watchChanges: a filter or a search may change a list in another section). A
 * state reports only what the page has not reported yet (same rule, selector, value and text), with its label in
 * "state". localStorage lives in each page (nothing a state saves reaches another page). At most MAX_STATES states
 * per page.
 *
 * Advice (§3.6 patterns that no rule of §7.1 measures; reported apart, never counted, so that Phase 2 can prove
 * zero): 4 decorative numbering (a zero-padded number standing alone outside steps), 10 the same short metadata on
 * every item of one kind in a list or grid (same tag and classes, BEM modifiers and states aside: a closing tile is
 * not one more card; the labels of label/value pairs, <dt>, repeat by design), 11 dashed or dotted boxes, 12 a word in
 * another colour than the rest of its heading (a muted run of the neutral ink ramp and a step number of an allowed
 * sequence, "1." in a step context, are not coloured words).
 *
 * Output: storage/qa/lint-design.json (options, the rule table, the summary, every page with its counts and states,
 * every violation {rule, route, locale, width, state, area, selector, value, text, count, source?, file?}, sorted by
 * rule within a page; identical violations on one page are folded into one record with a count; every advisory
 * {pattern, route, locale, width, state, area, selector, value, text, count}) and storage/qa/lint-design.md (counts
 * by rule, first render and states, public pages apart from the styleguide, by area and rule, by route, the most
 * frequent selectors per rule, "set by" per declaration with its file, WhatsApp links and placeholders, the exempted
 * and advisory counts: illustration paper, safe-area insets, inline SVG drawings outside illustrations, axe
 * "incomplete", ignored auto/percentage spacing; the advice per pattern; the states; the rule to fix-list and area
 * table). Nothing volatile is written (no time), so two runs of the same tree give the same files.
 *
 * Capture: Google Chrome (channel chrome), one context per locale and width (cookies summit_design and
 * summit_qa=1, Vite's reload client stubbed: qa/lib/browser.mjs), browser locale of the page language,
 * Asia/Dubai, light scheme, prefers-reduced-motion: reduce (final states; the hover test switches to
 * no-preference), device scale 1, other hosts blocked, no POST to /enquiries, localStorage per page. Each page:
 * load, fonts, one scroll through (lazy tools mount), back to the top, scripts and animations settled and frozen,
 * then the checks; then the overlays (first route), the hover test and the interactive states.
 */
import { cpSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { dirname, extname, join, relative, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from '@playwright/test';
import { newContext, scrollThrough, watchPage } from '../../qa/lib/browser.mjs';
import { freezeAnimations, script, settleStyles } from '../../qa/lib/runtime.mjs';
import { buildRoutes, readRegistry } from './text-snapshot.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const DEFAULT_BASE = 'http://127.0.0.1:8042';
const DEFAULT_WIDTHS = [1440, 768, 390];
const DEFAULT_OUT = 'storage/qa/lint-design';
const DEFAULT_CLOCK = '2026-11-10T10:30:00+04:00';
const TIMEZONE = 'Asia/Dubai';
const BROWSER_LOCALE = { en: 'en-GB', ar: 'ar-AE', ru: 'ru-RU', root: 'en-GB' };
const NAV_TIMEOUT = 90_000;
const MAX_HOVER_CARDS = 60;
const MAX_PER_RULE = 4000;
/** Interactive states per page; a page with more gets a note ("state limit reached"). */
const MAX_STATES = 240;
/** The query the shell states type into the search palette, per locale (a word with results in each language). */
const PALETTE_QUERY = { en: 'cheque', ar: 'شيك', ru: 'чек', root: 'cheque' };
/** The queries typed into each in-page search field: one with results (the palette's word) and one with none. */
const SEARCH_QUERY = PALETTE_QUERY;
const NO_MATCH_QUERY = { en: 'zzqx', ar: 'زززق', ru: 'зззкх', root: 'zzqx' };
/** The stylesheets the "set by" file lookup indexes (rules 6, 7, 9, 10): every .css file under these folders. */
const CSS_DIRS = ['resources/css/d2'];
const FIXTURE_DIR = join(ROOT, 'qa/lint/fixture');

// ------------------------------------------------------------------------------------------ rules

/** The sixteen rules of §7.1 with the fix-list items (§4) and brief sections each one guards. */
export const RULES = [
    { id: 1, slug: 'mono', title: 'Monospace text outside illustrations', fixes: ['D01', 'D29', 'D47', 'D48'], brief: '§3.2, §3.6.1' },
    { id: 2, slug: 'caps', title: 'Uppercase with letter-spacing above 0.02em', fixes: ['D01', 'D29', 'D47', 'D48'], brief: '§3.6.1' },
    { id: 3, slug: 'dots', title: 'Middle dot or bullet used as a separator', fixes: ['D02', 'D12', 'D17'], brief: '§3.6.2' },
    { id: 4, slug: 'arrows', title: 'Arrow characters in links and buttons', fixes: ['D03', 'D48'], brief: '§3.6.3' },
    { id: 5, slug: 'ellipsis', title: 'Text ending with an ellipsis or cut by CSS', fixes: ['D09', 'D21'], brief: '§3.6.8, §5.2' },
    { id: 6, slug: 'shadow', title: 'Shadow, glow or glass outside overlays and illustrations', fixes: ['D05', 'D06', 'D48'], brief: '§3.4, §3.6.6' },
    { id: 7, slug: 'gradient', title: 'CSS gradient outside illustrations', fixes: ['D06', 'D27'], brief: '§3.6.6' },
    { id: 8, slug: 'lift', title: 'Card that moves on hover', fixes: ['D05'], brief: '§3.5, §3.6.7' },
    { id: 9, slug: 'type', title: 'Font size off the scale, weight not 400/500/600, text below 13 px', fixes: ['D08'], brief: '§3.2' },
    { id: 10, slug: 'spacing', title: 'Margin, padding or gap off the spacing scale', fixes: ['D45'], brief: '§3.4' },
    { id: 11, slug: 'edges', title: 'Container edges of header, sections and footer differ', fixes: ['D13', 'D47'], brief: '§3.4' },
    { id: 12, slug: 'rows', title: 'Card titles or link rows out of line within a row', fixes: ['D21', 'D25', 'D27'], brief: '§3.5' },
    { id: 13, slug: 'icons', title: 'Icon not allowed, or inside a card title, chip, KPI tile or menu item', fixes: ['D03', 'D04', 'D10', 'D27', 'D44', 'D46', 'D48'], brief: '§3.5, §3.6.5' },
    { id: 14, slug: 'whatsapp', title: 'WhatsApp link without the official mark or with another href than WA01', fixes: ['D10', 'D40', 'D47'], brief: '§3.8, §5.2' },
    { id: 15, slug: 'digits', title: 'Arabic text mixing 0-9 and ٠-٩', fixes: ['D11'], brief: '§3.7' },
    { id: 16, slug: 'contrast', title: 'axe colour-contrast', fixes: [], brief: '§3.3, §7.2' },
];

/** Code areas, in report order. */
export const AREAS = ['platform', 'components', 'header', 'footer-shell', 'home-a', 'home-b', 'inner', 'time-digits'];

/**
 * The stylesheets each area maintains (repo-relative; "*" within a folder, {a,b} alternatives). Rules 6, 7, 9 and 10
 * go to the area of the file that holds the winning declaration ("set by"), the element's location only when no
 * file is known (a browser default, an inline style, a value no stylesheet sets). resources/css/d2/styleguide.css is
 * shared by every area's specimens (the styleguide section decides). A new stylesheet must be added here (the unit
 * test checks that every file under resources/css/d2 has exactly one area).
 */
export const AREA_FILES = {
    platform: ['resources/css/d2/app.css', 'resources/css/d2/tokens.css', 'resources/css/d2/utilities.css', 'resources/css/d2/base/*.css', 'resources/css/d2/layout/*.css'],
    components: ['resources/css/d2/components/{button,icon,chip,segmented,field,date,badge,kbd,progress,card,accordion,tabs,sheet,modal,toast,tooltip,skeleton,callout,popover,carousel,upload}.css'],
    header: ['resources/css/d2/components/{header,megamenu,langmenu}.css'],
    'footer-shell': ['resources/css/d2/components/{footer,tabbar,sticky-cta}.css'],
    'home-a': ['resources/css/d2/sections/{hero,start,services,anatomy,method,navigator}.css', 'resources/css/d2/components/{doc,stage}.css'],
    'home-b': ['resources/css/d2/sections/{tools,who,about,team,cases,guides,faq,contact}.css', 'resources/css/d2/components/{tool-link,wizard,profile,palette}.css'],
    inner: ['resources/css/d2/pages/*.css'],
    'time-digits': ['resources/css/d2/components/hours.css'],
};

/** Stylesheets that no single area owns: the location of the element decides (AREA_FILES). */
export const SHARED_CSS = ['resources/css/d2/styleguide.css'];

// ------------------------------------------------------------------------------------------ pure helpers
// Every helper below is also injected into the page (qa/lib/runtime.mjs script()): self-contained, no module
// references except other helpers of this list, which are defined beside it in the page.

/** Splits a computed list value (box-shadow, font-family) on the commas outside parentheses. */
export function splitLayers(value) {
    const out = [];
    let depth = 0;
    let start = 0;
    const s = String(value || '');
    for (let i = 0; i < s.length; i += 1) {
        const c = s[i];
        if (c === '(') depth += 1;
        else if (c === ')') depth -= 1;
        else if (c === ',' && depth === 0) {
            out.push(s.slice(start, i).trim());
            start = i + 1;
        }
    }
    out.push(s.slice(start).trim());
    return out.filter(Boolean);
}

/** Alpha of a computed colour (rgb/rgba/hsl/… or transparent); 1 when unknown. */
export function colorAlpha(color) {
    const c = String(color || '').trim();
    if (!c || c === 'transparent' || c === 'none') return 0;
    let m = c.match(/\/\s*([\d.]+)(%?)\s*\)\s*$/);
    if (m) return parseFloat(m[1]) / (m[2] ? 100 : 1);
    m = c.match(/^(?:rgba|hsla)\(([^)]*)\)$/i);
    if (m) {
        const parts = m[1].split(',').map((p) => p.trim());
        if (parts.length === 4) return parts[3].endsWith('%') ? parseFloat(parts[3]) / 100 : parseFloat(parts[3]);
    }
    return 1;
}

/** True when a computed box-shadow / text-shadow draws anything (a visible colour and a non-zero length). */
export function visibleShadow(value) {
    const v = String(value || '').trim();
    if (!v || v === 'none') return false;
    return splitLayers(v).some((layer) => {
        const color = (layer.match(/(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\([^)]*\)|transparent|#[0-9a-f]{3,8}\b/i) || ['rgb(0, 0, 0)'])[0];
        if (colorAlpha(color) === 0) return false;
        const lengths = (layer.replace(color, ' ').match(/-?\d*\.?\d+(?:px|em|rem)?/g) || []).map(parseFloat);
        return lengths.some((n) => n !== 0);
    });
}

export function hasGradient(value) {
    return /gradient\(/i.test(String(value || ''));
}

/** First family of a font-family list, unquoted. */
export function primaryFamily(list) {
    const first = splitLayers(list)[0] || '';
    return first.replace(/^["']|["']$/g, '').trim();
}

export function isMonospace(list) {
    return /(?:^|\b)(?:monospace|ui-monospace|sf ?mono|menlo|monaco|consolas|courier(?: new)?|lucida console|andale mono|liberation mono|dejavu sans mono|fira (?:code|mono)|jetbrains mono|source code pro|roboto mono|ibm plex mono|cascadia (?:code|mono)|d2 mono)\b/i.test(primaryFamily(list));
}

/**
 * Rule 2: uppercase (CSS, small caps, or authored capitals) with letter-spacing above 0.02em. Returns the
 * description, or null.
 */
export function trackedCaps({ textTransform, fontVariantCaps, letterSpacing, fontSize, text }) {
    const size = parseFloat(fontSize) || 0;
    const ls = letterSpacing === 'normal' ? 0 : parseFloat(letterSpacing) || 0;
    if (!size || ls / size <= 0.02 + 1e-6) return null;
    const em = `${(Math.round((ls / size) * 1000) / 1000).toString()}em`;
    if (textTransform === 'uppercase') return `text-transform: uppercase with letter-spacing ${em}`;
    if (/small-caps|petite-caps|unicase|titling-caps/.test(String(fontVariantCaps || ''))) return `font-variant-caps: ${fontVariantCaps} with letter-spacing ${em}`;
    const t = String(text || '');
    if (/\p{Lu}[^]*\p{Lu}/u.test(t) && !/\p{Ll}/u.test(t)) return `text in capitals with letter-spacing ${em}`;
    return null;
}

/** Rule 4: the arrow characters of a text. Guillemets that pair up («…», ‹…›) are quotation marks. */
export function arrowsIn(text) {
    const t = String(text || '')
        .replace(/«[^«»]*»/gu, (m) => ' '.repeat(m.length))
        .replace(/‹[^‹›]*›/gu, (m) => ' '.repeat(m.length));
    return [...t.matchAll(/->|<-|[→←↗↖↘↙↑↓⟶⟵⟹⟸⇒⇐⇢⇠⇨⇦➔➜➝➞➟➠➡⬅›‹»«]/gu)].map((m) => m[0]);
}

/** Rule 3: offsets of the separator characters that stand alone (whitespace or the node edge on both sides). */
export function separatorIndexes(text) {
    const s = String(text || '');
    const out = [];
    for (const m of s.matchAll(/[·•∙⋅‧●◦・]/gu)) {
        const i = m.index;
        const before = i === 0 || /\s/u.test(s[i - 1]);
        const after = i === s.length - 1 || /\s/u.test(s[i + 1]);
        if (before && after) out.push(i);
    }
    return out;
}

/** Rule 5: the text ends with an ellipsis (… or ...), possibly before closing punctuation. */
export function endsWithEllipsis(text) {
    return /(?:…|\.\.\.)[\s"'»”’)\]]*$/u.test(String(text || ''));
}

/** Rule 15: the text holds both Western (0-9) and Eastern Arabic (٠-٩ or ۰-۹) digits. */
export function digitMix(text) {
    const s = String(text || '');
    return /[0-9]/.test(s) && /[٠-٩۰-۹]/u.test(s);
}

/** Rule 10: on the §3.4 scale (either sign, ±0.25 px). */
export function onSpacingScale(px) {
    const a = Math.abs(Number(px));
    return [0, 4, 8, 12, 16, 20, 24, 32, 48, 64, 96, 128].some((s) => Math.abs(a - s) <= 0.25);
}

/** Rule 9: the §3.2 sizes at this viewport width (desktop values from 768 px, tokens.md §3); Arabic body +1. */
export function typeScale(width, arabic) {
    const desktop = Number(width) >= 768;
    const sizes = desktop ? [56, 36, 22, 19, 17, 16, 15, 13] : [40, 28, 20, 17, 16, 15, 14, 13];
    if (arabic) sizes.push(desktop ? 18 : 17);
    return [...new Set(sizes)];
}

export function inTypeScale(px, sizes) {
    return sizes.some((s) => Math.abs(Number(px) - s) <= 0.05);
}

export function isWhatsAppHref(href) {
    return /^\s*(?:https?:\/\/(?:wa\.me|api\.whatsapp\.com|(?:www\.)?whatsapp\.com)(?:[/?#]|$)|whatsapp:)/i.test(String(href || ''));
}

/**
 * Rule 14: null when the href is the approved WA01 (identical, or the same after URL parsing: protocol, host,
 * number, decoded draft text, no extra parameters), else what differs.
 */
export function compareWhatsApp(href, expected) {
    if (!expected) return 'no approved WA01 draft for this page language';
    const h = String(href || '').trim();
    if (h === expected) return null;
    let a;
    let b;
    try {
        a = new URL(h);
        b = new URL(expected);
    } catch {
        return 'the href is not a valid URL';
    }
    const problems = [];
    if (a.protocol !== b.protocol || a.host.toLowerCase() !== b.host.toLowerCase()) problems.push(`address ${a.protocol}//${a.host} instead of ${b.protocol}//${b.host}`);
    if (a.pathname.replace(/\/+$/, '') !== b.pathname.replace(/\/+$/, '')) problems.push(`number ${a.pathname} instead of ${b.pathname}`);
    const ta = a.searchParams.get('text');
    const tb = b.searchParams.get('text');
    if (ta !== tb) problems.push(ta === null ? 'no draft text' : 'the draft text differs from WA01');
    const extra = [...new Set([...a.searchParams.keys()].filter((k) => !b.searchParams.has(k)))];
    if (extra.length) problems.push(`extra parameter ${extra.join(', ')}`);
    return problems.length ? problems.join('; ') : null;
}

/**
 * Rule 13: null when the icon is allowed here, else the reason. The names follow §3.6 rule 5 and the components
 * contract (App\Design\D2\Icons::ALLOWED): search, menu, close / cross, the chevrons, external, phone,
 * mail / email, clock (contact block only), check, upload, info, warning, error; the arrows §3.6 rule 3 allows
 * under their own names carousel-prev / carousel-next and form-back / form-next (a bare arrow only inside a
 * carousel/pager step or the form's Back/Next); minus / plus (stepper) and calendar (date field). A "go"
 * chevron (chevron, chevron-start) on a link or in a card is an arrow (Icons::ARROWS) unless it belongs to a
 * disclosure or a step control. ctx: { chip, kpi, cardTitle, menuItem, disclosure, externalLink, inLink, inCard,
 * contact, step, stepper, date, specimen } (booleans measured in the page). specimen: inside [data-lint-specimen]
 * (a styleguide gallery that shows icons out of their context): the name must be allowed, the place is not checked.
 */
export function iconVerdict(name, ctx) {
    const n = String(name || '').trim().toLowerCase();
    if (!n) return 'icon without data-icon';
    const c = ctx || {};
    if (n === 'whatsapp') return 'a drawn "whatsapp" icon: the WhatsApp mark is the brand mark [data-brand="whatsapp"] (Meta\'s files), never a glyph';
    if (c.chip) return `icon "${n}" inside a chip`;
    if (c.kpi) return `icon "${n}" inside a KPI tile`;
    if (c.cardTitle) return `icon "${n}" inside a card title`;
    if (c.menuItem && !(n === 'external' && c.externalLink) && !(/^chevron/.test(n) && c.disclosure)) return `icon "${n}" inside a menu item`;
    if (['search', 'menu', 'close', 'cross', 'phone', 'mail', 'email', 'check', 'upload', 'info', 'warning', 'error', 'chevron-down', 'chevron-up', 'carousel-prev', 'carousel-next', 'form-back', 'form-next'].includes(n)) return null;
    // The home hero's autoplay toggle (the owner's autoplay, WCAG 2.2.2): its glyph is its only visible label.
    if ((n === 'pause' || n === 'play') && (c.step || c.specimen)) return null;
    if (c.specimen && ['chevron', 'chevron-start', 'clock', 'external', 'minus', 'plus', 'calendar'].includes(n)) return null;
    if (/^chevron(?:-(?:start|end|left|right))?$/.test(n)) return !c.disclosure && !c.step && (c.inLink || c.inCard) ? `"go" chevron "${n}" on a link or card (an arrow, §3.6 rule 3)` : null;
    if (n === 'clock') return c.contact ? null : 'icon "clock" outside the contact block';
    if (n === 'external') return c.externalLink ? null : 'icon "external" on a link that stays on this site';
    if (n === 'arrow' || n === 'arrow-start' || n === 'arrow-end') return c.step ? null : `arrow icon "${n}" outside carousel previous/next and the form's Back/Next`;
    if (n === 'minus' || n === 'plus') return c.stepper ? null : `icon "${n}" outside a stepper`;
    if (n === 'calendar') return c.date ? null : 'icon "calendar" outside a date field';
    return `icon "${n}" is not in the allowed list`;
}

/** Card and chip class tokens: a block name (card, svc-card, chip …) or its modifier, never a BEM element. */
export function isCardToken(token) {
    return /^(?:[a-z0-9]+-)*card(?:--[a-z0-9-]+)?$/.test(String(token || ''));
}

export function isChipToken(token) {
    return /^(?:[a-z0-9]+-)*chip(?:--[a-z0-9-]+)?$/.test(String(token || ''));
}

export function isKpiToken(token) {
    return /^(?:[a-z0-9]+-)*kpi(?:--[a-z0-9-]+)?$/.test(String(token || ''));
}

/** The text of a computed `content` value (strings, attr(), quotes); counters and images give nothing. */
export function contentText(content, attr) {
    const s = String(content || '');
    if (!s || s === 'none' || s === 'normal') return '';
    const unescape = (t) => t.replace(/\\([0-9a-fA-F]{1,6})\s?/g, (_, hex) => String.fromCodePoint(parseInt(hex, 16))).replace(/\\(.)/g, '$1');
    let out = '';
    for (const m of s.matchAll(/"((?:[^"\\]|\\.)*)"|'((?:[^'\\]|\\.)*)'|attr\(\s*([^)\s]+)\s*\)|(open|close)-quote/g)) {
        if (m[1] !== undefined) out += unescape(m[1]);
        else if (m[2] !== undefined) out += unescape(m[2]);
        else if (m[3]) out += (attr ? attr(m[3]) : '') || '';
        else if (m[4]) out += '"';
    }
    return out;
}

/** Rule 10: a declaration that uses a safe-area inset (var(--safe-*) or env(safe-area-inset-*)) is device geometry. */
export function isSafeAreaSource(source) {
    return /var\(\s*--safe-[\w-]*|env\(\s*safe-area-inset-/i.test(String(source || ''));
}

/** Rule 5: the input types whose one line of text a field edge can cut (a date, a range or a file input is not text). */
export function cuttableInput(type) {
    return ['', 'text', 'search', 'email', 'tel', 'url', 'number'].includes(String(type ?? '').trim().toLowerCase());
}

/** §3.6 rule 4 (advice): decorative numbering, a zero-padded number standing alone ("01", "07.", "٠٣"). */
export function zeroPadded(text) {
    return /^\s*(?:0[0-9]|[٠۰][٠-٩۰-۹])[.)]?\s*$/u.test(String(text || ''));
}

/** §3.6 rule 11 (advice): how many sides of a box are dashed or dotted (computed styles and widths, top right bottom left). */
export function dashedSides(styles, widths) {
    return styles.filter((s, i) => (s === 'dashed' || s === 'dotted') && parseFloat(widths[i]) > 0).length;
}

/**
 * §3.6 rule 10 (advice): the texts that every item of a group (three or more) shows, from each item's set of short
 * metadata texts; a text inside a longer common text ("3" inside "3 min read") is left out.
 */
export function commonTexts(sets) {
    if (sets.length < 3) return [];
    const [first, ...rest] = sets;
    const common = [...first].filter((t) => rest.every((s) => s.has(t)));
    return common.filter((t) => !common.some((o) => o !== t && o.includes(t))).sort();
}

/**
 * States phase: the sample date (YYYY-MM-DD) typed into a calculator's date field, from the field's name (data-input,
 * name or id) and the page clock: a start lies years back, an expiry ahead, a notice or a last day a little back.
 */
export function sampleDate(name, clockMs) {
    const n = String(name || '').toLowerCase();
    const now = new Date(Number.isFinite(clockMs) ? clockMs : Date.now());
    const shift = (months) => new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + months, 1)).toISOString().slice(0, 10);
    if (/start|from|begin|join|hire|since|commenc/.test(n)) return shift(-76);
    if (/expir|renew/.test(n)) return shift(3);
    if (/notice|receiv|sent|serve/.test(n)) return shift(-2);
    if (/last|end|until|leav|termin|exit/.test(n)) return shift(-1);
    return shift(-12);
}

/** States phase: the number typed into a calculator field: its placeholder's digits (the example), its minimum, or 1. */
export function sampleNumber(placeholder, min) {
    const western = String(placeholder || '')
        .replace(/[٠-٩]/gu, (c) => String(c.charCodeAt(0) - 0x660))
        .replace(/[۰-۹]/gu, (c) => String(c.charCodeAt(0) - 0x6f0));
    const digits = western.replace(/[^\d.]/g, '').replace(/^\.+|\.+$/g, '');
    if (/\d/.test(digits)) return digits;
    if (min !== null && min !== undefined && String(min).trim() !== '' && Number.isFinite(Number(min))) return String(Number(min));
    return '1';
}

// Rules 9 and 10 measure lengths as drawn on screen. A linear map is [a, b, c, d]: (x, y) -> (a x + c y, b x + d y),
// the first four numbers of matrix(a, b, c, d, e, f); translation never changes a length, so it is left out.

/** The product p·q of two linear maps (q applies first). */
export function mulLinear(p, q) {
    return [p[0] * q[0] + p[2] * q[1], p[1] * q[0] + p[3] * q[1], p[0] * q[2] + p[2] * q[3], p[1] * q[2] + p[3] * q[3]];
}

/** How long the drawn x and y unit vectors of a linear map are (a rotation keeps 1, a mirror keeps 1). */
export function linearScale(m) {
    return { x: Math.hypot(m[0], m[1]), y: Math.hypot(m[2], m[3]) };
}

/**
 * The linear part of what a box's own computed `rotate`, `scale` and `transform` do to it (CSS Transforms 2 order:
 * rotate, then scale, then transform), or the identity for a box that cannot be transformed: display contents, none
 * or a table column, and display inline on a non-replaced element (a span's `scale` has no effect). s: { display,
 * tag, scale, rotate, transform } (computed values; transform is matrix() or matrix3d(), as Chrome resolves it).
 */
export function ownLinear(s) {
    const identity = [1, 0, 0, 1];
    const display = String(s?.display || '');
    if (/^(?:contents|none|table-column|table-column-group)$/.test(display)) return identity;
    if (display === 'inline' && !/^(?:img|svg|video|canvas|iframe|embed|object|input|select|textarea|button|audio|meter|progress)$/i.test(String(s?.tag || ''))) return identity;
    let m = identity;
    const rotate = String(s?.rotate || 'none').trim();
    if (rotate && rotate !== 'none') {
        const parts = rotate.split(/\s+/);
        const angle = parts.pop();
        const unit = (angle.match(/[a-z]+$/i) || ['deg'])[0].toLowerCase();
        const rad = parseFloat(angle) * ({ deg: Math.PI / 180, rad: 1, grad: Math.PI / 200, turn: 2 * Math.PI }[unit] ?? Math.PI / 180);
        const axes = { x: [1, 0, 0], y: [0, 1, 0], z: [0, 0, 1] };
        let [x, y, z] = parts.length === 1 && axes[parts[0]] ? axes[parts[0]] : parts.length === 3 ? parts.map(Number) : [0, 0, 1];
        const n = Math.hypot(x, y, z) || 1;
        [x, y, z] = [x / n, y / n, z / n];
        if (Number.isFinite(rad)) {
            const c = Math.cos(rad);
            const sn = Math.sin(rad);
            const t = 1 - c;
            // Rodrigues: the upper-left 2x2 of the 3D rotation (what the screen plane sees of it)
            m = mulLinear(m, [t * x * x + c, t * x * y + sn * z, t * x * y - sn * z, t * y * y + c]);
        }
    }
    const scale = String(s?.scale || 'none').trim();
    if (scale && scale !== 'none') {
        const v = scale.split(/\s+/).map((p) => (p.endsWith('%') ? parseFloat(p) / 100 : parseFloat(p)));
        if (Number.isFinite(v[0])) m = mulLinear(m, [v[0], 0, 0, Number.isFinite(v[1]) ? v[1] : v[0]]);
    }
    const transform = String(s?.transform || 'none').trim();
    const mm = transform.match(/^matrix(3d)?\(([^)]*)\)$/);
    if (mm) {
        const v = mm[2].split(',').map((p) => parseFloat(p));
        const lin = mm[1] ? [v[0], v[1], v[4], v[5]] : v.slice(0, 4);
        if (lin.length === 4 && lin.every(Number.isFinite)) m = mulLinear(m, lin);
    }
    return m;
}

/** A length as drawn: "7.2px (8px at scale 0.9)" when the box is scaled, else "8px". */
export function drawnLength(px, k) {
    const r = (n) => Math.round(n * 10) / 10;
    return Math.abs(k - 1) > 0.0005 ? `${r(px * k)}px (${r(px)}px at scale ${Number(k.toFixed(3))})` : `${r(px)}px`;
}

/**
 * Advice 12: a colour of the neutral ink ramp (ink, ink-2, ink-3 and their light counterparts on dark: grey with at
 * most a trace of hue), which is not "a coloured word". rgb()/rgba() and oklch()/oklab()/lch()/lab() computed values;
 * anything else counts as coloured.
 */
export function neutralColor(color) {
    const s = String(color || '').trim().toLowerCase();
    let m = s.match(/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)/);
    if (m) {
        const v = m.slice(1, 4).map(Number);
        return Math.max(...v) - Math.min(...v) <= 16;
    }
    m = s.match(/^(oklch|lch)\(\s*[\d.]+%?\s+([\d.]+)/);
    if (m) return parseFloat(m[2]) <= (m[1] === 'oklch' ? 0.02 : 4);
    m = s.match(/^(oklab|lab)\(\s*[\d.]+%?\s+(-?[\d.]+)\s+(-?[\d.]+)/);
    if (m) return Math.hypot(parseFloat(m[2]), parseFloat(m[3])) <= (m[1] === 'oklab' ? 0.02 : 4);
    return false;
}

/** Advice 12 (with the step context of advice 4): a step number standing alone ("1.", "2", "03)", "١."). */
export function stepNumber(text) {
    return /^\s*[0-9٠-٩۰-۹]{1,3}[.)]?\s*$/u.test(String(text || ''));
}

/**
 * Advice 10: the kind of a list item, so that only items of one kind are compared (a closing tile is not a twelfth
 * service card): the tag and its classes without BEM modifiers (x--y) and state classes (is-, has-, js-).
 */
export function itemKind(tag, classes) {
    return `${String(tag || '').toUpperCase()}|${[...(classes || [])].filter((c) => !/--|^(?:is|has|js)-/.test(c)).sort().join(' ')}`;
}

// ------------------------------------------------------------------------------------------ set by: the stylesheet file

/** A selector as the cascade and the source both write it: whitespace collapsed, none around combinators and commas. */
export function normSelector(text) {
    return String(text || '').replace(/\s+/g, ' ').replace(/\s*([>+~,()])\s*/g, '$1').trim();
}

/** A declaration as "property:value" without whitespace or the closing semicolon; "" when it is not a declaration. */
export function normDeclaration(text) {
    const s = String(text || '').trim().replace(/;\s*$/, '');
    const colon = s.indexOf(':');
    if (colon <= 0) return '';
    const prop = s.slice(0, colon).trim().toLowerCase();
    if (!/^-?[a-z][a-z0-9-]*$|^--[\w-]+$/.test(prop)) return '';
    return `${prop}:${s.slice(colon + 1).replace(/\s+/g, '')}`;
}

/**
 * The style rules of a stylesheet's text, for the "set by" file lookup: [{ selector, parents (nearest first), layer,
 * decls }] with normalised selectors and declarations. Comments and strings are skipped; nested rules (CSS nesting)
 * and conditional at-rule blocks (@layer, @media, @supports, @container) are walked; a rule's declarations are its
 * own, not those of the rules nested in it; @layer statements, @import, @font-face, @keyframes and @property add
 * nothing.
 */
export function cssRules(text) {
    const src = String(text || '').replace(/\/\*[\s\S]*?\*\//g, ' ');
    const out = [];
    const stack = [];
    let buf = '';
    let quote = null;
    let paren = 0;
    const declaration = () => {
        const d = buf.trim();
        buf = '';
        const top = stack[stack.length - 1];
        if (!d || !top || !top.rule) return;
        const n = normDeclaration(d);
        if (n) top.rule.decls.push(n);
    };
    for (let i = 0; i < src.length; i += 1) {
        const c = src[i];
        if (quote) {
            buf += c;
            if (c === '\\' && i + 1 < src.length) buf += src[++i];
            else if (c === quote) quote = null;
            continue;
        }
        if (c === '"' || c === "'") {
            quote = c;
            buf += c;
            continue;
        }
        if (c === '(' || c === ')' || paren > 0) {
            if (c === '(') paren += 1;
            else if (c === ')') paren = Math.max(0, paren - 1);
            buf += c;
            continue;
        }
        if (c === '{') {
            const prelude = buf.trim();
            buf = '';
            const inert = stack.some((s) => s.inert);
            if (prelude.startsWith('@')) {
                const layer = prelude.match(/^@layer\s+([\w.-]+)\s*$/i);
                stack.push({ layer: layer ? layer[1] : null, inert: inert || /^@(?:-webkit-)?(?:keyframes|font-face|property|counter-style|page|font-feature-values)\b/i.test(prelude) });
            } else if (inert) {
                stack.push({ inert: true });
            } else {
                const parents = stack.filter((s) => s.rule).map((s) => s.rule.selector).reverse();
                const layerAt = [...stack].reverse().find((s) => s.layer);
                const rule = { selector: normSelector(prelude), parents, layer: layerAt ? layerAt.layer : '', decls: [] };
                out.push(rule);
                stack.push({ rule });
            }
            continue;
        }
        if (c === ';') {
            declaration();
            continue;
        }
        if (c === '}') {
            declaration();
            stack.pop();
            continue;
        }
        buf += c;
    }
    return out;
}

/**
 * The file that holds a winning rule (CSS.getMatchedStylesForNode): w = { selector, parents (the protocol's
 * nestingSelectors, nearest first), layer (innermost), decls (the authored declarations that set the value),
 * sheetPath (the stylesheet URL's path) }. The stylesheet's own file when it holds the rule (a separate entry such as
 * styleguide.css); else the one file with a rule of this selector and these declarations, the nesting chain and then
 * the layer deciding between files; else (a build that rewrote the selector) the one file with these declarations.
 * null when no file or more than one fits. index: buildCssIndex().
 */
export function findRuleFile(index, w) {
    const want = (w.decls || []).map(normDeclaration).filter(Boolean);
    if (!index || !index.files || !want.length) return null;
    const sel = normSelector(w.selector);
    const nest = (w.parents || []).map(normSelector).join('\u0000');
    const layer = w.layer || '';
    const has = (r) => want.every((d) => r.decls.includes(d));
    if (w.sheetPath && index.files.has(w.sheetPath) && index.files.get(w.sheetPath).some((r) => r.selector === sel && has(r))) return w.sheetPath;
    const oneFile = (list) => {
        const files = [...new Set(list.map((c) => c.file))];
        return files.length === 1 ? files[0] : null;
    };
    const exact = [];
    for (const [file, rules] of index.files) for (const r of rules) if (r.selector === sel && has(r)) exact.push({ file, r });
    if (exact.length) {
        const one = oneFile(exact);
        if (one) return one;
        const byNest = exact.filter((c) => c.r.parents.join('\u0000') === nest);
        const pool = byNest.length ? byNest : exact;
        return oneFile(byNest) ?? oneFile(pool.filter((c) => c.r.layer === layer));
    }
    // The last compound of each selector of the list (".timeline__dot" of "& .timeline__dot" and of a flattened
    // ".timeline--full .timeline__list .timeline__dot") must be shared: equal declarations elsewhere do not count.
    const tails = (s) => new Set(s.split(',').map((x) => x.trim().split(/[\s>+~]+/).pop().replace(/^&/, '')).filter(Boolean));
    const want2 = tails(sel);
    const loose = [];
    for (const [file, rules] of index.files) {
        for (const r of rules) if (has(r) && (!layer || r.layer === layer) && [...tails(r.selector)].some((t) => want2.has(t))) loose.push({ file, r });
    }
    return oneFile(loose);
}

/** Reads every .css file under the folders (repo-relative) into { files: Map(path -> cssRules) }. */
export function buildCssIndex(dirs = CSS_DIRS, root = ROOT) {
    const files = new Map();
    const walk = (dir) => {
        for (const name of readdirSync(join(root, dir)).sort()) {
            const rel = `${dir}/${name}`;
            if (statSync(join(root, rel)).isDirectory()) walk(rel);
            else if (name.endsWith('.css')) files.set(rel, cssRules(readFileSync(join(root, rel), 'utf8')));
        }
    };
    for (const dir of dirs) if (existsSync(join(root, dir))) walk(dir);
    return { files };
}

/** A path pattern of AREA_FILES as a regular expression ("*" within one folder, {a,b} alternatives). */
export function globRegExp(pattern) {
    let re = '';
    for (const part of String(pattern).split(/(\{[^}]*\}|\*)/)) {
        if (part === '*') re += '[^/]*';
        else if (/^\{[^}]*\}$/.test(part)) re += `(?:${part.slice(1, -1).split(',').map((a) => a.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})`;
        else re += part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    return new RegExp(`^${re}$`);
}

/** The area that maintains a stylesheet (AREA_FILES), or null (unknown or shared: the location decides). */
export function areaForFile(file, map = AREA_FILES) {
    if (!file) return null;
    for (const [area, patterns] of Object.entries(map)) for (const p of patterns) if (globRegExp(p).test(file)) return area;
    return null;
}

/**
 * Where a violation is fixed. d: { design, rule, page, sg, shellPart, region, sheet, homeSection, hours, component,
 * base } (region: header, palette, sheet, footer, main, body; sg: the styleguide section, "sg-" removed; shellPart:
 * "footer" inside a footer, tab bar, sheet or sticky-CTA specimen).
 */
export function areaFor(d) {
    if (d.design !== '2') return 'design-1';
    if (d.rule === 15 || d.hours) return 'time-digits';
    if (d.page === 'styleguide' && d.region === 'main') {
        // Each section goes to the area that maintains it in Phase 1; the frame (page head, contents) to platform.
        const footer = d.shellPart === 'footer';
        const map = {
            foundations: 'platform', type: 'platform', icons: 'components', controls: 'components', surfaces: 'components',
            upload: 'components', documents: 'home-a', shell: footer ? 'footer-shell' : 'header',
            mobile: footer ? 'footer-shell' : 'components', hero: 'home-a', sections: 'home-a', navigator: 'home-a',
            tools: 'home-b', cards: 'home-b', search: 'home-b', wizard: 'home-b', inner: 'inner',
        };
        return (d.sg && map[d.sg]) || 'platform';
    }
    const cssRule = [1, 2, 6, 7, 8, 9, 10, 16].includes(d.rule);
    if (cssRule && d.base) return 'platform';
    if (cssRule && d.component) return 'components';
    if (d.region === 'header') return 'header';
    if (d.region === 'palette') return 'home-b';
    if (d.region === 'footer') return 'footer-shell';
    if (d.region === 'sheet') return d.sheet === 'toc' ? 'inner' : d.sheet === 'navigator' ? 'home-a' : 'footer-shell';
    if (d.region === 'main') {
        if (d.page !== 'home') return 'inner';
        return ['tools', 'who-we-help', 'about', 'case-studies', 'guides', 'questions', 'consultation-form'].includes(d.homeSection) ? 'home-b' : 'home-a';
    }
    return 'platform';
}

/**
 * The longhand names that set a computed property, for the cascade lookup ("set by"): a physical side of margin or
 * padding also answers to its logical name (direction-aware); gap shorthands expand into row-gap / column-gap.
 */
export function longhandNames(prop, rtl) {
    const m = String(prop).match(/^(margin|padding)-(top|bottom|left|right)$/);
    if (!m) return [prop];
    const [, kind, side] = m;
    const logical = { top: 'block-start', bottom: 'block-end', left: rtl ? 'inline-end' : 'inline-start', right: rtl ? 'inline-start' : 'inline-end' }[side];
    return [prop, `${kind}-${logical}`];
}

/**
 * The declaration that wins for a set of longhand names, from a CSS.getMatchedStylesForNode answer (DevTools
 * protocol; rules in ascending cascade order, author shorthands expanded into longhands): "user agent: td
 * { padding-inline-start: 1px }", "@layer components .x { gap: 10px }", "inline style { … }", or null when no rule
 * sets it (the initial value). For a pseudo-element pass pseudo "before" or "after". inherit (font-size,
 * font-weight): when the element's own rules do not set it, the pseudo-element's host and then the ancestors
 * (nearest first) are searched, and the answer starts with "inherited: ". !important is not weighed.
 */
export function cssSource(matched, names, pseudo = null, inherit = false) {
    const win = cssWinner(matched, names, pseudo, inherit);
    return win ? describeWinner(win, names) : null;
}

/**
 * The winning declaration's holder for a set of longhand names (see cssSource): { rule } (a CSS rule of the protocol)
 * or { inline } (an inline style), with inherited: true when an ancestor (or a pseudo-element's host) sets it; null
 * when nothing sets it.
 */
export function cssWinner(matched, names, pseudo = null, inherit = false) {
    if (!matched) return null;
    const sets = (style) => (style?.cssProperties ?? []).some((p) => names.includes(p.name) && !p.disabled && p.parsedOk !== false);
    const own = pseudo ? (matched.pseudoElements ?? []).find((p) => p.pseudoType === pseudo)?.matches ?? [] : matched.matchedCSSRules ?? [];
    const levels = [{ rules: own, inline: pseudo ? null : matched.inlineStyle }];
    if (inherit) {
        if (pseudo) levels.push({ rules: matched.matchedCSSRules ?? [], inline: matched.inlineStyle });
        for (const entry of matched.inherited ?? []) levels.push({ rules: entry.matchedCSSRules ?? [], inline: entry.inlineStyle });
    }
    for (const [i, level] of levels.entries()) {
        if (sets(level.inline)) return { inline: level.inline, inherited: i > 0 };
        let win = null;
        for (const m of level.rules) if (sets(m.rule?.style)) win = m.rule;
        if (win) return { rule: win, inherited: i > 0 };
    }
    return null;
}

/** The authored declarations of a winner that set the names: the longhand itself or a shorthand of it (margin, gap …). */
export function winnerDeclarations(win, names) {
    const style = win?.rule ? win.rule.style : win?.inline;
    const gapShorthands = ['gap', 'grid-gap', 'grid-row-gap', 'grid-column-gap'];
    const covers = (p) => names.includes(p) || names.some((n) => n.startsWith(`${p}-`) || (/^(?:row|column)-gap$/.test(n) && gapShorthands.includes(p)));
    return (style?.cssProperties ?? []).filter((p) => p.text && !p.disabled && covers(p.name)).map((p) => p.text.trim().replace(/;$/, ''));
}

/** "set by" text of a cssWinner() answer (see cssSource). */
export function describeWinner(win, names) {
    const prefix = win.inherited ? 'inherited: ' : '';
    const authored = winnerDeclarations(win, names).join('; ');
    if (win.inline) return `${prefix}inline style { ${authored || names[0]} } (set by script)`;
    const rule = win.rule;
    const selector = rule.selectorList?.text ?? '?';
    if (rule.origin === 'user-agent') {
        const decl = rule.style.cssProperties.filter((p) => names.includes(p.name)).map((p) => `${p.name}: ${p.value}`).join('; ');
        return `${prefix}user agent: ${selector} { ${decl} }`;
    }
    const layer = (rule.layers ?? []).map((l) => l.text).filter(Boolean).join('.');
    const decls = authored || rule.style.cssProperties.filter((p) => names.includes(p.name)).map((p) => `${p.name}: ${p.value}`).join('; ');
    // A nested rule ("& .x") names its parents (nearest first in the protocol): ".parent { & .x { … } }".
    const nest = Array.isArray(rule.nestingSelectors) ? [...rule.nestingSelectors].reverse() : [];
    return `${prefix}${layer ? `@layer ${layer} ` : ''}${nest.map((s) => `${s} { `).join('')}${selector} { ${decls} }${nest.map(() => ' }').join('')}`;
}

// ------------------------------------------------------------------------------------------ DOM helpers (page only)

/**
 * Rules 9 and 10: the linear map of everything that draws an element's ancestors (their own rotate, scale and
 * transform, ownLinear), from the root down to its parent; SVG content inside an <svg> is skipped (its own geometry).
 */
export function ancestorLinear(el) {
    let m = [1, 0, 0, 1];
    for (let a = el ? el.parentElement : null; a; a = a.parentElement) {
        if (a instanceof SVGElement && !(a instanceof SVGSVGElement)) continue;
        const cs = getComputedStyle(a);
        m = mulLinear(ownLinear({ display: cs.display, tag: a.tagName, scale: cs.scale, rotate: cs.rotate, transform: cs.transform }), m);
    }
    return m;
}

/** A readable, index-free selector: up to three levels, prefixed by the nearest stable id or landmark. */
export function domSelector(el) {
    if (!el || el.nodeType !== 1) return String(el && el.nodeName);
    const stateClass = /^(?:is-|has-|js-|no-js$|x-|hero-armed|hero-live)/;
    const autoId = /^(?:d2-tip-|tip-|alpine-|x-|radix-|headlessui-)/;
    const short = (e) => {
        let s = e.tagName.toLowerCase();
        if (e.id && !autoId.test(e.id)) s += `#${e.id}`;
        const cls = [...e.classList].filter((c) => !stateClass.test(c) && c !== 'data-lint-root').slice(0, 2);
        for (const c of cls) s += `.${c}`;
        return s;
    };
    const chain = [];
    let e = el;
    for (; e && e.nodeType === 1 && e !== document.body && e !== document.documentElement && chain.length < 3; e = e.parentElement) {
        chain.unshift(short(e));
        if (e.id && !autoId.test(e.id)) return chain.join(' > ');
    }
    let anchor = null;
    for (let a = e; a && a !== document.body && a !== document.documentElement; a = a.parentElement) {
        if ((a.id && !autoId.test(a.id)) || /^(HEADER|FOOTER|MAIN|NAV|ASIDE|DIALOG|SECTION)$/.test(a.tagName)) {
            anchor = a;
            break;
        }
    }
    return anchor ? `${short(anchor)} ${chain.join(' > ')}` : chain.join(' > ');
}

export function textExcerpt(el, max = 120) {
    if (!el) return '';
    const label = el.nodeType === 1 && !el.textContent.trim() ? el.getAttribute('aria-label') || el.getAttribute('title') || '' : '';
    return String(label || el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, max);
}

/** The area of an element for a rule (areaFor on what the page shows about it). */
export function domArea(el, rule) {
    const html = document.documentElement;
    const design = html.getAttribute('data-design') === '2' ? '2' : '1';
    const pageKey = html.getAttribute('data-page') || '';
    if (design !== '2') return 'design-1';
    // A tooltip bubble drawn in a layer at the end of <body> belongs where its trigger is (aria-describedby, or the
    // [data-tooltip] whose text it shows).
    const tip = el.closest('[role="tooltip"][id]');
    if (tip && !tip.parentElement?.closest('[data-tooltip-host]') && !tip.parentElement?.closest('a[href], button, [role="button"]')) {
        const text = (tip.textContent || '').replace(/\s+/g, ' ').trim();
        const trigger = document.querySelector(`[aria-describedby~="${CSS.escape(tip.id)}"]`) || [...document.querySelectorAll('[data-tooltip]')].find((t) => (t.getAttribute('data-tooltip') || '').replace(/\s+/g, ' ').trim() === text);
        if (trigger && !trigger.closest('[role="tooltip"]')) return domArea(trigger, rule);
    }
    const main = document.querySelector('main');
    const landmark = (tag) => [...document.getElementsByTagName(tag)].find((x) => !x.parentElement || !x.parentElement.closest('main, article, aside, nav, section')) || null;
    const banner = landmark('header');
    const contentinfo = landmark('footer');
    let region = 'body';
    let sheet = null;
    const sheetEl = el.closest('[data-sheet]');
    if (el.closest('dialog.palette, #modal-palette, [data-modal="palette"]')) region = 'palette';
    else if (sheetEl) {
        region = 'sheet';
        sheet = sheetEl.getAttribute('data-sheet') || '';
    } else if (el.closest('#d2-megamenu, .megamenu, #d2-langmenu, .langmenu') || (banner && banner.contains(el))) region = 'header';
    else if ((contentinfo && contentinfo.contains(el)) || el.closest('.tabbar, [data-tabbar], .sticky-cta, [data-sticky-cta], .toast-region')) region = 'footer';
    else if (main && main.contains(el)) region = 'main';
    let homeSection = null;
    if (region === 'main' && pageKey === 'home') {
        let s = el;
        while (s.parentElement && s.parentElement !== main) s = s.parentElement;
        homeSection = s.id || [...s.classList][0] || '';
    }
    const sgSection = pageKey === 'styleguide' ? el.closest('section.sg-section[id^="sg-"]') || el.closest('section[id^="sg-"]') : null;
    const kit = new Set(['btn', 'chip', 'card', 'field', 'input', 'select', 'textarea', 'checkbox', 'radio', 'badge', 'segmented', 'seg', 'tabs', 'tab', 'acc', 'accordion', 'callout', 'steps', 'progress', 'kbd', 'skeleton', 'tooltip', 'toast', 'modal', 'sheet', 'popover', 'carousel', 'stepper', 'icon', 'icon-button', 'iconbtn', 'doc', 'upload', 'dropzone']);
    let component = null;
    for (let e = el, depth = 0; e && e !== document.body && depth < 2 && !component; e = e.parentElement, depth += 1) {
        for (const c of e.classList) {
            const block = c.split(/__|--/)[0];
            if (kit.has(block)) {
                component = block;
                break;
            }
        }
    }
    const baseTags = /^(STRONG|B|EM|I|CODE|KBD|PRE|SAMP|SMALL|MARK|ABBR|SUB|SUP|HR|BLOCKQUOTE|CITE|Q|DFN|VAR|ADDRESS|U|S|DEL|INS)$/;
    return areaFor({
        design,
        rule,
        page: pageKey,
        sg: sgSection ? sgSection.id.slice(3) : null,
        shellPart: sgSection && el.closest('footer, .d2-footer, .tabbar, [data-tabbar], .sticky-cta, [data-sticky-cta], [data-sheet], .sheet') ? 'footer' : null,
        region,
        sheet,
        homeSection,
        hours: Boolean(el.closest('[data-hours-status], .hours-status')),
        component,
        base: baseTags.test(el.tagName) && !el.getAttribute('class'),
    });
}

// ------------------------------------------------------------------------------------------ the page pass

/**
 * Runs in the page: rules 1-7, 9-15 (8 and 16 run from Node). o: { rules, width, scope (selector or null),
 * layout (rules 11 and 12), whatsapp (rule 14), wa01 {en, ar, ru}, maxPerRule }.
 */
export function pageLint(o) {
    const html = document.documentElement;
    const body = document.body;
    const page = { lang: (html.getAttribute('lang') || '').toLowerCase(), key: html.getAttribute('data-page') || '' };
    const arabicPage = /^ar\b/.test(page.lang);
    const rules = new Set(o.rules);
    const counts = {};
    const records = new Map();
    const info = {
        elements: 0,
        textNodes: 0,
        illustrations: document.querySelectorAll('[data-illustration]').length,
        overlays: document.querySelectorAll('[data-overlay]').length,
        unmarkedDocs: [...document.querySelectorAll('.doc[data-doc], [data-doc]')].filter((d) => !d.closest('[data-illustration]')).length,
        ignoredRelative: 0,
        placeholders: 0,
        waLinks: 0,
        waHidden: 0,
        waNoNewTab: 0,
        waMarkSources: [],
        digitNodes: { western: 0, eastern: 0 },
        svgArt: 0,
        svgArtSamples: [],
        truncated: 0,
    };
    // The scope: the body, or every element the selector matches (a state marks the regions it changed), the
    // outermost ones only (a region inside another is walked once).
    const marked = o.scope ? [...document.querySelectorAll(o.scope)] : [body];
    const roots = marked.filter((r) => !marked.some((x) => x !== r && x.contains(r)));
    if (!roots.length) return { records: [], counts, info: { ...info, error: `scope not found: ${o.scope}` }, advice: [] };
    const inScope = (selector) => roots.flatMap((r) => [...(r.matches(selector) ? [r] : []), ...r.querySelectorAll(selector)]);
    const main = document.querySelector('main');
    const round = (n) => Math.round(n * 10) / 10;
    const kebab = (s) => s.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`);

    const probe = document.createElement('div');
    probe.style.boxShadow = 'var(--shadow-overlay)';
    body.appendChild(probe);
    const overlayShadow = getComputedStyle(probe).boxShadow;
    probe.remove();
    info.overlayShadow = overlayShadow;

    const push = (rule, el, value, text, extra) => {
        if (!rules.has(rule) || !el) return;
        counts[rule] = (counts[rule] || 0) + 1;
        const selector = domSelector(el);
        const v = String(value).slice(0, 300);
        const t = String(text || '').replace(/\s+/g, ' ').trim().slice(0, 120);
        const key = `${rule}\u0000${selector}\u0000${v}\u0000${t}`;
        const known = records.get(key);
        if (known) {
            known.count += 1;
            return;
        }
        if (records.size >= o.maxPerRule * 16) {
            info.truncated += 1;
            return;
        }
        // key: identity of the hit before Node rewrites anything (the states phase reports each key once per page).
        const rec = { rule, area: domArea(el, rule), selector, value: v, text: t, count: 1, key, ...(extra || {}) };
        if (rec.css && rec.exempt) delete rec.css;
        if (rec.css) {
            // Node asks the cascade which rule sets the value on this first element (cssSource, "set by").
            const id = `${o.probePrefix || 'p'}${probeSeq++}`;
            el.setAttribute('data-lint-probe', `${el.getAttribute('data-lint-probe') || ''} ${id}`.trim());
            rec.css = { id, ...rec.css };
        }
        records.set(key, rec);
    };
    let probeSeq = 0;
    // Advice: §3.6 patterns that no rule of §7.1 measures (4 decorative numbering, 10 the same metadata on every item,
    // 11 dashed boxes, 12 a coloured word in a heading). Reported apart, never counted.
    const advice = new Map();
    const pushAdvice = (pattern, el, value, text) => {
        if (!el) return;
        const selector = domSelector(el);
        const v = String(value).slice(0, 200);
        const t = String(text || '').replace(/\s+/g, ' ').trim().slice(0, 120);
        const key = `A${pattern}\u0000${selector}\u0000${v}\u0000${t}`;
        const known = advice.get(key);
        if (known) known.count += 1;
        else if (advice.size < o.maxPerRule) advice.set(key, { pattern, area: domArea(el, null), selector, value: v, text: t, count: 1, key });
    };
    // Rules 3, 5, 9, 10, 13 (and 16, from Node) on the sample paper of an illustration (.doc or [data-doc] inside
    // [data-illustration], and drawings: svg): the brief notes the exemption only for rules 1, 2, 6 and 7. By default
    // (art) these hits are recorded as exempt and reported apart, with counts; --illustrations=strict counts them as
    // violations (the brief's literal wording). Interface text that an illustration holds outside its paper (callout
    // labels, pin numbers) counts like any other text.
    const artExtra = (paper, extra) => (paper && !o.strictIllustrations ? { ...(extra || {}), exempt: 'illustration' } : extra);

    const clippedBox = (el, cs) => {
        if (cs.clipPath && /inset\(\s*50%/.test(cs.clipPath)) return true;
        if (cs.clip && /^rect\(\s*0(?:px)?,?\s*0(?:px)?,?\s*0(?:px)?,?\s*0(?:px)?\s*\)$/.test(cs.clip)) return true;
        if (cs.position === 'absolute' && cs.overflow === 'hidden' && parseFloat(cs.width) <= 1 && parseFloat(cs.height) <= 1) return true;
        return false;
    };
    const relativeValue = (el, prop) => {
        try {
            const v = el.computedStyleMap().get(prop);
            if (!v) return false;
            if (typeof CSSKeywordValue !== 'undefined' && v instanceof CSSKeywordValue) return true;
            return /%|auto/.test(String(v));
        } catch {
            return false;
        }
    };
    const linkish = 'a[href], button, [role="button"], [role="link"], [role="tab"], [role^="menuitem"], summary, input[type="submit"], input[type="button"]';
    const stepControls = '[data-carousel-step], [data-pager-step], [data-pager-toggle], [data-wizard-back], [data-wizard-next], [data-lint-allow~="arrow"]';
    const isIcon = (el) => {
        if (el.hasAttribute('data-brand')) return false;
        if (el.hasAttribute('data-icon')) return true;
        if (el.tagName.toLowerCase() !== 'svg' || el.classList.contains('icon-sprite')) return false;
        if (el.classList.contains('icon')) return true;
        return [...el.children].some((c) => c.tagName.toLowerCase() === 'use' && /#(?:d2i|i|icon)-/.test(c.getAttribute('href') || c.getAttribute('xlink:href') || ''));
    };

    const cssOf = (label, ...groups) => ({ css: { groups, pseudo: label ? label.trim().replace(/^::/, '') : null } });
    const cssInherited = (label, ...groups) => ({ css: { ...cssOf(label, ...groups).css, inherit: true } });
    const shadowChecks = (el, s, f, label) => {
        if (f.ill) return;
        const text = textExcerpt(el);
        if (visibleShadow(s.boxShadow) && !(f.ovl && s.boxShadow === overlayShadow)) push(6, el, `${label}box-shadow ${s.boxShadow}`, text, cssOf(label, ['box-shadow']));
        if (visibleShadow(s.textShadow)) push(6, el, `${label}text-shadow ${s.textShadow}`, text, cssOf(label, ['text-shadow']));
        if (/drop-shadow\(/.test(s.filter || '')) push(6, el, `${label}filter ${s.filter}`, text, cssOf(label, ['filter']));
        if (s.backdropFilter && s.backdropFilter !== 'none') push(6, el, `${label}backdrop-filter ${s.backdropFilter}`, text, cssOf(label, ['backdrop-filter', '-webkit-backdrop-filter']));
    };
    const gradientChecks = (el, s, f, label) => {
        if (f.ill) return;
        for (const prop of ['backgroundImage', 'borderImageSource', 'listStyleImage']) {
            if (hasGradient(s[prop])) {
                push(7, el, `${label}${kebab(prop)} ${s[prop].slice(0, 200)}`, textExcerpt(el), cssOf(label, [kebab(prop)]));
                return;
            }
        }
    };
    // ill: inside [data-illustration] (rules 1 and 2 are not measured there); paper: on its sample paper (rule 9 is art).
    // k: the vertical scale the text is drawn at (its box's and its ancestors' scale and transform, and CSS zoom): the
    // size is checked as it shows on screen, so a label sized ÷ k inside a canvas drawn at k reads its real size.
    // SVG <text> takes its scale from getScreenCTM (the viewBox and every HTML ancestor's transform included).
    // k0: the same without the zoom of a styleguide specimen (specimenZoom): a size that is off the scale only because
    // of that zoom, and still 13 px or more on screen, is recorded apart (exempt "specimen-zoom").
    const textStyleChecks = (el, s, text, label, ill, paper, k = 1, k0 = k) => {
        if (!ill && isMonospace(s.fontFamily)) push(1, el, `${label}font-family ${s.fontFamily.slice(0, 120)}`, text);
        const caps = ill ? null : trackedCaps({ textTransform: s.textTransform, fontVariantCaps: s.fontVariantCaps, letterSpacing: s.letterSpacing, fontSize: s.fontSize, text });
        if (caps) push(2, el, `${label}${caps}`, text);
        const computed = parseFloat(s.fontSize);
        let scale = k;
        let scale0 = k0;
        if (el instanceof SVGElement && typeof el.getScreenCTM === 'function') {
            const m = el.getScreenCTM();
            scale = m ? Math.hypot(m.c, m.d) : 1;
            scale0 = scale;
        }
        const size = computed * scale;
        const weight = parseFloat(s.fontWeight);
        const langEl = el.closest('[lang]');
        const arabic = /^ar\b/i.test(langEl ? langEl.getAttribute('lang') : page.lang);
        const sizes = typeScale(o.width, arabic);
        const problems = [];
        const groups = [];
        let specimenOnly = null;
        if (size >= 1 && !inTypeScale(size, sizes)) {
            const drawn = drawnLength(computed, scale);
            const below = size < 12.95 ? 'below 13 px' : '';
            const value = `font-size ${below && drawn.endsWith(')') ? `${drawn.slice(0, -1)}; ${below})` : `${drawn}${below ? ` (${below})` : ''}`}`;
            if (!below && Math.abs(scale0 - scale) > 0.0005 && inTypeScale(computed * scale0, sizes)) specimenOnly = value;
            else {
                problems.push(value);
                groups.push(['font-size']);
            }
        }
        if (![400, 500, 600].includes(weight)) {
            problems.push(`font-weight ${weight}`);
            groups.push(['font-weight']);
        }
        if (problems.length) push(9, el, `${label}${problems.join('; ')}`, text, artExtra(paper, cssInherited(label, ...groups)));
        if (specimenOnly) push(9, el, `${label}${specimenOnly}`, text, artExtra(paper, { exempt: 'specimen-zoom' }));
    };

    // Rule 5: text that a field's edge cuts. An input's placeholder (or a value the page set) is put in as the value
    // for a moment and its scroll width compared with the field; a closed select's chosen option is measured in a
    // probe with the select's font against the content box (when the select wraps its text, against its height).
    // Approved copy gets no exemption: it is never cut. Fields the states phase typed into are not measured.
    const fieldCut = (el, cs) => {
        if (el.tagName === 'INPUT') {
            if (!cuttableInput(el.getAttribute('type') || 'text')) return null;
            const own = el.value;
            const kind = own ? 'value' : 'placeholder';
            const text = own || el.getAttribute('placeholder') || '';
            if (!text.trim()) return null;
            // A native placeholder that is not painted (a wrapped copy shows instead: the FAQ and resources search
            // boxes) cannot be cut; the copy is ordinary text.
            if (!own) {
                const ph = getComputedStyle(el, '::placeholder');
                if (parseFloat(ph.opacity) === 0 || colorAlpha(ph.color) === 0 || ph.visibility === 'hidden') return null;
            }
            let over = 0;
            let width = 0;
            try {
                if (!own) el.value = text;
                if (el.value === text) {
                    over = el.scrollWidth - el.clientWidth;
                    width = el.scrollWidth;
                }
            } finally {
                if (!own) el.value = '';
            }
            return over > 1 ? { value: `${kind} cut by the field edge: ${Math.round(over)} px of ${Math.round(width)} px hidden`, text } : null;
        }
        if (el.tagName !== 'SELECT' || el.multiple || el.size > 1) return null;
        const option = el.selectedOptions && el.selectedOptions[0];
        const text = option ? String(option.label || option.textContent || '').replace(/\s+/g, ' ').trim() : '';
        if (!text) return null;
        const box = el.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
        const probeEl = document.createElement('span');
        for (const p of ['fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'fontStretch', 'letterSpacing', 'wordSpacing', 'fontFeatureSettings', 'fontVariationSettings', 'fontKerning', 'fontVariantNumeric', 'textTransform', 'lineHeight']) probeEl.style[p] = cs[p];
        probeEl.style.position = 'absolute';
        probeEl.style.visibility = 'hidden';
        probeEl.style.insetBlockStart = '0';
        probeEl.style.insetInlineStart = '0';
        probeEl.style.whiteSpace = 'pre';
        const langEl = el.closest('[lang]');
        if (langEl) probeEl.setAttribute('lang', langEl.getAttribute('lang'));
        probeEl.setAttribute('dir', cs.direction);
        probeEl.textContent = text;
        (el.parentElement || body).appendChild(probeEl);
        try {
            const need = probeEl.getBoundingClientRect().width;
            if (need <= box + 1) return null;
            if (!/^(?:nowrap|pre)$/.test(cs.whiteSpace)) {
                probeEl.style.display = 'block';
                probeEl.style.inlineSize = `${box}px`;
                probeEl.style.whiteSpace = cs.whiteSpace;
                probeEl.style.overflowWrap = cs.overflowWrap;
                probeEl.style.wordBreak = cs.wordBreak;
                const tall = probeEl.getBoundingClientRect().height;
                const room = el.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom);
                if (tall <= room + 1) return null;
                return { value: `chosen option cut by the field edge: ${Math.round(tall)} px of wrapped text in ${Math.round(room)} px`, text };
            }
            return { value: `chosen option cut by the field edge: ${Math.round(need - box)} px of ${Math.round(need)} px hidden`, text };
        } finally {
            probeEl.remove();
        }
    };

    // Advice §3.6 rule 4: numbering is allowed on steps (the workflow and the form steps).
    const stepContext = (el) => Boolean(el.closest('ol, [data-steps], [data-step], [data-wizard-steps], [data-wizard-steps-for], [aria-current="step"], [class*="step"]'));

    // ---------------------------------------------------------------- one walk over the scope
    const flagsOf = (el) => {
        const ill = Boolean(el.closest('[data-illustration]'));
        return { ill, paper: ill && Boolean(el.closest('.doc, [data-doc], svg')), ovl: Boolean(el.closest('[data-overlay]')), clip: false, icon: false };
    };
    const skip = /^(SCRIPT|STYLE|NOSCRIPT|TEMPLATE|HEAD|META|LINK|TITLE|IFRAME|OBJECT|EMBED|OPTION|OPTGROUP|DATALIST|BR|WBR)$/;
    const icons = [];
    const links = [];
    const generated = new Map();
    const sepHits = [];
    const easternBlocks = new Set();
    // lin: the linear map the element's parent is drawn with (ancestorLinear for a scope root); each element adds
    // its own rotate, scale and transform (rules 9 and 10 measure as drawn). spec / specZoom: inside a styleguide
    // specimen, and the CSS zoom that the specimen applies (a specimen's own magnification: the docked header
    // specimen zooms a 78rem bar into its card); components scale with transform or scale, which always count.
    const specimenSel = '.sg-specimen, [data-lint-specimen]';
    const specimenZoomAbove = (r) => {
        let z = 1;
        for (let a = r.parentElement; a && a.closest(specimenSel); a = a.parentElement) z *= parseFloat(getComputedStyle(a).zoom) || 1;
        return z;
    };
    const stack = roots.map((r) => [r, { ...flagsOf(r), lin: ancestorLinear(r), spec: Boolean(r.parentElement && r.parentElement.closest(specimenSel)), specZoom: specimenZoomAbove(r) }]).reverse();
    while (stack.length) {
        const [el, pf] = stack.pop();
        if (skip.test(el.tagName)) continue;
        const cs = getComputedStyle(el);
        if (cs.display === 'none') continue;
        info.elements += 1;
        const tag = el.tagName.toLowerCase();
        const svgRoot = tag === 'svg';
        const f = {
            ill: pf.ill || el.hasAttribute('data-illustration'),
            paper: pf.paper,
            ovl: pf.ovl || el.hasAttribute('data-overlay'),
            clip: pf.clip || clippedBox(el, cs),
            icon: pf.icon,
            lin: cs.scale === 'none' && cs.rotate === 'none' && cs.transform === 'none' ? pf.lin : mulLinear(pf.lin, ownLinear({ display: cs.display, tag: el.tagName, scale: cs.scale, rotate: cs.rotate, transform: cs.transform })),
            spec: pf.spec || el.matches(specimenSel),
            specZoom: pf.specZoom,
        };
        f.paper = f.paper || (f.ill && (svgRoot || el.matches('.doc, [data-doc]')));
        if (f.spec && cs.zoom !== '1') f.specZoom *= parseFloat(cs.zoom) || 1;
        // CSS zoom: computed lengths are unzoomed, currentCSSZoom is the zoom the element is drawn with; zoom0 leaves
        // out a styleguide specimen's own zoom.
        const zoom = typeof el.currentCSSZoom === 'number' && el.currentCSSZoom > 0 ? el.currentCSSZoom : 1;
        const zoom0 = zoom / (f.specZoom || 1);
        const drawnAt = linearScale(f.lin);
        const hasBox = cs.display !== 'contents' && el.getClientRects().length > 0;
        const shown = hasBox && cs.visibility === 'visible' && el.checkVisibility({ opacityProperty: true, visibilityProperty: true });
        const textShown = hasBox && cs.visibility === 'visible';

        if (shown) shadowChecks(el, cs, f, '');
        if (shown) gradientChecks(el, cs, f, '');
        if (svgRoot && shown && !f.ill && rules.has(7) && el.querySelector('linearGradient, radialGradient')) push(7, el, 'SVG linearGradient/radialGradient', textExcerpt(el.parentElement));

        // rule 13: collect icons (an icon's own descendants are part of it)
        if (!f.icon && isIcon(el)) {
            if (shown) icons.push([el, f.paper]);
            f.icon = true;
        }

        // advice §3.6 rule 11: a dashed or dotted box (three or four sides, at least 24 px each way)
        if (shown && !f.ill && !f.icon && !svgRoot) {
            const sides = dashedSides([cs.borderTopStyle, cs.borderRightStyle, cs.borderBottomStyle, cs.borderLeftStyle], [cs.borderTopWidth, cs.borderRightWidth, cs.borderBottomWidth, cs.borderLeftWidth]);
            if (sides >= 3) {
                const r = el.getBoundingClientRect();
                if (r.width >= 24 && r.height >= 24) {
                    const filled = [...el.querySelectorAll('input, select, textarea, button, a[href], img, video, canvas')].some((x) => x.getClientRects().length > 0);
                    const words = (el.innerText || '').replace(/\s+/g, ' ').trim();
                    pushAdvice(11, el, `${[cs.borderTopStyle, cs.borderRightStyle, cs.borderBottomStyle, cs.borderLeftStyle].find((s) => s === 'dashed' || s === 'dotted')} border on ${sides} sides${!filled && words.length <= 160 ? ' (an empty state?)' : ''}`, words);
                }
            }
        }

        // rule 10: spacing, as drawn: padding and gaps at the scale of the element's own box, margins at its parent's
        // (a margin is laid out in the parent's space); top and bottom on the vertical axis, left and right on the
        // horizontal one
        if (rules.has(10) && shown && !f.clip) {
            const bad = [];
            const groups = [];
            const specimenOnly = [];
            const ltr = cs.direction !== 'rtl';
            const sides = [['Top', 'block-start'], ['Bottom', 'block-end'], ['Left', ltr ? 'inline-start' : 'inline-end'], ['Right', ltr ? 'inline-end' : 'inline-start']];
            const parentAt = linearScale(pf.lin);
            // one length: off the scale as drawn (bad), or only because of a styleguide specimen's zoom (apart)
            const check = (raw, axis, name, longhands) => {
                const v = raw * axis * zoom;
                if (!Number.isFinite(v) || onSpacingScale(v)) return;
                if (relativeValue(el, longhands[0])) {
                    info.ignoredRelative += 1;
                    return;
                }
                const text = `${name} ${drawnLength(raw, axis * zoom)}`;
                if (zoom0 !== zoom && onSpacingScale(raw * axis * zoom0)) {
                    specimenOnly.push(text);
                    return;
                }
                bad.push(text);
                groups.push(longhands);
            };
            for (const kind of ['margin', 'padding']) {
                const at = kind === 'margin' ? parentAt : drawnAt;
                for (const [phys, logical] of sides) check(parseFloat(cs[`${kind}${phys}`]), phys === 'Top' || phys === 'Bottom' ? at.y : at.x, `${kind}-${logical}`, longhandNames(`${kind}-${phys.toLowerCase()}`, !ltr));
            }
            const gaps = /flex|grid/.test(cs.display) || cs.columnCount !== 'auto' || cs.columnWidth !== 'auto';
            if (gaps) {
                for (const [prop, name, axis] of [['rowGap', 'row-gap', drawnAt.y], ['columnGap', 'column-gap', drawnAt.x]]) {
                    if (cs[prop] !== 'normal') check(parseFloat(cs[prop]), axis, name, [name]);
                }
            }
            // Inside an illustration, a box that shows no text of its own and is no control is part of the drawing
            // (a folder, a sheet edge scaled with the art): art like the paper. A box with text (a callout label) counts.
            const drawing = f.ill && !el.matches(linkish) && ![...el.childNodes].some((n) => n.nodeType === 3 && /\S/.test(n.data));
            // parts: one per group, so Node can drop a side that a safe-area inset sets (isSafeAreaSource)
            if (bad.length) push(10, el, bad.join('; '), textExcerpt(el), artExtra(f.paper || drawing, { css: { groups, pseudo: null, parts: bad } }));
            if (specimenOnly.length) push(10, el, specimenOnly.join('; '), textExcerpt(el), artExtra(f.paper || drawing, { exempt: 'specimen-zoom' }));
        }

        // rule 5: text cut by CSS
        if (rules.has(5) && shown && !/^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName)) {
            const clamp = cs.webkitLineClamp && cs.webkitLineClamp !== 'none' ? cs.webkitLineClamp : cs.lineClamp && cs.lineClamp !== 'none' ? cs.lineClamp : null;
            if (clamp && el.scrollHeight > el.clientHeight + 1) push(5, el, `cut by line-clamp ${clamp} (${el.scrollHeight}px of text in ${el.clientHeight}px)`, textExcerpt(el), artExtra(f.paper));
            else if (cs.textOverflow === 'ellipsis' && cs.overflowX !== 'visible' && el.scrollWidth > el.clientWidth + 1) push(5, el, `cut by text-overflow: ellipsis (${el.scrollWidth}px of text in ${el.clientWidth}px)`, textExcerpt(el), artExtra(f.paper));
        }
        // rule 5: text a field's edge cuts (placeholders, values the page set, a closed select's chosen option)
        if (rules.has(5) && shown && !f.clip && (tag === 'input' || tag === 'select') && !el.hasAttribute('data-lint-filled')) {
            const cut = fieldCut(el, cs);
            if (cut) push(5, el, cut.value, cut.text, artExtra(f.paper));
        }

        if (svgRoot) {
            // Advice (not a rule of §7.1): an inline SVG drawing outside illustrations that is neither an icon, a
            // brand mark nor a logo (its own, parent or grandparent class names logo, lockup, brand, wordmark or
            // emblem), larger than 64 px (generated covers, patterns: §3.6 rule 9, D27).
            const logoish = (e) => Boolean(e) && [...e.classList].some((c) => /(?:^|[-_])(?:logo|lockup|brand|wordmark|emblem)(?:$|[-_])/.test(c));
            if (shown && !f.ill && !f.icon && !el.closest('[data-brand]') && ![el, el.parentElement, el.parentElement && el.parentElement.parentElement].some(logoish)) {
                const r = el.getBoundingClientRect();
                if (r.width > 64 && r.height > 64) {
                    info.svgArt += 1;
                    if (info.svgArtSamples.length < 3) info.svgArtSamples.push(domSelector(el));
                }
            }
            // SVG internals are art or glyphs; only <text> is read (at its screen scale: getScreenCTM).
            if (shown) for (const t of el.querySelectorAll('text')) if (t.textContent.trim()) textStyleChecks(t, getComputedStyle(t), t.textContent, '', f.ill, f.paper);
            continue;
        }

        // rules 4 and 13: links and buttons
        if (el.matches(linkish) && textShown) links.push([el, f]);

        // text nodes: rules 3, 5, 15 and the text-bearing element (rules 1, 2, 9)
        let own = '';
        for (const n of el.childNodes) {
            if (n.nodeType !== 3 || !/\S/.test(n.data)) continue;
            own += n.data;
            info.textNodes += 1;
            if (!textShown) continue;
            for (const idx of separatorIndexes(n.data)) sepHits.push({ node: n, idx, el, clip: f.clip, ill: f.paper });
            if (rules.has(5) && main && main.contains(el) && endsWithEllipsis(n.data.trim())) push(5, el, 'ends with an ellipsis', n.data, artExtra(f.paper, { full: n.data.replace(/\s+/g, ' ').trim().slice(0, 400) }));
            if (zeroPadded(n.data) && !f.ill && !f.clip && !stepContext(el)) pushAdvice(4, el, 'zero-padded numbering on something that is not a step', n.data);
            if (arabicPage) {
                const west = /[0-9]/.test(n.data);
                const east = /[٠-٩۰-۹]/u.test(n.data);
                const island = el.closest('bdi[dir="ltr"], [dir="ltr"], [lang]:not([lang|="ar"]), a[href^="tel:"], a[href^="mailto:"]');
                if (!island) {
                    if (west) info.digitNodes.western += 1;
                    if (east) info.digitNodes.eastern += 1;
                }
                if (west && east) push(15, el, 'mixes 0-9 and ٠-٩ in one text node', n.data);
                else if ((west || east) && !island) {
                    let block = el;
                    while (block && block !== body && /^(inline|contents)$/.test(getComputedStyle(block).display)) block = block.parentElement;
                    if (block) easternBlocks.add(block);
                }
            }
        }
        if (own.trim() && shown && !f.clip) textStyleChecks(el, cs, own, '', f.ill, f.paper, drawnAt.y * zoom, drawnAt.y * zoom0);
        if (/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) && shown && !f.clip) {
            const type = (el.getAttribute('type') || 'text').toLowerCase();
            const value = el.tagName === 'SELECT' ? (el.selectedOptions && el.selectedOptions[0] ? el.selectedOptions[0].textContent : '') : el.value || el.getAttribute('placeholder') || '';
            if (!/^(hidden|checkbox|radio|range|color|file|image)$/.test(type) && value.trim()) textStyleChecks(el, cs, value, '', f.ill, f.paper, drawnAt.y * zoom, drawnAt.y * zoom0);
        }

        // generated content (::before, ::after)
        for (const pseudo of ['::before', '::after']) {
            const ps = getComputedStyle(el, pseudo);
            if (!ps.content || ps.content === 'none' || ps.content === 'normal' || ps.display === 'none') continue;
            if (shown) {
                shadowChecks(el, ps, f, `${pseudo} `);
                gradientChecks(el, ps, f, `${pseudo} `);
            }
            if (textShown && !f.ill && /decimal-leading-zero/.test(ps.content) && !stepContext(el)) pushAdvice(4, el, `${pseudo} ${ps.content.slice(0, 80)} on something that is not a step`, textExcerpt(el));
            const ptext = contentText(ps.content, (name) => el.getAttribute(name));
            if (!ptext.trim()) continue;
            if (shown && !f.clip) {
                const plin = ps.scale === 'none' && ps.rotate === 'none' && ps.transform === 'none' ? f.lin : mulLinear(f.lin, ownLinear({ display: ps.display, tag: '', scale: ps.scale, rotate: ps.rotate, transform: ps.transform }));
                textStyleChecks(el, ps, ptext, `${pseudo} `, f.ill, f.paper, linearScale(plin).y * zoom, linearScale(plin).y * zoom0);
            }
            if (textShown) for (const idx of separatorIndexes(ptext)) sepHits.push({ pseudo, host: el, text: ptext, idx, el, clip: f.clip, ill: f.paper });
            const host = el.closest(linkish);
            if (host) generated.set(host, `${generated.get(host) || ''} ${ptext}`);
        }

        for (let i = el.children.length - 1; i >= 0; i -= 1) stack.push([el.children[i], f]);
    }

    // ---------------------------------------------------------------- rule 4: arrows in links and buttons
    if (rules.has(4)) {
        for (const [el] of links) {
            if (el.closest(stepControls)) continue;
            const text = `${el.tagName === 'INPUT' ? el.value || '' : el.textContent || ''} ${generated.get(el) || ''}`;
            const arrows = arrowsIn(text);
            if (arrows.length) push(4, el, `arrow ${[...new Set(arrows)].join(' ')}`, text);
        }
    }

    // ---------------------------------------------------------------- rule 3: separators (same line, real text on both sides)
    if (rules.has(3) && sepHits.length) {
        const walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT);
        const visibleText = (n) => {
            const p = n.parentElement;
            if (!p || !/\S/.test(n.data) || p.closest('script, style, noscript, template, [aria-hidden="true"] svg')) return false;
            const s = getComputedStyle(p);
            return s.visibility === 'visible' && p.getClientRects().length > 0;
        };
        const neighbour = (node, index, dir) => {
            let n = node;
            let i = index + dir;
            walker.currentNode = node;
            for (let hops = 0; hops < 80 && n; hops += 1) {
                if (n.nodeType === 3) for (; i >= 0 && i < n.data.length; i += dir) if (!/\s/u.test(n.data[i])) return { node: n, i };
                do n = dir < 0 ? walker.previousNode() : walker.nextNode();
                while (n && !visibleText(n));
                if (!n) return null;
                i = dir < 0 ? n.data.length - 1 : 0;
            }
            return null;
        };
        const charBox = (node, i) => {
            const r = document.createRange();
            r.setStart(node, i);
            r.setEnd(node, Math.min(node.data.length, i + 1));
            return [...r.getClientRects()].find((b) => b.width > 0 || b.height > 0) || null;
        };
        const sameLine = (a, b) => Boolean(a && b) && Math.abs((a.top + a.bottom) / 2 - (b.top + b.bottom) / 2) < Math.max(a.height, b.height) * 0.6;
        const blockOf = (node) => {
            let e = node.nodeType === 1 ? node : node.parentElement;
            while (e && e !== body && /^(inline|contents)$/.test(getComputedStyle(e).display)) e = e.parentElement;
            return e || body;
        };
        const word = (n, i) => {
            let s = i;
            let e = i;
            while (s > 0 && !/\s/u.test(n.data[s - 1])) s -= 1;
            while (e < n.data.length - 1 && !/\s/u.test(n.data[e + 1])) e += 1;
            return n.data.slice(s, e + 1);
        };
        for (const hit of sepHits) {
            let prev;
            let next;
            let box;
            let block;
            let char;
            if (hit.pseudo) {
                char = hit.text[hit.idx];
                // Words of the generated string itself on either side ("SAMPLE · ILLUSTRATION" drawn from attr()):
                // a separator between two of them needs no neighbour in the DOM.
                const before = hit.text.slice(0, hit.idx).trim();
                const after = hit.text.slice(hit.idx + 1).trim();
                if (before && after) {
                    const around = `${before.split(/\s+/u).pop()} ${char} ${after.split(/\s+/u)[0]}`;
                    push(3, hit.el, `separator "${char}" (${hit.pseudo}) in "${around}"`, hit.text, artExtra(hit.ill, { around }));
                    continue;
                }
                walker.currentNode = hit.host;
                if (hit.pseudo === '::before') {
                    let p = walker.previousNode();
                    while (p && !visibleText(p)) p = walker.previousNode();
                    prev = p ? neighbour(p, p.data.length, -1) : null;
                    walker.currentNode = hit.host;
                    let q = walker.nextNode();
                    while (q && !visibleText(q)) q = walker.nextNode();
                    next = q ? neighbour(q, -1, 1) : null;
                } else {
                    const last = (() => {
                        const inner = document.createTreeWalker(hit.host, NodeFilter.SHOW_TEXT);
                        let l = null;
                        for (let t = inner.nextNode(); t; t = inner.nextNode()) if (visibleText(t)) l = t;
                        return l;
                    })();
                    prev = last ? neighbour(last, last.data.length, -1) : null;
                    let q = hit.host;
                    while (q && !q.nextSibling) q = q.parentElement;
                    walker.currentNode = q && q.nextSibling ? q.nextSibling : hit.host;
                    let t = walker.currentNode.nodeType === 3 ? walker.currentNode : walker.nextNode();
                    while (t && !visibleText(t)) t = walker.nextNode();
                    next = t ? neighbour(t, -1, 1) : null;
                }
                const hostRects = hit.host.getClientRects();
                box = (hit.pseudo === '::before' ? hostRects[0] : hostRects[hostRects.length - 1]) || null;
                block = blockOf(hit.host);
            } else {
                char = hit.node.data[hit.idx];
                prev = neighbour(hit.node, hit.idx, -1);
                next = neighbour(hit.node, hit.idx, 1);
                box = charBox(hit.node, hit.idx);
                block = blockOf(hit.node);
            }
            if (hit.pseudo) {
                // One side may still be inside the generated string (content: "· " attr(x)).
                const before = hit.text.slice(0, hit.idx).trim();
                const after = hit.text.slice(hit.idx + 1).trim();
                if (before) prev = { inside: before.split(/\s+/u).pop() };
                if (after) next = { inside: after.split(/\s+/u)[0] };
            }
            if (!prev || !next) continue;
            const pb = prev.inside ? box : charBox(prev.node, prev.i);
            const nb = next.inside ? box : charBox(next.node, next.i);
            const inBlock = (x) => Boolean(x.inside) || block.contains(x.node);
            const separator = hit.clip
                ? inBlock(prev) && inBlock(next)
                : (sameLine(box, pb) && sameLine(box, nb)) || (sameLine(box, pb) && inBlock(next)) || (sameLine(box, nb) && inBlock(prev));
            if (!separator) continue;
            const wordOf = (x) => x.inside ?? word(x.node, x.i);
            const around = `${wordOf(prev)} ${char} ${wordOf(next)}`;
            const context = hit.pseudo ? hit.host : block.contains(hit.el) && block !== body ? block : hit.el;
            push(3, hit.el, `separator "${char}"${hit.pseudo ? ` (${hit.pseudo})` : ''} in "${around}"`, textExcerpt(context), artExtra(hit.ill, { around }));
        }
    }

    // ---------------------------------------------------------------- rule 15: blocks whose pieces mix
    if (rules.has(15) && arabicPage) {
        for (const block of easternBlocks) {
            const w = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
            let west = false;
            let east = false;
            let mixedNode = false;
            for (let t = w.nextNode(); t; t = w.nextNode()) {
                const p = t.parentElement;
                if (!p || p.closest('bdi[dir="ltr"], [dir="ltr"], [lang]:not([lang|="ar"]), a[href^="tel:"], a[href^="mailto:"], script, style, template')) continue;
                if (getComputedStyle(p).visibility !== 'visible' || !p.getClientRects().length) continue;
                const a = /[0-9]/.test(t.data);
                const b = /[٠-٩۰-۹]/u.test(t.data);
                if (a && b) mixedNode = true;
                west ||= a;
                east ||= b;
            }
            if (west && east && !mixedNode) push(15, block, 'mixes 0-9 and ٠-٩ across inline elements', block.textContent);
        }
    }

    // ---------------------------------------------------------------- rule 13: icons
    if (rules.has(13)) {
        const tokenUp = (el, test, attr) => {
            for (let a = el, d = 0; a && a !== body && d < 8; a = a.parentElement, d += 1) {
                if (a.hasAttribute(attr) || [...a.classList].some((c) => test(c))) return a;
            }
            return null;
        };
        const isCard = (el) => el.hasAttribute('data-card') || [...el.classList].some(isCardToken);
        const cardUp = (el) => {
            for (let a = el; a && a !== body; a = a.parentElement) if (isCard(a)) return a;
            return null;
        };
        const menus = '[role="menu"], [role="menubar"], [role="listbox"], #d2-megamenu, .megamenu, #d2-langmenu, .langmenu, [data-langmenu], header nav, .d2-nav, [data-sheet="menu"] nav, .sheet--menu nav';
        for (const [icon, ill] of icons) {
            const link = icon.closest('a[href]');
            let externalLink = false;
            if (link) {
                try {
                    const u = new URL(link.href, location.href);
                    externalLink = /^https?:$/.test(u.protocol) && u.origin !== location.origin;
                } catch {
                    externalLink = false;
                }
            }
            const title = icon.closest('h1, h2, h3, h4, h5, h6, [class*="__title"], [data-card-title]');
            const item = icon.closest('[role^="menuitem"]') || (link && link.closest(menus) ? link : null);
            const near = (test) => {
                for (let a = icon.parentElement, d = 0; a && a !== body && d < 4; a = a.parentElement, d += 1) if (test(a)) return true;
                return false;
            };
            const ctx = {
                chip: Boolean(tokenUp(icon, isChipToken, 'data-chip')),
                kpi: Boolean(tokenUp(icon, isKpiToken, 'data-kpi')),
                cardTitle: Boolean(title && cardUp(title)),
                menuItem: Boolean(item),
                disclosure: Boolean(icon.closest('[aria-expanded], [aria-haspopup]:not([aria-haspopup="false"]), summary, select, [class*="select"]')),
                externalLink,
                inLink: Boolean(link),
                inCard: Boolean(cardUp(icon)) && !icon.closest('button, [role="button"]'),
                contact: Boolean(icon.closest('[data-contact-block], .contact-card, .cta-card')),
                step: Boolean(icon.closest(stepControls)),
                stepper: Boolean(icon.closest('[data-stepper], .stepper, button[data-step]')),
                date: near((a) => a.matches('[data-datepicker], .datepicker, .date-picker') || Boolean(a.querySelector('input[type="date"], [data-datepicker]'))),
                specimen: Boolean(icon.closest('[data-lint-specimen]')),
            };
            const name = icon.getAttribute('data-icon');
            let verdict = iconVerdict(name, ctx);
            if (!name) {
                const use = icon.querySelector('use');
                verdict = `icon without data-icon (${use ? use.getAttribute('href') || use.getAttribute('xlink:href') : icon.tagName.toLowerCase()})`;
            }
            if (verdict) push(13, icon, verdict, textExcerpt(icon.closest('a, button, label, li, h1, h2, h3, h4, h5, h6, p') || icon.parentElement), artExtra(ill));
        }
    }

    // ---------------------------------------------------------------- rule 14: WhatsApp links
    if (rules.has(14) && o.whatsapp) {
        // The draft of the link's own language: the page's, or an island's (a styleguide specimen in another
        // language carries lang="…" and that language's WA01).
        const langOf = (el) => {
            const l = el.closest('[lang]');
            return ((l && l.getAttribute('lang')) || page.lang).slice(0, 2).toLowerCase();
        };
        const sources = new Set();
        for (const a of inScope('a[href]')) {
            const href = a.getAttribute('href') || '';
            if (!isWhatsAppHref(href)) continue;
            const expected = (o.wa01 || {})[langOf(a)] || null;
            info.waLinks += 1;
            const linkShown = a.getClientRects().length > 0 && a.checkVisibility({ opacityProperty: true, visibilityProperty: true });
            if (!linkShown) info.waHidden += 1;
            if (a.getAttribute('target') !== '_blank' || !/\bnoopener\b/.test(a.rel) || !/\bnoreferrer\b/.test(a.rel)) info.waNoNewTab += 1;
            const marks = [...(a.matches('[data-brand="whatsapp"]') ? [a] : []), ...a.querySelectorAll('[data-brand="whatsapp"]')];
            const problems = [];
            const differs = compareWhatsApp(href, expected);
            if (differs) problems.push(differs);
            if (!marks.length) problems.push('no [data-brand="whatsapp"] mark');
            else if (linkShown && !marks.some((m) => m.getClientRects().length > 0 && m.checkVisibility({ opacityProperty: true, visibilityProperty: true }) && m.getBoundingClientRect().width > 0)) problems.push('the WhatsApp mark is not visible');
            // A shown link counts when the placeholder is what it shows (with one of Meta's two files dropped, the other
            // surface's slot stays a hidden placeholder); a hidden link (closed sheet) counts when it holds one.
            const slots = marks.flatMap((m) => [...(m.matches('[data-placeholder="whatsapp-logo"]') ? [m] : []), ...m.querySelectorAll('[data-placeholder="whatsapp-logo"]')]);
            if (slots.some((p) => !linkShown || (p.getClientRects().length > 0 && p.checkVisibility({ opacityProperty: true, visibilityProperty: true })))) info.placeholders += 1;
            for (const m of marks) {
                const img = m.matches('img') ? m : m.querySelector('img');
                const use = m.querySelector('use');
                sources.add(m.hasAttribute('data-placeholder') || m.querySelector('[data-placeholder]') ? 'placeholder' : img ? (img.currentSrc || img.src || '').replace(location.origin, '') : use ? use.getAttribute('href') : m.tagName.toLowerCase());
            }
            if (problems.length) push(14, a, problems.join('; '), textExcerpt(a), linkShown ? undefined : { hidden: true });
        }
        info.waMarkSources = [...sources].sort();
    }

    // ---------------------------------------------------------------- rules 11 and 12: layout of the page
    if (o.layout && (rules.has(11) || rules.has(12)) && !o.scope) {
        const rendered = (e) => e.getClientRects().length > 0 && getComputedStyle(e).visibility !== 'hidden' && e.checkVisibility({ opacityProperty: true, visibilityProperty: true });
        const landmark = (tag) => [...document.getElementsByTagName(tag)].find((x) => !x.parentElement || !x.parentElement.closest('main, article, aside, nav, section')) || null;
        if (rules.has(11)) {
            const edges = (c) => {
                const r = c.getBoundingClientRect();
                const s = getComputedStyle(c);
                return [r.left + parseFloat(s.borderLeftWidth) + parseFloat(s.paddingLeft), r.right - parseFloat(s.borderRightWidth) - parseFloat(s.paddingRight)];
            };
            const containersIn = (r) => {
                if (r.matches('.container')) return [r];
                return [...r.querySelectorAll('.container')].filter((c) => {
                    const up = c.parentElement ? c.parentElement.closest('.container') : null;
                    return (!up || !r.contains(up)) && rendered(c) && !c.closest('[data-overlay], [data-sheet], dialog');
                });
            };
            const hasContent = (e) => Boolean((e.innerText || '').trim()) || Boolean(e.querySelector('img, svg, video, canvas, input, button, select, textarea'));
            const bands = [];
            const banner = landmark('header');
            const contentinfo = landmark('footer');
            if (banner && rendered(banner)) bands.push({ el: banner, kind: 'header' });
            if (main && rendered(main)) {
                const mainW = main.getBoundingClientRect().width;
                const bandTag = /^(SECTION|ARTICLE|ASIDE|HEADER|FOOTER|NAV|FORM)$/;
                const visit = (parent) => {
                    let found = 0;
                    for (const child of parent.children) {
                        if (skip.test(child.tagName) || !rendered(child)) continue;
                        const s = getComputedStyle(child);
                        if (/^(fixed|sticky|absolute)$/.test(s.position)) continue;
                        const w = child.getBoundingClientRect().width;
                        if (w < mainW - 1.5) {
                            if (child.matches('.container')) {
                                bands.push({ el: child, kind: 'section' });
                                found += 1;
                            }
                            continue;
                        }
                        if (bandTag.test(child.tagName) || [...child.classList].some((c) => /^section(?:--|$)/.test(c))) {
                            bands.push({ el: child, kind: 'section' });
                            found += 1;
                            continue;
                        }
                        const inner = visit(child);
                        if (inner) {
                            found += inner;
                            continue;
                        }
                        if (child.matches('.container') || child.querySelector('.container') || hasContent(child)) {
                            bands.push({ el: child, kind: 'section' });
                            found += 1;
                        }
                    }
                    return found;
                };
                visit(main);
            }
            if (contentinfo && rendered(contentinfo)) bands.push({ el: contentinfo, kind: 'footer' });
            for (const b of bands) b.containers = containersIn(b.el);
            const refBand = bands.find((b) => b.kind === 'header' && b.containers.length) || bands.find((b) => b.containers.length);
            if (refBand) {
                const ref = edges(refBand.containers[0]);
                const refName = refBand.kind === 'header' ? 'the header' : domSelector(refBand.el);
                for (const b of bands) {
                    if (!b.containers.length) {
                        if (hasContent(b.el)) push(11, b.el, `no .container in this ${b.kind}`, textExcerpt(b.el));
                        continue;
                    }
                    for (const c of b.containers) {
                        const [l, r] = edges(c);
                        const dl = l - ref[0];
                        const dr = r - ref[1];
                        if (Math.abs(dl) > 1 || Math.abs(dr) > 1) {
                            const sign = (n) => `${n >= 0 ? '+' : ''}${round(n)}`;
                            push(11, b.el, `inner edges ${round(l)}..${round(r)} px, ${refName} ${round(ref[0])}..${round(ref[1])} px (left ${sign(dl)}, right ${sign(dr)})`, textExcerpt(c, 80));
                        }
                    }
                }
            }
        }
        if (rules.has(12) && main) {
            const isCard = (el) => el.hasAttribute('data-card') || [...el.classList].some(isCardToken);
            const all = [...main.querySelectorAll('[class*="card"], [data-card]')].filter((e) => isCard(e) && !e.closest('[data-illustration]') && rendered(e));
            const cards = all.filter((e) => !all.some((x) => x !== e && x.contains(e)));
            const groups = new Map();
            for (const card of cards) {
                let slot = card;
                let g = card.parentElement;
                while (g && g !== body && cards.filter((c) => g.contains(c)).length < 2) {
                    slot = g;
                    g = g.parentElement;
                }
                if (!g || g === body) continue;
                if (!groups.has(g)) groups.set(g, []);
                groups.get(g).push({ card, slot, r: slot.getBoundingClientRect() });
            }
            const firstShown = (list) => list.find((e) => rendered(e)) || null;
            const titleOf = (card) => firstShown([...card.querySelectorAll('h1, h2, h3, h4, h5, h6, [class*="__title"], [data-card-title]')]);
            const linkRowOf = (card) => {
                const title = titleOf(card);
                const marked = card.querySelector('[data-card-link]');
                if (marked && rendered(marked)) return marked;
                const list = [...card.querySelectorAll('a[href], button, [class*="__cta"], [class*="__read"], [class*="__more"], [class*="__link"], [class*="__footer"], [class*="__foot"], [class*="__actions"]')]
                    .filter((e) => !e.classList.contains('card__link') && !(title && (title.contains(e) || e.contains(title))) && rendered(e));
                return list.length ? list[list.length - 1] : null;
            };
            for (const [g, items] of groups) {
                // A styleguide specimen grid (sg-*) sets different components side by side: not a card row.
                if ([...g.classList].some((c) => c.startsWith('sg-'))) continue;
                const sorted = items.sort((a, b) => a.r.top - b.r.top || a.r.left - b.r.left);
                const rows = [];
                for (const it of sorted) {
                    const row = rows.find((rw) => Math.abs(rw.top - it.r.top) <= 2 && !rw.items.some((x) => x.r.left < it.r.right - 1 && it.r.left < x.r.right - 1));
                    if (row) row.items.push(it);
                    else rows.push({ top: it.r.top, items: [it] });
                }
                // Only like with like: a title or link row is compared with the same kind of element (tag and first
                // class) in the row's other cards, so a CTA tile's 44 px button is not measured against text links.
                const kindOf = (e) => `${e.tagName}.${e.classList[0] || ''}`;
                for (const row of rows) {
                    if (row.items.length < 2) continue;
                    for (const [what, pick] of [['title tops', titleOf], ['link rows', linkRowOf]]) {
                        const byKind = new Map();
                        for (const it of row.items) {
                            const e = pick(it.card);
                            if (!e) continue;
                            if (!byKind.has(kindOf(e))) byKind.set(kindOf(e), []);
                            byKind.get(kindOf(e)).push(e.getBoundingClientRect().top);
                        }
                        const spread = Math.max(0, ...[...byKind.values()].filter((t) => t.length > 1).map((t) => Math.max(...t) - Math.min(...t)));
                        if (spread > 1) push(12, g, `${what} differ by ${round(spread)} px in a row of ${row.items.length} cards`, textExcerpt(titleOf(row.items[0].card) || row.items[0].card, 80));
                    }
                }
            }
        }
    }

    // ---------------------------------------------------------------- advice (§3.6 rules 10 and 12; 4 and 11 above)
    const shownEl = (e) => e.getClientRects().length > 0 && getComputedStyle(e).visibility === 'visible' && e.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    const hiddenUpTo = (e, top) => {
        for (let a = e; a && a !== top.parentElement; a = a.parentElement) if (clippedBox(a, getComputedStyle(a))) return true;
        return false;
    };
    // §3.6 rule 12: a word in another colour than the rest of its heading (the colour most of its letters have). A run
    // in the neutral ink ramp (ink, ink-2, ink-3: grey, not a colour) and a step number of an allowed sequence ("1."
    // in a step context, as advice 4) are not coloured words.
    for (const h of inScope('h1, h2, h3, h4, h5, h6, [role="heading"]')) {
        if (h.closest('[data-illustration]') || !shownEl(h)) continue;
        const runs = [];
        const lengths = new Map();
        const walker = document.createTreeWalker(h, NodeFilter.SHOW_TEXT);
        for (let t = walker.nextNode(); t; t = walker.nextNode()) {
            const p = t.parentElement;
            const s = t.data.replace(/\s+/g, ' ').trim();
            if (!s || !p || p.closest('svg') || !shownEl(p) || hiddenUpTo(p, h)) continue;
            const color = getComputedStyle(p).color;
            lengths.set(color, (lengths.get(color) || 0) + s.length);
            runs.push([p, color, s]);
        }
        if (lengths.size < 2) continue;
        const dominant = [...lengths.entries()].sort((a, b) => b[1] - a[1])[0][0];
        const done = new Set();
        for (const [p, color, s] of runs) {
            if (color === dominant || done.has(p)) continue;
            done.add(p);
            if (neutralColor(color) || (stepNumber(s) && stepContext(p))) continue;
            pushAdvice(12, p, `${color} inside a heading in ${dominant}`, s);
        }
    }
    // §3.6 rule 10: the same short metadata on every item of a list or grid (three or more items of one kind: list
    // items, cards, articles with the same tag and classes, BEM modifiers and states aside, itemKind; a closing tile
    // is not one more card). Metadata: a text of at most 40 characters, not a heading, not the label of a control
    // inside the item (a card that is one link keeps its texts), not a card's link row or another underlined action,
    // not a label ending with a colon, not the label of a label/value pair (a <dt>, a header cell, a *__label /
    // *__term / *__key followed by its value: "What we check" names the value beside it on every item by design),
    // that is small (15 px or less), holds a digit, is a value (<dd>) or a time, or sits in a chip, badge, pill, tag,
    // meta, count, time, date, status or timeline element.
    const metaClass = /(?:^|[-_])(?:chip|badge|pill|tag|meta|kicker|eyebrow|count|time|date|status|timeline|lang|langs)(?:$|[-_])/;
    const isItem = (e) => (e.tagName === 'LI' && /^(UL|OL)$/.test(e.parentElement ? e.parentElement.tagName : '')) || e.tagName === 'ARTICLE' || e.hasAttribute('data-card') || [...e.classList].some(isCardToken);
    const metaTexts = (item) => {
        const out = new Set();
        for (const e of item.querySelectorAll('*')) {
            if (!e.childNodes.length || ![...e.childNodes].some((n) => n.nodeType === 3 && /\S/.test(n.data))) continue;
            if (e.closest('svg, script, style, template, h1, h2, h3, h4, h5, h6, [role="heading"], [class*="__title"]')) continue;
            const control = e.closest('a[href], button, summary, label, select, textarea, [role="button"], [role="link"], [role="tab"]');
            const text = (e.textContent || '').replace(/\s+/g, ' ').trim();
            if (!text || text.length > 40) continue;
            // the link row of a card ("Read the Guide", "Show the starting file", also inside a card that is one link)
            // is an action, not metadata; so is underlined text, and a label that introduces what follows ("…if:")
            let action = /[:：]$/.test(text) || getComputedStyle(e).textDecorationLine.includes('underline');
            for (let a = e; a && a !== item && !action; a = a.parentElement) {
                // (a card footer is not one: it can hold the metadata beside the action, "3 min read" next to the link)
                action = a.matches('[data-card-link], [class*="__cta"], [class*="__read"], [class*="__more"], [class*="__link"], [class*="__action"], [class*="__cue"], [class*="__open"]') && !a.querySelector('h1, h2, h3, h4, h5, h6, [role="heading"]');
            }
            if (action) continue;
            // a control inside the item: its text is a label, unless it is a link that holds the item's heading (a card
            // that is one link keeps its texts); a button or summary is always a control
            if (control && item.contains(control) && control !== item && (!control.matches('a[href], [role="link"]') || !control.querySelector('h1, h2, h3, h4, h5, h6, [role="heading"]'))) continue;
            const next = e.nextElementSibling;
            if (e.matches('dt, th') || (/__(?:label|term|key)\b/.test(e.getAttribute('class') || '') && next && (next.tagName === 'DD' || /__(?:value|def)\b/.test(next.getAttribute('class') || '')))) continue;
            if (!shownEl(e) || hiddenUpTo(e, item)) continue;
            let meta = parseFloat(getComputedStyle(e).fontSize) <= 15.5 || /\d/.test(text) || /^(TIME|DD)$/.test(e.tagName);
            for (let a = e; !meta && a && a !== item.parentElement; a = a.parentElement) if ([...a.classList].some((c) => metaClass.test(c))) meta = true;
            if (meta) out.add(text);
        }
        return out;
    };
    const itemGroups = new Map();
    for (const e of inScope('li, article, [data-card], [class*="card"]')) {
        if (!isItem(e) || e.closest('[data-illustration]') || !e.parentElement || !shownEl(e)) continue;
        const key = itemKind(e.tagName, [...e.classList]);
        if (!itemGroups.has(e.parentElement)) itemGroups.set(e.parentElement, new Map());
        const kinds = itemGroups.get(e.parentElement);
        if (!kinds.has(key)) kinds.set(key, []);
        kinds.get(key).push(e);
    }
    for (const [group, kinds] of itemGroups) {
        // a styleguide frame list (class sg-*: swatches, contrast pairs) annotates specimens: not a list of items
        if ([...group.classList].some((c) => c.startsWith('sg-'))) continue;
        for (const items of kinds.values()) {
            if (items.length < 3) continue;
            for (const t of commonTexts(items.map(metaTexts))) pushAdvice(10, group, `"${t}" on all ${items.length} items`, t);
        }
    }

    return { records: [...records.values()], counts, info, advice: [...advice.values()] };
}

// ------------------------------------------------------------------------------------------ hover, axe, overlays (page side)

/** Registers the outermost visible cards of main for the hover test; returns how many. */
export function hoverPrepare(o) {
    const main = document.querySelector('main');
    if (!main) return 0;
    const isCard = (el) => el.hasAttribute('data-card') || [...el.classList].some(isCardToken);
    const all = [...main.querySelectorAll('[class*="card"], [data-card]')].filter((e) => isCard(e) && !e.closest('[data-illustration]'));
    const outer = all.filter((e) => !all.some((x) => x !== e && x.contains(e)));
    window.__lintCards = outer.filter((e) => e.getClientRects().length > 0 && e.checkVisibility({ opacityProperty: true, visibilityProperty: true })).slice(0, o.max);
    return window.__lintCards.length;
}

/** One card: phase "rest" scrolls it to the centre and finds a point that hits it; "hover" measures again. */
export function hoverMeasure(arg) {
    const card = (window.__lintCards || [])[arg.i];
    if (!card || !card.isConnected) return null;
    if (arg.phase === 'rest') card.scrollIntoView({ block: 'center', inline: 'center', behavior: 'instant' });
    for (const a of document.getAnimations()) {
        try {
            const t = a.effect && a.effect.getComputedTiming ? a.effect.getComputedTiming() : null;
            if (t && Number.isFinite(t.endTime) && a.playState !== 'finished') a.finish();
        } catch {
            // an animation that cannot finish stays as it is
        }
    }
    const r = card.getBoundingClientRect();
    const media = [...card.querySelectorAll('img, picture, video, [class*="__media"] > *, [class*="portrait"]')].slice(0, 8).map((m) => {
        const b = m.getBoundingClientRect();
        return [b.x, b.y, b.width, b.height];
    });
    const out = { box: [r.x, r.y, r.width, r.height], shadow: getComputedStyle(card).boxShadow, media };
    if (arg.phase === 'rest') {
        out.selector = domSelector(card);
        out.area = domArea(card, 8);
        out.areaShadow = domArea(card, 6);
        out.text = textExcerpt(card.querySelector('h1, h2, h3, h4, h5, h6, [class*="__title"]') || card, 80);
        out.point = null;
        for (const [fx, fy] of [[0.5, 0.5], [0.5, 0.3], [0.3, 0.5], [0.7, 0.5], [0.5, 0.7], [0.2, 0.2], [0.8, 0.8]]) {
            const x = r.left + r.width * fx;
            const y = r.top + r.height * fy;
            if (x < 2 || y < 2 || x > innerWidth - 2 || y > innerHeight - 2) continue;
            const hit = document.elementFromPoint(x, y);
            if (hit && (hit === card || card.contains(hit))) {
                out.point = [x, y];
                break;
            }
        }
    }
    return out;
}

/** Stable selectors, areas and text for axe's node targets. */
export function describeTargets(list) {
    return list.map((sel) => {
        let el = null;
        try {
            el = document.querySelector(sel);
        } catch {
            el = null;
        }
        if (!el) return null;
        // ill: on the sample paper of an illustration (art); interface text an illustration holds elsewhere counts.
        return { selector: domSelector(el), area: domArea(el, 16), text: textExcerpt(el, 120), ill: Boolean(el.closest('[data-illustration]') && el.closest('.doc, [data-doc], svg')) };
    });
}

/** The overlay triggers outside main and the footer (header, tab bar): each marked data-lint-trigger="n". */
export function findTriggers() {
    const shown = (e) => e.getClientRects().length > 0 && e.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    const contentinfo = [...document.getElementsByTagName('footer')].find((x) => !x.parentElement || !x.parentElement.closest('main, article, aside, nav, section')) || null;
    const list = [];
    const seen = new Set();
    for (const el of document.querySelectorAll('[aria-controls][aria-expanded], [aria-haspopup]:not([aria-haspopup="false"]), [data-sheet-open], [data-modal-open], [data-d2-search]')) {
        if (el.closest('main, [data-illustration], [data-overlay], [data-sheet], dialog') || (contentinfo && contentinfo.contains(el)) || !shown(el)) continue;
        const target = el.getAttribute('data-sheet-open')
            ? `sheet:${el.getAttribute('data-sheet-open')}`
            : el.getAttribute('data-modal-open')
              ? `modal:${el.getAttribute('data-modal-open')}`
              : el.hasAttribute('data-d2-search')
                ? 'modal:palette'
                : el.getAttribute('aria-controls')
                  ? `#${el.getAttribute('aria-controls')}`
                  : null;
        if (!target || seen.has(target)) continue;
        seen.add(target);
        el.setAttribute('data-lint-trigger', String(list.length));
        list.push({ index: list.length, target, label: textExcerpt(el, 40) });
    }
    return list;
}

/** The open overlay of a trigger target, marked data-lint-root; null while it is not shown. */
export function overlayRoot(target) {
    const shown = (e) => e.getClientRects().length > 0 && e.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    for (const old of document.querySelectorAll('[data-lint-root]')) old.removeAttribute('data-lint-root');
    let el = null;
    if (target.startsWith('#')) el = document.getElementById(target.slice(1));
    else if (target.startsWith('sheet:')) el = document.querySelector(`[data-sheet="${CSS.escape(target.slice(6))}"]`);
    else if (target.startsWith('modal:')) {
        const id = target.slice(6);
        el = document.querySelector(`dialog[data-modal="${CSS.escape(id)}"][open], #modal-${CSS.escape(id)}[open]`) || document.querySelector('dialog[open]');
    }
    if (!el) return null;
    let rootEl = el;
    if (!shown(el)) {
        const panel = [...el.querySelectorAll('[role="dialog"], dialog, [class*="__panel"]')].find(shown);
        if (!panel) return null;
        rootEl = panel;
    }
    if (el.matches('[data-sheet]') && !el.matches('.is-open, [open]') && !el.querySelector('.is-open, [open]')) {
        const panel = [...el.querySelectorAll('[role="dialog"], [class*="__panel"]')].find(shown);
        if (!panel) return null;
    }
    rootEl.setAttribute('data-lint-root', '');
    return { id: rootEl.id || target };
}

// ------------------------------------------------------------------------------------------ states phase (page side)

/**
 * Opens every <details> of main (an exclusive group, <details name>, loses its name first, so all its answers stay
 * open together) and expands every collapsed in-page disclosure: a shown button with aria-expanded="false" and
 * aria-controls whose target is in main and is not a tab, menu, sheet or dialog. Each button is clicked once (an
 * accordion that closes the others keeps its last one open). Returns how many of each.
 */
export function openDisclosures() {
    const main = document.querySelector('main');
    if (!main) return { details: 0, buttons: 0 };
    const shown = (e) => e.getClientRects().length > 0 && e.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    let details = 0;
    let buttons = 0;
    const clicked = new Set();
    for (let pass = 0; pass < 3; pass += 1) {
        let changed = 0;
        for (const d of main.querySelectorAll('details')) {
            if (d.hasAttribute('name')) {
                d.setAttribute('data-lint-name', d.getAttribute('name'));
                d.removeAttribute('name');
            }
            if (!d.open) {
                d.open = true;
                details += 1;
                changed += 1;
            }
        }
        for (const b of main.querySelectorAll('button[aria-expanded="false"][aria-controls], [role="button"][aria-expanded="false"][aria-controls]')) {
            if (clicked.has(b) || b.matches('[role="tab"], [aria-haspopup]:not([aria-haspopup="false"]), [data-sheet-open], [data-modal-open], [disabled], [aria-disabled="true"]') || !shown(b)) continue;
            const target = document.getElementById((b.getAttribute('aria-controls') || '').trim().split(/\s+/)[0]);
            if (!target || !main.contains(target) || target.closest('dialog, [data-sheet], [data-overlay], [role="dialog"], [role="menu"], [role="listbox"]')) continue;
            clicked.add(b);
            b.click();
            buttons += 1;
            changed += 1;
        }
        if (!changed) break;
    }
    return { details, buttons };
}

/**
 * The controls of main that switch what it shows, each marked data-lint-act="a<n>": tabs ([role=tab] in a tablist),
 * pressed groups (two or more [aria-pressed] under one parent, with aria-controls (document chips, parts; nested: the
 * groups a button's panel holds) or without (filter chips)), pagers (two or more buttons under one parent, one of
 * them aria-current: the hero's document dots; a carousel's dots are not one, its slides are all rendered), radio
 * groups (by name), selects, calculators ([data-tool]), form wizards ([data-wizard] holding [data-wizard-next]),
 * in-page search fields (input type=search or role=searchbox), in-page sheet or dialog triggers ([data-sheet-open],
 * [data-modal-open]) and the controls that show a toast ([data-toast], and the copy controls [data-faq-copy-link],
 * [data-nav-action="copy"], [data-action="copy"]). Radios, selects and search fields inside a wizard belong to the
 * wizard; a search field inside a calculator, dialog or sheet is not an in-page search.
 */
export function stateControls() {
    const main = document.querySelector('main');
    const out = { tablists: [], pressed: [], pagers: [], radios: [], selects: [], tools: [], wizards: [], searches: [], triggers: [], toasts: [] };
    if (!main) return out;
    let n = 0;
    for (const old of document.querySelectorAll('[data-lint-act]')) old.removeAttribute('data-lint-act');
    const mark = (e) => {
        if (!e.hasAttribute('data-lint-act')) e.setAttribute('data-lint-act', `a${n++}`);
        return e.getAttribute('data-lint-act');
    };
    const label = (e) => String((e && (e.getAttribute('aria-label') || e.textContent)) || '').replace(/\s+/g, ' ').trim().slice(0, 48);
    const inWizard = (e) => Boolean(e.closest('[data-wizard], [data-wizard-form]'));
    // The same control again (a styleguide shows one component in many specimens): same kind, same labels, same
    // surface (dark or light) and direction. The states phase tries each signature once per page.
    const surface = (e) => `${e.closest('.on-dark, .on-night, .sg-night, .section--dark, [data-surface="dark"]') ? 'dark' : 'light'}|${getComputedStyle(e).direction}`;
    const sig = (kind, e, labels) => `${kind}|${surface(e)}|${labels.join('\u0001')}`;
    for (const list of main.querySelectorAll('[role="tablist"]')) {
        const tabs = [...list.querySelectorAll('[role="tab"]')];
        if (tabs.length >= 2) out.tablists.push({ id: mark(list), sig: sig('tabs', list, tabs.map(label)), tabs: tabs.map((t) => ({ id: mark(t), label: label(t), selected: t.getAttribute('aria-selected') === 'true' })) });
    }
    // a control that would leave the page (a link to another address) is never clicked
    const leaves = (e) => e.matches('[href]:not([href^="#"])');
    const groups = new Map();
    for (const b of main.querySelectorAll('[aria-pressed]')) {
        if (leaves(b)) continue;
        if (!groups.has(b.parentElement)) groups.set(b.parentElement, []);
        groups.get(b.parentElement).push(b);
    }
    for (const [parent, list] of groups) {
        if (list.length >= 2) out.pressed.push({ id: mark(parent), sig: sig('pressed', parent, list.map(label)), buttons: list.map((b) => ({ id: mark(b), label: label(b), pressed: b.getAttribute('aria-pressed') === 'true', controls: b.getAttribute('aria-controls') || '' })) });
    }
    const pagerParents = new Set();
    for (const b of main.querySelectorAll('button[aria-current], [role="button"][aria-current]')) {
        if (b.getAttribute('aria-current') !== 'false' && b.parentElement && !b.closest('[data-carousel]')) pagerParents.add(b.parentElement);
    }
    for (const parent of pagerParents) {
        const list = [...parent.children].filter((x) => x.matches('button, [role="button"]') && !leaves(x));
        if (list.length >= 2) out.pagers.push({ id: mark(parent), sig: sig('pager', parent, list.map(label)), buttons: list.map((b) => ({ id: mark(b), label: label(b), current: b.hasAttribute('aria-current') && b.getAttribute('aria-current') !== 'false' })) });
    }
    for (const g of out.pressed) {
        for (const b of g.buttons) {
            const panel = document.getElementById(b.controls.trim().split(/\s+/)[0]);
            b.nested = panel ? out.pressed.filter((h) => h !== g && panel.contains(document.querySelector(`[data-lint-act="${h.id}"]`))).map((h) => h.id) : [];
        }
    }
    const radios = new Map();
    for (const r of main.querySelectorAll('input[type="radio"]')) {
        if (inWizard(r) || !r.name) continue;
        const key = `${r.form ? r.form.id || 'form' : ''}|${r.name}`;
        if (!radios.has(key)) radios.set(key, []);
        radios.get(key).push(r);
    }
    for (const list of radios.values()) {
        if (list.length >= 2) {
            const options = list.map((r) => ({ id: mark(r), label: label(r.labels && r.labels[0] ? r.labels[0] : null) || r.value, checked: r.checked }));
            out.radios.push({ name: list[0].name, sig: sig('radios', list[0], options.map((o) => o.label)), options });
        }
    }
    for (const s of main.querySelectorAll('select')) {
        if (!s.multiple && !inWizard(s) && s.options.length >= 2) out.selects.push({ id: mark(s), name: s.id || s.name || 'select', value: s.value, sig: sig('select', s, [...s.options].map((o) => o.textContent.replace(/\s+/g, ' ').trim())) });
    }
    for (const t of main.querySelectorAll('[data-tool]')) {
        const name = t.getAttribute('data-tool') || t.id || 'calculator';
        out.tools.push({ id: mark(t), name, sig: sig('tool', t, [name, [...t.querySelectorAll('input, select')].map((f) => f.getAttribute('data-input') || f.name || f.type).join(' ')]) });
    }
    for (const w of main.querySelectorAll('[data-wizard]')) {
        if (w.querySelector('[data-wizard-next]')) out.wizards.push({ id: mark(w), sig: sig('wizard', w, [...w.querySelectorAll('[data-step-panel]')].map((p) => p.getAttribute('data-step-panel'))) });
    }
    for (const f of main.querySelectorAll('input[type="search"], input[role="searchbox"]')) {
        if (inWizard(f) || f.closest('[data-tool], dialog, [data-sheet], [data-overlay]') || f.disabled || f.readOnly) continue;
        out.searches.push({ id: mark(f), name: f.id || f.name || 'search', controls: Boolean(f.getAttribute('aria-controls')), sig: sig('search', f, [f.getAttribute('placeholder') || f.getAttribute('aria-label') || f.name || '']) });
    }
    for (const t of main.querySelectorAll('[data-sheet-open], [data-modal-open]')) {
        const target = t.hasAttribute('data-sheet-open') ? `sheet:${t.getAttribute('data-sheet-open')}` : `modal:${t.getAttribute('data-modal-open')}`;
        out.triggers.push({ id: mark(t), target, label: label(t), sig: sig('trigger', t, [target]) });
    }
    for (const t of main.querySelectorAll('[data-toast], [data-faq-copy-link], [data-nav-action="copy"], [data-action="copy"]')) {
        out.toasts.push({ id: mark(t), label: label(t), sig: sig('toast', t, [label(t), t.getAttribute('data-toast') || '']) });
    }
    return out;
}

/**
 * Watches main for what the next action changes (elements and text added, attributes set; the lint's own data-lint-*
 * attributes aside). stateScope({ changed: true }) reads it: every region that changed is linted with the control's.
 */
export function watchChanges() {
    if (window.__lintWatch) window.__lintWatch.observer.disconnect();
    const target = document.querySelector('main') || document.body;
    const nodes = new Set();
    const note = (list) => {
        for (const m of list) {
            if (m.type === 'attributes' && /^data-lint/.test(m.attributeName || '')) continue;
            nodes.add(m.target.nodeType === 1 ? m.target : m.target.parentElement);
            for (const n of m.addedNodes) nodes.add(n.nodeType === 1 ? n : n.parentElement);
        }
    };
    const observer = new MutationObserver(note);
    observer.observe(target, { subtree: true, childList: true, attributes: true, characterData: true });
    window.__lintWatch = { observer, nodes, note };
    return true;
}

/** Types a query into a marked search field (value and an input event, as typing does; '' clears it and its mark). */
export function typeQuery(a) {
    const e = document.querySelector(`[data-lint-act="${a.id}"]`);
    if (!e) return { ok: false, note: 'control gone' };
    const shown = (x) => x.getClientRects().length > 0 && x.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    if (a.value && !shown(e)) return { ok: false, note: 'not shown' };
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
    setter.call(e, a.value);
    if (a.value) e.setAttribute('data-lint-filled', '');
    else e.removeAttribute('data-lint-filled');
    e.dispatchEvent(new Event('input', { bubbles: true }));
    return { ok: true };
}

/** The pressed state of marked buttons, in order. */
export function pressedNow(a) {
    return a.ids.map((id) => {
        const e = document.querySelector(`[data-lint-act="${id}"]`);
        return Boolean(e) && e.getAttribute('aria-pressed') === 'true';
    });
}

/**
 * The tooltip triggers of main (where "main") or of the shell (header, tab bar, footer; outside main and outside
 * closed overlays), each marked data-lint-tip="n": an element with [data-tooltip], the first control of a
 * [data-tooltip-host], and a control that holds its own tip (a [data-overlay] inside a button or link: the icon
 * button's). Returns { id, label (the tip's text), sig } once per text and surface.
 */
export function tooltipTriggers(o) {
    const main = document.querySelector('main');
    for (const old of document.querySelectorAll('[data-lint-tip]')) old.removeAttribute('data-lint-tip');
    const shown = (x) => x.getClientRects().length > 0 && x.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    const focusable = 'a[href], button, input, select, textarea, summary, [tabindex]';
    const where = (e) => (o.where === 'main' ? Boolean(main && main.contains(e)) : !(main && main.contains(e)) && !e.closest('[data-overlay], [data-sheet], dialog, [data-illustration]'));
    const found = [];
    for (const t of document.querySelectorAll('[data-tooltip]')) {
        const text = (t.getAttribute('data-tooltip') || '').replace(/\s+/g, ' ').trim();
        if (text) found.push([t, text]);
    }
    for (const h of document.querySelectorAll('[data-tooltip-host]')) {
        const a = h.querySelector(focusable);
        const bubble = h.querySelector(':scope > [role="tooltip"]');
        if (a && bubble) found.push([a, (bubble.textContent || '').replace(/\s+/g, ' ').trim()]);
    }
    for (const a of document.querySelectorAll('button, a[href], [role="button"]')) {
        const tip = a.querySelector('[data-overlay]');
        if (tip) found.push([a, (tip.textContent || '').replace(/\s+/g, ' ').trim()]);
    }
    const out = [];
    const seenSig = new Set();
    const anchors = new Set();
    for (const [a, text] of found) {
        if (!text || anchors.has(a) || !where(a) || !shown(a)) continue;
        anchors.add(a);
        const s = `tooltip|${a.closest('.on-dark, .on-night, .sg-night, .section--dark, [data-surface="dark"]') ? 'dark' : 'light'}|${getComputedStyle(a).direction}|${text}`;
        if (seenSig.has(s)) continue;
        seenSig.add(s);
        a.setAttribute('data-lint-tip', String(out.length));
        out.push({ id: String(out.length), label: text.slice(0, 48), sig: s });
    }
    return out;
}

/**
 * Brings a marked tooltip trigger into view and focuses it (the caller pressed a key first, so the focus is a
 * keyboard focus: :focus-visible, which shows every kind of tip).
 */
export function focusTrigger(a) {
    const e = document.querySelector(`[data-lint-tip="${a.id}"]`);
    if (!e) return { ok: false, note: 'control gone' };
    const shown = (x) => x.getClientRects().length > 0 && x.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    if (!shown(e)) return { ok: false, note: 'not shown' };
    e.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
    e.focus({ preventScroll: true });
    return document.activeElement === e ? { ok: true } : { ok: false, note: 'not focusable' };
}

/**
 * The tip a focused trigger shows, marked data-lint-root: the bubble its aria-describedby names, a [data-overlay] or
 * [role=tooltip] inside it or its host, else any shown [role=tooltip]. null when none shows.
 */
export function tooltipRoot(a) {
    for (const old of document.querySelectorAll('[data-lint-root]')) old.removeAttribute('data-lint-root');
    const e = document.querySelector(`[data-lint-tip="${a.id}"]`);
    if (!e) return null;
    const shown = (x) => x.getClientRects().length > 0 && x.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    const described = (e.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean).map((id) => document.getElementById(id));
    const host = e.closest('[data-tooltip-host]');
    const tip = described.find((x) => x && x.matches('[role="tooltip"], [data-overlay]') && shown(x))
        || [...e.querySelectorAll('[data-overlay], [role="tooltip"]')].find(shown)
        || (host ? [...host.querySelectorAll('[role="tooltip"]')].find(shown) : null)
        || [...document.querySelectorAll('[role="tooltip"]')].find(shown)
        || null;
    if (!tip) return null;
    tip.setAttribute('data-lint-root', '');
    return { text: textExcerpt(tip, 48) };
}

/** Before a toast action: marks the toasts already there (data-lint-old), so the new one can be told apart. */
export function toastMark() {
    const region = document.querySelector('[data-toast-region]');
    if (!region) return false;
    for (const t of region.children) t.setAttribute('data-lint-old', '');
    return true;
}

/** The toast an action showed (the newest shown child of [data-toast-region] that was not there), marked data-lint-root. */
export function toastRoot() {
    for (const old of document.querySelectorAll('[data-lint-root]')) old.removeAttribute('data-lint-root');
    const region = document.querySelector('[data-toast-region]');
    if (!region) return null;
    const shown = (x) => x.getClientRects().length > 0 && x.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    const list = [...region.children].filter((t) => !t.hasAttribute('data-lint-old') && shown(t));
    const last = list[list.length - 1];
    if (!last) return null;
    last.setAttribute('data-lint-root', '');
    return { text: textExcerpt(last, 48) };
}

/**
 * Acts on a marked control: how "click" (a DOM click: the control's own handlers run, nothing scrolls) or "select"
 * (a select takes the given value, or its first other non-empty option, announced with input and change events).
 * A control that is not shown (nor its label) is left alone unless force is set.
 */
export function stateAct(a) {
    const e = document.querySelector(`[data-lint-act="${a.id}"]`);
    if (!e) return { ok: false, note: 'control gone' };
    const shown = (x) => x.getClientRects().length > 0 && x.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    if (!a.force && !shown(e) && !(e.labels && [...e.labels].some(shown))) return { ok: false, note: 'not shown' };
    if (a.enabled && (e.disabled || e.getAttribute('aria-disabled') === 'true')) return { ok: false, note: 'disabled' };
    if (a.how === 'click') {
        e.click();
        return { ok: true };
    }
    if (a.how === 'select') {
        const options = [...e.options];
        const pick = a.value !== undefined && a.value !== null ? options.find((o) => o.value === a.value) : options.find((o) => !o.disabled && o.value !== '' && o.value !== e.value);
        if (!pick) return { ok: false, note: 'no other option' };
        e.value = pick.value;
        e.dispatchEvent(new Event('input', { bubbles: true }));
        e.dispatchEvent(new Event('change', { bubbles: true }));
        return { ok: true, label: String(pick.label || pick.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 48) };
    }
    return { ok: false, note: `unknown action ${a.how}` };
}

/**
 * Marks what a state changed (data-lint-root): the control's panel (aria-controls) when asked for and shown; else the
 * nearest section, region, dialog, styleguide specimen (.sg-specimen) or [data-lint-scope] around the control that
 * also holds its panel; else the band of main that holds the control (its child of main); main itself for
 * { main: true }. With { changed: true } also every region of main that changed since watchChanges() (the nearest
 * such element around each change, or its band): a filter or a search may change a list in another section.
 * Returns false without main.
 */
export function stateScope(a) {
    for (const old of document.querySelectorAll('[data-lint-root]')) old.removeAttribute('data-lint-root');
    const main = document.querySelector('main');
    if (!main) return false;
    const shown = (x) => x.getClientRects().length > 0 && x.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    const e = a.id ? document.querySelector(`[data-lint-act="${a.id}"]`) : null;
    const control = e && e.matches('input') && e.labels && e.labels[0] ? e : e;
    const panelOf = (x) => (x ? document.getElementById((x.getAttribute('aria-controls') || '').trim().split(/\s+/)[0]) : null);
    const panel = panelOf(control) || (control && control.labels && control.labels[0] ? panelOf(control.labels[0]) : null);
    const near = '[data-lint-scope], .sg-specimen, section, [role="region"], dialog';
    const band = (x) => {
        let b = x;
        while (b.parentElement && b.parentElement !== main) b = b.parentElement;
        return b;
    };
    let root = null;
    if (a.panel && panel && shown(panel)) root = panel;
    if (!root && control && !a.main && main.contains(control)) {
        let s = control.closest(near);
        while (s && main.contains(s) && s !== main && panel && !s.contains(panel)) s = s.parentElement ? s.parentElement.closest(near) : null;
        root = s && s !== main && main.contains(s) ? s : band(control);
    }
    const roots = [root || main];
    const watch = window.__lintWatch;
    if (a.changed && watch) {
        window.__lintWatch = null;
        watch.note(watch.observer.takeRecords());
        watch.observer.disconnect();
        for (const n of watch.nodes) {
            if (!n || !n.isConnected || !main.contains(n) || n === main) continue;
            const s = n.closest(near);
            const region = s && s !== main && main.contains(s) ? s : band(n);
            if (!roots.includes(region)) roots.push(region);
        }
    }
    for (const r of roots) if (!roots.some((x) => x !== r && x.contains(r))) r.setAttribute('data-lint-root', '');
    return true;
}

/** Shows a hidden calculator: clicks the radio whose value is its name, or the control whose panel holds it. */
export function revealTool(a) {
    const tool = document.querySelector(`[data-lint-act="${a.id}"]`);
    const main = document.querySelector('main');
    if (!tool || !main) return 'none';
    const shown = (x) => x.getClientRects().length > 0 && x.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    if (shown(tool)) return 'shown';
    const name = tool.getAttribute('data-tool') || '';
    const radio = name ? [...main.querySelectorAll('input[type="radio"]')].find((r) => r.value === name) : null;
    const control = radio || [...main.querySelectorAll('[aria-controls]')].find((b) => {
        const p = document.getElementById((b.getAttribute('aria-controls') || '').trim().split(/\s+/)[0]);
        return p && p.contains(tool);
    });
    if (!control) return 'none';
    control.click();
    return 'clicked';
}

/**
 * Types sample values into the shown fields of a marked root (each marked data-lint-filled: rule 5 never measures
 * what the lint typed) and returns how many. mode "tool" (a calculator): an empty number field takes its
 * placeholder's digits (sampleNumber), an empty date field a date from its name (sampleDate). mode "wizard" (the
 * current step of a form): an e-mail, a phone, a name, a summary, a deadline date (o.dueDate), required checkboxes,
 * the first option of a required radio group or empty required select; optional fields stay empty. Honeypots
 * (tabindex -1) are never filled.
 */
export function fillFields(o) {
    const root = document.querySelector(`[data-lint-act="${o.id}"]`);
    if (!root) return 0;
    const shown = (x) => x.getClientRects().length > 0 && x.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    let filled = 0;
    const set = (el, v) => {
        el.value = v;
        el.setAttribute('data-lint-filled', '');
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        filled += 1;
    };
    const radiosDone = new Set();
    for (const el of root.querySelectorAll('input, select, textarea')) {
        const type = String(el.getAttribute('type') || (el.tagName === 'SELECT' ? 'select' : el.tagName === 'TEXTAREA' ? 'textarea' : 'text')).toLowerCase();
        if (['hidden', 'submit', 'button', 'image', 'file', 'reset', 'color', 'range'].includes(type) || el.disabled || el.readOnly || el.getAttribute('tabindex') === '-1') continue;
        if (!shown(el) && !(el.labels && [...el.labels].some(shown))) continue;
        const name = `${el.getAttribute('data-input') || ''} ${el.getAttribute('data-wizard-field') || ''} ${el.name || ''} ${el.id || ''} ${el.getAttribute('autocomplete') || ''}`.toLowerCase();
        const required = el.required || el.getAttribute('aria-required') === 'true';
        // The D36 date field (<x-d2.ui.date>) is a text input once enhanced (inputmode numeric); its value setter takes
        // an ISO date, like a native date input.
        const isDate = type === 'date' || el.hasAttribute('data-date-input');
        if (o.mode === 'tool') {
            if (isDate && !el.value) set(el, sampleDate(name, o.clockMs));
            else if ((type === 'number' || (type === 'text' && /decimal|numeric/.test(el.getAttribute('inputmode') || ''))) && !el.value) set(el, sampleNumber(el.getAttribute('placeholder'), el.getAttribute('min')));
            continue;
        }
        if (type === 'checkbox') {
            if (required && !el.checked) {
                el.click();
                el.setAttribute('data-lint-filled', '');
                filled += 1;
            }
            continue;
        }
        if (type === 'radio') {
            if (required && el.name && !radiosDone.has(el.name) && !root.querySelector(`input[type="radio"][name="${CSS.escape(el.name)}"]:checked`)) {
                radiosDone.add(el.name);
                el.click();
                filled += 1;
            }
            continue;
        }
        if (type === 'select') {
            if (required && !el.value) {
                const option = [...el.options].find((x) => x.value && !x.disabled);
                if (option) set(el, option.value);
            }
            continue;
        }
        if (el.value && !(type === 'tel' && /^\+?\d{0,4}$/.test(el.value.trim()))) continue;
        if (type === 'email' || /mail/.test(name)) set(el, 'sample@example.com');
        else if (type === 'tel' || /phone|\btel\b/.test(name)) set(el, '+971 50 000 0000');
        else if (isDate) set(el, o.dueDate);
        else if (type === 'number') set(el, sampleNumber(el.getAttribute('placeholder'), el.getAttribute('min')));
        else if (type === 'textarea') set(el, 'Sample summary typed by the lint to move through the steps: what happened, who is involved and what is needed next.');
        else if (/name/.test(name)) set(el, 'Sample Name');
        else if (required) set(el, 'Sample');
    }
    return filled;
}

/**
 * A marked wizard's visible step (its [data-step-panel] value, else the length of its text), whether a Next button
 * shows (marked data-lint-next) and how many fields are invalid. Installs, once, a guard that stops a native form
 * submission the wizard does not handle itself (the lint never sends a form).
 */
export function wizardStatus(o) {
    const root = document.querySelector(`[data-lint-act="${o.id}"]`);
    if (!root) return null;
    if (!window.__lintSubmitGuard) {
        window.__lintSubmitGuard = true;
        window.addEventListener('submit', (event) => event.preventDefault());
    }
    const shown = (x) => x.getClientRects().length > 0 && x.checkVisibility({ opacityProperty: true, visibilityProperty: true });
    for (const old of document.querySelectorAll('[data-lint-next]')) old.removeAttribute('data-lint-next');
    const panel = [...root.querySelectorAll('[data-step-panel]')].find(shown);
    const next = [...root.querySelectorAll('[data-wizard-next]')].find(shown);
    if (next) next.setAttribute('data-lint-next', '');
    return { step: panel ? panel.getAttribute('data-step-panel') : `text ${(root.innerText || '').length}`, next: Boolean(next), invalid: root.querySelectorAll('[aria-invalid="true"]').length };
}

/** Clicks the Next button wizardStatus() marked (a DOM click; the submit guard keeps the page). */
export function wizardNext() {
    const b = document.querySelector('[data-lint-next]');
    if (!b) return false;
    b.removeAttribute('data-lint-next');
    b.click();
    return true;
}

// ------------------------------------------------------------------------------------------ Node: sources

/** The approved WA01 href per locale from database/content/settings.json ("whatsapp_url"). */
export function readWa01(file = join(ROOT, 'database/content/settings.json')) {
    const settings = JSON.parse(readFileSync(file, 'utf8'));
    const urls = settings.whatsapp_url ?? settings.find?.((row) => row.key === 'whatsapp_url')?.value;
    if (!urls || typeof urls !== 'object') throw new Error(`no whatsapp_url in ${relative(ROOT, file)}`);
    return Object.fromEntries(Object.entries(urls).filter(([, v]) => typeof v === 'string' && v));
}

/** Every string of the approved content (database/content/*.json). */
function contentStrings(dir = join(ROOT, 'database/content')) {
    const out = [];
    const walk = (v) => {
        if (typeof v === 'string') out.push(v);
        else if (Array.isArray(v)) v.forEach(walk);
        else if (v && typeof v === 'object') Object.values(v).forEach(walk);
    };
    if (existsSync(dir)) for (const f of readdirSync(dir).sort()) if (f.endsWith('.json')) walk(JSON.parse(readFileSync(join(dir, f), 'utf8')));
    return out;
}

const collapse = (s) => String(s).replace(/\s+/g, ' ').trim();

/**
 * Exemptions from approved copy (database/content/*.json, imported from the client's DOCX files), which is never
 * rewritten: approved strings that end with an ellipsis (rule 5: the form's submitting state "Submitting your
 * request..." is complete, not a truncation) and approved strings that contain a separator (rule 3: the DOCX
 * writes "Working Languages: Arabic · English · Russian"). Both are reported apart, as questions for the owner.
 * Proposed and interface strings (lang/) get no exemption: their authors can change them.
 */
export function readSources() {
    const approved = contentStrings().map(collapse);
    return {
        approvedEllipses: new Set(approved.filter((s) => endsWithEllipsis(s))),
        approvedSeparators: approved.filter((s) => /\s[·•∙⋅‧●◦・]\s/u.test(s)),
        approved,
    };
}

// ------------------------------------------------------------------------------------------ Node: one page

const sleep = (ms) => new Promise((done) => setTimeout(done, ms));

function viewportFor(width) {
    if (width < 768) return { viewport: { width, height: 844 }, isMobile: true, hasTouch: true };
    if (width < 1024) return { viewport: { width, height: 1024 }, isMobile: false, hasTouch: true };
    return { viewport: { width, height: 900 }, isMobile: false, hasTouch: false };
}

/** Date and Date.now start at the clock and run on; timers and performance.now are left alone. */
const clockShim = (targetMs) => `(() => {
    const RealDate = Date;
    const offset = ${targetMs} - RealDate.now();
    const now = () => RealDate.now() + offset;
    function ShiftedDate(...args) {
        if (!new.target) return new RealDate(now()).toString();
        return args.length === 0 ? new RealDate(now()) : new RealDate(...args);
    }
    ShiftedDate.prototype = RealDate.prototype;
    ShiftedDate.now = now;
    ShiftedDate.parse = RealDate.parse;
    ShiftedDate.UTC = RealDate.UTC;
    globalThis.Date = ShiftedDate;
})();`;

/**
 * localStorage lives in the page (a fresh, empty store on every load): what one page saves (a form draft the states
 * phase typed, recent searches) never reaches the other pages of the same context, whatever order they run in.
 */
const memoryStorage = `(() => {
    const m = new Map();
    const store = {
        getItem: (k) => (m.has(String(k)) ? m.get(String(k)) : null),
        setItem: (k, v) => { m.set(String(k), String(v)); },
        removeItem: (k) => { m.delete(String(k)); },
        clear: () => m.clear(),
        key: (i) => [...m.keys()][i] ?? null,
        get length() { return m.size; },
    };
    try { Object.defineProperty(window, 'localStorage', { configurable: true, get: () => store }); } catch {}
})();`;

async function openContext(browser, opt, locale, width) {
    const context = await newContext(browser, {
        width,
        locale,
        variant: 'reduced',
        baseURL: opt.base,
        design: opt.design,
        extra: { ...viewportFor(width), deviceScaleFactor: 1, locale: BROWSER_LOCALE[locale] ?? 'en-GB', timezoneId: TIMEZONE, serviceWorkers: 'block' },
    });
    if (opt.clockMs !== null) await context.addInitScript({ content: clockShim(opt.clockMs) });
    await context.addInitScript({ content: memoryStorage });
    await context.route(/^[a-z]+:\/\/(?!(?:127\.0\.0\.1|localhost|\[::1\])(?::\d+)?\/)/i, (route) => route.abort('blockedbyclient'));
    await context.route(/\/enquiries(?:[/?#]|$)/, (route) => (route.request().method() === 'GET' ? route.fallback() : route.abort('blockedbyclient')));
    return context;
}

/** Load, fonts, one scroll through the page and back, scripts and animations settled and frozen. */
async function settlePage(page, watch) {
    await page.waitForFunction(() => document.documentElement.classList.contains('js'), null, { timeout: 8000 }).catch(() => {});
    await page.evaluate(() => document.fonts.ready);
    await scrollThrough(page, { pauseMs: 40 });
    await watch.idle(4000);
    await page.evaluate(script(settleStyles, { quietMs: 250, maxMs: 2500 }));
    await page.evaluate(script(freezeAnimations));
    await page.evaluate(() => document.fonts.ready);
}

const DEPS = {
    splitLayers, colorAlpha, visibleShadow, hasGradient, primaryFamily, isMonospace, trackedCaps, arrowsIn, separatorIndexes,
    endsWithEllipsis, digitMix, onSpacingScale, typeScale, inTypeScale, isWhatsAppHref, compareWhatsApp, iconVerdict,
    isCardToken, isChipToken, isKpiToken, contentText, areaFor, domSelector, textExcerpt, domArea, longhandNames,
    cuttableInput, zeroPadded, dashedSides, commonTexts, sampleDate, sampleNumber, mulLinear, linearScale, ownLinear,
    drawnLength, neutralColor, stepNumber, itemKind, ancestorLinear,
};

/** The identity of a hit on one page (rule, selector, value, text): the states phase reports each one once. */
const keyOf = (r) => r.key ?? `${r.rule}\u0000${r.selector}\u0000${r.value}\u0000${r.text}`;

/**
 * "Set by" for the records of rules 6, 7, 9 and 10: the page pass tagged the first element of each record
 * (data-lint-probe); the DevTools protocol's cascade (CSS.getMatchedStylesForNode) names the rule that sets the
 * value, or the user-agent default. Writes record.source; then:
 *   - record.file: the stylesheet that holds the winning rule (findRuleFile over opt.cssIndex: the Vite dev server and
 *     a build both serve one bundled sheet, so the rule's selector and declarations find their file), and the record
 *     moves to that file's area (AREA_FILES); a browser default, an inline style or an unknown file keep the area of
 *     the element's location;
 *   - rule 10: a side whose winning declaration uses a safe-area inset (var(--safe-*), env(safe-area-inset-*)) is
 *     device geometry, not a spacing step: it is dropped, and a record left with no side becomes exempt "safe-area".
 * Removes the tags (always, also when no record needs a lookup).
 */
async function resolveCss(page, records, opt = {}) {
    const wanted = records.filter((r) => r.css);
    let cdp = null;
    try {
        if (!wanted.length) return;
        cdp = await page.context().newCDPSession(page);
        const sheets = new Map();
        cdp.on('CSS.styleSheetAdded', ({ header }) => sheets.set(header.styleSheetId, header));
        await cdp.send('DOM.enable');
        await cdp.send('CSS.enable');
        const { root } = await cdp.send('DOM.getDocument', { depth: 0 });
        const sheetPath = (id) => {
            try {
                const url = sheets.get(id)?.sourceURL;
                return url ? decodeURIComponent(new URL(url).pathname).replace(/^\/+/, '') : null;
            } catch {
                return null;
            }
        };
        for (const r of wanted) {
            const { nodeId } = await cdp.send('DOM.querySelector', { nodeId: root.nodeId, selector: `[data-lint-probe~="${r.css.id}"]` });
            if (!nodeId) continue;
            const matched = await cdp.send('CSS.getMatchedStylesForNode', { nodeId });
            const parts = r.css.groups.map((names, k) => {
                const win = cssWinner(matched, names, r.css.pseudo, Boolean(r.css.inherit));
                let file = null;
                if (win?.rule && win.rule.origin !== 'user-agent' && opt.cssIndex) {
                    const layers = (win.rule.layers ?? []).map((l) => l.text).filter(Boolean);
                    file = findRuleFile(opt.cssIndex, {
                        selector: win.rule.selectorList?.text,
                        parents: win.rule.nestingSelectors ?? [],
                        layer: layers[layers.length - 1] ?? '',
                        decls: winnerDeclarations(win, names),
                        sheetPath: sheetPath(win.rule.styleSheetId),
                    });
                }
                return { source: win ? describeWinner(win, names) : `initial value of ${names[0]}`, file, part: r.css.parts?.[k] };
            });
            let kept = parts;
            if (r.rule === 10) {
                kept = parts.filter((p) => !isSafeAreaSource(p.source));
                if (!kept.length) {
                    r.exempt = 'safe-area';
                    r.source = [...new Set(parts.map((p) => p.source))].join(' + ');
                    continue;
                }
                if (kept.length < parts.length && kept.every((p) => p.part)) r.value = kept.map((p) => p.part).join('; ');
            }
            r.source = [...new Set(kept.map((p) => p.source))].join(' + ');
            const file = kept.find((p) => p.file)?.file ?? null;
            if (file) {
                r.file = file;
                const area = areaForFile(file, opt.areaFiles ?? AREA_FILES);
                if (area && r.area !== 'design-1') r.area = area;
            }
        }
    } catch (error) {
        if (process.env.QA_LINT_DEBUG) console.log(`  [set by] ${String(error?.message ?? error).split('\n')[0]}`);
    } finally {
        for (const r of wanted) delete r.css;
        await cdp?.detach().catch(() => {});
        await page.evaluate(() => {
            for (const el of document.querySelectorAll('[data-lint-probe]')) el.removeAttribute('data-lint-probe');
        }).catch(() => {});
    }
}

let axeSourceText = null;
/** axe-core's own build (the version @axe-core/playwright pins), read once. */
const axeSource = () => (axeSourceText ??= readFileSync(createRequire(import.meta.url).resolve('axe-core/axe.min.js'), 'utf8'));

/**
 * axe-core color-contrast in the page: injected once per document (the DevTools protocol, which the page's CSP does not
 * restrict) and run with axe.run; @axe-core/playwright's analyze() would inject it again and open a blank page for
 * every call, which the states phase cannot afford. include / exclude: CSS selectors or null.
 */
async function axeRun(page, include, exclude) {
    if (!(await page.evaluate(() => typeof window.axe?.run === 'function'))) await page.evaluate(axeSource());
    return page.evaluate(async ({ include: inc, exclude: exc }) => {
        const context = inc || exc ? { ...(inc ? { include: [inc] } : {}), ...(exc ? { exclude: [exc] } : {}) } : document;
        const res = await window.axe.run(context, { runOnly: { type: 'rule', values: ['color-contrast'] }, resultTypes: ['violations', 'incomplete'] });
        return {
            violations: res.violations.map((v) => ({ nodes: v.nodes.map((n) => ({ target: n.target, any: n.any.map((a) => ({ data: a.data })) })) })),
            incomplete: res.incomplete.reduce((sum, v) => sum + v.nodes.length, 0),
        };
    }, { include, exclude });
}

/**
 * Rule 16: axe-core color-contrast over the page (or one overlay or state); nodes on the paper of an illustration
 * are recorded as exempt (art). exclude: what not to check (the states leave out the paper: its art was tallied).
 */
async function axeContrast(page, include, strictIllustrations, exclude = null) {
    const result = await axeRun(page, include, exclude);
    const nodes = result.violations.flatMap((v) => v.nodes);
    const targets = nodes.map((n) => (Array.isArray(n.target) && n.target.length === 1 && typeof n.target[0] === 'string' ? n.target[0] : null));
    const described = await page.evaluate(script(describeTargets, targets.map((t) => t ?? ':not(*)'), DEPS));
    const records = [];
    nodes.forEach((n, i) => {
        const d = described[i];
        const data = n.any?.[0]?.data ?? {};
        const ratio = data.contrastRatio ? `${data.contrastRatio}:1` : 'unknown ratio';
        const value = `contrast ${ratio} (needs ${data.expectedContrastRatio ?? '4.5:1'}), ${data.fgColor ?? '?'} on ${data.bgColor ?? '?'}, ${data.fontSize ?? '?'} ${data.fontWeight ?? ''}`.trim();
        records.push({ rule: 16, area: d?.area ?? 'platform', selector: d?.selector ?? (targets[i] || String(n.target)), value, text: d?.text ?? '', count: 1, ...(d?.ill && !strictIllustrations ? { exempt: 'illustration' } : {}) });
    });
    return { records, incomplete: result.incomplete };
}

async function hoverTest(page, rules) {
    const records = [];
    const count = await page.evaluate(script(hoverPrepare, { max: MAX_HOVER_CARDS }, DEPS));
    if (!count) return { records, hovered: 0, skipped: 0 };
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    let hovered = 0;
    let skipped = 0;
    try {
        for (let i = 0; i < count; i += 1) {
            await page.mouse.move(1, 1);
            const rest = await page.evaluate(script(hoverMeasure, { i, phase: 'rest' }, DEPS));
            if (!rest || !rest.point) {
                skipped += 1;
                continue;
            }
            await page.mouse.move(rest.point[0], rest.point[1]);
            const hov = await page.evaluate(script(hoverMeasure, { i, phase: 'hover' }, DEPS));
            if (!hov) continue;
            hovered += 1;
            const moved = rest.box.map((v, k) => hov.box[k] - v);
            if (rules.has(8) && moved.some((d) => Math.abs(d) > 0.5)) {
                const [dx, dy, dw, dh] = moved.map((d) => Math.round(d * 10) / 10);
                records.push({ rule: 8, area: rest.area, selector: rest.selector, value: `box moves on hover (x ${dx}, y ${dy}, width ${dw}, height ${dh} px)`, text: rest.text, count: 1, state: 'hover' });
            } else if (rules.has(8)) {
                const zoom = rest.media.findIndex((m, k) => hov.media[k] && (Math.abs(hov.media[k][2] - m[2]) > 0.5 || Math.abs(hov.media[k][3] - m[3]) > 0.5));
                if (zoom !== -1) records.push({ rule: 8, area: rest.area, selector: rest.selector, value: 'media inside the card scales on hover', text: rest.text, count: 1, state: 'hover' });
            }
            if (rules.has(6) && !visibleShadow(rest.shadow) && visibleShadow(hov.shadow)) {
                records.push({ rule: 6, area: rest.areaShadow, selector: rest.selector, value: `box-shadow on hover ${hov.shadow}`, text: rest.text, count: 1, state: 'hover' });
            }
        }
    } finally {
        await page.mouse.move(1, 1).catch(() => {});
        await page.emulateMedia({ reducedMotion: 'reduce' }).catch(() => {});
        await page.evaluate(() => window.scrollTo({ top: 0, left: 0, behavior: 'instant' })).catch(() => {});
    }
    return { records, hovered, skipped };
}

/**
 * Lints one state: pageLint on the scope (rules 1-7, 9, 10 and 13-15: no hover test, container edges or card rows),
 * the "set by" lookup, the advice and axe colour contrast (rule 16) on the same scope. Only hits this page has not
 * reported yet (seen: rule, selector, value, text) are kept, so a state reports what it shows for the first time.
 */
/** QA_LINT_DEBUG: where the time of the states goes (ms per step, summed over a run). */
const stateClock = {};
const timed = async (name, fn) => {
    const t = Date.now();
    try {
        return await fn();
    } finally {
        stateClock[name] = (stateClock[name] ?? 0) + Date.now() - t;
    }
};

async function lintScope(page, opt, target, scope, seen, prefix) {
    const rules = opt.rules.filter((r) => ![8, 11, 12].includes(r));
    const res = await timed('pageLint', () => page.evaluate(script(pageLint, { rules, width: target.width, scope, layout: false, whatsapp: rules.includes(14), wa01: opt.wa01, maxPerRule: MAX_PER_RULE, strictIllustrations: opt.illustrations === 'strict', probePrefix: prefix }, DEPS)));
    if (res.info.error) throw new Error(res.info.error);
    const fresh = res.records.filter((r) => !seen.has(keyOf(r)));
    for (const r of res.records) seen.add(keyOf(r));
    await timed('set-by', () => resolveCss(page, fresh, opt));
    const advice = res.advice.filter((a) => !seen.has(a.key));
    for (const a of res.advice) seen.add(a.key);
    const records = [...fresh];
    if (rules.includes(16)) {
        const strict = opt.illustrations === 'strict';
        const axe = await timed('axe', () => axeContrast(page, scope, strict, strict ? null : '[data-illustration] .doc, [data-illustration] [data-doc]'));
        for (const r of axe.records) {
            if (seen.has(keyOf(r))) continue;
            seen.add(keyOf(r));
            records.push(r);
        }
    }
    return { records, advice };
}

/**
 * Waits for what an action started: scripts writing styles (at most maxMs: live specimens such as a running
 * clock never stop writing), animations (frozen at their end) and, with network, requests (an overlay that loads its
 * data, the palette's search index).
 */
async function settleState(page, watch, { quietMs = 80, network = false, maxMs = network ? 1500 : 500 } = {}) {
    await sleep(quietMs);
    if (network) await watch.idle(1500).catch(() => {});
    await page.evaluate(script(settleStyles, { quietMs, maxMs })).catch(() => {});
    await page.evaluate(script(freezeAnimations)).catch(() => {});
}

/** Closes the open overlay of a trigger target (Escape, twice if needed); reloads the page when it stays open. */
async function closeOverlay(page, target, url, watch) {
    await page.keyboard.press('Escape').catch(() => {});
    await sleep(200);
    for (let attempt = 0; attempt < 2; attempt += 1) {
        const still = await page.evaluate(script(overlayRoot, target, DEPS)).catch(() => null);
        if (!still) break;
        if (attempt === 1) {
            await page.goto(url, { waitUntil: 'load', timeout: NAV_TIMEOUT });
            await settlePage(page, watch);
            break;
        }
        await page.keyboard.press('Escape').catch(() => {});
        await sleep(300);
    }
    await page.evaluate(() => {
        for (const el of document.querySelectorAll('[data-lint-root]')) el.removeAttribute('data-lint-root');
    }).catch(() => {});
}

/**
 * Opens each trigger in turn (Playwright click on [data-lint-trigger]) and lints the open overlay, then its own
 * states: every preview of a menu with preview items ([data-mega-item], [data-preview-item]: hovered and focused one
 * by one) and a search overlay with the locale's query typed in (PALETTE_QUERY: the results list). prefix: "" at
 * the top of the page, "compact header " once scrolled.
 */
async function openOverlays(page, opt, target, watch, seen, triggers, prefix, out) {
    for (const trigger of triggers) {
        const state = { state: `${prefix}overlay ${trigger.target}`, label: trigger.label, ok: false, records: [], advice: [] };
        out.push(state);
        const url = page.url();
        let opened = false;
        try {
            const handle = page.locator(`[data-lint-trigger="${trigger.index}"]`).first();
            if (!(await handle.count()) || !(await handle.isVisible())) {
                state.note = 'the trigger is no longer shown';
                continue;
            }
            await handle.click({ timeout: 4000 });
            if (page.url() !== url) {
                state.note = 'the trigger navigated instead of opening an overlay';
                await page.goto(url, { waitUntil: 'load', timeout: NAV_TIMEOUT });
                await settlePage(page, watch);
                await page.evaluate(script(findTriggers, null, DEPS));
                continue;
            }
            let root = null;
            for (let t = 0; t < 20 && !root; t += 1) {
                root = await page.evaluate(script(overlayRoot, trigger.target, DEPS));
                if (!root) await sleep(100);
            }
            if (!root) {
                state.note = 'no overlay opened';
                continue;
            }
            opened = true;
            await page.evaluate(script(freezeAnimations));
            await sleep(150);
            const res = await lintScope(page, opt, target, '[data-lint-root]', seen, `s${out.length}-`);
            state.records = res.records.map((r) => ({ ...r, state: state.state }));
            state.advice = res.advice.map((a) => ({ ...a, state: state.state }));
            state.id = root.id;
            state.ok = true;

            // a menu with previews: each other item hovered and focused
            const items = await page.evaluate(() => {
                const shown = (e) => e.getClientRects().length > 0 && e.checkVisibility({ opacityProperty: true, visibilityProperty: true });
                const rootEl = document.querySelector('[data-lint-root]');
                if (!rootEl) return [];
                return [...rootEl.querySelectorAll('[data-mega-item], [data-preview-item]')].map((e, i) => {
                    e.setAttribute('data-lint-item', String(i));
                    return { i, label: (e.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 48), active: e.matches('.is-active, [aria-current], [aria-selected="true"]'), shown: shown(e) };
                });
            });
            for (const item of items.filter((x) => x.shown && !x.active)) {
                const sub = { state: `${state.state} preview "${item.label}"`, label: trigger.label, ok: false, records: [], advice: [] };
                out.push(sub);
                try {
                    const loc = page.locator(`[data-lint-item="${item.i}"]`).first();
                    await loc.hover({ timeout: 3000 });
                    await loc.focus({ timeout: 3000 }).catch(() => {});
                    await settleState(page, watch, { network: true });
                    const r = await lintScope(page, opt, target, '[data-lint-root]', seen, `s${out.length}-`);
                    sub.records = r.records.map((x) => ({ ...x, state: sub.state }));
                    sub.advice = r.advice.map((x) => ({ ...x, state: sub.state }));
                    sub.ok = true;
                } catch (error) {
                    sub.note = String(error?.message ?? error).split('\n')[0];
                }
            }

            // a search overlay: the locale's query typed in, the results linted
            const search = page.locator('[data-lint-root] input[type="search"], [data-lint-root] input[role="combobox"], [data-lint-root] input[role="searchbox"]').first();
            if ((await search.count()) && (await search.isVisible())) {
                const query = PALETTE_QUERY[target.locale] ?? PALETTE_QUERY.en;
                const sub = { state: `${state.state} results "${query}"`, label: trigger.label, ok: false, records: [], advice: [] };
                out.push(sub);
                try {
                    await search.fill(query, { timeout: 3000 });
                    await settleState(page, watch, { quietMs: 300, network: true });
                    const r = await lintScope(page, opt, target, '[data-lint-root]', seen, `s${out.length}-`);
                    sub.records = r.records.map((x) => ({ ...x, state: sub.state }));
                    sub.advice = r.advice.map((x) => ({ ...x, state: sub.state }));
                    sub.ok = true;
                } catch (error) {
                    sub.note = String(error?.message ?? error).split('\n')[0];
                }
            }
        } catch (error) {
            state.note = String(error?.message ?? error).split('\n')[0];
        } finally {
            if (opened || state.note) await closeOverlay(page, trigger.target, url, watch);
            await page.evaluate(script(findTriggers, null, DEPS)).catch(() => {});
        }
    }
}

/**
 * Shell states, on the first selected route of each locale and width (normally home): every overlay the header and
 * the tab bar can open at the top of the page (mega menu, language menu, More, menu and services sheets, search
 * palette), with the menu previews and the palette's results; the tooltips of the header, the tab bar and the
 * footer (tooltipStates: the search button's "Search (/)"); then the compact header (the page scrolled down) and the
 * overlays only its triggers open (the desktop menu sheet).
 */
async function overlayStates(page, opt, target, watch, seen) {
    const out = [];
    const triggers = await page.evaluate(script(findTriggers, null, DEPS));
    await openOverlays(page, opt, target, watch, seen, triggers, '', out);
    await tooltipStates(page, opt, target, watch, seen, 'shell', out);

    const compact = { state: 'compact header', ok: false, records: [], advice: [] };
    out.push(compact);
    try {
        await page.evaluate(() => window.scrollTo({ top: Math.max(1600, window.innerHeight * 2), left: 0, behavior: 'instant' }));
        await settleState(page, watch, { quietMs: 400, network: true });
        const marked = await page.evaluate(() => {
            const banner = [...document.getElementsByTagName('header')].find((x) => !x.parentElement || !x.parentElement.closest('main, article, aside, nav, section'));
            if (!banner || !banner.getClientRects().length) return false;
            for (const old of document.querySelectorAll('[data-lint-root]')) old.removeAttribute('data-lint-root');
            banner.setAttribute('data-lint-root', '');
            return true;
        });
        if (marked) {
            const r = await lintScope(page, opt, target, '[data-lint-root]', seen, `c${out.length}-`);
            compact.records = r.records.map((x) => ({ ...x, state: compact.state }));
            compact.advice = r.advice.map((x) => ({ ...x, state: compact.state }));
            compact.ok = true;
        } else {
            compact.note = 'no banner header shows once scrolled';
        }
        const opened = new Set(triggers.map((t) => t.target));
        const more = (await page.evaluate(script(findTriggers, null, DEPS))).filter((t) => !opened.has(t.target));
        await openOverlays(page, opt, target, watch, seen, more, 'compact header ', out);
    } catch (error) {
        compact.note = String(error?.message ?? error).split('\n')[0];
    } finally {
        await page.evaluate(() => window.scrollTo({ top: 0, left: 0, behavior: 'instant' })).catch(() => {});
        await sleep(300);
    }
    return out;
}

/**
 * Tooltip states: each tooltip trigger of the shell (where "shell": header, tab bar, footer, on the first route) or of
 * main (where "main", every page), once per text and surface (tooltipTriggers). A key press first makes the next focus
 * a keyboard focus; the trigger is scrolled into view and focused (:focus-visible shows every kind of tip: the icon
 * button's own, a [data-tooltip] bubble, a [data-tooltip-host] bubble) and the tip is linted as `tooltip "<text>"`.
 * Blurred after each one; the page goes back to its top at the end. At most `limit` entries in `out`.
 */
async function tooltipStates(page, opt, target, watch, seen, where, out, limit = Infinity) {
    const tips = await page.evaluate(script(tooltipTriggers, { where }, DEPS));
    try {
        for (const t of tips) {
            if (out.length >= limit) {
                if (!out.some((x) => x.note === 'state limit reached')) out.push({ state: 'more states', kind: 'tooltip', ok: false, records: [], advice: [], note: 'state limit reached' });
                break;
            }
            const st = { state: `tooltip "${t.label}"`, kind: 'tooltip', label: t.label, ok: false, records: [], advice: [] };
            out.push(st);
            try {
                await page.keyboard.press('Shift');
                const f = await page.evaluate(script(focusTrigger, { id: t.id }, DEPS));
                if (!f.ok) {
                    st.note = f.note;
                    continue;
                }
                let root = null;
                for (let i = 0; i < 10 && !root; i += 1) {
                    await sleep(i ? 100 : 250);
                    await page.evaluate(script(freezeAnimations)).catch(() => {});
                    root = await page.evaluate(script(tooltipRoot, { id: t.id }, DEPS));
                }
                if (!root) {
                    st.note = 'no tooltip showed';
                    continue;
                }
                const r = await lintScope(page, opt, target, '[data-lint-root]', seen, `t${where[0]}${out.length}-`);
                st.records = r.records.map((x) => ({ ...x, state: st.state }));
                st.advice = r.advice.map((x) => ({ ...x, state: st.state }));
                st.ok = true;
            } catch (error) {
                st.note = String(error?.message ?? error).split('\n')[0];
            } finally {
                await page.evaluate(() => {
                    if (document.activeElement && document.activeElement !== document.body) document.activeElement.blur();
                }).catch(() => {});
                await sleep(120);
            }
        }
    } finally {
        await page.evaluate(() => {
            for (const el of document.querySelectorAll('[data-lint-root], [data-lint-tip]')) {
                el.removeAttribute('data-lint-root');
                el.removeAttribute('data-lint-tip');
            }
            window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
        }).catch(() => {});
        if (tips.length) await sleep(250);
    }
}

/**
 * Interactive states of main (every route): what the first render never shows, driven through the page's own
 * controls (DOM clicks and input events; only the tooltips scroll, to focus their trigger) and linted with
 * lintScope(). In order:
 *   1. every <details> open and every in-page disclosure expanded (openDisclosures)
 *   2. each other tab of every tablist (its panel), details inside opened
 *   3. each other button of a pressed group (aria-pressed, with or without aria-controls: document chips and parts,
 *      filter chips), with the groups its panel holds; back to the group's first pattern after each
 *   4. each other button of a pager (aria-current: the hero's document dots; not a carousel's dots)
 *   5. each other option of a radio group of up to 8 (segmented controls); a larger group once (a choice made)
 *   6. one other option of each select outside forms
 *   7. each calculator ([data-tool]) shown (through its switch) and filled with sample values (fillFields)
 *   8. each form wizard: Next on the empty first step (the errors), then step by step with sample values (never sent)
 *   9. each in-page search field: a query with results, one without (SEARCH_QUERY, NO_MATCH_QUERY), then cleared
 *  10. each in-page sheet or dialog trigger of main (the contents sheet)
 *  11. each control that shows a toast ([data-toast], the copy controls): the toast
 *  12. each tooltip trigger of main (tooltipStates)
 * Pressed groups, pagers and searches lint the control's region and every region of main they changed (a filter or a
 * search may change a list in another section: watchChanges). Each control is put back to its first state after its
 * group. A page stops at MAX_STATES states.
 */
async function interactiveStates(page, opt, target, watch, seen) {
    const out = [];
    let seq = 0;
    const run = async (state, kind, scope, settle) => {
        if (out.length >= MAX_STATES) {
            if (!out.some((s) => s.note === 'state limit reached')) out.push({ state: 'more states', kind, ok: false, records: [], advice: [], note: 'state limit reached' });
            return;
        }
        const entry = { state, kind, ok: false, records: [], advice: [] };
        out.push(entry);
        try {
            await timed('settle', () => settleState(page, watch, settle));
            let sel = scope;
            if (typeof scope !== 'string') sel = (await timed('scope', () => page.evaluate(script(stateScope, scope, DEPS)))) ? '[data-lint-root]' : 'body';
            const res = await lintScope(page, opt, target, sel, seen, `i${seq++}-`);
            entry.records = res.records.map((r) => ({ ...r, state }));
            entry.advice = res.advice.map((a) => ({ ...a, state }));
            entry.ok = true;
        } catch (error) {
            entry.note = String(error?.message ?? error).split('\n')[0];
        }
    };
    // Past the limit nothing more is clicked (a restore still is: force).
    const act = (a) => (out.length >= MAX_STATES && !a.force ? Promise.resolve({ ok: false, note: 'state limit reached' }) : timed('act', () => page.evaluate(script(stateAct, a, DEPS))).catch((error) => ({ ok: false, note: String(error?.message ?? error) })));
    const recipe = async (kind, fn) => {
        try {
            await fn();
        } catch (error) {
            out.push({ state: `${kind} (stopped)`, kind, ok: false, records: [], advice: [], note: String(error?.message ?? error).split('\n')[0] });
        }
    };
    const disclose = () => page.evaluate(script(openDisclosures, null, DEPS));
    const watchMain = () => page.evaluate(script(watchChanges, null, DEPS));
    // Each distinct control once per page (a styleguide repeats one component in many specimens: stateControls sig).
    const tried = new Set();
    const fresh = (g) => {
        if (!g.sig) return true;
        if (tried.has(g.sig)) return false;
        tried.add(g.sig);
        return true;
    };

    await recipe('disclosures', async () => {
        const opened = await disclose();
        if (opened.details || opened.buttons) await run('details and disclosures open', 'disclosures', { main: true });
    });
    const c = await page.evaluate(script(stateControls, null, DEPS));

    await recipe('tabs', async () => {
        for (const list of c.tablists.filter(fresh)) {
            const initial = list.tabs.find((t) => t.selected) ?? list.tabs[0];
            let changed = false;
            for (const tab of list.tabs) {
                if (tab === initial || !(await act({ id: tab.id, how: 'click' })).ok) continue;
                changed = true;
                await disclose();
                await run(`tab "${tab.label}"`, 'tab', { id: tab.id, panel: true });
            }
            if (changed) await act({ id: initial.id, how: 'click', force: true });
        }
    });

    await recipe('pressed', async () => {
        const byId = new Map(c.pressed.map((g) => [g.id, g]));
        const done = new Set();
        const pattern = (g) => page.evaluate(script(pressedNow, { ids: g.buttons.map((b) => b.id) }, DEPS));
        // Back to the group's first pattern: the button that was pressed first (one click puts a single-choice group
        // back), else each button that differs (independent toggles).
        const restore = async (g, want) => {
            for (let pass = 0; pass <= g.buttons.length; pass += 1) {
                const now = await pattern(g);
                const diff = g.buttons.filter((b, i) => now[i] !== want[i]);
                if (!diff.length) return;
                await act({ id: (diff.find((b) => want[g.buttons.indexOf(b)]) ?? diff[0]).id, how: 'click', force: true });
            }
        };
        const pressGroup = async (g, depth) => {
            done.add(g.id);
            const want = g.buttons.map((b) => b.pressed);
            for (const [i, b] of g.buttons.entries()) {
                let pressed = false;
                if (!want[i]) {
                    await watchMain();
                    if (!(await act({ id: b.id, how: 'click' })).ok) continue;
                    pressed = true;
                    await run(`pressed "${b.label}"`, 'pressed', { id: b.id, changed: true });
                }
                if (depth === 0) for (const nid of b.nested) if (byId.has(nid) && !done.has(nid)) await pressGroup(byId.get(nid), 1);
                if (pressed) await restore(g, want);
            }
        };
        for (const g of c.pressed) if (!done.has(g.id) && fresh(g)) await pressGroup(g, 0);
    });

    await recipe('pager', async () => {
        // the hero swaps its documents and draws their callouts: a longer settle
        const slow = { quietMs: 250, maxMs: 2000 };
        for (const g of c.pagers.filter(fresh)) {
            const initial = g.buttons.find((b) => b.current) ?? null;
            let changed = false;
            for (const b of g.buttons) {
                if (initial && b.id === initial.id) continue;
                await watchMain();
                if (!(await act({ id: b.id, how: 'click' })).ok) continue;
                changed = true;
                await run(`pager "${b.label}"`, 'pager', { id: b.id, changed: true }, slow);
            }
            if (changed && initial) {
                await act({ id: initial.id, how: 'click', force: true });
                await settleState(page, watch, slow);
            }
        }
    });

    await recipe('choices', async () => {
        for (const g of c.radios.filter(fresh)) {
            const initial = g.options.find((o) => o.checked) ?? null;
            const others = g.options.filter((o) => o !== initial);
            let changed = false;
            for (const o of others.length > 7 ? others.slice(0, 1) : others) {
                if (!(await act({ id: o.id, how: 'click' })).ok) continue;
                changed = true;
                await run(`choice ${g.name} "${o.label}"`, 'choice', { id: o.id });
            }
            if (changed && initial) await act({ id: initial.id, how: 'click', force: true });
        }
    });

    await recipe('selects', async () => {
        for (const s of c.selects.filter(fresh)) {
            const r = await act({ id: s.id, how: 'select' });
            if (!r.ok) continue;
            await run(`select ${s.name} "${r.label}"`, 'select', { id: s.id });
            await act({ id: s.id, how: 'select', value: s.value, force: true });
        }
    });

    await recipe('calculators', async () => {
        for (const t of c.tools.filter(fresh)) {
            const how = await page.evaluate(script(revealTool, { id: t.id }, DEPS));
            if (how === 'none') continue;
            if (how === 'clicked') await settleState(page, watch);
            const filled = await page.evaluate(script(fillFields, { id: t.id, mode: 'tool', clockMs: opt.clockMs ?? Date.now() }, DEPS));
            if (filled) await run(`calculator ${t.name} with values`, 'calculator', { id: t.id });
        }
    });

    await recipe('wizard', async () => {
        const clock = opt.clockMs ?? Date.now();
        const dueDate = new Date(clock + 30 * 86_400_000).toISOString().slice(0, 10);
        for (const w of c.wizards.filter(fresh)) {
            let status = await page.evaluate(script(wizardStatus, { id: w.id }, DEPS));
            if (!status?.next) continue;
            await page.evaluate(script(wizardNext, null, DEPS));
            await run('wizard: Next on an empty step', 'wizard', { id: w.id });
            for (let i = 0; i < 6; i += 1) {
                status = await page.evaluate(script(wizardStatus, { id: w.id }, DEPS));
                if (!status?.next) break;
                await page.evaluate(script(fillFields, { id: w.id, mode: 'wizard', clockMs: clock, dueDate }, DEPS));
                await settleState(page, watch);
                await page.evaluate(script(wizardStatus, { id: w.id }, DEPS));
                await page.evaluate(script(wizardNext, null, DEPS));
                await settleState(page, watch);
                const after = await page.evaluate(script(wizardStatus, { id: w.id }, DEPS));
                if (!after || after.step === status.step) {
                    out.push({ state: `wizard: step ${status.step} does not move on`, kind: 'wizard', ok: false, records: [], advice: [], note: `${after?.invalid ?? 0} invalid field(s) after sample values` });
                    break;
                }
                await run(`wizard step ${after.step}`, 'wizard', { id: w.id });
            }
        }
    });

    await recipe('searches', async () => {
        // a debounced search answers after its delay: a longer settle
        const slow = { quietMs: 300, maxMs: 2000 };
        const queries = [SEARCH_QUERY[target.locale] ?? SEARCH_QUERY.en, NO_MATCH_QUERY[target.locale] ?? NO_MATCH_QUERY.en];
        for (const f of c.searches.filter(fresh)) {
            let typed = false;
            for (const query of queries) {
                if (out.length >= MAX_STATES) break;
                await watchMain();
                const r = await page.evaluate(script(typeQuery, { id: f.id, value: query }, DEPS));
                if (!r.ok) break;
                typed = true;
                await run(`search ${f.name} "${query}"`, 'search', { id: f.id, changed: true }, slow);
            }
            if (typed) {
                // cleared again: the reset state (a status line, the whole list back)
                await watchMain();
                await page.evaluate(script(typeQuery, { id: f.id, value: '' }, DEPS));
                await run(`search ${f.name} cleared`, 'search', { id: f.id, changed: true }, slow);
            }
        }
    });

    await recipe('sheets', async () => {
        for (const t of c.triggers.filter(fresh)) {
            if (out.length >= MAX_STATES) break;
            const url = page.url();
            const handle = page.locator(`[data-lint-act="${t.id}"]`).first();
            if (!(await handle.count()) || !(await handle.isVisible())) continue;
            try {
                await handle.click({ timeout: 4000 });
                let root = null;
                for (let i = 0; i < 20 && !root; i += 1) {
                    root = await page.evaluate(script(overlayRoot, t.target, DEPS));
                    if (!root) await sleep(100);
                }
                if (root) {
                    await settleState(page, watch, { network: true });
                    await run(`sheet ${t.target}`, 'sheet', '[data-lint-root]');
                } else {
                    out.push({ state: `sheet ${t.target}`, kind: 'sheet', ok: false, records: [], advice: [], note: 'no overlay opened' });
                }
            } catch (error) {
                out.push({ state: `sheet ${t.target}`, kind: 'sheet', ok: false, records: [], advice: [], note: String(error?.message ?? error).split('\n')[0] });
            } finally {
                await closeOverlay(page, t.target, url, watch);
            }
        }
    });

    await recipe('toasts', async () => {
        // once per label and surface, counted only when the toast showed: a copy control stays disabled until a
        // calculator has values, a hidden one is tried in its next specimen, and a static specimen (the styleguide's
        // navigator actions) raises none, which is no state (the next control of that label is tried)
        const shownOnce = new Set();
        for (const t of c.toasts) {
            if (out.length >= MAX_STATES) break;
            if (shownOnce.has(t.sig)) continue;
            await page.evaluate(script(toastMark, null, DEPS));
            if (!(await act({ id: t.id, how: 'click', enabled: true })).ok) continue;
            let root = null;
            for (let i = 0; i < 12 && !root; i += 1) {
                await sleep(100);
                root = await page.evaluate(script(toastRoot, null, DEPS));
            }
            if (!root) continue;
            shownOnce.add(t.sig);
            await run(`toast "${t.label}"`, 'toast', '[data-lint-root]');
        }
    });

    await recipe('tooltips', async () => {
        await tooltipStates(page, opt, target, watch, seen, 'main', out, MAX_STATES);
    });
    return out;
}

async function lintTarget(contexts, opt, target) {
    const context = await contexts.get(target.locale, target.width);
    const page = await context.newPage();
    const watch = watchPage(page, { expectStatus: target.expect });
    const result = { route: target.slug, locale: target.locale, width: target.width, path: target.path, status: null, ok: false, info: {}, records: [], advice: [], states: [] };
    const timing = [];
    let clock = Date.now();
    const lap = (name) => {
        const now = Date.now();
        timing.push(`${name} ${now - clock}`);
        clock = now;
    };
    try {
        let response = null;
        for (let attempt = 1; attempt <= 2; attempt += 1) {
            try {
                response = await page.goto(opt.base + target.path, { waitUntil: 'load', timeout: NAV_TIMEOUT });
                break;
            } catch (error) {
                if (attempt === 2) throw error;
                await sleep(1500);
            }
        }
        result.status = response ? response.status() : null;
        if (result.status !== target.expect) throw new Error(`HTTP ${result.status} (expected ${target.expect})`);
        const served = await page.evaluate(() => document.documentElement.getAttribute('data-design'));
        if (opt.design === '2' && served !== '2' && !opt.selfTest) throw new Error(`served data-design=${served ?? 'none'} for Design 2 (cookie not applied?)`);
        if (opt.design === '1' && served === '2') throw new Error('served Design 2 for --design=1');
        lap('load');
        await settlePage(page, watch);
        lap('settle');
        const lint = await page.evaluate(script(pageLint, { rules: opt.rules, width: target.width, scope: null, layout: true, whatsapp: true, wa01: opt.wa01, maxPerRule: MAX_PER_RULE, strictIllustrations: opt.illustrations === 'strict' }, DEPS));
        if (lint.info.error) throw new Error(lint.info.error);
        lap('lint');
        // Every hit of the page pass is "seen": a state reports only what it shows for the first time.
        const seen = new Set([...lint.records.map(keyOf), ...lint.advice.map((a) => a.key)]);
        await resolveCss(page, lint.records, opt);
        lap('set-by');
        result.records.push(...lint.records);
        result.advice.push(...lint.advice);
        result.info = lint.info;
        if (opt.rules.includes(16)) {
            const axe = await axeContrast(page, null, opt.illustrations === 'strict');
            for (const r of axe.records) seen.add(keyOf(r));
            result.records.push(...axe.records);
            result.info.axeIncomplete = axe.incomplete;
            lap('axe');
        }
        // Overlays first, while the page is at its top (the header changes its layout once scrolled), then the
        // hover test, which scrolls every card into view, then the interactive states, which change the page.
        if (target.states) {
            result.states.push(...(await overlayStates(page, opt, target, watch, seen)));
            lap('overlays');
        }
        if (opt.rules.includes(8) || opt.rules.includes(6)) {
            const hover = await hoverTest(page, new Set(opt.rules));
            for (const r of hover.records) seen.add(keyOf(r));
            result.records.push(...hover.records);
            result.info.hovered = hover.hovered;
            result.info.hoverSkipped = hover.skipped;
            lap('hover');
        }
        if (opt.states) {
            result.states.push(...(await interactiveStates(page, opt, target, watch, seen)));
            lap('states');
        }
        result.ok = true;
    } catch (error) {
        result.error = String(error?.message ?? error).split('\n')[0];
    } finally {
        await page.close().catch(() => {});
        contexts.release(target.locale, target.width);
    }
    if (process.env.QA_LINT_DEBUG) console.log(`  [timing ms] ${target.slug} ${target.locale} ${target.width}: ${timing.join(', ')}; states so far: ${Object.entries(stateClock).map(([k, v]) => `${k} ${v}`).join(', ')}`);
    return result;
}

/** One browser context per locale and width, opened on first use and closed after its last page. */
function contextPool(browser, opt, targets) {
    const pending = new Map();
    for (const t of targets) pending.set(`${t.locale}|${t.width}`, (pending.get(`${t.locale}|${t.width}`) ?? 0) + 1);
    const open = new Map();
    return {
        get(locale, width) {
            const key = `${locale}|${width}`;
            if (!open.has(key)) open.set(key, openContext(browser, opt, locale, width));
            return open.get(key);
        },
        async release(locale, width) {
            const key = `${locale}|${width}`;
            const left = (pending.get(key) ?? 1) - 1;
            pending.set(key, left);
            if (left === 0 && open.has(key)) {
                const context = await open.get(key);
                open.delete(key);
                await context.close().catch(() => {});
            }
        },
        async closeAll() {
            for (const c of open.values()) await (await c).close().catch(() => {});
        },
    };
}

async function pool(items, size, task, onDone) {
    const results = new Array(items.length);
    let next = 0;
    let done = 0;
    const worker = async () => {
        while (next < items.length) {
            const index = next++;
            results[index] = await task(items[index], index);
            done += 1;
            onDone?.(results[index], done, items.length);
        }
    };
    await Promise.all(Array.from({ length: Math.max(1, Math.min(size, items.length)) }, worker));
    return results;
}

// ------------------------------------------------------------------------------------------ Node: results

/**
 * Applies the exemptions (illustration art, safe-area insets, approved copy); returns the kept records and what was
 * exempted.
 */
export function applyExemptions(records, sources) {
    const kept = [];
    const exempt = { approvedEllipsis: 0, approvedSeparators: [], illustration: [], safeArea: [], specimenZoom: [] };
    for (const r of records) {
        if (r.exempt === 'illustration') {
            exempt.illustration.push(r);
            continue;
        }
        if (r.exempt === 'safe-area') {
            exempt.safeArea.push(r);
            continue;
        }
        if (r.exempt === 'specimen-zoom') {
            exempt.specimenZoom.push(r);
            continue;
        }
        if (r.rule === 5 && r.full !== undefined && sources.approvedEllipses.has(collapse(r.full))) {
            exempt.approvedEllipsis += r.count;
            continue;
        }
        if (r.rule === 3 && r.around && sources.approvedSeparators.some((s) => s.includes(collapse(r.around)))) {
            exempt.approvedSeparators.push(r);
            continue;
        }
        kept.push(r);
    }
    return { kept, exempt };
}

/** The §3.6 patterns the advice reports (no rule of §7.1 measures them; never counted). */
export const ADVICE = [
    { id: 4, title: 'Decorative numbering (01, 02, 03) on things that are not steps' },
    { id: 10, title: 'The same metadata repeated on every item' },
    { id: 11, title: 'Dashed or dotted boxes (empty states)' },
    { id: 12, title: 'A coloured word inside a heading' },
];

/** The kind of a state label: page, overlay, compact header, hover, or the interactive recipe (tab, choice …). */
export function stateKind(state) {
    const s = String(state || 'page');
    if (s === 'page' || s === 'hover') return s;
    if (s.startsWith('compact header')) return 'compact header';
    if (s.startsWith('overlay ')) return / preview "/.test(s) ? 'menu preview' : / results "/.test(s) ? 'search results' : 'overlay';
    const m = s.match(/^(details and disclosures open|tab|pressed|pager|choice|select|calculator|wizard|search|sheet|toast|tooltip)\b/);
    return m ? (m[1] === 'details and disclosures open' ? 'disclosures' : m[1]) : 'other';
}

/** Every kind of state the states phase lints (the self-test plants a violation that shows only in each). */
export const STATE_KINDS = ['overlay', 'menu preview', 'search results', 'compact header', 'tooltip', 'disclosures', 'tab', 'pressed', 'pager', 'choice', 'select', 'calculator', 'wizard', 'search', 'sheet', 'toast'];

/** Folds the page and state results into violations (rule order kept per page), advisories and the summary. */
export function summarise(results, sources, opt) {
    const violations = [];
    const advisories = [];
    const pages = [];
    const approvedSeparators = [];
    const illustrationExempt = Object.fromEntries(RULES.map((r) => [r.id, 0]));
    let approvedEllipsis = 0;
    let safeAreaExempt = 0;
    const safeAreaSources = new Map();
    const specimenZoom = { 9: 0, 10: 0 };
    for (const res of results) {
        const pageRecords = [];
        const pageAdvice = [];
        const pageIllustration = {};
        const take = (records, state) => {
            const { kept, exempt } = applyExemptions(records, sources);
            approvedEllipsis += exempt.approvedEllipsis;
            for (const r of exempt.approvedSeparators) approvedSeparators.push({ route: res.route, locale: res.locale, width: res.width, text: r.around, selector: r.selector });
            for (const r of exempt.illustration) {
                illustrationExempt[r.rule] += r.count ?? 1;
                pageIllustration[r.rule] = (pageIllustration[r.rule] ?? 0) + (r.count ?? 1);
            }
            for (const r of exempt.safeArea) {
                safeAreaExempt += r.count ?? 1;
                safeAreaSources.set(r.source ?? '?', (safeAreaSources.get(r.source ?? '?') ?? 0) + (r.count ?? 1));
            }
            for (const r of exempt.specimenZoom) specimenZoom[r.rule] = (specimenZoom[r.rule] ?? 0) + (r.count ?? 1);
            for (const r of kept) {
                pageRecords.push({ rule: r.rule, route: res.route, locale: res.locale, width: res.width, state: r.state ?? state ?? 'page', area: r.area, selector: r.selector, value: r.value, text: r.text, count: r.count ?? 1, ...(r.source ? { source: r.source } : {}), ...(r.file ? { file: r.file } : {}), ...(r.hidden ? { hidden: true } : {}) });
            }
        };
        const takeAdvice = (list, state) => {
            for (const a of list ?? []) pageAdvice.push({ pattern: a.pattern, route: res.route, locale: res.locale, width: res.width, state: a.state ?? state ?? 'page', area: a.area, selector: a.selector, value: a.value, text: a.text, count: a.count ?? 1 });
        };
        take(res.records, 'page');
        takeAdvice(res.advice, 'page');
        for (const st of res.states) {
            take(st.records, st.state);
            takeAdvice(st.advice, st.state);
        }
        // Rule order within a page (stable: document order, then overlay states, hover and the interactive states).
        pageRecords.sort((a, b) => a.rule - b.rule);
        pageAdvice.sort((a, b) => a.pattern - b.pattern);
        const counts = {};
        for (const r of pageRecords) counts[r.rule] = (counts[r.rule] ?? 0) + r.count;
        const adviceCounts = {};
        for (const a of pageAdvice) adviceCounts[a.pattern] = (adviceCounts[a.pattern] ?? 0) + a.count;
        violations.push(...pageRecords);
        advisories.push(...pageAdvice);
        pages.push({
            route: res.route,
            locale: res.locale,
            width: res.width,
            path: res.path,
            status: res.status,
            ok: res.ok,
            ...(res.error ? { error: res.error } : {}),
            counts,
            illustrationExempt: pageIllustration,
            placeholders: res.info?.placeholders ?? 0,
            whatsappLinks: res.info?.waLinks ?? 0,
            whatsappWithoutNewTab: res.info?.waNoNewTab ?? 0,
            whatsappMarkSources: res.info?.waMarkSources ?? [],
            digitNodes: res.info?.digitNodes,
            illustrations: res.info?.illustrations ?? 0,
            unmarkedDocuments: res.info?.unmarkedDocs ?? 0,
            overlaysMarked: res.info?.overlays ?? 0,
            ignoredRelativeSpacing: res.info?.ignoredRelative ?? 0,
            svgDrawings: res.info?.svgArt ?? 0,
            svgDrawingSamples: res.info?.svgArtSamples ?? [],
            axeIncomplete: res.info?.axeIncomplete ?? 0,
            cardsHovered: res.info?.hovered ?? 0,
            advice: adviceCounts,
            states: res.states.map((s) => ({ state: s.state, ...(s.label ? { label: s.label } : {}), ok: s.ok, ...(s.note ? { note: s.note } : {}) })),
        });
    }
    const byRule = Object.fromEntries(RULES.map((r) => [r.id, 0]));
    const byArea = {};
    const byRuleArea = {};
    const byState = {};
    for (const v of violations) {
        byRule[v.rule] += v.count;
        byArea[v.area] = (byArea[v.area] ?? 0) + v.count;
        byRuleArea[v.rule] ??= {};
        byRuleArea[v.rule][v.area] = (byRuleArea[v.rule][v.area] ?? 0) + v.count;
        const kind = stateKind(v.state);
        byState[kind] = (byState[kind] ?? 0) + v.count;
    }
    const advisory = Object.fromEntries(ADVICE.map((a) => [a.id, 0]));
    for (const a of advisories) advisory[a.pattern] = (advisory[a.pattern] ?? 0) + a.count;
    const statesLinted = {};
    for (const p of pages) for (const st of p.states) if (st.ok) statesLinted[stateKind(st.state)] = (statesLinted[stateKind(st.state)] ?? 0) + 1;
    const total = Object.values(byRule).reduce((a, b) => a + b, 0);
    return {
        violations,
        advisories,
        pages,
        summary: {
            total,
            byRule,
            byArea,
            byRuleArea,
            byState,
            pagesLinted: pages.filter((p) => p.ok).length,
            pagesFailed: pages.filter((p) => !p.ok).length,
            statesLinted,
            statesNotLinted: pages.reduce((sum, p) => sum + p.states.filter((s) => !s.ok).length, 0),
            whatsappPlaceholders: pages.reduce((sum, p) => sum + p.placeholders, 0),
            illustrationExempt,
            safeAreaExempt,
            safeAreaSources: [...safeAreaSources.entries()].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0])).map(([source, count]) => ({ source, count })),
            specimenZoomExempt: specimenZoom,
            approvedEllipsisExempt: approvedEllipsis,
            approvedSeparators,
            advisory,
        },
        options: opt,
    };
}

function writeJson(file, report, opt) {
    const json = {
        generator: 'scripts/qa/lint-design.mjs',
        format: 2,
        brief: 'docs/briefs/design-2.1-and-admin-cms.md §7.1',
        options: {
            base: opt.base,
            design: opt.design,
            routes: opt.routeFilter ?? 'all',
            locales: opt.locales,
            widths: opt.widths,
            rules: opt.rules,
            states: opt.states,
            illustrations: opt.illustrations,
            clock: opt.clock,
        },
        rules: RULES,
        advice: ADVICE,
        summary: report.summary,
        pages: report.pages,
        violations: report.violations,
        advisories: report.advisories,
    };
    mkdirSync(dirname(file), { recursive: true });
    writeFileSync(file, `${JSON.stringify(json, null, 1)}\n`);
}

const mdCell = (s) => String(s ?? '').replace(/\|/g, '\\|').replace(/\s+/g, ' ').trim();

export function renderMarkdown(report, opt) {
    const { summary, pages, violations } = report;
    const lines = [];
    const areaCols = [...new Set([...AREAS, ...Object.keys(summary.byArea)])].filter((a) => AREAS.includes(a) || summary.byArea[a]);
    lines.push('# design lint (brief §7.1)', '');
    lines.push(`Generated by \`scripts/qa/lint-design.mjs\`. Design ${opt.design}, ${opt.base}; routes ${opt.routeFilter ?? 'all'}; locales ${opt.locales.join(', ')}; widths ${opt.widths.join(', ')}; rules ${opt.rules.join(', ')}; states ${opt.states ? 'on' : 'off'}; illustrations ${opt.illustrations}; clock ${opt.clock}.`, '');
    lines.push(`**${summary.total} violation(s)** on ${summary.pagesLinted} page(s) linted${summary.pagesFailed ? `, **${summary.pagesFailed} page(s) could not be linted**` : ''}. A phase is done only at zero.`, '');
    const onStyleguide = violations.filter((v) => v.route === 'styleguide').reduce((s, v) => s + v.count, 0);
    lines.push(`Public pages: ${summary.total - onStyleguide}; the internal styleguide: ${onStyleguide}.`, '');
    const inStates = Object.entries(summary.byState ?? {}).filter(([k]) => k !== 'page');
    lines.push(`Found in the first render: ${summary.byState?.page ?? 0}; only in a state: ${inStates.reduce((s, [, n]) => s + n, 0)}${inStates.length ? ` (${inStates.sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0])).map(([k, n]) => `${k} ${n}`).join(', ')})` : ''}. Advice (§3.6 patterns without a rule, not counted): ${ADVICE.map((a) => `${a.id}: ${summary.advisory?.[a.id] ?? 0}`).join(', ')}.`, '');
    lines.push('## By rule', '');
    lines.push('| # | Rule | Violations | Pages | Distinct selectors | Fix list | Areas |');
    lines.push('|---|---|---|---|---|---|---|');
    for (const rule of RULES) {
        if (!opt.rules.includes(rule.id)) {
            lines.push(`| ${rule.id} | ${rule.title} | not run | | | ${rule.fixes.join(', ')} | |`);
            continue;
        }
        const list = violations.filter((v) => v.rule === rule.id);
        const pagesHit = new Set(list.map((v) => `${v.route}|${v.locale}|${v.width}`)).size;
        const selectors = new Set(list.map((v) => v.selector)).size;
        const areas = Object.entries(summary.byRuleArea[rule.id] ?? {}).sort((a, b) => b[1] - a[1]).map(([a, n]) => `${a} ${n}`).join(', ');
        lines.push(`| ${rule.id} | ${rule.title} (${rule.brief}) | ${summary.byRule[rule.id]} | ${pagesHit} | ${selectors} | ${rule.fixes.join(', ') || '-'} | ${areas || '-'} |`);
    }
    lines.push('', '## By area and rule', '');
    lines.push(`| Area | ${RULES.map((r) => r.id).join(' | ')} | Total |`);
    lines.push(`|---|${RULES.map(() => '---').join('|')}|---|`);
    for (const area of areaCols) {
        const row = RULES.map((r) => summary.byRuleArea[r.id]?.[area] ?? 0);
        lines.push(`| ${area} | ${row.map((n) => (n ? String(n) : '·')).join(' | ')} | ${row.reduce((a, b) => a + b, 0)} |`);
    }
    lines.push('', '## By route', '');
    const routes = new Map();
    for (const v of violations) {
        const r = routes.get(v.route) ?? { total: 0, rules: {}, where: new Set() };
        r.total += v.count;
        r.rules[v.rule] = (r.rules[v.rule] ?? 0) + v.count;
        r.where.add(`${v.locale} ${v.width}`);
        routes.set(v.route, r);
    }
    if (routes.size) {
        lines.push('| Route | Violations | By rule | Pages hit (locale × width) |');
        lines.push('|---|---|---|---|');
        for (const [route, r] of [...routes.entries()].sort((a, b) => b[1].total - a[1].total || a[0].localeCompare(b[0]))) {
            lines.push(`| ${route} | ${r.total} | ${Object.entries(r.rules).map(([k, n]) => `${k}: ${n}`).join(', ')} | ${r.where.size} |`);
        }
    } else {
        lines.push('No route has a violation.');
    }
    lines.push('', '## Most frequent selectors per rule', '');
    lines.push('Count = violating elements over every page, locale and width (identical elements on one page are folded). Example = one page where it occurs.', '');
    for (const rule of RULES) {
        const list = violations.filter((v) => v.rule === rule.id);
        if (!list.length) continue;
        const groups = new Map();
        for (const v of list) {
            const key = `${v.area}|${v.selector}|${v.value.replace(/[-\d.]+(?=\s*px|:1)/g, 'N')}`;
            const g = groups.get(key) ?? { area: v.area, selector: v.selector, value: v.value, text: v.text, count: 0, where: new Set(), example: `${v.route} ${v.locale} ${v.width}${v.state !== 'page' ? ` (${v.state})` : ''}` };
            g.count += v.count;
            g.where.add(`${v.route}|${v.locale}`);
            groups.set(key, g);
        }
        const top = [...groups.values()].sort((a, b) => b.count - a.count || a.selector.localeCompare(b.selector)).slice(0, 15);
        lines.push(`### ${rule.id}. ${rule.title}`, '');
        lines.push(`${summary.byRule[rule.id]} violation(s), ${groups.size} distinct; fix list ${rule.fixes.join(', ') || '-'}.`, '');
        lines.push('| Count | Area | Selector | Value | Text | Example |');
        lines.push('|---|---|---|---|---|---|');
        for (const g of top) lines.push(`| ${g.count} | ${g.area} | \`${mdCell(g.selector)}\` | ${mdCell(g.value).slice(0, 140)} | ${mdCell(g.text).slice(0, 70)} | ${g.example} |`);
        lines.push('');
    }
    const sourced = violations.filter((v) => v.source);
    if (sourced.length) {
        lines.push('## Set by: the CSS rule behind each value (rules 6, 7, 9, 10)', '');
        lines.push('The declaration that wins the cascade on the first violating element of each record (DevTools protocol), or the user-agent default; for font size and weight the nearest ancestor that sets it ("inherited"). File: the stylesheet that holds it (under `resources/css/d2/`); these rules go to that file\'s area (`AREA_FILES`), the element\'s location only when no file is known. One row per rule and declaration, over every page, locale and width.', '');
        for (const rule of RULES.filter((r) => sourced.some((v) => v.rule === r.id))) {
            const groups = new Map();
            for (const v of sourced.filter((x) => x.rule === rule.id)) {
                const g = groups.get(v.source) ?? { count: 0, areas: new Map(), file: v.file ?? '', value: v.value, example: `${v.route} ${v.locale} ${v.width}${v.state !== 'page' ? ` (${v.state})` : ''}` };
                g.count += v.count;
                g.areas.set(v.area, (g.areas.get(v.area) ?? 0) + v.count);
                groups.set(v.source, g);
            }
            const top = [...groups.entries()].sort((a, b) => b[1].count - a[1].count || a[0].localeCompare(b[0])).slice(0, 40);
            lines.push(`### ${rule.id}. ${rule.title}: ${groups.size} declaration(s)`, '');
            lines.push('| Count | Areas | File | Set by | Value (example) | Example |');
            lines.push('|---|---|---|---|---|---|');
            for (const [source, g] of top) lines.push(`| ${g.count} | ${[...g.areas.entries()].map(([a, n]) => `${a} ${n}`).join(', ')} | ${g.file ? `\`${mdCell(g.file).replace(/^resources\/css\/d2\//, '')}\`` : '-'} | \`${mdCell(source).slice(0, 220)}\` | ${mdCell(g.value).slice(0, 90)} | ${g.example} |`);
            lines.push('');
        }
    }
    lines.push('## WhatsApp marks and links (rule 14)', '');
    const waPages = pages.filter((p) => p.whatsappLinks);
    lines.push(`- ${pages.reduce((s, p) => s + p.whatsappLinks, 0)} WhatsApp link(s) on ${waPages.length} page(s); ${summary.whatsappPlaceholders} show the placeholder mark (\`data-placeholder="whatsapp-logo"\`, allowed until Meta's files are in \`resources/brand/whatsapp/\`).`);
    const sources = [...new Set(pages.flatMap((p) => p.whatsappMarkSources))];
    lines.push(`- Mark sources seen: ${sources.length ? sources.map((s) => `\`${s}\``).join(', ') : 'none'}.`);
    lines.push(`- Advice (§5.2, not counted): ${pages.reduce((s, p) => s + p.whatsappWithoutNewTab, 0)} WhatsApp link(s) without target="_blank" and rel="noopener noreferrer".`, '');
    lines.push('## Exempted and advisory', '');
    const ill = Object.entries(summary.illustrationExempt ?? {}).filter(([, n]) => n);
    lines.push(opt.illustrations === 'strict'
        ? '- Illustrations: strict (rules 3, 5, 9, 10, 13 and 16 count inside `[data-illustration]` too; the brief notes the exemption for rules 1, 2, 6 and 7 only).'
        : `- On the sample paper of \`[data-illustration]\` (\`.doc\`, \`[data-doc]\`, drawings: art, not counted; \`--illustrations=strict\` counts them): ${ill.length ? ill.map(([r, n]) => `rule ${r}: ${n}`).join(', ') : 'none'}. The brief notes the exemption for rules 1, 2, 6 and 7 (never measured inside an illustration); rules 4, 8, 11, 12, 14 and 15 apply inside illustrations as everywhere; interface text an illustration holds outside its paper (callout labels, pin numbers) counts like any text.`);
    lines.push(`- Rule 10: ${summary.safeAreaExempt ?? 0} value(s) set by a safe-area inset (\`var(--safe-*)\`, \`env(safe-area-inset-*)\`): device geometry, not a spacing step, not counted${summary.safeAreaSources?.length ? ` (${summary.safeAreaSources.slice(0, 4).map((x) => `\`${mdCell(x.source).slice(0, 120)}\` ${x.count}`).join('; ')})` : ''}.`);
    const zoomed = summary.specimenZoomExempt ?? {};
    lines.push(`- Rules 9 and 10 measure as drawn (computed value × the scale and zoom the box is drawn at). In a styleguide specimen that zooms a component to fit its card (CSS zoom inside \`.sg-specimen\` or \`[data-lint-specimen]\`), a value off the scale only because of that zoom, at its own tokens on the scale and text still 13 px or more, is not counted: rule 9: ${zoomed[9] ?? 0}, rule 10: ${zoomed[10] ?? 0}.`);
    lines.push(`- Rule 5: ${summary.approvedEllipsisExempt} text node(s) ending with an ellipsis are complete approved strings (the form's submitting state), not truncations.`);
    const seps = new Map();
    for (const s of summary.approvedSeparators) seps.set(s.text, (seps.get(s.text) ?? 0) + 1);
    lines.push(`- Rule 3: ${summary.approvedSeparators.length} separator(s) belong to approved DOCX copy and are not counted (approved copy is never rewritten). Owner or client question: ${seps.size ? [...seps.keys()].slice(0, 8).map((t) => `"${t}"`).join(', ') : 'none'}.`);
    const unmarked = pages.filter((p) => p.unmarkedDocuments);
    lines.push(`- Sample documents without \`[data-illustration]\` (their paper art counts against rules 1-3, 5-7, 9, 10, 13 and 16 until marked): ${unmarked.length ? `${unmarked.length} page(s), e.g. ${unmarked.slice(0, 4).map((p) => `${p.route} ${p.locale} ${p.width} (${p.unmarkedDocuments})`).join(', ')}` : 'none'}.`);
    const mixed = pages.filter((p) => p.locale === 'ar' && p.digitNodes && p.digitNodes.western && p.digitNodes.eastern);
    lines.push(`- Rule 15 advice (§3.7: a page never mixes): ${mixed.length} Arabic page(s) show both digit systems in different text nodes${mixed.length ? `, e.g. ${mixed.slice(0, 4).map((p) => `${p.route} ${p.width} (${p.digitNodes.western} Western, ${p.digitNodes.eastern} Eastern)`).join(', ')}` : ''}.`);
    lines.push(`- Rule 10: ${pages.reduce((s, p) => s + p.ignoredRelativeSpacing, 0)} auto or percentage value(s) ignored.`);
    const art = pages.filter((p) => p.svgDrawings);
    lines.push(`- Advice (§3.6 rule 9, D27; no rule of §7.1): ${art.reduce((s, p) => s + p.svgDrawings, 0)} inline SVG drawing(s) larger than 64 px outside \`[data-illustration]\` that are neither icons nor brand marks (generated covers, patterns) on ${art.length} page(s)${art.length ? `, e.g. ${[...new Set(art.flatMap((p) => p.svgDrawingSamples))].slice(0, 4).map((x) => `\`${mdCell(x)}\``).join(', ')}` : ''}.`);
    lines.push(`- Rule 16: ${pages.reduce((s, p) => s + p.axeIncomplete, 0)} axe "incomplete" node(s) (contrast that axe could not decide, e.g. text over images); not counted.`, '');
    lines.push('## Advice: §3.6 patterns without a rule of §7.1 (not counted)', '');
    lines.push('Reported so that Phase 2 can prove zero; an owner decides each one. 4: a zero-padded number standing alone (or a `decimal-leading-zero` counter) outside steps (`ol`, `[data-steps]`, the wizard\'s steps, a class with "step"); 10: a short text (40 characters at most; small, with a digit, or in a chip, badge, tag, meta, time, status or timeline element; never a heading, the label of a control, a card\'s action cue, underlined text, a label ending with a colon or the label of a label/value pair) on every item of one kind (tag and classes, modifiers and states aside) in a list or grid of three or more; 11: a box with three or four dashed or dotted sides, 24 px or more; 12: text in another colour than most of its heading (a muted run of the neutral ink ramp and a step number in a step context aside).', '');
    lines.push('| §3.6 | Pattern | Count | Pages | Distinct |');
    lines.push('|---|---|---|---|---|');
    for (const a of ADVICE) {
        const list = (report.advisories ?? []).filter((x) => x.pattern === a.id);
        lines.push(`| ${a.id} | ${a.title} | ${summary.advisory?.[a.id] ?? 0} | ${new Set(list.map((x) => `${x.route}|${x.locale}|${x.width}`)).size} | ${new Set(list.map((x) => `${x.selector}|${x.value}`)).size} |`);
    }
    lines.push('');
    for (const a of ADVICE) {
        const list = (report.advisories ?? []).filter((x) => x.pattern === a.id);
        if (!list.length) continue;
        const groups = new Map();
        for (const x of list) {
            const key = `${x.area}|${x.selector}|${x.value}`;
            const g = groups.get(key) ?? { ...x, count: 0, where: new Set(), example: `${x.route} ${x.locale} ${x.width}${x.state !== 'page' ? ` (${x.state})` : ''}` };
            g.count += x.count;
            g.where.add(`${x.route}|${x.locale}|${x.width}`);
            groups.set(key, g);
        }
        lines.push(`### Advice ${a.id}. ${a.title}`, '');
        lines.push('| Count | Pages | Area | Selector | Value | Text | Example |');
        lines.push('|---|---|---|---|---|---|---|');
        for (const g of [...groups.values()].sort((x, y) => y.count - x.count || x.selector.localeCompare(y.selector) || x.value.localeCompare(y.value)).slice(0, 20)) {
            lines.push(`| ${g.count} | ${g.where.size} | ${g.area} | \`${mdCell(g.selector)}\` | ${mdCell(g.value).slice(0, 110)} | ${mdCell(g.text).slice(0, 60)} | ${g.example} |`);
        }
        lines.push('');
    }
    const failed = pages.filter((p) => !p.ok);
    const stateNotes = pages.flatMap((p) => p.states.filter((s) => !s.ok).map((s) => `${p.route} ${p.locale} ${p.width}: ${s.state} (${s.note ?? 'not linted'})`));
    lines.push('## Pages and states', '');
    lines.push(`- Linted: ${summary.pagesLinted}; failed: ${failed.length}${failed.length ? ` (${failed.map((p) => `${p.route} ${p.locale} ${p.width}: ${p.error}`).join('; ')})` : ''}.`);
    const linted = Object.entries(summary.statesLinted ?? {}).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
    lines.push(`- States linted: ${linted.reduce((s, [, n]) => s + n, 0)}${linted.length ? ` (${linted.map(([k, n]) => `${k} ${n}`).join(', ')})` : ''}. Overlays, the menu previews, the search results, the shell's tooltips and the compact header on the first route of each locale and width; the interactive states (details and disclosures, tabs, pressed groups and filter chips, pagers, radio choices, selects, calculators with values, the form wizard, in-page searches with and without results, in-page sheets, toasts, tooltips) on every page. A state reports only what the page has not reported yet.`);
    lines.push(`- States not linted: ${stateNotes.length}${stateNotes.length ? ` (${stateNotes.slice(0, 12).join('; ')}${stateNotes.length > 12 ? '; …' : ''})` : ''}.`, '');
    lines.push('## Rule to fix list and areas', '');
    lines.push('| # | Slug | Fix list | Brief | Usual areas |');
    lines.push('|---|---|---|---|---|');
    const usual = {
        1: 'components (labels, kbd), header (mega menu), footer-shell (group headings), home-a/home-b (eyebrows), platform (code, kbd)',
        2: 'as rule 1',
        3: 'header (language pill), time-digits (live status), home-a (hero caption), components',
        4: 'components (buttons), every area that passes icon-end or an arrow character',
        5: 'home-a (service summaries), inner, components (card clamps)',
        6: 'components (buttons, cards, fields, segmented), header (overlays need data-overlay), footer-shell (sheets), platform/home-a (styleguide specimens need data-overlay or data-illustration)',
        7: 'components (gold buttons), home-a (hero light), header',
        8: 'components (card), home-a/home-b/inner card variants',
        9: 'platform (strong, base), components, every area with its own sizes',
        10: 'every area (the Stylelint list in tokens.md §12)',
        11: 'header, footer-shell, home-a, home-b, inner (each band and its .container)',
        12: 'home-a (services), home-b (cases, guides, team), inner (hub, case and guide cards)',
        13: 'components (chips, cards), header (mega menu, language), footer-shell (tab bar, footer), home-a/home-b/inner (decorative icons)',
        14: 'components (the brand mark), header, footer-shell, home-a (hero), home-b (contact), inner (CTA cards)',
        15: 'time-digits',
        16: 'components, every area',
    };
    for (const r of RULES) lines.push(`| ${r.id} | ${r.slug} | ${r.fixes.join(', ') || '-'} | ${r.brief} | ${usual[r.id]} |`);
    lines.push('');
    return `${lines.join('\n')}\n`;
}

// ------------------------------------------------------------------------------------------ CLI

const USAGE = `Usage: node scripts/qa/lint-design.mjs [--routes=<slugs>] [--locales=en,ar,ru,root] [--widths=1440,768,390]
       [--rules=<numbers or slugs>] [--base=<url>] [--design=1|2] [--concurrency=<n>] [--states=on|off]
       [--illustrations=art|strict] [--clock=<ISO>|real] [--out=<prefix>] [--self-test [--verbose]] [--help]
See the header of scripts/qa/lint-design.mjs for the rules, exemptions and decisions.`;

class UsageError extends Error {}

const splitList = (value) => String(value).split(',').map((s) => s.trim()).filter(Boolean);

export function parseArgs(argv) {
    const opt = { base: DEFAULT_BASE, design: '2', concurrency: 4, states: true, illustrations: 'art', clock: DEFAULT_CLOCK, out: DEFAULT_OUT, selfTest: false, verbose: false, help: false, routes: null, locales: null, widths: null, rules: null };
    for (const arg of argv) {
        const [, key, value] = arg.match(/^--([a-z-]+)(?:=(.*))?$/) ?? [];
        if (key === 'self-test' || key === 'help' || key === 'verbose') {
            if (value !== undefined) throw new UsageError(`--${key} takes no value`);
            opt[key === 'self-test' ? 'selfTest' : key] = true;
        } else if (['base', 'design', 'concurrency', 'states', 'illustrations', 'clock', 'out', 'routes', 'locales', 'widths', 'rules'].includes(key)) {
            if (!value) throw new UsageError(`--${key} needs a value`);
            opt[key] = value;
        } else {
            throw new UsageError(`unknown argument: ${arg}`);
        }
    }
    try {
        opt.base = new URL(opt.base).origin;
    } catch {
        throw new UsageError(`--base is not a URL: ${opt.base}`);
    }
    if (!['1', '2'].includes(String(opt.design))) throw new UsageError('--design must be 1 or 2');
    opt.design = String(opt.design);
    opt.concurrency = Number(opt.concurrency);
    if (!Number.isInteger(opt.concurrency) || opt.concurrency < 1 || opt.concurrency > 12) throw new UsageError('--concurrency must be a whole number from 1 to 12');
    if (typeof opt.states === 'string') {
        if (!['on', 'off'].includes(opt.states)) throw new UsageError('--states must be on or off');
        opt.states = opt.states === 'on';
    }
    if (!['art', 'strict'].includes(opt.illustrations)) throw new UsageError('--illustrations must be art or strict');
    opt.clockMs = opt.clock === 'real' ? null : Date.parse(opt.clock);
    if (opt.clock !== 'real' && !Number.isFinite(opt.clockMs)) throw new UsageError(`--clock must be an ISO date-time or "real" (got ${opt.clock})`);
    opt.widths = opt.widths ? splitList(opt.widths).map(Number) : [...DEFAULT_WIDTHS];
    if (opt.widths.some((w) => !Number.isInteger(w) || w < 320 || w > 2560)) throw new UsageError('--widths must be whole CSS pixel widths from 320 to 2560');
    if (opt.rules) {
        const ids = splitList(opt.rules).map((r) => {
            const rule = RULES.find((x) => String(x.id) === r || x.slug === r.toLowerCase());
            if (!rule) throw new UsageError(`unknown rule: ${r} (1-16 or ${RULES.map((x) => x.slug).join(', ')})`);
            return rule.id;
        });
        opt.rules = [...new Set(ids)].sort((a, b) => a - b);
    } else {
        opt.rules = RULES.map((r) => r.id);
    }
    return opt;
}

function selectTargets(opt) {
    const registry = readRegistry();
    const routes = buildRoutes(registry);
    const allLocales = [...registry.locales, 'root'];
    const locales = opt.locales ? splitList(opt.locales) : allLocales;
    for (const l of locales) if (!allLocales.includes(l)) throw new UsageError(`unknown locale: ${l} (${allLocales.join(', ')})`);
    const slugs = [...new Set(routes.map((r) => r.slug))];
    const patterns = opt.routes
        ? splitList(opt.routes).map((p) => {
              const re = new RegExp(`^${p.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replaceAll('*', '.*')}$`);
              if (!slugs.some((s) => re.test(s))) throw new UsageError(`no route matches "${p}" (routes: ${slugs.join(', ')})`);
              return re;
          })
        : null;
    // Routes of one design only (the Design 2 calculators page) are linted only in that design.
    const selected = routes.filter((r) => locales.includes(r.locale) && (!patterns || patterns.some((re) => re.test(r.slug))) && (!r.designs || r.designs.includes(String(opt.design))));
    if (!selected.length) throw new UsageError('no route matches the filters');
    const stateRoute = selected.some((r) => r.slug === 'home') ? 'home' : selected[0].slug;
    const targets = [];
    for (const locale of locales) {
        for (const width of opt.widths) {
            for (const r of selected.filter((x) => x.locale === locale)) targets.push({ ...r, width, states: opt.states && r.slug === stateRoute && r.locale !== 'root' });
        }
    }
    opt.locales = locales;
    opt.routeFilter = opt.routes ?? null;
    return targets;
}

/** The site answers with the selected design, and its stylesheets load (exit 2 otherwise). */
async function preflight(opt) {
    let html;
    try {
        const response = await fetch(`${opt.base}/en/`, { headers: { cookie: `summit_design=${opt.design}; summit_qa=1` }, signal: AbortSignal.timeout(20_000) });
        html = await response.text();
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
    } catch (error) {
        throw new Error(`${opt.base}/en/ cannot be reached: ${error.message}`);
    }
    for (const tag of [...html.matchAll(/<link\b[^>]*>/gi)].map((m) => m[0]).filter((t) => /\brel=["'][^"']*\bstylesheet\b/i.test(t))) {
        const href = tag.match(/\bhref=["']([^"']+)["']/i)?.[1];
        if (!href) continue;
        const url = new URL(href.replaceAll('&amp;', '&'), `${opt.base}/en/`).href;
        const response = await fetch(url, { signal: AbortSignal.timeout(20_000) }).catch((error) => ({ ok: false, status: error.message }));
        if (!response.ok) throw new Error(`stylesheet ${url} cannot be loaded (${response.status}); is the Vite dev server (or the build) there?`);
    }
}

async function run(opt, targets, { log = console.log } = {}) {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const contexts = contextPool(browser, opt, targets);
    const started = Date.now();
    let results;
    try {
        results = await pool(targets, opt.concurrency, (t) => lintTarget(contexts, opt, t), (res, done, total) => {
            const n = res.records.reduce((s, r) => s + (r.count ?? 1), 0) + res.states.reduce((s, st) => s + st.records.reduce((a, r) => a + (r.count ?? 1), 0), 0);
            log(`${String(done).padStart(4)}/${total} ${res.ok ? 'ok  ' : 'FAIL'} ${`${res.route} ${res.locale} ${res.width}`.padEnd(46)} ${String(n).padStart(5)} raw${res.states.length ? `, ${res.states.filter((s) => s.ok).length}/${res.states.length} states` : ''}${res.error ? `  ${res.error}` : ''}`);
        });
    } finally {
        await contexts.closeAll();
        await browser.close();
    }
    return { results, seconds: (Date.now() - started) / 1000 };
}

function printSummary(report, opt, seconds) {
    console.log(`\ndesign lint, design ${opt.design}: ${report.summary.total} violation(s) on ${report.summary.pagesLinted} page(s) in ${seconds.toFixed(0)} s${report.summary.pagesFailed ? `; ${report.summary.pagesFailed} page(s) FAILED` : ''}`);
    for (const r of RULES) {
        if (!opt.rules.includes(r.id)) continue;
        const areas = Object.entries(report.summary.byRuleArea[r.id] ?? {}).sort((a, b) => b[1] - a[1]).map(([a, n]) => `${a} ${n}`).join(', ');
        console.log(`  ${String(r.id).padStart(2)} ${r.slug.padEnd(9)} ${String(report.summary.byRule[r.id]).padStart(6)}${areas ? `  (${areas})` : ''}`);
    }
    const ill = Object.values(report.summary.illustrationExempt ?? {}).reduce((a, b) => a + b, 0);
    const zoomed = Object.values(report.summary.specimenZoomExempt ?? {}).reduce((a, b) => a + b, 0);
    console.log(`  WhatsApp links showing the placeholder mark: ${report.summary.whatsappPlaceholders}; not counted: ${ill} on illustration paper (art), ${report.summary.safeAreaExempt} safe-area inset(s), ${zoomed} in zoomed styleguide specimen(s), ${report.summary.approvedSeparators.length} approved separator(s), ${report.summary.approvedEllipsisExempt} approved ellipsis string(s)`);
    const states = Object.values(report.summary.statesLinted ?? {}).reduce((a, b) => a + b, 0);
    const inStates = Object.entries(report.summary.byState ?? {}).filter(([k]) => k !== 'page').reduce((a, [, n]) => a + n, 0);
    console.log(`  States linted: ${states} (${report.summary.statesNotLinted} not); violations only in a state: ${inStates}; advice (not counted): ${ADVICE.map((a) => `${a.id}: ${report.summary.advisory[a.id]}`).join(', ')}`);
}

// ------------------------------------------------------------------------------------------ self-test

const MIME = { '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.json': 'application/json', '.svg': 'image/svg+xml' };

/** Serves a folder (GET only, / -> index.html) on a free port of 127.0.0.1. */
function serveFolder(dir) {
    return new Promise((resolveServer, reject) => {
        const server = createServer((req, res) => {
            const url = new URL(req.url, 'http://127.0.0.1');
            let file = join(dir, decodeURIComponent(url.pathname));
            if (!file.startsWith(dir) || req.method !== 'GET') {
                res.writeHead(403).end();
                return;
            }
            if (existsSync(file) && statSync(file).isDirectory()) file = join(file, 'index.html');
            if (!existsSync(file)) {
                res.writeHead(404, { 'content-type': 'text/plain' }).end('not found');
                return;
            }
            res.writeHead(200, { 'content-type': MIME[extname(file)] ?? 'application/octet-stream', 'cache-control': 'no-store' }).end(readFileSync(file));
        });
        server.on('error', reject);
        server.listen(0, '127.0.0.1', () => resolveServer(server));
    });
}

/**
 * Fixture pages (qa/lint/fixture/) and what each rule must find there: one pattern per expected violation,
 * matched against "area | state | selector | value | text | set by" one to one, so a missed planted violation can
 * never be hidden by a false positive elsewhere. Every rule fires exactly once on the main fixture (rule 15 on the
 * Arabic one). "art" counts the hits on illustration paper that are recorded as exempt (not counted); the self-test
 * then lints the main fixture again with --illustrations=strict, where each of those counts too. "advice": the §3.6
 * patterns reported apart, one pattern per advisory ("area | state | selector | value | text", one to one); "shell":
 * the overlay states run there (the first route); "states": states that must be linted (by label); every kind of
 * state (STATE_KINDS) has a violation on a fixture that shows only in that state (states.html holds the kinds the
 * other pages do not); "safeArea": rule 10 values set by a safe-area inset (not counted). Areas: the self-test maps
 * qa/lint/fixture/fixture.css to "components" (FIXTURE_AREA_FILES), so rules 6, 7, 9 and 10 set there land in
 * components, the rest in the area of the location (inner: a page other than home).
 */
export const FIXTURES = [
    {
        slug: 'fixture',
        locale: 'en',
        path: '/en/',
        shell: true,
        placeholders: 1,
        expect: {
            1: [/Reference 0001/],
            2: [/uppercase.*Practice areas/],
            3: [/separator "·" in "now · closes".*Open now · closes at 6 pm/],
            4: [/arrow →.*Read more/],
            5: [/ends with an ellipsis.*has been cut/],
            6: [/^components \| page \| .*box-shadow.*Raised panel \| \.shadowed \{ box-shadow: 0 2px 8px rgb\(0 0 0 \/ 0\.2\) \}/],
            7: [/^components \| page \| .*linear-gradient.*Promotional band \| \.promo \{ background-image: linear-gradient\(90deg, #fff, #eee\) \}/],
            8: [/^components \| hover \| .*card--lift \| box moves on hover.*Second card/],
            9: [/^components \| page \| .*font-size 12px \(below 13 px\).*Fine print \| \.tiny \{ font-size: 12px \}/],
            10: [/^components \| page \| .*padding-.*10px.*Odd padding \| \.odd \{ padding: 10px \}/],
            11: [/section--off.*left \+16, right -16/],
            12: [/title tops differ.*Guide one/],
            13: [/^header \| overlay #fx-menu \| .*"check" inside a menu item.*First item/],
            14: [/draft text differs.*no \[data-brand="whatsapp"\] mark.*Chat on WhatsApp/],
            16: [/contrast 2\.\d+:1.*Low contrast note/],
        },
        art: { 3: 1, 5: 1, 9: 2, 10: 1, 13: 1, 16: 1 },
        advice: {},
        states: ['overlay #fx-menu'],
        safeArea: 0,
        svgDrawings: 0,
    },
    { slug: 'fixture', locale: 'ar', path: '/ar/', shell: false, placeholders: 0, expect: { 15: [/one text node.*من 9 صباحاً حتى ٦/] }, art: {}, advice: {}, states: [], safeArea: 0, svgDrawings: 0 },
    {
        slug: 'variants',
        locale: 'en',
        path: '/en/variants/',
        shell: false,
        placeholders: 0,
        expect: {
            2: [/capitals with letter-spacing.*PRACTICE AREAS/],
            3: [/separator "·" in "EN · RU"/, /separator "·" \(::before\) in "SAMPLE · ILLUSTRATION"/, /^inner \| tab "Second tab" \| .*separator "·" in "panel · hidden"/],
            4: [/::after|arrow →.*Next page/],
            5: [
                /line-clamp 1.*approved summary/,
                /^inner \| page \| .*input\.narrow-field \| placeholder cut by the field edge: \d+ px of \d+ px hidden \| Enter a word such as cheque, salary or tenancy$/,
                /^inner \| page \| .*select\.narrow-select \| chosen option cut by the field edge: \d+ px of \d+ px hidden \| You paid for a service but did not receive it$/,
            ],
            6: [/text-shadow.*Glowing headline \| \.glow \{ text-shadow: 0 0 8px rgb\(201 169 107 \/ 0\.6\) \}/],
            9: [
                /^components \| page \| .*font-weight 700.*Bold text \| \.bold \{ font-weight: 700 \}/,
                // as drawn: 15 px in a canvas at 0.9 shows 13.5 px (the label sized ÷ 0.949 at 0.949 shows 15 px: no hit)
                /^components \| page \| .*div\.ill-callout \| font-size 13\.5px \(15px at scale 0\.9\) \| Scaled label \| \.ill-callout \{ font-size: 15px \}$/,
                /^components \| details and disclosures open \| .*font-size 12px \(below 13 px\) \| Hidden fine print \| \.tiny \{ font-size: 12px \}/,
            ],
            10: [
                /^components \| page \| .*row-gap 10px; column-gap 10px.* \| \.gapped \{ gap: 10px \}/,
                /^inner \| page \| .*padding-.*10px.*Spacing set by the page's own style element \| \.inpage \{ padding: 10px \}/,
                /^components \| page \| .*div\.ill-callout \| padding-block-start 7\.2px \(8px at scale 0\.9\); padding-block-end 7\.2px \(8px at scale 0\.9\); padding-inline-start 10\.8px \(12px at scale 0\.9\); padding-inline-end 10\.8px \(12px at scale 0\.9\) \| Scaled label \| \.ill-callout \{ padding: 8px 12px \}$/,
            ],
            13: [
                /without data-icon \(#i-check\)/,
                /"clock" outside the contact block/,
                /^inner \| details and disclosures open \| .*"check" inside a chip \| Hidden chip/,
                /^inner \| page \| .*"check" inside a chip \| Chip label/,
                /"info" inside a card title/,
                /"external" on a link that stays/,
                /"briefcase" is not in the allowed list.*Gallery/,
                /a drawn "whatsapp" icon/,
            ],
            14: [/no \[data-brand="whatsapp"\] mark.*without a mark/, /address https:\/\/api\.whatsapp\.com.*extra parameter phone.*another address/],
        },
        art: { 9: 1 },
        advice: {
            4: [/zero-padded numbering on something that is not a step \| 01$/, /zero-padded numbering on something that is not a step \| 02$/, /zero-padded numbering on something that is not a step \| 03$/],
            // by kind: the three service items (one with a BEM modifier) beside a closing tile of another kind; the
            // repeated <dt> labels of label/value pairs are not metadata
            10: [/"3 min read" on all 3 items \| 3 min read$/, /"4 documents usually needed" on all 3 items \| 4 documents usually needed$/],
            11: [/dashed border on 4 sides \(an empty state\?\) \| Nothing here yet$/],
            // the bronze word only: the muted (ink-3) run and the bronze step number "1." are not coloured words
            12: [/span\.accent \| rgb\(138, 106, 46\) inside a heading in rgb\(11, 11, 12\) \| follow$/],
        },
        states: ['details and disclosures open', 'tab "Second tab"'],
        safeArea: 1,
        svgDrawings: 1,
    },
    {
        slug: 'states',
        locale: 'en',
        path: '/en/states/',
        shell: true,
        placeholders: 0,
        expect: {
            1: [/^header \| overlay #fx-mega preview "Second topic" \| .*font-family "Courier New", monospace \| Preview code 0002$/],
            2: [/^inner \| choice fx-choice "Option B" \| .*text-transform: uppercase with letter-spacing 0\.12em \| For businesses$/],
            3: [
                /^home-b \| overlay modal:palette results "cheque" \| .*separator "·" in "Cheque · guide"/,
                /^header \| tooltip "Search · shortcut" \| .*separator "·" in "Search · shortcut"/,
                /^inner \| pressed "Filter B" \| .*separator "·" in "b · filtered"/,
                /^inner \| search fx-find cleared \| .*separator "·" in "topics · shown"/,
                /^inner \| sheet sheet:toc \| .*separator "·" in "Contents · page"/,
                /^footer-shell \| toast "Copy link" \| .*separator "·" in "copied · ready"/,
                /^inner \| tooltip "Help · more" \| .*separator "·" in "Help · more"/,
            ],
            4: [
                /^header \| compact header \| .*arrow ↑ \| Back to top ↑$/,
                /^inner \| pager "Slide two" \| .*arrow → \| next →$/,
                /^inner \| wizard step 2 \| .*arrow » \| Continue »$/,
                /^inner \| search fx-find "cheque" \| .*arrow › \| Cheque topic ›$/,
            ],
            5: [
                /^inner \| select fx-select "Second option" \| .*ends with an ellipsis \| Second option chosen…$/,
                /^inner \| wizard: Next on an empty step \| .*ends with an ellipsis \| Please add your name…$/,
            ],
            9: [
                /^components \| calculator fx-calc with values \| .*font-size 12px \(below 13 px\) \| Estimate shown here \| \.tiny \{ font-size: 12px \}$/,
                /^components \| search fx-find "zzqx" \| .*font-weight 700 \| this word \| \.bold \{ font-weight: 700 \}$/,
            ],
            13: [/^inner \| pressed "Panel two" \| .*"check" inside a chip \| Panel chip$/],
        },
        art: {},
        advice: {},
        states: [
            'overlay #fx-mega',
            'overlay #fx-mega preview "Second topic"',
            'overlay modal:palette',
            'overlay modal:palette results "cheque"',
            'tooltip "Search · shortcut"',
            'compact header',
            'pressed "Panel two"',
            'pressed "Filter B"',
            'pager "Slide two"',
            'choice fx-choice "Option B"',
            'select fx-select "Second option"',
            'calculator fx-calc with values',
            'wizard: Next on an empty step',
            'wizard step 2',
            'search fx-find "cheque"',
            'search fx-find "zzqx"',
            'search fx-find cleared',
            'sheet sheet:toc',
            'toast "Copy link"',
            'tooltip "Help · more"',
        ],
        safeArea: 0,
        svgDrawings: 0,
    },
];

/** The self-test's area map: the fixture stylesheet is "components" (proves that rules 6, 7, 9, 10 follow the file). */
const FIXTURE_AREA_FILES = { components: ['qa/lint/fixture/fixture.css'] };

/** WA01 drafts of the fixture (never the client's): the self-test needs no content file. */
const FIXTURE_WA01 = {
    en: 'https://wa.me/10000000000?text=Fixture%20draft%20for%20the%20lint',
    ar: 'https://wa.me/10000000000?text=%D9%85%D8%B3%D9%88%D8%AF%D8%A9',
};

/** Approved copy of the fixture (the self-test reads no content file). */
const FIXTURE_SOURCES = { approvedEllipses: new Set(['Sending request…']), approvedSeparators: ['Working Languages: Arabic · English · Russian'], approved: [] };

async function selfTest(opt) {
    const dir = mkdtempSync(join(tmpdir(), 'lint-design-'));
    let server;
    try {
        cpSync(join(FIXTURE_DIR, 'fixture.css'), join(dir, 'fixture.css'));
        mkdirSync(join(dir, 'en/variants'), { recursive: true });
        mkdirSync(join(dir, 'en/states'), { recursive: true });
        mkdirSync(join(dir, 'ar'), { recursive: true });
        cpSync(join(FIXTURE_DIR, 'en.html'), join(dir, 'en/index.html'));
        cpSync(join(FIXTURE_DIR, 'variants.html'), join(dir, 'en/variants/index.html'));
        cpSync(join(FIXTURE_DIR, 'states.html'), join(dir, 'en/states/index.html'));
        cpSync(join(FIXTURE_DIR, 'ar.html'), join(dir, 'ar/index.html'));
        server = await serveFolder(dir);
        const base = `http://127.0.0.1:${server.address().port}`;
        const stOpt = { ...opt, base, design: '2', widths: [1440], rules: RULES.map((r) => r.id), states: true, illustrations: 'art', wa01: FIXTURE_WA01, selfTest: true, concurrency: 4, locales: ['en', 'ar'], routeFilter: 'fixtures', cssIndex: buildCssIndex(['qa/lint/fixture']), areaFiles: FIXTURE_AREA_FILES };
        const targets = FIXTURES.map((f) => ({ slug: f.slug, locale: f.locale, path: f.path, expect: 200, width: 1440, states: f.shell }));
        console.log(`Self-test: ${targets.length} fixture page(s) from ${dir} on ${base}`);
        const started = Date.now();
        const { results } = await run(stOpt, targets, { log: () => {} });
        const report = summarise(results, FIXTURE_SOURCES, stOpt);
        writeJson(join(dir, 'lint-design.json'), report, stOpt);
        let failures = 0;
        const fail = (row) => {
            failures += 1;
            console.log(`${row}  <- MISMATCH |`);
        };
        console.log('\n| Page | Rule | Expected | Found |');
        console.log('|---|---|---|---|');
        for (const f of FIXTURES) {
            const page = report.pages.find((p) => p.route === f.slug && p.locale === f.locale);
            if (!page || !page.ok) {
                fail(`| ${f.path} | - | page linted | ${page ? page.error : 'missing'}`);
                continue;
            }
            for (const rule of RULES) {
                const patterns = f.expect[rule.id] ?? [];
                const hits = report.violations
                    .filter((v) => v.route === f.slug && v.locale === f.locale && v.rule === rule.id)
                    .flatMap((v) => Array.from({ length: v.count }, () => `${v.area} | ${v.state} | ${v.selector} | ${v.value} | ${v.text}${v.source ? ` | ${v.source}` : ''}`));
                const unmatched = [...hits];
                const missing = patterns.filter((re) => {
                    const at = unmatched.findIndex((h) => re.test(h));
                    if (at === -1) return true;
                    unmatched.splice(at, 1);
                    return false;
                });
                if (hits.length === patterns.length && !missing.length && !unmatched.length) {
                    if (patterns.length) console.log(`| ${f.path} | ${rule.id} ${rule.slug} | ${patterns.length} | ${hits.length} |`);
                } else {
                    failures += 1;
                    for (const re of missing) console.log(`| ${f.path} | ${rule.id} ${rule.slug} | ${re} | not found  <- MISMATCH |`);
                    for (const h of unmatched) console.log(`| ${f.path} | ${rule.id} ${rule.slug} | (nothing) | ${h.slice(0, 160)}  <- UNEXPECTED |`);
                }
                const art = f.art[rule.id] ?? 0;
                const foundArt = page.illustrationExempt[rule.id] ?? 0;
                if (foundArt !== art) fail(`| ${f.path} | ${rule.id} ${rule.slug} inside [data-illustration] (art) | ${art} | ${foundArt}`);
                else if (art) console.log(`| ${f.path} | ${rule.id} ${rule.slug} inside [data-illustration] (art, not counted) | ${art} | ${foundArt} |`);
            }
            if (page.placeholders !== f.placeholders) fail(`| ${f.path} | WhatsApp links showing the placeholder | ${f.placeholders} | ${page.placeholders}`);
            if (page.svgDrawings !== f.svgDrawings) fail(`| ${f.path} | inline SVG drawings (advice) | ${f.svgDrawings} | ${page.svgDrawings}`);
            for (const a of ADVICE) {
                // one pattern per advisory, "area | state | selector | value | text", one to one
                const patterns = f.advice[a.id] ?? [];
                const hits = report.advisories
                    .filter((x) => x.route === f.slug && x.locale === f.locale && x.pattern === a.id)
                    .flatMap((x) => Array.from({ length: x.count }, () => `${x.area} | ${x.state} | ${x.selector} | ${x.value} | ${x.text}`));
                const unmatched = [...hits];
                const missing = patterns.filter((re) => {
                    const at = unmatched.findIndex((h) => re.test(h));
                    if (at === -1) return true;
                    unmatched.splice(at, 1);
                    return false;
                });
                if (!missing.length && !unmatched.length) {
                    if (patterns.length) console.log(`| ${f.path} | advice ${a.id} (not counted) | ${patterns.length} | ${hits.length} |`);
                } else {
                    failures += 1;
                    for (const re of missing) console.log(`| ${f.path} | advice ${a.id} | ${re} | not found  <- MISMATCH |`);
                    for (const h of unmatched) console.log(`| ${f.path} | advice ${a.id} | (nothing) | ${h.slice(0, 160)}  <- UNEXPECTED |`);
                }
            }
            for (const label of f.states) {
                const st = page.states.find((x) => x.state === label);
                if (!st || !st.ok) fail(`| ${f.path} | state ${label} linted | yes | ${st ? st.note ?? 'not ok' : 'missing'}`);
                else console.log(`| ${f.path} | state ${label} | linted | linted |`);
            }
        }
        // Every kind of state has a violation that shows only there (the patterns above name each one's state).
        for (const kind of STATE_KINDS) {
            const n = report.violations.filter((v) => stateKind(v.state) === kind).reduce((sum, v) => sum + v.count, 0);
            if (!n) fail(`| fixtures | a violation only in a state of kind "${kind}" | at least 1 | 0`);
            else console.log(`| fixtures | violations only in a state of kind "${kind}" | at least 1 | ${n} |`);
        }
        const safeWant = FIXTURES.reduce((sum, f) => sum + f.safeArea, 0);
        if (report.summary.safeAreaExempt !== safeWant) fail(`| fixtures | rule 10 safe-area insets exempted | ${safeWant} | ${report.summary.safeAreaExempt}`);
        else console.log(`| fixtures | rule 10 safe-area insets (not counted) | ${safeWant} | ${report.summary.safeAreaExempt} |`);
        const fixture = report.pages.find((p) => p.route === 'fixture' && p.locale === 'en');
        const statesOk = report.pages.reduce((sum, p) => sum + p.states.filter((s) => s.ok).length, 0);
        if (!fixture?.states.some((s) => s.ok && s.state.startsWith('overlay '))) fail('| /en/ | overlay states | at least 1 | 0');
        if (report.summary.approvedSeparators.length !== 1) fail(`| /en/ | approved separator exempted | 1 | ${report.summary.approvedSeparators.length}`);
        if (report.summary.approvedEllipsisExempt !== 1) fail(`| /en/ | approved ellipsis exempted | 1 | ${report.summary.approvedEllipsisExempt}`);

        // --illustrations=strict: the art hits of the main fixture count as violations too.
        const main = FIXTURES[0];
        const strictOpt = { ...stOpt, illustrations: 'strict', locales: ['en'] };
        const strict = summarise((await run(strictOpt, [targets[0]], { log: () => {} })).results, FIXTURE_SOURCES, strictOpt);
        for (const rule of RULES) {
            const want = (main.expect[rule.id] ?? []).length + (main.art[rule.id] ?? 0);
            const got = strict.summary.byRule[rule.id];
            if (got !== want) fail(`| /en/ --illustrations=strict | ${rule.id} ${rule.slug} | ${want} | ${got}`);
        }
        if (Object.values(strict.summary.illustrationExempt).some(Boolean)) fail('| /en/ --illustrations=strict | nothing recorded as art | 0 | some');
        console.log(`| /en/ --illustrations=strict | every rule | expected + art | ${strict.summary.total} |`);

        if (failures || opt.verbose) {
            console.log('\nViolations found:');
            for (const v of report.violations) console.log(`  ${v.locale} ${v.route} rule ${v.rule} ${v.area} ${v.state} ${v.selector}: ${v.value} | ${v.text}${v.source ? ` | set by ${v.source}` : ''}${v.file ? ` | file ${v.file}` : ''}`);
            for (const a of report.advisories) console.log(`  ${a.locale} ${a.route} advice ${a.pattern} ${a.state} ${a.selector}: ${a.value} | ${a.text}`);
        }
        console.log(`\nSelf-test ${failures ? `FAILED (${failures} mismatch(es))` : 'passed: every rule fires exactly as expected'} in ${((Date.now() - started) / 1000).toFixed(1)} s; states linted: ${statesOk}`);
        return failures ? 1 : 0;
    } finally {
        if (server) await new Promise((done) => server.close(done));
        rmSync(dir, { recursive: true, force: true });
    }
}

// ------------------------------------------------------------------------------------------ main

async function main() {
    let opt;
    let targets;
    try {
        opt = parseArgs(process.argv.slice(2));
        if (opt.help) {
            console.log(USAGE);
            return 0;
        }
        if (opt.selfTest) return await selfTest(opt);
        targets = selectTargets(opt);
        opt.wa01 = readWa01();
        opt.cssIndex = buildCssIndex();
    } catch (error) {
        console.error(error instanceof UsageError ? `${error.message}\n${USAGE}` : `Cannot start: ${error.message}`);
        return 2;
    }
    try {
        await preflight(opt);
    } catch (error) {
        console.error(error.message);
        return 2;
    }
    const sources = readSources();
    console.log(`Linting ${targets.length} page(s) of design ${opt.design} from ${opt.base} (${opt.concurrency} at a time), rules ${opt.rules.join(',')}`);
    const { results, seconds } = await run(opt, targets);
    const report = summarise(results, sources, opt);
    const out = resolve(ROOT, opt.out);
    writeJson(`${out}.json`, report, opt);
    writeFileSync(`${out}.md`, renderMarkdown(report, opt));
    printSummary(report, opt, seconds);
    console.log(`Wrote ${relative(process.cwd(), `${out}.json`)} and ${relative(process.cwd(), `${out}.md`)}`);
    return report.summary.total || report.summary.pagesFailed ? 1 : 0;
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    main().then(
        (code) => {
            process.exitCode = code;
        },
        (error) => {
            console.error(error);
            process.exitCode = 2;
        },
    );
}
