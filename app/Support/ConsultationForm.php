<?php

namespace App\Support;

/**
 * The consultation form switch (config('summit.consultation_form'); client PDF item 7, owner 2026-11-25: WhatsApp
 * only). Off: no wizard on any page, "Request a Legal Consultation" opens WhatsApp (App\Support\SiteUrl::consultation),
 * the enquiry endpoint answers 404, the admin screens of the requests (REQUEST_SCREENS) are hidden for every role and
 * the request jobs are not scheduled. The code, the enquiries table and its rows stay.
 */
final class ConsultationForm
{
    /** Admin screens (App\Access\Screens keys) that only serve the form's requests. */
    public const REQUEST_SCREENS = ['requests', 'reports', 'settings_forms', 'settings_notifications'];

    public static function enabled(): bool
    {
        return (bool) config('summit.consultation_form', false);
    }

    /** Whether an admin screen is retired while the form is off. */
    public static function retires(string $screen): bool
    {
        return ! self::enabled() && in_array($screen, self::REQUEST_SCREENS, true);
    }
}
