<?php

namespace App\Support;

use Throwable;

/**
 * The Languages settings (App\Settings\LanguageSettings, docs/briefs/design-2.1-and-admin-cms.md §12.3) applied to the
 * public site, read through the cached content (content()->settings('languages'); stored values over today's
 * defaults). With the defaults (every language on, English the default, the suggestion bar following the server
 * setting) nothing changes: apply() then leaves the configuration exactly as config/summit.php built it.
 *
 * apply() runs in App\Http\Middleware\SetLocale (every /{locale}/ page) and filter() in the language chooser and the
 * sitemap (App\Http\Controllers\SiteController). A language that is off is removed from config('summit.locales'),
 * which every language menu (Design 1 and Design 2), the hreflang links, the chooser and the sitemap read, and its
 * pages answer 404 (SetLocale). English is always on: every text falls back to it.
 */
final class Languages
{
    public const ALL = ['en', 'ar', 'ru'];

    public const ALWAYS_ON = 'en';

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        try {
            return content()->settings('languages');
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<string> the languages that are on, in the site's order (en, ar, ru) */
    public static function enabled(): array
    {
        $stored = self::stored()['enabled_locales'] ?? null;
        if (! is_array($stored)) {
            return self::ALL;
        }

        return array_values(array_filter(self::ALL, fn (string $locale) => $locale === self::ALWAYS_ON || in_array($locale, $stored, true)));
    }

    public static function isEnabled(string $locale): bool
    {
        return in_array($locale, self::enabled(), true);
    }

    /** The default language: the stored one when it is on, otherwise config summit.default_locale (en). */
    public static function defaultLocale(): string
    {
        $stored = self::stored()['default_locale'] ?? null;
        $configured = (string) (config('summit.default_locale_configured') ?? config('summit.default_locale', self::ALWAYS_ON));

        return is_string($stored) && self::isEnabled($stored) ? $stored : $configured;
    }

    /** The suggestion bar: the stored true/false, or (null) the server setting SUMMIT_LANGUAGE_SUGGESTION. */
    public static function suggestion(): bool
    {
        $stored = self::stored()['language_suggestion'] ?? null;

        return is_bool($stored) ? $stored : self::suggestionFromEnvironment();
    }

    public static function suggestionFromEnvironment(): bool
    {
        return (bool) (config('summit.features.language_suggestion_configured') ?? config('summit.features.language_suggestion'));
    }

    /**
     * Applies every Languages setting to this request's configuration. Each value is written only when it differs,
     * and the configured values are kept (summit.*_configured) so a later request of the same application (tests)
     * starts from them again.
     */
    public static function apply(): void
    {
        self::filter();

        config()->has('summit.default_locale_configured') || config(['summit.default_locale_configured' => config('summit.default_locale')]);
        $default = self::defaultLocale();
        if ($default !== config('summit.default_locale')) {
            config(['summit.default_locale' => $default]);
        }

        config()->has('summit.features.language_suggestion_configured') || config(['summit.features.language_suggestion_configured' => (bool) config('summit.features.language_suggestion')]);
        $suggestion = self::suggestion();
        if ($suggestion !== config('summit.features.language_suggestion')) {
            config(['summit.features.language_suggestion' => $suggestion]);
        }
    }

    /** Removes the languages that are off from config('summit.locales'), keeping the configured order. */
    public static function filter(): void
    {
        $all = config('summit.locales_configured');
        if (! is_array($all)) {
            $all = (array) config('summit.locales');
            config(['summit.locales_configured' => $all]);
        }
        $locales = array_intersect_key($all, array_flip(self::enabled()));
        if ($locales !== config('summit.locales')) {
            config(['summit.locales' => $locales]);
        }
    }
}
