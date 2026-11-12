<?php

namespace App\Filament\Resources\Users;

use App\Access\Screens;
use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Support\RequestFormat;
use App\Models\User;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * Users (docs/briefs/design-2.1-and-admin-cms.md §12.4): staff who may sign in, their roles, two-factor status and last
 * sign-in; invite (a one-time temporary password shown to the inviter: mail is not delivered from the server yet, and
 * a reset link must never land in a log), deactivate (sessions end at once), reset two-factor. Demo users are hidden.
 * Guards: nobody deactivates themselves or removes their own super admin role, and one active super admin remains.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'system/users';

    protected static string|UnitEnum|null $navigationGroup = 'system';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 10;

    protected static bool $isGloballySearchable = false;

    protected static bool $hasTitleCaseModelLabel = false;

    public static function screen(): string
    {
        return 'users';
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin_shell.users.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin_shell.users.plural');
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof UnitEnum ? $action->name : $action;
        $ability = \App\Filament\Cms\CmsResource::ABILITY[$action] ?? $action;

        return CmsAccess::can('users', $ability) ? Response::allow() : Response::deny((string) __('cms.access.denied'));
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false; // the Invite action creates users
    }

    /** @return Builder<User> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_demo', false)->with('roles');
    }

    /** @return array<string, string> role name => label */
    public static function roleOptions(): array
    {
        $out = [];
        foreach (Role::query()->where('guard_name', 'web')->orderBy('id')->get() as $role) {
            $out[$role->name] = RoleLabel::of($role->name);
        }

        return $out;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->schema([
                TextInput::make('name')->label(__('admin_shell.users.name'))->required()->maxLength(120),
                TextInput::make('email')->label(__('admin_shell.users.email'))->email()->required()->maxLength(254)
                    ->unique(ignoreRecord: true)->extraInputAttributes(['dir' => 'ltr']),
                Select::make('admin_locale')->label(__('admin.locale.label'))
                    ->options(['en' => 'English', 'ar' => 'العربية', 'ru' => 'Русский'])->native()->placeholder('—'),
                CheckboxList::make('role_names')->label(__('admin_shell.users.roles'))
                    ->options(fn (): array => self::roleOptions())->required()->columns(2),
            ]),
        ]);
    }

    public static function twoFactorState(User $user): string
    {
        if ($user->hasTwoFactor()) {
            return 'on';
        }

        return $user->requiresTwoFactor() ? 'required' : 'off';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('admin_shell.users.name'))->searchable()->sortable()->weight('medium')
                    ->description(fn (User $u): string => (string) $u->email),
                TextColumn::make('roles.name')->label(__('admin_shell.users.roles'))->badge()->color('gray')
                    ->formatStateUsing(fn (?string $state): string => RoleLabel::of((string) $state)),
                TextColumn::make('two_factor')->label(__('admin_shell.users.two_factor'))->badge()
                    ->state(fn (User $u): string => self::twoFactorState($u))
                    ->formatStateUsing(fn (string $state): string => (string) __('admin_shell.users.two_factor_'.$state))
                    ->color(fn (string $state): string => match ($state) { 'on' => 'success', 'required' => 'warning', default => 'gray' })
                    ->icon(fn (string $state): Heroicon => match ($state) { 'on' => Heroicon::OutlinedShieldCheck, 'required' => Heroicon::OutlinedExclamationTriangle, default => Heroicon::OutlinedMinusCircle }),
                TextColumn::make('last_login_at')->label(__('admin_shell.users.last_login'))->sortable()
                    ->formatStateUsing(fn (User $u): string => $u->last_login_at ? RequestFormat::received($u->last_login_at) : (string) __('admin_shell.users.never'))
                    ->placeholder(__('admin_shell.users.never'))
                    ->tooltip(fn (User $u): ?string => $u->last_login_at ? RequestFormat::exact($u->last_login_at) : null),
                TextColumn::make('status')->label(__('admin_shell.users.status'))->badge()
                    ->state(fn (User $u): string => $u->deactivated_at === null ? 'active' : 'deactivated')
                    ->formatStateUsing(fn (string $state): string => (string) __('admin_shell.users.'.$state))
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray')
                    ->icon(fn (string $state): Heroicon => $state === 'active' ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedNoSymbol),
            ])
            ->recordActions([
                EditAction::make(),
                self::deactivateAction(),
                self::reactivateAction(),
                self::resetTwoFactorAction(),
            ])
            ->paginated([25, 50, 100]);
    }

    public static function guard(User $target, bool $removesSuperAdmin = false, bool $deactivates = false): ?string
    {
        $me = auth()->user();
        if ($me instanceof User && $me->is($target) && ($removesSuperAdmin || $deactivates)) {
            return (string) __('admin_shell.users.own_account');
        }
        if ($target->hasRole(Screens::SUPER_ADMIN) && ($removesSuperAdmin || $deactivates)) {
            $others = User::role(Screens::SUPER_ADMIN)->whereNull('deactivated_at')->whereKeyNot($target->getKey())->count();
            if ($others === 0) {
                return (string) __('admin_shell.users.last_super_admin');
            }
        }

        return null;
    }

    public static function deactivateAction(): Action
    {
        return Action::make('deactivate')->label(__('admin_shell.users.deactivate'))->icon(Heroicon::OutlinedNoSymbol)->color('danger')
            ->visible(fn (User $record): bool => $record->deactivated_at === null && CmsAccess::can('users', 'update'))
            ->requiresConfirmation()->modalDescription(__('admin_shell.users.deactivate_body'))
            ->action(function (User $record): void {
                if ($error = self::guard($record, deactivates: true)) {
                    Notification::make()->danger()->title($error)->send();

                    return;
                }
                $record->forceFill(['deactivated_at' => now(), 'remember_token' => null])->save();
                DB::table('sessions')->where('user_id', $record->getKey())->delete();
                Audit::log('users', 'deactivate', $record, ['user' => $record->getKey()]);
                Notification::make()->success()->title(__('admin_shell.users.saved'))->send();
            });
    }

    public static function reactivateAction(): Action
    {
        return Action::make('reactivate')->label(__('admin_shell.users.reactivate'))->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')
            ->visible(fn (User $record): bool => $record->deactivated_at !== null && CmsAccess::can('users', 'update'))
            ->action(function (User $record): void {
                $record->forceFill(['deactivated_at' => null])->save();
                Audit::log('users', 'reactivate', $record, ['user' => $record->getKey()]);
                Notification::make()->success()->title(__('admin_shell.users.saved'))->send();
            });
    }

    public static function resetTwoFactorAction(): Action
    {
        return Action::make('resetTwoFactor')->label(__('admin_shell.users.reset_two_factor'))->icon(Heroicon::OutlinedKey)->color('gray')
            ->visible(fn (User $record): bool => $record->hasTwoFactor() && CmsAccess::can('users', 'update'))
            ->requiresConfirmation()->modalDescription(__('admin_shell.users.reset_two_factor_body'))
            ->action(function (User $record): void {
                $record->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null, 'has_email_authentication' => false])->save();
                Audit::log('users', 'reset_two_factor', $record, ['user' => $record->getKey()]);
                Notification::make()->success()->title(__('admin_shell.users.saved'))->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
