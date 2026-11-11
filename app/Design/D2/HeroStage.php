<?php

namespace App\Design\D2;

/**
 * The home hero's document stage (Design 2.1 brief §4 D15–D17, §6 "Hero document switch"; owner: hero; the owner's
 * "option 3": the brief's rules with the Phase 1 realism back).
 *
 * One canvas 620 design px wide (scaled to the stage width by CSS alone) holds a PILE of the six sample documents,
 * each in its own hand-placed pose (an offset and a few degrees of rotation, POSE), stacked by depth. The document
 * on top sits straight (rotation 0) at the pile's centre and shows its content; every sheet behind it shows only its
 * paper (D15: the stage hides its ink, so nothing readable is ever cut off at a sheet's edge). All documents share
 * one scale per layout (the cheque is drawn a little smaller, so it is about as wide as the A4 pages), so the back
 * sheets keep their real proportions: the green cheque with its stapled memo, the A4 contract and notice, the
 * slightly larger tenancy contract, the narrow transfer receipt and the phone screenshot of the messages.
 *
 * Callouts (D16) are laid out here, on the server, from the measured key fields of every document
 * (resources/js/d2/hero/callout-layout.json, written by scripts/d2/callout-layout.mjs), for the document's top pose:
 *   dot     at the inline-end edge of its field, in the empty paper beside it, never on text;
 *   line    one horizontal leader from the dot out of the paper (the fields reach their page's padding on that side,
 *           so the line crosses empty paper, then blank back sheets) to the label; when two labels would come closer
 *           than 12 px it takes one short vertical step in the clear gap beside the pile;
 *   label   in the clear margin on the inline-end side of the pile (never over any sheet; away from the H1, which
 *           sits on the inline-start side), its first, middle or last text line on the leader.
 * Two layouts: "wide" (from 768 px: labels) and "phone" (below 768 px: a larger pile, a numbered marker in place of
 * each label, the label text in the legend under the document). Everything is in canvas px measured from the
 * canvas's inline-start edge, so RTL mirrors without a second table; SVG paths are written in physical px.
 */
final class HeroStage
{
    public const W = 620;

    public const LAYOUTS = ['wide', 'phone'];

    /**
     * The owner's autoplay (home hero only): how long (ms) a document stays on top once its paper has settled and its
     * callouts are drawn, before the next one is lifted out of the pile (the shown pager segment fills meanwhile).
     */
    public const DWELL = 2800;

    /**
     * Per layout:
     *   h      the canvas height (the stage's aspect ratio is 620 / h);
     *   k      the pile's size against the wide pile (poses and scales are multiplied by it);
     *   start  the room between the canvas's inline-start edge and the pile (the H1 side);
     *   gap    pile edge → the label's facing edge; dot: field edge → dot centre; end: the leader stops this far
     *          before the label; knee: where a moved leader takes its vertical step, this far beyond the pile;
     *   mark   the label box on screen: lh, one text line (px), air, the least space between two labels (px), and
     *          minK, the smallest canvas scale of the layout (588 / 620 from 1280 px; a 320 px phone);
     *   marker phones: a numbered marker of this many screen px instead of a text label.
     *
     * @var array<string, array{h: int, k: float, start: float, gap: float, dot: float, end: float, knee: float, lh: float, air: float, minK: float, marker?: float}>
     */
    public const LAYOUT = [
        'wide' => ['h' => 510, 'k' => 1.0, 'start' => 36, 'gap' => 16, 'dot' => 7, 'end' => 4, 'knee' => 5, 'lh' => 22.5, 'air' => 12, 'minK' => 588 / 620],
        'phone' => ['h' => 650, 'k' => 1.28, 'start' => 8, 'gap' => 10, 'dot' => 10, 'end' => 0, 'knee' => 4, 'lh' => 24, 'air' => 12, 'minK' => 320 / 620, 'marker' => 24],
    ];

    /** The documents' scale in the wide pile (k 1): paper documents, and the cheque (about the A4 pages' width). */
    public const SCALE = ['paper' => 0.94, 'cheque' => 0.8];

