#!/usr/bin/env node
/**
 * Design 2 web fonts (docs/briefs/design-2.1-and-admin-cms.md §3.2; contract: docs/d2.1/phase-1/tokens.md §5).
 *
 *   node scripts/fonts/fontsource-d2.mjs                 copy the font files, write the CSS and the manifest
 *   node scripts/fonts/fontsource-d2.mjs --check         exit 1 when any output is stale (writes nothing)
 *   node scripts/fonts/fontsource-d2.mjs --out=<dir>     write under <dir> instead of the project (dry runs)
 *   node scripts/fonts/fontsource-d2.mjs --graphik-dir=<dir>   where licensed Graphik files are looked for
 *                                                        (default resources/fonts/graphik)
 *   --allow-missing                                      report characters no file draws instead of failing
 *
 * Pair B (free, in use): the UNMODIFIED WOFF2 files of two Fontsource packages, pinned exactly in package.json:
 *   @fontsource-variable/onest 5.3.1        CSS family "D2 Sans": Latin and Cyrillic (EN, RU pages), one variable
 *                                           file per subset, declared for the weights 400 to 600
 *   @fontsource/ibm-plex-sans-arabic 5.3.0  CSS family "D2 Sans Arabic": Arabic, and the Latin runs of Arabic pages
 *                                           (digits, spaces, codes, e-mail) from the same family, static 400/500/600
 * Nothing is subset, instanced or renamed inside a file: each file is copied byte for byte (its SHA-256 is in the
 * manifest) under its package file name plus a content hash, so the service worker's cache-first font cache never
 * serves a stale file. Each @font-face keeps the package's own unicode-range and order. The subsets copied are the
 * ones the site copy needs (database/content/*.json, lang/{en,ar,ru}/proposed.php with its proposed-d2 files): a
 * character of that copy that no copied file draws fails the script.
 *
 * Pair A (licensed, later): drop Commercial Type's WOFF2 files into resources/fonts/graphik/ named
 *   Graphik-Regular*.woff2, Graphik-Medium*.woff2, Graphik-Semibold*.woff2                       Latin (+ Cyrillic)
 *   GraphikArabic-Regular*.woff2, GraphikArabic-Medium*.woff2, GraphikArabic-Semibold*.woff2      Arabic
 * (plus their licence file) and run this script: it copies them as public/fonts/d2/graphik[-arabic]-{400,500,600}
 * .<hash>.woff2 and declares the CSS families "D2 Graphik" and "D2 Graphik Arabic" (unicode-range from each file's
 * own character map) with their fallbacks. The site switches with ONE token change in resources/css/d2/tokens.css:
 * `--font-sans: var(--font-b)` becomes `var(--font-a)`; App\Design\D2\Fonts reads that token for the preloads.
 *
 * Sample documents only (brief §3.2: no monospace or serif in the interface): "D2 Mono" (IBM Plex Mono), "D2 Serif"
 * (Source Serif 4) and "D2 Naskh" (Noto Naskh Arabic) keep the files scripts/fonts/build-d2.py made (mono-*,
 * serif-*, naskh-arabic in public/fonts/d2), declared unchanged. Do not run build-d2.py again: it would rewrite
 * fonts.css and the manifest for the retired IBM Plex Sans interface faces.
 *
 * Metric-matched fallbacks: every family gets "<family> Fallback" faces made of local fonts (Arial, Geeza Pro, Menlo,
 * Georgia, Times New Roman) with size-adjust, ascent-override, descent-override and line-gap-override measured from
 * the real font files over the site copy by scripts/fonts/fontsource-d2-metrics.py (fontTools in .venv-fonts), so
 * the swap to the web font moves nothing (CLS below 0.02). Arabic pages draw the space from the Latin file of their
 * family: a space-only fallback face gives it the web font's width until that file arrives.
 *
 * Outputs (deterministic: the same inputs write byte-identical files)
 *   public/fonts/d2/<file>.<hash8>.woff2     the copied files (any other font file in the folder is removed)
 *   public/fonts/d2/LICENSE-<font>.txt       each font's licence (SIL Open Font License 1.1), as shipped
 *   public/fonts/d2/manifest.json            packages and every file (url, family, weight, unicode-range, source,
 *                                            sha256, bytes); App\Design\D2\Fonts reads it
 *   resources/css/d2/base/fonts.css          the @font-face rules; App\Design\D2\FontFaces inlines them in the head
 *   storage/app/d2/fonts/fontsource-d2.json  report: coverage per pair and locale, every fallback measurement
 */
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const argv = Object.fromEntries(process.argv.slice(2).map((a) => {
    const [k, ...v] = a.replace(/^--/, '').split('=');
    return [k, v.length ? v.join('=') : 'true'];
}));
const CHECK = argv.check === 'true';
const OUT = path.resolve(ROOT, argv.out ?? '.');
const GRAPHIK_DIR = path.resolve(ROOT, argv['graphik-dir'] ?? 'resources/fonts/graphik');
const PYTHON = path.join(ROOT, '.venv-fonts', 'bin', 'python');
const METRICS = path.join(ROOT, 'scripts', 'fonts', 'fontsource-d2-metrics.py');
const SOURCE_FONT_DIR = path.join(ROOT, 'public', 'fonts', 'd2'); // the sample-document files are read from here
const FONT_DIR = path.join(OUT, 'public', 'fonts', 'd2');
const CSS_PATH = path.join(OUT, 'resources', 'css', 'd2', 'base', 'fonts.css');
const MANIFEST_PATH = path.join(FONT_DIR, 'manifest.json');
const REPORT_PATH = path.join(OUT, 'storage', 'app', 'd2', 'fonts', 'fontsource-d2.json');
const URL_PREFIX = '/fonts/d2/';
const LOCALES = ['en', 'ar', 'ru'];

