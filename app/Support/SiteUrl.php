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

    /**
     * A Location header for a redirect on this site: the root-relative path (and query), never an absolute URL built
     * from the request's Host header. A forged Host would otherwise produce a cacheable 301 to another domain (host
     * header injection / cache poisoning); browsers and crawlers resolve "/en/" against the address they asked for.
     */
    public static function redirectTarget(string $path, ?string $query = null): string
    {
        return '/'.ltrim($path, '/').($query !== null && $query !== '' ? '?'.$query : '');
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
     * "Request a Legal Consultation" (A01). WhatsApp only (client PDF item 7, App\Support\ConsultationForm): the
     * WhatsApp link with the approved message, opened in a new tab and never sent. With the form switched on: the
     * form, on the Contact page once it is released (P07 release rule), on the home page until then.
     */
    public static function consultation(?string $locale = null): string
    {
        if (! ConsultationForm::enabled()) {
            return (string) setting('whatsapp_url', $locale);
        }

        return config('summit.contact_page_released')
            ? self::page('P07', $locale, 'consultation-form')
            : self::home($locale, 'consultation-form');
    }

    /** "Contact Us" (P07): the Contact page (released 2026-11-25), else the home page's contact section (#consultation-form). */
    public static function contact(?string $locale = null): string
    {
        return config('summit.contact_page_released') ? self::page('P07', $locale) : self::home($locale, 'consultation-form');
    }

    /**
     * The target of a call-to-action button (a hero or closing CTA of the approved copy): a consultation request (P07,
     * A01) opens WhatsApp while the form is off (client PDF item 7), like consultation(); any other target is target().
     * Text links to P07 ("Contact Summit", the related-link lists) keep target(): the contact section.
     */
    public static function cta(string $target, ?string $locale = null): string
    {
        return in_array($target, ['P07', 'A01'], true) && ! ConsultationForm::enabled()
            ? self::consultation($locale)
            : self::target($target, $locale);
    }

    /** Whether a URL opens WhatsApp (wa.me, api.whatsapp.com): such links open in a new tab and carry the WhatsApp mark. */
    public static function isWhatsApp(?string $url): bool
    {
        return is_string($url) && preg_match('#^https://(?:wa\.me|api\.whatsapp\.com)/#i', $url) === 1;
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

    /**
     * Absolute production URL (config summit.canonical_host = SUMMIT_CANONICAL_HOST, default https://www.summitlegaluae.com)
     * for a site-relative path: canonical links, hreflang alternates, the sitemap and structured data. Never the request's
     * host, and never a query string or fragment (client PDF item 21): "/en/?navigator=S05-2" → ".../en/".
     */
    public static function canonical(string $path): string
    {
        $path = (string) preg_replace('/[?#].*$/s', '', $path);

        return self::canonicalOrigin().'/'.ltrim($path, '/');
    }

    /**
     * Absolute URL that other sites fetch when a page is shared (Open Graph og:url and og:image: WhatsApp, LinkedIn,
     * client PDF item 27): in production the canonical host (= canonical()); elsewhere this site's own configured
     * APP_URL, so a link shared from the test site previews the test site's page and image instead of a production
     * address that does not exist yet. Never the request's Host header; never a query string.
     */
    public static function share(string $path): string
    {
        $origin = app()->isProduction() ? '' : rtrim((string) config('app.url'), '/');
        if (! preg_match('#^https?://[^/]+$#i', $origin)) {
            return self::canonical($path);
        }

        return $origin.'/'.ltrim((string) preg_replace('/[?#].*$/s', '', $path), '/');
    }

    /** The configured production origin, without a trailing slash ("https://www.summitlegaluae.com"). */
    public static function canonicalOrigin(): string
    {
        $host = trim((string) config('summit.canonical_host'));
        if ($host === '') {
            $host = 'https://www.summitlegaluae.com';
        } elseif (! preg_match('#^https?://#i', $host)) {
            $host = 'https://'.$host;
        }

        return rtrim($host, '/');
    }

    /** The equivalent of the current path in another language (same registered route). */
    public static function swapLocale(string $path, string $to): string
    {
        $locales = implode('|', array_keys(config('summit.locales')));

        return preg_replace('#^/('.$locales.')(?=/|$)#', '/'.$to, $path) ?: '/'.$to.'/';
    }
}
