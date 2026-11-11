<?php

namespace App\Filament\Resources\FaqGroups;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Editing\Registry;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\FaqGroups\Pages\CreateFaqGroup;
use App\Filament\Resources\FaqGroups\Pages\EditFaqGroup;
use App\Filament\Resources\FaqGroups\Pages\ListFaqGroups;
use App\Filament\Resources\PracticeAreas\PracticeAreaResource;
use App\Models\Content\Faq;
use App\Models\Content\FaqCategory;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

/**
 * FAQ groups (docs/briefs/design-2.1-and-admin-cms.md §8.2 "FAQs + groups"; the faq_categories table): the group name,
 * its anchor in the FAQ centre (#cat-01), the order and the links under the group (each language's own list of
 * {label, target}). New groups are cat-13, cat-14 …
 */
class FaqGroupResource extends CmsResource
{
    protected static ?string $model = FaqCategory::class;

    protected static ?string $slug = 'faq-groups';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?int $navigationSort = 51;

    protected static function labelKey(): string
    {
        return 'cms_collections.faq_groups';
    }

    public static function getNavigationParentItem(): ?string
    {
        return (string) __('cms_collections.faqs.plural');
    }

    public static function form(Schema $schema): Schema
    {
        $limits = Limits::for('faq_categories');
        $record = $schema->getRecord();
        $t = fn (string $key) => (string) __('cms_collections.faq_groups.'.$key);

        return CmsForm::layout($schema, [
            Section::make()->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('code')->label((string) __('cms.field.code'))->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr'])
                        ->visible($record !== null),
                    CmsForm::anchor()->required()->default(fn () => self::newRecordAttributes([])['code'])
                        ->rule(fn () => Rule::unique('faq_categories', 'anchor')->ignore($record?->getKey(), 'code')),
                    CmsForm::position()->required()->default(fn () => self::nextPosition()),
                ]),
                Translatable::text('name', $t('name'), $limits['name'] ?? null, required: true),
            ]),
            Section::make()->schema([
                Text::make($t('links_hint'))->color('gray')->size('sm'),
                CmsForm::structured(FaqCategory::class, 'links', $t('links'), $limits),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $counts = null;
        $count = function (string $code) use (&$counts): int {
            $counts ??= Faq::query()->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category')->all();

            return (int) ($counts[$code] ?? 0);
        };

        return CmsTable::configure($table, [
            TextColumn::make('code')->label((string) __('cms.field.code'))->sortable()->searchable()->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('name')->label((string) __('cms_collections.faq_groups.name'))
                ->state(fn (FaqCategory $record) => Registry::label($record))
                ->description(fn (FaqCategory $record) => PracticeAreaResource::ltr($record->anchor && $record->anchor !== $record->code ? '#'.$record->anchor : null))
                ->wrap()
                ->extraCellAttributes(['class' => 'cms-col-title'])->extraHeaderAttributes(['class' => 'cms-col-title'])
                ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('anchor', 'like', "%{$search}%"))),
            TextColumn::make('cms_faqs')->label((string) __('cms_collections.faq_groups.faqs'))
                ->state(fn (FaqCategory $record) => $count((string) $record->code))
                ->numeric()
                ->toggleable(),
        ], exportName: 'faq-groups', reorderable: true);
    }

    /** A new record goes to the end of the list. */
    private static function nextPosition(): int
    {
        return (int) FaqCategory::withTrashed()->max('position') + 1;
    }

    /** The next free key (cat-13, cat-14 …) for a new FAQ group. */
    public static function newRecordAttributes(array $data): array
    {
        $max = 0;
        foreach (FaqCategory::withTrashed()->pluck('code') as $code) {
            if (preg_match('/^cat-(\d+)$/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return ['code' => sprintf('cat-%02d', $max + 1)];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqGroups::route('/'),
            'create' => CreateFaqGroup::route('/create'),
            'edit' => EditFaqGroup::route('/{record}/edit'),
        ];
    }
}
