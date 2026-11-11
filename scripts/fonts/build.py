"""Build Summit Legal's self-hosted web fonts.

Run from the project root (the venv only needs fonttools and brotli):

    .venv-fonts/bin/python -I scripts/fonts/build.py            # build everything
    .venv-fonts/bin/python -I scripts/fonts/build.py --fetch    # first download missing sources

Pipeline
  1. Verify the google/fonts sources in resources/fonts/src/<dir>/ against the git blob SHA-1s
     pinned below (--fetch downloads missing files from the pinned google/fonts commit).
  2. Make static instances: Source Serif 4 at its Display optical size (opsz 60), IBM Plex Sans at
     wdth 100, Noto Naskh Arabic; IBM Plex Sans Arabic is already static.
  3. Subset every instance into unicode-range groups (latin / cyrillic / arabic), keep the
     OpenType layout features needed for typesetting, drop hinting, rename the faces (OFL
     Reserved Font Names) and write WOFF2 files with content-hashed names to public/fonts/.
  4. Check that every character of the approved (black) DOCX copy is rendered by a Summit face.
  5. Compute metric-compatible fallback faces (size-adjust, ascent/descent/line-gap overrides)
     against the local system fonts in /System/Library/Fonts(/Supplemental).
  6. Write resources/scss/base/_fonts.scss, public/fonts/manifest.json,
     storage/app/qa/font-build.json and the generated block of docs/fonts.md.

The output is deterministic: the same sources produce byte-identical files and the same hashes.
"""

from __future__ import annotations

import argparse
import collections
import concurrent.futures
import dataclasses
import hashlib
import io
import json
import re
import subprocess
import sys
import unicodedata
import urllib.parse
import urllib.request
from pathlib import Path

from fontTools import subset as ftsubset
from fontTools.ttLib import TTCollection, TTFont, newTable
from fontTools.ttLib.tables import ttProgram
from fontTools.ttLib.woff2 import WOFF2FlavorData
from fontTools.varLib import instancer

ROOT = Path(__file__).resolve().parents[2]
SRC_DIR = ROOT / 'resources' / 'fonts' / 'src'
OUT_DIR = ROOT / 'public' / 'fonts'
SCSS_PATH = ROOT / 'resources' / 'scss' / 'base' / '_fonts.scss'
MANIFEST_PATH = OUT_DIR / 'manifest.json'
REPORT_PATH = ROOT / 'storage' / 'app' / 'qa' / 'font-build.json'
DOCS_PATH = ROOT / 'docs' / 'fonts.md'
DOCX_DIR = ROOT / 'package' / 'docx'
URL_PREFIX = '/fonts/'
LOCALES = ('en', 'ar', 'ru')
LOCAL_FONT_DIRS = (Path('/System/Library/Fonts'), Path('/System/Library/Fonts/Supplemental'))

# ---------------------------------------------------------------------------------------------
# Sources: google/fonts ofl/<dir>/<file>, pinned to one commit, verified by git blob SHA-1
# (the "sha" the GitHub contents API reports for each file).

GOOGLE_FONTS_COMMIT = '5bb28733cec313b63fbd415b574b5ae35c8aa8b8'  # google/fonts main, 2026-11-08
RAW_URL = 'https://raw.githubusercontent.com/google/fonts/{commit}/ofl/{dir}/{file}'
SOURCES: dict[str, dict[str, str]] = {
    'sourceserif4': {
        'SourceSerif4[opsz,wght].ttf': '40bbb58cb58e893ad0470f0bb291e096c73d3554',
        'OFL.txt': '318074ec9d43c936a56ee1dae92999f4df073ce8',
        'METADATA.pb': 'd11fa635f826bd90df219bf574e0fe83fff531a6',
        'upstream_info.md': '952918c4878f4b04a923805d4bbebe82ce6aba9a',
    },
    'ibmplexsans': {
        'IBMPlexSans[wdth,wght].ttf': '30f837e8b02e8ecddce6cbebc425b1f422392515',
        'OFL.txt': 'c35c4c618fab33da8695177b3a6cefe0810b7b28',
        'METADATA.pb': 'ccfb809905fe8f3133b5ea2937b8abdce1134f02',
        'upstream_info.md': '5980bcd5f887dfd4edd94b67ed718f05fe0bda68',
    },
    'ibmplexsansarabic': {
        'IBMPlexSansArabic-Regular.ttf': 'e779068718b9801b809605b2bf3067121cce524c',
        'IBMPlexSansArabic-Medium.ttf': 'e82313893b5b5b6af3e7429f5b798666f1e0b097',
        'IBMPlexSansArabic-SemiBold.ttf': 'f7a7fc87747a9ddabb3557d4a3f4140a7a1460b0',
        'OFL.txt': 'c35c4c618fab33da8695177b3a6cefe0810b7b28',
        'METADATA.pb': 'a11b215c4845702dc709482ee089e82cf4c16384',
        'upstream_info.md': '3c578755cbb11dd6c82cc93155ba483402b0e634',
    },
    'notonaskharabic': {
        'NotoNaskhArabic[wght].ttf': 'a8d2867262dc7bb28492ad746de5d56235735c8b',
        'OFL.txt': 'd4e170557fc8bcf8d1dcb317d9f1accaf8acf223',
        'METADATA.pb': '37dc7d09fcbf15c9f92f70bb6296b13c4ef27452',
        'upstream_info.md': '9637e7b1c0d6fc861a3232ee5394bc4db1021464',
    },
}

# ---------------------------------------------------------------------------------------------
# Unicode-range groups. Characters in the copy that fall outside these are reported (and the
# build fails) so that a range can be widened deliberately.

SUBSETS: dict[str, str] = {
    'latin': (
        'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, '
        'U+0329, U+2000-206F, U+20AC, U+2122, U+2190-2193, U+2212, U+2215, U+FEFF, U+FFFD'
    ),
    'cyrillic': 'U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116',
    'arabic': (
        'U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, '
        'U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC'
    ),
}

# OpenType layout features kept in every file (where the font has them). The first row is the
# typesetting set; the second row holds features the shaping engines apply automatically for
# the scripts we ship (required variation alternates, RTL mirrored forms, required contextual
# alternates), so dropping them would change default rendering.
LAYOUT_FEATURES = [
    'kern', 'liga', 'calt', 'ccmp', 'locl', 'mark', 'mkmk', 'rlig', 'init', 'medi', 'fina',
    'isol', 'curs', 'tnum', 'lnum', 'pnum', 'case',
    'rvrn', 'rtlm', 'rclt',
]

