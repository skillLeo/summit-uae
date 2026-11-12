<?php

namespace App\Filament\Resources\ContentPages;

use App\Content\Cms\Editing\Limits;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\BlockBuilder;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\ContentPages\Pages\EditContentPage;
use App\Filament\Resources\ContentPages\Pages\ListContentPages;
use App\Models\Content\Page;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Pages (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.4): home, practice areas overview, Legal Resources, case
 * studies, FAQ centre, about, contact, legal notice, privacy, terms, 404. Each page is its title, breadcrumb, slug,
 * SEO and its blocks (BlockBuilder). The set of pages is the site map: pages are not created or deleted here.
 */
class ContentPageResource extends CmsResource
{
    protected static string $screen = 'pages';

    protected static ?string $model = Page::class;

    protected static ?string $slug = 'pages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 10;

    protected static function labelKey(): string
    {
        return 'cms_pages.pages';
    }

    public static function form(Schema $schema): Schema
    {
        $limits = Limits::for('pages');
        $record = $schema->getRecord();

        return CmsForm::layout($schema, [
            Section::make((string) __('cms_pages.pages.section_page'))
                ->schema([
                    TextInput::make('code')->label((string) __('cms_pages.pages.key'))->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr']),
                    Translatable::text('h1', (string) __('cms_pages.pages.h1'), $limits['h1'] ?? null, required: true),
                    CmsForm::slug()->visible(fn (?Model $record) => $record !== null && ! in_array($record->getKey(), ['P01', 'U01'], true))
                        ->regex('#^(?:[a-z0-9]+(?:-[a-z0-9]+)*/)+$#'),
                    CmsForm::structured(Page::class, 'breadcrumb', (string) __('cms_pages.pages.breadcrumb'), $limits),
                    Translatable::textarea('whatsapp_draft', (string) __('cms_pages.pages.whatsapp_draft'), $limits['whatsapp_draft'] ?? null)
                        ->visible(fn (?Model $record) => filled(CmsForm::working($record, 'whatsapp_draft'))),
                    Translatable::text('last_updated', (string) __('cms_pages.pages.last_updated'), $limits['last_updated'] ?? null)
                        ->visible(fn (?Model $record) => filled(CmsForm::working($record, 'last_updated'))),
                ]),
            CmsForm::seo($limits),
            Section::make((string) __('cms.blocks.label'))
                ->schema($record !== null ? [BlockBuilder::make($record)->hiddenLabel()] : []),
        ]);
    }

    public static function table(Table $table): Table
    {
        return CmsTable::configure($table, [
            TextColumn::make('code')->label((string) __('cms.field.code'))->sortable()->searchable()->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('h1')->label((string) __('cms_pages.pages.h1'))
                ->state(fn (Page $record) => \App\Content\Cms\Editing\Registry::label($record))
                ->wrap()
                ->searchable(query: fn ($query, string $search) => $query->where('h1', 'like', '%'.$search.'%')),
            TextColumn::make('slug')->label((string) __('cms.field.slug'))->formatStateUsing(fn (?string $state) => '/'.($state ?? ''))
                ->extraAttributes(['dir' => 'ltr'])->toggleable(),
        ], exportName: 'pages')->defaultSort('code')->recordActions([\Filament\Actions\EditAction::make()])->toolbarActions([]);
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

    /** Filament 5 actions ask the authorization responses: the pages are the site map, never deleted or created. */
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

    public static function getPages(): array
    {
        return [
            'index' => ListContentPages::route('/'),
            'edit' => EditContentPage::route('/{record}/edit'),
        ];
    }
}
