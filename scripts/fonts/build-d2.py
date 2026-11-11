"""Build the Design 2 web fonts (docs/brief/upgrade-report.md §6.2 and §12 A2).

Run from the project root with the font venv (it only needs fonttools and brotli):

    .venv-fonts/bin/python -I scripts/fonts/build-d2.py            # build everything
    .venv-fonts/bin/python -I scripts/fonts/build-d2.py --fetch    # first download missing D2 sources

Design 1's pipeline (scripts/fonts/build.py, resources/fonts/src/**, public/fonts/*.woff2,
resources/scss/base/_fonts.scss) is frozen: this script only READS the Design 1 sources it shares
(IBM Plex Sans, IBM Plex Sans Arabic 400/500/600, Source Serif 4, Noto Naskh Arabic) and keeps its
own downloads (IBM Plex Sans Arabic Bold, IBM Plex Mono) in resources/fonts/src-d2/<family>/.
Every source is checked against the git blob SHA-1 that google/fonts reports for it.

Pipeline
  1. Verify the sources (checksum, name table: upstream family + SIL Open Font License 1.1).
  2. Make the web instances:
       sans    IBM Plex Sans variable, wght limited to 400-700, wdth pinned at 100
       arabic  IBM Plex Sans Arabic static 400 / 500 / 600 / 700
       mono    IBM Plex Mono static 500 (eyebrows) and 400
       serif   Source Serif 4 variable, wght 400-600, opsz pinned at 20 (Text)    sample documents
       naskh   Noto Naskh Arabic variable, wght 400-600                           sample documents
  3. Subset each instance per unicode-range group (latin / cyrillic / arabic), keep the OpenType
     layout features the text needs, drop hinting, rename the faces (the OFL Reserved Font Names
     "Plex" and "Source" may not be used by a Modified Version) and write content-hashed WOFF2
     files to public/fonts/d2/ plus public/fonts/d2/manifest.json (key -> URL).
  4. Check that every character of the site copy (database/content/*.json and lang/{locale}/**)
     is drawn by a Design 2 face in that language's font stack.
  5. Compute metric-matched fallback faces (size-adjust from the average advance width over the
     approved copy; ascent/descent/line-gap overrides from the web font, divided by size-adjust)
     against the local system fonts in /System/Library/Fonts(/Supplemental).
  6. Write resources/css/d2/base/fonts.css (inlined in the page head by App\\Design\\D2\\FontFaces,
     not bundled by Vite) and storage/app/d2/fonts/font-build.json (sizes, features, coverage,
     fallback metrics).

The output is deterministic: the same sources produce byte-identical files and the same hashes.
Arabic pages draw spaces, digits and punctuation from the Latin file (the Arabic files hold the
Arabic ranges only), exactly as Latin text inside Arabic pages.
"""

from __future__ import annotations

import argparse
import collections
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
FONTS_DIR = ROOT / 'resources' / 'fonts'
OUT_DIR = ROOT / 'public' / 'fonts' / 'd2'
URL_PREFIX = '/fonts/d2/'
MANIFEST_PATH = OUT_DIR / 'manifest.json'
CSS_PATH = ROOT / 'resources' / 'css' / 'd2' / 'base' / 'fonts.css'
REPORT_PATH = ROOT / 'storage' / 'app' / 'd2' / 'fonts' / 'font-build.json'
CONTENT_DIR = ROOT / 'database' / 'content'
LANG_DIR = ROOT / 'lang'
LOCALES = ('en', 'ar', 'ru')
LOCAL_FONT_DIRS = (Path('/System/Library/Fonts'), Path('/System/Library/Fonts/Supplemental'))
SIZE_BUDGET = 60 * 1024

# ---------------------------------------------------------------------------------------------
# 1. sources: google/fonts ofl/<dir>/<file> at one pinned commit, verified by git blob SHA-1
#    (the "sha" the GitHub contents API reports). 'src' is Design 1's tree (read only here).

GOOGLE_FONTS_COMMIT = '5bb28733cec313b63fbd415b574b5ae35c8aa8b8'  # google/fonts main, 2026-11-08
RAW_URL = 'https://raw.githubusercontent.com/google/fonts/{commit}/ofl/{dir}/{file}'
OFL_SHA_IBM = 'c35c4c618fab33da8695177b3a6cefe0810b7b28'
SOURCES: dict[tuple[str, str], dict[str, str]] = {
    ('src', 'ibmplexsans'): {
        'IBMPlexSans[wdth,wght].ttf': '30f837e8b02e8ecddce6cbebc425b1f422392515',
        'OFL.txt': OFL_SHA_IBM,
    },
    ('src', 'ibmplexsansarabic'): {
        'IBMPlexSansArabic-Regular.ttf': 'e779068718b9801b809605b2bf3067121cce524c',
        'IBMPlexSansArabic-Medium.ttf': 'e82313893b5b5b6af3e7429f5b798666f1e0b097',
        'IBMPlexSansArabic-SemiBold.ttf': 'f7a7fc87747a9ddabb3557d4a3f4140a7a1460b0',
        'OFL.txt': OFL_SHA_IBM,
    },
    ('src', 'sourceserif4'): {
        'SourceSerif4[opsz,wght].ttf': '40bbb58cb58e893ad0470f0bb291e096c73d3554',
        'OFL.txt': '318074ec9d43c936a56ee1dae92999f4df073ce8',
    },
    ('src', 'notonaskharabic'): {
        'NotoNaskhArabic[wght].ttf': 'a8d2867262dc7bb28492ad746de5d56235735c8b',
        'OFL.txt': 'd4e170557fc8bcf8d1dcb317d9f1accaf8acf223',
    },
    ('src-d2', 'ibmplexsansarabic'): {
        'IBMPlexSansArabic-Bold.ttf': '211ff62d8d1778bff69a1b3a906b2ad1686564bc',
        'OFL.txt': OFL_SHA_IBM,
        'METADATA.pb': 'a11b215c4845702dc709482ee089e82cf4c16384',
        'upstream_info.md': '3c578755cbb11dd6c82cc93155ba483402b0e634',
    },
    ('src-d2', 'ibmplexmono'): {
        'IBMPlexMono-Regular.ttf': '0c9770d5183ba60dc4350d3e011b782a320761ae',
        'IBMPlexMono-Medium.ttf': '33c546f68b6007a45a3f10e845523abb2db25399',
        'OFL.txt': OFL_SHA_IBM,
        'METADATA.pb': '29a9cb468609fda640a0c866f66827ac940ba3b6',
        'upstream_info.md': '3d5005e2ff4aa869875e20fded39ac2f12a0c6d5',
    },
}

