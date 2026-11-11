<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Office Hours (brief §12.3): the weekly schedule in Asia/Dubai (ISO weekdays, 1 = Monday), closure dates, the
 * proposed live status (on or off) and the internal review target in business hours (never public). The approved
 * hours SENTENCES stay copy of the pages (App\Design\D2\Hours).
 */
class OfficeHoursSettings extends Settings
{
    public string $timezone;

    /** @var list<int> */
    public array $days;

    public string $open;

    public string $close;

    /** @var list<string> Y-m-d */
    public array $closures;

    public bool $live_status;

    public ?int $review_target_hours;

    public static function group(): string
    {
        return 'hours';
    }
}
