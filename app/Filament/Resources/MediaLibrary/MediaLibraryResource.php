<?php

namespace App\Filament\Resources\MediaLibrary;

use App\Content\Cms\CopySource;
use App\Content\Cms\MediaFiles;
use App\Content\Cms\Tree;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Cms\Support\CmsLabels;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\MediaLibrary\Forms\FocalPointPicker;
use App\Filament\Resources\MediaLibrary\Pages\CreateMediaAsset;
use App\Filament\Resources\MediaLibrary\Pages\EditMediaAsset;
use App\Filament\Resources\MediaLibrary\Pages\ListMediaAssets;
use App\Models\MediaAsset;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use UnitEnum;

/**
 * Media Library (docs/briefs/design-2.1-and-admin-cms.md §8.9, §9.1 "Site"): the images and PDF downloads the pages can
 * use (the image-and-text block and the social images pick them by UUID, App\Filament\Cms\Forms\MediaPicker). Each asset
 * has one file (JPEG, PNG, WebP, AVIF or PDF, at most 10 MB), cleaned of EXIF and other metadata and converted to WebP
 * and AVIF at 480, 768, 1200 and 1600 px (App\Content\Cms\MediaFiles); its alt text per language and its focal point.
 * Not drafted: saving is immediate. Deleting moves the asset to the Trash (restorable); only a super admin deletes the
 * file for good. Team photos are uploaded on the Team screen.
 */
class MediaLibraryResource extends CmsResource
{
    protected static string $screen = 'media';

    protected static ?string $model = MediaAsset::class;

    protected static ?string $slug = 'media';

    protected static string|UnitEnum|null $navigationGroup = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 20;

    /** Alt text: a hard limit that keeps it a sentence (no approved text is longer: the longest is 89 characters). */
    public const ALT_LIMIT = 200;

