<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Backups (docs/briefs/design-2.1-and-admin-cms.md §12.4; System, Backups screen): the daily backup on or off and its
 * time in Dubai ("HH:MM"). routes/console.php schedules `backup:run` at that time and `backup:clean` after it.
 */
class BackupSettings extends Settings
{
    public bool $enabled;

    /** "HH:MM", Asia/Dubai. */
    public string $time;

    public static function group(): string
    {
        return 'backups';
    }
}
