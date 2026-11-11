<?php

namespace App\Filament\Support;

use Filament\Support\Colors\Color;

/**
 * The admin panel's Filament colour palettes, built from the Design 2 tokens (resources/css/d2/tokens.css;
 * contract docs/d2.1/phase-1/tokens.md) so the panel and the site never drift apart. Each token below is read
 * from that file at runtime, following var() aliases (a Design 2.0 name such as --night resolves to --dark); the
 * fallback is the same value, used only if the file cannot be read. Shades without a token are interpolated in
 * OKLCH between their neighbours or come from Filament's palette generator for the same hue. Filament itself then
 * picks accessible text shades from these.
 *
 *   primary  bronze      100 badge-brand-bg #F7F1E6 · 300 focus-on-dark · 400 gold · 500 bronze #8A6A2E (white
 *                        text 5.02:1) · 600 bronze-700 (6.21:1)
 *   gray     zinc        50 band #F6F6F7 · 100 badge-neutral-bg · 200 line #E4E4E7 · 300 line-strong · 400 on-dark-3 ·
 *                        500 field-border-on-dark #71717A (4.83:1 on white) · 600 badge-neutral-fg · 700 ink-2 ·
 *                        800 line-on-dark · 900 dark-2 · 950 dark
 *   success / warning / danger / info   50 the badge background · 200 the decorative line · 500 the state colour ·
 *                        600 a step darker (brief §13: from the badge pairs of §3.3)
 *
 * Filament fills a button with shade 600 and hovers it with 500 when white text passes AA on both, otherwise it
 * falls back to 400 with dark text: each state colour at 500 and a darker step at 600 therefore gives buttons with
 * white text; links and small primary text use 600 (bronze-700). The full admin look is Phase 7 (brief §13).
 */
final class AdminPalette
{
    /** token name => the value in tokens.css today (fallback only) */
    private const FALLBACK = [
        'canvas' => '#FFFFFF',
        'band' => '#F6F6F7',
        'dark' => '#0B0B0C',
        'dark-2' => '#151517',
        'ink' => '#0B0B0C',
        'ink-2' => '#3F3F46',
        'ink-3' => '#6B6B73',
        'on-dark-3' => '#A1A1AA',
        'line' => '#E4E4E7',
        'line-strong' => '#D4D4D8',
        'line-on-dark' => '#27272A',
        'field-border-on-dark' => '#71717A',
        'gold' => '#C9A96B',
        'bronze' => '#8A6A2E',
        'bronze-700' => '#7A5C24',
        'focus-on-dark' => '#E2CF9F',
        'badge-neutral-fg' => '#52525B',
        'badge-neutral-bg' => '#F1F1F3',
        'badge-brand-bg' => '#F7F1E6',
        'success' => '#1E6B47',
        'badge-success-bg' => '#E8F5EE',
        'success-line' => '#BEE2CD',
        'warning' => '#8A5300',
        'badge-warning-bg' => '#FFF4E0',
        'warning-line' => '#F2D9A8',
        'danger' => '#B42318',
        'badge-danger-bg' => '#FDECEA',
        'danger-line' => '#F5C9C4',
        'info' => '#2F5DA0',
        'badge-info-bg' => '#EAF1FB',
        'info-line' => '#C9DAF2',
        'night' => '#0B0B0C', // legacy name (InitialsAvatarProvider)
    ];

    /** @var array<string, string>|null */
    private static ?array $tokens = null;

    /** @return array<string, array<int, string>> Filament ->colors([...]) */
    public static function colors(): array
    {
        return [
            'primary' => self::primary(),
            'gray' => self::gray(),
            'success' => self::semantic('success'),
            'warning' => self::semantic('warning'),
            'danger' => self::semantic('danger'),
            'info' => self::semantic('info'),
        ];
    }

    /** @return array<int, string> */
    public static function primary(): array
    {
        $generated = Color::generatePalette(self::token('bronze'));

        return [
            50 => $generated[50],
            100 => self::token('badge-brand-bg'),
            200 => $generated[200],
            300 => self::token('focus-on-dark'),
            400 => self::token('gold'),
            500 => self::token('bronze'),
            600 => self::token('bronze-700'),
            700 => self::mix(self::token('bronze-700'), $generated[800]),
            800 => $generated[800],
            900 => $generated[900],
            950 => $generated[950],
        ];
    }

    /** @return array<int, string> */
    public static function gray(): array
    {
        return [
            50 => self::token('band'),
            100 => self::token('badge-neutral-bg'),
            200 => self::token('line'),
            300 => self::token('line-strong'),
            400 => self::token('on-dark-3'),
            500 => self::token('field-border-on-dark'),
            600 => self::token('badge-neutral-fg'),
            700 => self::token('ink-2'),
            800 => self::token('line-on-dark'),
            900 => self::token('dark-2'),
            950 => self::token('dark'),
        ];
    }

    /** @return array<int, string> */
    public static function semantic(string $name): array
    {
        $base = self::token($name);
        $generated = Color::generatePalette($base);

        return [
            50 => self::token("badge-{$name}-bg"),
            100 => $generated[100],
            200 => self::token("{$name}-line"),
            300 => $generated[300],
            400 => $generated[400],
            500 => $base,
            600 => self::mix($base, $generated[800]),
            700 => $generated[800],
            800 => $generated[900],
            900 => self::mix($generated[900], $generated[950]),
            950 => $generated[950],
        ];
    }

    /** A colour token from resources/css/d2/tokens.css (#RRGGBB, var() aliases followed), or its known value. */
    public static function token(string $name): string
    {
        return self::tokens()[$name] ?? self::FALLBACK[$name] ?? throw new \InvalidArgumentException("Unknown token --{$name}");
    }

    /** @return array<string, string> every `--name:` declaration of tokens.css that resolves to #RRGGBB (first one wins) */
    public static function tokens(): array
    {
        if (self::$tokens !== null) {
            return self::$tokens;
        }

        $path = resource_path('css/d2/tokens.css');
        $css = is_file($path) ? (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path)) : '';
        preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/', $css, $matches, PREG_SET_ORDER);

        $raw = [];
        foreach ($matches as [, $name, $value]) {
            $raw[$name] ??= trim($value);
        }
        $tokens = [];
        foreach (array_keys($raw) as $name) {
            $value = $raw[$name];
            for ($hops = 0; $hops < 8 && preg_match('/^var\(--([a-z0-9-]+)\)$/', $value, $alias) && isset($raw[$alias[1]]); $hops++) {
                $value = $raw[$alias[1]];
            }
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $tokens[$name] = strtoupper($value);
            }
        }

        return self::$tokens = $tokens;
    }

    /** The OKLCH midpoint of two colours. */
    private static function mix(string $a, string $b): string
    {
        [$l1, $c1, $h1] = sscanf(Color::convertToOklch($a), 'oklch(%f %f %f)');
        [$l2, $c2, $h2] = sscanf(Color::convertToOklch($b), 'oklch(%f %f %f)');
        $delta = fmod(($h2 - $h1 + 540), 360) - 180;
        $hue = fmod($h1 + $delta / 2 + 360, 360);

        return sprintf('oklch(%s %s %s)', round(($l1 + $l2) / 2, 3), round(($c1 + $c2) / 2, 3), round($hue, 3));
    }
}
