<?php

namespace App\Health;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * The backups of config/backup.php (spatie/laravel-backup): the zip files in the backup folder of the first backup disk
 * (the private "local" disk: storage/app/private/backups). Used by the Backups screen and the "last backup" check.
 * File names come from the disk listing only, never from a request, so a download or a delete can only reach a backup.
 */
final class BackupFiles
{
    public static function diskName(): string
    {
        return (string) (config('backup.backup.destination.disks')[0] ?? 'local');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function folder(): string
    {
        return trim((string) config('backup.backup.name', 'backups'), '/');
    }

    /** @return list<array{key: string, name: string, path: string, size: int, modified: int}> newest first */
    public static function all(): array
    {
        try {
            $disk = self::disk();
            $out = [];
            foreach ($disk->files(self::folder()) as $path) {
                if (! str_ends_with(strtolower($path), '.zip')) {
                    continue;
                }
                $name = basename($path);
                $out[] = [
                    'key' => $name,
                    'name' => $name,
                    'path' => $path,
                    'size' => (int) $disk->size($path),
                    'modified' => (int) $disk->lastModified($path),
                ];
            }
        } catch (Throwable) {
            return [];
        }
        usort($out, fn ($a, $b) => [$b['modified'], $b['name']] <=> [$a['modified'], $a['name']]);

        return $out;
    }

    /** @return array{key: string, name: string, path: string, size: int, modified: int}|null */
    public static function find(string $name): ?array
    {
        foreach (self::all() as $file) {
            if (hash_equals($file['name'], $name)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Run a backup now (the same as the daily one): the database dump and the media, in this request, with the dump
     * timeout of config/backup.php. One at a time (a cache lock). Throws with the command's output on failure.
     */
    public static function run(): string
    {
        $timeout = max(60, (int) config('backup.backup.dump_timeout', 300));
        $lock = Cache::lock('summit:backup-run', $timeout + 60);
        if (! $lock->get()) {
            throw new RuntimeException('busy');
        }
        try {
            @set_time_limit($timeout + 60);
            $before = array_column(self::all(), 'name');
            $code = Artisan::call('backup:run', ['--disable-notifications' => true, '--timeout' => $timeout]);
            $output = Artisan::output();
            $new = array_values(array_diff(array_column(self::all(), 'name'), $before));
            if ($code !== 0 || $new === []) {
                throw new RuntimeException(trim($output) !== '' ? $output : 'backup failed');
            }

            return $new[0];
        } finally {
            $lock->release();
        }
    }
}