// ------------------------------------------------------------------------------------------- configuration

/** The two packages of pair B, pinned exactly (package.json devDependencies). */
const PACKAGES = {
    onest: { name: '@fontsource-variable/onest', version: '5.3.1', css: 'wght.css', licence: 'LICENSE-onest.txt' },
    plex: { name: '@fontsource/ibm-plex-sans-arabic', version: '5.3.0', css: '{weight}.css', licence: 'LICENSE-ibm-plex-sans-arabic.txt' },
};

/**
 * Interface families of pair B. `serves`: the page locales whose text the family draws first; `weights`: the ones
 * the brief allows (400, 500, 600). A variable file is declared once with the range, a static one per weight.
 */
const PAIR_B = {
    'D2 Sans': { package: 'onest', serves: ['en', 'ru'], weights: [400, 500, 600], variable: true },
    'D2 Sans Arabic': { package: 'plex', serves: ['ar'], weights: [400, 500, 600], variable: false },
};
/**
 * Script-only aliases: the same files as their source family (no extra download), declared only for one script's
 * code points. A page stack names the other script through its alias, so a stack never holds two families that both
 * cover the page's own text: document.fonts.load(<computed family list>, text), which loads every face of the list
 * whose unicode-range meets the text, then never fetches the other family's Latin files.
 */
const ARABIC_BLOCKS = [[0x0600, 0x06ff], [0x0750, 0x077f], [0x0870, 0x08ff], [0xfb50, 0xfdff], [0xfe70, 0xfefc], [0x10e60, 0x10e7e], [0x1ee00, 0x1eeff]];
const CYRILLIC_BLOCKS = [[0x0400, 0x052f], [0x1c80, 0x1c8f], [0x2de0, 0x2dff], [0xa640, 0xa69f]];
const ALIASES = {
    'D2 Sans Arabic Script': { source: 'D2 Sans Arabic', blocks: ARABIC_BLOCKS, part: 'arabic' },
    'D2 Sans Cyrillic': { source: 'D2 Sans', blocks: CYRILLIC_BLOCKS, part: 'text' },
    'D2 Graphik Arabic Script': { source: 'D2 Graphik Arabic', blocks: ARABIC_BLOCKS, part: 'arabic' },
    'D2 Graphik Cyrillic': { source: 'D2 Graphik', blocks: CYRILLIC_BLOCKS, part: 'text' },
};
/** How each locale's text falls through the families (tokens.css: --font-b-latin, --font-b-arabic, --font-a-*). */
const STACKS = {
    b: { en: ['D2 Sans', 'D2 Sans Arabic Script'], ru: ['D2 Sans', 'D2 Sans Arabic Script'], ar: ['D2 Sans Arabic', 'D2 Sans Cyrillic'] },
    a: { en: ['D2 Graphik', 'D2 Graphik Arabic Script'], ru: ['D2 Graphik', 'D2 Graphik Arabic Script'], ar: ['D2 Graphik Arabic', 'D2 Graphik Cyrillic'] },
};

/** Sample-document faces (scripts/fonts/build-d2.py made them; these are the ranges it subset them to). */
const DOC_RANGES = {
    latin: 'U+0000-00FF, U+0100-017F, U+2000-206F, U+2190-2193, U+2212, U+2264-2265',
    cyrillic: 'U+0400-045F, U+0490-0491, U+2116',
    arabic: 'U+0600-06FF, U+0750-077F, U+FE70-FEFF, U+200C-200F',
};
/** family -> [file key, font-weight, range] in the order they are declared (the last one is checked first) */
const DOC_FAMILIES = {
    'D2 Mono': [['mono-latin-400', '400', 'latin'], ['mono-cyrillic-400', '400', 'cyrillic'], ['mono-latin', '500', 'latin'], ['mono-cyrillic', '500', 'cyrillic']],
    'D2 Serif': [['serif-latin', '400 600', 'latin'], ['serif-cyrillic', '400 600', 'cyrillic']],
    'D2 Naskh': [['serif-latin', '400 600', 'latin'], ['serif-cyrillic', '400 600', 'cyrillic'], ['naskh-arabic', '400 600', 'arabic']],
};
const DOC_SOURCES = {
    'D2 Mono': 'IBM Plex Mono 400 and 500, subset and renamed by scripts/fonts/build-d2.py (OFL Reserved Font Name)',
    'D2 Serif': 'Source Serif 4, wght 400-600, opsz 20, subset and renamed by scripts/fonts/build-d2.py',
    'D2 Naskh': 'Noto Naskh Arabic, wght 400-600, subset by scripts/fonts/build-d2.py; Latin and Cyrillic from D2 Serif',
};
const DOC_LICENCES = {
    'LICENSE-ibm-plex-mono.txt': 'resources/fonts/src-d2/ibmplexmono/OFL.txt',
    'LICENSE-source-serif-4.txt': 'resources/fonts/src/sourceserif4/OFL.txt',
    'LICENSE-noto-naskh-arabic.txt': 'resources/fonts/src/notonaskharabic/OFL.txt',
};