    /**
     * Each document's place in the pile when it is not on top: [dx, dy, rotation°] from the pile's centre (wide canvas
     * px, inline axis; × LAYOUT k), as if put down by hand. The document on top sits at the centre, rotation 0.
     * Chosen so every back sheet shows a recognisable edge: the cheque's long green edge below and beside the pages,
     * the phone's dark frame at the inline-end top, the receipt at the inline-start bottom, the larger tenancy page
     * around the others; the whole pile stays clear of the H1 side and of the label margin.
     *
     * @var array<string, array{0: float, 1: float, 2: float}>
     */
    public const POSE = [
        'cheque' => [0, 112, -1.8],
        'contract' => [-14, -8, -2.4],
        'tenancy' => [16, 10, 2.6],
        'transfer' => [-40, 24, -7.5],
        'messages' => [58, -26, 6.5],
        'notice' => [8, -4, 3.2],
    ];

    /**
     * Callout fine-tuning (hero only): fy = where on the field's height the dot sits (0 top … 1 bottom, default 0.5);
     * below = put the dot (and its leader) in the clear gap between the field and that field below it (a field
     * whose row continues with other text: the notice's case number sits beside its department).
     *
     * @var array<string, array<string, array{fy?: float, below?: string}>>
     */
    public const TUNE = [
        'cheque' => [
            // The reason on the stapled memo: its leader runs through the memo's empty paper and above the cheque.
            'figures' => ['fy' => 0.75],
        ],
        'notice' => [
            'case' => ['below' => 'hearing'],
        ],
        'transfer' => [
            'wallet' => ['fy' => 0.3],
            'txid' => ['fy' => 0.75],
        ],
        'tenancy' => [
            'period' => ['fy' => 0.3],
            'rent' => ['fy' => 0.7],
        ],
    ];

    /** Natural size of a document box (Docs::SIZE) as [w, h]. */
    public static function size(string $type): array
    {
        return Docs::SIZE[$type] ?? [360, 470];
    }

    public static function scale(string $type, string $layout): float
    {
        return round(($type === 'cheque' ? self::SCALE['cheque'] : self::SCALE['paper']) * self::LAYOUT[$layout]['k'], 4);
    }

    /** A document's pile pose in a layout: [dx, dy, rotation°] from the pile's centre (canvas px, inline axis). */
    public static function pose(string $type, string $layout): array
    {
        [$dx, $dy, $rot] = self::POSE[$type] ?? [0, 0, 0];
        $k = self::LAYOUT[$layout]['k'];

        return [round($dx * $k, 2), round($dy * $k, 2), $rot];
    }

    /** Half extents [x, y] of a w × h rectangle turned by $rot degrees (its axis-aligned box). */
    private static function half(float $w, float $h, float $rot): array
    {
        $a = deg2rad(abs($rot));

        return [($w / 2) * cos($a) + ($h / 2) * sin($a), ($w / 2) * sin($a) + ($h / 2) * cos($a)];
    }

    /**
     * The pile around its centre: [start, end, top, bottom] offsets (canvas px) of everything on the paper side, every
     * document at its pile pose and at its top pose (so the label margin is clear whichever document is on top).
     */
    public static function extents(string $layout): array
    {
        $x0 = INF;
        $x1 = -INF;
        $y0 = INF;
        $y1 = -INF;
        foreach (Docs::TYPES as $type) {
            [$w, $h] = self::size($type);
            $s = self::scale($type, $layout);
            [$dx, $dy, $rot] = self::pose($type, $layout);
            foreach ([[$dx, $dy, $rot], [0, 0, 0]] as [$px, $py, $pr]) {
                [$hx, $hy] = self::half($w * $s, $h * $s, $pr);
                $x0 = min($x0, $px - $hx);
                $x1 = max($x1, $px + $hx);
                $y0 = min($y0, $py - $hy);
                $y1 = max($y1, $py + $hy);
            }
        }

        return [$x0, $x1, $y0, $y1];
    }

    /** The pile's centre on the canvas: [x from the inline-start edge, y]. */
    public static function centre(string $layout): array
    {
        $l = self::LAYOUT[$layout];
        [$x0, , $y0, $y1] = self::extents($layout);

        return [round($l['start'] - $x0, 2), round(($l['h'] - ($y1 - $y0)) / 2 - $y0, 2)];
    }

