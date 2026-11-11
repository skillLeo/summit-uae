"""Font metrics for scripts/fonts/fontsource-d2.mjs (Design 2 fonts, brief 3.2). Do not run it by hand.

The Node script sends one JSON job on stdin and reads one JSON answer on stdout:

  in   {"files":     {key: absolute path of a WOFF2 file},
        "texts":     {locale: [string, ...]},                       the site copy that weights the averages
        "fallbacks": [{"id": str,
                       "face": [{"key": file key, "ranges": [[lo, hi], ...]}, ...],   web files, first match wins
                       "weight": int,                                the weight the web face is measured at
                       "kind": "chars" | "arabic" | "space",
                       "locales": [locale, ...],                     whose copy weights the average
                       "ranges": [[lo, hi], ...],                    the characters the fallback face draws
                       "locals": [local() name, ...]}]}              tuned for the first one installed
  out  {"files":     {key: {"cmap": [code point, ...], "upm", "ascent", "descent", "lineGap", "typo",
                            "family", "version", "axes": {tag: [min, default, max]}}},
        "fallbacks": {id: {"sizeAdjust", "ascentOverride", "descentOverride", "lineGapOverride",
                           "tunedFor", "localFile", "webAdvance", "localAdvance", "samples",
                           "missingWeb", "missingLocal", "alternatives": {local name: size-adjust}}}}

Method (the one scripts/fonts/build-d2.py used, measured at CLS <= 0.012 in production):
  size-adjust     average advance width of the web face / the local font's, over the site copy of the
                  locales the face serves (each locale weighted equally): characters for Latin and
                  Cyrillic, shaped glyph forms (presentation forms, lam-alef ligatures, marks excluded)
                  for Arabic, the space alone for the space faces;
  ascent-override and the others: the web face's vertical metrics (OS/2 typo metrics when the font sets
                  USE_TYPO_METRICS, else hhea) in em, divided by size-adjust, because browsers scale the
                  override values by size-adjust as well.
Variable files are measured at the requested weight (fontTools instancer). Local fonts are looked up by
full name and PostScript name (what CSS local() matches) in /System/Library/Fonts(/Supplemental).
Pure reads: no file is written. Run with the font venv: .venv-fonts/bin/python -I (fonttools, brotli).
"""

from __future__ import annotations

import collections
import json
import re
import sys
import unicodedata
from pathlib import Path

from fontTools.ttLib import TTCollection, TTFont
from fontTools.varLib import instancer

LOCAL_FONT_DIRS = (Path('/System/Library/Fonts'), Path('/System/Library/Fonts/Supplemental'))


# ------------------------------------------------------------------------------------------- web files

_fonts: dict[str, TTFont] = {}
_instances: dict[tuple[str, int], TTFont] = {}


def font(path: str) -> TTFont:
    if path not in _fonts:
        _fonts[path] = TTFont(path, recalcTimestamp=False)
    return _fonts[path]


def at_weight(path: str, weight: int) -> TTFont:
    """The face at one weight: a static file as it is, a variable file instanced at wght."""
    key = (path, weight)
    if key not in _instances:
        f = font(path)
        if 'fvar' in f:
            axes = {a.axisTag: a for a in f['fvar'].axes}
            location = {}
            for tag, axis in axes.items():
                value = weight if tag == 'wght' else axis.defaultValue
                location[tag] = min(max(value, axis.minValue), axis.maxValue)
            f = instancer.instantiateVariableFont(TTFont(path, recalcTimestamp=False), location, inplace=False,
                                                  optimize=False, updateFontNames=False)
        _instances[key] = f
    return _instances[key]


def vertical(f: TTFont) -> tuple[int, int, int, bool]:
    """Ascent, descent (positive), line gap in font units, and whether the typo metrics were used."""
    hhea, os2 = f['hhea'], f['OS/2']
    if os2.fsSelection & (1 << 7):
        return os2.sTypoAscender, -os2.sTypoDescender, os2.sTypoLineGap, True
    return hhea.ascent, -hhea.descent, hhea.lineGap, False


def describe(path: str) -> dict:
    f = font(path)
    asc, desc, gap, typo = vertical(f)
    name = f['name']
    version = name.getDebugName(5) or ''
    m = re.search(r'(\d+\.\d+)', version)
    return {
        'cmap': sorted(f.getBestCmap()),
        'upm': f['head'].unitsPerEm,
        'ascent': asc, 'descent': desc, 'lineGap': gap, 'typo': typo,
        'family': name.getDebugName(16) or name.getDebugName(1) or '',
        'version': m.group(1) if m else version,
        'axes': {a.axisTag: [a.minValue, a.defaultValue, a.maxValue] for a in f['fvar'].axes} if 'fvar' in f else {},
    }


# ------------------------------------------------------------------------------------------- Arabic shaping

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
    """Approximate Arabic shaping: (code point to measure, base code point, is_mark) per glyph, with
    the presentation form of the joining context and the mandatory lam-alef ligatures."""
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


# ------------------------------------------------------------------------------------------- local fonts

