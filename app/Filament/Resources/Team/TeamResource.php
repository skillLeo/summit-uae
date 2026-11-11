<?php

namespace App\Filament\Resources\Team;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\Registry;
use App\Content\Cms\MediaFiles;
use App\Content\Cms\Tree;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\MediaLibrary\Forms\FocalPointPicker;
use App\Filament\Resources\Team\Pages\CreateTeamMember;
use App\Filament\Resources\Team\Pages\EditTeamMember;
use App\Filament\Resources\Team\Pages\ListTeamMembers;
use App\Models\Content\TeamMember;
use BackedEnum;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Team (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.11): name, role, focus line, biography, areas of expertise,
 * working languages, links, the photo's alt text, anchor (#team-ahmed …) and order, all drafted like every collection;
 * the "photo pending" flag and the focal point (the point between the eyes, App\Design\D2\Portraits) are draft fields too.
 *
 * The photo: an upload (media collection "photo", source = upload; JPEG, PNG, WebP or AVIF, 10 MB, cleaned of EXIF,
 * converted to 4:5 WebP and AVIF) is stored when the form is saved and replaces the static files on the public site at
 * once (<x-d2.team.portrait>), unless the photo is pending. Without an upload the site keeps public/images/d2/team.
 *
 * Expertise: the brief allows up to 3 expertise tags; the approved profiles have more items (5 to 10). They are kept;
 * only new items beyond 3 are refused (validateAreas()).
 */
class TeamResource extends CmsResource
{
    protected static ?string $model = TeamMember::class;

    protected static ?string $slug = 'team';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 60;

    public const MAX_AREAS = 3;

    protected static function labelKey(): string
    {
        return 'cms_media.team';
    }

