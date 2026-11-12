<?php

namespace App\Requests;

use App\Models\Enquiry;
use App\Settings\OfficeHoursSettings;
use Carbon\CarbonInterface;
use Throwable;

/**
 * The internal review target (docs/briefs/design-2.1-and-admin-cms.md §10.5, §12.3): how many business hours a request
 * may wait for its first review. It is internal and never shown on the public site. Set in Office Hours; until the
 * client decides, config('cms.requests.default_review_target_hours') applies (8 = one working day).
 */
final class ReviewTarget
{
    public static function hours(): int
    {
        try {
            $set = app(OfficeHoursSettings::class)->review_target_hours;
        } catch (Throwable) {
            $set = null;
        }

        return max(1, (int) ($set ?? config('cms.requests.default_review_target_hours', 8)));
    }

    public static function isCustom(): bool
    {
        try {
            return app(OfficeHoursSettings::class)->review_target_hours !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /** Business hours the request waited for its first review, or has waited so far. */
    public static function waited(Enquiry $enquiry, ?BusinessHours $hours = null, ?CarbonInterface $now = null): float
    {
        $hours ??= BusinessHours::fromSettings();
        $received = $enquiry->received_at ?? $enquiry->created_at;

        return $hours->between($received, $enquiry->first_reviewed_at ?? $now ?? now());
    }

    /** Still waiting for a first review (status New) and already past the target. */
    public static function isOverdue(Enquiry $enquiry, ?BusinessHours $hours = null, ?CarbonInterface $now = null): bool
    {
        return $enquiry->status === Enquiry::STATUS_NEW
            && $enquiry->first_reviewed_at === null
            && self::waited($enquiry, $hours, $now) > self::hours();
    }
}
