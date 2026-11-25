<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Integrations (docs/briefs/design2-only-black-header-ga4.md Part C), edited on Settings, Integrations
 * (App\Filament\Cms\Pages\Settings\Integrations; super admin only) and read by App\Support\Analytics:
 *
 *   ga4_measurement_id   the Google Analytics 4 measurement ID ("G-" and 6 to 12 capitals or digits), or null = the
 *                        server setting GA4_MEASUREMENT_ID (config services.ga4.id). Empty or invalid: no tag.
 *                        Default null.
 */
class IntegrationSettings extends Settings
{
    public ?string $ga4_measurement_id;

    public static function group(): string
    {
        return 'integrations';
    }
}
