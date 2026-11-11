<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * SEO global defaults (docs/briefs/design-2.1-and-admin-cms.md §8.2 "SEO: per record plus global defaults"), edited on
 * the SEO screen (App\Filament\Cms\Pages\Seo):
 *
 *   title_suffix         {en, ar, ru} or null: today none (every page's SEO title of the DOCX already ends with the
 *                        brand, in its own form), so it is stored empty and not applied to the public pages
 *   default_description  {en, ar, ru} or null: used by App\Support\Seo::make() only for a page that has no meta
 *                        description of its own; today none (null), so no page changes
 *   default_og_image     a Media Library uuid or null: today none (the layouts use public/images/og-default.png)
 *   robots_index         true: pages may be indexed (App\Support\Seo::make() also honours config summit.noindex,
 *                        SUMMIT_NOINDEX, which always wins); false adds "noindex, follow" to every page
 */
class SeoSettings extends Settings
{
    /** @var array<string, string>|null */
    public ?array $title_suffix;

    /** @var array<string, string>|null */
    public ?array $default_description;

    public ?string $default_og_image;

    public bool $robots_index;

    public static function group(): string
    {
        return 'seo';
    }
}