/** Local fonts the fallback faces scale (tuned for the first one installed; the Windows names follow). */
const ARIAL = ['Arial', 'ArialMT', 'Helvetica Neue', 'HelveticaNeue'];
const ARIAL_BOLD = ['Arial Bold', 'Arial-BoldMT', 'Helvetica Neue Bold', 'HelveticaNeue-Bold'];
const GEEZA = ['Geeza Pro Regular', 'GeezaPro', 'Geeza Pro', 'Segoe UI', 'SegoeUI', 'Tahoma'];
const GEEZA_BOLD = ['Geeza Pro Bold', 'GeezaPro-Bold', 'Segoe UI Bold', 'SegoeUI-Bold', 'Tahoma Bold', 'Tahoma-Bold'];
const MENLO = ['Menlo Regular', 'Menlo-Regular', 'Menlo', 'Consolas'];
const GEORGIA = ['Georgia'];
const GEORGIA_BOLD = ['Georgia Bold', 'Georgia-Bold'];
const TIMES = ['Times New Roman', 'TimesNewRomanPSMT', 'Geeza Pro Regular', 'GeezaPro', 'Geeza Pro'];
const TIMES_BOLD = ['Times New Roman Bold', 'TimesNewRomanPS-BoldMT', 'Geeza Pro Bold', 'GeezaPro-Bold'];
const latinLocals = (weight) => (weight >= 600 ? ARIAL_BOLD : ARIAL);
const arabicLocals = (weight) => (weight >= 600 ? GEEZA_BOLD : GEEZA);
const SPACE = [[0x20, 0x20], [0xa0, 0xa0]];

// ------------------------------------------------------------------------------------------- helpers

const rel = (p) => path.relative(ROOT, p) || '.';
const fail = (message) => {
    console.error(`fontsource-d2: ${message}`);
    process.exit(1);
};
const sha256 = (buffer) => createHash('sha256').update(buffer).digest('hex');
const pct = (value) => `${(value * 100).toFixed(2).replace(/\.?0+$/, '')}%`;
const hex = (cp) => cp.toString(16).toUpperCase().padStart(4, '0');
const isControl = (ch) => /[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u.test(ch);
const charName = (ch, n) => `U+${hex(ch.codePointAt(0))} "${ch}" x${n}`;
const inRanges = (cp, ranges) => ranges.some(([lo, hi]) => cp >= lo && cp <= hi);

/** "U+0000-00FF,U+0131" -> [[0, 255], [305, 305]] */
function parseRanges(spec) {
    return spec.split(',').map((part) => {
        const m = /^U\+([0-9A-F]{1,6})(?:-([0-9A-F]{1,6}))?$/i.exec(part.trim());
        if (!m) fail(`bad unicode-range part "${part}"`);
        return [parseInt(m[1], 16), parseInt(m[2] ?? m[1], 16)];
    });
}

/** Ranges -> a compact, sorted, merged unicode-range ("U+0020-007E, U+00A0") */
function formatRanges(ranges) {
    const sorted = ranges.map(([a, b]) => [a, b]).sort((x, y) => x[0] - y[0] || x[1] - y[1]);
    const merged = [];
    for (const [lo, hi] of sorted) {
        const last = merged[merged.length - 1];
        if (last && lo <= last[1] + 1) last[1] = Math.max(last[1], hi);
        else merged.push([lo, hi]);
    }
    return merged.map(([lo, hi]) => (lo === hi ? `U+${hex(lo)}` : `U+${hex(lo)}-${hex(hi)}`)).join(', ');
}
const pointsToRanges = (cps) => [...new Set(cps)].sort((a, b) => a - b).map((cp) => [cp, cp]);

/** the part of `ranges` inside `blocks` */
function intersect(ranges, blocks) {
    const out = [];
    for (const [lo, hi] of ranges) {
        for (const [blo, bhi] of blocks) {
            const a = Math.max(lo, blo);
            const b = Math.min(hi, bhi);
            if (a <= b) out.push([a, b]);
        }
    }
    return out;
}

/** The script-only alias families of the families present (same files, ranges cut to one script). */
function buildAliases(sources) {
    const out = {};
    for (const [name, spec] of Object.entries(ALIASES)) {
        const source = sources[spec.source];
        if (!source) continue;
        const members = source.members
            .map((m) => ({ ...m, ranges: intersect(m.ranges, spec.blocks) }))
            .filter((m) => m.ranges.length)
            .map((m) => ({ ...m, unicodeRange: formatRanges(m.ranges) }));
        if (members.length) out[name] = { ...source, alias: spec.source, part: spec.part, members };
    }
    return out;
}

/** ranges minus the code points of `remove` */
function subtract(ranges, remove) {
    return ranges.flatMap(([lo, hi]) => {
        let pieces = [[lo, hi]];
        for (const [rlo, rhi] of remove) {
            pieces = pieces.flatMap(([a, b]) => (rhi < a || rlo > b ? [[a, b]] : [
                ...(a < rlo ? [[a, rlo - 1]] : []),
                ...(b > rhi ? [[rhi + 1, b]] : []),
            ]));
        }
        return pieces;
    });
}

/** The @font-face blocks of a Fontsource CSS file, in source order. */
function fontsourceFaces(cssFile) {
    const css = readFileSync(cssFile, 'utf8');
    return [...css.matchAll(/@font-face\s*\{([^}]*)\}/g)].map(([, body]) => {
        const file = /url\(\.\/files\/([^)]+\.woff2)\)/.exec(body)?.[1];
        const range = /unicode-range:\s*([^;]+);/.exec(body)?.[1];
        const subset = file && /-(arabic|cyrillic-ext|cyrillic|latin-ext|latin|math|symbols|vietnamese)-(?:wght|\d{3})-normal\.woff2$/.exec(file)?.[1];
        if (!file || !range || !subset) fail(`${rel(cssFile)}: an @font-face block this script does not understand`);
        return { file, subset, unicodeRange: range.split(',').map((s) => s.trim()).join(', ') };
    });
}

