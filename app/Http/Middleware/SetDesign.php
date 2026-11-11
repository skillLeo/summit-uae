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
 * Registered twice: globally (so unmatched URLs render their 404 in the chosen design; the session
 * does not exist yet there) and at the end of the web group (after StartSession, to sync the session).
 */
final class SetDesign
{
    public function __construct(private readonly DesignManager $designs) {}

    public function handle(Request $request, Closure $next): Response
    {
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