class LocalFonts:
    def __init__(self) -> None:
        self._index: dict[str, tuple[Path, int]] | None = None

    def find(self, name: str) -> tuple[TTFont, str] | None:
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
                    for i, member in enumerate(members):
                        try:
                            table = member['name']
                        except Exception:
                            continue
                        for name_id in (4, 6):
                            value = table.getDebugName(name_id)
                            if value:
                                self._index.setdefault(value, (path, i))
        if name not in self._index:
            return None
        path, i = self._index[name]
        f = TTCollection(path).fonts[i] if path.suffix.lower() == '.ttc' else TTFont(path)
        return f, (f'{path} #{i}' if path.suffix.lower() == '.ttc' else str(path))


# ------------------------------------------------------------------------------------------- averages

def in_ranges(cp: int, ranges: list) -> bool:
    return any(lo <= cp <= hi for lo, hi in ranges)


def frequencies(job: dict, texts: dict) -> collections.Counter:
    """Relative frequency of what the face draws in the copy (each locale weighted equally)."""
    freq: collections.Counter = collections.Counter()
    for loc in job['locales']:
        counts: collections.Counter = collections.Counter()
        for text in texts.get(loc, []):
            if job['kind'] == 'arabic':
                for measure, base, is_mark in shape_arabic(text):
                    if not is_mark and in_ranges(base, job['ranges']):
                        counts[(measure, base)] += 1
            elif job['kind'] == 'space':
                counts.update((0x20, 0x20) for c in text if c == ' ')
            else:
                counts.update((ord(c), ord(c)) for c in text
                              if in_ranges(ord(c), job['ranges']) and unicodedata.category(c) not in ('Cc', 'Cf'))
        total = sum(counts.values()) or 1
        for key, n in counts.items():
            freq[key] += n / total
    return freq


def web_lookup(job: dict, files: dict):
    """Code point -> advance in em of the composite web face (members checked in the given order,
    as a browser checks the @font-face rules of one family: the first whose unicode-range matches)."""
    members = [(m['ranges'], at_weight(files[m['key']], job['weight'])) for m in job['face']]
    cmaps = [(ranges, f.getBestCmap(), f['hmtx'], f['head'].unitsPerEm) for ranges, f in members]

    def advance(cp: int):
        for ranges, cmap, hmtx, upm in cmaps:
            if in_ranges(cp, ranges):
                gid = cmap.get(cp)
                return None if gid is None else hmtx[gid][0] / upm
        return None

    return advance


def average(freq: collections.Counter, lookup) -> tuple[float, list[str]]:
    total = weight = 0.0
    missing: set[str] = set()
    for (measure, base), w in freq.items():
        value = lookup(measure)
        if value is None and measure != base:
            value = lookup(base)
        if value is None:
            missing.add(f'U+{base:04X}')
            continue
        total += value * w
        weight += w
    return (total / weight if weight else 0.0), sorted(missing)


def local_lookup(f: TTFont):
    cmap, hmtx, upm = f.getBestCmap(), f['hmtx'], f['head'].unitsPerEm

    def advance(cp: int):
        gid = cmap.get(cp)
        return None if gid is None else hmtx[gid][0] / upm

    return advance


def fallback(job: dict, files: dict, texts: dict, local_fonts: LocalFonts) -> dict:
    freq = frequencies(job, texts)
    web_avg, web_missing = average(freq, web_lookup(job, files))
    # every file of one web family shares its vertical metrics: the first member's are the family's
    reference = at_weight(files[job['face'][0]['key']], job['weight'])
    asc, desc, gap, _ = vertical(reference)
    em = reference['head'].unitsPerEm
    candidates = []
    for name in job['locals']:
        found = local_fonts.find(name)
        if not found:
            continue
        local, where = found
        local_avg, local_missing = average(freq, local_lookup(local))
        if local_avg:
            candidates.append((name, where, local_avg, local_missing))
    if not candidates or not web_avg:
        raise SystemExit(f'{job["id"]}: none of {job["locals"]} is installed, or the copy has no sample')
    name, where, local_avg, local_missing = candidates[0]
    size = web_avg / local_avg
    return {
        'sizeAdjust': size,
        'ascentOverride': asc / em / size,
        'descentOverride': desc / em / size,
        'lineGapOverride': gap / em / size,
        'tunedFor': name,
        'localFile': where,
        'webAdvance': round(web_avg, 6),
        'localAdvance': round(local_avg, 6),
        'samples': len(freq),
        'missingWeb': web_missing,
        'missingLocal': local_missing,
        'alternatives': {c[0]: round(web_avg / c[2], 6) for c in candidates[1:] if c[0] != name},
    }


def main() -> None:
    job = json.load(sys.stdin)
    files = job['files']
    out = {'files': {key: describe(path) for key, path in sorted(files.items())}, 'fallbacks': {}}
    local_fonts = LocalFonts()
    for spec in job['fallbacks']:
        out['fallbacks'][spec['id']] = fallback(spec, files, job['texts'], local_fonts)
    json.dump(out, sys.stdout, ensure_ascii=False, sort_keys=True)


if __name__ == '__main__':
    main()
