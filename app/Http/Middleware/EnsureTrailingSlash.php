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
            $query = $request->getQueryString();

            // Absolute target: redirect()->to() would run the path through url(), which strips
            // the trailing slash again and loops.
            return new RedirectResponse(SiteUrl::abs($path.'/').($query !== null ? '?'.$query : ''), 301);
        }

        return $next($request);
    }
}
