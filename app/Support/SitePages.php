<?php

namespace App\Support;

use App\Content\Cms\Status;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The site's public, indexable pages (client PDF items 21-23): the one list that decides which pages carry a canonical
 * URL and hreflang links, and which the sitemap lists.
 *
 *   - the home page (P01) and every registered page of config('summit.pages') (P02-P08, L01, L02) whose published CMS
 *     record exists;
 *   - every published practice area (content()->services(), in the approved order) that has a route slug
 *     (config('summit.services'), filled from the published slugs by App\Content\Cms\Editing\Slugs). A practice area
 *     that is unpublished or removed (Legal Translation, owner decision 6) drops out by itself: nothing is hard-coded.
 *
 * Never listed: the 404 page (U01), the calculators (pending copy), the styleguide, the offline page, the admin and
 * the signed previews. A page exists in a language when that language is on (Settings, Languages) and the record has
 * its H1 (or title) in that language; hreflang links and sitemap entries only name those languages.
 */
final class SitePages
{
    /**
     * Every current page in site order: the home page, Practice Areas and its practice areas, then the other pages. The
     * path is relative to the language ("" for the home page, "practice-areas/labor-law/" …).
     *
     * @return list<array{key: string, type: 'page'|'service', code: string, path: string, locales: list<string>}>
     */
    public static function all(): array
    {
        $pages = [];
        $services = self::services();
        foreach (array_merge(['P01' => ['path' => '']], (array) config('summit.pages')) as $code => $page) {
            $entry = self::page((string) $code);
            if ($entry !== null) {
                $pages[] = $entry;
            }
            if ($code === 'P02') {
                array_push($pages, ...$services);
            }
        }

        return $pages;
    }

    /** @return array{key: string, type: 'page', code: string, path: string, locales: list<string>}|null */
    public static function page(string $code): ?array
    {
        $path = $code === 'P01' ? '' : config("summit.pages.{$code}.path");
        if (! is_string($path)) {
            return null;
        }
        $locales = self::localesWith(fn (string $locale) => content()->page($code, $locale)['h1'] ?? null);

        return $locales === [] ? null : ['key' => $code, 'type' => 'page', 'code' => $code, 'path' => $path, 'locales' => $locales];
    }

    /** @return array{key: string, type: 'service', code: string, path: string, locales: list<string>}|null */
    public static function service(string $code): ?array
    {
        $slug = config("summit.services.{$code}");
        $hub = config('summit.pages.P02.path');
        if (! is_string($slug) || ! is_string($hub)) {
            return null;
        }
        $locales = self::localesWith(function (string $locale) use ($code) {
            $record = content()->service($code, $locale);

            return $record === null ? null : ($record['h1'] ?? $record['title'] ?? null);
        });

        return $locales === [] ? null : ['key' => $code, 'type' => 'service', 'code' => $code, 'path' => $hub.$slug.'/', 'locales' => $locales];
    }

    /** @return list<array{key: string, type: 'service', code: string, path: string, locales: list<string>}> */
    public static function services(): array
    {
        $out = [];
        foreach (content()->services(config('summit.default_locale')) as $record) {
            $entry = isset($record['code']) ? self::service((string) $record['code']) : null;
            if ($entry !== null) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * The registry entry of the page this request renders (by route: home, a registered page code, a service slug),
     * or null for anything else (404, calculators, styleguide, offline …).
     *
     * @return array{key: string, type: 'page'|'service', code: string, path: string, locales: list<string>}|null
     */
    public static function current(): ?array
    {
        $route = request()->route();
        if ($route === null) {
            return null;
        }
        if ($route->getName() === 'home') {
            return self::page('P01');
        }
        $slug = $route->parameter('service');
        if (is_string($slug)) {
            $code = array_search($slug, (array) config('summit.services'), true);

            return is_string($code) ? self::service($code) : null;
        }
        $code = $route->defaults['code'] ?? null;

        return is_string($code) && config("summit.pages.{$code}") !== null ? self::page($code) : null;
    }

    /**
     * The page's own social image (the published record's og_image column: a Media Library uuid set on its SEO panel),
     * or null. Read from the table: the content snapshot does not carry it.
     */
    public static function socialImage(?array $entry): ?string
    {
        if ($entry === null) {
            return null;
        }
        try {
            $value = DB::table($entry['type'] === 'service' ? 'services' : 'pages')
                ->where('code', $entry['code'])->where('status', Status::PUBLISHED)->whereNull('deleted_at')
                ->value('og_image');
        } catch (Throwable) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Whether the page exists in this language (its address may be indexed and linked as an alternate). */
    public static function existsIn(?array $entry, string $locale): bool
    {
        return $entry !== null && in_array($locale, $entry['locales'], true);
    }

    /**
     * The enabled languages (config('summit.locales'), after App\Support\Languages) for which $text returns a real text.
     *
     * @param  callable(string): mixed  $text
     * @return list<string>
     */
    private static function localesWith(callable $text): array
    {
        $locales = [];
        foreach (array_keys((array) config('summit.locales')) as $locale) {
            $value = $text((string) $locale);
            if (is_string($value) && trim($value) !== '' && ! str_starts_with(trim($value), '⟦')) {
                $locales[] = (string) $locale;
            }
        }

        return $locales;
    }
}
