<?php

namespace App\Filament\Resources\Situations;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\Registry;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Forms\TreeFields;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\PracticeAreas\PracticeAreaResource;
use App\Filament\Resources\Situations\Pages\CreateSituation;
use App\Filament\Resources\Situations\Pages\EditSituation;
use App\Filament\Resources\Situations\Pages\ListSituations;
use App\Models\Content\Service;
use App\Models\Content\Situation;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Situations (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.11): the lines "This service may be relevant if:" of
 * each practice area, shown by "Start your matter" and "Where Do You Stand?". A situation may carry its own documents,
 * first steps and mistakes; empty (null) means the practice area's own lists are shown, which is the case for every
 * approved situation. "Featured" makes it a quick pick of Start your matter. The key is the practice area and the
 * situation's number in it (S05-2); a new one is the next number (S05-5).
 */
class SituationResource extends CmsResource
{
    protected static ?string $model = Situation::class;

    protected static ?string $slug = 'situations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 70;

    private const LISTS = ['documents', 'first_steps', 'mistakes'];

    protected static function labelKey(): string
    {
        return 'cms_collections.situations';
    }

    public static function form(Schema $schema): Schema
    {
        $limits = Limits::for('situations');
        $record = $schema->getRecord();
        $t = fn (string $key) => (string) __('cms_collections.situations.'.$key);

        return CmsForm::layout($schema, [
            Section::make($t('section_situation'))->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('code')->label((string) __('cms.field.code'))->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr'])
                        ->visible($record !== null),
                    CmsForm::position()->required($record !== null),
                ]),
                Select::make('practice_area')->label($t('practice_area'))->required()
                    ->options(fn () => PracticeAreaResource::options())
                    ->disabled($record !== null)->dehydrated($record === null),
                Translatable::textarea('title', $t('title'), $limits['title'] ?? null, rows: 2, required: true),
            ]),
            Section::make($t('section_lists'))->description($t('section_lists_hint'))->collapsible()
                ->collapsed(fn (?Model $record) => ! self::hasOwnLists($record))
                ->schema(array_map(fn (string $column) => self::listField($column, $t($column)), self::LISTS)),
            Section::make($t('section_featured'))->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    Toggle::make('featured')->label($t('featured'))->helperText($t('featured_hint'))->live(),
                    TextInput::make('featured_position')->label($t('featured_position'))->numeric()->integer()->minValue(1)->maxValue(255)
                        ->visible(fn (Get $get) => (bool) $get('featured')),
                ]),
            ]),
        ]);
    }

    /**
     * A situation's own list: the shape of the practice areas' lists (a list of texts per language), with their limits.
     * Empty means the practice area's list.
     */
    private static function listField(string $column, string $label): Group
    {
        $samples = CmsForm::samples(Service::class, $column);
        $limits = Limits::for('services');

        return Translatable::make($column, fn (string $locale) => TreeFields::perLocale($locale, $samples, $column, $limits)
            ->helperText((string) __('cms_collections.situations.inherit_hint')), $label);
    }

    private static function hasOwnLists(?Model $record): bool
    {
        if (! $record?->exists) {
            return false;
        }
        $working = Publisher::workingCopy($record);
        foreach (self::LISTS as $column) {
            if (! empty($working[$column])) {
                return true;
            }
        }

        return false;
    }

    /**
     * A new situation gets the next position of its practice area when none is given; featured_position is kept only
     * for a quick pick.
     */
    public static function fromFormState(Model $record, array $data): array
    {
        if (! $record->exists && ($data['position'] ?? null) === null && is_string($record->getAttribute('practice_area'))) {
            $data['position'] = (int) Situation::withTrashed()->where('practice_area', $record->getAttribute('practice_area'))->max('position') + 1;
        }
        if (array_key_exists('featured', $data) && ! $data['featured'] && ! array_key_exists('featured_position', $data)) {
            $data['featured_position'] = null;
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        $areas = null;
        $area = function (?string $code) use (&$areas): ?string {
            $areas ??= PracticeAreaResource::options();

            return $code === null ? null : ($areas[$code] ?? $code);
        };
        $t = fn (string $key) => (string) __('cms_collections.situations.'.$key);

        return CmsTable::configure($table, [
            TextColumn::make('code')->label((string) __('cms.field.code'))->sortable()->searchable()->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('title')->label($t('title'))
                ->state(fn (Situation $record) => Registry::label($record))
                ->wrap()
                ->extraCellAttributes(['class' => 'cms-col-title'])->extraHeaderAttributes(['class' => 'cms-col-title'])
                ->searchable(query: fn ($query, string $search) => $query->where('title', 'like', "%{$search}%")),
            TextColumn::make('practice_area')->label($t('practice_area'))
                ->formatStateUsing(fn (?string $state) => $area($state))
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('featured')->label($t('featured'))
                ->state(fn (Situation $record) => $record->featured ? $t('featured').' '.$record->featured_position : null)
                ->badge()->color('primary')->icon('heroicon-m-star')
                ->toggleable(),
            TextColumn::make('cms_lists')->label($t('own_lists'))
                ->state(fn (Situation $record) => self::hasOwnLists($record) ? $t('own_lists') : $t('inherits'))
                ->badge()
                ->color(fn (Situation $record) => self::hasOwnLists($record) ? 'info' : 'gray')
                ->toggleable(isToggledHiddenByDefault: true),
        ], [
            SelectFilter::make('practice_area')->label($t('practice_area'))->options(fn () => PracticeAreaResource::options()),
            TernaryFilter::make('featured')->label($t('featured')),
        ], exportName: 'situations')
            ->groups([
                TableGroup::make('practice_area')->label($t('practice_area'))
                    ->getTitleFromRecordUsing(fn (Situation $record) => (string) $area($record->practice_area))
                    ->orderQueryUsing(fn ($query, string $direction) => $query->orderBy('practice_area', $direction)->orderBy('position')),
            ])
            ->defaultGroup('practice_area')
            ->defaultSort('position');
    }

    /** The key of a new situation: its practice area and the next number in it (S05-5). */
    public static function newRecordAttributes(array $data): array
    {
        $area = (string) ($data['practice_area'] ?? '');
        $max = 0;
        foreach (Situation::withTrashed()->where('practice_area', $area)->pluck('code') as $code) {
            if (preg_match('/^'.preg_quote($area, '/').'-(\d+)$/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return ['code' => $area.'-'.($max + 1), 'practice_area' => $area, 'featured' => 0];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSituations::route('/'),
            'create' => CreateSituation::route('/create'),
            'edit' => EditSituation::route('/{record}/edit'),
        ];
    }
}
