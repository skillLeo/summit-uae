<?php

namespace App\Access;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the permissions of the matrix (App\Access\Screens) and the five built-in roles with their default sets
 * (docs/briefs/design-2.1-and-admin-cms.md §12.1). Idempotent: run by the Phase 7 migration and by
 * `php artisan summit:roles-sync`. A built-in role keeps the permissions a super admin added or removed on the Roles
 * screen unless --reset is given; permissions that no longer exist are removed.
 */
final class RoleSync
{
    public static function run(bool $promoteAdmins = false, bool $reset = false): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $names = Screens::permissions();
        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Permission::query()->where('guard_name', 'web')->whereNotIn('name', $names)->delete();

        Role::findOrCreate(Screens::SUPER_ADMIN, 'web');
        foreach (Screens::roles() as $role => $permissions) {
            $model = Role::findOrCreate($role, 'web');
            if ($reset || ! $model->permissions()->exists()) {
                $model->syncPermissions($permissions);
            }
        }

        if ($promoteAdmins) {
            foreach (User::query()->where('is_admin', true)->where('is_demo', false)->get() as $user) {
                if ($user->roles()->doesntExist()) {
                    $user->assignRole(Screens::SUPER_ADMIN);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
