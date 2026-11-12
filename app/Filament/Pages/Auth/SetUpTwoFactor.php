<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Http\Middleware\EnsureTwoFactor;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\Pages\SetUpRequiredMultiFactorAuthentication;
use Filament\Facades\Filament;

/**
 * Filament's "set up two-factor" page with a "Set up later" button (owner's decision, 2026-11-12): it lets a super
 * admin or admin into the panel for this sign-in only; EnsureTwoFactor asks again after the next sign-in, because the
 * session (and its flag) is renewed when signing in.
 */
class SetUpTwoFactor extends SetUpRequiredMultiFactorAuthentication
{
    public function getFooterActions(): array
    {
        return [
            $this->getContinueAction(),
            $this->laterAction(),
        ];
    }

    public function laterAction(): Action
    {
        return Action::make('later')
            ->label(__('admin_shell.two_factor_setup.later'))
            ->tooltip(__('admin_shell.two_factor_setup.later_hint'))
            ->color('gray')
            ->outlined()
            ->action(function () {
                session()->put(EnsureTwoFactor::SKIPPED, true);

                return redirect()->intended(Filament::getUrl());
            })
            ->visible(fn (): bool => ! $this->isEnabled());
    }
}
