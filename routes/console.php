<?php

use App\Content\Cms\Editing\Publisher;
use App\Jobs\PublishScheduledContent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CMS scheduled publishing (docs/briefs/design-2.1-and-admin-cms.md §8.5): every minute, a queued job publishes the
// records whose publish_at has come. `php artisan summit:publish-scheduled` does the same at once.
Schedule::job(new PublishScheduledContent)->everyMinute()->name('cms-publish-scheduled')->withoutOverlapping();

Artisan::command('summit:publish-scheduled', function () {
    $count = Publisher::publishDue();
    $this->info("Published {$count} scheduled record(s).");
})->purpose('Publish the CMS records whose scheduled time has come');

// Requests inbox (docs/briefs/design-2.1-and-admin-cms.md §10.5, §10.6), in Dubai time:
// - the retention job every night (spam and the Trash after N days, closed requests anonymised after N months);
// - the daily digest at the Notifications setting's time (09:00 by default), when it is on;
// - the overdue sweep every 15 minutes (one alert per request past the internal target in business hours).
Schedule::command('summit:requests-retention')->dailyAt('03:30')->timezone('Asia/Dubai')->name('requests-retention')->withoutOverlapping();
Schedule::command('summit:requests-digest')
    ->dailyAt((string) rescue(fn () => App\Requests\Notifier::settings()['digest_time'], '09:00', report: false))
    ->timezone('Asia/Dubai')
    ->when(fn (): bool => (bool) rescue(fn () => App\Requests\Notifier::settings()['digest_enabled'], true, report: false))
    ->name('requests-digest');
Schedule::command('summit:requests-overdue')->everyFifteenMinutes()
    ->when(fn (): bool => (bool) rescue(fn () => App\Requests\Notifier::settings()['overdue_alerts'], true, report: false))
    ->name('requests-overdue')->withoutOverlapping();

Artisan::command('summit:requests-retention', function () {
    $out = App\Requests\Privacy::retention();
    $this->info("Spam deleted: {$out['spam_deleted']}. Trash deleted: {$out['trash_deleted']}. Closed requests anonymised: {$out['anonymised']}.");
})->purpose('Apply the request retention periods (Forms and Consent settings)');

Artisan::command('summit:requests-digest', function () {
    $figures = App\Requests\Digest::figures();
    $sent = app(App\Requests\Notifier::class)->digest($figures);
    $this->info("Digest queued for {$sent} recipient(s): {$figures['received']} received, {$figures['waiting']} waiting, {$figures['overdue']} over target.");
})->purpose('Send the daily requests digest to the on-duty list');

Artisan::command('summit:requests-overdue', function () {
    $count = App\Requests\Digest::sweepOverdue(app(App\Requests\Notifier::class));
    $this->info("Overdue alerts sent: {$count}.");
})->purpose('Alert the on-duty list about requests past the internal review target');

// System (docs/briefs/design-2.1-and-admin-cms.md §12.4; Phase 7), in Dubai time:
// - the daily backup at the Backups screen's time (App\Settings\BackupSettings, 02:30 by default, when it is on): the
//   database and the media to storage/app/private/backups (config/backup.php), then backup:clean (the default
//   retention: every backup for 7 days, then daily for 16 days, weekly for 8 weeks, monthly for 4 months);
// - the health heartbeats every minute (the scheduler check and, through a queued job, the queue check) and the checks
//   every five minutes (the Health screen shows the latest results; its "Run checks" button runs them at once).
$backupTime = (string) rescue(fn () => app(App\Settings\BackupSettings::class)->time, '02:30', report: false);
$backupTime = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $backupTime) === 1 ? $backupTime : '02:30';
[$backupHour, $backupMinute] = array_map('intval', explode(':', $backupTime));
$cleanTime = sprintf('%02d:%02d', ($backupHour + intdiv($backupMinute + 30, 60)) % 24, ($backupMinute + 30) % 60);
Schedule::command('backup:run --disable-notifications')->dailyAt($backupTime)->timezone('Asia/Dubai')
    ->when(fn (): bool => (bool) rescue(fn () => app(App\Settings\BackupSettings::class)->enabled, true, report: false))
    ->name('backup-run')->withoutOverlapping();
Schedule::command('backup:clean --disable-notifications')->dailyAt($cleanTime)->timezone('Asia/Dubai')
    ->when(fn (): bool => (bool) rescue(fn () => app(App\Settings\BackupSettings::class)->enabled, true, report: false))
    ->name('backup-clean')->withoutOverlapping();
Schedule::command('health:schedule-check-heartbeat')->everyMinute()->name('health-schedule-heartbeat');
Schedule::command('health:queue-check-heartbeat')->everyMinute()->name('health-queue-heartbeat');
Schedule::command('health:check --no-notification')->everyFiveMinutes()->name('health-check')->withoutOverlapping();