STYLE_NAMES = {400: 'Regular', 500: 'Medium', 600: 'SemiBold'}


@dataclasses.dataclass(frozen=True)
class Face:
    key: str                     # file / manifest prefix
    family: str                  # CSS family name, also written into the font's name table
    upstream: str                # upstream family name
    src_dir: str                 # resources/fonts/src/<dir>
    weights: dict                # weight -> (source file, axis location in user units)
    subsets: tuple               # unicode-range groups
    role: str


FACES: tuple[Face, ...] = (
    Face('serif', 'Summit Serif', 'Source Serif 4', 'sourceserif4',
         {w: ('SourceSerif4[opsz,wght].ttf', {'opsz': 60, 'wght': w}) for w in (400, 600)},
         ('latin', 'cyrillic'), 'Latin + Cyrillic display (Display optical size, opsz 60)'),
    Face('sans', 'Summit Sans', 'IBM Plex Sans', 'ibmplexsans',
         {w: ('IBMPlexSans[wdth,wght].ttf', {'wdth': 100, 'wght': w}) for w in (400, 500, 600)},
         ('latin', 'cyrillic'), 'Latin + Cyrillic text and UI'),
    Face('sans-arabic', 'Summit Sans Arabic', 'IBM Plex Sans Arabic', 'ibmplexsansarabic',
         {400: ('IBMPlexSansArabic-Regular.ttf', {}),
          500: ('IBMPlexSansArabic-Medium.ttf', {}),
          600: ('IBMPlexSansArabic-SemiBold.ttf', {})},
         ('arabic',), 'Arabic text and UI'),
    Face('naskh', 'Summit Naskh', 'Noto Naskh Arabic', 'notonaskharabic',
         {w: ('NotoNaskhArabic[wght].ttf', {'wght': w}) for w in (400, 600)},
         ('arabic',), 'Arabic display'),
)
FACE = {f.key: f for f in FACES}

# Which faces render which text, in font-stack order (mirrors resources/scss/tokens/_tokens.scss):
# a character is drawn by the first face whose unicode-range includes it and whose file has it.
STACKS = {
    'en': {'display': ('serif',), 'text': ('sans',)},
    'ru': {'display': ('serif',), 'text': ('sans',)},
    'ar': {'display': ('naskh', 'serif'), 'text': ('sans-arabic', 'sans')},
}

# The one display-font file each language page preloads.
PRELOAD = {'en': 'serif-400-latin', 'ru': 'serif-400-cyrillic', 'ar': 'naskh-400-arabic'}


@dataclasses.dataclass(frozen=True)
class LocalFont:
    name: str                    # the name as requested (first local() source)
    aliases: tuple = ()          # extra local() names: full name / PostScript name variants


@dataclasses.dataclass(frozen=True)
class Fallback:
    family: str
    web: str                     # Face key whose metrics the fallback imitates
    sample: str                  # 'display' (heading blocks) or 'text' (all other blocks)
    locales: tuple               # copy used for the average advance width
    weights: dict                # weight -> tuple[LocalFont, ...]; overrides are tuned for [0]
    restrict: bool = False       # limit the face to its web face's unicode-range
    also: tuple = ()             # (face key, sample) the fallback also stands in for: reported only


# Chrome matches local() against the full name or the PostScript name only (tested on macOS:
# local('Geeza Pro') does not resolve, local('Geeza Pro Regular') and local('GeezaPro') do), so
# each requested name is followed by its full-name / PostScript aliases.
_GEORGIA = LocalFont('Georgia')
_GEORGIA_B = LocalFont('Georgia Bold', ('Georgia-Bold',))
_TIMES = LocalFont('Times New Roman', ('TimesNewRomanPSMT',))
_TIMES_B = LocalFont('Times New Roman Bold', ('TimesNewRomanPS-BoldMT',))
_ARIAL = LocalFont('Arial', ('ArialMT',))
_ARIAL_B = LocalFont('Arial Bold', ('Arial-BoldMT',))
_HELVETICA = LocalFont('Helvetica Neue', ('HelveticaNeue',))
_HELVETICA_B = LocalFont('Helvetica Neue Bold', ('HelveticaNeue-Bold',))
_GEEZA = LocalFont('Geeza Pro', ('Geeza Pro Regular', 'GeezaPro'))
_GEEZA_B = LocalFont('Geeza Pro Bold', ('GeezaPro-Bold',))
_SEGOE = LocalFont('Segoe UI', ('SegoeUI',))
_SEGOE_B = LocalFont('Segoe UI Bold', ('SegoeUI-Bold',))
_TAHOMA = LocalFont('Tahoma')
_TAHOMA_B = LocalFont('Tahoma Bold', ('Tahoma-Bold',))

FALLBACKS: tuple[Fallback, ...] = (
    Fallback('Summit Serif Fallback', 'serif', 'display', ('en', 'ru'), {
        400: (_GEORGIA, _TIMES),
        600: (_GEORGIA_B, _TIMES_B),
    }),
    Fallback('Summit Sans Fallback', 'sans', 'text', ('en', 'ru'), {
        400: (_ARIAL, _HELVETICA),
        500: (_ARIAL, _HELVETICA),
        600: (_ARIAL_B, _HELVETICA_B),
    }),
    # Arabic only: Latin, digits and spaces on Arabic pages must not be drawn by a face whose
    # size-adjust was tuned for Arabic letters; they fall through to the next family instead.
    Fallback('Summit Arabic Fallback', 'sans-arabic', 'text', ('ar',), {
        400: (_GEEZA, _SEGOE, _TAHOMA, _ARIAL),
        500: (_GEEZA, _SEGOE, _TAHOMA, _ARIAL),
        600: (_GEEZA_B, _SEGOE_B, _TAHOMA_B, _ARIAL_B),
    }, restrict=True, also=(('naskh', 'display'),)),
)

GENERATED_START = '<!-- BEGIN GENERATED BY scripts/fonts/build.py -->'
GENERATED_END = '<!-- END GENERATED BY scripts/fonts/build.py -->'


# ---------------------------------------------------------------------------------------------
# helpers

def log(msg: str) -> None:
    print(msg, flush=True)


def fail(msg: str) -> None:
    print(f'ERROR: {msg}', file=sys.stderr, flush=True)
    sys.exit(1)


def git_blob_sha1(data: bytes) -> str:
    return hashlib.sha1(b'blob %d\0' % len(data) + data).hexdigest()


