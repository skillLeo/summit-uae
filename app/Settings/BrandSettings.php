<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Brand (docs/briefs/design-2.1-and-admin-cms.md §12.3, §3.8), edited on Settings, Brand
 * (App\Filament\Cms\Pages\Settings\Brand). Every value is null by default, which means today's files and colour:
 *
 *   header_lockup, mark, footer_lockup, favicon   a Media Library uuid (App\Models\MediaAsset) or null = today's file
 *                                                  in public/. Stored only: the public layouts keep today's files until
 *                                                  they are wired to these values.
 *   accent                                         "#RRGGBB" or null = bronze #8A6A2E. Super admin only; saving is
 *                                                  blocked unless white text on it reaches 4.5:1 and it reaches 3:1
 *                                                  against white and the light band. Applied to the admin panel's
 *                                                  primary colour only (App\Filament\Support\AdminPalette), never to the
 *                                                  public CSS.
 *
 * The default social image is the SEO screen's seo.default_og_image (App\Settings\SeoSettings): the Brand screen edits
 * that same value, so there is one source.
 */
class BrandSettings extends Settings
{
    public ?string $header_lockup;

    public ?string $mark;

    public ?string $footer_lockup;

    public ?string $favicon;

    public ?string $accent;

    public static function group(): string
    {
        return 'brand';
    }
}
