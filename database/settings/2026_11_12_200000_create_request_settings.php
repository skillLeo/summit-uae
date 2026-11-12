<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The requests and notifications settings (App\Settings\RequestSettings, App\Settings\NotificationSettings; brief §10.5,
 * §10.6). Defaults: spam after 30 days, closed requests anonymised after 24 months (both client questions), case
 * handlers see every request, and the on-duty list is empty, which means today's enquiry inbox (SUMMIT_ENQUIRY_INBOX).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('requests.spam_min_fill_seconds', 3);
        $this->migrator->add('requests.spam_max_links', 2);
        $this->migrator->add('requests.spam_burst_per_day', 3);
        $this->migrator->add('requests.retention_spam_days', 30);
        $this->migrator->add('requests.retention_closed_months', 24);
        $this->migrator->add('requests.case_handler_scope', 'all');
        $this->migrator->add('requests.consent_privacy_revision_id', null);

        // empty = today's enquiry inbox (SUMMIT_ENQUIRY_INBOX), App\Requests\Notifier::recipients()
        $this->migrator->add('notifications.recipients', []);
        $this->migrator->add('notifications.notify_staff', true);
        $this->migrator->add('notifications.digest_enabled', true);
        $this->migrator->add('notifications.digest_time', '09:00');
        $this->migrator->add('notifications.overdue_alerts', true);
        $this->migrator->add('notifications.include_details', false);
    }

    public function down(): void
    {
        foreach (['spam_min_fill_seconds', 'spam_max_links', 'spam_burst_per_day', 'retention_spam_days', 'retention_closed_months', 'case_handler_scope', 'consent_privacy_revision_id'] as $name) {
            $this->migrator->deleteIfExists('requests.'.$name);
        }
        foreach (['recipients', 'notify_staff', 'digest_enabled', 'digest_time', 'overdue_alerts', 'include_details'] as $name) {
            $this->migrator->deleteIfExists('notifications.'.$name);
        }
    }
};
