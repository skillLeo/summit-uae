<?php

namespace App\Filament\Resources\Guides;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Editing\Registry;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\BlockBuilder;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\Guides\Pages\CreateGuide;
use App\Filament\Resources\Guides\Pages\EditGuide;
use App\Filament\Resources\Guides\Pages\ListGuides;
use App\Models\Content\Guide;
use App\Models\Content\GuideCategory;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

/**
 * Legal Guides (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.11): the homepage card fields, the Legal Resources card
 * fields, the full guide body (its sections, as blocks), the stable anchor used by the knowledge hub (unpaid-salary …),
 * the category and the order. New guides are G10, G11 …; their copy is pending client approval.
 *
 * This resource is the reference for the other collections: CmsResource + CmsForm::layout() + Translatable fields with
 * the importer's limits (Limits::for()) + CmsTable::configure() + EditsCmsRecord / CreatesCmsRecord.
 */
class GuideResource extends CmsResource
{
    protected static string $screen = 'guides';

    protected static ?string $model = Guide::class;

    protected static ?string $slug = 'guides';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 30;

    protected static function labelKey(): string
    {
        return 'cms_pages.guides';
    }

    public static function form(Schema $schema): Schema
    {
        $limits = Limits::for('guides');
        $record = $schema->getRecord();

        return CmsForm::layout($schema, [
            Section::make((string) __('cms_pages.guides.section_guide'))->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('code')->label((string) __('cms.field.code'))->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr'])
                        ->visible($record !== null),
                    CmsForm::anchor()->required()
                        ->rule(fn () => Rule::unique('guides', 'anchor')->ignore($record?->getKey(), 'code')),
                    Select::make('category')->label((string) __('cms_pages.guides.category'))
                        ->options(fn () => GuideCategory::query()->orderBy('position')->get()->mapWithKeys(fn ($c) => [$c->code => Registry::label($c)])->all()),
                    CmsForm::position(),
                ]),
                Translatable::text('title', (string) __('cms_pages.guides.title'), $limits['title'] ?? null, required: true),
                Toggle::make('standalone')->label((string) __('cms_pages.guides.standalone'))->helperText((string) __('cms_pages.guides.standalone_hint')),
            ]),
            Section::make((string) __('cms_pages.guides.section_cards'))->schema([
                Translatable::text('card_title', (string) __('cms_pages.guides.card_title'), $limits['card_title'] ?? null),
                Translatable::textarea('card_summary', (string) __('cms_pages.guides.card_summary'), $limits['card_summary'] ?? null),
                Translatable::text('home_title', (string) __('cms_pages.guides.home_title'), $limits['home_title'] ?? null),
                Translatable::textarea('home_summary', (string) __('cms_pages.guides.home_summary'), $limits['home_summary'] ?? null),
            ]),
            CmsForm::seo($limits),
            Section::make((string) __('cms_pages.guides.body'))->schema([
                BlockBuilder::make($record, templates: self::blockTemplates())->hiddenLabel(),
            ]),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public static function blockTemplates(): array
    {
        static $templates = null;

        return $templates ??= BlockBuilder::templates(Guide::class);
    }

    public static function table(Table $table): Table
    {
        return CmsTable::configure($table, [
            TextColumn::make('code')->label((string) __('cms.field.code'))->sortable()->searchable()->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('card_title')->label((string) __('cms_pages.guides.card_title'))
                ->state(fn (Guide $record) => Registry::label($record))
                ->description(fn (Guide $record) => $record->anchor ? '#'.$record->anchor : null)
                ->wrap()
                ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('card_title', 'like', "%{$search}%")->orWhere('anchor', 'like', "%{$search}%"))),
            TextColumn::make('category')->label((string) __('cms_pages.guides.category'))
                ->formatStateUsing(fn (?string $state) => $state ? Registry::label(GuideCategory::query()->find($state) ?? new GuideCategory(['code' => $state])) : null)
                ->toggleable(),
        ], [
            SelectFilter::make('category')->label((string) __('cms_pages.guides.category'))
                ->options(fn () => GuideCategory::query()->orderBy('position')->get()->mapWithKeys(fn ($c) => [$c->code => Registry::label($c)])->all()),
        ], exportName: 'guides', reorderable: true);
    }

    /** The next free key (G10, G11 …) for a new guide. */
    public static function newRecordAttributes(array $data): array
    {
        $max = 0;
        foreach (Guide::withTrashed()->pluck('code') as $code) {
            if (preg_match('/^G(\d+)$/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return ['code' => sprintf('G%02d', $max + 1)];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuides::route('/'),
            'create' => CreateGuide::route('/create'),
            'edit' => EditGuide::route('/{record}/edit'),
        ];
    }
}