def parse_ranges(spec: str) -> set[int]:
    cps: set[int] = set()
    for part in spec.split(','):
        m = re.fullmatch(r'U\+([0-9A-F]{1,6})(?:-([0-9A-F]{1,6}))?', part.strip().upper())
        if not m:
            raise ValueError(f'bad unicode-range part: {part!r}')
        lo = int(m.group(1), 16)
        hi = int(m.group(2) or m.group(1), 16)
        cps.update(range(lo, hi + 1))
    return cps


RANGES = {name: parse_ranges(spec) for name, spec in SUBSETS.items()}


def u(cp: int) -> str:
    return f'U+{cp:04X}'


def pct(value: float) -> str:
    text = f'{value * 100:.2f}'.rstrip('0').rstrip('.')
    return f'{text}%'


def kb(n: int) -> str:
    return f'{n / 1024:.1f}'


def logical_name(face: Face, weight: int, subset: str) -> str:
    return f'{face.key}-{weight}-{subset}'


# ---------------------------------------------------------------------------------------------
# 1. sources

def verify_sources(fetch: bool) -> None:
    for d, files in SOURCES.items():
        for name, sha in files.items():
            path = SRC_DIR / d / name
            if not path.exists():
                if not fetch:
                    fail(f'missing source {path.relative_to(ROOT)} (run with --fetch)')
                url = RAW_URL.format(commit=GOOGLE_FONTS_COMMIT, dir=d, file=urllib.parse.quote(name))
                log(f'  fetch {url}')
                with urllib.request.urlopen(url, timeout=300) as resp:
                    data = resp.read()
                if git_blob_sha1(data) != sha:
                    fail(f'{url}: checksum mismatch')
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_bytes(data)
            data = path.read_bytes()
            if git_blob_sha1(data) != sha:
                fail(f'{path.relative_to(ROOT)}: git blob SHA-1 {git_blob_sha1(data)} != pinned {sha}')
    log('  sources verified against google/fonts@' + GOOGLE_FONTS_COMMIT[:12])


def font_version(font: TTFont) -> str:
    raw = font['name'].getDebugName(5) or ''
    m = re.search(r'(\d+\.\d+)', raw)
    return m.group(1) if m else f"{font['head'].fontRevision:.3f}"


# ---------------------------------------------------------------------------------------------
# 2. instances

def make_instance(face: Face, weight: int) -> tuple[TTFont, dict, bytes]:
    """Static instance (font, axis location used, serialised bytes)."""
    file, location = face.weights[weight]
    font = TTFont(SRC_DIR / face.src_dir / file, recalcTimestamp=False)
    used: dict = {}
    if 'fvar' in font:
        for axis in font['fvar'].axes:
            value = location.get(axis.axisTag, axis.defaultValue)
            if not axis.minValue <= value <= axis.maxValue:
                fail(f'{file}: {axis.axisTag}={value} outside {axis.minValue}..{axis.maxValue}')
            used[axis.axisTag] = value
        font = instancer.instantiateVariableFont(font, used, inplace=False, optimize=True,
                                                 updateFontNames=False)
    elif location:
        fail(f'{file} is static but an axis location was requested: {location}')
    # round-trip through bytes so every subset starts from an identical, normalised instance
    buf = io.BytesIO()
    font.save(buf)
    data = buf.getvalue()
    return TTFont(io.BytesIO(data), recalcTimestamp=False), used, data


# ---------------------------------------------------------------------------------------------
# 3. subsets

def subset_options() -> ftsubset.Options:
    opts = ftsubset.Options()
    opts.layout_features = list(LAYOUT_FEATURES)
    opts.layout_scripts = ['*']
    opts.hinting = False
    opts.legacy_kern = False
    opts.glyph_names = False
    opts.name_IDs = [0, 1, 2, 3, 4, 5, 6, 13, 14]       # copyright and licence stay
    opts.name_languages = [0x0409]
    opts.name_legacy = False
    opts.notdef_outline = False
    opts.recalc_bounds = True
    opts.recalc_timestamp = False
    opts.drop_tables = sorted(set(opts.drop_tables) | {'STAT', 'meta', 'BASE', 'DSIG', 'MVAR', 'HVAR'})
    return opts


def set_names(font: TTFont, face: Face, weight: int, version: str) -> None:
    """Rename to the Summit family: IBM Plex ("Plex") and Source Serif ("Source") have OFL
    Reserved Font Names, which a Modified Version (instanced + subset) must not use."""
    name = font['name']
    style = STYLE_NAMES[weight]
    ps_name = face.family.replace(' ', '') + '-' + style
    name.names = [r for r in name.names if r.nameID in (0, 5, 13, 14)]
    legacy_family = face.family if weight == 400 else f'{face.family} {style}'
    records = {
        1: legacy_family,
        2: 'Regular',
        3: f'{version};SUMMIT;{ps_name}',
        4: f'{face.family} {style}',
        6: ps_name,
    }
    if weight != 400:
        records[16] = face.family
        records[17] = style
    for name_id, text in records.items():
        name.setName(text, name_id, 3, 1, 0x409)
    name.names.sort(key=lambda r: (r.platformID, r.platEncID, r.langID, r.nameID))

    os2 = font['OS/2']
    os2.usWeightClass = weight
    os2.fsSelection = (os2.fsSelection & ~0b1100001) | (1 << 6)   # REGULAR; clear ITALIC/BOLD
    font['head'].macStyle &= ~0b11


def set_unhinted(font: TTFont) -> None:
    """Same unhinted state the upstream Source Serif 4 / Noto files ship with (gftools
    fix-unhinted): smooth at all sizes, dropout control on, no glyph programs."""
    gasp = newTable('gasp')
    gasp.version = 1
    gasp.gaspRange = {0xFFFF: 0x000F}
    font['gasp'] = gasp
    program = ttProgram.Program()
    program.fromAssembly(['PUSHW[]', '511', 'SCANCTRL[]', 'PUSHB[]', '4', 'SCANTYPE[]'])
    prep = newTable('prep')
    prep.program = program
    font['prep'] = prep
    for tag in ('fpgm', 'cvt ', 'cvar', 'hdmx', 'VDMX', 'LTSH'):
        if tag in font:
            del font[tag]


def features_of(font: TTFont) -> list[str]:
    tags: set[str] = set()
    for t in ('GSUB', 'GPOS'):
        if t in font and font[t].table.FeatureList:
            tags.update(fr.FeatureTag for fr in font[t].table.FeatureList.FeatureRecord)
    return sorted(tags)


