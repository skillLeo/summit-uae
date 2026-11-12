<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getSubheading(): ?string
    {
        return (string) __('admin_shell.users.subheading');
    }

    /** Invite: the user is created with the chosen roles and a temporary password the inviter passes on. */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')->label(__('admin_shell.users.invite'))->icon(Heroicon::OutlinedUserPlus)
                ->visible(fn (): bool => CmsAccess::can('users', 'create'))
                ->modalDescription(__('admin_shell.users.invite_body'))
                ->schema([
                    TextInput::make('name')->label(__('admin_shell.users.name'))->required()->maxLength(120),
                    TextInput::make('email')->label(__('admin_shell.users.email'))->email()->required()->maxLength(254)
                        ->unique(table: 'users', column: 'email')->extraInputAttributes(['dir' => 'ltr']),
                    CheckboxList::make('roles')->label(__('admin_shell.users.roles'))->options(fn (): array => UserResource::roleOptions())->required()->columns(2),
                ])
                ->action(function (array $data): void {
                    $password = Str::password(20);
                    $user = new User;
                    $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'password' => $password, 'is_admin' => true])->save();
                    $user->syncRoles($data['roles']);
                    Audit::log('users', 'invite', $user, ['user' => $user->getKey(), 'roles' => array_values($data['roles'])]);
                    Notification::make()->success()->persistent()
                        ->title(__('admin_shell.users.invited'))
                        ->body(__('admin_shell.users.invite_link').' '.$password)
                        ->send();
                }),
        ];
    }
}
