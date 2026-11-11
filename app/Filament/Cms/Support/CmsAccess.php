<?php

namespace App\Filament\Cms\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may do what in the CMS screens, until roles arrive (docs/briefs/design-2.1-and-admin-cms.md §12.1, Phase 7).
 *
 * Today every admin user (users.is_admin) may edit, publish and move content to the Trash. The super-admin-only actions
 * (permanent delete, calculator rules) go through superAdmin(): for now it is the same admin flag.
 *
 * PHASE 7 HOOK: replace superAdmin() with the "super admin" role of spatie/laravel-permission (and admin() with the
 * per-screen permissions of the matrix). Every caller already goes through these two methods.
 */
final class CmsAccess
{
    public static function admin(?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User && $user->is_admin === true;
    }

    public static function superAdmin(?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        // Phase 7: return $user?->hasRole('super_admin') === true;
        return $user instanceof User && $user->is_admin === true && (bool) config('cms.super_admin_is_admin', true);
    }
}
