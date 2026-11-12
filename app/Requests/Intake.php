<?php

namespace App\Requests;

use App\Design\D2\Pending;
use App\Design\DesignManager;
use App\Models\Revision;
use App\Settings\RequestSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * What the inbox keeps about where a request came from (docs/briefs/design-2.1-and-admin-cms.md §10.1), read from the
 * form post without changing its contract (docs/enquiries.md): unknown inputs were always ignored, so these are optional.
 *
 * - source_page  the path of the page that holds the form: the same-origin Referer (both designs send it)
 * - referrer     the external site the visitor came from: only its host (Design 2's form sends `landing_referrer`)
 * - utm_*        the UTM values of the page's address (Design 2's form sends them; otherwise from the Referer's query)
 * - device_class mobile, tablet or desktop, from the User-Agent; the User-Agent itself is never stored
 * - design       1 or 2, as the site served it
 * - privacy_revision_id  the Privacy Policy revision the consent refers to (Forms and Consent setting, else the latest)
 * - extras       the pending-approval extras (audience, situation, ticked documents, tool) only while pending copy is
 *                shown on the site (Pending::visible()); otherwise they are dropped
 * - form_elapsed the milliseconds between opening the form and sending it (Design 2), only for the spam check
 *
 * Nothing here is personal data: no IP, no User-Agent, no query string other than the five UTM keys.
 */
final class Intake
{
    /** @return array<string, mixed> columns for Enquiry */
    public static function context(Request $request): array
    {
        $referer = (string) $request->headers->get('referer', '');
        $own = self::sameOrigin($referer, $request) ? $referer : '';
        $utm = self::utm($request, $own);

        return [
            'source_page' => $own !== '' ? Str::limit((string) (parse_url($own, PHP_URL_PATH) ?: '/'), 250, '') : null,
            'referrer' => self::externalHost((string) $request->input('landing_referrer', ''), $request),
            'device_class' => self::deviceClass((string) $request->userAgent()),
            'design' => self::design($request),
            'privacy_revision_id' => self::privacyRevision(),
            'extras' => self::extras($request),
        ] + $utm;
    }

    public static function elapsedMs(Request $request): ?int
    {
        $value = $request->input('form_elapsed');

        return is_numeric($value) ? (int) $value : null;
    }

    /** @return array<string, string|null> */
    private static function utm(Request $request, string $referer): array
    {
        parse_str((string) parse_url($referer, PHP_URL_QUERY), $fromReferer);
        $out = [];
        foreach (\App\Models\Enquiry::UTM as $key) {
            $value = $request->input($key, $fromReferer[$key] ?? null);
            $value = is_string($value) ? trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '') : '';
            $out[$key] = $value !== '' ? Str::limit($value, 100, '') : null;
        }

        return $out;
    }

    private static function sameOrigin(string $url, Request $request): bool
    {
        if ($url === '') {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && strcasecmp($host, $request->getHost()) === 0;
    }

    private static function externalHost(string $url, Request $request): ?string
    {
        $host = parse_url(trim($url), PHP_URL_HOST);
        if (! is_string($host) || $host === '' || strcasecmp($host, $request->getHost()) === 0) {
            return null;
        }
        $host = strtolower(preg_replace('/^www\./i', '', $host) ?? $host);

        return preg_match('/^[a-z0-9.-]{1,190}$/', $host) === 1 ? $host : null;
    }

    public static function deviceClass(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }
        if (preg_match('/iPad|Tablet|PlayBook|Silk|Kindle|(Android(?!.*Mobile))/i', $userAgent) === 1) {
            return 'tablet';
        }
        if (preg_match('/Mobi|iPhone|iPod|Android|Windows Phone|Opera Mini|IEMobile/i', $userAgent) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }

    private static function design(Request $request): ?int
    {
        try {
            $designs = app(DesignManager::class);
            if ($designs->applied()) {
                return $designs->current();
            }
        } catch (Throwable) {
            // fall through
        }

        return DesignManager::normalize($request->cookies->get((string) config('design.cookie')));
    }

    private static function privacyRevision(): ?int
    {
        try {
            $pinned = app(RequestSettings::class)->consent_privacy_revision_id;
            if ($pinned !== null) {
                return $pinned;
            }
        } catch (Throwable) {
            // the latest revision below
        }

        return rescue(fn () => Revision::query()->where('revisionable_type', 'page')
            ->where('revisionable_id', (string) config('cms.privacy_page', 'L01'))
            ->latest('id')->value('id'), null, report: false);
    }

    /** @return array<string, mixed>|null */
    private static function extras(Request $request): ?array
    {
        if (! rescue(fn () => Pending::visible(), false, report: false)) {
            return null;
        }
        $out = [];
        foreach (['audience' => 40, 'situation' => 40, 'tool' => 40] as $key => $max) {
            $value = $request->input('context_'.$key);
            if (is_string($value) && preg_match('/^[A-Za-z0-9_-]{1,'.$max.'}$/', $value) === 1) {
                $out[$key] = $value;
            }
        }
        $documents = $request->input('context_documents');
        if (is_string($documents) && $documents !== '') {
            $list = array_values(array_filter(array_map('trim', explode("\n", $documents)), fn ($d) => $d !== ''));
            $out['documents'] = array_map(fn ($d) => Str::limit($d, 200, ''), array_slice($list, 0, 30));
        }

        return $out !== [] ? $out : null;
    }
}
