<?php

namespace App\Support;

use App\Content\Cms\MediaFiles;
use Throwable;

/**
 * Head metadata for a page: title, description, self-referencing www canonical and reciprocal hreflang alternates
 * (+ x-default → the default language). The SEO screen's global defaults (App\Settings\SeoSettings) give the
 * description of a page without one and the site-wide index switch.
 *
 * Alternates (client PDF item 22): only on a page that may be indexed, and only to the languages in which the same
 * page exists (App\Support\SitePages: the language is on and the page's record has its text in it); every registered
 * route exists under the same path in every language, so each alternate is the equivalent page, never just a
 * language's home page. x-default names the default language's version when that exists.
 *
 * Open Graph (client PDF item 27): og:url is the page's clean address and og:image an absolute https image with its
 * size, type and alt text (SiteUrl::share(): the production host in production, the site's own APP_URL elsewhere, so
 * the test site's previews work too). The image is the page's own social image (its record's og_image) or the SEO
 * screen's default one when either is a JPEG or PNG at least 600 px wide (WhatsApp and LinkedIn show neither WebP nor
 * AVIF reliably), else public/images/og-default.png (1200 × 630, the gold lockup on black).
 */
final class Seo
{
    /**
     * @param  bool  $index  whether this page may be indexed (a current page of the site); the site-wide switch
     *                       (siteIndexable()) is applied on top for the robots meta only, so the test site keeps its
     *                       canonical and alternates while every page says noindex
     * @param  list<string>|null  $locales  the languages in which this page exists; null: the current page's
     *                                      (SitePages::current()), or every language that is on
     * @param  string|null  $image  the page's social image (a Media Library uuid: the record's og_image), if any
     * @return array{title: string, description: ?string, canonical: ?string, alternates: array<string, string>, index: bool, og_url: ?string, image: array{url: string, width: int, height: int, type: string, alt: string}, og_locale: string, og_alternates: list<string>}
     */
    public static function make(string $title, ?string $description = null, bool $index = true, ?string $path = null, bool $alternates = true, ?array $locales = null, ?string $image = null): array
    {
        $path ??= request()->getPathInfo();
        $locale = app()->getLocale();
        $configured = (array) config('summit.locales');
        $defaults = self::defaults();
        $description ??= $defaults['description'][$locale] ?? null;

        $current = SitePages::current();
        $locales ??= $current['locales'] ?? array_keys($configured);
        $image ??= $index ? SitePages::socialImage($current) : null;
        $available = array_values(array_filter(array_keys($configured), fn ($code) => in_array($code, $locales, true)));

        $links = [];
        if ($index && $alternates && count($available) > 1 && in_array($locale, $available, true)) {
            foreach ($available as $code) {
                $links[$configured[$code]['hreflang']] = SiteUrl::canonical(SiteUrl::swapLocale($path, $code));
            }
            $default = (string) config('summit.default_locale');
            if (in_array($default, $available, true)) {
                $links['x-default'] = SiteUrl::canonical(SiteUrl::swapLocale($path, $default));
            }
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $index ? SiteUrl::canonical($path) : null,
            'alternates' => $links,
            'index' => $index && self::siteIndexable(),
            'og_url' => $index ? SiteUrl::share($path) : null,
            'image' => self::image($image, $defaults['og_image']),
            'og_locale' => $configured[$locale]['og'] ?? 'en_GB',
            'og_alternates' => $links === [] ? [] : array_values(array_map(
                fn ($code) => $configured[$code]['og'],
                array_filter($available, fn ($code) => $code !== $locale),
            )),
        ];
    }

    /**
     * Whether the site may be indexed at all: SUMMIT_NOINDEX (config summit.noindex; true by default when APP_ENV is
     * staging) is off and the SEO screen's "may be indexed" switch is on. Off on the test site: every page says
     * noindex (and every response, App\Http\Middleware\SecurityHeaders), and robots.txt names no sitemap
     * (App\Http\Controllers\SiteController::robots()).
     */
    public static function siteIndexable(): bool
    {
        return ! config('summit.noindex') && self::defaults()['robots_index'];
    }

    /**
     * The social image: the page's own, else the SEO screen's default, else public/images/og-default.png.
     *
     * @return array{url: string, width: int, height: int, type: string, alt: string}
     */
    private static function image(?string $own, ?string $default): array
    {
        $locale = app()->getLocale();
        foreach ([$own, $default] as $uuid) {
            if (! is_string($uuid) || $uuid === '') {
                continue;
            }
            try {
                $media = MediaFiles::byUuid($uuid);
            } catch (Throwable) {
                $media = null;
            }
            $width = (int) ($media?->getCustomProperty('width') ?? 0);
            $height = (int) ($media?->getCustomProperty('height') ?? 0);
            if ($media !== null && in_array($media->mime_type, ['image/jpeg', 'image/png'], true) && $width >= 600 && $height > 0) {
                $url = MediaFiles::mediaUrl($media);

                return [
                    'url' => preg_match('#^https?://#i', $url) ? $url : SiteUrl::share($url),
                    'width' => $width,
                    'height' => $height,
                    'type' => (string) $media->mime_type,
                    'alt' => MediaFiles::alt($uuid, $locale) ?: ui('brand.logo_alt'),
                ];
            }
        }

        return [
            'url' => SiteUrl::share('images/og-default.png'),
            'width' => 1200,
            'height' => 630,
            'type' => 'image/png',
            'alt' => ui('brand.logo_alt'),
        ];
    }

    /**
     * The global SEO defaults of the SEO screen (App\Settings\SeoSettings, read through the content repository): a
     * description per language for pages without their own (none today), and whether pages may be indexed (true today;
     * SUMMIT_NOINDEX still wins). With today's values every page is unchanged.
     *
     * @return array{description: array<string, string>, robots_index: bool, og_image: ?string}
     */
    private static function defaults(): array
    {
        $seo = content()->settings('seo');
        $description = $seo['default_description'] ?? null;

        return [
            'description' => is_array($description) ? array_filter($description, fn ($text) => is_string($text) && trim($text) !== '') : [],
            'robots_index' => ($seo['robots_index'] ?? true) !== false,
            'og_image' => is_string($seo['default_og_image'] ?? null) ? $seo['default_og_image'] : null,
        ];
    }
}
