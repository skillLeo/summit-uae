<?php

namespace App\Design\D2;

/**
 * Guide cards (Design 2.1 brief D27, §3.6 rules 9 and 10): the cover hook and the guide's practice area.
 *
 * COVER. A guide card is a text card (category, title, summary, "Read the Guide"). It shows an image area only
 * when the guide has a real cover: an image the admin uploads (Phase 4 media library, brief §8.9). None exists
 * yet, so no card draws one; there are no generated covers (patterns, circles with icons: fake thumbnails) and no
 * reading time ("N min read" on every card is repeated metadata). image() is the one place the card asks for it:
 * the guide record's `cover`, either a path or URL string, or ['src' => …, 'alt' => …, 'width' => …,
 * 'height' => …]. Only same-site paths and http(s) URLs are accepted; anything else is no cover.
 */
final class Cover
{
    /**
     * The real cover of a guide, or null when it has none (every guide today).
     *
     * @param  array<string, mixed>  $guide  a content()->guide() record
     * @return array{src: string, alt: string, width: int|null, height: int|null}|null
     */
    public static function image(array $guide): ?array
    {
        $cover = $guide['cover'] ?? null;
        if (is_string($cover)) {
            $cover = ['src' => $cover];
        }
        if (! is_array($cover)) {
            return null;
        }
        $src = trim((string) ($cover['src'] ?? ''));
        if ($src === '' || preg_match('#^(?:https?://|/(?!/))#i', $src) !== 1) {
            return null;
        }
        $size = fn (string $key): ?int => is_numeric($cover[$key] ?? null) && (int) $cover[$key] > 0 ? (int) $cover[$key] : null;

        return [
            'src' => $src,
            // The card's title names the card; a cover is decorative unless the admin describes it.
            'alt' => trim((string) ($cover['alt'] ?? '')),
            'width' => $size('width'),
            'height' => $size('height'),
        ];
    }

    /**
     * The service a guide belongs to: the first service code (S01–S11) among its approved related links
     * ("Learn About the Legal Service Related to This Topic"). The Knowledge Centre prefills the consultation
     * form with it (components/d2/resources/article.blade.php).
     *
     * @param  array<string, mixed>  $guide  a content()->guide() record
     */
    public static function serviceFor(array $guide): ?string
    {
        foreach ((array) ($guide['body']['related']['items'] ?? []) as $item) {
            $target = is_array($item) ? ($item['target'] ?? null) : null;
            if (is_string($target) && preg_match('/^S(0[1-9]|1[01])$/', $target) === 1) {
                return $target;
            }
        }

        return null;
    }
}
