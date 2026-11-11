<?php

namespace App\Design\D2;

/**
 * The official WhatsApp logo (brief §3.8, D10; owner decision of Phase 1): Meta's own files, used UNCHANGED.
 *
 * Meta's brand guidelines allow only the logo files of Meta's WhatsApp brand page
 * (https://www.meta.com/brand/resources/whatsapp/whatsapp-brand/, "WhatsApp-Brand-Resource-Center.zip"):
 * never redrawn, recoloured, re-pathed, combined with other words or images, or merged into a sprite. So this
 * class never reads or rewrites a file's content: the page shows the file itself in an <img>
 * (<x-d2.ui.brand-mark>), the published copy is the same bytes, and the inline fallback is those bytes in
 * base64.
 *
 * The drop folder (owner): resources/brand/whatsapp/ (config design.d2.whatsapp_logo.source overrides it in tests)
 *   whatsapp-logo-light.svg   Meta's logo for LIGHT backgrounds (the green one), unchanged; or .png / .webp
 *   whatsapp-logo-dark.svg    Meta's logo for DARK backgrounds (the white one), unchanged; or .png / .webp
 * Copy Meta's files in under these two names (renaming does not alter a file); SVG is preferred when a variant
 * exists in more than one format. Each variant switches on its own the moment its file is there: every WhatsApp
 * control renders through <x-d2.ui.icon name="whatsapp"> → <x-d2.ui.brand-mark>, so all of them switch at once,
 * with no code change. Until a variant's file exists that variant is the clearly marked placeholder
 * (data-placeholder="whatsapp-logo").
 *
 * Serving: `node scripts/d2/whatsapp-logo.mjs` copies each file byte for byte to
 * public/d2/brand/whatsapp/{name}.{8-hex sha256}.{ext} (a new name whenever the file changes, so caches never
 * serve an old logo) and removes stale copies; `--check` fails while a copy is missing or stale. Without the
 * copy (the moment after the drop) the page inlines the same bytes as a data: URI (the CSP allows img-src
 * data:), so the switch never waits for the script; WhatsAppLogoTest fails until the copies are published.
 */
final class WhatsAppLogo
{
    /** The two variants: for light surfaces and for dark surfaces. */
    public const VARIANTS = ['light', 'dark'];

    /** Accepted formats, in order of preference => media type. */
    public const FORMATS = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'webp' => 'image/webp'];

    /** File name of a variant in the drop folder, without the extension. */
    public const BASENAME = 'whatsapp-logo-';

    /** Where the published copies are served from (public/d2/brand/whatsapp). */
    public const PUBLIC_URL = '/d2/brand/whatsapp/';

    /** @var array<string, array{key: string, value: ?array}> per variant, keyed by the source file's state */
    private static array $memo = [];

    public static function sourceDir(): string
    {
        return rtrim((string) config('design.d2.whatsapp_logo.source', resource_path('brand/whatsapp')), '/');
    }

    public static function publicDir(): string
    {
        return rtrim((string) config('design.d2.whatsapp_logo.public', public_path('d2/brand/whatsapp')), '/');
    }

    /** The drop-folder file of a variant (the first format found, svg first), or null while it is missing. */
    public static function source(string $variant): ?string
    {
        if (! in_array($variant, self::VARIANTS, true)) {
            return null;
        }
        foreach (array_keys(self::FORMATS) as $ext) {
            $path = self::sourceDir().'/'.self::BASENAME.$variant.'.'.$ext;
            if (is_file($path) && filesize($path) > 0) {
                return $path;
            }
        }

        return null;
    }

    /** The published file name of a source: {name}.{first 8 hex of its sha256}.{ext}. */
    public static function publishedName(string $source): string
    {
        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));

        return pathinfo($source, PATHINFO_FILENAME).'.'.substr((string) hash_file('sha256', $source), 0, 8).'.'.$ext;
    }

    /**
     * What the page shows for a variant: null while Meta's file is not in the drop folder (the placeholder),
     * otherwise ['src' => the published URL or a data: URI of the unchanged bytes, 'type' => media type,
     * 'file' => source path, 'sha256' => …, 'published' => bool].
     *
     * @return array{src: string, type: string, file: string, sha256: string, published: bool}|null
     */
    public static function variant(string $variant): ?array
    {
        $source = self::source($variant);
        if ($source === null) {
            return null;
        }
        clearstatcache(true, $source);
        $key = $source.'|'.filesize($source).'|'.filemtime($source).'|'.self::publicDir();
        if ((self::$memo[$variant]['key'] ?? null) === $key) {
            return self::$memo[$variant]['value'];
        }

        $sha = (string) hash_file('sha256', $source);
        $type = self::FORMATS[strtolower(pathinfo($source, PATHINFO_EXTENSION))];
        $name = self::publishedName($source);
        $copy = self::publicDir().'/'.$name;
        $published = is_file($copy) && hash_file('sha256', $copy) === $sha;
        $value = [
            'src' => $published
                ? self::PUBLIC_URL.$name
                : 'data:'.$type.';base64,'.base64_encode((string) file_get_contents($source)),
            'type' => $type,
            'file' => $source,
            'sha256' => $sha,
            'published' => $published,
        ];
        self::$memo[$variant] = ['key' => $key, 'value' => $value];

        return $value;
    }

    /** Both of Meta's files are in place (no placeholder anywhere). */
    public static function official(): bool
    {
        return self::variant('light') !== null && self::variant('dark') !== null;
    }

    /** Forgets the memo (tests that change the drop folder). */
    public static function flush(): void
    {
        self::$memo = [];
    }
}
