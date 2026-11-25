<?php

namespace App\Support;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Throwable;

/**
 * Google Analytics 4 (docs/briefs/design2-only-black-header-ga4.md Part C).
 *
 * The measurement ID: Settings, Integrations (App\Settings\IntegrationSettings, super admin only) when it is set,
 * otherwise GA4_MEASUREMENT_ID (config services.ga4.id). It must match PATTERN; empty or invalid = no tag at all.
 *
 * The tag (resources/views/designs/d2/partials/analytics.blade.php, in the head of d2::layouts.app and the root
 * chooser) is printed only when every condition holds: the environment is production; the request matched a route
 * (an unmatched URL renders the 404 page); the page is not an error, offline or styleguide page; it is not the admin
 * (/admin, Livewire) and not a signed CMS preview (?cms_preview); nobody is signed in (every account is staff). The
 * page records its decision on the request (ATTRIBUTE), so the consent bar, the footer's Privacy Settings button and
 * the CSP (App\Http\Middleware\SecurityHeaders opens Google's origins only for that response) follow the same answer.
 */
final class Analytics
{
    /** "G-" and 6 to 12 capitals or digits (brief C2); D: "$" never accepts a trailing line break. */
    public const PATTERN = '/^G-[A-Z0-9]{6,12}$/D';

    /** Request attribute: the measurement ID this response prints, or null. */
    public const ATTRIBUTE = 'summit.ga4';

    /** The script origin (CSP script-src). */
    public const SCRIPT_ORIGIN = 'https://www.googletagmanager.com';

    /** Where the tag sends its hits (CSP connect-src and img-src). */
    public const COLLECT_ORIGINS = ['https://*.google-analytics.com', 'https://*.analytics.google.com', 'https://www.googletagmanager.com'];

    /** $pageKey values of the public layouts that never get the tag. */
    private const EXCLUDED_PAGES = ['error', 'offline', 'styleguide'];

    public static function valid(mixed $id): bool
    {
        return is_string($id) && preg_match(self::PATTERN, $id) === 1;
    }

    /** The configured measurement ID (setting, else environment), or null when it is empty or invalid. */
    public static function measurementId(): ?string
    {
        try {
            $stored = content()->settings('integrations')['ga4_measurement_id'] ?? null;
        } catch (Throwable) {
            $stored = null;
        }
        $id = is_string($stored) && trim($stored) !== '' ? $stored : config('services.ga4.id');
        $id = is_string($id) ? trim($id) : null;

        return self::valid($id) ? $id : null;
    }

    /** Whether this request may carry the tag at all (every condition but the ID). */
    public static function allowed(Request $request, ?string $pageKey = null): bool
    {
        if (! app()->isProduction() || in_array($pageKey, self::EXCLUDED_PAGES, true)) {
            return false;
        }
        if ($request->route() === null || SecurityHeaders::isAdminRequest($request)) {
            return false;
        }
        if ($request->query->has('cms_preview') || content()->previewing()) {
            return false;
        }

        return $request->user() === null;
    }

    /**
     * The measurement ID when this page prints the tag, else null. Recorded on the request for active() and the CSP.
     */
    public static function tag(?string $pageKey = null, ?Request $request = null): ?string
    {
        $request ??= request();
        $id = self::allowed($request, $pageKey) ? self::measurementId() : null;
        $request->attributes->set(self::ATTRIBUTE, $id);

        return $id;
    }

    /** Whether this response printed the tag (tag() ran and returned an ID). */
    public static function active(?Request $request = null): bool
    {
        return self::valid(($request ?? request())->attributes->get(self::ATTRIBUTE));
    }
}
