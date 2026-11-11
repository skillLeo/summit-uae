<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Applies the language from the URL prefix. Never redirects by Accept-Language or IP. */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->route('locale');
        $locales = config('summit.locales');
        if (! array_key_exists($locale, $locales)) {
            abort(404);
        }

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
        $request->route()?->forgetParameter('locale');

        View::share([
            'locale' => $locale,
            'dir' => $locales[$locale]['dir'],
        ]);

        return $next($request);
    }
}
