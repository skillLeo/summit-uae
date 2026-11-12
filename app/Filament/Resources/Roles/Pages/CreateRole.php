<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Support\Audit;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $permissions = RoleResource::permissionsFrom((array) ($data['matrix'] ?? []));
        $role->syncPermissions($permissions);
        Audit::log('roles', 'create', $role, ['role' => $role->name, 'permissions' => $permissions]);

        return $role;
    }
}