def to_woff2(font: TTFont) -> bytes:
    font.flavor = 'woff2'
    font.flavorData = WOFF2FlavorData(transformedTables={'glyf', 'loca', 'hmtx'})
    font.recalcTimestamp = False
    buf = io.BytesIO()
    font.save(buf)
    return buf.getvalue()


def build_files(instances: dict) -> list[dict]:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    files = []
    for face in FACES:
        for weight in face.weights:
            inst, location, source_bytes = instances[(face.key, weight)]
            version = font_version(inst)
            cmap = inst.getBestCmap()
            for sub in face.subsets:
                unicodes = sorted(set(cmap) & RANGES[sub])
                font = TTFont(io.BytesIO(source_bytes), recalcTimestamp=False)
                subsetter = ftsubset.Subsetter(subset_options())
                subsetter.populate(unicodes=unicodes)
                subsetter.subset(font)
                set_names(font, face, weight, version)
                set_unhinted(font)
                feats = features_of(font)
                kept_cmap = sorted(font.getBestCmap())
                glyphs = len(font.getGlyphOrder())
                data = to_woff2(font)
                digest = hashlib.sha256(data).hexdigest()[:8]
                logical = logical_name(face, weight, sub)
                filename = f'{logical}.{digest}.woff2'
                (OUT_DIR / filename).write_bytes(data)
                files.append({
                    'name': logical, 'file': filename, 'url': URL_PREFIX + filename,
                    'face': face.key, 'family': face.family, 'upstream': face.upstream,
                    'version': version, 'weight': weight, 'subset': sub, 'location': location,
                    'bytes': len(data), 'glyphs': glyphs, 'codepoints': len(kept_cmap),
                    'features': feats, 'cmap': kept_cmap,
                })
                log(f'  {filename:38s} {kb(len(data)):>6s} KB  {glyphs:4d} glyphs  {len(kept_cmap):4d} code points')
    remove_stale(files)
    return files


def remove_stale(files: list[dict]) -> None:
    keep = {f['file'] for f in files}
    pattern = re.compile(r'^(?:%s)-\d{3}-(?:%s)\.[0-9a-f]{8}\.woff2$' % (
        '|'.join(re.escape(f.key) for f in FACES), '|'.join(SUBSETS)))
    for path in OUT_DIR.iterdir():
        if pattern.match(path.name) and path.name not in keep:
            path.unlink()
            log(f'  removed stale {path.name}')


# ---------------------------------------------------------------------------------------------
# 4. approved copy and coverage

PHP_FALLBACK = (
    'require $argv[1] . "/vendor/autoload.php";'
    '$doc = (new App\\Content\\Docx\\DocxReader)->read($argv[2], $argv[3]);'
    'echo json_encode(array_map(fn ($b) => $b->toArray(), $doc->blocks),'
    ' JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);'
)


def dump_docx(locale: str, path: Path) -> tuple[list[dict], str, list[str]]:
    """Black-text blocks of one DOCX via `php artisan summit:docx-dump --json`, cross-checked
    against App\\Content\\Docx\\DocxReader called directly (the class the command wraps).

    The console route is not byte-exact: Laravel's Command::line() collapses runs of whitespace
    and shortens "..." to "..", so when the two disagree the reader's text is used and the
    difference is reported. The reader alone is used when the console app cannot boot."""
    rel = path.relative_to(ROOT).as_posix()
    proc = subprocess.run(['php', 'artisan', 'summit:docx-dump', '--json', rel, f'--locale={locale}'],
                          cwd=ROOT, capture_output=True, text=True)
    artisan = None
    if proc.returncode == 0:
        try:
            artisan = json.loads(proc.stdout)
        except json.JSONDecodeError:
            artisan = None
    direct = subprocess.run(['php', '-r', PHP_FALLBACK, str(ROOT), str(path), locale],
                            cwd=ROOT, capture_output=True, text=True)
    if direct.returncode != 0:
        if artisan is None:
            fail(f'cannot read {rel}: {direct.stderr.strip()[:400]}')
        return artisan, 'artisan', []
    reader = json.loads(direct.stdout)
    if artisan is None:
        return reader, 'DocxReader (artisan could not boot)', []
    diffs = []
    by_index = {b.get('i'): b.get('text', '') for b in artisan}
    for block in reader:
        theirs = by_index.get(block.get('i'), '')
        if block.get('text', '') != theirs:
            diffs.append(f"{rel} block {block.get('i')}: artisan {theirs[-40:]!r} != reader {block.get('text', '')[-40:]!r}")
    return reader, ('artisan, differs from DocxReader: reader text used' if diffs else 'artisan'), diffs


def collect_copy() -> dict:
    jobs = [(loc, p) for loc in LOCALES for p in sorted((DOCX_DIR / loc).glob('*.docx'))]
    if not jobs:
        fail(f'no DOCX files under {DOCX_DIR.relative_to(ROOT)}')
    copy = {loc: {'files': [], 'display': [], 'text': []} for loc in LOCALES}
    methods = collections.Counter()
    diffs: list[str] = []
    with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
        results = list(pool.map(lambda job: (job, dump_docx(*job)), jobs))
    for (loc, path), (blocks, method, file_diffs) in results:
        methods[method] += 1
        diffs.extend(file_diffs)
        copy[loc]['files'].append(path.name)
        for block in blocks:
            text = block.get('text', '')
            if text:
                copy[loc]['display' if block.get('h') else 'text'].append(text)
    log(f'  read {len(jobs)} DOCX files: ' + '; '.join(f'{n} {m}' for m, n in methods.items()))
    for d in diffs:
        log(f'  note: {d}')
    copy['_methods'] = {'methods': dict(methods), 'artisan_differences': diffs}
    return copy


def is_format_control(cp: int) -> bool:
    return unicodedata.category(chr(cp)) in ('Cc', 'Cf') or cp in (0x2028, 0x2029)


