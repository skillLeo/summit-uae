#!/usr/bin/env node
/**
 * Design 2 compliance gate (upgrade spec §9.12 and §12 Phase C; docs/d2/engineering.md §0).
 *
 * Fetches every Design 2 page (the home, the styleguide and every other Design 2 route: the practice-area
 * hub and the eleven service pages, resources, case studies, FAQ, about, contact, the legal pages, offline,
 * the 404 page and the language chooser, plus two server-rendered home states) in EN, AR and RU from
 * QA_BASE_URL (default http://127.0.0.1:8040) with the cookies summit_design=2 and summit_qa=1, parses the
 * HTML and fails (exit 1) on:
 *
 *   banned        banned claims: law firm, free consultation, 24/7, success rate, years of experience,
 *                 awards, rankings, testimonials, star ratings (English, Arabic and Russian wording)
 *   raw-key       a translation key printed instead of its text (proposed.d2.…, styleguide_d2/…)
 *   style-attr    a style="" attribute anywhere in the HTML (blocked by the CSP: style-src has no 'unsafe-inline')
 *   bidi          a phone number or e-mail address outside <bdi> (or a dir="ltr" element) on Arabic pages
 *   watermark     a sample document (.doc[data-doc]) without its watermark in the page language
 *   proposed-key  a key of lang/en/proposed-d2/*.php that is missing or empty in lang/ar or lang/ru
 *   latin         a Latin-only text node (or accessible name) on an Arabic or Russian page outside the
 *                 allow-list: the brand, codes, phone, e-mail, URLs, keycaps, text declared lang="en"
 *                 (language names, styleguide annotations) and the fine print drawn on sample documents
 *
 *   node scripts/compliance-check.mjs              (npm run compliance)
 *   QA_BASE_URL=http://127.0.0.1:8040 node scripts/compliance-check.mjs --verbose   every finding, not 8 per check
 *   node scripts/compliance-check.mjs --self-test  plants each violation in a synthetic page (no server needed)
 *
 * No browser and no dependencies: a small HTML tokenizer below reads the server-rendered markup, which is
 * what every visitor (and every visitor without JavaScript) receives. Exit 0 = clean, 1 = findings,
 * 2 = the site could not be reached.
 */