function readPackage(id) {
    const spec = PACKAGES[id];
    const dir = path.join(ROOT, 'node_modules', ...spec.name.split('/'));
    if (!existsSync(path.join(dir, 'package.json'))) fail(`${spec.name} is not installed (npm ci)`);
    const pkg = JSON.parse(readFileSync(path.join(dir, 'package.json'), 'utf8'));
    if (pkg.version !== spec.version) fail(`${spec.name} is ${pkg.version}; the owner pinned ${spec.version}`);
    const metadata = JSON.parse(readFileSync(path.join(dir, 'metadata.json'), 'utf8'));
    return { ...spec, dir, license: pkg.license, metadata };
}

function python(job) {
    if (!existsSync(PYTHON)) {
        fail(`${rel(PYTHON)} is missing: python3 -m venv .venv-fonts && .venv-fonts/bin/pip install -r scripts/fonts/requirements.txt`);
    }
    const out = execFileSync(PYTHON, ['-I', METRICS], { input: JSON.stringify(job), maxBuffer: 256 * 1024 * 1024 });
    return JSON.parse(out.toString('utf8'));
}

// ------------------------------------------------------------------------------------------- the copy

const PHP_DUMP = 'echo json_encode(require $argv[1], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);';
const dumpPhp = (file) => JSON.parse(execFileSync('php', ['-r', PHP_DUMP, file], { cwd: ROOT, encoding: 'utf8' }) || 'null');

function strings(value, out = []) {
    if (typeof value === 'string') out.push(value);
    else if (Array.isArray(value)) value.forEach((v) => strings(v, out));
    else if (value && typeof value === 'object') Object.values(value).forEach((v) => strings(v, out));
    return out;
}

/** Per locale: public text (approved DOCX copy, proposed copy) and internal labels (styleguide notes, switcher). */
function collectCopy() {
    const copy = Object.fromEntries(LOCALES.map((l) => [l, { public: [], internal: [] }]));
    const walk = (value, locale) => {
        if (Array.isArray(value)) return value.forEach((v) => walk(v, locale));
        if (value && typeof value === 'object') {
            const keys = Object.keys(value);
            if (keys.length && keys.every((k) => LOCALES.includes(k))) return keys.forEach((k) => walk(value[k], k));
            return Object.values(value).forEach((v) => walk(v, locale));
        }
        if (typeof value === 'string' && locale) copy[locale].public.push(value);
        return undefined;
    };
    const contentDir = path.join(ROOT, 'database', 'content');
    for (const file of readdirSync(contentDir).filter((f) => f.endsWith('.json')).sort()) {
        walk(JSON.parse(readFileSync(path.join(contentDir, file), 'utf8')));
    }
    const notes = readdirSync(path.join(ROOT, 'lang', 'en', 'styleguide_d2')).filter((f) => f.endsWith('.php')).sort()
        .flatMap((f) => strings(dumpPhp(path.join(ROOT, 'lang', 'en', 'styleguide_d2', f))));
    for (const locale of LOCALES) {
        const dir = path.join(ROOT, 'lang', locale);
        // proposed.php merges every proposed-d2/<area>.php under its "d2" key
        if (existsSync(path.join(dir, 'proposed.php'))) strings(dumpPhp(path.join(dir, 'proposed.php')), copy[locale].public);
        if (existsSync(path.join(dir, 'switcher.php'))) strings(dumpPhp(path.join(dir, 'switcher.php')), copy[locale].internal);
        copy[locale].internal.push(...notes); // styleguide annotations are English on every page
    }
    return copy;
}

function countChars(texts) {
    const counts = new Map();
    for (const text of texts) for (const ch of text) if (!isControl(ch)) counts.set(ch, (counts.get(ch) ?? 0) + 1);
    return [...counts].sort((a, b) => a[0].codePointAt(0) - b[0].codePointAt(0));
}

// ------------------------------------------------------------------------------------------- the faces

/** Pair B: every candidate file of each family, in the package's CSS order. */
function pairB(packages) {
    const families = {};
    for (const [name, spec] of Object.entries(PAIR_B)) {
        const pkg = packages[spec.package];
        const members = [];
        for (const weight of spec.variable ? [null] : spec.weights) {
            for (const face of fontsourceFaces(path.join(pkg.dir, pkg.css.replace('{weight}', String(weight))))) {
                members.push({
                    key: face.file.replace(/\.woff2$/, ''),
                    source: path.join(pkg.dir, 'files', face.file),
                    sourceLabel: `${pkg.name}@${pkg.version}/files/${face.file}`,
                    subset: face.subset,
                    descriptor: spec.variable ? `${spec.weights[0]} ${spec.weights[spec.weights.length - 1]}` : String(weight),
                    weights: spec.variable ? spec.weights : [weight],
                    unicodeRange: face.unicodeRange,
                    ranges: parseRanges(face.unicodeRange),
                });
            }
        }
        families[name] = { pair: 'b', serves: spec.serves, weights: spec.weights, members, label: `${pkg.name} ${pkg.version} (${pkg.metadata.family} ${pkg.metadata.version})` };
    }
    return families;
}

