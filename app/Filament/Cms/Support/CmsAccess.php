<?php

namespace App\Filament\Cms\Support;

use App\Access\Screens;
use App\Models\User;
use App\Settings\RequestSettings;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * Who may do what in the admin (docs/briefs/design-2.1-and-admin-cms.md §12.1): the roles and the permission matrix
 * of spatie/laravel-permission (App\Access\Screens; permissions "screen.ability"). The super admin may do everything
 * (Gate::before in AppServiceProvider). Every screen, action and widget asks through these methods.
 */
final class CmsAccess
{
    private static function user(?Authenticatable $user): ?User
    {
        $user ??= auth()->user();

        return $user instanceof User ? $user : null;
    }

    /** May open the admin at all: flagged, active and holding a role. */
    public static function admin(?Authenticatable $user = null): bool
    {
        $user = self::user($user);

        return $user !== null && $user->canUsePanel();
    }

    /**
     * Whether the user may do $ability (view, create, update, publish, delete, restore, force_delete, export) on
     * $screen (a key of App\Access\Screens).
     */
    public static function can(string $screen, string $ability = 'view', ?Authenticatable $user = null): bool
    {
        $user = self::user($user);
        if ($user === null || ! $user->canUsePanel()) {
            return false;
        }
        if ($user->hasRole(Screens::SUPER_ADMIN)) {
            return true;
        }
        if (! Screens::exists($screen, $ability)) {
            return false;
        }

        try {
            return $user->hasPermissionTo(Screens::permission($screen, $ability), 'web');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The requests a user may see: everything, or (a user who handles requests but does not manage them, while the
     * Forms and Consent setting is "assigned only") the requests assigned to them.
     *
     * @template TBuilder of \Illuminate\Database\Eloquent\Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function scopeRequests($query, ?Authenticatable $user = null)
    {
        $user = self::user($user);
        if ($user === null || ! self::assignedOnly($user)) {
            return $query;
        }

        return $query->where('assignee_id', $user->getKey());
    }

    public static function assignedOnly(?Authenticatable $user = null): bool
    {
        $user = self::user($user);
        if ($user === null || $user->hasAnyRole([Screens::SUPER_ADMIN, Screens::ADMIN]) || self::can('requests', 'delete', $user)) {
            return false;
        }
        try {
            return app(RequestSettings::class)->case_handler_scope === 'assigned';
        } catch (Throwable) {
            return false;
        }
    }

    /** A read-only user (the Viewer role): figures without links into the workflow. */
    public static function readOnly(?Authenticatable $user = null): bool
    {
        return ! self::can('requests', 'view', $user) && ! self::can('pages', 'update', $user);
    }

    public static function superAdmin(?Authenticatable $user = null): bool
    {
        $user = self::user($user);

        return $user !== null && $user->canUsePanel() && $user->hasRole(Screens::SUPER_ADMIN);
    }
}
