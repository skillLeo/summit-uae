<?php

namespace App\Health;

use App\Settings\BackupSettings;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Last backup: the newest backup on the backup disk (App\Health\BackupFiles::all()). Warning when
 * there is none or the newest is older than 48 hours, failed from 96 hours (config health.summit); a warning too
 * while the daily backup is switched off on the Backups screen.
 */
final class LastBackupCheck extends Check
{
    protected ?string $name = 'backup';

    public function run(): Result
    {
        $files = BackupFiles::all();
        $newest = $files[0] ?? null;
        $scheduled = (bool) rescue(fn () => app(BackupSettings::class)->enabled, true, report: false);
        if ($newest === null) {
            return Outcome::warning('backup_none');
        }
        $minutes = max(0, (int) floor((now()->getTimestamp() - $newest['modified']) / 60));
        $hours = intdiv($minutes, 60);
        $params = ['hours' => $hours, 'size' => DiskSpaceCheck::size($newest['size'])];

        return match (true) {
            $hours >= (int) config('health.summit.backup_failed_hours', 96) => Outcome::failed('backup_stale', $params),
            $hours >= (int) config('health.summit.backup_warning_hours', 48) => Outcome::warning('backup_old', $params),
            ! $scheduled => Outcome::warning('backup_unscheduled', $params),
            $hours < 1 => Outcome::ok('backup_ok_recent', ['minutes' => $minutes, 'size' => $params['size']]),
            default => Outcome::ok('backup_ok', $params),
        };
    }
}
