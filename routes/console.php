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
