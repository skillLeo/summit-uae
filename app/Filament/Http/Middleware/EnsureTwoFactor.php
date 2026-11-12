<?php

namespace App\Filament\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * Two-factor authentication is required for the super admin and the admin (docs/briefs/design-2.1-and-admin-cms.md
 * §12.2). Filament decides at route registration whether its "set up required" middleware runs on every page; this is
 * that middleware (AdminPanelProvider::multiFactorAuthenticationRequiredMiddlewareName), asking the user's roles:
 * a super admin or admin without an authenticator app or e-mail codes is sent to Filament's set-up page; everyone else
 * passes (they may still turn two-factor on in their profile).
 */
final class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User || ! $user->requiresTwoFactor() || MultiFactorChallenge::make()->hasEnabledProviders($user)) {
            return $next($request);
        }

        return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }
}
