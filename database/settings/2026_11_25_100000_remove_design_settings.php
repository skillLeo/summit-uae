<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Design 2 is the only design (owner, 2026-11-25): the Design Preview settings (the default design, who saw the design
 * switch, the share-link version) are retired with the switch. down() puts them back with their old defaults.
 */
return new class extends SettingsMigration
{
    private const PROPERTIES = [
        'design.default_design' => null,
        'design.switcher' => null,
        'design.share_version' => 1,
    ];

    public function up(): void
    {
        foreach (array_keys(self::PROPERTIES) as $property) {
            $this->migrator->deleteIfExists($property);
        }
    }

    public function down(): void
    {
        foreach (self::PROPERTIES as $property => $value) {
            if (! $this->migrator->exists($property)) {
                $this->migrator->add($property, $value);
            }
        }
    }
};
