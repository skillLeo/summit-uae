<?php

namespace App\Filament\Http\Middleware;

use App\Filament\Support\AdminLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel only (persistent, so Livewire updates render in the same language). The interface
 * language follows the signed-in admin's choice (users.admin_locale); on the sign-in page the
 * language links (?lang=ar) store a choice in the session. Arabic renders the panel right to left.
 * Never touches the public site's locale handling (SetLocale on the /{locale}/ routes).
 */
final class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $choice = $request->query(AdminLocale::QUERY);
        if ($request->isMethodSafe() && AdminLocale::valid($choice) && $request->hasSession()) {
            $request->session()->put(AdminLocale::SESSION_KEY, $choice);
        }

        $locale = AdminLocale::resolve($request);
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