# ---------------------------------------------------------------------------------------------
# Unicode-range groups (task A1). Latin: Basic Latin, Latin-1, Latin Extended-A, General
# Punctuation, plus the arrows, minus and comparison signs the Design 2 copy uses (U+2190-2193,
# U+2212, U+2264-2265). The copy uses no currency sign (AED is written in letters), so no currency
# block. Characters of the copy outside these groups fail the build, so a group can only be widened
# deliberately.

SUBSETS: dict[str, str] = {
    'latin': 'U+0000-00FF, U+0100-017F, U+2000-206F, U+2190-2193, U+2212, U+2264-2265',
    'cyrillic': 'U+0400-045F, U+0490-0491, U+2116',
    'arabic': 'U+0600-06FF, U+0750-077F, U+08A0-08FF, U+FB50-FDFF, U+FE70-FEFF, U+200C-200F',
    # Noto Naskh (sample documents only): without Arabic Presentation Forms-A (U+FB50-FDFF, 25 KB of
    # ligature code points that shaping never produces) and Arabic Extended-A (U+08A0-08FF, African
    # and Quranic letters), so the variable 400-600 file stays under 60 KB. Characters outside it
    # still get the tuned "D2 Naskh Fallback" (full Arabic range).
    'arabic-doc': 'U+0600-06FF, U+0750-077F, U+FE70-FEFF, U+200C-200F',
}

# OpenType layout features kept where the font has them: typesetting (kern liga calt ccmp locl
# mark mkmk), Arabic joining (rlig init medi fina isol curs), figures (tnum lnum pnum), case, and
# the features shaping engines apply by default (rvrn: IBM Plex's variable alternates, rtlm, rclt).
# Dropped: aalt salt ss01-ss06 dlig frac numr dnom sups subs sinf ordn zero onum.
LAYOUT_FEATURES = [
    'kern', 'liga', 'calt', 'ccmp', 'locl', 'mark', 'mkmk', 'rlig', 'init', 'medi', 'fina',
    'isol', 'curs', 'tnum', 'lnum', 'pnum', 'case', 'rvrn', 'rtlm', 'rclt',
]

STYLE_NAMES = {400: 'Regular', 500: 'Medium', 600: 'SemiBold', 700: 'Bold'}


@dataclasses.dataclass(frozen=True)
class Face:
    key: str
    internal: str                # family name written into the name table (OFL: renamed)
    upstream: str                # upstream family name (must match the source's name table)
    role: str


FACES: dict[str, Face] = {f.key: f for f in (
    Face('sans', 'Summit D2 Sans', 'IBM Plex Sans',
         'UI and running text, Latin + Cyrillic (variable: wght 400-700, wdth 100)'),
    Face('sans-arabic', 'Summit D2 Sans Arabic', 'IBM Plex Sans Arabic',
         'Arabic UI and running text (static 400 / 500 / 600 / 700)'),
    Face('mono', 'Summit D2 Mono', 'IBM Plex Mono',
         'eyebrows, keyboard keys and figures, Latin + Cyrillic only (static 500 and 400)'),
    Face('serif', 'Summit D2 Serif', 'Source Serif 4',
         'sample documents only (variable: wght 400-600, opsz 20 Text)'),
    Face('naskh', 'Summit D2 Naskh', 'Noto Naskh Arabic',
         'Arabic sample documents only (variable: wght 400-600)'),
)}


@dataclasses.dataclass(frozen=True)
class FileSpec:
    key: str                     # manifest key = file name stem
    face: str
    source: str                  # path under resources/fonts
    location: tuple              # ((axis, value | (min, max)), ...): pinned or limited axes
    subset: str
    weight: int | None = None    # static weight (None: variable, the range comes from 'wght')


PLEX_SANS = 'src/ibmplexsans/IBMPlexSans[wdth,wght].ttf'
SERIF = 'src/sourceserif4/SourceSerif4[opsz,wght].ttf'
NASKH = 'src/notonaskharabic/NotoNaskhArabic[wght].ttf'
ARABIC_STATIC = {
    400: 'src/ibmplexsansarabic/IBMPlexSansArabic-Regular.ttf',
    500: 'src/ibmplexsansarabic/IBMPlexSansArabic-Medium.ttf',
    600: 'src/ibmplexsansarabic/IBMPlexSansArabic-SemiBold.ttf',
    700: 'src-d2/ibmplexsansarabic/IBMPlexSansArabic-Bold.ttf',
}
MONO_STATIC = {
    400: 'src-d2/ibmplexmono/IBMPlexMono-Regular.ttf',
    500: 'src-d2/ibmplexmono/IBMPlexMono-Medium.ttf',
}
SANS_LOCATION = (('wdth', 100), ('wght', (400, 700)))
SERIF_LOCATION = (('opsz', 20), ('wght', (400, 600)))
NASKH_LOCATION = (('wght', (400, 600)),)