def check_coverage(copy: dict, files: list[dict]) -> dict:
    cmaps: dict = collections.defaultdict(dict)       # face -> subset -> set(cp) (all weights)
    for f in files:
        cps = set(f['cmap'])
        prev = cmaps[f['face']].get(f['subset'])
        cmaps[f['face']][f['subset']] = cps if prev is None else prev & cps

    def resolve(cp: int, stack: tuple) -> str | None:
        for key in stack:
            for sub in FACE[key].subsets:
                if cp in RANGES[sub] and cp in cmaps[key][sub]:
                    return f'{key}/{sub}'
        return None

    def mark_problems(loc: str) -> list[str]:
        """Browsers draw a base character and its combining marks with one font: a mark must
        follow a base that is drawn by a face which also has the mark (e.g. harakat on Arabic
        letters, never on a space or Latin letter, which the Arabic faces do not cover)."""
        found = collections.Counter()
        for role, stack in STACKS[loc].items():
            for text in copy[loc]['display'] + copy[loc]['text']:
                for i, ch in enumerate(text):
                    if unicodedata.category(ch) != 'Mn':
                        continue
                    j = i - 1
                    while j >= 0 and unicodedata.category(text[j]) == 'Mn':
                        j -= 1
                    where = resolve(ord(text[j]), stack) if j >= 0 else None
                    face, _, sub = (where or '//').partition('/')
                    if not where or ord(ch) not in RANGES[sub] or ord(ch) not in cmaps[face][sub]:
                        base = u(ord(text[j])) if j >= 0 else 'start of text'
                        found[f'{role}: {u(ord(ch))} after {base} ({where or "no face"})'] += 1
        return [f'{k} x{n}' for k, n in sorted(found.items())]

    result = {}
    problems = []
    for loc in LOCALES:
        counts = collections.Counter(''.join(copy[loc]['display'] + copy[loc]['text']))
        resolved = collections.Counter()
        controls, missing = [], []
        for ch, n in sorted(counts.items(), key=lambda kv: ord(kv[0])):
            cp = ord(ch)
            if is_format_control(cp):
                controls.append(u(cp))
                continue
            for role, stack in STACKS[loc].items():
                where = resolve(cp, stack)
                if where is None:
                    missing.append({'char': ch, 'cp': u(cp), 'name': unicodedata.name(ch, '?'),
                                    'role': role, 'count': n})
                else:
                    resolved[f'{role}:{where}'] += n
        marks = mark_problems(loc)
        mark_total = sum(1 for t in copy[loc]['display'] + copy[loc]['text'] for ch in t if unicodedata.category(ch) == 'Mn')
        result[loc] = {
            'files': len(copy[loc]['files']),
            'characters': sum(counts.values()),
            'distinct': len(counts),
            'charset': ''.join(sorted(counts, key=ord)),
            'resolved_by': dict(sorted(resolved.items())),
            'format_controls': controls,
            'missing': missing,
            'combining_marks': mark_total,
            'mark_problems': marks,
        }
        problems.extend(f"{loc} {m['role']}: {m['cp']} {m['name']} (x{m['count']})" for m in missing)
        problems.extend(f'{loc} combining mark {m}' for m in marks)
        log(f'  {loc}: {len(counts)} distinct characters in {sum(counts.values())} -> '
            + ('all covered' if not missing else f'{len(missing)} MISSING')
            + (f'; {mark_total} combining marks, all on bases drawn by the same face' if mark_total and not marks else '')
            + (f'; {len(marks)} combining-mark problems' if marks else ''))
    if problems:
        fail('approved copy not fully covered by the Summit faces (widen SUBSETS):\n  ' + '\n  '.join(problems))
    return result


# ---------------------------------------------------------------------------------------------
# 5. fallback metrics

FORM_TAGS = {'isolated': 'isol', 'final': 'fina', 'initial': 'init', 'medial': 'medi'}


def arabic_tables() -> tuple[dict, dict]:
    """Contextual forms from the Unicode presentation-form decompositions."""
    forms: dict[int, dict[str, int]] = {}
    lam_alef: dict[int, dict[str, int]] = {}
    for cp in list(range(0xFE70, 0xFF00)) + list(range(0xFB50, 0xFE00)):
        m = re.fullmatch(r'<(isolated|final|initial|medial)> ([0-9A-F ]+)', unicodedata.decomposition(chr(cp)))
        if not m:
            continue
        form = FORM_TAGS[m.group(1)]
        parts = [int(x, 16) for x in m.group(2).split()]
        if len(parts) == 1:
            forms.setdefault(parts[0], {}).setdefault(form, cp)
        elif len(parts) == 2 and parts[0] == 0x0644 and 0xFEF5 <= cp <= 0xFEFC:
            lam_alef.setdefault(parts[1], {}).setdefault(form, cp)
    return forms, lam_alef


ARABIC_FORMS, LAM_ALEF = arabic_tables()


def joining_type(cp: int) -> str:
    if cp in (0x0640, 0x200D):
        return 'C'
    if unicodedata.category(chr(cp)) in ('Mn', 'Me'):
        return 'T'
    forms = ARABIC_FORMS.get(cp, {})
    if 'init' in forms or 'medi' in forms:
        return 'D'
    if 'fina' in forms:
        return 'R'
    return 'U'


def shape_arabic(text: str) -> list[tuple[int, int, bool]]:
    """Approximate Arabic shaping: (code point to measure, base code point, is_mark) per glyph,
    choosing isolated/initial/medial/final presentation forms from the joining context and
    forming the mandatory lam-alef ligatures."""
    cps = [ord(c) for c in text]
    jt = [joining_type(c) for c in cps]
    n = len(cps)
    out: list[tuple[int, int, bool]] = []
    consumed: set[int] = set()
    for i, cp in enumerate(cps):
        if i in consumed:
            continue
        t = jt[i]
        if t == 'T':
            out.append((cp, cp, True))
            continue
        if t not in 'RD':
            out.append((cp, cp, False))
            continue
        p = i - 1
        while p >= 0 and jt[p] == 'T':
            p -= 1
        q = i + 1
        while q < n and jt[q] == 'T':
            q += 1
        joins_prev = p >= 0 and jt[p] in 'DC'
        joins_next = t == 'D' and q < n and jt[q] in 'RDC'
        if cp == 0x0644 and q < n and cps[q] in LAM_ALEF:
            lig = LAM_ALEF[cps[q]].get('fina' if joins_prev else 'isol')
            if lig:
                out.append((lig, cp, False))
                consumed.add(q)
                continue
        form = ('medi' if joins_next else 'fina') if joins_prev else ('init' if joins_next else 'isol')
        out.append((ARABIC_FORMS[cp].get(form, cp), cp, False))
    return out