/** Pair A: licensed Graphik files in resources/fonts/graphik/ (none until the licence exists). */
function pairA() {
    if (!existsSync(GRAPHIK_DIR)) return {};
    const weightOf = { regular: 400, medium: 500, semibold: 600 };
    const families = {};
    for (const file of readdirSync(GRAPHIK_DIR).sort()) {
        const m = /^Graphik(Arabic)?[-_ ]?(Regular|Medium|Semi-?bold)(?![a-z]).*\.woff2$/i.exec(file);
        if (!m) continue;
        const name = m[1] ? 'D2 Graphik Arabic' : 'D2 Graphik';
        const weight = weightOf[m[2].toLowerCase().replace('-', '')];
        families[name] ??= { pair: 'a', serves: m[1] ? ['ar'] : ['en', 'ru'], weights: [], members: [], label: 'Commercial Type, licensed (resources/fonts/graphik/)' };
        if (families[name].weights.includes(weight)) fail(`${rel(GRAPHIK_DIR)}: two files for ${name} ${weight}`);
        families[name].weights.push(weight);
        families[name].members.push({
            key: `${m[1] ? 'graphik-arabic' : 'graphik'}-${weight}`,
            source: path.join(GRAPHIK_DIR, file),
            sourceLabel: `resources/fonts/graphik/${file}`,
            subset: null,
            descriptor: String(weight),
            weights: [weight],
            unicodeRange: null, // the file's own character map (below)
            ranges: null,
        });
    }
    for (const family of Object.values(families)) {
        family.weights.sort((a, b) => a - b);
        family.members.sort((a, b) => a.weights[0] - b.weights[0]);
    }
    return families;
}

/** The sample-document files in public/fonts/d2 (scripts/fonts/build-d2.py), by key. */
function documentFiles() {
    const keys = new Set(Object.values(DOC_FAMILIES).flat().map(([key]) => key));
    const found = {};
    for (const file of existsSync(SOURCE_FONT_DIR) ? readdirSync(SOURCE_FONT_DIR).sort() : []) {
        const m = /^([a-z0-9-]+)\.([0-9a-f]{8})\.woff2$/.exec(file);
        if (!m || !keys.has(m[1])) continue;
        if (found[m[1]]) fail(`${rel(SOURCE_FONT_DIR)}: two files for ${m[1]}`);
        found[m[1]] = path.join(SOURCE_FONT_DIR, file);
    }
    for (const key of keys) if (!found[key]) fail(`the sample-document face ${key} is missing from ${rel(SOURCE_FONT_DIR)}`);
    return found;
}
const docRangeName = (key) => (key.includes('arabic') ? 'arabic' : key.includes('cyrillic') ? 'cyrillic' : 'latin');

// ------------------------------------------------------------------------------------------- 1. inputs

const packages = { onest: readPackage('onest'), plex: readPackage('plex') };
const copy = collectCopy();
const families = { ...pairB(packages), ...pairA() };
const graphik = Boolean(families['D2 Graphik'] || families['D2 Graphik Arabic']);
if (graphik && !(families['D2 Graphik'] && families['D2 Graphik Arabic'])) {
    fail(`${rel(GRAPHIK_DIR)}: pair A needs both Graphik-* (Latin) and GraphikArabic-* (Arabic) files`);
}
const docFiles = documentFiles();
const allFiles = { ...docFiles };
for (const family of Object.values(families)) for (const m of family.members) allFiles[m.key] = m.source;

// character maps and vertical metrics of every candidate file (fontTools)
const probe = python({ files: allFiles, texts: {}, fallbacks: [] }).files;
for (const family of Object.values(families)) {
    for (const m of family.members) {
        m.cmap = new Set(probe[m.key].cmap);
        if (!m.ranges) { // Graphik: what the file draws, without control characters
            m.ranges = pointsToRanges([...m.cmap].filter((cp) => cp >= 0x20 && !(cp >= 0x7f && cp <= 0x9f)));
            m.unicodeRange = formatRanges(m.ranges);
        }
    }
}

// ------------------------------------------------------------------------------------------- 2. coverage

/** The file a browser uses for `cp` in `stack`: the first family whose file for the range has the glyph. */
function drawnBy(cp, stack, weight = null) {
    for (const name of stack) {
        const family = families[name];
        if (!family) continue;
        // within a family the rule written last is checked first
        const member = [...family.members].reverse().find((m) => (weight === null || m.weights.includes(weight)) && inRanges(cp, m.ranges));
        if (member && member.cmap.has(cp)) return { family: name, member };
    }
    return null;
}

const pairs = graphik ? ['b', 'a'] : ['b'];
const needed = new Set(); // member keys the public copy needs
Object.assign(families, buildAliases(families));
for (const pair of pairs) {
    for (const locale of LOCALES) {
        for (const [ch] of countChars(copy[locale].public)) {
            const hit = drawnBy(ch.codePointAt(0), STACKS[pair][locale]);
            if (!hit) continue;
            // every weight of that subset (one variable file, or the static file of each weight), in the source family
            const subset = hit.member.subset;
            for (const m of families[families[hit.family].alias ?? hit.family].members) {
                if (subset ? m.subset === subset : m.key === hit.member.key) needed.add(m.key);
            }
        }
    }
}
for (const name of Object.keys(ALIASES)) delete families[name];
for (const family of Object.values(families)) family.members = family.members.filter((m) => needed.has(m.key) || family.pair === 'a');
Object.assign(families, buildAliases(families));

