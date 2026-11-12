<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Staff access since Phase 7 (docs/briefs/design-2.1-and-admin-cms.md §12): a role from the permission matrix, and,
     * for the super admin and the admin, two-factor already set up (an authenticator secret), so the panel does not send
     * the test user to the set-up page.
     */
    protected function grantRole(\App\Models\User $user, string|\Spatie\Permission\Contracts\Role $role = 'super_admin'): \App\Models\User
    {
        $role = is_string($role) ? $role : $role->name;
        if (\Spatie\Permission\Models\Role::query()->where('name', $role)->doesntExist()) {
            \App\Access\RoleSync::run();
        }
        $user->forceFill(['is_admin' => true])->save();
        $user->syncRoles([$role]);
        if (in_array($role, \App\Access\Screens::TWO_FACTOR_ROLES, true)) {
            $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP'])->save();
        }

        return $user->refresh();
    }

    /**
     * Laravel's test client trims trailing slashes; Summit page URLs end with "/" (and
     * EnsureTrailingSlash 301s the slashless form), so keep the slash when a test asks for it.
     */
    protected function prepareUrlForRequest($uri)
    {
        $url = parent::prepareUrlForRequest($uri);
        $path = parse_url((string) $uri, PHP_URL_PATH) ?? '';

        if (str_ends_with($path, '/') && $path !== '/') {
            $query = parse_url((string) $uri, PHP_URL_QUERY);
            $base = $query !== null ? substr($url, 0, (int) strpos($url, '?')) : $url;

            return rtrim($base, '/').'/'.($query !== null ? '?'.$query : '');
        }

        return $url;
    }
}
