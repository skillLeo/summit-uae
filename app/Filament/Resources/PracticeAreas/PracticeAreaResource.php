<?php

namespace App\Filament\Resources\PracticeAreas;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\Registry;
use App\Content\Cms\Editing\Slugs;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\BlockBuilder;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Support\CmsLabels;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\PracticeAreas\Pages\EditPracticeArea;
use App\Filament\Resources\PracticeAreas\Pages\ListPracticeAreas;
use App\Models\Content\Guide;
use App\Models\Content\Service;
use App\Models\Content\SpecialistSite;
use BackedEnum;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

/**
 * Practice Areas (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.11): the eleven practice areas S01–S11 of the site
 * map. The approved files give a different text for each place a practice area appears (the homepage card and mega
 * menu, the Practice Areas page card, the detail page), so each one is its own field and none is derived from another.
 * Then the detail page's lists, the links (associated site, related guides), the slug (a change leaves 301 redirects),
 * SEO and the page body as blocks. The eleven are the site map: none is created or deleted here.
 */
class PracticeAreaResource extends CmsResource
{
    protected static string $screen = 'practice_areas';

    protected static ?string $model = Service::class;

    protected static ?string $slug = 'practice-areas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 20;

    protected static function labelKey(): string
    {
        return 'cms_collections.practice_areas';
    }