FILES: tuple[FileSpec, ...] = (
    FileSpec('sans-latin', 'sans', PLEX_SANS, SANS_LOCATION, 'latin'),
    FileSpec('sans-cyrillic', 'sans', PLEX_SANS, SANS_LOCATION, 'cyrillic'),
    *(FileSpec(f'sans-arabic-{w}', 'sans-arabic', src, (), 'arabic', w) for w, src in ARABIC_STATIC.items()),
    FileSpec('mono-latin', 'mono', MONO_STATIC[500], (), 'latin', 500),
    FileSpec('mono-cyrillic', 'mono', MONO_STATIC[500], (), 'cyrillic', 500),
    FileSpec('mono-latin-400', 'mono', MONO_STATIC[400], (), 'latin', 400),
    FileSpec('mono-cyrillic-400', 'mono', MONO_STATIC[400], (), 'cyrillic', 400),
    FileSpec('serif-latin', 'serif', SERIF, SERIF_LOCATION, 'latin'),
    FileSpec('serif-cyrillic', 'serif', SERIF, SERIF_LOCATION, 'cyrillic'),
    FileSpec('naskh-arabic', 'naskh', NASKH, NASKH_LOCATION, 'arabic-doc'),
)
FILE = {f.key: f for f in FILES}

# ---------------------------------------------------------------------------------------------
# CSS families. A family is a list of @font-face rules (file key, font-weight descriptor) in
# source order. Browsers first group a family's faces by their descriptors and only then combine
# faces of one group by unicode-range, so every member of one weight band must carry the SAME
# font-weight value. Where unicode-ranges overlap (U+200C-200F) the rule written LAST wins.
#
# "D2 Sans Arabic" also carries the Latin and Cyrillic files: Latin words, digits, spaces and
# punctuation on Arabic pages render in D2 Sans (IBM Plex Sans) even though --font-arabic does not
# list "D2 Sans". Its bands map the requested weight to the Arabic static weight the CSS matching
# algorithm would pick (450 -> 500, 550 -> 600, 650 -> 700) while the variable Latin file is
# clamped into the band, i.e. it renders at the exact requested weight.

SANS_ARABIC_BANDS = ((400, 449, 400), (450, 549, 500), (550, 649, 600), (650, 700, 700))

FAMILIES: dict[str, list[tuple[str, str]]] = {
    'D2 Sans': [('sans-latin', '400 700'), ('sans-cyrillic', '400 700')],
    'D2 Sans Arabic': [
        member
        for lo, hi, w in SANS_ARABIC_BANDS
        for member in (('sans-latin', f'{lo} {hi}'), ('sans-cyrillic', f'{lo} {hi}'), (f'sans-arabic-{w}', f'{lo} {hi}'))
    ],
    'D2 Mono': [('mono-latin-400', '400'), ('mono-cyrillic-400', '400'), ('mono-latin', '500'), ('mono-cyrillic', '500')],
    'D2 Serif': [('serif-latin', '400 600'), ('serif-cyrillic', '400 600')],
    'D2 Naskh': [('serif-latin', '400 600'), ('serif-cyrillic', '400 600'), ('naskh-arabic', '400 600')],
}

# Which CSS families draw which language's text (mirrors --font-* in resources/css/d2/tokens.css).
STACKS = {
    'en': {'text': 'D2 Sans', 'eyebrow': 'D2 Mono', 'document': 'D2 Serif'},
    'ru': {'text': 'D2 Sans', 'eyebrow': 'D2 Mono', 'document': 'D2 Serif'},
    'ar': {'text': 'D2 Sans Arabic', 'document': 'D2 Naskh'},
}
# Arabic islands on English and Russian pages (the language pill's «ع», native language names) are
# set with --font-arabic / --font-doc-arabic, so they are checked against these families.
ISLANDS = {'text': 'D2 Sans Arabic', 'eyebrow': 'D2 Sans Arabic', 'document': 'D2 Naskh'}
REQUIRED_ROLES = ('text', 'document')   # missing glyphs fail the build; others are reported


# ---------------------------------------------------------------------------------------------
# Fallback faces: local fonts scaled (size-adjust) and given the web font's vertical metrics, so
# text set in a fallback while a web font loads (or if it fails) takes the same space.

@dataclasses.dataclass(frozen=True)
class LocalFont:
    name: str                    # first local() name
    aliases: tuple = ()          # further local() names: Chrome matches the full or PostScript name


@dataclasses.dataclass(frozen=True)
class FallbackMember:
    face: str                    # web face whose metrics it imitates
    sample: tuple                # locales whose copy weights the average advance
    weights: dict                # weight -> tuple[LocalFont, ...]; tuned for the first available
    subset: str | None = None    # unicode-range restriction ('latin+cyrillic', 'arabic') or None


ARIAL = LocalFont('Arial', ('ArialMT',))
ARIAL_B = LocalFont('Arial Bold', ('Arial-BoldMT',))
HELVETICA = LocalFont('Helvetica Neue', ('HelveticaNeue',))
HELVETICA_B = LocalFont('Helvetica Neue Bold', ('HelveticaNeue-Bold',))
GEEZA = LocalFont('Geeza Pro Regular', ('GeezaPro', 'Geeza Pro'))
GEEZA_B = LocalFont('Geeza Pro Bold', ('GeezaPro-Bold',))
SEGOE = LocalFont('Segoe UI', ('SegoeUI',))
SEGOE_B = LocalFont('Segoe UI Bold', ('SegoeUI-Bold',))
TAHOMA = LocalFont('Tahoma')
TAHOMA_B = LocalFont('Tahoma Bold', ('Tahoma-Bold',))
MENLO = LocalFont('Menlo Regular', ('Menlo-Regular', 'Menlo'))
CONSOLAS = LocalFont('Consolas')
GEORGIA = LocalFont('Georgia')
GEORGIA_B = LocalFont('Georgia Bold', ('Georgia-Bold',))
TIMES = LocalFont('Times New Roman', ('TimesNewRomanPSMT',))
TIMES_B = LocalFont('Times New Roman Bold', ('TimesNewRomanPS-BoldMT',))

_SANS_LATIN = {400: (ARIAL, HELVETICA), 500: (ARIAL, HELVETICA), 600: (ARIAL_B, HELVETICA_B), 700: (ARIAL_B, HELVETICA_B)}
_SANS_ARABIC = {400: (GEEZA, SEGOE, TAHOMA), 500: (GEEZA, SEGOE, TAHOMA),
                600: (GEEZA_B, SEGOE_B, TAHOMA_B), 700: (GEEZA_B, SEGOE_B, TAHOMA_B)}
