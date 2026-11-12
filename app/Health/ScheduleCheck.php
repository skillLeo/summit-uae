<?php

namespace App\Health;

use Carbon\Carbon;
use Spatie\Health\Checks\Checks\ScheduleCheck as BaseScheduleCheck;
use Spatie\Health\Checks\Result;

/**
 * Scheduler: `health:schedule-check-heartbeat` runs every minute from the scheduler (routes/console.php) and writes the
 * time to the cache. The server's cron must call `php artisan schedule:run` every minute.
 */
final class ScheduleCheck extends BaseScheduleCheck
{
    protected ?string $name = 'schedule';

    public function run(): Result
    {
        $max = (int) config('health.summit.schedule_max_minutes', 2);
        $last = cache()->store($this->cacheStoreName)->get($this->getCacheKey());
        if (! $last) {
            return Outcome::failed('schedule_never');
        }
        $minutes = (int) floor(Carbon::createFromTimestamp($last)->diffInMinutes(now(), true));
        if ($minutes > $max) {
            return Outcome::failed('schedule_stale', ['minutes' => $minutes]);
        }

        return Outcome::ok('schedule_ok', ['minutes' => $minutes]);
    }
}