class LocalFontIndex:
    def __init__(self) -> None:
        self._index: dict[str, tuple[Path, int]] | None = None

    def find(self, names: tuple) -> tuple[TTFont, str] | None:
        if self._index is None:
            self._index = {}
            for d in LOCAL_FONT_DIRS:
                for path in sorted(d.glob('*')):
                    if path.suffix.lower() not in ('.ttf', '.otf', '.ttc'):
                        continue
                    try:
                        members = TTCollection(path, lazy=True).fonts if path.suffix.lower() == '.ttc' else [TTFont(path, lazy=True)]
                    except Exception:
                        continue
                    for i, font in enumerate(members):
                        try:
                            nm = font['name']
                        except Exception:
                            continue
                        for name_id in (4, 6):
                            value = nm.getDebugName(name_id)
                            if value:
                                self._index.setdefault(value, (path, i))
        for name in names:
            if name in self._index:
                path, i = self._index[name]
                font = TTCollection(path).fonts[i] if path.suffix.lower() == '.ttc' else TTFont(path)
                return font, f'{path} #{i}' if path.suffix.lower() == '.ttc' else str(path)
        return None


def char_frequencies(copy: dict, web: str, sample: str, locales: tuple) -> collections.Counter:
    """Characters of the sample text that the web face renders, each locale weighted equally."""
    freq: collections.Counter = collections.Counter()
    ranges = set().union(*(RANGES[s] for s in FACE[web].subsets))
    for loc in locales:
        counts = collections.Counter(c for t in copy[loc][sample] for c in t if ord(c) in ranges)
        total = sum(counts.values()) or 1
        for ch, n in counts.items():
            freq[ch] += n / total
    return freq


def arabic_glyph_frequencies(copy: dict, sample: str, locales: tuple) -> collections.Counter:
    """Shaped Arabic glyphs (contextual forms, lam-alef ligatures; marks excluded) of the copy."""
    freq: collections.Counter = collections.Counter()
    ranges = RANGES['arabic']
    for loc in locales:
        counts: collections.Counter = collections.Counter()
        for text in copy[loc][sample]:
            for measure, base, is_mark in shape_arabic(text):
                if base in ranges and not is_mark:
                    counts[(measure, base)] += 1
        total = sum(counts.values()) or 1
        for key, n in counts.items():
            freq[key] += n / total
    return freq


def sample_frequencies(copy: dict, web: str, sample: str, locales: tuple) -> tuple[collections.Counter, bool]:
    arabic = FACE[web].subsets == ('arabic',)
    if arabic:
        return arabic_glyph_frequencies(copy, sample, locales), True
    return char_frequencies(copy, web, sample, locales), False


def average_advance(font: TTFont, freq: collections.Counter, arabic: bool) -> tuple[float, list[str]]:
    cmap = font.getBestCmap()
    hmtx = font['hmtx']
    upm = font['head'].unitsPerEm
    total = weight_sum = 0.0
    missing: set[str] = set()
    for key, w in freq.items():
        if arabic:
            measure, base = key
            gid = cmap.get(measure) or cmap.get(base)
            if gid is None:
                missing.add(u(base))
                continue
        else:
            gid = cmap.get(ord(key))
            if gid is None:
                missing.add(u(ord(key)))
                continue
        total += hmtx[gid][0] * w
        weight_sum += w
    if not weight_sum:
        return 0.0, sorted(missing)
    return total / weight_sum / upm, sorted(missing)


def vertical_metrics(font: TTFont) -> tuple[int, int, int, int]:
    """The metrics browsers use for these web fonts (USE_TYPO_METRICS is set and hhea == typo)."""
    hhea, os2 = font['hhea'], font['OS/2']
    if os2.fsSelection & (1 << 7):
        return os2.sTypoAscender, -os2.sTypoDescender, os2.sTypoLineGap, font['head'].unitsPerEm
    return hhea.ascent, -hhea.descent, hhea.lineGap, font['head'].unitsPerEm


def compute_fallbacks(copy: dict, instances: dict) -> list[dict]:
    """size-adjust = average advance of the web font / average advance of the local font over
    the sample copy; the overrides are the web font's vertical metrics divided by size-adjust,
    because browsers scale override values by size-adjust as well."""
    index = LocalFontIndex()
    out = []
    for fb in FALLBACKS:
        freq, arabic = sample_frequencies(copy, fb.web, fb.sample, fb.locales)
        for weight, locals_ in fb.weights.items():
            web = instances[(fb.web, weight)][0]
            web_avg, web_missing = average_advance(web, freq, arabic)
            asc, desc, gap, upm = vertical_metrics(web)
            candidates = []
            local_fonts = {}
            for lf in locals_:
                found = index.find((lf.name,) + lf.aliases)
                if not found:
                    candidates.append({'local': lf.name, 'available': False})
                    continue
                font, where = found
                local_fonts[lf.name] = font
                local_avg, local_missing = average_advance(font, freq, arabic)
                candidates.append({
                    'local': lf.name, 'available': True, 'file': where,
                    'size_adjust': web_avg / local_avg, 'missing': local_missing,
                })
            primary = candidates[0]
            if not primary.get('available'):
                fail(f'{fb.family} {weight}: primary local font {primary["local"]} not found on this Mac')
            size_adjust = primary['size_adjust']
            also = []
            for key, sample in fb.also:
                if (key, weight) not in instances:
                    continue
                other_freq, other_arabic = sample_frequencies(copy, key, sample, fb.locales)
                other_avg, _ = average_advance(instances[(key, weight)][0], other_freq, other_arabic)
                local_avg, _ = average_advance(local_fonts[primary['local']], other_freq, other_arabic)
                also.append({'web': f'{FACE[key].family} {weight}', 'sample': f"{'+'.join(fb.locales)} {sample} copy",
                             'ideal_size_adjust': other_avg / local_avg,
                             'width_error': size_adjust / (other_avg / local_avg) - 1})
            entry = {
                'family': fb.family, 'weight': weight, 'web': f'{FACE[fb.web].family} {weight}',
                'sample': f"{'+'.join(fb.locales)} {fb.sample} copy" + (', shaped forms' if arabic else ''),
                'src': [n for lf in locals_ for n in (lf.name,) + lf.aliases],
                'size_adjust': size_adjust,
                'ascent_override': asc / upm / size_adjust,
                'descent_override': desc / upm / size_adjust,
                'line_gap_override': gap / upm / size_adjust,
                'web_metrics': {'ascent': asc, 'descent': desc, 'line_gap': gap, 'upm': upm,
                                'avg_advance_em': web_avg, 'missing': web_missing},
                'unicode_range': ', '.join(SUBSETS[s] for s in FACE[fb.web].subsets) if fb.restrict else None,
                'candidates': candidates,
                'also': also,
            }
            out.append(entry)
            alts = ', '.join(f"{c['local']} {pct(c['size_adjust'])}" for c in candidates[1:] if c.get('available'))
            log(f'  {fb.family} {weight}: size-adjust {pct(size_adjust)} vs {primary["local"]}'
                + (f' (would be: {alts})' if alts else '')
                + ''.join(f"; for {a['web']} ideal {pct(a['ideal_size_adjust'])}" for a in also))
    return out