_SERIF_LATIN = {400: (GEORGIA,), 600: (GEORGIA_B,)}

FALLBACKS: dict[str, list[FallbackMember]] = {
    # Latin + Cyrillic only: an Arabic island that is not set in --font-arabic falls through to the
    # system UI font instead of Arial's Arabic scaled for Latin.
    'D2 Sans Fallback': [FallbackMember('sans', ('en', 'ru'), _SANS_LATIN, 'latin+cyrillic')],
    # Latin first (digits, spaces and Latin words on Arabic pages), then Arabic (wins U+200C-200F).
    'D2 Sans Arabic Fallback': [
        FallbackMember('sans', ('en', 'ru'), _SANS_LATIN, 'latin+cyrillic'),
        FallbackMember('sans-arabic', ('ar',), _SANS_ARABIC, 'arabic'),
    ],
    'D2 Mono Fallback': [FallbackMember('mono', ('en', 'ru'), {400: (MENLO, CONSOLAS), 500: (MENLO, CONSOLAS)}, 'latin+cyrillic')],
    'D2 Serif Fallback': [FallbackMember('serif', ('en', 'ru'), _SERIF_LATIN, 'latin+cyrillic')],
    'D2 Naskh Fallback': [
        FallbackMember('serif', ('en', 'ru'), _SERIF_LATIN, 'latin+cyrillic'),
        FallbackMember('naskh', ('ar',), {400: (TIMES, GEEZA), 600: (TIMES_B, GEEZA_B)}, 'arabic'),
    ],
}

# The Arabic naskh fallback (sample documents) is measured against these too, for the report.
COMPARE_ONLY = {('naskh', 400): (LocalFont('DecoType Naskh Regular', ('DecoTypeNaskh',)), LocalFont('Al Nile', ('AlNile',)))}


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


def subset_spec(name: str) -> str:
    return ', '.join(SUBSETS[s] for s in name.split('+'))


def subset_cps(name: str) -> set[int]:
    return set().union(*(RANGES[s] for s in name.split('+')))


def u(cp: int) -> str:
    return f'U+{cp:04X}'


def pct(value: float) -> str:
    text = f'{value * 100:.2f}'.rstrip('0').rstrip('.')
    return f'{text}%'


def kb(n: int) -> str:
    return f'{n / 1024:.1f}'


def font_version(font: TTFont) -> str:
    raw = font['name'].getDebugName(5) or ''
    m = re.search(r'(\d+\.\d+)', raw)
    return m.group(1) if m else f"{font['head'].fontRevision:.3f}"


def family_name(font: TTFont) -> str:
    return font['name'].getDebugName(16) or font['name'].getDebugName(1) or ''


# ---------------------------------------------------------------------------------------------
# 1. sources

def verify_sources(fetch: bool) -> None:
    for (tree, d), files in SOURCES.items():
        for name, sha in files.items():
            path = FONTS_DIR / tree / d / name
            if not path.exists():
                if tree != 'src-d2':
                    fail(f'missing Design 1 source {path.relative_to(ROOT)} (restore it with scripts/fonts/build.py --fetch)')
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
            if name == 'OFL.txt' and b'SIL OPEN FONT LICENSE Version 1.1' not in data:
                fail(f'{path.relative_to(ROOT)} is not the SIL Open Font License 1.1')
    for spec in FILES:
        font = TTFont(FONTS_DIR / spec.source, lazy=True)
        upstream = FACES[spec.face].upstream
        if not family_name(font).startswith(upstream):
            fail(f'{spec.source}: name table family {family_name(font)!r} is not {upstream!r}')
        licence = font['name'].getDebugName(13) or ''
        if 'SIL Open Font License, Version 1.1' not in licence:
            fail(f'{spec.source}: name ID 13 is not the SIL Open Font License 1.1 ({licence[:60]!r})')
        if not (FONTS_DIR / spec.source).with_name('OFL.txt').exists():
            fail(f'{spec.source}: OFL.txt missing next to the font')
    log('  sources verified against google/fonts@' + GOOGLE_FONTS_COMMIT[:12] + ' (name tables: upstream family, OFL 1.1)')


# ---------------------------------------------------------------------------------------------
# 2. instances

def load_source(spec: FileSpec) -> tuple[TTFont, dict]:
    """The web instance of a file spec (pinned / limited axes) and the axis limits used."""
    font = TTFont(FONTS_DIR / spec.source, recalcTimestamp=False)
    used: dict = {}
    if 'fvar' in font:
        location = dict(spec.location)
        for axis in font['fvar'].axes:
            value = location.get(axis.axisTag, axis.defaultValue)
            lo, hi = value if isinstance(value, tuple) else (value, value)
            if not (axis.minValue <= lo <= hi <= axis.maxValue):
                fail(f'{spec.source}: {axis.axisTag}={value} outside {axis.minValue}..{axis.maxValue}')
            used[axis.axisTag] = value
        font = instancer.instantiateVariableFont(font, used, inplace=False, optimize=True, updateFontNames=False)
        if 'fvar' in font:
            font['fvar'].instances = []          # named instances are not used on the web
    elif spec.location:
        fail(f'{spec.source} is static but an axis location was requested: {spec.location}')
    buf = io.BytesIO()
    font.save(buf)
    return TTFont(io.BytesIO(buf.getvalue()), recalcTimestamp=False), used


def static_at(spec: FileSpec, weight: int, cache: dict) -> TTFont:
    """A static instance of a face at one weight (metrics for fallbacks and sanity checks)."""
    key = (spec.source, spec.location, weight)
    if key not in cache:
        font = TTFont(FONTS_DIR / spec.source, recalcTimestamp=False)
        if 'fvar' in font:
            location = {k: (weight if k == 'wght' else (v if not isinstance(v, tuple) else v[0]))
                        for k, v in spec.location}
            font = instancer.instantiateVariableFont(font, location, inplace=False, optimize=False, updateFontNames=False)
        cache[key] = font
    return cache[key]


