<?php

namespace App\Support;

/**
 * Head metadata for a page: title, description, self-referencing www canonical and reciprocal
 * hreflang alternates (+ x-default → /en/). Every registered route exists in all three
 * languages under the same path, so alternates are real equivalent pages. The SEO screen's global defaults
 * (App\Settings\SeoSettings) give the description of a page without one and the site-wide index switch.
 */
final class Seo
{
    /**
     * @return array{title: string, description: ?string, canonical: ?string, alternates: array<string, string>, index: bool, og_locale: string, og_alternates: list<string>}
     */
    public static function make(string $title, ?string $description = null, bool $index = true, ?string $path = null, bool $alternates = true): array
    {
        $path ??= request()->getPathInfo();
        $locale = app()->getLocale();
        $locales = config('summit.locales');
        $defaults = self::defaults();
        $description ??= $defaults['description'][$locale] ?? null;

        $links = [];
        if ($alternates) {
            foreach ($locales as $code => $cfg) {
                $links[$cfg['hreflang']] = SiteUrl::canonical(SiteUrl::swapLocale($path, $code));
            }
            $links['x-default'] = SiteUrl::canonical(SiteUrl::swapLocale($path, config('summit.default_locale')));
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $index ? SiteUrl::canonical($path) : null,
            'alternates' => $links,
            'index' => $index && ! config('summit.noindex') && $defaults['robots_index'],
            'og_locale' => $locales[$locale]['og'] ?? 'en_GB',
            'og_alternates' => array_values(array_map(fn ($c) => $c['og'], array_diff_key($locales, [$locale => true]))),
        ];
    }

    /**
     * The global SEO defaults of the SEO screen (App\Settings\SeoSettings, read through the content repository): a
     * description per language for pages without their own (none today), and whether pages may be indexed (true today;
     * SUMMIT_NOINDEX still wins). With today's values every page is unchanged.
     *
     * @return array{description: array<string, string>, robots_index: bool}
     */
    private static function defaults(): array
    {
        $seo = content()->settings('seo');
        $description = $seo['default_description'] ?? null;

        return [
            'description' => is_array($description) ? array_filter($description, fn ($text) => is_string($text) && trim($text) !== '') : [],
            'robots_index' => ($seo['robots_index'] ?? true) !== false,
        ];
    }

    /**
     * Font files to preload for the current language's H1 (from the font pipeline): its display
     * face, plus the Latin serif on AR/RU pages, whose H1 spaces and full stops come from it.
     *
     * @return list<string>
     */
    public static function preloadFonts(): array
    {
        $manifest = self::fontManifest();
        $keys = match (app()->getLocale()) {
            'ar' => ['naskh-400-arabic', 'serif-400-latin'],
            'ru' => ['serif-400-cyrillic', 'serif-400-latin'],
            default => ['serif-400-latin'],
        };

        return array_values(array_filter(array_map(fn ($k) => $manifest[$k] ?? null, $keys)));
    }

    /** @return array<string, string> */
    private static function fontManifest(): array
    {
        static $manifest = null;

        return $manifest ??= is_file(public_path('fonts/manifest.json'))
            ? (json_decode((string) file_get_contents(public_path('fonts/manifest.json')), true) ?: [])
            : [];
    }
}
