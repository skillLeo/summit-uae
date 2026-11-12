<?php

namespace App\Filament\Resources\GuideCategories;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Editing\Registry;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\GuideCategories\Pages\CreateGuideCategory;
use App\Filament\Resources\GuideCategories\Pages\EditGuideCategory;
use App\Filament\Resources\GuideCategories\Pages\ListGuideCategories;
use App\Models\Content\Guide;
use App\Models\Content\GuideCategory;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Guide categories (brief §8.2 "Legal Guides + categories"): name and the guides of the category, in order. */
class GuideCategoryResource extends CmsResource
{
    protected static string $screen = 'guide_categories';

    protected static ?string $model = GuideCategory::class;

    protected static ?string $slug = 'guide-categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?int $navigationSort = 31;


    protected static function labelKey(): string
    {
        return 'cms_pages.categories';
    }

    public static function getNavigationParentItem(): ?string
    {
        return (string) __('cms_pages.guides.plural');
    }

    public static function form(Schema $schema): Schema
    {
        $limits = Limits::for('guide_categories');

        return CmsForm::layout($schema, [
            Section::make()->schema([
                TextInput::make('code')->label((string) __('cms.field.code'))->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr'])
                    ->visible(fn ($record) => $record !== null),
                Translatable::text('name', (string) __('cms_pages.categories.name'), $limits['name'] ?? null, required: true),
                Select::make('guides')->label((string) __('cms_pages.categories.guides'))->multiple()
                    ->options(fn () => Guide::query()->orderBy('position')->get()->mapWithKeys(fn ($g) => [$g->code => $g->code.': '.Registry::label($g)])->all()),
                CmsForm::position(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return CmsTable::configure($table, [
            TextColumn::make('code')->label((string) __('cms.field.code'))->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('name')->label((string) __('cms_pages.categories.name'))->state(fn (GuideCategory $record) => Registry::label($record)),
            TextColumn::make('guides')->label((string) __('cms_pages.categories.guides'))
                ->state(fn (GuideCategory $record) => implode(', ', (array) $record->guides))->extraAttributes(['dir' => 'ltr']),
        ], exportName: 'guide-categories', reorderable: true);
    }

    public static function newRecordAttributes(array $data): array
    {
        $max = 0;
        foreach (GuideCategory::withTrashed()->pluck('code') as $code) {
            if (preg_match('/^gc-(\d+)$/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return ['code' => sprintf('gc-%02d', $max + 1)];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuideCategories::route('/'),
            'create' => CreateGuideCategory::route('/create'),
            'edit' => EditGuideCategory::route('/{record}/edit'),
        ];
    }
}
