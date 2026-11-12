<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** The daily backup (App\Settings\BackupSettings; brief §12.4): on, at 02:30 Dubai time. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('backups.enabled', true);
        $this->migrator->add('backups.time', '02:30');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('backups.enabled');
        $this->migrator->deleteIfExists('backups.time');
    }
};
