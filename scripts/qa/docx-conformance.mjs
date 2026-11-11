#!/usr/bin/env node
/**
 * DOCX conformance of Design 2 (docs/briefs/design-2.1-and-admin-cms.md §5.3).
 *
 * For every page and locale the rendered text of Design 2 is compared with the approved black text of the page's
 * Word file (package/docx/{locale}/*.docx), after normalising whitespace and quotes:
 *   - every approved line is present, complete and in the DOCX order;
 *   - every other string on the page is a registered interface string or marked pending client approval.
 *
 *   node scripts/qa/docx-conformance.mjs                 every page, en ar ru (npm run qa:docx)
 *   node scripts/qa/docx-conformance.mjs --routes=home,s06* --locales=ar
 *   node --test scripts/qa/docx-conformance.test.mjs     the normaliser and matcher self-test (npm run qa:docx:selftest)
 *
 * Options
 *   --base=<url>          site under test, default http://127.0.0.1:8042 (a server of this working tree)
 *   --routes=<list>       page slugs ("*" wildcard): home, p02-practice-areas, p03-resources, p04-case-studies,
 *                         p05-faq, p06-about, p07-contact, p08-legal-disclaimer, l01-privacy-policy,
 *                         l02-terms-and-conditions, s01-debt-recovery-cheque-cases … s11-business-services, 404,
 *                         calculators
 *   --locales=<list>      en, ar, ru (default all)
 *   --width=<px>          viewport width, default 1440 (height 900)
 *   --js=on|off           run the page's JavaScript (default on: what a visitor sees; off: the server HTML)
 *   --concurrency=<n>     parallel pages, default 2
 *   --out=<path>          report path without extension, default storage/qa/docx-conformance (.md and .json)
 *   --strict              exit 1 also on out-of-order lines and unregistered strings
 *
 * Exit 0 when no selected page has a missing, truncated or altered approved line of kind visible, group or control
 * (the §5.3 gate: "A phase is done only with zero missing or truncated lines"); 1 otherwise (and with --strict also
 * on out-of-order lines and unregistered strings) or when a page cannot be captured; 2 on bad arguments or when the
 * site or `php artisan summit:docx-black` cannot be reached. Missing labels and state messages are reported apart.
 *
 * Where the approved lines come from
 *   `php artisan summit:docx-black --json` (app/Console/Commands/DocxBlack.php) reuses the content importer: DocxReader
 *   keeps black runs and drops red designer notes, and the summit:import mappers name the field of every black block.
 *   Each line has a kind from an explicit rule list on that field (printed in the report, never fuzzy):
 *     visible            must be rendered, complete, in the DOCX order (most lines)
 *     group              rendered, in order within its group only (the homepage form lists labels, then options;
 *                        the header's navigation and language labels; the copyright line, owner decision)
 *     control            an interface string given by role (search, filter and toggle buttons): present, any order
 *     label              an accessible name or attribute (aria-label, alt, placeholder, svg title) or text
 *     state              shown only after an action: in the DOM (hidden is fine) or in the page's data
 *     conditional        rendered only when a condition outside the page holds (secure uploads, optional trackers)
 *     seo-title / meta-description   the HTML title and the meta description
 *     wa-draft           the WA01 message in every wa.me link's text parameter
 *   Pages other than the home also expect the shared header and footer lines of the homepage DOCX (every inner-page
 *   DOCX says to reuse them). 00_Site_Map is the link registry, not a page. The calculators page has no DOCX.
 *
 * Capture
 *   Google Chrome (channel 'chrome'), cookies summit_design=2 and summit_qa=1, 1440 x 900, reduced motion, the page's
 *   browser locale and Asia/Dubai. Images and media are not fetched. Every text node of the document is read with its
 *   state: visible, visually hidden (screen-reader only), collapsed (a closed details element) or hidden (display:
 *   none, the hidden attribute, visibility: hidden, opacity 0). Option text counts as visible when its select is.
 *   Attribute text (aria-label, alt, title, placeholder, aria-placeholder, button values, svg titles) is read
 *   separately, and so is the page's data (other attribute values, JSON scripts, templates) for the state messages.
 *
 * Normalisation (both sides, normalise()): NFC; soft hyphens, zero-width characters and bidi controls removed;
 * curly and angle quotes folded to ' and "; the non-breaking hyphen to "-"; Eastern Arabic digits to 0-9 (the
 * Arabic digit style is a render setting, §3.7); the year after "©" masked (it is dynamic, §5.2); every run of
 * white space to one space. Letter case, dashes, ellipses and all other punctuation are compared exactly.
 *
 * Matching
 *   The page text becomes a sequence of words; inline elements join (a link inside a sentence stays in the
 *   sentence), block elements separate. An approved line matches a run of equal words that starts where a text
 *   node starts (or after a sentence end) and ends where one ends (or with its own sentence end), so a short label
 *   such as "English" never matches inside a paragraph (an edge may also fall inside a word where one text node ends:
 *   the link "Privacy Policy" in "… the Privacy Policy."). Scope: page lines must be found in <main>, header lines in
 *   the banner header (or the menu sheets and tab bar), footer lines in the contentinfo footer. Order: the longest chain of matches in increasing page
 *   position (preferring visible matches) decides which lines are in order; a found line outside that chain is
 *   out of order. A line inside the previous line's match (the consent label's "Privacy Policy" link) is embedded.
 *   A line that is not found is truncated (a prefix is shown, then an ellipsis or the end of the element, or CSS
 *   clamps it), altered (most of it is there but it differs), merged (complete but run together with other text
 *   in one element; 4+ words only) or missing.
 *
 * Extra strings: every word not covered by a matched line, grouped per unit of copy (its link, button, label, list
 * item, paragraph, heading or cell; then its block elements; then its text nodes until a piece is recognised), must
 * be approved copy of any DOCX (another page's line reused here), the host name of a DOCX hyperlink (shown beside
 * the link), an imported content or interface string (database/content), a lang string, or proposed copy
 * (lang/{locale}/proposed.php and proposed-d2/), placeholders (:name, with at least three letters of their own) and
 * plural forms included. Proposed copy must sit under data-copy-status="proposed" or a pending block
 * (PENDING_BLOCKS); text inside [data-illustration] (sample documents, §7.1), text without letters, single letters
 * (initials) and the required-field asterisk are exempt.
 */
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, isAbsolute, join, relative, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const DEFAULT_BASE = 'http://127.0.0.1:8042';
const DEFAULT_OUT = join(ROOT, 'storage/qa/docx-conformance');
const BROWSER_LOCALE = { en: 'en-GB', ar: 'ar-AE', ru: 'ru-RU' };
const NAV_TIMEOUT = 90_000;
// Vite's dev client would reload the page whenever another file changes (as in qa/lib/browser.mjs).
const HMR_STUB = `const noop = () => {};
export const createHotContext = () => ({ data: {}, accept: noop, acceptExports: noop, dispose: noop, prune: noop, invalidate: noop, decline: noop, on: noop, off: noop, send: noop });
export const updateStyle = noop; export const removeStyle = noop; export const injectQuery = (url) => url;
export class ErrorOverlay {}`;
const ATTEMPTS = 2;

/**
 * Pending blocks by page: switchable home blocks whose copy is pending (config design.d2.home.blocks[key].pending)
 * and pages that render only while pending copy is shown. Any element marked data-copy-status="proposed" (or
 * data-pending / data-pending-block) also counts, with everything inside it.
 */
export const PENDING_BLOCKS = {
    home: { start: '#start', anatomy: '#anatomy', navigator: '#navigator', team: '#our-team' },
    calculators: { page: 'main' },
};
const MARKED = '[data-copy-status="proposed"], [data-pending], [data-pending-block]';

/** Where each part's lines must be found (the capture's node parts: header, main, footer, other). */
const SCOPES = {
    header: ['header', 'other'],
    'shared-header': ['header', 'other'],
    footer: ['footer'],
    'shared-footer': ['footer'],
    page: ['main'],
};

// ------------------------------------------------------------------------------------------ normalisation

