<?php

use App\Content\Cms\Sources;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The settings groups of App\Settings with today's values (App\Content\Cms\Sources: the contacts and WA01 drafts of the
 * DOCX import, the hours of config/summit.php, the Design 2 switches). `php artisan summit:content-import --fresh`
 * writes them again from the sources; without --fresh it keeps what the settings screens saved.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach ((new Sources)->build()['settings'] as $group => $properties) {
            foreach ($properties as $name => $value) {
                $this->migrator->add("{$group}.{$name}", $value);
            }
        }
    }
};