    protected static function labelKey(): string
    {
        return 'cms_media.library';
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof MediaAsset ? (string) $record->name : static::getModelLabel();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('media')->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /** @return array<string, string> */
    public static function collectionOptions(): array
    {
        $out = [];
        foreach (MediaAsset::COLLECTIONS as $collection) {
            $out[$collection] = (string) __('cms_media.library.collection_'.$collection);
        }

        return $out;
    }

    public static function form(Schema $schema): Schema
    {
        $isImage = fn (?Model $record) => $record instanceof MediaAsset ? MediaFiles::isImage($record->file()) : true;

        $fields = [
            Section::make((string) __('cms_media.library.section_asset'))->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('name')->label((string) __('cms_media.library.name'))
                        ->helperText(fn (?Model $record) => $record === null ? (string) __('cms_media.library.name_hint') : null)
                        ->required(fn (?Model $record) => $record !== null)
                        ->maxLength(160),
                    Select::make('collection')->label((string) __('cms_media.library.collection'))
                        ->options(self::collectionOptions())->default('library')->required()->native(false)->selectablePlaceholder(false),
                ]),
                SpatieMediaLibraryFileUpload::make('file')
                    ->label((string) __('cms_media.library.file'))
                    ->helperText((string) __('cms_media.library.file_hint'))
                    ->collection(MediaAsset::FILE)
                    ->disk((string) config('media-library.disk_name', 'public'))
                    ->visibility('public')
                    ->acceptedFileTypes(MediaFiles::ACCEPTED)
                    ->maxSize(MediaFiles::MAX_KB)
                    ->required()
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file, ?Model $record) => self::store($record, $file))
                    ->visibleOn('create'), // the file of an asset is not replaced: pages refer to it by its UUID
            ]),
            Section::make((string) __('cms_media.library.section_alt'))
                ->description((string) __('cms_media.library.alt_hint'))
                ->schema([
                    Translatable::text('alt', (string) __('cms_media.library.alt'), self::ALT_LIMIT),
                ])
                ->visible($isImage),
            Section::make((string) __('cms_media.focal.label'))
                ->description((string) __('cms_media.library.focal_hint'))
                ->schema([
                    FocalPointPicker::make('focal_point')->hiddenLabel()
                        ->imageUrl(fn (?Model $record) => $record instanceof MediaAsset && ($media = $record->file()) !== null ? url(MediaFiles::mediaUrl($media, 'webp-768')) : null),
                ])
                ->visible(fn (?Model $record) => $record !== null && $isImage($record)),
        ];

        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'lg' => 3])
                ->extraAttributes(['class' => 'cms-edit cms-media-edit'])
                ->schema([
                    Group::make([CmsForm::localeSwitcher()->options(self::localeOptions(...)), ...$fields])->columnSpan(['default' => 1, 'lg' => 2]),
                    self::filePanel()->columnSpan(['default' => 1, 'lg' => 1]),
                ]),
        ]);
    }

    /** The end-side panel: the file, its type, size, dimensions, conversions and address, and the approval. */
    private static function filePanel(): Section
    {
        $media = fn (?Model $record): ?Media => $record instanceof MediaAsset ? $record->file() : null;

        return Section::make((string) __('cms_media.library.section_file'))
            ->extraAttributes(['class' => 'cms-publish-panel cms-media-panel'])
            ->schema([
                ImageEntry::make('cms_media_preview')->hiddenLabel()
                    ->state(fn (?Model $record) => ($m = $media($record)) !== null && MediaFiles::isImage($m) ? url(MediaFiles::mediaUrl($m, 'webp-480')) : null)
                    ->extraImgAttributes(['alt' => '', 'class' => 'cms-media-panel__img'])
                    ->imageWidth('100%')->imageHeight('auto')
                    ->visible(fn (?Model $record) => MediaFiles::isImage($media($record))),
                TextEntry::make('cms_media_type')->label((string) __('cms_media.library.type'))
                    ->state(fn (?Model $record) => self::typeLabel($media($record)))
                    ->visible(fn (?Model $record) => $media($record) !== null),
                TextEntry::make('cms_media_size')->label((string) __('cms_media.library.size'))
                    ->state(fn (?Model $record) => ($m = $media($record)) !== null ? MediaFiles::humanSize((int) $m->size) : null)
                    ->extraAttributes(['dir' => 'ltr', 'class' => 'cms-media-figures'])
                    ->visible(fn (?Model $record) => $media($record) !== null),
                TextEntry::make('cms_media_dimensions')->label((string) __('cms_media.library.dimensions'))
                    ->state(fn (?Model $record) => self::dimensions($media($record)))
                    ->extraAttributes(['dir' => 'ltr', 'class' => 'cms-media-figures'])
                    ->visible(fn (?Model $record) => MediaFiles::isImage($media($record))),
                TextEntry::make('cms_media_conversions')->label((string) __('cms_media.library.conversions'))
                    ->state(fn (?Model $record) => self::conversions($media($record)))
                    ->visible(fn (?Model $record) => MediaFiles::isImage($media($record))),
                TextEntry::make('cms_media_url')->label((string) __('cms_media.library.url'))
                    ->state(fn (?Model $record) => ($m = $media($record)) !== null ? MediaFiles::mediaUrl($m) : null)
                    ->url(fn (?Model $record) => ($m = $media($record)) !== null ? MediaFiles::mediaUrl($m) : null, shouldOpenInNewTab: true)
                    ->copyable()
                    ->extraAttributes(['dir' => 'ltr', 'class' => 'cms-media-url'])
                    ->visible(fn (?Model $record) => $media($record) !== null),
                TextEntry::make('cms_media_source')->label((string) __('cms_media.library.source'))
                    ->state(fn (?Model $record) => ($m = $media($record)) !== null
                        ? (string) __('cms_media.library.source_'.($m->getCustomProperty('source') === MediaFiles::SOURCE_IMPORT ? 'import' : 'upload'))
                        : null)
                    ->visible(fn (?Model $record) => $media($record) !== null),
                Select::make('copy_source')->label((string) __('cms_media.library.approval'))
                    ->helperText((string) __('cms_media.library.approval_hint'))
                    ->options(CmsLabels::copyOptions())->default(CopySource::PENDING)->required()->native(false)->selectablePlaceholder(false)
                    ->visible(fn (?Model $record) => $record !== null),
                TextEntry::make('cms_media_updated')->label((string) __('cms.field.updated_at'))
                    ->state(fn (?Model $record) => $record !== null ? CmsLabels::dubai($record->updated_at) : null)
                    ->visible(fn (?Model $record) => $record !== null),
                TextEntry::make('cms_media_new')->hiddenLabel()
                    ->state((string) __('cms_media.library.new_hint'))
                    ->color('gray')
                    ->visible(fn (?Model $record) => $record === null),
            ]);
    }

    /** @return array<string, string> the locale switcher's options with the missing alt texts */
    public static function localeOptions(?Model $record): array
    {
        $missing = $record instanceof MediaAsset ? self::missingAlt($record) : [];
        $out = [];
        foreach (Tree::LOCALES as $locale) {
            $out[$locale] = CmsLabels::locale($locale).(in_array($locale, $missing, true) ? ' ('.trans_choice('cms.locale.missing_count', 1, ['count' => 1]).')' : '');
        }

        return $out;
    }

    /**
     * The languages whose alt text is empty (images only; a PDF has none).
     *
     * @return list<string>
     */
    public static function missingAlt(MediaAsset $asset): array
    {
        $media = $asset->file();
        if (! MediaFiles::isImage($media)) {
            return [];
        }
        $alt = (array) ($media->getCustomProperty('alt') ?? []);

        return array_values(array_filter(Tree::LOCALES, fn (string $l) => ! is_string($alt[$l] ?? null) || trim($alt[$l]) === ''));
    }

    public static function typeLabel(?Media $media): ?string
    {
        if ($media === null) {
            return null;
        }

        return match ($media->mime_type) {
            'image/jpeg' => 'JPEG',
            'image/png' => 'PNG',
            'image/webp' => 'WebP',
            'image/avif' => 'AVIF',
            'application/pdf' => 'PDF',
            default => (string) $media->mime_type,
        };
    }

    public static function dimensions(?Media $media): ?string
    {
        $w = (int) ($media?->getCustomProperty('width') ?? 0);
        $h = (int) ($media?->getCustomProperty('height') ?? 0);

        return $w > 0 && $h > 0 ? $w.' × '.$h : null;
    }

    private static function conversions(?Media $media): ?string
    {
        if ($media === null) {
            return null;
        }
        $widths = [];
        foreach (MediaFiles::sizes($media, false) as $name => [$width]) {
            if ($media->hasGeneratedConversion('webp-'.$name) && $media->hasGeneratedConversion('avif-'.$name)) {
                $widths[] = $width;
            }
        }

        return $widths === [] ? (string) __('cms_media.library.conversions_none')
            : (string) __('cms_media.library.conversions_list', ['widths' => implode((string) __('cms_media.library.list_separator'), $widths)]);
    }

    /** Store an uploaded file on an asset (checked, cleaned, measured, converted). */
    public static function store(?Model $record, TemporaryUploadedFile $file): ?string
    {
        if (! $record instanceof MediaAsset || ! $file->exists()) {
            return null;
        }
        $media = MediaFiles::add($record, $file->getRealPath(), MediaAsset::FILE,
            pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), $file->getClientOriginalName(), [
                'source' => MediaFiles::SOURCE_UPLOAD,
                'copy_source' => (string) ($record->copy_source ?: CopySource::PENDING),
                'alt' => array_fill_keys(Tree::LOCALES, ''),
            ]);

        return (string) $media->uuid;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cms_media_thumb')->label((string) __('cms_media.library.preview'))
                    ->state(fn (MediaAsset $record) => ($m = $record->file()) !== null && MediaFiles::isImage($m) ? url(MediaFiles::mediaUrl($m, 'webp-480')) : null)
                    ->imageHeight(48)->imageWidth(72)
                    ->extraImgAttributes(['alt' => '', 'class' => 'cms-media-thumb', 'loading' => 'lazy'])
                    ->defaultImageUrl(null),
                TextColumn::make('name')->label((string) __('cms_media.library.name'))
                    ->description(fn (MediaAsset $record) => $record->file()?->file_name)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('media', fn ($m) => $m->where('file_name', 'like', "%{$search}%")->orWhere('custom_properties', 'like', "%{$search}%"))))
                    ->sortable()
                    ->wrap(),
                TextColumn::make('collection')->label((string) __('cms_media.library.collection'))
                    ->formatStateUsing(fn (?string $state) => self::collectionOptions()[$state] ?? $state)
                    ->badge()->color('gray')
                    ->toggleable(),
                TextColumn::make('cms_media_type')->label((string) __('cms_media.library.type'))
                    ->state(fn (MediaAsset $record) => self::typeLabel($record->file())),
                TextColumn::make('cms_media_size')->label((string) __('cms_media.library.size'))
                    ->state(fn (MediaAsset $record) => ($m = $record->file()) !== null ? MediaFiles::humanSize((int) $m->size) : null)
                    ->extraAttributes(['dir' => 'ltr', 'class' => 'cms-media-figures'])
                    ->toggleable(),
                TextColumn::make('cms_media_dimensions')->label((string) __('cms_media.library.dimensions'))
                    ->state(fn (MediaAsset $record) => self::dimensions($record->file()))
                    ->placeholder('–')
                    ->extraAttributes(['dir' => 'ltr', 'class' => 'cms-media-figures'])
                    ->toggleable(),
                TextColumn::make('cms_media_alt')->label((string) __('cms_media.library.alt_status'))
                    ->state(function (MediaAsset $record): array {
                        if (! MediaFiles::isImage($record->file())) {
                            return [];
                        }
                        $missing = self::missingAlt($record);

                        return array_map(fn (string $l) => $l.(in_array($l, $missing, true) ? ':missing' : ':ok'), Tree::LOCALES);
                    })
                    ->formatStateUsing(fn (string $state) => strtoupper(explode(':', $state)[0]))
                    ->badge()
                    ->color(fn (string $state) => str_ends_with($state, ':missing') ? 'warning' : 'gray')
                    ->icon(fn (string $state) => str_ends_with($state, ':missing') ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check')
                    ->tooltip(function (MediaAsset $record) {
                        $missing = self::missingAlt($record);

                        return $missing === [] ? (string) __('cms_media.library.alt_complete')
                            : (string) __('cms_media.library.alt_missing_in', ['languages' => implode(', ', array_map(CmsLabels::locale(...), $missing))]);
                    })
                    ->placeholder((string) __('cms_media.library.alt_none'))
                    ->extraAttributes(['class' => 'cms-translation-chips', 'dir' => 'ltr']),
                CmsTable::copyColumn(),
                TextColumn::make('updated_at')->label((string) __('cms.field.updated_at'))
                    ->formatStateUsing(fn ($state) => CmsLabels::dubai($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('collection')->label((string) __('cms_media.library.collection'))->options(self::collectionOptions()),
                SelectFilter::make('cms_media_kind')->label((string) __('cms_media.library.type'))
                    ->options(['image' => (string) __('cms_media.library.kind_image'), 'pdf' => (string) __('cms_media.library.kind_pdf')])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'image' => $query->whereHas('media', fn ($m) => $m->where('mime_type', 'like', 'image/%')),
                        'pdf' => $query->whereHas('media', fn ($m) => $m->where('mime_type', 'application/pdf')),
                        default => $query,
                    }),
                TernaryFilter::make('cms_media_missing_alt')->label((string) __('cms_media.library.missing_alt'))
                    ->placeholder((string) __('cms_media.library.missing_alt_all'))
                    ->trueLabel((string) __('cms_media.library.missing_alt_yes'))
                    ->falseLabel((string) __('cms_media.library.missing_alt_no'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereIn('id', self::missingAltIds()),
                        false: fn (Builder $query) => $query->whereNotIn('id', self::missingAltIds()),
                        blank: fn (Builder $query) => $query,
                    ),
                SelectFilter::make('copy_source')->label((string) __('cms.copy.label'))->options(CmsLabels::copyOptions()),
                TrashedFilter::make(),
            ])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make(),
                RestoreAction::make()->label((string) __('cms.action.restore'))->successNotificationTitle((string) __('cms.action.restored')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label((string) __('cms.action.delete')),
                    RestoreBulkAction::make()->label((string) __('cms.action.restore')),
                    ForceDeleteBulkAction::make()->label((string) __('cms.action.force_delete'))
                        ->visible(fn () => CmsAccess::can('media', 'force_delete'))->authorize(fn () => CmsAccess::can('media', 'force_delete')),
                ]),
            ])
            ->emptyStateHeading((string) __('cms_media.library.empty'))
            ->emptyStateDescription((string) __('cms_media.library.empty_body'))
            ->extraAttributes(['class' => 'cms-table cms-media-table']);
    }

    /** @return list<int> ids of the image assets (Trash included) with an empty alt text in any language */
    public static function missingAltIds(): array
    {
        $ids = [];
        MediaAsset::withTrashed()->with('media')->chunkById(200, function ($assets) use (&$ids) {
            foreach ($assets as $asset) {
                if (self::missingAlt($asset) !== []) {
                    $ids[] = (int) $asset->id;
                }
            }
        });

        return $ids;
    }

    /** Header actions of the edit page: Trash, restore, permanent delete (super admin). */
    public static function editHeaderActions(): array
    {
        return [
            DeleteAction::make()->label((string) __('cms.action.delete'))->successNotificationTitle((string) __('cms.action.deleted')),
            RestoreAction::make()->label((string) __('cms.action.restore'))->successNotificationTitle((string) __('cms.action.restored')),
            ForceDeleteAction::make()->label((string) __('cms.action.force_delete'))
                ->modalDescription((string) __('cms_media.library.force_delete_body'))
                ->successNotificationTitle((string) __('cms.action.force_deleted'))
                ->visible(fn (Model $record) => CmsAccess::can('media', 'force_delete') && $record->trashed())
                ->authorize(fn () => CmsAccess::can('media', 'force_delete')),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaAssets::route('/'),
            'create' => CreateMediaAsset::route('/create'),
            'edit' => EditMediaAsset::route('/{record}/edit'),
        ];
    }
}
