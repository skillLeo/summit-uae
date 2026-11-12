<?php

namespace App\Filament\Resources\Roles;

use App\Access\Screens;
use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Users\RoleLabel;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * Roles and Permissions (docs/briefs/design-2.1-and-admin-cms.md §12.1, §12.4): the five built-in roles and custom
 * roles, edited as a matrix of screens (rows) and abilities (view, create, update, publish, delete, restore, force
 * delete, export; only those that exist on a screen). The super admin may do everything and is not editable; built-in
 * roles keep their names and cannot be deleted; a custom role in use cannot be deleted.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $slug = 'system/roles';

    protected static string|UnitEnum|null $navigationGroup = 'system';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 20;

    protected static bool $isGloballySearchable = false;

    protected static bool $hasTitleCaseModelLabel = false;

    public static function screen(): string
    {
        return 'roles';
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin_shell.roles.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin_shell.roles.plural');
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof UnitEnum ? $action->name : $action;
        $ability = \App\Filament\Cms\CmsResource::ABILITY[$action] ?? $action;

        return CmsAccess::can('roles', $ability) ? Response::allow() : Response::deny((string) __('cms.access.denied'));
    }

    public static function canEdit(Model $record): bool
    {
        return CmsAccess::can('roles', 'update') && $record->getAttribute('name') !== Screens::SUPER_ADMIN;
    }

    public static function canDelete(Model $record): bool
    {
        return CmsAccess::can('roles', 'delete')
            && ! in_array($record->getAttribute('name'), Screens::SYSTEM_ROLES, true)
            && ! $record->users()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** Matrix state key of a screen's abilities. */
    public static function field(string $screen): string
    {
        return 'matrix.'.$screen;
    }

    public static function form(Schema $schema): Schema
    {
        $sections = [];
        $groups = [];
        foreach (Screens::all() as $screen => $def) {
            $groups[$def['group']][$screen] = $def['abilities'];
        }
        foreach ($groups as $group => $screens) {
            $fields = [];
            foreach ($screens as $screen => $abilities) {
                $options = [];
                foreach ($abilities as $ability) {
                    $options[$ability] = (string) __('admin_shell.ability.'.$ability);
                }
                $fields[] = CheckboxList::make(self::field($screen))
                    ->label(__('admin_shell.screen.'.$screen))
                    ->options($options)
                    ->bulkToggleable()
                    ->columns(['default' => 2, 'md' => 4, 'xl' => 8])
                    ->extraAttributes(['data-matrix' => $screen]);
            }
            $sections[] = Section::make(__('admin_shell.group.'.$group))->schema($fields)->collapsible();
        }

        return $schema->columns(1)->components([
            Section::make()->schema([
                TextInput::make('name')->label(__('admin_shell.roles.name'))
                    ->helperText(__('admin_shell.roles.name_hint'))
                    ->required()->maxLength(60)->regex('/^[a-z][a-z0-9_]{1,59}$/')
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?Role $record): bool => $record !== null && in_array($record->name, Screens::SYSTEM_ROLES, true))
                    ->dehydrated(fn (?Role $record): bool => $record === null || ! in_array($record->name, Screens::SYSTEM_ROLES, true))
                    ->extraInputAttributes(['dir' => 'ltr']),
                Text::make(__('admin_shell.roles.matrix_hint')),
            ]),
            ...$sections,
        ]);
    }

    /** @return array<string, list<string>> screen => abilities granted */
    public static function matrixOf(Role $role): array
    {
        $out = [];
        foreach ($role->permissions->pluck('name') as $name) {
            [$screen, $ability] = array_pad(explode('.', (string) $name, 2), 2, '');
            $out[$screen][] = $ability;
        }

        return $out;
    }

    /** @param  array<string, mixed>  $matrix @return list<string> */
    public static function permissionsFrom(array $matrix): array
    {
        $out = [];
        foreach ($matrix as $screen => $abilities) {
            foreach ((array) $abilities as $ability) {
                if (Screens::exists((string) $screen, (string) $ability)) {
                    $out[] = Screens::permission((string) $screen, (string) $ability);
                }
            }
        }

        return $out;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['users', 'permissions']))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('name')->label(__('admin_shell.roles.label'))->weight('medium')
                    ->formatStateUsing(fn (string $state): string => RoleLabel::of($state))
                    ->description(fn (Role $r): string => (string) __(in_array($r->name, Screens::SYSTEM_ROLES, true) ? 'admin_shell.roles.system' : 'admin_shell.roles.custom')),
                TextColumn::make('users_count')->label(__('admin_shell.roles.users'))->numeric(),
                TextColumn::make('permissions_count')->label(__('admin_shell.roles.permissions'))
                    ->formatStateUsing(fn (Role $r, $state): string => $r->name === Screens::SUPER_ADMIN ? (string) count(Screens::permissions()) : (string) $state),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (Role $record): bool => self::canDelete($record))->authorize(fn (Role $record): bool => self::canDelete($record)),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