const DROP = /[\u00AD\u200B-\u200F\u202A-\u202E\u2060-\u2064\u2066-\u2069\uFEFF]/gu;
const SINGLE_QUOTES = /[‘’‚‛′‵`´]/gu;
const DOUBLE_QUOTES = /[“”„‟″‶«»]/gu;
const WHITE = /[\s\p{Zs}\u2028\u2029]/gu;

/** Character-level folding (no white-space collapse). Length may change (removed characters). */
export function foldChars(text) {
    return String(text)
        .normalize('NFC')
        .replace(DROP, '')
        .replace(SINGLE_QUOTES, "'")
        .replace(DOUBLE_QUOTES, '"')
        .replace(/\u2011/gu, '-')
        .replace(/[٠-٩]/gu, (d) => String(d.charCodeAt(0) - 0x0660))
        .replace(/[۰-۹]/gu, (d) => String(d.charCodeAt(0) - 0x06f0))
        .replace(WHITE, ' ');
}

/** Same length as its input: the year after "©" becomes YYYY (the copyright year is dynamic). */
export function maskYear(text) {
    return text.replace(/©(\s*)[0-9]{4}/gu, '©$1YYYY');
}

/** The comparison form of a string: folded, year masked, one space between words, trimmed. */
export function normalise(text) {
    return maskYear(foldChars(text)).replace(/ +/gu, ' ').trim();
}

export const toWords = (text) => normalise(text).split(' ').filter(Boolean);
const hasLetter = (text) => /\p{L}/u.test(text);
const SENTENCE_END = /[.!?؟…]["')\]»]?$/u;

// ------------------------------------------------------------------------------------------ page model

/**
 * Builds the word sequence of a captured page. nodes: [{t: text, b: block id, v: visibility, ...}] in document
 * order. Inline neighbours join ("Policy" + "." is one word), a new block always starts a new word.
 * Each word: {w, n0, n1 (first and last node), b, start (begins its node's text), end (ends it)}.
 */
export function buildWords(nodes) {
    const chars = [];
    const owner = [];
    let lastBlock = null;
    nodes.forEach((node, i) => {
        if (lastBlock !== null && node.b !== lastBlock) {
            chars.push('\n');
            owner.push(-1);
        }
        lastBlock = node.b;
        for (const ch of foldChars(node.t)) {
            chars.push(ch);
            owner.push(i);
        }
    });
    const text = maskYear(chars.join(''));
    // maskYear keeps the length, but works on UTF-16 units; chars holds code points: rebuild per code point.
    const masked = [...text];
    const firstChar = new Map();
    const lastChar = new Map();
    masked.forEach((ch, k) => {
        const n = owner[k];
        if (n < 0 || ch === ' ' || ch === '\n') return;
        if (!firstChar.has(n)) firstChar.set(n, k);
        lastChar.set(n, k);
    });
    const words = [];
    let k = 0;
    while (k < masked.length) {
        if (masked[k] === ' ' || masked[k] === '\n') {
            k += 1;
            continue;
        }
        const from = k;
        while (k < masked.length && masked[k] !== ' ' && masked[k] !== '\n') k += 1;
        const to = k - 1;
        const n0 = owner[from];
        const n1 = owner[to];
        // Offsets (UTF-16) inside the word where one text node ends and the next begins: "Policy|." "(|Privacy".
        const cuts = [];
        let offset = 0;
        for (let j = from; j <= to; j += 1) {
            if (j > from && owner[j] !== owner[j - 1]) cuts.push(offset);
            offset += masked[j].length;
        }
        words.push({ w: masked.slice(from, k).join(''), n0, n1, b: nodes[n0].b, L: nodes[n0].L ?? nodes[n0].b, start: firstChar.get(n0) === from, end: lastChar.get(n1) === to, cuts });
    }
    return words;
}

/** Index of word -> positions, for candidate search; `cut` lists the words that span two text nodes. */
export function indexWords(words) {
    const index = new Map();
    const cut = [];
    words.forEach((word, i) => {
        const list = index.get(word.w);
        if (list) list.push(i);
        else index.set(word.w, [i]);
        if (word.cuts?.length) cut.push(i);
    });
    index.cut = cut;
    return index;
}

/**
 * Whether the line's words sit at page position p. A line may start or end inside a page word where one text node
 * ends and the next begins (the consent label's link "Privacy Policy" inside "… the Privacy Policy."): then that
 * edge is an element boundary. Returns null or {startCut, endCut}.
 */
function matchAt(p, lineWords, words) {
    const k = lineWords.length;
    if (p + k > words.length) return null;
    let startCut = false;
    let endCut = false;
    for (let j = 0; j < k; j += 1) {
        const pw = words[p + j];
        const lw = lineWords[j];
        if (pw.w === lw) continue;
        const cuts = pw.cuts ?? [];
        if (k === 1) {
            let found = false;
            for (let i = pw.w.indexOf(lw); i >= 0; i = pw.w.indexOf(lw, i + 1)) {
                const end = i + lw.length;
                if ((i === 0 || cuts.includes(i)) && (end === pw.w.length || cuts.includes(end))) {
                    startCut = i > 0;
                    endCut = end < pw.w.length;
                    found = true;
                    break;
                }
            }
            if (found) continue;
            return null;
        }
        if (j === 0 && pw.w.endsWith(lw) && cuts.includes(pw.w.length - lw.length)) {
            startCut = true;
            continue;
        }
        if (j === k - 1 && pw.w.startsWith(lw) && cuts.includes(lw.length)) {
            endCut = true;
            continue;
        }
        return null;
    }
    return { startCut, endCut };
}

/** Every occurrence of a line (array of words) that respects element or sentence boundaries. */
export function findCandidates(lineWords, words, index, { boundaries = true } = {}) {
    const out = [];
    if (!lineWords.length) return out;
    const k = lineWords.length;
    const exact = index.get(lineWords[0]) ?? [];
    const positions = index.cut?.length ? [...new Set([...exact, ...index.cut])].sort((a, b) => a - b) : exact;
    for (const p of positions) {
        const m = matchAt(p, lineWords, words);
        if (!m) continue;
        if (boundaries) {
            const first = words[p];
            const last = words[p + k - 1];
            const prev = words[p - 1];
            const next = words[p + k];
            const startOk = m.startCut || first.start || !prev || prev.b !== first.b || SENTENCE_END.test(prev.w);
            const endOk = m.endCut || last.end || !next || next.b !== last.b || SENTENCE_END.test(last.w);
            if (!startOk || !endOk) continue;
        }
        out.push({ s: p, e: p + k - 1 });
    }
    return out;
}

/** Fenwick tree over positions for prefix maxima: [score, node id]. */
class MaxTree {
    constructor(size) {
        this.size = size + 2;
        this.score = new Array(this.size).fill(-1);
        this.ref = new Array(this.size).fill(-1);
    }

    update(pos, score, ref) {
        for (let i = pos + 1; i < this.size; i += i & -i) {
            if (score > this.score[i]) {
                this.score[i] = score;
                this.ref[i] = ref;
            }
        }
    }

    /** Best over positions 0..pos (pos may be -1). */
    query(pos) {
        let best = -1;
        let ref = -1;
        for (let i = pos + 1; i > 0; i -= i & -i) {
            if (this.score[i] > best) {
                best = this.score[i];
                ref = this.ref[i];
            }
        }
        return [best, ref];
    }
}

/**
 * The longest chain of matches in increasing page position, one per line, in the lines' order. Visible matches are
 * preferred between chains of the same length. items: [{cands: [{s, e, vis}]}]. Returns item index -> chosen cand.
 */
export function bestChain(items, size) {
    const tree = new MaxTree(size);
    const nodes = [];
    for (let i = 0; i < items.length; i += 1) {
        const added = [];
        for (const cand of items[i].cands) {
            const [prevScore, prevRef] = tree.query(cand.s - 1);
            const score = Math.max(prevScore, 0) + 1000 + (cand.vis ? 1 : 0);
            nodes.push({ item: i, cand, score, prev: prevScore >= 0 ? prevRef : -1 });
            added.push(nodes.length - 1);
        }
        for (const id of added) tree.update(nodes[id].cand.e, nodes[id].score, id);
    }
    let best = -1;
    nodes.forEach((node, id) => {
        if (best < 0 || node.score > nodes[best].score) best = id;
    });
    const chosen = new Map();
    for (let id = best; id >= 0; id = nodes[id].prev) chosen.set(nodes[id].item, nodes[id].cand);
    return chosen;
}

/** Longest prefix (or suffix) of the line found on the page with a start (end) boundary: [length, position]. */
function longestPart(lineWords, words, index, fromEnd) {
    const found = (k) => {
        const part = fromEnd ? lineWords.slice(lineWords.length - k) : lineWords.slice(0, k);
        for (const p of index.get(part[0]) ?? []) {
            if (p + k > words.length) continue;
            let ok = true;
            for (let j = 1; j < k; j += 1) {
                if (words[p + j].w !== part[j]) {
                    ok = false;
                    break;
                }
            }
            if (!ok) continue;
            if (!fromEnd) {
                const prev = words[p - 1];
                if (words[p].start || !prev || prev.b !== words[p].b || SENTENCE_END.test(prev.w)) return p;
            } else {
                const last = words[p + k - 1];
                const next = words[p + k];
                if (last.end || !next || next.b !== last.b || SENTENCE_END.test(last.w)) return p;
            }
        }
        return -1;
    };
    let lo = 0;
    let hi = lineWords.length;
    let at = -1;
    while (lo < hi) {
        const mid = Math.ceil((lo + hi) / 2);
        const p = found(mid);
        if (p >= 0) {
            lo = mid;
            at = p;
        } else hi = mid - 1;
    }
    return [lo, lo > 0 ? at : -1];
}

const ELLIPSIS = /(…|\.\.\.)$/u;

/**
 * Why a line without an exact match is not there: truncated, altered, merged or missing (with the evidence).
 */
export function diagnose(lineWords, words, index) {
    const n = lineWords.length;
    const enough = (k) => k >= Math.max(3, Math.ceil(n * 0.3));
    const lower = lineWords.map((w) => w.toLocaleLowerCase());
    const lowered = words.map((w) => ({ ...w, w: w.w.toLocaleLowerCase() }));
    const cased = findCandidates(lower, lowered, indexWords(lowered));
    if (cased.length) return { status: 'altered', at: cased[0].s, shown: n, of: n, detail: 'letter case differs' };
    const [pre, at] = longestPart(lineWords, words, index, false);
    if (pre > 0 && pre < n) {
        const next = words[at + pre];
        const lastShown = words[at + pre - 1];
        const cut = !next || next.b !== lastShown.b;
        const ellipsis = next && next.b === lastShown.b && ELLIPSIS.test(next.w) && lineWords[pre].startsWith(next.w.replace(ELLIPSIS, ''));
        if (ellipsis || (cut && enough(pre))) {
            return { status: 'truncated', at, shown: pre, of: n, detail: `${pre} of ${n} words shown${ellipsis ? ', then an ellipsis' : ', then the element ends'}` };
        }
        if (enough(pre)) {
            return { status: 'altered', at, shown: pre, of: n, detail: `differs after word ${pre} of ${n}: expected "${lineWords.slice(pre, pre + 4).join(' ')}", page has "${words.slice(at + pre, at + pre + 4).map((w) => w.w).join(' ')}"` };
        }
    }
    const [suf, sat] = longestPart(lineWords, words, index, true);
    if (suf > 0 && suf < n && enough(suf)) {
        return { status: 'altered', at: sat, shown: suf, of: n, detail: `the last ${suf} of ${n} words match; the start differs: expected "${lineWords.slice(0, Math.min(4, n - suf)).join(' ')}"` };
    }
    if (n >= 4) {
        const loose = findCandidates(lineWords, words, index, { boundaries: false });
        if (loose.length) return { status: 'merged', at: loose[0].s, shown: n, of: n, detail: 'complete, but run together with other text in one element' };
    }
    return { status: 'missing', at: -1, shown: pre, of: n, detail: pre > 0 ? `only the first ${pre} of ${n} words appear together` : '' };
}

// ------------------------------------------------------------------------------------------ registry

/** Registered strings per locale: exact normalised strings and placeholder templates, with their category. */
export function buildRegistry(raw) {
    const exact = new Map();
    const templates = [];
    const order = ['approved', 'hosts', 'ui', 'content', 'lang', 'proposed'];
    for (const category of order) {
        for (const item of raw[category] ?? []) {
            const value = String(item);
            const variants = new Set([value, value.replace(/<[^>]+>/gu, '')]);
            if (value.includes('|')) {
                for (const part of value.split('|')) variants.add(part.replace(/^\s*(\{\d+\}|\[\d+\s*,\s*(\d+|\*)\])\s*/u, ''));
            }
            for (const variant of variants) {
                const key = normalise(variant);
                if (!key) continue;
                if (/(^|[^\p{L}\p{N}]):[A-Za-z_][A-Za-z0-9_]*/u.test(key)) {
                    const source = key
                        .split(/(:[A-Za-z_][A-Za-z0-9_]*)/u)
                        .map((piece, i) => (i % 2 ? '(.+?)' : piece.replace(/[.*+?^${}()|[\]\\]/gu, '\\$&')))
                        .join('');
                    // A template needs some words of its own: ":n. :question" would match any numbered line.
                    const literal = key.replace(/(^|[^\p{L}\p{N}]):[A-Za-z_][A-Za-z0-9_]*/gu, '$1');
                    if ((literal.match(/\p{L}/gu) ?? []).length >= 3) templates.push({ re: new RegExp(`^${source}$`, 'u'), category, template: variant });
                } else if (!exact.has(key)) exact.set(key, category);
            }
        }
    }
    return { exact, templates };
}

/** The category of a string ("approved", "ui", "content", "lang", "proposed") or null. */
export function classify(text, registry) {
    const key = normalise(text);
    if (!key) return 'empty';
    if (!hasLetter(key)) return 'no-letters';
    const hit = registry.exact.get(key);
    if (hit) return hit;
    const trimmed = key.replace(/^[\s:;,.·|/()–—-]+|[\s:;,·|/()–—-]+$/gu, '');
    if (trimmed !== key && registry.exact.has(trimmed)) return registry.exact.get(trimmed);
    for (const t of registry.templates) if (t.re.test(key)) return t.category;
    const parts = key.split(/\s*[,،]\s*/u).filter(Boolean);
    if (parts.length > 1) {
        const cats = parts.map((p) => registry.exact.get(p) ?? registry.exact.get(p.replace(/^(and|и|و)\s+/u, '')) ?? null);
        if (cats.every(Boolean)) return cats.includes('proposed') ? 'proposed' : cats[0];
    }
    return null;
}

// ------------------------------------------------------------------------------------------ comparison

/**
 * Compares one page's DOCX lines with its capture. Returns lines with a status and the extra strings.
 * page: an entry of summit:docx-black (slug, lines[]); capture: pageCapture()'s result; registry: buildRegistry().
 */
export function comparePage(page, capture, registry) {
    const words = buildWords(capture.nodes);
    const index = indexWords(words);
    const nodeVis = (s, e) => {
        const states = new Set();
        for (let i = s; i <= e; i += 1) {
            for (let n = words[i].n0; n <= words[i].n1; n += 1) states.add(capture.nodes[n].v);
        }
        if (states.size === 1 && states.has('v')) return 'v';
        for (const v of ['hidden', 'collapsed', 'sr']) if (states.has(v)) return v;
        return [...states].find((v) => v !== 'v') ?? 'v';
    };
    const clampedIn = (s, e) => {
        for (let i = s; i <= e; i += 1) if (capture.nodes[words[i].n0].clamp) return true;
        return false;
    };
    const selectorAt = (pos) => (pos >= 0 && words[pos] ? capture.els[capture.nodes[words[pos].n0].p] : null);

    const lines = page.lines.map((line, i) => ({ ...line, n: i, words: toWords(line.text), status: null }));
    const partAt = (pos) => capture.nodes[words[pos].n0].part;
    for (const line of lines) {
        if (['visible', 'group', 'control', 'label', 'state', 'conditional'].includes(line.kind)) {
            line.cands = findCandidates(line.words, words, index).map((c) => ({ ...c, vis: nodeVis(c.s, c.e) === 'v' }));
        } else line.cands = [];
        if (line.kind === 'visible' || line.kind === 'group') {
            // Scope: header lines in the banner (or the menu sheets and tab bar), footer lines in the contentinfo
            // footer, page lines in <main>.
            const scope = SCOPES[line.part] ?? SCOPES.page;
            const inside = line.cands.filter((c) => scope.includes(partAt(c.s)) && scope.includes(partAt(c.e)));
            if (inside.length < line.cands.length) line.outside = line.cands.filter((c) => !inside.includes(c)).map((c) => `${partAt(c.s)}: ${selectorAt(c.s)}`);
            line.cands = inside;
        }
    }

    // 1. visible lines: one chain over the page.
    const covered = new Uint8Array(words.length);
    const cover = (c) => {
        for (let i = c.s; i <= c.e; i += 1) covered[i] = 1;
    };
    const used = new Set();
    const strict = lines.filter((l) => l.kind === 'visible');
    const chain = bestChain(strict, words.length);
    strict.forEach((line, i) => {
        const cand = chain.get(i);
        if (cand) {
            line.match = cand;
            line.status = 'ok';
            used.add(cand.s);
            cover(cand);
        }
    });
    // 2. groups: a chain within each group, never re-using a word of the visible chain.
    const groups = new Map();
    for (const line of lines.filter((l) => l.kind === 'group')) {
        if (!groups.has(line.group)) groups.set(line.group, []);
        groups.get(line.group).push(line);
    }
    for (const members of groups.values()) {
        for (const line of members) line.cands = line.cands.filter((c) => !covered[c.s]);
        const groupChain = bestChain(members, words.length);
        members.forEach((line, i) => {
            const cand = groupChain.get(i);
            if (cand) {
                line.match = cand;
                line.status = 'ok';
                used.add(cand.s);
                cover(cand);
            }
        });
    }
    // 3. lines found but not in their chain: embedded, a repeat short of occurrences, or out of order.
    const ordered = lines.filter((l) => l.kind === 'visible' || l.kind === 'group');
    ordered.forEach((line) => {
        if (line.status) return;
        if (!line.cands.length) return;
        const peers = ordered.filter((l) => (l.kind === line.kind && l.group === line.group) && l.match);
        const before = [...peers].reverse().find((l) => l.n < line.n);
        const after = peers.find((l) => l.n > line.n);
        const inside = line.cands.find((c) => [before, after].some((l) => l && l.match.s <= c.s && c.e <= l.match.e));
        if (inside) {
            line.match = inside;
            line.status = 'ok';
            line.embedded = true;
            return;
        }
        const free = line.cands.filter((c) => !used.has(c.s));
        if (!free.length) {
            line.status = 'missing';
            line.detail = `the page has ${line.cands.length} occurrence(s), all used by earlier identical lines`;
            return;
        }
        line.status = 'out-of-order';
        line.match = free.find((c) => c.vis) ?? free[0];
        line.expectedAfter = before ? { text: before.text, selector: selectorAt(before.match.s) } : null;
        cover(line.match);
    });
    // 4. not found: truncated, altered, merged or missing.
    for (const line of ordered) {
        if (line.status) continue;
        if (line.outside?.length) {
            line.status = 'missing';
            line.detail = `found only outside its part (${line.part}): ${line.outside.slice(0, 2).join('; ')}`;
            continue;
        }
        const d = diagnose(line.words, words, index);
        line.status = d.status;
        line.detail = d.detail;
        if (d.at >= 0) {
            line.foundAt = selectorAt(d.at);
            line.pageText = words.slice(d.at, d.at + Math.min(d.of + 3, 40)).map((w) => w.w).join(' ');
        }
        const peers = ordered.filter((l) => l.match && l.n < line.n && l.kind === line.kind && l.group === line.group);
        const before = peers[peers.length - 1];
        if (before) line.expectedAfter = { text: before.text, selector: selectorAt(before.match.s) };
        if (d.status === 'merged' || d.status === 'truncated' || d.status === 'altered') {
            if (d.at >= 0) for (let i = d.at; i < d.at + d.shown && i < words.length; i += 1) covered[i] = 1;
        }
    }
    // 5. visible matches clamped by CSS are truncated too; hidden-only matches are noted.
    for (const line of ordered) {
        if (line.status !== 'ok' && line.status !== 'out-of-order') continue;
        if (clampedIn(line.match.s, line.match.e)) {
            line.status = 'truncated';
            line.detail = 'clamped by CSS (line-clamp or text-overflow)';
        }
        const v = nodeVis(line.match.s, line.match.e);
        if (v !== 'v') line.hidden = v;
        line.selector = selectorAt(line.match.s);
    }

    // controls: present anywhere (any part, visible or hidden); order not checked.
    const repeats = new Map();
    for (const line of lines) if (line.kind === 'control') repeats.set(line.text, (repeats.get(line.text) ?? 0) + 1);
    for (const line of lines.filter((l) => l.kind === 'control')) {
        if (line.cands.length) {
            const c = line.cands.find((x) => x.vis) ?? line.cands[0];
            line.status = 'ok';
            line.selector = selectorAt(c.s);
            const v = nodeVis(c.s, c.e);
            if (v !== 'v') line.hidden = v;
            if (repeats.get(line.text) > line.cands.length && !lines.some((l) => l.kind === 'control' && l.text === line.text && l.note)) line.note = `the DOCX has it ${repeats.get(line.text)} times, the page ${line.cands.length}`;
            for (const x of line.cands) cover(x);
        } else {
            const d = diagnose(line.words, words, index);
            line.status = d.status === 'merged' ? 'ok' : d.status;
            line.detail = d.detail;
            if (d.at >= 0) line.foundAt = selectorAt(d.at);
        }
    }

    // labels, states, conditional lines
    const attrIndex = new Map();
    for (const a of capture.attrs) {
        const key = normalise(a.value);
        if (!attrIndex.has(key)) attrIndex.set(key, a);
    }
    const data = capture.data.map((d) => normalise(d));
    for (const line of lines) {
        const key = normalise(line.text);
        if (line.kind === 'label') {
            const attr = attrIndex.get(key);
            if (attr) {
                line.status = 'ok';
                line.via = `${attr.name} on ${capture.els[attr.el]}`;
            } else if (line.cands.length) {
                const c = line.cands.find((x) => x.vis) ?? line.cands[0];
                line.status = 'ok';
                line.via = `text${nodeVis(c.s, c.e) === 'v' ? '' : ` (${nodeVis(c.s, c.e)})`} in ${selectorAt(c.s)}`;
                cover(c);
            } else line.status = 'missing';
        } else if (line.kind === 'state' || line.kind === 'conditional') {
            const c = line.cands[0];
            if (c) {
                line.status = 'ok';
                line.via = `in the DOM (${nodeVis(c.s, c.e) === 'v' ? 'visible' : nodeVis(c.s, c.e)}) ${selectorAt(c.s)}`;
                for (const x of line.cands) cover(x);
            } else if (attrIndex.has(key) || data.some((d) => d === key || d.includes(key))) {
                line.status = 'ok';
                line.via = 'in the page data (attributes, JSON or templates)';
            } else line.status = line.kind === 'conditional' ? 'absent' : 'missing';
        } else if (line.kind === 'seo-title') {
            line.status = normalise(capture.title) === key ? 'ok' : 'differs';
            line.actual = capture.title;
        } else if (line.kind === 'meta-description') {
            line.status = normalise(capture.description ?? '') === key ? 'ok' : 'differs';
            line.actual = capture.description;
        } else if (line.kind === 'wa-draft') {
            const links = capture.wa;
            const wrong = links.filter((l) => normalise(l.text ?? '') !== key);
            line.status = !links.length ? 'no-link' : wrong.length ? 'differs' : 'ok';
            line.detail = `${links.length} WhatsApp link(s)${wrong.length ? `, ${wrong.length} with another draft: ${[...new Set(wrong.map((l) => l.text))].slice(0, 2).join(' / ')}` : ''}`;
        }
    }

    // extra strings
    const extras = [];
    const runs = [];
    let run = null;
    words.forEach((word, i) => {
        const node = capture.nodes[word.n0];
        const exempt = covered[i] || node.ill;
        if (exempt) {
            run = null;
            return;
        }
        if (!run || run.L !== word.L || run.last !== i - 1) {
            run = { L: word.L, from: i, last: i, words: [] };
            runs.push(run);
        }
        run.words.push(i);
        run.last = i;
    });
    // Paragraph texts (every word of a paragraph-level element), for runs split by an inline-block (a <bdi> code).
    const paragraphs = new Map();
    words.forEach((word, i) => {
        if (!paragraphs.has(word.L)) paragraphs.set(word.L, []);
        paragraphs.get(word.L).push(i);
    });
    for (const r of runs) {
        const text = r.words.map((i) => words[i].w).join(' ');
        if (!hasLetter(text)) continue;
        // A single letter is not copy (the initials of a pending portrait, a drop cap).
        if (/^\p{L}$/u.test(text)) continue;
        const pieces = [];
        const whole = classify(text, registry);
        const paragraph = whole ? null : classify(paragraphs.get(r.L).map((i) => words[i].w).join(' '), registry);
        if (whole) pieces.push({ text, category: whole, idx: r.words });
        else if (paragraph && paragraph !== 'no-letters' && paragraph !== 'empty') pieces.push({ text, category: paragraph, idx: r.words });
        else {
            // Smaller and smaller units: the run's block elements (a card's title and its summary), then its text
            // nodes (a label and its value).
            const split = (idx, key) => {
                const groups = new Map();
                for (const i of idx) {
                    const k = key(words[i]);
                    if (!groups.has(k)) groups.set(k, []);
                    groups.get(k).push(i);
                }
                return [...groups.values()];
            };
            const add = (idx, depth) => {
                const t = idx.map((i) => words[i].w).join(' ');
                if (!hasLetter(t) || /^\p{L}$/u.test(t)) return;
                const category = classify(t, registry);
                if (category || depth === 2) {
                    pieces.push({ text: t, category, idx });
                    return;
                }
                const parts = split(idx, depth === 0 ? (w) => w.b : (w) => w.n0);
                if (parts.length < 2) add(idx, depth + 1);
                else for (const part of parts) add(part, depth + 1);
            };
            const blocks = split(r.words, (w) => w.b);
            if (blocks.length < 2) add(r.words, 1);
            else for (const part of blocks) add(part, 1);
        }
        for (const piece of pieces) {
            if (piece.category === 'no-letters' || piece.category === 'empty') continue;
            const nodes = [...new Set(piece.idx.map((i) => words[i].n0))].map((n) => capture.nodes[n]);
            const marked = nodes.some((nd) => nd.prop || nd.pend);
            const vis = nodes.every((nd) => nd.v === 'v') ? 'v' : (nodes.find((nd) => nd.v !== 'v')?.v ?? 'v');
            const selector = capture.els[nodes[0].p];
            const part = nodes[0].part;
            let verdict;
            if (piece.category === null) verdict = marked ? 'pending (marked, not in a lang file)' : 'unregistered';
            else if (piece.category === 'proposed') verdict = marked ? 'proposed (marked)' : 'proposed-unmarked';
            else verdict = piece.category;
            extras.push({ text: piece.text, category: piece.category, verdict, selector, vis, part });
        }
    }

    const count = (fn) => lines.filter(fn).length;
    const summary = {
        lines: lines.length,
        checked: count((l) => l.kind === 'visible' || l.kind === 'group'),
        ok: count((l) => (l.kind === 'visible' || l.kind === 'group') && l.status === 'ok'),
        missing: count((l) => (l.kind === 'visible' || l.kind === 'group' || l.kind === 'control') && l.status === 'missing'),
        labelsMissing: count((l) => l.kind === 'label' && l.status === 'missing'),
        statesMissing: count((l) => l.kind === 'state' && l.status === 'missing'),
        truncated: count((l) => l.status === 'truncated'),
        altered: count((l) => l.status === 'altered'),
        merged: count((l) => l.status === 'merged'),
        outOfOrder: count((l) => l.status === 'out-of-order'),
        hidden: count((l) => (l.kind === 'visible' || l.kind === 'group') && l.hidden),
        seo: count((l) => (l.kind === 'seo-title' || l.kind === 'meta-description') && l.status !== 'ok'),
        whatsapp: count((l) => l.kind === 'wa-draft' && l.status !== 'ok'),
        unregistered: extras.filter((x) => x.verdict === 'unregistered').length,
        proposedUnmarked: extras.filter((x) => x.verdict === 'proposed-unmarked').length,
    };
    const extraCounts = extras.reduce((t, x) => ({ ...t, [x.verdict]: (t[x.verdict] ?? 0) + 1 }), {});
    return {
        lines: lines.map(({ words: _w, cands: _c, match: _m, n: _n, ...rest }) => rest),
        extras,
        extraCounts,
        summary,
    };
}

// ------------------------------------------------------------------------------------------ browser

/** Runs in the page: every text node with its block, visibility and markers; attribute text; title, meta, wa.me. */
function pageCapture({ pending, marked }) {
    const els = [];
    const elIds = new Map();
    const selector = (el) => {
        const parts = [];
        for (let e = el; e && e.nodeType === 1; e = e.parentElement) {
            if (e.id && /^[A-Za-z][\w-]*$/.test(e.id)) {
                parts.unshift(`#${e.id}`);
                break;
            }
            let s = e.localName;
            const cls = [...e.classList].filter((c) => !/^(is-|x-|js-)/.test(c)).slice(0, 2);
            if (cls.length) s += `.${cls.map((c) => CSS.escape(c)).join('.')}`;
            const parent = e.parentElement;
            if (parent && e.localName !== 'body' && e.localName !== 'html') {
                const same = [...parent.children].filter((c) => c.localName === e.localName);
                if (same.length > 1) s += `:nth-of-type(${same.indexOf(e) + 1})`;
            }
            parts.unshift(s);
            if (e.localName === 'body' || parts.length >= 6) break;
        }
        return parts.join(' > ');
    };
    const elId = (el) => {
        if (!elIds.has(el)) {
            elIds.set(el, els.length);
            els.push(selector(el));
        }
        return elIds.get(el);
    };
    const styleCache = new Map();
    const style = (el) => {
        if (!styleCache.has(el)) styleCache.set(el, getComputedStyle(el));
        return styleCache.get(el);
    };
    const blockOf = (el) => {
        for (let e = el; e; e = e.parentElement) {
            const d = style(e).display;
            if (d !== 'inline' && d !== 'contents') return e;
        }
        return document.body;
    };
    // The unit of copy a text belongs to: its link, button, label, list item, paragraph, heading or cell, so a
    // <bdi> code inside a link (a flex item, so computed display: block) stays part of the link's text.
    const UNIT = 'a, button, label, summary, legend, p, h1, h2, h3, h4, h5, h6, li, dt, dd, td, th, figcaption, blockquote, option';
    const paraOf = (el) => el.closest(UNIT) ?? blockOf(el);
    const visCache = new Map();
    const visibility = (el0) => {
        const el = el0.closest('option, optgroup') ? (el0.closest('select') ?? el0) : el0;
        if (visCache.has(el)) return visCache.get(el);
        let v = 'v';
        const shown = el.checkVisibility({ opacityProperty: true, visibilityProperty: true, checkOpacity: true, checkVisibilityCSS: true });
        if (!shown) {
            v = 'hidden';
            for (let e = el; e; e = e.parentElement) {
                if (e.localName === 'details' && !e.open && !el.closest('summary')) {
                    v = 'collapsed';
                    break;
                }
            }
        } else {
            for (let e = el, depth = 0; e && depth < 4; e = e.parentElement, depth += 1) {
                const s = style(e);
                const r = e.getBoundingClientRect();
                if ((r.width <= 1 && r.height <= 1) || s.clip === 'rect(0px, 0px, 0px, 0px)' || s.clipPath === 'inset(50%)') {
                    v = 'sr';
                    break;
                }
                if (s.display !== 'inline' && s.display !== 'contents') break;
            }
        }
        visCache.set(el, v);
        return v;
    };
    const clampCache = new Map();
    const clamped = (el) => {
        const block = blockOf(el);
        for (let e = el; e; e = e.parentElement) {
            if (clampCache.has(e)) return clampCache.get(e);
            const s = style(e);
            const lc = s.webkitLineClamp ?? s.getPropertyValue('-webkit-line-clamp');
            let c = false;
            if (lc && lc !== 'none' && e.scrollHeight > e.clientHeight + 1) c = true;
            if (s.textOverflow === 'ellipsis' && s.overflowX !== 'visible' && e.scrollWidth > e.clientWidth + 1) c = true;
            clampCache.set(e, c);
            if (c) return true;
            if (e === block) break;
        }
        return false;
    };
    const pendingSel = pending.length ? pending.join(', ') : null;
    const header = [...document.querySelectorAll('header')].find((h) => !h.parentElement.closest('article, aside, main, nav, section'));
    const footer = [...document.querySelectorAll('footer')].find((f) => !f.parentElement.closest('article, aside, main, nav, section'));
    const main = document.querySelector('main');
    const partOf = (el) => (header?.contains(el) ? 'header' : main?.contains(el) ? 'main' : footer?.contains(el) ? 'footer' : 'other');

    const nodes = [];
    const SKIP = new Set(['script', 'style', 'noscript', 'template', 'textarea']);
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, {
        acceptNode(node) {
            if (node.nodeType === 1) {
                const name = node.localName;
                if (SKIP.has(name) || ((name === 'title' || name === 'desc') && node.closest('svg'))) return NodeFilter.FILTER_REJECT;
                return name === 'br' ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
            }
            return NodeFilter.FILTER_ACCEPT;
        },
    });
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        if (node.nodeType === 1) {
            // <br>: a line break between words ("Guidance.<br>Practical").
            const p = node.parentElement;
            nodes.push({ t: '\n', b: elId(blockOf(p)), p: elId(p), v: 'v' });
            continue;
        }
        const p = node.parentElement;
        const text = node.nodeValue;
        // The required-field asterisk is a form convention, not copy: "Name" + "*" stays "Name".
        if (/^\s*[*\u2217]\s*$/u.test(text)) continue;
        if (!/\S/.test(text)) {
            nodes.push({ t: text, b: elId(blockOf(p)), p: elId(p), v: 'v' });
            continue;
        }
        const v = visibility(p);
        nodes.push({
            t: text,
            b: elId(blockOf(p)),
            L: elId(paraOf(p)),
            p: elId(p),
            v,
            clamp: v === 'v' ? clamped(p) : false,
            ill: Boolean(p.closest('[data-illustration]')),
            prop: Boolean(p.closest(marked)),
            pend: Boolean(pendingSel && p.closest(pendingSel)),
            part: partOf(p),
        });
    }

    const attrs = [];
    const ATTRS = ['aria-label', 'alt', 'title', 'placeholder', 'aria-placeholder', 'aria-description', 'aria-roledescription'];
    for (const el of document.querySelectorAll('body *')) {
        for (const name of ATTRS) {
            const value = el.getAttribute(name);
            if (value && /\S/.test(value)) attrs.push({ name, value, el: elId(el) });
        }
        if (el.localName === 'input' && /^(submit|button|reset)$/i.test(el.type) && el.value) attrs.push({ name: 'value', value: el.value, el: elId(el) });
        if (el.localName === 'title' && el.closest('svg') && el.textContent.trim()) attrs.push({ name: 'svg title', value: el.textContent, el: elId(el.closest('svg')) });
    }

    const data = [];
    const leaves = (value) => {
        if (typeof value === 'string') data.push(value);
        else if (Array.isArray(value)) value.forEach(leaves);
        else if (value && typeof value === 'object') Object.values(value).forEach(leaves);
    };
    const decode = (s) => s.replace(/\\u([0-9a-fA-F]{4})/g, (_, h) => String.fromCharCode(parseInt(h, 16))).replace(/\\\//g, '/');
    for (const el of document.querySelectorAll('*')) {
        for (const a of el.attributes) {
            if (['class', 'style', 'id', 'href', 'src', 'srcset', 'd', 'viewBox', 'points', 'transform'].includes(a.name)) continue;
            const value = a.value.trim();
            if (!/\p{L}/u.test(value) || value.length < 4) continue;
            if (/^[{[]/.test(value)) {
                try {
                    leaves(JSON.parse(value));
                    continue;
                } catch {
                    // not JSON
                }
            }
            data.push(decode(value));
        }
    }
    for (const s of document.querySelectorAll('script[type="application/json"], script[type="application/ld+json"]')) {
        try {
            leaves(JSON.parse(s.textContent));
        } catch {
            data.push(decode(s.textContent));
        }
    }
    for (const t of document.querySelectorAll('template')) data.push(t.content.textContent);

    const wa = [];
    for (const a of document.querySelectorAll('a[href]')) {
        let url;
        try {
            url = new URL(a.href);
        } catch {
            continue;
        }
        if (url.hostname === 'wa.me' || url.hostname === 'api.whatsapp.com') wa.push({ href: a.href, text: url.searchParams.get('text'), el: elId(a) });
    }
    const html = document.documentElement;
    return {
        nodes,
        els,
        attrs,
        data,
        wa,
        title: document.title,
        description: document.querySelector('meta[name="description"]')?.getAttribute('content') ?? null,
        lang: html.getAttribute('lang'),
        dir: html.getAttribute('dir'),
        design: html.getAttribute('data-design'),
        h1: document.querySelectorAll('h1').length,
    };
}

async function captureOnce(browser, opt, target) {
    const origin = new URL(opt.base);
    const context = await browser.newContext({
        viewport: { width: opt.width, height: 900 },
        deviceScaleFactor: 1,
        javaScriptEnabled: opt.js,
        reducedMotion: 'reduce',
        colorScheme: 'light',
        locale: BROWSER_LOCALE[target.locale] ?? 'en-GB',
        timezoneId: 'Asia/Dubai',
        serviceWorkers: 'block',
    });
    try {
        await context.addCookies([
            { name: 'summit_design', value: '2', url: origin.origin },
            { name: 'summit_qa', value: '1', url: origin.origin },
        ]);
        await context.route('**/*', (route) => {
            const request = route.request();
            if (/\/@vite\/client(?:\?.*)?$/u.test(request.url())) return route.fulfill({ status: 200, contentType: 'text/javascript', body: HMR_STUB });
            let host = '';
            try {
                host = new URL(request.url()).hostname;
            } catch {
                // data: and blob: URLs
            }
            const local = host === origin.hostname || host === '127.0.0.1' || host === 'localhost';
            return !local || ['image', 'media'].includes(request.resourceType()) ? route.abort() : route.continue();
        });
        const page = await context.newPage();
        const response = await page.goto(origin.origin + target.url, { waitUntil: 'load', timeout: NAV_TIMEOUT });
        if (!response) throw new Error('no response');
        if (opt.js) await page.waitForTimeout(700);
        const pending = Object.entries(PENDING_BLOCKS[target.slug] ?? {})
            .filter(([key]) => target.pendingKeys === null || target.pendingKeys.includes(key))
            .map(([, css]) => css);
        const data = await page.evaluate(pageCapture, { pending, marked: MARKED });
        return { status: response.status(), ...data };
    } finally {
        await context.close();
    }
}

async function capture(browser, opt, target) {
    let last = null;
    for (let attempt = 1; attempt <= ATTEMPTS; attempt += 1) {
        try {
            const result = await captureOnce(browser, opt, target);
            if (result.status < 500 || attempt === ATTEMPTS) return result;
            last = `HTTP ${result.status}`;
        } catch (error) {
            last = String(error?.message ?? error).split('\n')[0];
        }
    }
    return { error: last };
}

async function pool(items, size, task) {
    const results = new Array(items.length);
    let next = 0;
    const worker = async () => {
        while (next < items.length) {
            const index = next++;
            results[index] = await task(items[index], index);
        }
    };
    await Promise.all(Array.from({ length: Math.max(1, Math.min(size, items.length)) }, worker));
    return results;
}

// ------------------------------------------------------------------------------------------ report

const clip = (text, n = 110) => {
    const s = String(text ?? '').replace(/\s+/gu, ' ');
    return s.length > n ? `${s.slice(0, n - 1)}[…]` : s;
};
const code = (s) => `\`${String(s ?? '').replaceAll('`', "'")}\``;
const q = (s, n) => `"${clip(s, n)}"`;

function renderMarkdown(result) {
    const out = [];
    const { pages, opt, rules } = result;
    out.push('# DOCX conformance of Design 2', '');
    out.push(`Generated by \`scripts/qa/docx-conformance.mjs\` (brief 2.1 §5.3) against ${opt.base}, ${opt.width} px, JavaScript ${opt.js ? 'on' : 'off'}, ${result.generated}. Approved lines from \`php artisan summit:docx-black --json\`.`, '');
    out.push(`Gate (zero missing, truncated or altered approved lines): **${result.gate ? 'pass' : 'fail'}**.`, '');
    const locales = [...new Set(pages.map((p) => p.locale))];
    const slugs = [...new Set(pages.map((p) => p.slug))];
    out.push('## Summary', '');
    out.push('Each cell: missing / truncated / altered / out of order / unregistered extra strings, then (in brackets, when not zero) missing labels or state messages and proposed strings without a pending marker. "clean" = all zero. Lines found only in hidden or collapsed elements are listed per page.', '');
    out.push(`| Page | ${locales.join(' | ')} |`, `|---|${locales.map(() => '---').join('|')}|`);
    for (const slug of slugs) {
        const cells = locales.map((locale) => {
            const p = pages.find((x) => x.slug === slug && x.locale === locale);
            if (!p) return '';
            if (p.error) return `error: ${clip(p.error, 40)}`;
            const s = p.summary;
            const nums = [s.missing, s.truncated, s.altered, s.outOfOrder, s.unregistered];
            const extra = [s.labelsMissing ? `labels ${s.labelsMissing}` : '', s.statesMissing ? `states ${s.statesMissing}` : '', s.proposedUnmarked ? `unmarked ${s.proposedUnmarked}` : ''].filter(Boolean).join(', ');
            return `${nums.every((n) => n === 0) ? 'clean' : nums.join(' / ')}${extra ? ` (${extra})` : ''}`;
        });
        out.push(`| ${slug} | ${cells.join(' | ')} |`);
    }
    out.push('');
    const totals = pages.reduce((t, p) => {
        if (p.summary) for (const [k, v] of Object.entries(p.summary)) t[k] = (t[k] ?? 0) + v;
        return t;
    }, {});
    out.push(`Totals: ${['checked', 'ok', 'missing', 'truncated', 'altered', 'merged', 'outOfOrder', 'hidden', 'labelsMissing', 'statesMissing', 'unregistered', 'proposedUnmarked', 'seo', 'whatsapp'].map((k) => `${k} ${totals[k] ?? 0}`).join(', ')}.`, '');

    for (const p of pages) {
        out.push(`## ${p.slug} (${p.locale}) ${p.url}`, '');
        if (p.error) {
            out.push(`Capture failed: ${p.error}`, '');
            continue;
        }
        out.push(`DOCX ${p.file ?? '(none: shared header and footer only)'}; HTTP ${p.status}; ${p.summary.checked} ordered lines, ${p.summary.ok} in order.`, '');
        const lines = p.lines;
        const section = (title, items, fmt) => {
            if (!items.length) return;
            out.push(`### ${title} (${items.length})`, '');
            for (const item of items) out.push(`- ${fmt(item)}`);
            out.push('');
        };
        const where = (l) => `${l.file ? `${l.file.replace(/\.docx$/, '')}#${l.i}` : ''} ${code(l.field ?? '-')}`;
        const after = (l) => (l.expectedAfter ? `; expected after ${q(l.expectedAfter.text, 60)} (${code(l.expectedAfter.selector)})` : '');
        section('Missing', lines.filter((l) => l.status === 'missing' && ['visible', 'group', 'control'].includes(l.kind)), (l) => `${q(l.text)} [${l.kind}] ${where(l)}${l.detail ? `; ${l.detail}` : ''}${after(l)}`);
        section('Truncated', lines.filter((l) => l.status === 'truncated'), (l) => `${q(l.text)} ${where(l)}; ${l.detail}; at ${code(l.selector ?? l.foundAt)}`);
        section('Altered', lines.filter((l) => l.status === 'altered'), (l) => `${q(l.text)} ${where(l)}; ${l.detail}; at ${code(l.foundAt)}${l.pageText ? `; page: ${q(l.pageText)}` : ''}`);
        section('Out of order', lines.filter((l) => l.status === 'out-of-order'), (l) => `${q(l.text)} ${where(l)}; found at ${code(l.selector)}${after(l)}`);
        section('Merged with other text (present, not counted as missing)', lines.filter((l) => l.status === 'merged'), (l) => `${q(l.text)} ${where(l)}; at ${code(l.foundAt)}; page: ${q(l.pageText)}`);
        section('Labels and state messages not found (accessible names, alt text, messages shown after an action)', lines.filter((l) => l.status === 'missing' && (l.kind === 'label' || l.kind === 'state')), (l) => `${q(l.text)} [${l.kind}] ${where(l)}`);
        section('Controls: count notes', lines.filter((l) => l.kind === 'control' && l.note), (l) => `${q(l.text, 80)}: ${l.note}`);
        const hiddenLines = lines.filter((l) => l.hidden && l.status === 'ok');
        if (hiddenLines.length) {
            const why = hiddenLines.reduce((t, l) => ({ ...t, [l.hidden]: (t[l.hidden] ?? 0) + 1 }), {});
            out.push(`### Present only in hidden, collapsed or visually hidden elements (${hiddenLines.length}: ${Object.entries(why).map(([k, v]) => `${k} ${v}`).join(', ')})`, '');
            for (const l of hiddenLines.slice(0, 12)) out.push(`- ${q(l.text, 80)} (${l.hidden}) at ${code(l.selector)}`);
            if (hiddenLines.length > 12) out.push(`- … ${hiddenLines.length - 12} more in the JSON report`);
            out.push('');
        }
        section('SEO title and meta description', lines.filter((l) => (l.kind === 'seo-title' || l.kind === 'meta-description') && l.status !== 'ok'), (l) => `${l.kind}: expected ${q(l.text)}, page has ${q(l.actual ?? '(none)')}`);
        section('WhatsApp draft', lines.filter((l) => l.kind === 'wa-draft' && l.status !== 'ok'), (l) => `${l.status}: ${l.detail}`);
        section('Conditional lines absent (allowed)', lines.filter((l) => l.kind === 'conditional' && l.status === 'absent' && l.rule !== 'cookie-ui'), (l) => `${q(l.text, 80)} [${l.rule}]`);
        const extras = p.extras;
        const group = (items) => {
            const map = new Map();
            for (const x of items) {
                if (!map.has(x.text)) map.set(x.text, { ...x, n: 0, selectors: [], parts: new Set() });
                const g = map.get(x.text);
                g.n += 1;
                g.parts.add(`${x.part}${x.vis !== 'v' ? ` ${x.vis}` : ''}`);
                if (g.selectors.length < 2) g.selectors.push(x.selector);
            }
            return [...map.values()];
        };
        const fmtExtra = (x) => `${q(x.text)}${x.n > 1 ? ` x${x.n}` : ''} (${[...x.parts].join(', ')}) at ${x.selectors.map(code).join(', ')}${x.n > 2 ? ' …' : ''}`;
        section('Unregistered extra strings', group(extras.filter((x) => x.verdict === 'unregistered')), fmtExtra);
        section('Proposed strings without a pending marker', group(extras.filter((x) => x.verdict === 'proposed-unmarked')), fmtExtra);
        out.push(`Extra strings by verdict: ${Object.entries(p.extraCounts ?? {}).map(([k, v]) => `${k} ${v}`).join(', ') || 'none'}.`, '');
    }

    out.push('## Line kinds (the documented list)', '');
    out.push('Every black DOCX line is `visible` unless one of these rules (first match on the importer\'s field path) names it. Source: `App\\Console\\Commands\\DocxBlack::RULES`.', '');
    out.push('| Rule | Kind | Field pattern | Why |', '|---|---|---|---|');
    for (const r of rules) out.push(`| ${r.id} | ${r.kind}${r.group ? ` (${r.group})` : ''} | ${code(r.field)} | ${r.why.replaceAll('|', '/')} |`);
    out.push('');
    for (const e of result.excluded) out.push(`Not a page: ${code(e.file)}: ${e.why}`);
    out.push('', `Pending blocks: ${Object.entries(PENDING_BLOCKS).map(([page, blocks]) => `${page}: ${Object.entries(blocks).map(([k, s]) => `${k} ${code(s)}`).join(', ')}`).join('; ')}; and anything under ${code(MARKED)}.`, '');
    return `${out.join('\n')}\n`;
}

// ------------------------------------------------------------------------------------------ main

class UsageError extends Error {}

function parseArgs(argv) {
    const opt = { base: DEFAULT_BASE, routes: null, locales: null, width: 1440, js: true, concurrency: 2, out: DEFAULT_OUT, strict: false };
    for (const arg of argv) {
        const m = /^--([a-z-]+)(?:=(.*))?$/u.exec(arg);
        if (!m) throw new UsageError(`unknown argument: ${arg}`);
        const [, key, value] = m;
        if (key === 'strict') opt.strict = true;
        else if (key === 'help') opt.help = true;
        else if (value === undefined) throw new UsageError(`--${key} needs a value`);
        else if (key === 'base') opt.base = value.replace(/\/+$/u, '');
        else if (key === 'routes') opt.routes = value;
        else if (key === 'locales') opt.locales = value.split(',').map((s) => s.trim()).filter(Boolean);
        else if (key === 'width') opt.width = Number(value);
        else if (key === 'js') opt.js = !/^(off|false|0|no)$/iu.test(value);
        else if (key === 'concurrency') opt.concurrency = Math.max(1, Number(value) || 1);
        else if (key === 'out') opt.out = isAbsolute(value) ? value : join(ROOT, value);
        else throw new UsageError(`unknown option --${key}`);
    }
    if (!Number.isFinite(opt.width) || opt.width < 320) throw new UsageError('--width must be a number of pixels (>= 320)');
    return opt;
}

function loadDocx() {
    const json = execFileSync('php', ['artisan', 'summit:docx-black', '--json'], { cwd: ROOT, encoding: 'utf8', maxBuffer: 256 * 1024 * 1024 });
    return JSON.parse(json);
}

async function main() {
    let opt;
    try {
        opt = parseArgs(process.argv.slice(2));
    } catch (error) {
        console.error(String(error.message));
        return 2;
    }
    if (opt.help) {
        console.log('Usage: node scripts/qa/docx-conformance.mjs [--base=<url>] [--routes=<slugs>] [--locales=en,ar,ru] [--width=1440] [--js=on|off] [--concurrency=2] [--out=<path>] [--strict]');
        return 0;
    }
    let docx;
    try {
        docx = loadDocx();
    } catch (error) {
        console.error(`php artisan summit:docx-black --json failed: ${String(error.message).split('\n')[0]}`);
        return 2;
    }
    const slugs = [...new Set(docx.pages.map((p) => p.slug))];
    let patterns = null;
    if (opt.routes) {
        patterns = opt.routes.split(',').map((p) => {
            const re = new RegExp(`^${p.trim().replace(/[.+?^${}()|[\]\\]/gu, '\\$&').replaceAll('*', '.*')}$`, 'u');
            if (!slugs.some((s) => re.test(s))) {
                console.error(`no page matches "${p}" (pages: ${slugs.join(', ')})`);
                process.exit(2);
            }
            return re;
        });
    }
    const pendingKeys = Object.entries(docx.pending?.homeBlocks ?? {}).filter(([, b]) => b?.pending).map(([k]) => k);
    const targets = docx.pages
        .filter((p) => (!opt.locales || opt.locales.includes(p.locale)) && (!patterns || patterns.some((re) => re.test(p.slug))))
        .map((p) => ({ ...p, pendingKeys: p.slug === 'home' ? [...pendingKeys] : null }));
    if (!targets.length) {
        console.error('no page matches the filters');
        return 2;
    }
    try {
        const response = await fetch(`${opt.base}/en/`, { headers: { cookie: 'summit_design=2; summit_qa=1' }, signal: AbortSignal.timeout(20_000) });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
    } catch (error) {
        console.error(`${opt.base}/en/ cannot be reached: ${error.message}`);
        return 2;
    }
    const registries = Object.fromEntries(Object.entries(docx.registry ?? {}).map(([locale, raw]) => [locale, buildRegistry(raw)]));
    const { chromium } = await import('@playwright/test');
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const started = Date.now();
    let done = 0;
    const pages = await pool(targets, opt.concurrency, async (target) => {
        const raw = await capture(browser, opt, target);
        done += 1;
        const base = { slug: target.slug, locale: target.locale, url: target.url, file: target.file };
        if (raw.error) {
            console.log(`[${done}/${targets.length}] ${target.locale} ${target.slug}: capture failed (${raw.error})`);
            return { ...base, error: raw.error };
        }
        if (raw.design !== '2') return { ...base, error: `served data-design=${raw.design ?? 'none'} (Design 2 cookie not applied?)` };
        if (raw.status !== target.status) return { ...base, error: `HTTP ${raw.status}, expected ${target.status}` };
        const result = comparePage(target, raw, registries[target.locale]);
        // The report keeps the extra strings that need action; the recognised ones are only counted (extraCounts).
        result.extras = result.extras.filter((x) => ['unregistered', 'proposed-unmarked', 'pending (marked, not in a lang file)'].includes(x.verdict));
        const s = result.summary;
        console.log(`[${done}/${targets.length}] ${target.locale} ${target.slug}: missing ${s.missing}, truncated ${s.truncated}, altered ${s.altered}, out of order ${s.outOfOrder}, unregistered ${s.unregistered}`);
        return { ...base, status: raw.status, ...result };
    });
    await browser.close();

    const gate = pages.every((p) => !p.error && p.summary.missing + p.summary.truncated + p.summary.altered === 0);
    const strictOk = pages.every((p) => p.error || p.summary.outOfOrder + p.summary.unregistered === 0);
    const result = {
        generated: new Date().toISOString(),
        opt: { base: opt.base, width: opt.width, js: opt.js, routes: opt.routes, locales: opt.locales },
        gate,
        rules: docx.rules,
        excluded: docx.excluded,
        pages,
    };
    mkdirSync(dirname(opt.out), { recursive: true });
    writeFileSync(`${opt.out}.json`, `${JSON.stringify(result)}\n`);
    writeFileSync(`${opt.out}.md`, renderMarkdown(result));
    console.log(`\n${targets.length} pages in ${Math.round((Date.now() - started) / 1000)} s. Gate ${gate ? 'pass' : 'fail'}. Report: ${relative(ROOT, `${opt.out}.md`)} and .json`);
    return !gate || (opt.strict && !strictOk) || pages.some((p) => p.error) ? 1 : 0;
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    main().then(
        (status) => process.exit(status),
        (error) => {
            console.error(error);
            process.exit(2);
        },
    );
}
