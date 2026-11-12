<?php

namespace App\Filament\Resources\CaseStudies;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Editing\Registry;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\BlockBuilder;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\CaseStudies\Pages\CreateCaseStudy;
use App\Filament\Resources\CaseStudies\Pages\EditCaseStudy;
use App\Filament\Resources\CaseStudies\Pages\ListCaseStudies;
use App\Filament\Resources\PracticeAreas\PracticeAreaResource;
use App\Models\Content\CaseStudy;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

/**
 * Case Studies (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.11): the case's title and matter line, the Case
 * Studies page card, the homepage preview (its own title, summary and link label), the stable anchor (#case-01), the
 * order, SEO and the case body as blocks. New case studies are case-07, case-08 …; their copy is pending client approval.
 */
class CaseStudyResource extends CmsResource
{
    protected static string $screen = 'case_studies';

    protected static ?string $model = CaseStudy::class;

    protected static ?string $slug = 'case-studies';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?int $navigationSort = 40;

    protected static function labelKey(): string
    {
        return 'cms_collections.case_studies';
    }

    public static function form(Schema $schema): Schema
    {
        $limits = Limits::for('case_studies');
        $record = $schema->getRecord();
        $t = fn (string $key) => (string) __('cms_collections.case_studies.'.$key);

        return CmsForm::layout($schema, [
            Section::make($t('section_case'))->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('code')->label((string) __('cms.field.code'))->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr'])
                        ->visible($record !== null),
                    CmsForm::anchor()->required()->default(fn () => self::newRecordAttributes([])['code'])
                        ->rule(fn () => Rule::unique('case_studies', 'anchor')->ignore($record?->getKey(), 'code')),
                    CmsForm::position()->required()->default(fn () => self::nextPosition()),
                ]),
                Translatable::text('title', $t('title'), $limits['title'] ?? null, required: true),
                Translatable::text('matter', $t('matter'), $limits['matter'] ?? null),
                TextInput::make('category')->label($t('category'))->helperText($t('category_hint'))->maxLength(120),
            ]),
            Section::make($t('section_cards'))->schema([
                Translatable::text('card_title', $t('card_title'), $limits['card_title'] ?? null),
                Translatable::textarea('card_summary', $t('card_summary'), $limits['card_summary'] ?? null),
                Translatable::text('home_title', $t('home_title'), $limits['home_title'] ?? null),
                Translatable::textarea('home_summary', $t('home_summary'), $limits['home_summary'] ?? null),
                Translatable::text('home_link_label', $t('home_link_label'), $limits['home_link_label'] ?? null),
            ]),
            CmsForm::seo($limits),
            Section::make($t('body'))->schema([
                BlockBuilder::make($record, templates: self::blockTemplates())->hiddenLabel(),
            ]),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public static function blockTemplates(): array
    {
        static $templates = null;

        return $templates ??= BlockBuilder::templates(CaseStudy::class);
    }

    public static function table(Table $table): Table
    {
        return CmsTable::configure($table, [
            TextColumn::make('code')->label((string) __('cms.field.code'))->sortable()->searchable()->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('card_title')->label((string) __('cms_collections.case_studies.title'))
                ->state(fn (CaseStudy $record) => Registry::label($record))
                ->description(fn (CaseStudy $record) => PracticeAreaResource::ltr($record->anchor ? '#'.$record->anchor : null))
                ->wrap()
                ->extraCellAttributes(['class' => 'cms-col-title'])->extraHeaderAttributes(['class' => 'cms-col-title'])
                ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('card_title', 'like', "%{$search}%")->orWhere('home_title', 'like', "%{$search}%")->orWhere('anchor', 'like', "%{$search}%"))),
            TextColumn::make('category')->label((string) __('cms_collections.case_studies.category'))->toggleable(isToggledHiddenByDefault: true),
        ], exportName: 'case-studies', reorderable: true);
    }

    /** A new record goes to the end of the list. */
    private static function nextPosition(): int
    {
        return (int) CaseStudy::withTrashed()->max('position') + 1;
    }

    /** The next free key (case-07, case-08 …) for a new case study. */
    public static function newRecordAttributes(array $data): array
    {
        $max = 0;
        foreach (CaseStudy::withTrashed()->pluck('code') as $code) {
            if (preg_match('/^case-(\d+)$/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return ['code' => sprintf('case-%02d', $max + 1)];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCaseStudies::route('/'),
            'create' => CreateCaseStudy::route('/create'),
            'edit' => EditCaseStudy::route('/{record}/edit'),
        ];
    }
}
