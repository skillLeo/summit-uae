<?php

namespace App\Design\D2;

/**
 * Design 2 font preloads (brief §3.2: "preload only the current locale's 400 and 600 files"; §6: two preloaded
 * font files per page at most) and the font manifest. Contract: docs/d2.1/phase-1/tokens.md §5.
 *
 * The files, public/fonts/d2/manifest.json and the @font-face rules (resources/css/d2/base/fonts.css, inlined by
 * App\Design\D2\FontFaces) come from scripts/fonts/fontsource-d2.mjs; a preload URL is the manifest URL, i.e.
 * exactly the @font-face src, so the preloaded response is the one the face uses.
 *
 * The active pair is read from the one font token in resources/css/d2/tokens.css (`--font-sans: var(--font-b)`
 * or `var(--font-a)`), so switching to Graphik is a file drop, the script, and that token: no PHP change.
 *   pair b (free, in use)
 *     en  Onest's Latin file: one variable file draws 400 and 600 (and 500), so English pages preload one file.
 *     ru  Onest's Cyrillic file (the letters) and its Latin file (spaces, digits, punctuation of Russian text).
 *     ar  IBM Plex Sans Arabic's Arabic 400 (lead and body) and 600 (headings, the H1 = LCP). The Latin files of
 *         the same family (spaces, digits, codes) and the 500 faces (UI labels, buttons) are not preloaded: the
 *         boot script starts them in the head (FontFaces::earlyLoads()), and the space-only fallback face keeps
 *         Arabic lines from re-wrapping before they arrive.
 *   pair a (Graphik, once licensed files are in resources/fonts/graphik/): the 400 and 600 file of the page's
 *         family (Graphik for en and ru, Graphik Arabic for ar).
 */
final class Fonts
{
    /** manifest keys to preload, per pair and locale (at most two each) */
    public const PRELOAD = [
        'b' => [
            'en' => ['onest-latin-wght-normal'],
            'ru' => ['onest-cyrillic-wght-normal', 'onest-latin-wght-normal'],
            'ar' => ['ibm-plex-sans-arabic-arabic-400-normal', 'ibm-plex-sans-arabic-arabic-600-normal'],
        ],
        'a' => [
            'en' => ['graphik-400', 'graphik-600'],
            'ru' => ['graphik-400', 'graphik-600'],
            'ar' => ['graphik-arabic-400', 'graphik-arabic-600'],
        ],
    ];

    /** @var array<string, mixed> */
    private static array $memo = [];

    /** @return list<string> preload URLs for the locale (unknown locale: English) */
    public static function preload(string $locale): array
    {
        $manifest = self::manifest();
        $keys = self::PRELOAD[self::pair()][$locale] ?? self::PRELOAD[self::pair()]['en'];

        return array_values(array_filter(array_map(fn (string $key) => $manifest[$key] ?? null, $keys)));
    }

    /** @return array<string, string> manifest key => URL (public/fonts/d2/manifest.json "files") */
    public static function manifest(): array
    {
        $path = public_path('fonts/d2/manifest.json');
        $stamp = is_file($path) ? (string) filemtime($path) : 'none';

        return self::$memo['manifest:'.$stamp] ??= (function () use ($path): array {
            $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            $files = is_array($data) && is_array($data['files'] ?? null) ? $data['files'] : [];
            $out = [];
            foreach ($files as $key => $file) {
                if (is_array($file) && is_string($file['url'] ?? null)) {
                    $out[(string) $key] = $file['url'];
                }
            }

            return $out;
        })();
    }

    /** 'b' (Onest + IBM Plex Sans Arabic) or 'a' (Graphik + Graphik Arabic): the value of tokens.css --font-sans */
    public static function pair(): string
    {
        $path = resource_path('css/d2/tokens.css');
        $stamp = is_file($path) ? (string) filemtime($path) : 'none';

        return self::$memo['pair:'.$stamp] ??= (is_file($path)
            && preg_match('/--font-sans:\s*var\(--font-([ab])\)/', (string) file_get_contents($path), $m)) ? $m[1] : 'b';
    }
}