const report = { generator: 'scripts/fonts/fontsource-d2.mjs', coverage: {}, fallbacks: {}, files: {} };
let missing = 0;
for (const pair of pairs) {
    for (const locale of LOCALES) {
        const entry = { stack: STACKS[pair][locale], files: {}, missing: [], internalMissing: [] };
        const publicChars = countChars(copy[locale].public);
        const publicSet = new Set(publicChars.map(([ch]) => ch));
        for (const [ch, n] of publicChars) {
            const hit = drawnBy(ch.codePointAt(0), STACKS[pair][locale]);
            const where = hit && `${hit.family} ${hit.member.subset ?? hit.member.key}`;
            if (hit) entry.files[where] = (entry.files[where] ?? 0) + n;
            else entry.missing.push(charName(ch, n));
        }
        for (const [ch, n] of countChars(copy[locale].internal)) {
            if (!publicSet.has(ch) && !drawnBy(ch.codePointAt(0), STACKS[pair][locale])) entry.internalMissing.push(charName(ch, n));
        }
        missing += entry.missing.length;
        report.coverage[`${pair}:${locale}`] = entry;
        console.log(`  pair ${pair} ${locale}: ${Object.entries(entry.files).map(([k, n]) => `${k} (${n})`).join(', ')}`
            + (entry.missing.length ? `\n    NOT DRAWN: ${entry.missing.join('; ')}` : '; every character of the copy is drawn')
            + (entry.internalMissing.length ? `\n    internal labels in a system font: ${entry.internalMissing.join('; ')}` : ''));
    }
}
if (missing && argv['allow-missing'] !== 'true') {
    fail('characters of the copy that no font file draws: widen the subsets deliberately, or pass --allow-missing');
}

// ------------------------------------------------------------------------------------------- 3. fallbacks

const texts = Object.fromEntries(LOCALES.map((l) => [l, copy[l].public]));
const jobs = [];
const fallbackFamilies = []; // [name, [{ id, weight, unicodeRange, locals, part }]] in CSS order
/** the members of a family at one weight, in the order a browser checks them */
const checkOrder = (family, weight) => [...family.members].filter((m) => m.weights.includes(weight)).reverse()
    .map((m) => ({ key: m.key, ranges: m.ranges }));
const rangesOf = (members) => members.flatMap((m) => m.ranges);

for (const [name, family] of Object.entries(families)) {
    if (family.alias) continue; // below, from the source family's measurements
    const faces = [];
    for (const weight of family.weights) {
        const members = checkOrder(family, weight);
        if (!family.serves.includes('ar')) {
            const id = `${name} Fallback|${weight}`;
            jobs.push({ id, face: members, weight, kind: 'chars', locales: family.serves, ranges: rangesOf(members), locals: latinLocals(weight) });
            faces.push({ id, weight, part: 'text', locals: latinLocals(weight), unicodeRange: formatRanges(rangesOf(members)) });
            continue;
        }
        // Arabic families: the Arabic letters, the Latin of Arabic pages, and the space alone (written last, so a
        // browser checks it first for U+0020 and U+00A0)
        const arabic = members.filter((m) => inRanges(0x0627, m.ranges));
        const latin = members.filter((m) => !inRanges(0x0627, m.ranges) && inRanges(0x41, m.ranges));
        const parts = [
            ['arabic', arabic, 'arabic', arabicLocals(weight), rangesOf(arabic), rangesOf(arabic)],
            ['latin', latin, 'chars', latinLocals(weight), subtract(rangesOf(latin), SPACE), rangesOf(latin)],
            ['space', latin, 'space', latinLocals(weight), SPACE, SPACE],
        ];
        for (const [part, face, kind, locals, sample, declared] of parts) {
            if (!face.length) continue;
            const id = `${name} Fallback|${weight}|${part}`;
            jobs.push({ id, face, weight, kind, locales: ['ar'], ranges: sample, locals });
            faces.push({ id, weight, part, locals, unicodeRange: formatRanges(declared) });
        }
    }
    const order = { arabic: 0, text: 0, latin: 1, space: 2 };
    fallbackFamilies.push([`${name} Fallback`, faces.sort((a, b) => order[a.part] - order[b.part] || a.weight - b.weight)]);
}
// the script-only aliases: the source family's fallback for that script, cut to the alias's ranges
for (const [name, family] of Object.entries(families)) {
    if (!family.alias) continue;
    const faces = family.weights.map((weight) => {
        const ranges = rangesOf(checkOrder(family, weight));
        const id = family.part === 'arabic' ? `${family.alias} Fallback|${weight}|arabic` : `${family.alias} Fallback|${weight}`;
        const locals = family.part === 'arabic' ? arabicLocals(weight) : latinLocals(weight);
        return { id, weight, part: family.part, locals, unicodeRange: formatRanges(ranges) };
    }).filter((f) => f.unicodeRange);
    fallbackFamilies.push([`${name} Fallback`, faces]);
}
// sample documents
const docMembers = (keys) => keys.map((key) => ({ key, ranges: parseRanges(DOC_RANGES[docRangeName(key)]) }));
const latinCyrillic = `${DOC_RANGES.latin}, ${DOC_RANGES.cyrillic}`;
const docPlan = [
    ['D2 Mono Fallback', [
        [400, 'text', docMembers(['mono-cyrillic-400', 'mono-latin-400']), 'chars', ['en', 'ru'], MENLO, latinCyrillic],
        [500, 'text', docMembers(['mono-cyrillic', 'mono-latin']), 'chars', ['en', 'ru'], MENLO, latinCyrillic],
    ]],
    ['D2 Serif Fallback', [
        [400, 'text', docMembers(['serif-cyrillic', 'serif-latin']), 'chars', ['en', 'ru'], GEORGIA, latinCyrillic],
        [600, 'text', docMembers(['serif-cyrillic', 'serif-latin']), 'chars', ['en', 'ru'], GEORGIA_BOLD, latinCyrillic],
    ]],
    ['D2 Naskh Fallback', [
        [400, 'latin', docMembers(['serif-cyrillic', 'serif-latin']), 'chars', ['en', 'ru'], GEORGIA, latinCyrillic],
        [600, 'latin', docMembers(['serif-cyrillic', 'serif-latin']), 'chars', ['en', 'ru'], GEORGIA_BOLD, latinCyrillic],
        [400, 'arabic', docMembers(['naskh-arabic']), 'arabic', ['ar'], TIMES, 'U+0600-06FF, U+0750-077F, U+08A0-08FF, U+FB50-FDFF, U+FE70-FEFF, U+200C-200F'],
        [600, 'arabic', docMembers(['naskh-arabic']), 'arabic', ['ar'], TIMES_BOLD, 'U+0600-06FF, U+0750-077F, U+08A0-08FF, U+FB50-FDFF, U+FE70-FEFF, U+200C-200F'],
    ]],
];
for (const [name, rows] of docPlan) {
    const faces = rows.map(([weight, part, face, kind, locales, locals, unicodeRange]) => {
        const id = `${name}|${weight}|${part}`;
        jobs.push({ id, face, weight, kind, locales, ranges: rangesOf(face), locals });
        return { id, weight, part, locals, unicodeRange };
    });
    fallbackFamilies.push([name, faces]);
}
const measured = python({ files: allFiles, texts, fallbacks: jobs }).fallbacks;
for (const job of jobs) {
    const m = measured[job.id];
    report.fallbacks[job.id] = { ...m, locals: job.locals };
    console.log(`  ${job.id.padEnd(38)} size-adjust ${pct(m.sizeAdjust).padStart(8)} vs ${m.tunedFor}`
        + (Object.keys(m.alternatives).length ? ` (others: ${Object.entries(m.alternatives).map(([k, v]) => `${k} ${pct(v)}`).join(', ')})` : ''));
}

