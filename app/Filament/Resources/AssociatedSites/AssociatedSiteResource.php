<?php

namespace App\Filament\Resources\AssociatedSites;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Editing\Registry;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\AssociatedSites\Pages\CreateAssociatedSite;
use App\Filament\Resources\AssociatedSites\Pages\EditAssociatedSite;
use App\Filament\Resources\AssociatedSites\Pages\ListAssociatedSites;
use App\Filament\Resources\PracticeAreas\PracticeAreaResource;
use App\Models\Content\SpecialistSite;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Associated Sites (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.11; the specialist_sites table): the specialist
 * resources linked from the practice area pages: name (per language), domain, https address, the linked practice
 * area, the order and whether it is shown. New sites are X07, X08 …
 */
class AssociatedSiteResource extends CmsResource
{
    protected static string $screen = 'associated_sites';

    protected static ?string $model = SpecialistSite::class;

    protected static ?string $slug = 'associated-sites';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 90;

    protected static function labelKey(): string
    {
        return 'cms_collections.associated_sites';
    }

    public static function form(Schema $schema): Schema
    {
        $limits = Limits::for('specialist_sites');
        $record = $schema->getRecord();
        $t = fn (string $key) => (string) __('cms_collections.associated_sites.'.$key);

        return CmsForm::layout($schema, [
            Section::make($t('section_site'))->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('code')->label((string) __('cms.field.code'))->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr'])
                        ->visible($record !== null),
                    CmsForm::position()->required()->default(fn () => self::nextPosition()),
                    Toggle::make('enabled')->label($t('enabled'))->default(true)->inline(false),
                ]),
                Translatable::text('label', $t('label'), $limits['label'] ?? null, required: true),
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('domain')->label($t('domain'))->helperText($t('domain_hint'))->required()->maxLength(190)
                        ->regex('/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/')
                        ->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('url')->label($t('url'))->helperText($t('url_hint'))->required()->maxLength(255)
                        ->url()->startsWith(['https://'])
                        ->extraInputAttributes(['dir' => 'ltr']),
                ]),
                Select::make('practice_area')->label($t('practice_area'))->options(fn () => PracticeAreaResource::options()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $t = fn (string $key) => (string) __('cms_collections.associated_sites.'.$key);
        $areas = null;
        $area = function (?string $code) use (&$areas): ?string {
            $areas ??= PracticeAreaResource::options();

            return $code === null ? null : ($areas[$code] ?? $code);
        };

        return CmsTable::configure($table, [
            TextColumn::make('code')->label((string) __('cms.field.code'))->sortable()->searchable()->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('label')->label($t('label'))
                ->state(fn (SpecialistSite $record) => Registry::label($record))
                ->description(fn (SpecialistSite $record) => PracticeAreaResource::ltr($record->domain))
                ->wrap()
                ->extraCellAttributes(['class' => 'cms-col-title'])->extraHeaderAttributes(['class' => 'cms-col-title'])
                ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q->where('label', 'like', "%{$search}%")->orWhere('domain', 'like', "%{$search}%")->orWhere('url', 'like', "%{$search}%"))),
            TextColumn::make('practice_area')->label($t('practice_area'))->formatStateUsing(fn (?string $state) => $area($state))->wrap()->toggleable(),
            TextColumn::make('enabled')->label($t('enabled'))
                ->formatStateUsing(fn ($state) => $state ? $t('yes') : $t('no'))
                ->badge()
                ->color(fn ($state) => $state ? 'success' : 'gray')
                ->icon(fn ($state) => $state ? 'heroicon-m-eye' : 'heroicon-m-eye-slash')
                ->toggleable(),
        ], [
            TernaryFilter::make('enabled')->label($t('enabled')),
        ], exportName: 'associated-sites', reorderable: true);
    }

    /** A new record goes to the end of the list. */
    private static function nextPosition(): int
    {
        return (int) SpecialistSite::withTrashed()->max('position') + 1;
    }

    /** The next free key (X07, X08 …) for a new associated site. */
    public static function newRecordAttributes(array $data): array
    {
        $max = 0;
        foreach (SpecialistSite::withTrashed()->pluck('code') as $code) {
            if (preg_match('/^X(\d+)$/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return ['code' => sprintf('X%02d', $max + 1)];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssociatedSites::route('/'),
            'create' => CreateAssociatedSite::route('/create'),
            'edit' => EditAssociatedSite::route('/{record}/edit'),
        ];
    }
}