def check_variable_instance(spec: FileSpec, web: TTFont, cache: dict) -> list[str]:
    """The axis-limited web font must reproduce the original design at every weight it serves:
    compare the advance widths of all mapped glyphs at sample weights."""
    if 'fvar' not in web:
        return []
    axis = next(a for a in web['fvar'].axes if a.axisTag == 'wght')
    problems = []
    weights = sorted({int(axis.minValue), int(axis.maxValue), *range(int(axis.minValue) + 50, int(axis.maxValue), 100)})
    for w in weights:
        limited = instancer.instantiateVariableFont(web, {'wght': w}, inplace=False, optimize=False, updateFontNames=False)
        original = static_at(spec, w, cache)
        lc, oc = limited.getBestCmap(), original.getBestCmap()
        for cp in sorted(set(lc) & set(oc) & subset_cps(spec.subset)):
            a, b = limited['hmtx'][lc[cp]][0], original['hmtx'][oc[cp]][0]
            if abs(a - b) > 1:
                problems.append(f'{spec.key}: {u(cp)} advance {a} != original {b} at wght {w}')
                break
    return problems


# ---------------------------------------------------------------------------------------------
# 3. subsets

def subset_options(variable: bool) -> ftsubset.Options:
    opts = ftsubset.Options()
    opts.layout_features = list(LAYOUT_FEATURES)
    opts.layout_scripts = ['*']
    opts.hinting = False
    opts.legacy_kern = False
    opts.glyph_names = False
    opts.name_IDs = ['*']                 # pruned in set_names() (fvar axis names stay)
    opts.name_languages = [0x0409]
    opts.name_legacy = False
    opts.notdef_outline = False
    opts.recalc_bounds = True
    opts.recalc_timestamp = False
    drop = {'STAT', 'meta', 'BASE', 'DSIG', 'MVAR', 'cvar'}
    if not variable:
        drop |= {'HVAR'}
    opts.drop_tables = sorted(set(opts.drop_tables) | drop)
    return opts


def set_names(font: TTFont, face: Face, style: str, weight_class: int, version: str) -> None:
    """Rename to the Summit D2 family: IBM Plex ("Plex") and Source Serif ("Source") have OFL
    Reserved Font Names, which a Modified Version (instanced + subset) must not use. Copyright
    (0), version (5), licence (13) and licence URL (14) stay; so do the fvar axis names."""
    name = font['name']
    ps_family = face.internal.replace(' ', '')
    ps_name = f'{ps_family}-{style}'
    axis_names = {a.axisNameID for a in font['fvar'].axes} if 'fvar' in font else set()
    name.names = [r for r in name.names if r.nameID in (0, 5, 13, 14) or r.nameID in axis_names]
    records = {
        1: face.internal if style == 'Regular' else f'{face.internal} {style}',
        2: 'Regular',
        3: f'{version};SUMMIT-D2;{ps_name}',
        4: f'{face.internal} {style}',
        6: ps_name,
    }
    if style != 'Regular':
        records[16] = face.internal
        records[17] = style
    if 'fvar' in font:
        records[25] = ps_family               # variations PostScript name prefix
    for name_id, text in records.items():
        name.setName(text, name_id, 3, 1, 0x409)
    name.names.sort(key=lambda r: (r.platformID, r.platEncID, r.langID, r.nameID))
    for record in name.names:
        text = record.toUnicode()
        if record.nameID not in (0, 13, 14) and re.search(r'\b(Plex|Source)\b', text):
            fail(f'{face.internal}: name ID {record.nameID} still uses a Reserved Font Name: {text!r}')

    os2 = font['OS/2']
    os2.usWeightClass = weight_class
    os2.fsSelection = (os2.fsSelection & ~0b1100001) | (1 << 6)   # REGULAR; clear ITALIC / BOLD
    font['head'].macStyle &= ~0b11


def set_unhinted(font: TTFont) -> None:
    """Same unhinted state the upstream Noto / Source files ship with (gftools fix-unhinted):
    smooth at all sizes, dropout control on, no glyph programs."""
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


def weight_range(font: TTFont, spec: FileSpec) -> tuple[int, int]:
    if spec.weight is not None:
        return spec.weight, spec.weight
    axis = next(a for a in font['fvar'].axes if a.axisTag == 'wght')
    return int(axis.minValue), int(axis.maxValue)


def build_files(cache: dict) -> tuple[list[dict], list[str]]:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    files, problems = [], []
    instances: dict = {}
    for spec in FILES:
        ikey = (spec.source, spec.location)
        if ikey not in instances:
            inst, used = load_source(spec)
            instances[ikey] = (inst, used)
            log(f'  {FACES[spec.face].upstream} {font_version(inst)} ' + (f'at {used}' if used else '(static source)'))
        inst, used = instances[ikey]
        face = FACES[spec.face]
        variable = 'fvar' in inst
        cmap = inst.getBestCmap()
        unicodes = sorted(set(cmap) & RANGES[spec.subset])
        buf = io.BytesIO()
        inst.save(buf)
        font = TTFont(io.BytesIO(buf.getvalue()), recalcTimestamp=False)
        subsetter = ftsubset.Subsetter(subset_options(variable))
        subsetter.populate(unicodes=unicodes)
        subsetter.subset(font)
        lo, hi = weight_range(font, spec)
        style = STYLE_NAMES[spec.weight] if spec.weight else 'Regular'
        set_names(font, face, style, spec.weight or lo, font_version(inst))
        set_unhinted(font)
        problems += check_variable_instance(spec, font, cache)
        data = to_woff2(font)
        digest = hashlib.sha256(data).hexdigest()[:8]
        filename = f'{spec.key}.{digest}.woff2'
        (OUT_DIR / filename).write_bytes(data)
        kept = sorted(font.getBestCmap())
        missing = sorted(set(unicodes) - set(kept))
        if missing:
            problems.append(f'{spec.key}: lost {len(missing)} code points while subsetting')
        files.append({
            'key': spec.key, 'file': filename, 'url': URL_PREFIX + filename, 'face': spec.face,
            'internal_family': face.internal, 'upstream': face.upstream, 'version': font_version(inst),
            'source': f'resources/fonts/{spec.source}', 'location': {k: list(v) if isinstance(v, tuple) else v for k, v in used.items()},
            'weight': [lo, hi] if lo != hi else lo, 'variable': variable, 'subset': spec.subset,
            'unicode_range': SUBSETS[spec.subset], 'bytes': len(data), 'glyphs': len(font.getGlyphOrder()),
            'codepoints': len(kept), 'features': features_of(font),
            'tables': sorted(t for t in font.keys() if t != 'GlyphOrder'), 'cmap': kept,
        })
        flag = '' if len(data) <= SIZE_BUDGET else '  OVER 60 KB'
        log(f'  {filename:34s} {kb(len(data)):>6s} KB  {len(font.getGlyphOrder()):4d} glyphs  {len(kept):4d} code points{flag}')
    remove_stale(files)
    return files, problems


