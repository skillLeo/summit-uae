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

    /**
     * Whether the user may do $ability (view, create, update, publish, delete, restore, force_delete, export) on $screen
     * (a key of the permission matrix). Until the roles of Phase 7 every admin may do everything.
     */
    public static function can(string $screen, string $ability = 'view', ?Authenticatable $user = null): bool
    {
        return self::admin($user);
    }

    /**
     * The requests a user may see: everything, or (a case handler while the setting is "assigned only") the requests
     * assigned to them. Until Phase 7, everything.
     *
     * @template TBuilder of \Illuminate\Database\Eloquent\Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function scopeRequests($query, ?Authenticatable $user = null)
    {
        return $query;
    }

    /** A read-only user (the Viewer role): figures without links into the workflow. */
    public static function readOnly(?Authenticatable $user = null): bool
    {
        return false;
    }

    public static function superAdmin(?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        // Phase 7: return $user?->hasRole('super_admin') === true;
        return $user instanceof User && $user->is_admin === true && (bool) config('cms.super_admin_is_admin', true);
    }
}
