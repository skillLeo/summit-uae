<?php

namespace App\Filament\Resources\Faqs;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Editing\Registry;
use App\Content\Cms\Tree;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Resources\Faqs\Pages\EditFaq;
use App\Filament\Resources\Faqs\Pages\ListFaqs;
use App\Filament\Resources\PracticeAreas\PracticeAreaResource;
use App\Models\Content\Faq;
use App\Models\Content\FaqCategory;
use App\Models\Content\Page;
use App\Models\Content\Service;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * FAQs (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.11): the question, the answer (each language's own list of
 * paragraphs, {type: p, text}), the FAQ group, the anchor (#faq-12), the order and where else the FAQ is shown
 * (placements: page or record codes; stored as they are, null when none). New FAQs are faq-75, faq-76 …; their copy is
 * pending client approval.
 */
class FaqResource extends CmsResource
{
    protected static ?string $model = Faq::class;

    protected static ?string $slug = 'faqs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?int $navigationSort = 50;

    protected static function labelKey(): string
    {
        return 'cms_collections.faqs';
    }

    public static function form(Schema $schema): Schema
    {
        $limits = Limits::for('faqs');
        $record = $schema->getRecord();
        $t = fn (string $key) => (string) __('cms_collections.faqs.'.$key);

        return CmsForm::layout($schema, [
            Section::make($t('section_faq'))->schema([
                Translatable::text('question', $t('question'), $limits['question'] ?? null, required: true),
                CmsForm::structured(Faq::class, 'answer', $t('answer'), $limits),
            ])->description($t('answer_hint')),
            Section::make($t('section_placement'))->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('code')->label((string) __('cms.field.code'))->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr'])
                        ->visible($record !== null),
                    CmsForm::anchor()->placeholder(fn (?Model $record) => $record?->getKey())
                        ->rule(fn () => Rule::unique('faqs', 'anchor')->ignore($record?->getKey(), 'code')),
                    CmsForm::position()->required()->default(fn () => self::nextPosition()),
                ]),
                Select::make('category')->label($t('group'))->required()->options(fn () => self::groupOptions()),
                TagsInput::make('placements')->label($t('placements'))->helperText($t('placements_hint'))
                    ->suggestions(fn () => self::placementSuggestions())
                    ->extraInputAttributes(['dir' => 'ltr']),
            ]),
        ]);
    }

    /** @return array<string, string> FAQ groups (cat-01: name …), in order */
    public static function groupOptions(): array
    {
        return FaqCategory::query()->orderBy('position')->get()->mapWithKeys(fn (FaqCategory $c) => [$c->code => Registry::label($c)])->all();
    }

    /** @return list<string> page and practice area codes */
    private static function placementSuggestions(): array
    {
        return [...Page::query()->orderBy('code')->pluck('code')->all(), ...Service::query()->orderBy('code')->pluck('code')->all()];
    }

    /**
     * An empty anchor is the FAQ's key (faq-75), the id the FAQ centre gives it anyway. Placements: no codes is stored as it was (null), never as an empty list. A new answer paragraph gets the type of
     * the approved ones ("p"); the type of an existing item is kept as it is.
     */
    public static function fromFormState(Model $record, array $data): array
    {
        if (array_key_exists('anchor', $data) && ! filled($data['anchor'])) {
            $data['anchor'] = (string) $record->getKey(); // the FAQ centre's id of an FAQ without its own anchor
        }
        if (array_key_exists('placements', $data)) {
            $codes = array_values(array_filter((array) $data['placements'], fn ($c) => is_string($c) && trim($c) !== ''));
            $data['placements'] = $codes === [] && CmsForm::working($record, 'placements') !== [] ? null : $codes;
        }
        if (is_array($data['answer'] ?? null)) {
            foreach (Tree::LOCALES as $locale) {
                if (! is_array($data['answer'][$locale] ?? null)) {
                    continue;
                }
                foreach ($data['answer'][$locale] as $i => $item) {
                    if (is_array($item) && array_key_exists('type', $item) && ($item['type'] === null || $item['type'] === '')) {
                        $data['answer'][$locale][$i]['type'] = 'p';
                    }
                }
            }
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        $memo = null;
        $groups = function () use (&$memo): array {
            return $memo ??= self::groupOptions();
        };

        return CmsTable::configure($table, [
            TextColumn::make('code')->label((string) __('cms.field.code'))->sortable()->searchable()->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('question')->label((string) __('cms_collections.faqs.question'))
                ->state(fn (Faq $record) => Registry::label($record))
                ->description(fn (Faq $record) => PracticeAreaResource::ltr($record->anchor && $record->anchor !== $record->code ? '#'.$record->anchor : null))
                ->wrap()
                ->extraCellAttributes(['class' => 'cms-col-title'])->extraHeaderAttributes(['class' => 'cms-col-title'])
                ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q->where('question', 'like', "%{$search}%")->orWhere('answer', 'like', "%{$search}%")->orWhere('anchor', 'like', "%{$search}%"))),
            TextColumn::make('category')->label((string) __('cms_collections.faqs.group'))
                ->formatStateUsing(fn (?string $state) => $state !== null ? ($groups()[$state] ?? $state) : null)
                ->wrap()
                ->toggleable(),
        ], [
            SelectFilter::make('category')->label((string) __('cms_collections.faqs.group'))->options($groups),
        ], exportName: 'faqs', reorderable: true);
    }

    /** A new record goes to the end of the list. */
    private static function nextPosition(): int
    {
        return (int) Faq::withTrashed()->max('position') + 1;
    }

    /** The next free key (faq-75, faq-76 …) for a new FAQ. */
    public static function newRecordAttributes(array $data): array
    {
        $max = 0;
        foreach (Faq::withTrashed()->pluck('code') as $code) {
            if (preg_match('/^faq-(\d+)$/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return ['code' => sprintf('faq-%02d', $max + 1)];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqs::route('/'),
            'create' => CreateFaq::route('/create'),
            'edit' => EditFaq::route('/{record}/edit'),
        ];
    }
}
