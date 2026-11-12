<?php

namespace App\Health;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Disk: the share of the disk that holds storage/ in use (disk_total_space / disk_free_space, no `df` parsing).
 * Warning from 80 %, failed from 90 % (config health.summit).
 */
final class DiskSpaceCheck extends Check
{
    protected ?string $name = 'disk';

    public function run(): Result
    {
        $path = storage_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if (! is_float($total) || ! is_float($free) || $total <= 0) {
            return Outcome::failed('disk_unknown');
        }
        $used = (int) round(($total - $free) / $total * 100);
        $params = ['percent' => $used, 'free' => self::size($free)];
        $failed = (int) config('health.summit.disk_failed_percent', 90);
        $warning = (int) config('health.summit.disk_warning_percent', 80);

        return match (true) {
            $used >= $failed => Outcome::failed('disk_failed', $params),
            $used >= $warning => Outcome::warning('disk_warning', $params),
            default => Outcome::ok('disk_ok', $params),
        };
    }

    public static function size(float|int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i >= 2 ? 1 : 0).' '.$units[$i];
    }
}
