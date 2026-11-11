<?php

namespace App\Filament\Resources\Navigation;

use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Tree;
use App\Filament\Cms\Actions\CmsActions;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\Navigation\Pages\EditNavigationMenu;
use App\Filament\Resources\Navigation\Pages\ListNavigationMenus;
use App\Models\Content\NavigationItem;
use App\Models\Content\NavigationMenu;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Navigation (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.11): the nine menus the Design 2 shell reads through
 * App\Content\ContentRepository::menu() (header row, header call to action, mega menu, the footer groups, the footer
 * contact channels, the mobile bar). A menu is edited as the list of its items; saving keeps the list as the menu's
 * draft and publishing makes it the menu's navigation_items rows (Publisher::syncMenuItems()).
 *
 * The client's site-map key of an item (H-L001 …, and TEL / WA01 / EMAIL as options.channel) is read-only: the form
 * shows it, but the saved list always takes the key, the site-map label and the presentation options from the stored
 * item with the same id (fromFormState()), so no key can be changed, forged or moved to another item. New items have
 * no key. The menus themselves are not created or deleted here: the views know their keys.
 */
class NavigationResource extends CmsResource
{
    protected static ?string $model = NavigationMenu::class;

    protected static ?string $slug = 'navigation';

    protected static string|UnitEnum|null $navigationGroup = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?int $navigationSort = 10;

    /** Item types whose label may stay empty: the label then comes from the target (record title, phone, e-mail). */
    public const OWN_LABEL = ['record', 'tel', 'email'];

    /** Item types without a target: the address comes from the contact settings. */
    public const NO_TARGET = ['tel', 'email', 'whatsapp'];

