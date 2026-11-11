<?php

namespace App\Design\D2;

/**
 * The home hero's document stage (Design 2.1 brief §4 D15–D17, §6 "Hero document switch"; owner: hero).
 *
 * One canvas of 620×560 design px (scaled to the stage width by CSS alone) shows ONE sample document at a time
 * (rotation 0 at rest) on a small file of two blank offset sheets (D15: no back sheet carries text, so nothing
 * readable is ever cut off). The other documents wait in the same place, hidden, for the pager.
 *
 * Callouts (D16) are laid out here, on the server, from the measured key fields of every document
 * (resources/js/d2/hero/callout-layout.json, written by scripts/d2/callout-layout.mjs):
 *   dot     at the edge of its field, in the empty paper beside it (the side of the callout), never on text;
 *   line    one straight horizontal leader from the dot out of the paper (the fields reach their page's padding on
 *           that side, so the line crosses empty paper only) to the label;
 *   label   in the clear margin beside the file (a column outside the pile, never over a paper edge), its first
 *           text line on the leader (its last line for the upper one of two close callouts on the same side).
 * Two layouts: "wide" (from 768 px: labels) and "phone" (below 768 px: the document larger, a numbered marker in
 * place of each label, the label text in the legend under the document). Everything is in canvas px measured from
 * the canvas's inline-start edge, so RTL mirrors without a second table; SVG paths are written in physical px.
 */
final class HeroStage
{
    public const W = 620;

    /** The tallest canvas (the phone layout); the wide layout is shorter (LAYOUT[...]['h']). */
    public const H = 540;

    public const LAYOUTS = ['wide', 'phone'];

    /**
     * The box a document is fitted into (canvas px), and per-document scales that differ from the fit.
     * The cheque is a short, wide document: it is drawn a little wider than the file and keeps its labels clear.
     *
     * h: the canvas height of the layout (the stage's aspect ratio is 620 / h); dx: a document moved along the inline
     * axis from the centre (canvas px; its file moves with it).
     *
     * @var array<string, array{h: int, fit: array{0: int, 1: int}, scale: array<string, float>, file: array{0: int, 1: int}, sheets: list<array{0: float, 1: float, 2: float}>, gap: float, dot: float, end: float}>
     */
    public const LAYOUT = [
        'wide' => [
            'h' => 440,
            'fit' => [260, 400],
            // The cheque (all three labels on its end side) is larger and sits towards the start with its file.
            'scale' => ['cheque' => 0.66],
            'dx' => ['cheque' => -78],
            // The two blank sheets under the document (width, height) and their offsets [x, y, rotation°].
            'file' => [260, 339],
            'sheets' => [[-5, 7, -1.6], [5, -5, 1.2]],
            'gap' => 18,   // pile edge → the label's facing edge
            'dot' => 7,    // field edge → dot centre
            'end' => 6,    // the leader stops this far before the label
        ],
        'phone' => [
            'h' => 540,
            'fit' => [400, 470],
            'scale' => ['cheque' => 0.84],
            'dx' => [],
            'file' => [360, 470],
            'sheets' => [[-7, 9, -1.6], [7, -7, 1.2]],
            'gap' => 10,
            'dot' => 10,
            'end' => 0,
        ],
    ];

    /**
     * Callout fine-tuning (hero only): side = the label's side when it differs from Docs::CALLOUTS; fy = where on the
     * field's height the dot sits (0 top … 1 bottom, default 0.5); align = which text line of the label meets the
     * leader (first, default, or last: the upper label of a close pair on one side grows upwards, away from it).
     *
     * @var array<string, array<string, array{fy?: float, align?: string}>>
     */
    public const TUNE = [
        'cheque' => [
            // The reason on the stapled memo: its leader runs through the memo's empty paper and above the cheque.
            'memo' => ['side' => 'end', 'align' => 'last'],
            'figures' => ['fy' => 0.75],
        ],
    ];

    /**
     * Phones: two markers on the same side keep at least this far apart, centre to centre (canvas px): a 24 px
     * marker and 12 px of air at the smallest canvas scale (a 360 px phone: 320 / 620). Closer ones are spread
     * apart and their leaders take a short step outside the paper.
     */
    public const PHONE_SPACING = (24 + 12) / (320 / 620);

    /**
     * From 1280 px the Russian H1 runs into the stage's top inline-start corner (its third line, canvas y ≈ 117–192,
     * up to x ≈ 23; the English and Arabic H1 stay in their column). A start-side label whose lines may reach that
     * band keeps this much room clear at the canvas edge, in the languages listed (scripts/d2/callout-layout.mjs
     * --check measures 12 px between every label and the H1 in every language from 1280 px).
     */
    public const H1_BAND = [95, 215];

    public const H1_LOCALES = ['ru'];

    public const H1_ROOM = 40;

    /** Natural size of a document box (Docs::SIZE) as [w, h]. */
    public static function size(string $type): array
    {
        return Docs::SIZE[$type] ?? [360, 470];
    }