import { execFileSync } from 'node:child_process';
import { readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const BASE = (process.env.QA_BASE_URL || 'http://127.0.0.1:8040').replace(/\/+$/, '');
const COOKIE = 'summit_design=2; summit_qa=1';
const LOCALES = ['en', 'ar', 'ru'];
const VERBOSE = process.argv.includes('--verbose');
const SHOW = VERBOSE ? Infinity : 8;

/** Every Design 2 route (routes/web.php); services are read from the practice-area hub. */
const PAGES = [
    { path: '', label: 'home' },
    { path: '?navigator=S05-2', label: 'home (Navigator file S05-2, no-JS state)' },
    { path: '?service=S06&situation=S06-2&audience=individual&consultant=team-ahmed&tool=gratuity', label: 'home (wizard prefill, no-JS state)' },
    { path: 'styleguide/', label: 'styleguide' },
    { path: 'practice-areas/', label: 'practice areas' },
    { path: 'resources/', label: 'resources' },
    { path: 'case-studies/', label: 'case studies' },
    { path: 'faq/', label: 'FAQ' },
    { path: 'about/', label: 'about' },
    { path: 'contact/', label: 'contact' },
    { path: 'legal-disclaimer/', label: 'legal disclaimer' },
    { path: 'privacy-policy/', label: 'privacy policy' },
    { path: 'terms-and-conditions/', label: 'terms and conditions' },
    { path: 'offline/', label: 'offline' },
    { path: 'no-such-page-compliance/', label: '404', status: 404 },
];

// lang/{en,ar,ru}/proposed-d2/docs.php 'watermark' (Design 2.1: one phrase, no middle dot, brief §3.6 rule 2)
const WATERMARK = { en: 'ILLUSTRATIVE SAMPLE', ar: 'نموذج توضيحي', ru: 'УСЛОВНЫЙ ОБРАЗЕЦ' };

// ------------------------------------------------------------------------------------------ banned claims
// Claim phrases, not single words: approved legal copy uses "free zone", "تقييم" (assessment), "оценка",
// "جائز" (permissible) and negations such as "does not mean the review is free" correctly.
const BANNED = [
    ['law firm', /\blaw[\s-]+firms?\b|\blegal\s+firm\b|مكتب\s+(?:ال)?محاما[ةه]|مكاتب\s+(?:ال)?محاما[ةه]|شركة\s+(?:ال)?محاما[ةه]|юридическ\p{L}*\s+фирм\p{L}*|адвокатск\p{L}*\s+(?:фирм|контор|бюро)\p{L}*|юрфирм\p{L}*/giu],
    ['free consultation', /\bfree\s+(?:initial\s+|first\s+|legal\s+)?consultations?\b|\bconsultations?\s+(?:is|are)\s+free\b|\bfree\s+of\s+charge\b|\bcomplimentary\s+consultations?\b|استشار(?:ة|ات)\s+(?:قانونية\s+)?مجاني[ةه]|مجان(?:ًا|اً|ا)\s+(?:و)?بدون|бесплатн\p{L}*\s+(?:юридическ\p{L}*\s+)?консультаци\p{L}*|консультаци\p{L}*\s+бесплатн\p{L}*/giu],
    ['24/7', /\b24\s*[/x×]\s*7\b|٢٤\s*\/\s*٧|\b24\s+hours\s+a\s+day\b|\baround[\s-]the[\s-]clock\b|\bround[\s-]the[\s-]clock\b|على\s+مدار\s+الساعة|طوال\s+(?:ال)?24\s+ساعة|круглосуточн\p{L}*|24\s+часа\s+в\s+сутки/giu],
    ['success rate', /\bsuccess\s+rates?\b|\bwin\s+rates?\b|\bcases\s+won\b|\b\d{1,3}\s*%\s+(?:success|of\s+cases)|نسب[ةه]\s+(?:ال)?نجاح|معدل\s+(?:ال)?نجاح|قضايا\s+ناجح[ةه]|процент\p{L}*\s+(?:успех|побед|выигр)\p{L}*|выигранн\p{L}*\s+дел\p{L}*/giu],
    ['years of experience', /\byears?\s+of\s+(?:combined\s+)?experience\b|\byears['’]?\s+experience\b|\b\d+\+?\s+years?\s+(?:in\s+(?:practice|business)|of\s+practice)\b|\bdecades?\s+of\s+experience\b|سنوات\s+(?:من\s+)?(?:ال)?خبر[ةه]|عام(?:ًا|اً|ا)?\s+من\s+(?:ال)?خبر[ةه]|خبر[ةه]\s+(?:تزيد|تمتد|تتجاوز)|лет\s+опыта|\p{L}*летн\p{L}*\s+опыт\p{L}*/giu],
    ['award', /\bawards?\b|\baward[\s-]winning\b|\bawarded\b|جوائز|حائز(?:ة|ون)?\s+على|наград\p{L}*|лауреат\p{L}*/giu],
    ['ranking', /\branked\b|\brankings?\b|\btop[\s-]ranked\b|\bnumber\s+one\s+(?:law|legal)|#\s?1\s+(?:law|legal)|\bbest\s+(?:law|legal)\s+(?:firm|consultan)\p{L}*|المرتب[ةه]\s+الأولى|ضمن\s+أفضل|рейтинг\p{L}*|лучш\p{L}*\s+юридическ\p{L}*/giu],
    ['testimonial', /\btestimonials?\b|\bclient\s+reviews?\b|\bwhat\s+our\s+clients\s+say\b|شهادات\s+(?:ال)?عملاء|آراء\s+(?:ال)?عملاء|قالوا\s+عنا|отзыв\p{L}*\s+(?:наших\s+)?клиент\p{L}*|что\s+говорят\s+клиент\p{L}*/giu],
    // "4.9/5", "5/5 stars", "rated 5" (a step counter such as "Step 1 of 5" or "1 / 5" is not a rating)
    ['star rating', /[★☆⭐]+|\b[1-5][.,]\d\s*(?:\/|out\s+of)\s*5\b|\b[1-5]\s*(?:\/|out\s+of)\s*5\s+(?:stars?|rating)\b|\brated\s+[1-5]\b|\b(?:five|5)[\s-]stars?\b|\b\d\s+stars\b|خمس\s+نجوم|\d\s+نجوم|пять\s+звёзд|\d\s+звёзд\p{L}*|\d\s+звезд\p{L}*/giu],
];

// ------------------------------------------------------------------------------------------ allow-list (latin)
// Latin words that may stand alone on an Arabic or Russian page: the brand and codes (spec and
// engineering §0.4: USDT, TxID, VARA, DIFC, ADGM, PDF, AED amounts …) and the language codes of the
// language pill (EN · ع · RU, spec §12 A).
const LATIN_WORDS = new Set([
    'Summit', 'SUMMIT', 'WhatsApp', 'EN', 'AR', 'RU',
    'AED', 'USD', 'USDT', 'TxID', 'VARA', 'DIFC', 'ADGM', 'PDF', 'DLD', 'RERA', 'ERC', 'TRC', 'BTC', 'ETH', 'IBAN',
    'SWIFT', 'MICR', 'VAT', 'FTA', 'LLC', 'UAE', 'KYC', 'AML', 'ID', 'UTC', 'GMT', 'DOCX', 'JPG', 'JPEG', 'PNG', 'HEIC',
    'KB', 'MB', 'Ejari',
]);
// S01, S06-2, G09, P07, L01, ERC20, ERC-20, SLC-000000-000000 (a reference code), 1st …
const CODE = /^(?:[SPLG]\d{2}(?:-\d)?|[A-Z]{1,4}\d{1,4}|[A-Z]{2,5}(?:-[A-Z0-9]+)+|\d+[A-Za-z]{1,2})$/;
/** The brand as it is approved in every language (the Russian copy writes it in Latin letters). */
const BRAND = /\bSummit(?:\s+Legal(?:\s+Consultancy)?)?\b|\bSUMMIT(?:\s+LEGAL(?:\s+CONSULTANCY)?)?\b/g;
/** Values that are codes, not words: colours (#0B0B0C), site paths (/ar/), file names (contract.pdf). */
const CODE_VALUES = /#[0-9A-Fa-f]{3,8}\b|(?:^|\s)\/[\w/.-]*|[\w.-]+\.(?:pdf|docx?|jpe?g|png|heic|webp|avif|json|webmanifest)\b/gi;
const URL_OR_MAIL = /(?:https?:\/\/|www\.)\S+|[\p{L}\d._%+-]+@[\p{L}\d.-]+\.[A-Za-z]{2,}|\b[a-z0-9-]+(?:\.[a-z0-9-]+)*\.(?:com|ae|org|net|gov|io)(?:\/\S*)?/giu;
const PHONE = /(?:\+|00)?\s?9\s?7\s?1[\s().-]*\d{1,2}[\s().-]*\d{3}[\s().-]*\d{4}|\+[\d٠-٩][\d٠-٩\s().-]{6,}[\d٠-٩]/gu;
const EMAIL = /[\p{L}\d._%+-]+@[\p{L}\d.-]+\.[A-Za-z]{2,}/gu;
const LATIN = /\p{Script=Latin}/u;
const NATIVE = { ar: /\p{Script=Arabic}/u, ru: /\p{Script=Cyrillic}/u };
/** Accessible names and other visible text carried in attributes. */
const TEXT_ATTRS = ['aria-label', 'alt', 'title', 'placeholder', 'aria-roledescription', 'aria-valuetext', 'aria-description', 'data-tooltip', 'data-toast', 'label'];

// ------------------------------------------------------------------------------------------ HTML tokenizer
const VOID = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr']);
const RAW = new Set(['script', 'style']);
const RCDATA = new Set(['textarea', 'title']);
const ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", middot: '·', hellip: '…', mdash: '—', ndash: '–', laquo: '«', raquo: '»', lsquo: '‘', rsquo: '’', ldquo: '“', rdquo: '”', bull: '•', copy: '©', times: '×', rarr: '→', larr: '←' };
// invisible characters by code point (no literal control characters in this file)
Object.assign(ENTITIES, { nbsp: String.fromCharCode(0xa0), thinsp: String.fromCharCode(0x2009), zwnj: String.fromCharCode(0x200c), zwj: String.fromCharCode(0x200d), lrm: String.fromCharCode(0x200e), rlm: String.fromCharCode(0x200f) });

function decode(s) {
    if (!s.includes('&')) return s;
    return s.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (m, e) => {
        if (e[0] === '#') {
            const code = e[1] === 'x' || e[1] === 'X' ? parseInt(e.slice(2), 16) : parseInt(e.slice(1), 10);
            return Number.isFinite(code) ? String.fromCodePoint(code) : m;
        }
        return ENTITIES[e.toLowerCase()] ?? m;
    });
}

/** Tokens: { type: 'start', name, attrs, self } · { type: 'end', name } · { type: 'text', text } · { type: 'raw', name, attrs, text }. */
function* tokenize(html) {
    const n = html.length;
    let i = 0;
    while (i < n) {
        const lt = html.indexOf('<', i);
        if (lt === -1) {
            yield { type: 'text', text: html.slice(i) };
            return;
        }
        if (lt > i) yield { type: 'text', text: html.slice(i, lt) };
        if (html.startsWith('<!--', lt)) {
            const end = html.indexOf('-->', lt + 4);
            i = end === -1 ? n : end + 3;
            continue;
        }
        if (html[lt + 1] === '!' || html[lt + 1] === '?') {
            const end = html.indexOf('>', lt);
            i = end === -1 ? n : end + 1;
            continue;
        }
        if (html[lt + 1] === '/') {
            const m = /^<\/([a-zA-Z][\w:-]*)\s*>/.exec(html.slice(lt, lt + 80));
            if (m) {
                yield { type: 'end', name: m[1].toLowerCase() };
                i = lt + m[0].length;
            } else {
                yield { type: 'text', text: '<' };
                i = lt + 1;
            }
            continue;
        }
        const m = /^<([a-zA-Z][\w:-]*)/.exec(html.slice(lt, lt + 80));
        if (!m) {
            yield { type: 'text', text: '<' };
            i = lt + 1;
            continue;
        }
        const name = m[1].toLowerCase();
        const attrs = {};
        let self = false;
        let j = lt + m[0].length;
        while (j < n) {
            while (j < n && /\s/.test(html[j])) j++;
            if (html[j] === '>') {
                j++;
                break;
            }
            if (html[j] === '/' && html[j + 1] === '>') {
                self = true;
                j += 2;
                break;
            }
            if (html[j] === '/') {
                j++;
                continue;
            }
            const am = /^[^\s"'>/=]+/.exec(html.slice(j, j + 256));
            if (!am) {
                j++;
                continue;
            }
            const attr = am[0].toLowerCase();
            j += am[0].length;
            while (j < n && /\s/.test(html[j])) j++;
            let value = '';
            if (html[j] === '=') {
                j++;
                while (j < n && /\s/.test(html[j])) j++;
                const q = html[j];
                if (q === '"' || q === "'") {
                    const e = html.indexOf(q, j + 1);
                    value = html.slice(j + 1, e === -1 ? n : e);
                    j = e === -1 ? n : e + 1;
                } else {
                    const vm = /^[^\s>]+/.exec(html.slice(j, j + 4096));
                    value = vm ? vm[0] : '';
                    j += value.length;
                }
            }
            if (!(attr in attrs)) attrs[attr] = decode(value);
        }
        i = j;
        if (RAW.has(name) || RCDATA.has(name)) {
            const close = new RegExp(`</${name}\\s*>`, 'ig');
            close.lastIndex = i;
            const cm = close.exec(html);
            const end = cm ? cm.index : n;
            if (RAW.has(name)) {
                yield { type: 'raw', name, attrs, text: html.slice(i, end) };
            } else {
                yield { type: 'start', name, attrs, self: false };
                yield { type: 'text', text: html.slice(i, end) };
                yield { type: 'end', name };
            }
            i = cm ? cm.index + cm[0].length : n;
            continue;
        }
        yield { type: 'start', name, attrs, self };
    }
}

// ------------------------------------------------------------------------------------------ page scan
const classes = (attrs) => new Set((attrs.class || '').split(/\s+/).filter(Boolean));
const where = (stack) =>
    stack
        .slice(-3)
        .map((e) => `${e.name}${e.attrs.id ? `#${e.attrs.id}` : ''}${e.attrs.class ? `.${e.attrs.class.trim().split(/\s+/).slice(0, 2).join('.')}` : ''}`)
        .join(' > ');
const clip = (s, max = 110) => {
    const one = String(s).replace(/\s+/g, ' ').trim();
    return one.length > max ? `${one.slice(0, max)}…` : one;
};

/** Removes everything a Latin-only run may legitimately consist of; what is left must not contain Latin letters. */
function strayLatin(text) {
    const rest = text.replace(URL_OR_MAIL, ' ').replace(PHONE, ' ').replace(BRAND, ' ').replace(CODE_VALUES, ' ');
    const words = rest.match(/[A-Za-z][A-Za-z0-9'’-]*/g) || [];
    return words.filter((w) => !LATIN_WORDS.has(w) && !CODE.test(w) && !LATIN_WORDS.has(w.replace(/['’]s$/, '')));
}

function scanPage(html, page) {
    const findings = [];
    const add = (check, detail) => findings.push({ check, page: page.id, detail });
    const htmlTag = /<html\b[^>]*>/i.exec(html)?.[0] ?? '';
    const lang = (/\blang="([a-z]{2})/i.exec(htmlTag)?.[1] || 'en').toLowerCase();
    const design = /\bdata-design="(\d)"/.exec(htmlTag)?.[1] ?? null;
    const visible = []; // every visible string, for the banned-claim scan
    const stack = [{ name: '#root', attrs: {}, lang, inDoc: false, iso: false, code: false, doc: null, native: false, pending: [] }];
    const styleguide = /\/styleguide\/$/.test(page.url.split('?')[0]);
    let docs = 0;
    /** Latin-only text applies only where the declared language is the page language (lang="en" is declared English). */
    const latinApplies = (el) => (lang === 'ar' || lang === 'ru') && !el.inDoc && !el.code && el.lang === lang;
    /**
     * A Latin-only run inside <bdi> or dir="ltr" (engineering §0.4: codes and product names inside Arabic
     * text are isolated): one token is a code; several words are fine only inside a sentence in the page
     * language, so the check waits until the element around the isolate closes.
     */
    const latinFinding = (text, detail) => {
        if (!strayLatin(text).length) return;
        const top = stack[stack.length - 1];
        if (top.iso) {
            if (!/\s/.test(text.trim())) return;
            let k = stack.length - 1;
            while (k > 1 && stack[k - 1].iso) k--;
            stack[Math.max(0, k - 1)].pending.push(detail);
            return;
        }
        add('latin', detail);
    };

    const checkKey = (text, context) => {
        const re = /\bproposed\.d2\.[a-z0-9_]+(?:\.[a-z0-9_-]+)+|\bstyleguide_d2\/[a-z0-9_-]+\.[a-z0-9_.-]+/gi;
        for (const m of text.matchAll(re)) {
            if (/\.php$/i.test(m[0])) continue; // a file path in a styleguide annotation, not a missing key
            add('raw-key', `${m[0]}  (${context})`);
        }
    };

    for (const t of tokenize(html)) {
        const top = stack[stack.length - 1];
        if (t.type === 'start') {
            const a = t.attrs;
            const cls = classes(a);
            if ('style' in a) add('style-attr', `<${t.name}${a.id ? ` id="${a.id}"` : ''}${a.class ? ` class="${clip(a.class, 50)}"` : ''} style="${clip(a.style, 60)}">  in ${where(stack)}`);
            for (const [k, v] of Object.entries(a)) if (v.includes('proposed.d2.') || v.includes('styleguide_d2/')) checkKey(v, `${t.name}[${k}] in ${where(stack)}`);
            const isDoc = cls.has('doc') && 'data-doc' in a;
            const el = {
                name: t.name,
                attrs: a,
                lang: a.lang ? a.lang.toLowerCase().slice(0, 2) : top.lang,
                inDoc: top.inDoc || isDoc,
                // an isolate: <bdi>, or a dedicated dir="ltr" element (never the page itself: <html dir="ltr">)
                iso: top.iso || t.name === 'bdi' || (t.name !== 'html' && t.name !== 'body' && (a.dir || '').toLowerCase() === 'ltr'),
                code: top.code || ['kbd', 'code', 'samp', 'pre'].includes(t.name) || cls.has('sg-code'),
                native: false,
                pending: [],
                doc: isDoc ? { type: a['data-doc'], variant: a['data-variant'] || '', lines: [], where: where(stack) } : top.doc,
                isDoc,
            };
            if (isDoc) docs++;
            if (el.doc && cls.has('doc__wm-line')) el.doc.lines.push({ lang: (a.lang || '').slice(0, 2), text: (a['data-wm'] || '').trim() });
            // visible text in attributes
            const texts = TEXT_ATTRS.filter((k) => a[k]).map((k) => [k, a[k]]);
            if (t.name === 'meta' && /^(?:description|og:title|og:description|twitter:title|twitter:description)$/.test(a.name || a.property || '')) texts.push(['content', a.content || '']);
            if (t.name === 'input' && /^(?:submit|button|reset)$/.test(a.type || '') && a.value) texts.push(['value', a.value]);
            for (const [k, v] of texts) {
                visible.push(v);
                // the styleguide is an internal English page: its <title> and meta cannot declare their own language
                if (styleguide && (t.name === 'meta' || t.name === 'title')) continue;
                if (latinApplies(el) && LATIN.test(v) && !NATIVE[lang].test(v) && strayLatin(v).length) add('latin', `${t.name}[${k}]="${clip(v, 80)}"  in ${where(stack)}`);
            }
            const voidEl = VOID.has(t.name) || t.self;
            if (!voidEl) stack.push(el);
            else if (isDoc) closeDoc(el);
        } else if (t.type === 'end') {
            let k = stack.length - 1;
            while (k > 0 && stack[k].name !== t.name) k--;
            if (k > 0) {
                const popped = stack.splice(k);
                for (let j = popped.length - 1; j >= 0; j--) {
                    const e = popped[j];
                    const parent = j > 0 ? popped[j - 1] : stack[stack.length - 1];
                    if (e.isDoc) closeDoc(e);
                    if (e.native) {
                        if (parent) parent.native = true;
                    } else {
                        for (const detail of e.pending) add('latin', detail);
                    }
                }
            }
        } else if (t.type === 'raw') {
            if (t.name === 'script' && /json/i.test(t.attrs.type || '')) {
                if (t.text.includes('proposed.d2.') || t.text.includes('styleguide_d2/')) checkKey(t.text, `script${t.attrs.id ? `#${t.attrs.id}` : ''}[type=json] in ${where(stack)}`);
                try {
                    const walk = (v) => {
                        if (typeof v === 'string') visible.push(v);
                        else if (v && typeof v === 'object') Object.values(v).forEach(walk);
                    };
                    walk(JSON.parse(t.text));
                } catch {
                    /* not JSON: ignore */
                }
            }
        } else if (t.type === 'text') {
            const text = decode(t.text);
            if (!text.trim()) continue;
            visible.push(text);
            if (text.includes('proposed.d2.') || text.includes('styleguide_d2/')) checkKey(text, `text in ${where(stack)}`);
            // phone numbers and e-mail addresses are isolated on Arabic pages
            if (lang === 'ar' && !top.iso) {
                for (const m of text.matchAll(PHONE)) if (m[0].replace(/\D/g, '').length >= 8) add('bidi', `phone "${m[0].trim()}" outside <bdi>: "${clip(text, 70)}"  in ${where(stack)}`);
                for (const m of text.matchAll(EMAIL)) add('bidi', `e-mail "${m[0]}" outside <bdi>: "${clip(text, 70)}"  in ${where(stack)}`);
            }
            // Latin-only text on Arabic and Russian pages
            if (NATIVE[lang]?.test(text)) top.native = true;
            else if (latinApplies(top) && LATIN.test(text) && !(styleguide && top.name === 'title')) latinFinding(text, `"${clip(text, 80)}"  in ${where(stack)}`);
        }
    }

    // elements left open at the end of the document (tolerant parser): settle their pending Latin runs
    for (let j = stack.length - 1; j >= 0; j--) if (!stack[j].native) for (const detail of stack[j].pending) add('latin', detail);

    function closeDoc(e) {
        const d = e.doc;
        const own = d.lines.find((l) => l.lang === lang && l.text === WATERMARK[lang]);
        if (!own) {
            const seen = d.lines.map((l) => `${l.lang}:${l.text}`).join(' | ') || 'none';
            add('watermark', `.doc--${d.type}${d.variant ? ` (${d.variant})` : ''} has no "${WATERMARK[lang]}" line (found: ${seen})  in ${d.where}`);
        }
    }

    // banned claims over every visible string of the page
    const all = visible.join('\n');
    for (const [id, re] of BANNED) {
        for (const m of all.matchAll(re)) add('banned', `${id}: "${clip(all.slice(Math.max(0, m.index - 50), m.index + m[0].length + 50), 140)}"`);
    }
    return { findings, lang, design, docs };
}

// ------------------------------------------------------------------------------------------ proposed keys
function proposedKeys() {
    const dir = join(ROOT, 'lang/en/proposed-d2');
    const files = readdirSync(dir).filter((f) => f.endsWith('.php'));
    const php = `
        $out = [];
        $flat = function ($a, $p = '') use (&$flat) { $r = []; foreach ($a as $k => $v) { if (is_array($v)) { $r += $flat($v, $p.$k.'.'); } else { $r[$p.$k] = $v; } } return $r; };
        foreach (json_decode($argv[1], true) as $file) {
            foreach (['en', 'ar', 'ru'] as $l) {
                $path = $argv[2].'/lang/'.$l.'/proposed-d2/'.$file;
                $data = is_file($path) ? require $path : null;
                $out[$file][$l] = is_array($data) ? $flat($data) : null;
            }
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);`;
    const json = execFileSync('php', ['-r', php, JSON.stringify(files), ROOT], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
    const data = JSON.parse(json);
    const findings = [];
    let count = 0;
    for (const [file, by] of Object.entries(data)) {
        const area = file.replace(/\.php$/, '');
        for (const [key, value] of Object.entries(by.en || {})) {
            count++;
            for (const l of ['ar', 'ru']) {
                if (by[l] === null) {
                    findings.push({ check: 'proposed-key', page: `lang/${l}/proposed-d2/${file}`, detail: 'file missing' });
                    break;
                }
                const v = by[l][key];
                if (v === undefined) findings.push({ check: 'proposed-key', page: `lang/${l}/proposed-d2/${file}`, detail: `missing ${area}.${key}` });
                else if (String(v).trim() === '' && String(value).trim() !== '') findings.push({ check: 'proposed-key', page: `lang/${l}/proposed-d2/${file}`, detail: `empty ${area}.${key}` });
            }
        }
    }
    // keep each missing-file finding once
    const seen = new Set();
    return {
        count,
        files: files.length,
        findings: findings.filter((f) => {
            const k = `${f.page}|${f.detail}`;
            if (seen.has(k)) return false;
            seen.add(k);
            return true;
        }),
    };
}

// ------------------------------------------------------------------------------------------ run
async function get(path) {
    const res = await fetch(`${BASE}${path}`, { headers: { cookie: COOKIE, 'accept-language': 'en' }, redirect: 'manual' });
    return { status: res.status, body: await res.text(), location: res.headers.get('location') };
}

async function pool(items, size, fn) {
    const out = new Array(items.length);
    let next = 0;
    await Promise.all(
        Array.from({ length: size }, async () => {
            while (next < items.length) {
                const i = next++;
                out[i] = await fn(items[i]);
            }
        }),
    );
    return out;
}

async function main() {
    const started = Date.now();
    let hub;
    try {
        hub = await get('/en/practice-areas/');
    } catch (error) {
        console.error(`compliance: cannot reach ${BASE} (${error.cause?.code || error.message}). Start the site or set QA_BASE_URL.`);
        process.exit(2);
    }
    const slugs = [...new Set([...hub.body.matchAll(/href="(?:https?:\/\/[^/"]+)?\/en\/practice-areas\/([a-z0-9-]+)\/"/g)].map((m) => m[1]))];
    const pages = [{ id: '/', url: '/', label: 'language chooser', status: 200, locale: null }];
    for (const locale of LOCALES) {
        for (const p of PAGES) pages.push({ id: `/${locale}/${p.path}`, url: `/${locale}/${p.path}`, label: p.label, status: p.status ?? 200, locale });
        for (const s of slugs) pages.push({ id: `/${locale}/practice-areas/${s}/`, url: `/${locale}/practice-areas/${s}/`, label: `service ${s}`, status: 200, locale });
    }

    const findings = [];
    const notes = [];
    let docsTotal = 0;
    let bytes = 0;
    const results = await pool(pages, 4, async (page) => {
        const res = await get(page.url);
        return { page, res };
    });
    for (const { page, res } of results) {
        if (res.status !== page.status) {
            findings.push({ check: 'http', page: page.id, detail: `HTTP ${res.status}${res.location ? ` → ${res.location}` : ''}, expected ${page.status}` });
            continue;
        }
        bytes += res.body.length;
        const scan = scanPage(res.body, page);
        if (scan.design !== '2') {
            notes.push(`${page.id}: rendered by Design ${scan.design ?? '?'} (no Design 2 view), not checked`);
            continue;
        }
        if (page.locale && scan.lang !== page.locale) findings.push({ check: 'http', page: page.id, detail: `html lang="${scan.lang}", expected ${page.locale}` });
        docsTotal += scan.docs;
        findings.push(...scan.findings);
    }
    const keys = proposedKeys();
    findings.push(...keys.findings);

    // ---------------------------------------------------------------- report
    const CHECKS = [
        ['http', 'Pages answer with the expected status and language'],
        ['banned', 'No banned claims (law firm, free consultation, 24/7, success rate, years of experience, awards, rankings, testimonials, star ratings)'],
        ['raw-key', 'No raw translation keys (proposed.d2.…, styleguide_d2/…)'],
        ['style-attr', 'No style="" attributes (CSP)'],
        ['bidi', 'Phone numbers and e-mail addresses isolated in <bdi> on Arabic pages'],
        ['watermark', 'Every sample document carries its watermark'],
        ['proposed-key', 'Every lang/en/proposed-d2 key exists and is non-empty in ar and ru'],
        ['latin', 'No Latin-only text on Arabic and Russian pages outside the allow-list'],
    ];
    const checked = results.length - notes.length;
    console.log(`\nDesign 2 compliance check — ${BASE}`);
    console.log(`${checked} pages (${(bytes / 1048576).toFixed(1)} MB of HTML, ${docsTotal} sample documents), ${keys.count} proposed keys in ${keys.files} files, ${((Date.now() - started) / 1000).toFixed(1)} s\n`);
    let failed = 0;
    for (const [id, title] of CHECKS) {
        const list = findings.filter((f) => f.check === id);
        console.log(`${list.length ? 'FAIL' : 'PASS'}  ${title}${list.length ? `: ${list.length}` : ''}`);
        if (list.length) {
            failed += list.length;
            for (const f of list.slice(0, SHOW)) console.log(`        ${f.page}  ${f.detail}`);
            if (list.length > SHOW) console.log(`        … ${list.length - SHOW} more (--verbose lists all)`);
        }
    }
    for (const n of notes) console.log(`note  ${n}`);
    console.log(failed ? `\n${failed} finding(s). Fix them and run npm run compliance again.\n` : '\nClean.\n');
    process.exit(failed ? 1 : 0);
}

/** --self-test: every check must fire on a planted violation and stay quiet on the allowed forms. */
function selfTest() {
    const page = (lang, body, url = `/${lang}/`) => ({
        id: url,
        url,
        html: `<!DOCTYPE html><html lang="${lang}" dir="${lang === 'ar' ? 'rtl' : 'ltr'}" data-design="2"><head><title>${{ en: 'Test', ar: 'اختبار', ru: 'Тест' }[lang]}</title></head><body>${body}</body></html>`,
    });
    const doc = (wm) => `<div class="doc doc--cheque" data-doc="cheque"><div class="doc__body" aria-hidden="true"><p>Sample Bank</p>${wm}</div></div>`;
    const wmLine = (l, text) => `<span class="doc__wm"><span class="doc__wm-line" lang="${l}" data-wm="${text}"></span></span>`;
    const cases = [
        ['banned', page('en', '<p>A law firm with a free consultation 24/7.</p>'), 3],
        ['banned', page('en', '<p>Our success rate and 20 years of experience; award-winning, ranked first.</p><p>Testimonials ★★★★★ rated 5</p>'), 7],
        ['banned', page('ar', '<p>مكتب محاماة يقدم استشارة مجانية على مدار الساعة</p>'), 3],
        ['banned', page('ru', '<p>Юридическая фирма: бесплатная консультация, круглосуточно, отзывы клиентов</p>'), 4],
        ['banned', page('en', '<p>Step 1 of 5 · 1 / 5 · free zone · the review is not free</p>'), 0],
        ['raw-key', page('en', '<p>proposed.d2.hero.title</p><button aria-label="styleguide_d2/hero.next">x</button><p>lang/en/styleguide_d2/hero.php</p>'), 2],
        ['style-attr', page('en', '<div style="color: red">x</div><svg><path style="fill:red"/></svg>'), 2],
        ['bidi', page('ar', '<p>اتصل على +971 52 466 6191 أو info@summitlegaluae.com</p>'), 2],
        ['bidi', page('ar', '<p>اتصل على <bdi dir="ltr">+971 52 466 6191</bdi> أو <a dir="ltr">info@summitlegaluae.com</a></p>'), 0],
        ['watermark', page('ar', doc('') + doc(wmLine('en', WATERMARK.en)) + doc(wmLine('ar', WATERMARK.ar))), 2],
        ['latin', page('ar', '<p>Request a consultation</p><button aria-label="Close">×</button>'), 2],
        ['latin', page('ru', '<p>Read the guide</p><p><bdi>Two English words</bdi></p>'), 2],
        [
            'latin',
            page(
                'ar',
                '<p>تواصل <bdi dir="ltr">Smart Rental Index</bdi> الآن</p><span lang="en">English</span><kbd>Esc</kbd><span>USDT</span><span>RU</span>' +
                    '<bdi dir="ltr">SLC-000000-000000</bdi><dd dir="ltr">standalone</dd><p>Summit Legal Consultancy</p><a>https://www.summitlegaluae.com/ar/</a>' +
                    doc(wmLine('ar', WATERMARK.ar)),
            ),
            0,
        ],
    ];
    let bad = 0;
    for (const [check, p, expected] of cases) {
        const got = scanPage(p.html, p).findings.filter((f) => f.check === check);
        const ok = got.length === expected;
        if (!ok) bad++;
        console.log(`${ok ? 'ok  ' : 'FAIL'}  ${check.padEnd(10)} expected ${expected}, got ${got.length}${ok ? '' : `\n        ${got.map((f) => f.detail).join('\n        ')}`}`);
    }
    console.log(bad ? `\n${bad} self-test case(s) failed.\n` : '\nSelf-test passed.\n');
    process.exit(bad ? 1 : 0);
}

if (process.argv.includes('--self-test')) selfTest();
else main();
