<?php

namespace App\Health;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Health\Checks\Checks\QueueCheck as BaseQueueCheck;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * Queue: a worker ran the heartbeat job lately. `health:queue-check-heartbeat` (every minute, routes/console.php)
 * queues the job; a worker (`php artisan queue:work`) runs it and writes the time to the cache. With the "sync"
 * connection jobs run at once, so there is nothing to wait for. Failed jobs are a warning.
 */
final class QueueCheck extends BaseQueueCheck
{
    protected ?string $name = 'queue';

    public function run(): Result
    {
        $connection = (string) config('queue.default');
        if ($connection === 'sync') {
            return Outcome::ok('queue_sync');
        }
        $max = (int) config('health.summit.queue_max_minutes', 5);
        $queue = $this->getQueues()[0] ?? 'default';
        $last = $this->cacheStore()->get($this->getHeartbeatCacheKey($queue));
        $failedJobs = (int) rescue(fn () => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0, 0, report: false);
        $waiting = (int) rescue(fn () => $connection === 'database' && Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0, 0, report: false);

        try {
            if (! $last) {
                return Outcome::failed($waiting > 0 ? 'queue_never_waiting' : 'queue_never', ['waiting' => $waiting]);
            }
            $minutes = (int) floor(Carbon::createFromTimestamp($last)->diffInMinutes(now(), true));
            if ($minutes > $max) {
                return Outcome::failed($waiting > 0 ? 'queue_stale_waiting' : 'queue_stale', ['minutes' => $minutes, 'waiting' => $waiting]);
            }
            if ($failedJobs > 0) {
                return Outcome::warning('queue_failed_jobs', ['count' => $failedJobs]);
            }

            return Outcome::ok('queue_ok', ['connection' => $connection, 'minutes' => $minutes]);
        } catch (Throwable) {
            return Outcome::failed('queue_never');
        }
    }
}
