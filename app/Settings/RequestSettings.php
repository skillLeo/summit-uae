<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Requests (docs/briefs/design-2.1-and-admin-cms.md §10.6, §12.1, §12.3 "Forms and Consent"): the spam rules, the
 * retention periods and which requests a case handler sees. The internal review target lives with the office hours
 * (OfficeHoursSettings::$review_target_hours), because it is counted in business hours.
 */
class RequestSettings extends Settings
{
    /** A request sent sooner than this after the form was opened scores as spam (only when the form reports it). */
    public int $spam_min_fill_seconds;

    /** More links than this in the summary score as spam. */
    public int $spam_max_links;

    /** More accepted requests than this from one hashed IP within 24 hours score as spam. */
    public int $spam_burst_per_day;

    /** Spam is deleted after this many days. */
    public int $retention_spam_days;

    /** Closed requests are anonymised this many months after they were closed (the client decides; default 24). */
    public int $retention_closed_months;

    /** Case handlers see "all" requests or only those "assigned" to them. */
    public string $case_handler_scope;

    /** revisions.id of the Privacy Policy the consent text is linked to (null: the latest published revision). */
    public ?int $consent_privacy_revision_id;

    public static function group(): string
    {
        return 'requests';
    }
}