def remove_stale(files: list[dict]) -> None:
    keep = {f['file'] for f in files}
    pattern = re.compile(r'^(?:%s)\.[0-9a-f]{8}\.woff2$' % '|'.join(re.escape(f.key) for f in FILES))
    for path in OUT_DIR.iterdir():
        if pattern.match(path.name) and path.name not in keep:
            path.unlink()
            log(f'  removed stale {path.name}')


# ---------------------------------------------------------------------------------------------
# 4. copy and coverage

PHP_DUMP = 'echo json_encode(require $argv[1], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);'


def strings_of(value, out: list[str]) -> None:
    if isinstance(value, dict):
        for v in value.values():
            strings_of(v, out)
    elif isinstance(value, list):
        for v in value:
            strings_of(v, out)
    elif isinstance(value, str):
        out.append(value)


def collect_copy() -> dict:
    """Approved copy (database/content/*.json, translatable {en, ar, ru} leaves), proposed copy
    (lang/{locale}/proposed*.php and proposed-d2/*.php) and internal labels (styleguide and switcher
    annotations, set in D2 Sans but never in eyebrows or sample documents), per locale."""
    approved = {loc: [] for loc in LOCALES}

    def walk(value, loc=None):
        if isinstance(value, dict):
            if value and set(value) <= set(LOCALES):
                for k, v in value.items():
                    walk(v, k)
            else:
                for v in value.values():
                    walk(v, loc)
        elif isinstance(value, list):
            for v in value:
                walk(v, loc)
        elif isinstance(value, str) and loc:
            approved[loc].append(value)

    for path in sorted(CONTENT_DIR.glob('*.json')):
        walk(json.loads(path.read_text(encoding='utf-8')))
    lang = {loc: [] for loc in LOCALES}
    internal = {loc: [] for loc in LOCALES}
    for loc in LOCALES:
        for path in sorted((LANG_DIR / loc).rglob('*.php')):
            proc = subprocess.run(['php', '-r', PHP_DUMP, str(path)], cwd=ROOT, capture_output=True, text=True)
            if proc.returncode != 0:
                log(f'  note: could not read {path.relative_to(ROOT)}: {proc.stderr.strip()[:120]}')
                continue
            rel = path.relative_to(LANG_DIR / loc).as_posix()
            target = internal if rel.startswith(('styleguide', 'switcher')) else lang
            strings_of(json.loads(proc.stdout or 'null'), target[loc])
    return {'approved': approved, 'lang': lang, 'internal': internal}


def is_format_control(cp: int) -> bool:
    return unicodedata.category(chr(cp)) in ('Cc', 'Cf') or cp in (0x2028, 0x2029)


def family_coverage(files: list[dict]) -> dict[str, list[tuple[set, set]]]:
    """family -> [(unicode-range code points, code points the file has)] in priority order."""
    by_key = {f['key']: f for f in files}
    out = {}
    for family, members in FAMILIES.items():
        seen, entries = set(), []
        for key, _ in reversed(members):            # last rule wins
            if key in seen:
                continue
            seen.add(key)
            entries.append((RANGES[FILE[key].subset], set(by_key[key]['cmap'])))
        out[family] = entries
    return out


def resolves(cp: int, entries: list[tuple[set, set]]) -> bool:
    for rng, cmap in entries:
        if cp in rng:
            return cp in cmap
    return False


def check_coverage(copy: dict, files: list[dict]) -> dict:
    cover = family_coverage(files)
    result, problems = {}, []
    for loc in LOCALES:
        site = collections.Counter(''.join(copy['approved'][loc] + copy['lang'][loc]))
        # internal annotations fall back to English on Arabic and Russian pages
        counts = site + collections.Counter(''.join(copy['internal'][loc] + copy['internal']['en']))
        upper = collections.Counter()
        for ch, n in site.items():
            for c in ch.upper():
                upper[c] += n
        entry = {'characters': sum(counts.values()), 'distinct': len(counts), 'roles': {}}
        for role, family in STACKS[loc].items():
            # eyebrows are set in capitals; eyebrows and documents never show internal annotations
            needed = upper if role == 'eyebrow' else (counts if role == 'text' else site)
            missing, islands = [], collections.Counter()
            for ch, n in sorted(needed.items(), key=lambda kv: ord(kv[0])):
                cp = ord(ch)
                if is_format_control(cp):
                    continue
                target = family
                if loc != 'ar' and cp in RANGES['arabic']:
                    target = ISLANDS[role]
                    islands[ch] += n
                if not resolves(cp, cover[target]):
                    missing.append(f'{u(cp)} {unicodedata.name(ch, "?")} x{n} ({target})')
            entry['roles'][role] = {'family': family, 'missing': missing,
                                    'arabic_islands': ''.join(sorted(islands, key=ord))}
            if missing and role in REQUIRED_ROLES:
                problems.append(f'{loc} {role} ({family}): ' + '; '.join(missing[:12]))
            log(f'  {loc} {role:8s} {family:15s} '
                + ('all covered' if not missing else f'{len(missing)} missing: ' + '; '.join(missing[:6]))
                + (f'; Arabic islands {"".join(sorted(islands, key=ord))} -> {ISLANDS[role]}' if islands else ''))
        result[loc] = entry
    if problems:
        fail('copy not covered by the Design 2 faces (widen SUBSETS deliberately):\n  ' + '\n  '.join(problems))
    return result


