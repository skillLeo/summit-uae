<?php

namespace App\Providers;

use App\Health\CacheCheck;
use App\Health\DatabaseCheck;
use App\Health\DebugModeCheck;
use App\Health\DiskSpaceCheck;
use App\Health\LastBackupCheck;
use App\Health\MailCheck;
use App\Health\MySqlDump;
use App\Health\QueueCheck;
use App\Health\ScheduleCheck;
use App\Support\ActivityLogging;
use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\Health\Facades\Health;

/**
 * The System screens (docs/briefs/design-2.1-and-admin-cms.md §12.2, §12.4; Phase 7):
 * - the activity log's listeners and its secret mask (App\Support\ActivityLogging);
 * - the health checks of the Health screen (App\Health\*);
 * - the MySQL dump of the backups: mysqldump from BACKUP_MYSQLDUMP_PATH (config backup.backup.mysqldump_path, the
 *   folder that holds the binary; empty = the first of MYSQLDUMP_FOLDERS that has it, else the PATH), in one
 *   transaction (InnoDB, no table locks, no GTIDs: App\Health\MySqlDump), within the dump timeout.
 */
class SystemServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        ActivityLogging::register();

        DbDumperFactory::extend('mysql', function (): MySqlDump {
            $dumper = (new MySqlDump)
                ->useSingleTransaction()
                ->skipLockTables()
                ->setTimeout(max(60, (int) config('backup.backup.dump_timeout', 300)));
            $path = self::mysqldumpFolder();
            if ($path !== '') {
                $dumper->setDumpBinaryPath($path);
            }

            return $dumper;
        });

        Health::checks([
            DatabaseCheck::new(),
            CacheCheck::new(),
            QueueCheck::new(),
            ScheduleCheck::new(),
            MailCheck::new(),
            DiskSpaceCheck::new(),
            LastBackupCheck::new(),
            DebugModeCheck::new(),
        ]);
    }

    /** Folders searched for mysqldump when BACKUP_MYSQLDUMP_PATH is empty (servers, Homebrew, XAMPP), in this order. */
    public const MYSQLDUMP_FOLDERS = ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin', '/usr/local/mysql/bin', '/Applications/XAMPP/bin'];

    /** The folder of mysqldump: BACKUP_MYSQLDUMP_PATH, else the first known folder that has it, else '' (the PATH). */
    public static function mysqldumpFolder(): string
    {
        $path = rtrim((string) config('backup.backup.mysqldump_path', ''), '/');
        if ($path !== '') {
            return $path;
        }
        foreach (self::MYSQLDUMP_FOLDERS as $folder) {
            if (@is_executable($folder.'/mysqldump')) {
                return $folder;
            }
        }

        return '';
    }
}
