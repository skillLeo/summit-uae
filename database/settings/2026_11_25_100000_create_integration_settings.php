<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Integrations (App\Settings\IntegrationSettings): the Google Analytics 4 measurement ID, empty until the owner confirms
 * it (null = the server setting GA4_MEASUREMENT_ID, itself empty by default: no tag).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('integrations.ga4_measurement_id')) {
            $this->migrator->add('integrations.ga4_measurement_id', null);
        }
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('integrations.ga4_measurement_id');
    }
};