# ---------------------------------------------------------------------------------------------
# 5. fallback metrics

FORM_TAGS = {'isolated': 'isol', 'final': 'fina', 'initial': 'init', 'medial': 'medi'}


def arabic_tables() -> tuple[dict, dict]:
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
    using the presentation form for the joining context and the mandatory lam-alef ligatures."""
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
                return font, (f'{path} #{i}' if path.suffix.lower() == '.ttc' else str(path))
        return None


def sample_frequencies(copy: dict, subset: str, locales: tuple) -> tuple[collections.Counter, bool]:
    """Relative frequency of what a face draws in the approved copy (each locale weighted equally):
    characters for Latin/Cyrillic, shaped glyph forms (marks excluded) for Arabic."""
    arabic = subset == 'arabic'
    ranges = subset_cps(subset)
    freq: collections.Counter = collections.Counter()
    for loc in locales:
        counts: collections.Counter = collections.Counter()
        for text in copy['approved'][loc]:
            if arabic:
                for measure, base, is_mark in shape_arabic(text):
                    if base in ranges and not is_mark:
                        counts[(measure, base)] += 1
            else:
                counts.update(c for c in text if ord(c) in ranges and not is_format_control(ord(c)))
        total = sum(counts.values()) or 1
        for key, n in counts.items():
            freq[key] += n / total
    return freq, arabic


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
    """The metrics browsers use for the web fonts (all of them set USE_TYPO_METRICS)."""
    hhea, os2 = font['hhea'], font['OS/2']
    if os2.fsSelection & (1 << 7):
        return os2.sTypoAscender, -os2.sTypoDescender, os2.sTypoLineGap, font['head'].unitsPerEm
    return hhea.ascent, -hhea.descent, hhea.lineGap, font['head'].unitsPerEm


def face_spec(face: str) -> FileSpec:
    return next(f for f in FILES if f.face == face)


def web_instance(face: str, weight: int, cache: dict) -> TTFont:
    if face == 'sans-arabic':
        return static_at(FILE[f'sans-arabic-{weight}'], weight, cache)
    if face == 'mono':
        return static_at(FILE['mono-latin' if weight == 500 else 'mono-latin-400'], weight, cache)
    return static_at(face_spec(face), weight, cache)


def compute_fallbacks(copy: dict, cache: dict) -> list[dict]:
    """size-adjust = average advance of the web font / average advance of the local font over the
    sample copy; the overrides are the web font's vertical metrics divided by size-adjust, because
    browsers scale override values by size-adjust as well."""
    index = LocalFontIndex()
    out = []
    for family, members in FALLBACKS.items():
        for member in members:
            sample_subset = member.subset or ('arabic' if member.face in ('sans-arabic', 'naskh') else 'latin+cyrillic')
            freq, arabic = sample_frequencies(copy, sample_subset, member.sample)
            for weight, locals_ in member.weights.items():
                web = web_instance(member.face, weight, cache)
                web_avg, web_missing = average_advance(web, freq, arabic)
                asc, desc, gap, upm = vertical_metrics(web)
                candidates = []
                extra = COMPARE_ONLY.get((member.face, weight), ())
                for lf in locals_ + extra:
                    found = index.find((lf.name,) + lf.aliases)
                    if not found:
                        candidates.append({'local': lf.name, 'available': False, 'compare_only': lf in extra})
                        continue
                    font, where = found
                    local_avg, local_missing = average_advance(font, freq, arabic)
                    candidates.append({'local': lf.name, 'available': True, 'file': where, 'compare_only': lf in extra,
                                       'size_adjust': web_avg / local_avg if local_avg else None, 'missing': local_missing})
                primary = next((c for c in candidates if c.get('available') and not c['compare_only']), None)
                if primary is None:
                    fail(f'{family} {weight}: none of {[lf.name for lf in locals_]} is installed on this Mac')
                size_adjust = primary['size_adjust']
                out.append({
                    'family': family, 'weight': weight, 'face': member.face,
                    'imitates': f'{FACES[member.face].internal} {weight}',
                    'sample': '+'.join(member.sample) + (' shaped forms' if arabic else ' characters'),
                    'src': [n for lf in locals_ for n in (lf.name,) + lf.aliases],
                    'tuned_for': primary['local'],
                    'size_adjust': size_adjust,
                    'ascent_override': asc / upm / size_adjust,
                    'descent_override': desc / upm / size_adjust,
                    'line_gap_override': gap / upm / size_adjust,
                    'unicode_range': subset_spec(member.subset) if member.subset else None,
                    'web_metrics': {'ascent': asc, 'descent': desc, 'line_gap': gap, 'upm': upm,
                                    'avg_advance_em': round(web_avg, 5), 'missing': web_missing},
                    'candidates': candidates,
                })
                alts = ', '.join(f"{c['local']} {pct(c['size_adjust'])}" for c in candidates
                                 if c.get('available') and c is not primary and c.get('size_adjust'))
                log(f'  {family} {weight}' + (f' [{member.subset}]' if member.subset else '')
                    + f': size-adjust {pct(size_adjust)} vs {primary["local"]}' + (f' (others: {alts})' if alts else ''))
    return out


# ---------------------------------------------------------------------------------------------
# 6. outputs

def css(files: list[dict], fallbacks: list[dict]) -> str:
    by_key = {f['key']: f for f in files}
    lines = [
        '/*',
        ' * Design 2 web fonts. GENERATED by scripts/fonts/build-d2.py: do not edit by hand.',
        ' * Rebuild: .venv-fonts/bin/python -I scripts/fonts/build-d2.py (subsets in public/fonts/d2,',
        ' * keys and URLs in public/fonts/d2/manifest.json, preloads in App\\Design\\D2\\Fonts).',
        ' * Not bundled into app.css: d2::layouts.app inlines this file (App\\Design\\D2\\FontFaces), so',
        ' * the /fonts/d2/ URLs resolve against the page origin behind the Vite dev server as well.',
        ' *',
        ' * SIL Open Font License 1.1, instanced, subset and renamed (OFL Reserved Font Names); the',
        ' * licences ship in resources/fonts/src{,-d2}/<family>/OFL.txt and in every file\'s name table.',
    ]
    for face in FACES.values():
        lines.append(f' *   {face.internal}: {face.upstream}, {face.role}.')
    lines += [
        ' *',
        ' * Faces of one family that share a weight band carry the same font-weight value (browsers',
        ' * group faces by descriptors before combining them by unicode-range); where ranges overlap',
        ' * (U+200C-200F) the later rule wins. "D2 Sans Arabic" and "D2 Naskh" include the Latin and',
        ' * Cyrillic files so Latin text, digits and spaces on Arabic pages render in D2 Sans / D2 Serif.',
        ' * Fallback faces scale a local font (size-adjust) and take the web font\'s ascent, descent and',
        ' * line gap, so the swap to the web font does not move the layout. Tuned for the first local()',
        ' * font; storage/app/d2/fonts/font-build.json lists what the other local fonts would need.',
        ' */',
        '/* stylelint-disable declaration-property-value-allowed-list -- @font-face names its family */',
    ]
    for family, members in FAMILIES.items():
        lines += ['', f'/* {family} */']
        for key, weight in members:
            f = by_key[key]
            lines += [
                '',
                '@font-face {',
                f'  font-family: "{family}";',
                '  font-style: normal;',
                f'  font-weight: {weight};',
                '  font-display: swap;',
                f'  src: url("{f["url"]}") format("woff2");',
                f'  unicode-range: {f["unicode_range"]};',
                '}',
            ]
    current = None
    for fb in fallbacks:
        if fb['family'] != current:
            current = fb['family']
            lines += ['', f'/* {current} */']
        src = ', '.join(f'local("{n}")' for n in fb['src'])
        lines += [
            '',
            '@font-face {',
            f'  font-family: "{fb["family"]}";',
            '  font-style: normal;',
            f'  font-weight: {fb["weight"]};',
            f'  src: {src};',
            f'  size-adjust: {pct(fb["size_adjust"])};',
            f'  ascent-override: {pct(fb["ascent_override"])};',
            f'  descent-override: {pct(fb["descent_override"])};',
            f'  line-gap-override: {pct(fb["line_gap_override"])};',
        ]
        if fb['unicode_range']:
            lines.append(f'  unicode-range: {fb["unicode_range"]};')
        lines.append('}')
    text = '\n'.join(lines) + '\n'
    if re.search(r'#[0-9a-fA-F]{3}', text):
        fail('generated fonts.css would contain a hex colour')
    return text


def page_budget(files: list[dict]) -> dict:
    by_key = {f['key']: f['bytes'] for f in files}
    plans = {
        'en': ['sans-latin', 'mono-latin'],
        'ru': ['sans-latin', 'sans-cyrillic', 'mono-latin', 'mono-cyrillic'],
        'ar': ['sans-latin', 'sans-arabic-400', 'sans-arabic-500', 'sans-arabic-600', 'sans-arabic-700'],
    }
    return {loc: {'files': keys, 'bytes': sum(by_key[k] for k in keys)} for loc, keys in plans.items()}


def write_if_changed(path: Path, text: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    if not path.exists() or path.read_text(encoding='utf-8') != text:
        path.write_text(text, encoding='utf-8')
    log(f'  wrote {path.relative_to(ROOT)}')


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.split('\n', 1)[0])
    parser.add_argument('--fetch', action='store_true', help='download missing src-d2 sources from the pinned google/fonts commit')
    parser.add_argument('--css', type=Path, default=CSS_PATH, help='where to write the @font-face CSS (default: %(default)s)')
    args = parser.parse_args()

    log('1. sources')
    verify_sources(args.fetch)

    cache: dict = {}
    log('2-3. instances and subsets -> public/fonts/d2')
    files, problems = build_files(cache)
    if problems:
        fail('subset checks failed:\n  ' + '\n  '.join(problems))
    log('  variable instances reproduce the original advance widths at every served weight')

    log('4. copy coverage')
    copy = collect_copy()
    coverage = check_coverage(copy, files)

    log('5. fallback metrics')
    fallbacks = compute_fallbacks(copy, cache)

    log('6. outputs')
    manifest = {f['key']: f['url'] for f in sorted(files, key=lambda f: f['key'])}
    write_if_changed(MANIFEST_PATH, json.dumps(manifest, indent=2) + '\n')
    write_if_changed(args.css.resolve(), css(files, fallbacks))
    report = {
        'generated_by': 'scripts/fonts/build-d2.py',
        'google_fonts_commit': GOOGLE_FONTS_COMMIT,
        'layout_features': LAYOUT_FEATURES,
        'subsets': SUBSETS,
        'families': {k: [list(m) for m in v] for k, v in FAMILIES.items()},
        'files': [{k: v for k, v in f.items() if k != 'cmap'} for f in files],
        'pages_typical': page_budget(files),
        'coverage': coverage,
        'fallbacks': fallbacks,
    }
    write_if_changed(REPORT_PATH, json.dumps(report, indent=2, ensure_ascii=False) + '\n')
    total = sum(f['bytes'] for f in files)
    log(f'done: {len(files)} WOFF2 files, {kb(total)} KB in total; largest {max(f["bytes"] for f in files) / 1024:.1f} KB')


if __name__ == '__main__':
    main()
