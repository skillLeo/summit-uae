<?php

namespace App\Support;

/**
 * Localised URLs for the registered routes. Every page URL ends with "/", matching the
 * existing summitlegaluae.com routes. Targets use the stable IDs of the site map.
 */
final class SiteUrl
{
    public static function locale(?string $locale = null): string
    {
        return $locale ?? app()->getLocale();
    }

    /**
     * Absolute URL for a site-relative path, keeping its trailing slash. (Laravel's url()
     * helper trims trailing slashes, which would send every internal link through a 301.)
     */
    public static function abs(string $path): string
    {
        return rtrim(url('/'), '/').'/'.ltrim($path, '/');
    }

    /** Relative path for a page code, e.g. P02 → /en/practice-areas/ */
    public static function pagePath(string $code, ?string $locale = null): string
    {
        $path = config("summit.pages.{$code}.path");

        return '/'.self::locale($locale).'/'.($path ?? '');
    }

    public static function page(string $code, ?string $locale = null, ?string $fragment = null): string
    {
        return self::abs(self::pagePath($code, $locale)).($fragment ? '#'.$fragment : '');
    }

    public static function home(?string $locale = null, ?string $fragment = null): string
    {
        return self::page('P01', $locale, $fragment);
    }

    public static function servicePath(string $code, ?string $locale = null): string
    {
        return self::pagePath('P02', $locale).config("summit.services.{$code}").'/';
    }

    public static function service(string $code, ?string $locale = null, ?string $fragment = null): string
    {
        return self::abs(self::servicePath($code, $locale)).($fragment ? '#'.$fragment : '');
    }

    public static function guide(string $code, ?string $locale = null): string
    {
        return self::page('P03', $locale, config("summit.guides.{$code}"));
    }

    public static function caseStudy(string $code, ?string $locale = null): string
    {
        return self::page('P04', $locale, $code);
    }

    /**
     * P07 release rule: until the standalone Contact page is released, every contact or
     * consultation control targets the homepage form.
     */
    public static function consultation(?string $locale = null): string
    {
        return config('summit.contact_page_released')
            ? self::page('P07', $locale, 'consultation-form')
            : self::home($locale, 'consultation-form');
    }

    public static function contact(?string $locale = null): string
    {
        return config('summit.contact_page_released') ? self::page('P07', $locale) : self::consultation($locale);
    }

    /**
     * Resolve a target ID from the DOCX link notes: P01–P08, L01, L02, S01–S11, G01–G09,
     * case-01…case-06, A01–A04, cat-NN, faq-NN, team-*, or an absolute URL / mailto / tel.
     */
    public static function target(string $target, ?string $locale = null): string
    {
        return match (true) {
            str_contains($target, ':') => $target,
            $target === 'P07' => self::contact($locale),
            $target === 'A01' => self::consultation($locale),
            (bool) preg_match('/^A0[2-4]$/', $target) => self::page(config("summit.anchors.{$target}.page"), $locale, config("summit.anchors.{$target}.fragment")),
            (bool) preg_match('/^(P0\d|L0[12])$/', $target) => self::page($target, $locale),
            (bool) preg_match('/^S\d{2}$/', $target) => self::service($target, $locale),
            (bool) preg_match('/^G\d{2}$/', $target) => self::guide($target, $locale),
            (bool) preg_match('/^case-\d{2}$/', $target) => self::caseStudy($target, $locale),
            (bool) preg_match('/^(cat|faq)-\d{2}$/', $target) => self::page('P05', $locale, $target),
            str_starts_with($target, 'team') => self::page('P06', $locale, $target),
            str_starts_with($target, '#') => $target,
            default => self::home($locale),
        };
    }

    /** Absolute production URL (www host) for a site-relative path. */
    public static function canonical(string $path): string
    {
        return rtrim((string) config('summit.canonical_host'), '/').'/'.ltrim($path, '/');
    }

    /** The equivalent of the current path in another language (same registered route). */
    public static function swapLocale(string $path, string $to): string
    {
        $locales = implode('|', array_keys(config('summit.locales')));

        return preg_replace('#^/('.$locales.')(?=/|$)#', '/'.$to, $path) ?: '/'.$to.'/';
    }
}
