<?php

namespace App\Filament\Resources\DocumentSamples;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\Registry;
use App\Filament\Cms\Actions\CmsActions;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Forms\TreeFields;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\DocumentSamples\Pages\EditDocumentSample;
use App\Filament\Resources\DocumentSamples\Pages\ListDocumentSamples;
use App\Models\Content\DocumentSample;
use App\Models\Content\Service;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Document Samples (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.11): the documents of "Anatomy of a Document" on the
 * home (cheque, employment contract, tenancy contract, crypto transfer, hearing notice), their name, the practice area
 * they lead to, order, on/off, and their parts: label, what it can show, what we check. The parts are one structure for
 * every language (Registry tree "parts"); a part's key is the field id of the illustration (App\Design\D2\Docs::HOTSPOTS),
 * so it is read-only. The illustrations stay in code: there is no new document type without one, so samples are not
 * created or deleted here, and parts are not added or removed (every illustrated field already has its part).
 */
class DocumentSampleResource extends CmsResource
{
    protected static string $screen = 'document_samples';

    protected static ?string $model = DocumentSample::class;

    protected static ?string $slug = 'document-samples';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static ?int $navigationSort = 80;

    protected static function labelKey(): string
    {
        return 'cms_media.docs';
    }

    public static function form(Schema $schema): Schema
    {
        $limits = Limits::for('document_samples');
        $record = $schema->getRecord();

        return CmsForm::layout($schema, [
            Section::make((string) __('cms_media.docs.section_document'))->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('code')->label((string) __('cms.field.code'))->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr']),
                    Select::make('practice_area')->label((string) __('cms_media.docs.practice_area'))
                        ->options(fn () => self::practiceAreas())
                        ->placeholder((string) __('cms_media.docs.practice_area_none'))
                        ->native(false),
                    CmsForm::position(),
                ]),
                Translatable::text('title', (string) __('cms_media.docs.title'), $limits['title'] ?? null, required: true),
                Toggle::make('enabled')->label((string) __('cms_media.docs.enabled')),
            ]),
            Section::make((string) __('cms_media.docs.parts'))
                ->description((string) __('cms_media.docs.parts_hint'))
                ->schema([
                    Repeater::make('parts')->hiddenLabel()
                        ->schema([
                            TextInput::make('key')->label((string) __('cms_media.docs.key'))
                                ->disabled()->dehydrated()
                                ->extraInputAttributes(['dir' => 'ltr']),
                            Translatable::text('label', (string) __('cms_media.docs.label'), $limits['parts.*.label'] ?? null, required: true),
                            Translatable::textarea('can_show', (string) __('cms_media.docs.can_show'), $limits['parts.*.can_show'] ?? null, rows: 2),
                            Translatable::textarea('we_check', (string) __('cms_media.docs.we_check'), $limits['parts.*.we_check'] ?? null, rows: 2),
                        ])
                        ->itemLabel(fn (array $state) => TreeFields::firstText($state['label'] ?? null) ?? ($state['key'] ?? null))
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->collapsed()
                        ->defaultItems(0),
                ]),
        ]);
    }

    /** @return array<string, string> practice area key => its title in the admin language */
    public static function practiceAreas(): array
    {
        return Service::query()->orderBy('position')->get()
            ->mapWithKeys(fn (Service $s) => [(string) $s->getKey() => $s->getKey().' · '.Registry::label($s)])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return CmsTable::configure($table, [
            TextColumn::make('code')->label((string) __('cms.field.code'))->sortable()->searchable()->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('title')->label((string) __('cms_media.docs.title'))
                ->state(fn (DocumentSample $record) => Registry::label($record))
                ->description(fn (DocumentSample $record) => trans_choice('cms_media.docs.parts_count', $n = count((array) (Publisher::live($record)['parts'] ?? [])), ['count' => $n]))
                ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('parts', 'like', "%{$search}%"))),
            TextColumn::make('practice_area')->label((string) __('cms_media.docs.practice_area'))
                ->formatStateUsing(fn (?string $state) => $state ? (self::practiceAreas()[$state] ?? $state) : null)
                ->placeholder((string) __('cms_media.docs.practice_area_none'))
                ->wrap()
                ->toggleable(),
            IconColumn::make('enabled')->label((string) __('cms_media.docs.enabled'))->boolean(),
        ], [
            TernaryFilter::make('enabled')->label((string) __('cms_media.docs.enabled')),
        ], exportName: 'document-samples', reorderable: true)
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    CmsActions::bulkPublish(),
                    CmsActions::bulkUnpublish(),
                    CmsActions::bulkExport('document-samples', fn (Model $record) => CmsTable::exportRow($record)),
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
            'index' => ListDocumentSamples::route('/'),
            'edit' => EditDocumentSample::route('/{record}/edit'),
        ];
    }
}