# ---------------------------------------------------------------------------------------------
# 6. outputs

def scss(files: list[dict], fallbacks: list[dict], versions: dict) -> str:
    lines = [
        '// Self-hosted web fonts for Summit Legal Consultancy.',
        '// GENERATED by scripts/fonts/build.py: do not edit by hand.',
        '// Rebuild: .venv-fonts/bin/python -I scripts/fonts/build.py (see docs/fonts.md).',
        '// All four families are SIL Open Font License 1.1, instanced, subset and renamed (OFL',
        '// Reserved Font Names): the licences ship in resources/fonts/src/<family>/OFL.txt.',
    ]
    for face in FACES:
        lines.append(f'//   {face.family}: {face.upstream} {versions[face.key]}, {face.role}.')
    lines += [
        '// Fallback faces scale a local system font with size-adjust and override its ascent,',
        '// descent and line gap so that text set in the fallback takes the same space as the',
        '// web font, so swapping fonts does not shift the layout. One face per weight, tuned for',
        '// the first local() font. Summit Arabic Fallback covers the Arabic range only: on Arabic',
        "// pages list it before 'Summit Serif Fallback' / 'Summit Sans Fallback' so that Latin,",
        '// digits and spaces fall through to the Latin fallback, as they do to the Latin web faces.',
    ]
    for face in FACES:
        lines += ['', f'// {face.family}: {face.upstream} {versions[face.key]}']
        for f in (f for f in files if f['face'] == face.key):
            lines += [
                '',
                '@font-face {',
                f"  font-family: '{face.family}';",
                '  font-style: normal;',
                f"  font-weight: {f['weight']};",
                '  font-display: swap;',
                f"  src: url('{f['url']}') format('woff2');",
                f"  unicode-range: {SUBSETS[f['subset']]};",
                '}',
            ]
    current = None
    for fb in fallbacks:
        if fb['family'] != current:
            current = fb['family']
            lines += ['', f"// {fb['family']}: imitates {fb['web'].rsplit(' ', 1)[0]}; overrides tuned for"
                      f" {fb['candidates'][0]['local']} ({fb['sample']})"]
        src = ', '.join(f"local('{n}')" for n in fb['src'])
        lines += [
            '',
            '@font-face {',
            f"  font-family: '{fb['family']}';",
            '  font-style: normal;',
            f"  font-weight: {fb['weight']};",
            f'  src: {src};',
            f"  size-adjust: {pct(fb['size_adjust'])};",
            f"  ascent-override: {pct(fb['ascent_override'])};",
            f"  descent-override: {pct(fb['descent_override'])};",
            f"  line-gap-override: {pct(fb['line_gap_override'])};",
        ]
        if fb['unicode_range']:
            lines.append(f"  unicode-range: {fb['unicode_range']};")
        lines.append('}')
    text = '\n'.join(lines) + '\n'
    if re.search(r'#[0-9a-fA-F]{3}', text) or re.search(r'\d\s*px\b|\bpx\b', text):
        fail('generated _fonts.scss would contain a hex colour or a px value')
    return text


def page_budget(files: list[dict]) -> dict:
    by_name = {f['name']: f for f in files}

    def total(names: list[str]) -> int:
        return sum(by_name[n]['bytes'] for n in names)

    def names(face: str, weights, subsets) -> list[str]:
        return [f'{face}-{w}-{s}' for w in weights for s in subsets if f'{face}-{w}-{s}' in by_name]

    plans = {
        'en': {
            'preload': PRELOAD['en'],
            'core': names('serif', [400], ['latin']) + names('sans', [400], ['latin']),
            'all_weights': names('serif', [400, 600], ['latin']) + names('sans', [400, 500, 600], ['latin']),
        },
        'ru': {
            'preload': PRELOAD['ru'],
            'core': names('serif', [400], ['latin', 'cyrillic']) + names('sans', [400], ['latin', 'cyrillic']),
            'all_weights': names('serif', [400, 600], ['latin', 'cyrillic']) + names('sans', [400, 500, 600], ['latin', 'cyrillic']),
        },
        'ar': {
            'preload': PRELOAD['ar'],
            'core': names('naskh', [400], ['arabic']) + names('sans-arabic', [400], ['arabic'])
                    + names('serif', [400], ['latin']) + names('sans', [400], ['latin']),
            'all_weights': names('naskh', [400, 600], ['arabic']) + names('sans-arabic', [400, 500, 600], ['arabic'])
                           + names('serif', [400, 600], ['latin']) + names('sans', [400, 500, 600], ['latin']),
        },
    }
    for plan in plans.values():
        plan['preload_bytes'] = by_name[plan['preload']]['bytes']
        plan['core_bytes'] = total(plan['core'])
        plan['all_weights_bytes'] = total(plan['all_weights'])
    # The header language chooser shows the other languages' native names, which pulls in one
    # more text subset per page when those labels are set in the Summit faces.
    plans['en']['switcher_extra'] = names('sans', [400], ['cyrillic']) + names('sans-arabic', [400], ['arabic'])
    plans['ru']['switcher_extra'] = names('sans-arabic', [400], ['arabic'])
    plans['ar']['switcher_extra'] = names('sans', [400], ['cyrillic'])
    for plan in plans.values():
        plan['switcher_extra_bytes'] = total(plan['switcher_extra'])
    return plans


