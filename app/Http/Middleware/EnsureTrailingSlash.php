<?php

namespace App\Http\Middleware;

use App\Support\SiteUrl;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Every page URL ends with "/" (the existing summitlegaluae.com convention). */
final class EnsureTrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        if ($request->isMethodSafe() && ! str_ends_with($path, '/')) {
            // Root-relative target, never built from the Host header (SiteUrl::redirectTarget()); not redirect()->to(),
            // which would run the path through url(), strip the trailing slash again and loop.
            return new RedirectResponse(SiteUrl::redirectTarget($path.'/', $request->getQueryString()), 301);
        }

        return $next($request);
    }
}
