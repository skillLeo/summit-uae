<?php

namespace App\Filament\Resources\InterfaceText;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Tree;
use App\Filament\Cms\Actions\CmsActions;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\InterfaceText\Pages\EditInterfaceText;
use App\Filament\Resources\InterfaceText\Pages\ListInterfaceText;
use App\Models\Content\UiString;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Interface Text (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.10, §8.11 ui_strings): buttons, form labels and
 * messages, aria labels, mobile controls and tool labels, in English, Arabic and Russian. Group "ui" holds the approved
 * DOCX strings, "proposed" the team's texts (pending client approval), "switcher" the design switch.
 *
 * The views define the keys, so strings are never created or deleted here: an editor changes the text of a key, and the
 * site shows it after publishing. Each string has its own limit (115 % of its longest approved text, Limits::for(
 * 'ui_strings')['group.key']), and a translation keeps the placeholders (:count …) of its language. The list searches
 * the key and the three texts, filters by group, approval and missing translations, and exports or imports CSV
 * (InterfaceTextCsv).
 */
class InterfaceTextResource extends CmsResource
{
    protected static ?string $model = UiString::class;

    protected static ?string $slug = 'interface-text';

    protected static string|UnitEnum|null $navigationGroup = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?int $navigationSort = 30;

    protected static function labelKey(): string
    {
        return 'cms_site.ui';
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof UiString ? $record->group.'.'.$record->key : static::getModelLabel();
    }

    public static function form(Schema $schema): Schema
    {
        /** @var UiString|null $record */
        $record = $schema->getRecord();
        $name = $record !== null ? $record->group.'.'.$record->key : null;
        $limit = $name !== null ? (Limits::for('ui_strings')[$name] ?? null) : null;
        $live = $record !== null ? (array) (Publisher::live($record)['text'] ?? []) : [];

        return CmsForm::layout($schema, [
            Section::make((string) __('cms_site.ui.section'))
                ->schema([
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        TextInput::make('ui_group')->label((string) __('cms_site.ui.group'))
                            ->formatStateUsing(fn (?Model $record) => $record?->getAttribute('group'))
                            ->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('ui_key')->label((string) __('cms_site.ui.key'))
                            ->formatStateUsing(fn (?Model $record) => $record?->getAttribute('key'))
                            ->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr'])
                            ->columnSpan(['default' => 1, 'md' => 2]),
                    ]),
                    Translatable::make('text', fn (string $locale) => Textarea::make($locale)
                        ->rows(2)
                        ->autosize()
                        ->required($locale === 'en' && is_string($live['en'] ?? null))
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($live, $locale): void {
                            $before = is_string($live[$locale] ?? null) ? $live[$locale] : '';
                            $missing = array_diff(InterfaceTextCsv::placeholders($before), InterfaceTextCsv::placeholders((string) $value));
                            if (is_string($value) && trim($value) !== '' && $missing !== []) {
                                $fail((string) __('cms_site.ui.placeholders_missing', ['names' => ':'.implode(', :', $missing)]));
                            }
                        }),
                        (string) __('cms_site.ui.text'), $limit),
                    Text::make(fn () => self::placeholderHint($live))
                        ->color('gray')->size('sm')
                        ->visible(fn () => self::placeholderHint($live) !== null),
                    Textarea::make('note')->label((string) __('cms_site.ui.note'))
                        ->helperText((string) __('cms_site.ui.note_hint'))
                        ->rows(2)->autosize()->maxLength(2000),
                ]),
        ]);
    }

    /** "Keep these placeholders: :count, :name" when the string has any. */
    private static function placeholderHint(array $live): ?HtmlString
    {
        $names = [];
        foreach (Tree::LOCALES as $locale) {
            if (is_string($live[$locale] ?? null)) {
                $names = [...$names, ...InterfaceTextCsv::placeholders($live[$locale])];
            }
        }
        $names = array_values(array_unique($names));

        if ($names === []) {
            return null;
        }
        $codes = implode(', ', array_map(fn (string $n) => '<bdi dir="ltr">:'.e($n).'</bdi>', $names));

        return new HtmlString(str_replace('%names%', $codes, e((string) __('cms_site.ui.placeholders', ['names' => '%names%']))));
    }

    public static function table(Table $table): Table
    {
        return CmsTable::configure($table, [
            TextColumn::make('key')->label((string) __('cms_site.ui.key'))
                ->description(fn (UiString $record) => $record->group)
                ->extraAttributes(['dir' => 'ltr', 'class' => 'cms-ui-key'])
                ->sortable()
                ->searchable(query: fn (Builder $query, string $search) => self::search($query, $search)),
            TextColumn::make('cms_text')->label((string) __('cms_site.ui.text'))
                ->state(fn (UiString $record) => self::text($record))
                ->extraAttributes(fn () => ['dir' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr', 'class' => 'cms-ui-text'])
                ->limit(120)
                ->wrap(),
            TextColumn::make('note')->label((string) __('cms_site.ui.note'))->limit(60)->wrap()->toggleable(isToggledHiddenByDefault: true),
        ], [
            SelectFilter::make('group')->label((string) __('cms_site.ui.group'))
                ->options(fn () => UiString::query()->distinct()->orderBy('group')->pluck('group', 'group')->all()),
        ], exportName: 'interface-text')
            ->defaultSort(fn (Builder $query) => $query->orderBy('group')->orderBy('key'))
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    CmsActions::bulkPublish(),
                    CmsActions::bulkExport('interface-text', fn (Model $record) => array_combine(InterfaceTextCsv::COLUMNS, InterfaceTextCsv::row($record))),
                ]),
            ]);
    }

    /** Search the group, the key and the three texts (published and draft: JSON text, so LIKE finds them). */
    public static function search(Builder $query, string $search): Builder
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        return $query->where(fn (Builder $q) => $q->where('key', 'like', $like)
            ->orWhere('group', 'like', $like)
            ->orWhere('text', 'like', $like)
            ->orWhere('draft', 'like', $like)
            ->orWhereRaw("CONCAT(`group`, '.', `key`) like ?", [$like]));
    }

    /** The text in the admin's language (the working copy), else in English. */
    public static function text(UiString $record): ?string
    {
        $text = (array) (Publisher::workingCopy($record)['text'] ?? []);
        $locale = in_array(app()->getLocale(), Tree::LOCALES, true) ? app()->getLocale() : 'en';
        $value = $text[$locale] ?? $text['en'] ?? null;

        return is_string($value) ? $value : null;
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
            'index' => ListInterfaceText::route('/'),
            'edit' => EditInterfaceText::route('/{record}/edit'),
        ];
    }
}
