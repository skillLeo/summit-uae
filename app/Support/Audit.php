<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * The admin's audit trail (docs/briefs/design-2.1-and-admin-cms.md §9.4, §10.6, §12.2) on spatie/laravel-activitylog:
 * log "admin", event = the action, description = "screen.action", properties = what changed. Never a visitor's
 * personal data: requests are named by their reference, exports by their row count. A failure to log never breaks
 * the action itself.
 */
final class Audit
{
    public const LOG = 'admin';

    /** @param  array<string, mixed>  $properties */
    public static function log(string $screen, string $action, ?Model $subject = null, array $properties = [], ?User $by = null): void
    {
        try {
            $logger = activity(self::LOG)->event($action)->withProperties($properties);
            if ($subject !== null) {
                $logger->performedOn($subject);
            }
            $by ??= auth()->user() instanceof User ? auth()->user() : null;
            if ($by !== null) {
                $logger->causedBy($by);
            }
            $logger->log($screen.'.'.$action);
        } catch (Throwable) {
            // the audit trail must never undo the action it records
        }
    }
}