    protected static function labelKey(): string
    {
        return 'cms_site.navigation';
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof NavigationMenu ? (string) $record->name : static::getModelLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return CmsForm::layout($schema, [
            Section::make((string) __('cms_site.navigation.section_menu'))
                ->schema([
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        TextInput::make('menu_key')->label((string) __('cms_site.navigation.key'))
                            ->disabled()->dehydrated(false)
                            ->formatStateUsing(fn (?Model $record) => $record?->getAttribute('key'))
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('name')->label((string) __('cms_site.navigation.name'))->required()->maxLength(190)
                            ->helperText((string) __('cms_site.navigation.name_hint'))->columnSpan(['default' => 1, 'md' => 2]),
                    ]),
                    Textarea::make('description')->label((string) __('cms_site.navigation.description'))->rows(2)->maxLength(190),
                ]),
            Section::make((string) __('cms_site.navigation.section_items'))
                ->description((string) __('cms_site.navigation.items_hint'))
                ->schema([self::itemsRepeater()]),
        ]);
    }

    public static function itemsRepeater(): Repeater
    {
        return Repeater::make('items')
            ->hiddenLabel()
            ->reorderable()
            ->reorderableWithDragAndDrop()
            ->reorderableWithButtons()
            ->collapsible()
            ->collapsed()
            ->cloneable(false)
            ->defaultItems(0)
            ->addActionLabel((string) __('cms_site.navigation.add_item'))
            ->deleteAction(fn (Action $action) => $action->requiresConfirmation()
                ->modalHeading((string) __('cms_site.navigation.delete_item'))
                ->modalDescription((string) __('cms_site.navigation.delete_item_body')))
            ->itemLabel(fn (array $state): string => self::itemLabel($state))
            ->extraAttributes(['class' => 'cms-nav-items'])
            ->schema([
                Hidden::make('id'),
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('link_key')
                        ->label((string) __('cms_site.navigation.link_key'))
                        ->helperText((string) __('cms_site.navigation.link_key_hint'))
                        ->placeholder((string) __('cms_site.navigation.no_key'))
                        ->disabled()
                        ->dehydrated(false)
                        ->extraInputAttributes(['dir' => 'ltr']),
                    Select::make('target_type')
                        ->label((string) __('cms_site.navigation.target_type'))
                        ->options(self::typeOptions())
                        ->default('page')
                        ->required()
                        ->in(NavigationItem::TARGET_TYPES)
                        ->live()
                        ->selectablePlaceholder(false),
                    TextInput::make('target')
                        ->label((string) __('cms_site.navigation.target'))
                        ->helperText(fn (Get $get) => (string) __('cms_site.navigation.target_hint.'.self::type($get('target_type'))))
                        ->visible(fn (Get $get) => ! in_array(self::type($get('target_type')), self::NO_TARGET, true))
                        ->required(fn (Get $get) => ! in_array(self::type($get('target_type')), self::NO_TARGET, true))
                        ->maxLength(255)
                        ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $error = NavigationTargets::error(self::type($get('target_type')), is_string($value) ? trim($value) : null);
                            if ($error !== null) {
                                $fail((string) __('cms_site.navigation.errors.'.$error));
                            }
                        })
                        ->extraInputAttributes(['dir' => 'ltr']),
                ]),
                Text::make(fn (Get $get) => self::registryLine($get('id')))
                    ->color('gray')
                    ->size('sm')
                    ->visible(fn (Get $get) => self::registryLine($get('id')) !== null),
                Translatable::make('label', fn (string $locale) => TextInput::make($locale)
                    ->maxLength(120)
                    ->required(fn (Get $get) => $locale === 'en' && ! in_array(self::type($get('../target_type')), self::OWN_LABEL, true))
                    ->helperText(fn (Get $get) => in_array(self::type($get('../target_type')), self::OWN_LABEL, true)
                        ? (string) __('cms_site.navigation.label_hint_'.self::type($get('../target_type')))
                        : null),
                    (string) __('cms_site.navigation.label'), 120),
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    Toggle::make('enabled')->label((string) __('cms.field.enabled'))->default(true)
                        ->helperText((string) __('cms_site.navigation.enabled_hint')),
                    Toggle::make('new_tab')->label((string) __('cms_site.navigation.new_tab'))->default(false),
                ]),
            ]);
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        $out = [];
        foreach (NavigationItem::TARGET_TYPES as $type) {
            $out[$type] = (string) __('cms_site.navigation.types.'.$type);
        }

        return $out;
    }

    private static function type(mixed $type): string
    {
        return is_string($type) && in_array($type, NavigationItem::TARGET_TYPES, true) ? $type : 'page';
    }

    /** The repeater's title of an item: its key, then its label (or the target) in the admin's language. */
    public static function itemLabel(array $state): string
    {
        $locale = in_array(app()->getLocale(), Tree::LOCALES, true) ? app()->getLocale() : 'en';
        $label = is_array($state['label'] ?? null) ? ($state['label'][$locale] ?? null ?: ($state['label']['en'] ?? null)) : null;
        $type = self::type($state['target_type'] ?? null);
        $label = is_string($label) && $label !== '' ? $label : match ($type) {
            'tel', 'email', 'whatsapp' => (string) __('cms_site.navigation.types.'.$type),
            'record' => NavigationTargets::recordLabel((string) ($state['target'] ?? '')),
            default => (string) ($state['target'] ?? ''),
        };
        $key = is_string($state['link_key'] ?? null) && $state['link_key'] !== '' ? $state['link_key'] : self::storedKey($state['id'] ?? null);
        $key ??= is_numeric($state['id'] ?? null) ? null : (string) __('cms_site.navigation.new_item');
        $off = ($state['enabled'] ?? true) ? '' : ' ('.(string) __('cms_site.navigation.off').')';

        return ($key !== null ? $key.': ' : '').$label.$off;
    }

    /** The site-map key of a stored item (the repeater's item label gets the state without the read-only key field). */
    public static function storedKey(mixed $id): ?string
    {
        if (! is_numeric($id)) {
            return null;
        }
        static $keys = null;
        $keys ??= NavigationItem::query()->whereNotNull('link_key')->pluck('link_key', 'id')->all();

        return $keys[(int) $id] ?? null;
    }

    /** "Site map: Home" for an item with a site-map label (traceability), null otherwise. */
    public static function registryLine(mixed $id): ?string
    {
        if (! is_numeric($id)) {
            return null;
        }
        static $labels = null;
        $labels ??= NavigationItem::query()->get(['id', 'registry_label'])->mapWithKeys(fn (NavigationItem $item) => [
            $item->getKey() => Publisher::decode($item->getAttributes()['registry_label'] ?? null),
        ])->all();
        $label = $labels[(int) $id] ?? null;
        if (! is_array($label) || $label === []) {
            return null;
        }
        $locale = app()->getLocale();
        $text = $label[$locale] ?? $label['en'] ?? reset($label);

        return is_string($text) && $text !== '' ? (string) __('cms_site.navigation.registry_label', ['label' => $text]) : null;
    }

    /**
     * The working copy as the form shows it: the items as a list with every language present.
     *
     * @param  array<string, mixed>  $working
     * @return array<string, mixed>
     */
    public static function toFormState(Model $record, array $working): array
    {
        $working['items'] = array_map(fn (array $item) => [
            'id' => $item['id'],
            'link_key' => $item['link_key'],
            'target_type' => $item['target_type'],
            'target' => $item['target'],
            'label' => array_merge(array_fill_keys(Tree::LOCALES, null), (array) ($item['label'] ?? [])),
            'new_tab' => (bool) $item['new_tab'],
            'enabled' => (bool) $item['enabled'],
        ], array_values((array) ($working['items'] ?? [])));

        return $working;
    }

    /**
     * The form's items as the menu's draft. The key, the site-map label and the options of an item always come from the
     * stored item with that id (the working copy: the draft, else the published rows); an id the menu does not have is
     * a new item (no key). Items without a target (tel, e-mail, WhatsApp) store none.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromFormState(Model $record, array $data): array
    {
        if (! array_key_exists('items', $data)) {
            return $data;
        }
        $known = [];
        foreach ((array) (Publisher::workingCopy($record)['items'] ?? []) as $item) {
            if (isset($item['id'])) {
                $known[(int) $item['id']] = $item;
            }
        }
        $items = [];
        foreach (array_values((array) $data['items']) as $item) {
            $item = (array) $item;
            $id = isset($item['id']) && is_numeric($item['id']) && isset($known[(int) $item['id']]) ? (int) $item['id'] : null;
            $stored = $id !== null ? $known[$id] : [];
            $type = self::type($item['target_type'] ?? null);
            $target = is_string($item['target'] ?? null) ? trim($item['target']) : null;
            $items[] = [
                'id' => $id,
                'link_key' => $stored['link_key'] ?? null,
                'label' => $item['label'] ?? null,
                'registry_label' => $stored['registry_label'] ?? null,
                'target_type' => $type,
                'target' => in_array($type, self::NO_TARGET, true) || $target === '' ? null : $target,
                'new_tab' => (bool) ($item['new_tab'] ?? false),
                'enabled' => (bool) ($item['enabled'] ?? true),
                'options' => $stored['options'] ?? null,
            ];
        }
        $data['items'] = $items;

        return $data;
    }

    public static function table(Table $table): Table
    {
        return CmsTable::configure($table, [
            TextColumn::make('name')->label((string) __('cms_site.navigation.name'))
                ->description(fn (NavigationMenu $record) => $record->description)
                ->wrap()
                ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"))),
            TextColumn::make('key')->label((string) __('cms_site.navigation.key'))->extraAttributes(['dir' => 'ltr'])->toggleable(),
            TextColumn::make('cms_items')->label((string) __('cms_site.navigation.items'))
                ->state(fn (NavigationMenu $record) => count((array) (Publisher::workingCopy($record)['items'] ?? [])))
                ->numeric(),
        ], exportName: 'navigation')
            ->defaultSort('id')
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    CmsActions::bulkPublish(),
                    CmsActions::bulkExport('navigation', fn (Model $record) => CmsTable::exportRow($record)),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNavigationMenus::route('/'),
            'edit' => EditNavigationMenu::route('/{record}/edit'),
        ];
    }
}