    public static function form(Schema $schema): Schema
    {
        $limits = Limits::for('team_members');
        $record = $schema->getRecord();

        return CmsForm::layout($schema, [
            Section::make((string) __('cms_media.team.section_profile'))->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('code')->label((string) __('cms.field.code'))->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr'])
                        ->visible($record !== null),
                    CmsForm::anchor()->required($record !== null)
                        ->rule(fn () => Rule::unique('team_members', 'anchor')->ignore($record?->getKey(), 'code')),
                    CmsForm::position(),
                ]),
                Translatable::text('name', (string) __('cms_media.team.name'), $limits['name'] ?? null, required: true),
                Text::make((string) __('cms_media.team.new_name_hint'))->color('gray')->size('sm')->visible($record === null),
                Translatable::text('role', (string) __('cms_media.team.role'), $limits['role'] ?? null),
                Translatable::textarea('focus', (string) __('cms_media.team.focus'), $limits['focus'] ?? null, rows: 2),
                CmsForm::structured(TeamMember::class, 'bio', (string) __('cms_media.team.bio'), self::below('bio', $limits)),
            ]),
            Section::make((string) __('cms_media.team.section_expertise'))->schema([
                Text::make((string) __('cms_media.team.areas_hint'))->color('gray')->size('sm'),
                CmsForm::structured(TeamMember::class, 'areas', (string) __('cms_media.team.areas'), self::below('areas', $limits)),
                Translatable::text('languages', (string) __('cms_media.team.languages'), $limits['languages'] ?? null),
                CmsForm::structured(TeamMember::class, 'links', (string) __('cms_media.team.links'), self::below('links', $limits)),
            ]),
            Section::make((string) __('cms_media.team.section_photo'))->schema([
                Toggle::make('photo_pending')->label((string) __('cms_media.team.photo_pending'))
                    ->helperText((string) __('cms_media.team.photo_pending_hint'))
                    ->default(true),
                SpatieMediaLibraryFileUpload::make('photo')
                    ->label((string) __('cms_media.team.photo'))
                    ->helperText((string) __('cms_media.team.photo_hint'))
                    ->collection(TeamMember::PHOTO)
                    ->filterMediaUsing(fn (Collection $media) => $media->filter(fn (Media $m) => $m->getCustomProperty('source') === MediaFiles::SOURCE_UPLOAD))
                    ->disk((string) config('media-library.disk_name', 'public'))
                    ->visibility('public')
                    ->conversion('webp-480')
                    ->image()
                    ->acceptedFileTypes(MediaFiles::IMAGES)
                    ->maxSize(MediaFiles::MAX_KB)
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file, ?Model $record) => self::storePhoto($record, $file)),
                FocalPointPicker::make('focal_point')
                    ->label((string) __('cms_media.focal.label'))
                    ->helperText(fn (?Model $record) => (string) __('cms_media.team.focal_hint')
                        .(self::photoUrl($record) !== null ? ' '.(string) __('cms_media.team.focal_on', ['file' => self::photoSource($record)]) : ''))
                    ->imageUrl(fn (?Model $record) => self::photoUrl($record))
                    ->emptyText((string) __('cms_media.team.focal_empty')),
                Translatable::text('alt', (string) __('cms_media.team.alt'), $limits['alt'] ?? null),
                TextInput::make('portrait')->label((string) __('cms_media.team.portrait'))
                    ->helperText((string) __('cms_media.team.portrait_hint'))
                    ->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr'])
                    ->visible(fn (?Model $record) => filled(CmsForm::working($record, 'portrait'))),
            ]),
        ]);
    }

    /**
     * The limits below one structured column, keyed as TreeFields::perLocale() looks them up ("bio.*" stays "bio.*").
     *
     * @param  array<string, int>  $limits
     * @return array<string, int>
     */
    private static function below(string $column, array $limits): array
    {
        return array_filter($limits, fn (string $path) => str_starts_with($path, $column.'.'), ARRAY_FILTER_USE_KEY);
    }

    // ------------------------------------------------------------------ the photo

    /** The uploaded photo of a member (source = upload), else null. */
    public static function upload(?Model $record): ?Media
    {
        return $record instanceof TeamMember && $record->exists ? MediaFiles::teamPhoto((string) $record->getKey()) : null;
    }

    /** The photo the focal point is set on: the uploaded one (its 4:5 conversion), else the site's static 4:5 file. */
    public static function photoUrl(?Model $record): ?string
    {
        if (($upload = self::upload($record)) !== null) {
            return url(MediaFiles::mediaUrl($upload, 'webp-768'));
        }
        $stem = basename((string) CmsForm::working($record, 'portrait'));
        if ($stem !== '' && is_file(public_path("images/d2/team/{$stem}-800.webp"))) {
            return asset("images/d2/team/{$stem}-800.webp");
        }
        if ($stem !== '' && is_file(public_path("images/team/{$stem}-800.jpg"))) {
            return asset("images/team/{$stem}-800.jpg");
        }

        return null;
    }

    private static function photoSource(?Model $record): string
    {
        return (string) __(self::upload($record) !== null ? 'cms_media.team.focal_upload' : 'cms_media.team.focal_static');
    }

    /** Store an uploaded photo on the member: cleaned, measured and converted (4:5); the public portrait uses it at once. */
    public static function storePhoto(?Model $record, TemporaryUploadedFile $file): ?string
    {
        if (! $record instanceof TeamMember || ! $file->exists()) {
            return null;
        }
        $media = MediaFiles::add($record, $file->getRealPath(), TeamMember::PHOTO,
            pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), $file->getClientOriginalName(), [
                'source' => MediaFiles::SOURCE_UPLOAD,
                'copy_source' => 'pending',
            ]);
        // one uploaded photo per member: an earlier upload the editor did not remove is replaced
        $record->media()->where('collection_name', TeamMember::PHOTO)->whereKeyNot($media->getKey())->get()
            ->filter(fn (Media $m) => $m->getCustomProperty('source') === MediaFiles::SOURCE_UPLOAD)
            ->each(fn (Media $m) => $m->delete());

        return (string) $media->uuid;
    }

    // ------------------------------------------------------------------ form state

    /**
     * An unchanged focal point keeps the stored numbers (49.7 stays 49.7, 50.0 stays 50.0), so saving an untouched form
     * creates no draft; a blank anchor of a new member is its key.
     */
    public static function fromFormState(Model $record, array $data): array
    {
        if (array_key_exists('focal_point', $data)) {
            $stored = MediaFiles::point(CmsForm::working($record, 'focal_point'));
            $point = MediaFiles::point($data['focal_point']);
            if ($stored !== null && $point !== null && (float) $stored[0] === (float) $point[0] && (float) $stored[1] === (float) $point[1]) {
                $point = CmsForm::working($record, 'focal_point');
            }
            $data['focal_point'] = $point;
        }
        if (blank($data['anchor'] ?? null) && ! $record->exists) {
            $data['anchor'] = (string) $record->getKey();
        }

        return $data;
    }

    /**
     * Up to 3 expertise items (brief §8.2): the stored items are kept (approved copy is never cut); a language that gets
     * more items than max(3, stored) is refused.
     *
     * @param  array<string, mixed>  $data
     */
    public static function validateAreas(?Model $record, array $data, callable $switchTo): void
    {
        $stored = $record?->exists ? (array) (Publisher::workingCopy($record)['areas'] ?? []) : [];
        foreach (Tree::LOCALES as $locale) {
            $items = array_values(array_filter((array) ($data['areas'][$locale]['items'] ?? []), fn ($item) => ! Translatable::blank($item)));
            $allowed = max(self::MAX_AREAS, count((array) ($stored[$locale]['items'] ?? [])));
            if (count($items) > $allowed) {
                $switchTo($locale);

                throw ValidationException::withMessages(['data.areas.'.$locale.'.items' => (string) __('cms_media.team.areas_limit')]);
            }
        }
    }

    /** The key of a new member: team-<first name> in lowercase letters (the portrait rules need ^team-[a-z]+$). */
    public static function newRecordAttributes(array $data): array
    {
        $name = (string) ($data['name']['en'] ?? '');
        $words = array_values(array_filter(
            preg_split('/\s+/', Str::lower(Str::ascii($name))) ?: [],
            fn (string $w) => ! in_array(trim($w, '.'), ['mr', 'ms', 'mrs', 'miss', 'dr', 'prof', 'professor'], true),
        ));
        $words = array_values(array_filter(array_map(fn (string $w) => (string) preg_replace('/[^a-z]/', '', $w), $words)));
        $base = substr($words[0] ?? 'member', 0, 24) ?: 'member';
        $taken = TeamMember::withTrashed()->pluck('code')->all();
        $code = 'team-'.$base;
        foreach (array_slice($words, 1) as $word) {
            if (! in_array($code, $taken, true)) {
                break;
            }
            $code .= substr($word, 0, 24);
        }
        $suffix = 'a';
        $stem = $code;
        while (in_array($code, $taken, true)) {
            $code = $stem.$suffix;
            $suffix++;
        }

        return ['code' => $code];
    }

    // ------------------------------------------------------------------ list

    public static function table(Table $table): Table
    {
        return CmsTable::configure($table, [
            ImageColumn::make('cms_team_photo')->label((string) __('cms_media.team.list_photo'))
                ->state(fn (TeamMember $record) => self::thumb($record))
                ->imageHeight(56)->imageWidth(45)
                ->extraImgAttributes(['alt' => '', 'class' => 'cms-media-thumb', 'loading' => 'lazy']),
            TextColumn::make('name')->label((string) __('cms_media.team.name'))
                ->state(fn (TeamMember $record) => Registry::label($record))
                ->description(fn (TeamMember $record) => self::text($record, 'role'))
                ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('role', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))),
            TextColumn::make('anchor')->label((string) __('cms.field.anchor'))
                ->formatStateUsing(fn (?string $state) => $state ? '#'.$state : null)
                ->extraAttributes(['dir' => 'ltr'])
                ->toggleable(),
            TextColumn::make('cms_team_photo_status')->label((string) __('cms_media.team.photo_shown'))
                ->state(fn (TeamMember $record) => self::photoStatus($record))
                ->formatStateUsing(fn (string $state) => (string) __('cms_media.team.photo_'.$state))
                ->badge()
                ->color(fn (string $state) => $state === 'none' ? 'warning' : 'gray')
                ->icon(fn (string $state) => match ($state) {
                    'uploaded' => 'heroicon-m-arrow-up-tray',
                    'static' => 'heroicon-m-photo',
                    default => 'heroicon-m-user',
                }),
        ], [
            TernaryFilter::make('photo_pending')->label((string) __('cms_media.team.photo_pending')),
        ], exportName: 'team', reorderable: true);
    }

    private static function text(TeamMember $record, string $column): ?string
    {
        $value = Publisher::live($record)[$column] ?? null;
        $locale = app()->getLocale();

        return is_array($value) && is_string($value[$locale] ?? $value['en'] ?? null) ? ($value[$locale] ?? $value['en']) : null;
    }

    /** uploaded | static | none (the site shows the initials panel) */
    public static function photoStatus(TeamMember $record): string
    {
        if ((bool) Publisher::live($record)['photo_pending']) {
            return 'none';
        }
        if (self::upload($record) !== null) {
            return 'uploaded';
        }

        return self::photoUrl($record) !== null ? 'static' : 'none';
    }

    private static function thumb(TeamMember $record): ?string
    {
        if (($upload = self::upload($record)) !== null) {
            return url(MediaFiles::mediaUrl($upload, 'webp-480'));
        }
        $stem = basename((string) (Publisher::live($record)['portrait'] ?? ''));

        return $stem !== '' && is_file(public_path("images/d2/team/{$stem}-480.webp")) ? asset("images/d2/team/{$stem}-480.webp") : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamMembers::route('/'),
            'create' => CreateTeamMember::route('/create'),
            'edit' => EditTeamMember::route('/{record}/edit'),
        ];
    }
}
