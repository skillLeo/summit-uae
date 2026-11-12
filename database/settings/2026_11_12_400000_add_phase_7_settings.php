<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Phase 7 settings screens (docs/briefs/design-2.1-and-admin-cms.md §12.3) with today's behaviour as the defaults, so
 * every public page is byte-identical to before:
 *
 *   languages.enabled_locales      ["en", "ar", "ru"]   every language on
 *   languages.default_locale       "en"                 the x-default hreflang of today
 *   languages.language_suggestion  null                 the server setting SUMMIT_LANGUAGE_SUGGESTION decides
 *   brand.*                        null                 today's logo files, favicon and the bronze accent
 *   design.default_design          null                 the server setting SUMMIT_DEFAULT_DESIGN decides
 *   design.switcher                null                 the server setting SUMMIT_DESIGN_SWITCHER decides
 *   design.share_version           1                    no share link exists yet
 */
return new class extends SettingsMigration
{
    private const PROPERTIES = [
        'languages.enabled_locales' => ['en', 'ar', 'ru'],
        'languages.default_locale' => 'en',
        'languages.language_suggestion' => null,
        'brand.header_lockup' => null,
        'brand.mark' => null,
        'brand.footer_lockup' => null,
        'brand.favicon' => null,
        'brand.accent' => null,
        'design.default_design' => null,
        'design.switcher' => null,
        'design.share_version' => 1,
    ];

    public function up(): void
    {
        foreach (self::PROPERTIES as $property => $value) {
            if (! $this->migrator->exists($property)) {
                $this->migrator->add($property, $value);
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::PROPERTIES) as $property) {
            $this->migrator->deleteIfExists($property);
        }
    }
};
