<?php

namespace App\Filament\Resources\Users;

use App\Access\Screens;

/** The label of a role: the translated name of a built-in role, else the custom role's own name. */
final class RoleLabel
{
    public static function of(string $name): string
    {
        return in_array($name, Screens::SYSTEM_ROLES, true) ? (string) __('admin_shell.roles.builtin.'.$name) : $name;
    }
}
