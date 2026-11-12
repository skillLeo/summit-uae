<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Access\Screens;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Concerns\InteractsWithEmailAuthentication;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property bool $is_admin only these users may open the internal admin panel (/admin)
 * @property string|null $admin_locale en | ar | ru: the admin interface language this user chose
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasEmailAuthentication
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, InteractsWithEmailAuthentication, Notifiable;

    protected string $guard_name = 'web';

    /** Admin interface languages (lang/{en,ar,ru}/admin.php); Arabic switches the panel to RTL. */
    public const ADMIN_LOCALES = ['en', 'ar', 'ru'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_demo' => 'boolean',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * The admin panel is internal: only users flagged is_admin (set from the console, never through a
     * form or mass assignment) may open it. Everyone else is refused with 403 after signing in.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->canUsePanel();
    }

    /**
     * Staff who may use the admin (docs/briefs/design-2.1-and-admin-cms.md §12): flagged is_admin, not deactivated,
     * holding a role (App\Access\Screens). Demo users never sign in.
     */
    public function canUsePanel(): bool
    {
        return $this->is_admin === true && $this->deactivated_at === null && $this->is_demo !== true && $this->roles->isNotEmpty();
    }

    /** Super admin and admin must use two-factor authentication (§12.2). */
    public function requiresTwoFactor(): bool
    {
        return $this->hasAnyRole(Screens::TWO_FACTOR_ROLES);
    }

    public function hasTwoFactor(): bool
    {
        return filled($this->app_authentication_secret) || $this->has_email_authentication === true;
    }
}
