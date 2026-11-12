<?php

namespace App\Filament\Resources\Users\Pages;

use App\Access\Screens;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Audit;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/** @property User $record */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data + ['role_names' => $this->record->getRoleNames()->all()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $roles = array_values((array) ($data['role_names'] ?? []));
        $removesSuper = $record->hasRole(Screens::SUPER_ADMIN) && ! in_array(Screens::SUPER_ADMIN, $roles, true);
        if ($error = UserResource::guard($record, removesSuperAdmin: $removesSuper)) {
            Notification::make()->danger()->title($error)->send();

            throw new Halt;
        }
        $before = $record->getRoleNames()->all();
        $record->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'admin_locale' => $data['admin_locale'] ?? null,
            'is_admin' => true,
        ])->save();
        $record->syncRoles($roles);
        if ($before !== $roles) {
            Audit::log('users', 'roles', $record, ['user' => $record->getKey(), 'old' => $before, 'new' => $roles]);
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [UserResource::deactivateAction(), UserResource::reactivateAction(), UserResource::resetTwoFactorAction()];
    }
}