    public static function form(Schema $schema): Schema
    {
        $limits = Limits::for('services');
        $record = $schema->getRecord();
        $t = fn (string $key) => (string) __('cms_collections.practice_areas.'.$key);

        return CmsForm::layout($schema, [
            Section::make($t('section_area'))->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('code')->label((string) __('cms.field.code'))->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr']),
                    CmsForm::position()->required(),
                    TextInput::make('matter_value')->label($t('matter_value'))->helperText($t('matter_value_hint'))
                        ->maxLength(32)->extraInputAttributes(['dir' => 'ltr']),
                ]),
                CmsForm::slug()->required()
                    ->rule(fn (?Model $record) => function (string $attribute, mixed $value, Closure $fail) use ($record, $t) {
                        if (! Slugs::valid($record ?? new Service, is_string($value) ? $value : null)) {
                            $fail($t('slug_invalid'));
                        }
                    })
                    ->rule(fn (?Model $record) => Rule::unique('services', 'slug')->ignore($record?->getKey(), 'code')),
                TextEntry::make('cms_registry_label')->label($t('registry_label'))->helperText($t('registry_label_hint'))
                    ->state(fn (?Model $record) => self::registryLines($record))
                    ->listWithLineBreaks()->html()
                    ->visible(fn (?Model $record) => self::registryLines($record) !== []),
            ]),
            Section::make($t('section_cards'))->description($t('section_cards_hint'))->schema([
                Translatable::text('title', $t('title'), $limits['title'] ?? null, required: true),
                Translatable::textarea('home_summary', $t('home_summary'), $limits['home_summary'] ?? null),
                Translatable::text('hub_title', $t('hub_title'), $limits['hub_title'] ?? null),
                Translatable::textarea('hub_summary', $t('hub_summary'), $limits['hub_summary'] ?? null),
                Translatable::text('hub_link_label', $t('hub_link_label'), $limits['hub_link_label'] ?? null),
            ]),
            Section::make($t('section_page'))->schema([
                Translatable::text('h1', $t('h1'), $limits['h1'] ?? null),
                Translatable::textarea('intro', $t('intro'), $limits['intro'] ?? null, rows: 4),
                CmsForm::structured(Service::class, 'breadcrumb', $t('breadcrumb'), $limits),
                Translatable::textarea('whatsapp_draft', $t('whatsapp_draft'), $limits['whatsapp_draft'] ?? null, rows: 2),
            ]),
            Section::make($t('section_lists'))->collapsible()->schema([
                CmsForm::structured(Service::class, 'documents', $t('documents'), $limits),
                Translatable::textarea('documents_note', $t('documents_note'), $limits['documents_note'] ?? null, rows: 2),
                CmsForm::structured(Service::class, 'first_steps', $t('first_steps'), $limits),
                CmsForm::structured(Service::class, 'mistakes', $t('mistakes'), $limits),
            ]),
            Section::make($t('section_links'))->schema([
                Select::make('specialist')->label($t('specialist'))->helperText($t('specialist_hint'))
                    ->options(fn () => self::siteOptions()),
                Repeater::make('related_guides')->label($t('related_guides'))
                    ->simple(Select::make('code')->options(fn () => self::guideOptions())->required()->distinct())
                    ->reorderable()->reorderableWithButtons()->defaultItems(0)
                    ->addActionLabel($t('add_guide')),
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

        return $templates ??= BlockBuilder::templates(Service::class);
    }

    /** The site map label in each language it has (traceability, read-only). @return list<string> */
    private static function registryLines(?Model $record): array
    {
        $value = $record?->exists ? (Publisher::workingCopy($record)['registry_label'] ?? null) : null;
        $out = [];
        foreach ((array) $value as $locale => $text) {
            if (is_string($text) && $text !== '') {
                $out[] = e(CmsLabels::locale((string) $locale)).': <bdi lang="'.e((string) $locale).'">'.e($text).'</bdi>';
            }
        }

        return $out;
    }

    /** A Latin path, anchor or domain shown in a table cell: isolated left to right in the Arabic admin too. */
    public static function ltr(?string $text): ?HtmlString
    {
        return $text === null || $text === '' ? null : new HtmlString('<span dir="ltr" class="cms-ltr">'.e($text).'</span>');
    }

    /** Practice areas for a Select (S01: title …), in site order. @return array<string, string> */
    public static function options(): array
    {
        return Service::query()->orderBy('position')->get()->mapWithKeys(fn (Service $s) => [$s->code => $s->code.': '.Registry::label($s)])->all();
    }

    /** @return array<string, string> */
    private static function siteOptions(): array
    {
        return SpecialistSite::query()->orderBy('position')->get()
            ->mapWithKeys(fn (SpecialistSite $s) => [$s->code => $s->code.': '.Registry::label($s).' ('.$s->domain.')'])->all();
    }

    /** @return array<string, string> */
    private static function guideOptions(): array
    {
        return Guide::query()->orderBy('position')->get()->mapWithKeys(fn (Guide $g) => [$g->code => $g->code.': '.Registry::label($g)])->all();
    }

    /** An empty list of related guides stays as stored (null or []), so an unchanged form saves nothing. */
    public static function fromFormState(Model $record, array $data): array
    {
        if (array_key_exists('related_guides', $data)) {
            $guides = array_values(array_filter((array) $data['related_guides'], fn ($code) => is_string($code) && $code !== ''));
            $data['related_guides'] = $guides === [] && CmsForm::working($record, 'related_guides') === null ? null : $guides;
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return CmsTable::configure($table, [
            TextColumn::make('code')->label((string) __('cms.field.code'))->sortable()->searchable()->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('title')->label((string) __('cms.field.title'))
                ->state(fn (Service $record) => Registry::label($record))
                ->description(fn (Service $record) => self::ltr($record->slug ? '/'.$record->slug.'/' : null))
                ->wrap()
                ->extraCellAttributes(['class' => 'cms-col-title'])->extraHeaderAttributes(['class' => 'cms-col-title'])
                ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('hub_title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"))),
            TextColumn::make('specialist')->label((string) __('cms_collections.practice_areas.specialist'))
                ->formatStateUsing(fn (?string $state) => $state ? (SpecialistSite::query()->find($state)?->domain ?? $state) : null)
                ->extraAttributes(['dir' => 'ltr'])
                ->toggleable(isToggledHiddenByDefault: true),
        ], exportName: 'practice-areas', reorderable: true);
    }

    /*
     * The eleven practice areas are the site map: none is created or deleted. Filament 5 asks the authorization
     * responses (the Delete action and the bulk delete do not call canDelete()), so these are denied.
     */
    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function getForceDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPracticeAreas::route('/'),
            'edit' => EditPracticeArea::route('/{record}/edit'),
        ];
    }
}