    /** Inline extents [start, end] of the whole pile on the canvas. */
    public static function pile(string $layout): array
    {
        [$x0, $x1] = self::extents($layout);
        [$cx] = self::centre($layout);

        return [$cx + $x0, $cx + $x1];
    }

    /** The document box on top: [x (from the inline-start edge), y, w, h]. */
    public static function box(string $type, string $layout): array
    {
        [$w, $h] = self::size($type);
        $s = self::scale($type, $layout);
        [$cx, $cy] = self::centre($layout);

        return [$cx - $w * $s / 2, $cy - $h * $s / 2, $w * $s, $h * $s];
    }

    /** The label margin: [facing edge (canvas inline px), room to the canvas's inline-end edge]. */
    public static function margin(string $layout): array
    {
        [, $pe] = self::pile($layout);
        $edge = $pe + self::LAYOUT[$layout]['gap'];

        return [round($edge, 1), round(self::W - $edge, 1)];
    }

    /** The measured table (resources/js/d2/hero/callout-layout.json), read once. */
    private static function table(): array
    {
        return once(function () {
            $file = resource_path('js/d2/hero/callout-layout.json');

            return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
        });
    }

    /**
     * Measured key fields (natural document px; x from the document's inline-start edge) for a language, or null
     * when the table is missing or was measured with other document copy (the copy hash differs).
     *
     * @return array<string, array<string, array{0: float, 1: float, 2: float, 3: float}>>|null
     */
    public static function fields(string $locale): ?array
    {
        $table = self::table();
        $fields = $table['fields'][$locale] ?? null;
        if (! is_array($fields) || ($table['hash'][$locale] ?? null) !== self::copyHash($locale)) {
            return null;
        }

        return $fields;
    }

    /**
     * Measured label heights (wide layout, canvas px at the smallest wide scale) for a language, or null when they
     * were measured with other copy or another label margin.
     *
     * @return array<string, array<string, float>>|null
     */
    public static function labelHeights(string $locale): ?array
    {
        $table = self::table();
        $labels = $table['labels'][$locale] ?? null;
        if (! is_array($labels) || ($table['hash'][$locale] ?? null) !== self::copyHash($locale)
            || abs((float) ($table['room'] ?? 0) - self::margin('wide')[1]) > 0.5) {
            return null;
        }

        return $labels;
    }