// ------------------------------------------------------------------------------------------- 4. files, manifest, CSS

const outputs = new Map(); // absolute path -> Buffer
const urls = {};
const manifest = {
    generator: 'scripts/fonts/fontsource-d2.mjs',
    note: 'Generated: do not edit. Every file is copied unmodified; sha256 is the source file\'s and the copy\'s.',
    packages: Object.fromEntries(Object.values(packages).map((p) => [p.name, {
        version: p.version, license: p.license, font: `${p.metadata.family} ${p.metadata.version}`,
        attribution: p.metadata.license?.attribution?.split('. ')[0] ?? '', licenceFile: URL_PREFIX + p.licence,
    }])),
    files: {},
};
for (const [name, family] of Object.entries(families)) {
    if (family.alias) continue; // same files as the source family
    for (const m of family.members) {
        const data = readFileSync(m.source);
        const digest = sha256(data);
        const file = `${m.key}.${digest.slice(0, 8)}.woff2`;
        outputs.set(path.join(FONT_DIR, file), data);
        urls[m.key] = URL_PREFIX + file;
        manifest.files[m.key] = { url: URL_PREFIX + file, family: name, weight: m.descriptor, unicodeRange: m.unicodeRange, source: m.sourceLabel, sha256: digest, bytes: data.length };
    }
}
for (const [key, file] of Object.entries(docFiles)) {
    const data = readFileSync(file);
    const owners = Object.entries(DOC_FAMILIES).filter(([, rows]) => rows.some(([k]) => k === key));
    urls[key] = URL_PREFIX + path.basename(file);
    if (FONT_DIR !== SOURCE_FONT_DIR) outputs.set(path.join(FONT_DIR, path.basename(file)), data);
    manifest.files[key] = {
        url: urls[key], family: owners.map(([f]) => f).join(', '), weight: owners[0][1].find(([k]) => k === key)[1],
        unicodeRange: DOC_RANGES[docRangeName(key)], source: 'scripts/fonts/build-d2.py (sample documents only)', sha256: sha256(data), bytes: data.length,
    };
}
manifest.files = Object.fromEntries(Object.entries(manifest.files).sort(([a], [b]) => a.localeCompare(b)));
for (const p of Object.values(packages)) outputs.set(path.join(FONT_DIR, p.licence), readFileSync(path.join(p.dir, 'LICENSE')));
for (const [file, source] of Object.entries(DOC_LICENCES)) {
    if (existsSync(path.join(ROOT, source))) outputs.set(path.join(FONT_DIR, file), readFileSync(path.join(ROOT, source)));
}
if (graphik) {
    for (const file of readdirSync(GRAPHIK_DIR).filter((f) => /(licen[cs]e|eula)/i.test(f) && /\.(txt|md|pdf|html?)$/i.test(f)).sort()) {
        outputs.set(path.join(FONT_DIR, `LICENSE-graphik-${file.replace(/[^A-Za-z0-9.-]+/g, '-')}`), readFileSync(path.join(GRAPHIK_DIR, file)));
    }
}
outputs.set(MANIFEST_PATH, Buffer.from(`${JSON.stringify(manifest, null, 2)}\n`));
outputs.set(CSS_PATH, Buffer.from(stylesheet()));
report.files = manifest.files;

// ------------------------------------------------------------------------------------------- 5. write or check

const changed = [...outputs].filter(([file, data]) => !existsSync(file) || !readFileSync(file).equals(data)).map(([file]) => file);
const keep = new Set([...outputs.keys()].filter((f) => path.dirname(f) === FONT_DIR).map((f) => path.basename(f)));
if (FONT_DIR === SOURCE_FONT_DIR) for (const file of Object.values(docFiles)) keep.add(path.basename(file));
const extra = existsSync(FONT_DIR) ? readdirSync(FONT_DIR).filter((f) => /\.(woff2?|ttf|otf)$/i.test(f) && !keep.has(f)).sort() : [];
if (CHECK) {
    const stale = [...changed.map(rel), ...extra.map((f) => `${rel(path.join(FONT_DIR, f))} (no longer declared)`)];
    if (stale.length) fail(`stale outputs (run node scripts/fonts/fontsource-d2.mjs):\n  ${stale.join('\n  ')}`);
    console.log('fontsource-d2: every output is up to date');
    process.exit(0);
}
for (const file of changed) {
    mkdirSync(path.dirname(file), { recursive: true });
    writeFileSync(file, outputs.get(file));
}
for (const file of extra) {
    rmSync(path.join(FONT_DIR, file));
    console.log(`  removed ${rel(path.join(FONT_DIR, file))}`);
}
mkdirSync(path.dirname(REPORT_PATH), { recursive: true });
writeFileSync(REPORT_PATH, `${JSON.stringify(report, null, 2)}\n`);
for (const f of Object.values(manifest.files)) console.log(`  ${f.url.padEnd(66)} ${(f.bytes / 1024).toFixed(1).padStart(6)} KB  ${f.family} ${f.weight}`);
console.log(`fontsource-d2: ${changed.length} file(s) written, ${extra.length} removed; report ${rel(REPORT_PATH)}`);