    public static function scale(string $type, string $layout): float
    {
        $l = self::LAYOUT[$layout];
        if (isset($l['scale'][$type])) {
            return $l['scale'][$type];
        }
        [$w, $h] = self::size($type);

        return round(min($l['fit'][0] / $w, $l['fit'][1] / $h), 3);
    }

    /** A document's move along the inline axis from the centre (canvas px); its file moves with it. */
    public static function dx(string $type, string $layout): float
    {
        return (float) (self::LAYOUT[$layout]['dx'][$type] ?? 0);
    }

    /** The document box on the canvas: [x (from the inline-start edge), y, w, h]; centred, then moved by dx. */
    public static function box(string $type, string $layout): array
    {
        [$w, $h] = self::size($type);
        $s = self::scale($type, $layout);

        return [(self::W - $w * $s) / 2 + self::dx($type, $layout), (self::LAYOUT[$layout]['h'] - $h * $s) / 2, $w * $s, $h * $s];
    }

    /** Inline extents [start, end] of the file of blank sheets under a document (rotation included). */
    public static function file(string $layout, ?string $type = null): array
    {
        $shift = $type ? self::dx($type, $layout) : 0;
        $l = self::LAYOUT[$layout];
        [$fw, $fh] = $l['file'];
        $start = INF;
        $end = -INF;
        foreach ($l['sheets'] as [$dx, , $rot]) {
            $a = deg2rad(abs($rot));
            $half = ($fw / 2) * cos($a) + ($fh / 2) * sin($a);
            $start = min($start, self::W / 2 + $shift + $dx - $half);
            $end = max($end, self::W / 2 + $shift + $dx + $half);
        }

        return [$start, $end];
    }

    /** Inline extents [start, end] of everything on the paper side: the document and the file under it. */
    public static function pile(string $type, string $layout): array
    {
        [$x, , $w] = self::box($type, $layout);
        [$fs, $fe] = self::file($layout, $type);

        return [min($x, $fs), max($x + $w, $fe)];
    }

    /**
     * Measured key fields (natural document px; x from the document's inline-start edge) for a language, or null
     * when the table is missing or was measured with other document copy (the copy hash differs).
     *
     * @return array<string, array<string, array{0: float, 1: float, 2: float, 3: float}>>|null
     */
    public static function fields(string $locale): ?array
    {
        $table = once(function () {
            $file = resource_path('js/d2/hero/callout-layout.json');

            return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
        });
        $fields = $table['fields'][$locale] ?? null;
        if (! is_array($fields) || ($table['hash'][$locale] ?? null) !== self::copyHash($locale)) {
            return null;
        }

        return $fields;
    }