    /** What the measured fields depend on: the sample documents' copy in that language. */
    public static function copyHash(string $locale): string
    {
        $copy = trans('proposed.d2.docs', [], $locale);

        return md5((string) json_encode(is_array($copy) ? $copy : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * A label's height (canvas px, wide layout) without a measurement: its text broken into lines of the margin's
     * width at the smallest canvas scale, with generous average glyph widths per script.
     */
    public static function estimateHeight(string $label, string $locale): float
    {
        $l = self::LAYOUT['wide'];
        [, $room] = self::margin('wide');
        $em = 15 * ($locale === 'ar' ? 0.5 : ($locale === 'ru' ? 0.6 : 0.56));
        $width = $room * $l['minK'];
        $lines = 1;
        $run = 0.0;
        foreach (preg_split('/\s+/u', trim($label)) ?: [] as $word) {
            $w = mb_strlen($word) * $em;
            if ($run > 0 && $run + $em * 0.3 + $w > $width) {
                $lines++;
                $run = $w;
            } else {
                $run += ($run > 0 ? $em * 0.3 : 0) + $w;
            }
        }

        return $lines * $l['lh'] / $l['minK'];
    }

    /**
     * Where each label goes along the block axis (one side, labels sorted by leader y): its text line on the leader
     * (align first, middle or last; the combination with the most air, first preferred) and, when two labels would
     * still come closer than the layout's air, the leader's label end moved apart (a knee).
     *
     * @param  list<array{y: float, h: float}>  $items  leader y and label height (canvas px)
     * @return list<array{y: float, align: string}>
     */
    public static function stack(array $items, string $layout): array
    {
        $l = self::LAYOUT[$layout];
        $line = $l['lh'] / $l['minK'];
        $air = $l['air'] / $l['minK'];
        $lo = 2.0;
        $hi = $l['h'] - 2.0;
        $n = count($items);
        if ($n === 0) {
            return [];
        }
        $box = function (float $y, float $h, string $align) use ($line): array {
            // The label's own line (Arabic lines are taller): its height over its number of lines.
            $line = $h / max(1, round($h / $line));

            return match ($align) {
                'last' => [$y - $h + $line / 2, $y + $line / 2],
                'middle' => [$y - $h / 2, $y + $h / 2],
                default => [$y - $line / 2, $y + $h - $line / 2],
            };
        };
        $aligns = isset($l['marker']) ? ['middle'] : ['first', 'last', 'middle'];
        $best = null;
        $combos = [[]];
        for ($i = 0; $i < $n; $i++) {
            $next = [];
            foreach ($combos as $c) {
                foreach ($aligns as $a) {
                    $next[] = [...$c, $a];
                }
            }
            $combos = $next;
        }
        foreach ($combos as $combo) {
            $boxes = array_map(fn ($i) => $box($items[$i]['y'], $items[$i]['h'], $combo[$i]), range(0, $n - 1));
            $gap = INF;
            for ($i = 1; $i < $n; $i++) {
                $gap = min($gap, $boxes[$i][0] - $boxes[$i - 1][1]);
            }
            $gap = min($gap, $boxes[0][0] - $lo + $air, $hi - $boxes[$n - 1][1] + $air);
            $cost = array_sum(array_map(fn ($a) => ['first' => 0, 'last' => 1, 'middle' => 2][$a], $combo));
            $score = [min($gap, $air), -$cost];
            if ($best === null || $score > $best['score']) {
                $best = ['score' => $score, 'combo' => $combo];
            }
        }
        $out = array_map(fn ($i) => ['y' => $items[$i]['y'], 'align' => $best['combo'][$i]], range(0, $n - 1));
        // Relax: push neighbours apart (and away from the canvas edges) until every gap holds the air.
        for ($round = 0; $round < 60; $round++) {
            $moved = false;
            for ($i = 1; $i < $n; $i++) {
                $a = $box($out[$i - 1]['y'], $items[$i - 1]['h'], $out[$i - 1]['align']);
                $b = $box($out[$i]['y'], $items[$i]['h'], $out[$i]['align']);
                $short = $air + 0.5 - ($b[0] - $a[1]);
                if ($short > 0.01) {
                    $out[$i - 1]['y'] -= $short / 2;
                    $out[$i]['y'] += $short / 2;
                    $moved = true;
                }
            }
            $first = $box($out[0]['y'], $items[0]['h'], $out[0]['align']);
            if ($first[0] < $lo) {
                foreach ($out as &$o) {
                    $o['y'] += $lo - $first[0];
                }
                unset($o);
                $moved = true;
            }
            $last = $box($out[$n - 1]['y'], $items[$n - 1]['h'], $out[$n - 1]['align']);
            if ($last[1] > $hi) {
                foreach ($out as &$o) {
                    $o['y'] -= $last[1] - $hi;
                }
                unset($o);
                $moved = true;
            }
            if (! $moved) {
                break;
            }
        }

        return $out;
    }

    /**
     * The callouts of one document, laid out for both layouts (labels on the inline-end side of the pile).
     *
     * @return list<array{id: string, n: int, label: string, side: string, align: string, wide: array, phone: array}>
     */
    public static function callouts(string $type, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $rtl = $locale === 'ar';
        $fields = self::fields($locale)[$type] ?? [];
        $heights = self::labelHeights($locale)[$type] ?? [];
        [$nw, $nh] = self::size($type);
        $phys = fn (float $x) => $rtl ? self::W - $x : $x;
        $out = [];
        foreach (Docs::callouts($type, $locale) as $i => $c) {
            $tune = self::TUNE[$type][$c['id']] ?? [];
            // The field: measured, or (stale or missing table) a point at the old anchor, so the page still renders.
            $f = $fields[$c['id']] ?? [$c['x'] / 100 * $nw, $c['y'] / 100 * $nh, 0, 0];
            $fy = $f[1] + $f[3] * ($tune['fy'] ?? 0.5);
            if (isset($tune['below'], $fields[$tune['below']])) {
                $fy = ($f[1] + $f[3] + $fields[$tune['below']][1]) / 2;
            }
            $item = ['id' => $c['id'], 'n' => $i + 1, 'label' => $c['label'], 'side' => 'end', 'align' => 'first'];
            foreach (self::LAYOUTS as $layout) {
                $l = self::LAYOUT[$layout];
                $s = self::scale($type, $layout);
                [$bx, $by] = self::box($type, $layout);
                [$edge, $room] = self::margin($layout);
                [, $pe] = self::pile($layout);
                $y = $by + $fy * $s;
                $item[$layout] = [
                    'dotX' => $bx + ($f[0] + $f[2]) * $s + $l['dot'],
                    'dotY' => $y,
                    'y' => $y,
                    'h' => isset($l['marker']) ? $l['marker'] / $l['minK'] : (float) ($heights[$c['id']] ?? self::estimateHeight($c['label'], $locale)),
                    'edge' => $edge,
                    'room' => $room,
                    'knee' => $pe + $l['knee'],
                    'lineEnd' => $edge - $l['end'],
                ];
            }
            $out[] = $item;
        }
        // Labels (markers) along the block axis, per layout: one side, from the top down.
        foreach (self::LAYOUTS as $layout) {
            $order = array_keys($out);
            usort($order, fn ($a, $b) => $out[$a][$layout]['y'] <=> $out[$b][$layout]['y']);
            $placed = self::stack(array_map(fn ($i) => ['y' => $out[$i][$layout]['y'], 'h' => $out[$i][$layout]['h']], $order), $layout);
            foreach ($order as $j => $i) {
                $out[$i][$layout]['y'] = $placed[$j]['y'];
                if ($layout === 'wide') {
                    $out[$i]['align'] = $placed[$j]['align'];
                }
            }
        }
        foreach ($out as &$item) {
            foreach (self::LAYOUTS as $layout) {
                $g = $item[$layout];
                $r = fn (float $v) => round($v, 1);
                $dot = [$r($phys($g['dotX'])), $r($g['dotY'])];
                // One straight leader; a short step in the gap beside the pile when the label or marker was moved.
                $d = abs($g['y'] - $g['dotY']) < 0.05
                    ? sprintf('M%.1f %.1fH%.1f', $dot[0], $dot[1], $r($phys($g['lineEnd'])))
                    : sprintf('M%.1f %.1fH%.1fV%.1fH%.1f', $dot[0], $dot[1], $r($phys($g['knee'])), $r($g['y']), $r($phys($g['lineEnd'])));
                $item[$layout] = [
                    'dot' => $dot,
                    'y' => round($g['y'], 1),
                    'inset' => round($g['edge'], 1),
                    'room' => round($g['room'], 1),
                    'd' => $d,
                ];
            }
        }
        unset($item);

        return $out;
    }

    /**
     * The locale-independent geometry, as CSS for the nonce'd style block: per document its scale, its pile pose and
     * its top pose (the pile's centre), per layout, as offsets from the canvas centre.
     */
    public static function css(): string
    {
        $px = fn (float $v) => self::num($v, 2).'px';
        $rules = [];
        foreach (Docs::TYPES as $type) {
            $vars = [];
            foreach (self::LAYOUTS as $layout) {
                [$cx, $cy] = self::centre($layout);
                $ox = $cx - self::W / 2;
                $oy = $cy - self::LAYOUT[$layout]['h'] / 2;
                [$dx, $dy, $rot] = self::pose($type, $layout);
                $vars[] = "--s-{$layout}:".self::num(self::scale($type, $layout), 4)
                    .";--px-{$layout}:".$px($ox + $dx).";--py-{$layout}:".$px($oy + $dy).";--pr-{$layout}:".self::num($rot, 2).'deg'
                    .";--tx-{$layout}:".$px($ox).";--ty-{$layout}:".$px($oy);
            }
            $rules[] = '.hero-doc--'.$type.'{'.implode(';', $vars).'}';
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