def docs_block(files: list[dict], fallbacks: list[dict], coverage: dict, budget: dict, versions: dict) -> str:
    out = [GENERATED_START, '', '### Files', '',
           '| Logical name | File | KB | Glyphs | Code points | OpenType features kept |',
           '|---|---|--:|--:|--:|---|']
    for f in files:
        out.append(f"| `{f['name']}` | `{f['file']}` | {kb(f['bytes'])} | {f['glyphs']} | {f['codepoints']} | "
                   f"{' '.join(f['features'])} |")
    total_bytes = sum(f['bytes'] for f in files)
    out += ['', f'{len(files)} files, {kb(total_bytes)} KB in total.', '',
            '### Font bytes per language page', '',
            'Core is the display and text faces at 400. All weights adds serif 600 and sans 500/600. '
            'Spaces, punctuation, digits and Latin words on Arabic pages come from the Latin faces, '
            'so Arabic pages also load `serif-400-latin` and `sans-400-latin`. Browsers download only '
            'the files whose unicode-range and weight actually occur on the page.', '',
            '| Page | Preload (one display file) | Core (400) | All weights | Language chooser labels add |',
            '|---|---|--:|--:|--:|']
    for loc in LOCALES:
        b = budget[loc]
        out.append(f"| `/{loc}/` | `{b['preload']}` {kb(b['preload_bytes'])} KB | {kb(b['core_bytes'])} KB "
                   f"| {kb(b['all_weights_bytes'])} KB | +{kb(b['switcher_extra_bytes'])} KB "
                   f"({', '.join('`' + n + '`' for n in b['switcher_extra'])}) |")
    out += ['', '### Fallback faces', '',
            '| Family | Weight | Imitates | Tuned for | size-adjust | ascent | descent | line-gap | Other local fonts would need |',
            '|---|--:|---|---|--:|--:|--:|--:|---|']
    for fb in fallbacks:
        others = '; '.join(
            f"{c['local']} {pct(c['size_adjust'])}" if c.get('available') else f"{c['local']} (not on macOS, not measured)"
            for c in fb['candidates'][1:]) or 'n/a'
        out.append(f"| {fb['family']} | {fb['weight']} | {fb['web']} | {fb['candidates'][0]['local']} | "
                   f"{pct(fb['size_adjust'])} | {pct(fb['ascent_override'])} | {pct(fb['descent_override'])} | "
                   f"{pct(fb['line_gap_override'])} | {others} |")
    also = [(fb, a) for fb in fallbacks for a in fb['also']]
    if also:
        out += ['', 'The Arabic fallback also stands in for the Arabic display face. fontTools estimates against '
                'it are below; they ignore Naskh\'s and Geeza Pro\'s many required ligatures, so trust the '
                'Chrome measurement from `scripts/fonts/proof.mjs` instead:', '']
        for fb, a in also:
            out.append(f"- {fb['family']} {fb['weight']} for {a['web']} ({a['sample']}): estimated ideal size-adjust "
                       f"{pct(a['ideal_size_adjust'])} against the applied {pct(fb['size_adjust'])}, i.e. fallback lines "
                       f"about {pct(abs(a['width_error']))} {'wider' if a['width_error'] > 0 else 'narrower'} than the web font.")
    out += ['', '### Coverage of the approved copy', '',
            '| Locale | DOCX files | Characters | Distinct | Missing | Rendered by |', '|---|--:|--:|--:|--:|---|']
    for loc in LOCALES:
        c = coverage[loc]
        faces = collections.Counter()
        for key, n in c['resolved_by'].items():
            faces[key.split(':', 1)[1]] += n
        out.append(f"| {loc} | {c['files']} | {c['characters']} | {c['distinct']} | {len(c['missing'])} | "
                   + ', '.join(f'{k} ({v})' for k, v in sorted(faces.items())) + ' |')
    out += ['', 'Sources: google/fonts@`' + GOOGLE_FONTS_COMMIT + '`; '
            + '; '.join(f"{FACE[k].upstream} {v}" for k, v in versions.items()) + '.', '', GENERATED_END]
    return '\n'.join(out)


def update_docs(block: str) -> None:
    if not DOCS_PATH.exists():
        log(f'  {DOCS_PATH.relative_to(ROOT)} not found: generated block not written')
        return
    text = DOCS_PATH.read_text(encoding='utf-8')
    if GENERATED_START not in text or GENERATED_END not in text:
        log(f'  {DOCS_PATH.relative_to(ROOT)} has no generated-block markers: left unchanged')
        return
    head, rest = text.split(GENERATED_START, 1)
    _, tail = rest.split(GENERATED_END, 1)
    DOCS_PATH.write_text(head + block + tail, encoding='utf-8')
    log(f'  updated generated block in {DOCS_PATH.relative_to(ROOT)}')


def write_if_changed(path: Path, text: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    if not path.exists() or path.read_text(encoding='utf-8') != text:
        path.write_text(text, encoding='utf-8')
    log(f'  wrote {path.relative_to(ROOT)}')


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.split('\n', 1)[0])
    parser.add_argument('--fetch', action='store_true', help='download missing sources from the pinned google/fonts commit')
    args = parser.parse_args()

    log('1. sources')
    verify_sources(args.fetch)

    log('2. static instances')
    instances = {}
    versions = {}
    for face in FACES:
        for weight in face.weights:
            instances[(face.key, weight)] = make_instance(face, weight)
            versions[face.key] = font_version(instances[(face.key, weight)][0])
            loc = instances[(face.key, weight)][1]
            log(f'  {face.upstream} {versions[face.key]} wght {weight}' + (f' at {loc}' if loc else ' (static source)'))

    log('3. subsets -> public/fonts')
    files = build_files(instances)

    log('4. approved copy coverage')
    copy = collect_copy()
    coverage = check_coverage(copy, files)

    log('5. fallback metrics')
    fallbacks = compute_fallbacks(copy, instances)

    log('6. outputs')
    manifest = {f['name']: f['url'] for f in sorted(files, key=lambda f: f['name'])}
    write_if_changed(MANIFEST_PATH, json.dumps(manifest, indent=2) + '\n')
    write_if_changed(SCSS_PATH, scss(files, fallbacks, versions))
    budget = page_budget(files)
    report = {
        'generated_by': 'scripts/fonts/build.py',
        'google_fonts_commit': GOOGLE_FONTS_COMMIT,
        'versions': {FACE[k].upstream: v for k, v in versions.items()},
        'layout_features': LAYOUT_FEATURES,
        'subsets': SUBSETS,
        'preload': PRELOAD,
        'files': [{k: v for k, v in f.items() if k != 'cmap'} for f in files],
        'pages': budget,
        'coverage': coverage,
        'docx_reader': copy['_methods'],
        'fallbacks': fallbacks,
    }
    write_if_changed(REPORT_PATH, json.dumps(report, indent=2, ensure_ascii=False) + '\n')
    update_docs(docs_block(files, fallbacks, coverage, budget, versions))
    log(f'done: {len(files)} WOFF2 files, {kb(sum(f["bytes"] for f in files))} KB')


if __name__ == '__main__':
    main()