    /** What the measured fields depend on: the sample documents' copy in that language. */
    public static function copyHash(string $locale): string
    {
        $copy = trans('proposed.d2.docs', [], $locale);

        return md5((string) json_encode(is_array($copy) ? $copy : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * The callouts of one document, laid out for both layouts.
     *
     * @return list<array{id: string, n: int, label: string, side: string, align: string, wide: array, phone: array}>
     */
    public static function callouts(string $type, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $rtl = $locale === 'ar';
        $fields = self::fields($locale)[$type] ?? [];
        [$nw, $nh] = self::size($type);
        $phys = fn (float $x) => $rtl ? self::W - $x : $x;
        $out = [];
        foreach (Docs::callouts($type, $locale) as $i => $c) {
            $tune = self::TUNE[$type][$c['id']] ?? [];
            // The field: measured, or (stale or missing table) a point at the old anchor, so the page still renders.
            $f = $fields[$c['id']] ?? [$c['x'] / 100 * $nw, $c['y'] / 100 * $nh, 0, 0];
            $item = ['id' => $c['id'], 'n' => $i + 1, 'label' => $c['label'], 'side' => $tune['side'] ?? $c['side'], 'align' => $tune['align'] ?? 'first'];
            foreach (self::LAYOUTS as $layout) {
                $l = self::LAYOUT[$layout];
                $s = self::scale($type, $layout);
                [$bx, $by] = self::box($type, $layout);
                [$ps, $pe] = self::pile($type, $layout);
                $start = $item['side'] === 'start';
                $dotX = $start ? $bx + $f[0] * $s - $l['dot'] : $bx + ($f[0] + $f[2]) * $s + $l['dot'];
                $y = $by + ($f[1] + $f[3] * ($tune['fy'] ?? 0.5)) * $s;
                // The label's facing edge (canvas inline px) and the room to the canvas edge on its side.
                $edge = $start ? $ps - $l['gap'] : $pe + $l['gap'];
                $room = $start ? $edge : self::W - $edge;
                if ($layout === 'wide' && $start && in_array($locale, self::H1_LOCALES, true)) {
                    $lines = $item['align'] === 'last' ? [$y - 60, $y + 12] : [$y - 12, $y + 60];
                    if ($lines[0] < self::H1_BAND[1] && $lines[1] > self::H1_BAND[0]) {
                        $room -= self::H1_ROOM;
                    }
                }
                $item[$layout] = [
                    'start' => $start,
                    'dotX' => $dotX,
                    'dotY' => $y,
                    'y' => $y,
                    'edge' => $edge,
                    'room' => $room,
                    'knee' => $start ? $ps - $l['gap'] / 2 : $pe + $l['gap'] / 2,
                    'lineEnd' => $start ? $edge + $l['end'] : $edge - $l['end'],
                ];
            }
            $out[] = $item;
        }
        // Phones: spread markers on the same side that would come too close (pairs, from the top down).
        foreach (['start', 'end'] as $side) {
            $idx = array_keys(array_filter($out, fn ($c) => $c['side'] === $side));
            usort($idx, fn ($a, $b) => $out[$a]['phone']['y'] <=> $out[$b]['phone']['y']);
            // Relax until every neighbouring pair keeps its distance (a few rounds for three markers).
            for ($round = 0; $round < 24; $round++) {
                $moved = false;
                for ($j = 1; $j < count($idx); $j++) {
                    $a = &$out[$idx[$j - 1]]['phone'];
                    $b = &$out[$idx[$j]]['phone'];
                    $short = self::PHONE_SPACING - ($b['y'] - $a['y']);
                    if ($short > 0.01) {
                        $a['y'] -= $short / 2;
                        $b['y'] += $short / 2;
                        $moved = true;
                    }
                    unset($a, $b);
                }
                if (! $moved) {
                    break;
                }
            }
        }
        foreach ($out as &$item) {
            foreach (self::LAYOUTS as $layout) {
                $g = $item[$layout];
                $r = fn (float $v) => round($v, 1);
                $dot = [$r($phys($g['dotX'])), $r($g['dotY'])];
                // One straight leader; a short step outside the paper when the label or marker was moved.
                $d = abs($g['y'] - $g['dotY']) < 0.05
                    ? sprintf('M%.1f %.1fH%.1f', $dot[0], $dot[1], $r($phys($g['lineEnd'])))
                    : sprintf('M%.1f %.1fH%.1fV%.1fH%.1f', $dot[0], $dot[1], $r($phys($g['knee'])), $r($g['y']), $r($phys($g['lineEnd'])));
                $item[$layout] = [
                    'dot' => $dot,
                    'y' => round($g['y'], 1),
                    'inset' => round($g['start'] ? self::W - $g['edge'] : $g['edge'], 1),
                    'room' => round($g['room'], 1),
                    'd' => $d,
                ];
            }
        }
        unset($item);

        return $out;
    }

    /** The locale-independent geometry (document scales, the file's sheets), as CSS for the nonce'd style block. */
    public static function css(): string
    {
        $px = fn (float $v) => self::num($v, 2).'px';
        $rules = [];
        foreach (Docs::TYPES as $type) {
            $dx = fn (string $l) => $px(self::dx($type, $l));
            $rules[] = '.hero-doc--'.$type.'{--s-wide:'.self::scale($type, 'wide').';--s-phone:'.self::scale($type, 'phone').';--dx-wide:'.$dx('wide').';--dx-phone:'.$dx('phone').'}';
            // The file follows the document shown.
            $rules[] = '.hero-stage[data-active="'.$type.'"]{--fdx-wide:'.$dx('wide').';--fdx-phone:'.$dx('phone').'}';
        }
        foreach ([1, 2] as $n) {
            $vars = [];
            foreach (self::LAYOUTS as $layout) {
                [$fw, $fh] = self::LAYOUT[$layout]['file'];
                [$dx, $dy, $rot] = self::LAYOUT[$layout]['sheets'][$n - 1];
                $vars[] = "--w-{$layout}:".$px($fw).";--h-{$layout}:".$px($fh).";--x-{$layout}:".$px($dx).";--y-{$layout}:".$px($dy).";--r-{$layout}:{$rot}deg";
            }
            $rules[] = '.hero-sheet--'.$n.'{'.implode(';', $vars).'}';
        }

        return implode('', $rules);
    }

    /** A number for CSS: at most $decimals decimals, no trailing zeros ("0", "-78", "12.5"). */
    private static function num(float $v, int $decimals): string
    {
        $out = number_format($v, $decimals, '.', '');
        if (str_contains($out, '.')) {
            $out = rtrim(rtrim($out, '0'), '.');
        }

        return $out === '-0' ? '0' : $out;
    }

    /** The callout positions of every document in one language, as CSS (scoped to the stage's data-loc). */
    public static function calloutCss(string $locale): string
    {
        $px = fn (float $v) => self::num($v, 1).'px';
        $rules = [];
        foreach (Docs::TYPES as $type) {
            foreach (self::callouts($type, $locale) as $c) {
                $vars = [];
                foreach (self::LAYOUTS as $layout) {
                    $vars[] = "--i-{$layout}:".$px($c[$layout]['inset']).";--m-{$layout}:".$px($c[$layout]['room']).";--y-{$layout}:".$px($c[$layout]['y']);
                }
                $rules[] = '.hero-stage[data-loc="'.$locale.'"] .hero-doc--'.$type.' .hero-callout[data-callout="'.$c['id'].'"]{'.implode(';', $vars).'}';
            }
        }

        return implode('', $rules);
    }
}
