<?php

namespace App\Jobs;

use App\Content\Cms\Editing\Publisher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Scheduled publishing (docs/briefs/design-2.1-and-admin-cms.md §8.5 "Schedule publish (queued job)"): publishes every
 * CMS record whose publish_at has come. Dispatched every minute by the scheduler (routes/console.php); unique, so two
 * runs never overlap.
 */
final class PublishScheduledContent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function handle(): void
    {
        Publisher::publishDue();
    }
}