// ------------------------------------------------------------------------------------------- the stylesheet

function stylesheet() {
    const lines = [
        '/*',
        ' * Design 2 web fonts. GENERATED by scripts/fonts/fontsource-d2.mjs: do not edit by hand.',
        ' * Contract: docs/d2.1/phase-1/tokens.md §5. App\\Design\\D2\\FontFaces inlines these rules in the page head',
        ' * (grouped per locale; never bundled into app.css), so the /fonts/d2/ URLs resolve against the page origin',
        ' * behind the Vite dev server too. Style with the tokens (--font-sans; --font-doc-* inside sample documents',
        ' * only), never with these family names.',
        ' *',
        ` *   D2 Sans          ${families['D2 Sans'].label}: Latin and Cyrillic, English and`,
        ' *                    Russian pages and the Cyrillic of Arabic pages. Variable, declared for weights 400 to 600.',
        ` *   D2 Sans Arabic   ${families['D2 Sans Arabic'].label}: Arabic pages,`,
        ' *                    their Latin runs (digits, spaces, codes, e-mail) included, from the same family. 400/500/600.',
        graphik
            ? ' *   D2 Graphik, D2 Graphik Arabic   Commercial Type, licensed (resources/fonts/graphik/): pair A.'
            : ' *   (Pair A, D2 Graphik and D2 Graphik Arabic, is declared here once licensed files are in resources/fonts/graphik/.)',
        ' *   D2 Sans Arabic Script, D2 Sans Cyrillic   script-only aliases of the two (same files): the other script in a',
        ' *                    page stack, so a stack never holds two families that cover the page\'s own text.',
        ' *   D2 Mono, D2 Serif, D2 Naskh   inside the sample-document illustrations only.',
        ' *',
        ' * The files are the packages\' WOFF2 files, unmodified (SHA-256 in public/fonts/d2/manifest.json), under the SIL',
        ' * Open Font License 1.1 (public/fonts/d2/LICENSE-*.txt). Faces of one family with the same weight descriptor form',
        ' * one composite face split by unicode-range; where ranges overlap the rule written last is checked first, so each',
        ' * package\'s own order is kept. "<family> Fallback" faces scale a local font (size-adjust) and carry the web font\'s',
        ' * ascent, descent and line gap, measured from the real files over the site copy, so the swap moves nothing;',
        ' * they are tuned for the first local() font (storage/app/d2/fonts/fontsource-d2.json lists the others).',
        ' */',
        '/* stylelint-disable declaration-property-value-allowed-list -- @font-face names its family and weight range */',
    ];
    const face = (family, weight, url, range, comment) => {
        lines.push('');
        if (comment) lines.push(`/* ${comment} */`);
        lines.push('@font-face {', `  font-family: "${family}";`, '  font-style: normal;', `  font-weight: ${weight};`,
            '  font-display: swap;', `  src: url("${url}") format("woff2");`, `  unicode-range: ${range};`, '}');
    };
    for (const [name, family] of Object.entries(families)) {
        lines.push('', family.alias
            ? `/* ${name}: the ${family.part === 'arabic' ? 'Arabic' : 'Cyrillic'} code points of ${family.alias} only (same files), for the other script in a page stack */`
            : `/* ${name}: ${family.label} */`);
        for (const m of family.members) face(name, m.descriptor, urls[m.key], m.unicodeRange, m.key);
    }
    lines.push('', '/* ---------------------------------------------- sample-document faces (never in the interface) */');
    for (const [name, rows] of Object.entries(DOC_FAMILIES)) {
        lines.push('', `/* ${name}: ${DOC_SOURCES[name]} */`);
        for (const [key, weight, range] of rows) face(name, weight, urls[key], DOC_RANGES[range]);
    }
    lines.push('', '/* ---------------------------------------------- metric-matched fallbacks */');
    for (const [name, faces] of fallbackFamilies) {
        lines.push('', `/* ${name} */`);
        for (const f of faces) {
            const m = measured[f.id];
            const what = { text: '', arabic: 'Arabic, ', latin: 'Latin, ', space: 'the space alone, ' }[f.part];
            lines.push('', `/* ${f.weight}: ${what}tuned for ${m.tunedFor} */`, '@font-face {', `  font-family: "${name}";`,
                '  font-style: normal;', `  font-weight: ${f.weight};`, `  src: ${f.locals.map((n) => `local("${n}")`).join(', ')};`,
                `  size-adjust: ${pct(m.sizeAdjust)};`, `  ascent-override: ${pct(m.ascentOverride)};`,
                `  descent-override: ${pct(m.descentOverride)};`, `  line-gap-override: ${pct(m.lineGapOverride)};`,
                `  unicode-range: ${f.unicodeRange};`, '}');
        }
    }
    return `${lines.join('\n')}\n`;
}
