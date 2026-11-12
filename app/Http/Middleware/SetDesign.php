<?php

namespace App\Http\Middleware;

use App\Design\DesignManager;
use App\Support\SiteUrl;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses Design 1 or Design 2 for this request (docs/upgrade-plan.md).
 *
 * `?design=1` / `?design=2` records the visitor's choice in a cookie and the session and redirects to
 * the same address without the parameter (the browser keeps the #fragment), so the choice survives
 * page and language changes. Precedence: query → cookie → session → SUMMIT_DEFAULT_DESIGN. Visitors who
 * never chose get no design cookie.
 *
 * A share link of Settings, Design Preview (signed, ?design_preview=<version>) is accepted in the web group run only:
 * acceptShareLink().
 *
 * Registered twice: globally (so unmatched URLs render their 404 in the chosen design; the session
 * does not exist yet there) and at the end of the web group (after StartSession, to sync the session).
 */
final class SetDesign
{
    public function __construct(private readonly DesignManager $designs) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query->has((string) config('design.share_param')) && $request->hasSession() && $request->isMethodSafe()) {
            return $this->acceptShareLink($request);
        }

        $explicit = DesignManager::normalize($request->query('design'));
        $fromCookie = DesignManager::normalize($request->cookies->get((string) config('design.cookie')));
        $session = $request->hasSession() && $request->session()->isStarted() ? $request->session() : null;
        $fromSession = DesignManager::normalize($session?->get((string) config('design.session_key')));

        $choice = $explicit ?? $fromCookie ?? $fromSession;
        $this->designs->apply($choice ?? $this->designs->default());

        if ($choice !== null && $session !== null && $fromSession !== $choice) {
            $session->put((string) config('design.session_key'), $choice);
        }

        $response = $explicit !== null && $request->isMethodSafe()
            ? $this->redirectWithoutSwitch($request)
            : $next($request);

        if ($choice !== null && $fromCookie !== $choice) {
            $response->headers->setCookie(Cookie::create(
                (string) config('design.cookie'),
                (string) $choice,
                now()->addMinutes((int) config('design.cookie_minutes')),
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                Cookie::SAMESITE_LAX,
            ));
        }

        return $response;
    }

    /**
     * A share link of Settings, Design Preview (DesignManager::createShareLink; web group only, so the cookie is
     * encrypted like the others): when its signature is valid, it has not expired and its version is current, the
     * browser gets the share cookie until the link expires (the switch then shows while the setting is "admins and
     * share links"). Either way the visitor lands on the same page without the link's parameters.
     */
    private function acceptShareLink(Request $request): RedirectResponse
    {
        $param = (string) config('design.share_param');
        $valid = $request->hasValidSignature()
            && (int) $request->query($param) === DesignManager::shareVersion();

        $query = $request->query();
        unset($query[$param], $query['expires'], $query['signature']);
        $url = SiteUrl::abs($request->getPathInfo())
            .($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));

        $response = new RedirectResponse($url, 302);
        $response->headers->set('Cache-Control', 'no-store, private');
        if ($valid) {
            $response->headers->setCookie(DesignManager::shareCookie((int) $request->query('expires'), DesignManager::shareVersion(), $request->isSecure()));
        }

        return $response;
    }

    private function redirectWithoutSwitch(Request $request): RedirectResponse
    {
        $query = $request->query();
        unset($query['design']);
        $url = SiteUrl::abs($request->getPathInfo())
            .($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));

        $response = new RedirectResponse($url, 302);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
