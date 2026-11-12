<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Design Preview (docs/briefs/design-2.1-and-admin-cms.md §12.3), edited on Settings, Design Preview
 * (App\Filament\Cms\Pages\Settings\DesignPreview) and applied by App\Design\DesignManager:
 *
 *   default_design  1, 2, or null = the server setting SUMMIT_DEFAULT_DESIGN (config design.default). Default null.
 *   switcher        "off", "admins" (signed-in admins only), "link" (admins and browsers that opened a share link),
 *                   or null = the server setting SUMMIT_DESIGN_SWITCHER (on for everyone, or off). Default null.
 *                   SUMMIT_DESIGN_SWITCHER=false in the environment always wins in production.
 *   share_version   the version of the share links: raising it ("Revoke share links") ends every link and every
 *                   browser that opened one. Default 1.
 */
class DesignSettings extends Settings
{
    public ?int $default_design;

    public ?string $switcher;

    public int $share_version;

    public static function group(): string
    {
        return 'design';
    }
}
