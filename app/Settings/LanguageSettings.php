<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Languages (docs/briefs/design-2.1-and-admin-cms.md §12.3, §3.7), edited on Settings, Languages
 * (App\Filament\Cms\Pages\Settings\Languages) and applied by App\Support\Languages:
 *
 *   enabled_locales      the site languages that are on (en, ar, ru; English is always on). A language that is off
 *                        answers 404 on its pages and is left out of the language menus, the hreflang links and the
 *                        sitemap. Default: all three.
 *   default_locale       one of the enabled languages: the x-default hreflang link and the language of the page that
 *                        is not found. Default: en.
 *   language_suggestion  the language-suggestion bar (Design 1; Design 2 has none): true, false, or null = the server
 *                        setting SUMMIT_LANGUAGE_SUGGESTION (config summit.features.language_suggestion). Default null.
 *   arabic_digits        the digits of Arabic pages, "western" (0-9) or "eastern" (٠-٩); a client question.
 */
class LanguageSettings extends Settings
{
    /** @var list<string> */
    public array $enabled_locales;

    public string $default_locale;

    public ?bool $language_suggestion;

    public string $arabic_digits;

    public static function group(): string
    {
        return 'languages';
    }
}
