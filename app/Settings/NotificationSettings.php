<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Notifications (docs/briefs/design-2.1-and-admin-cms.md §10.5, §12.3): the on-duty e-mail list, the in-panel
 * notifications for staff, the daily digest (Dubai time) and the overdue alerts. The e-mails carry the reference and a
 * link to the panel; the visitor's details only when include_details is on and the mailer really sends (never with the
 * `log` mailer, which would write them into the log).
 */
class NotificationSettings extends Settings
{
    /** @var list<string> */
    public array $recipients;

    public bool $notify_staff;

    public bool $digest_enabled;

    /** "HH:MM", Asia/Dubai */
    public string $digest_time;

    public bool $overdue_alerts;

    public bool $include_details;

    public static function group(): string
    {
        return 'notifications';
    }
}
