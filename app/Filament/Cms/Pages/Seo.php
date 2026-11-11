<?php

namespace App\Filament\Cms\Pages;

use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\Registry;
use App\Content\Cms\Tree;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\MediaPicker;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Pages\Settings\Concerns\EditsSettings;
use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Cms\Support\CmsLabels;
use App\Models\Content\CaseStudy;
use App\Models\Content\Guide;
use App\Models\Content\Page as ContentPage;
use App\Models\Content\Service;
use App\Settings\SeoSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use UnitEnum;

/**
 * SEO (docs/briefs/design-2.1-and-admin-cms.md §8.2 "SEO: titles, descriptions, social images, sitemap, robots,
 * redirects; per record plus global defaults"):
 *   - the global defaults (App\Settings\SeoSettings): a title suffix, a default description and a default social
 *     image per language, and whether the site may be indexed. App\Support\Seo::make() applies the default description
 *     (to pages without one) and the index switch; with today's values (none, and "may be indexed") every page is
 *     unchanged. The title suffix is stored but not applied: the approved SEO titles already end with the brand.
 *   - every record with SEO fields (pages, practice areas, legal guides, case studies), with its SEO title and meta
 *     description in the chosen language, the languages where one is missing, and a link to its edit screen, where the
 *     per-record fields are edited (CmsForm::seo()).
 * Redirects have their own screen under this one (App\Filament\Resources\Redirects).
 */
class Seo extends SettingsPage implements HasTable
{
    use EditsSettings;
    use InteractsWithTable;

    protected static string $settings = SeoSettings::class;

    protected static ?string $slug = 'seo';

    protected static string|UnitEnum|null $navigationGroup = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = 40;

    /** The records with SEO fields: type => model (the morph names of Registry::models()). */
    public const MODELS = [
        'page' => ContentPage::class,
        'practice_area' => Service::class,
        'guide' => Guide::class,
        'case_study' => CaseStudy::class,
    ];

    /** SEO is an admin screen (brief §12.1 Admin: "SEO"), not a super-admin one. */
    public static function canAccess(): bool
    {
        return CmsAccess::admin();
    }

    public function canEdit(): bool
    {
        return CmsAccess::admin();
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('cms_site.seo.title');
    }

    public function getTitle(): string
    {
        return (string) __('cms_site.seo.heading');
    }

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.seo.subheading');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            CmsForm::localeSwitcher(),
            Section::make((string) __('cms_site.seo.defaults'))
                ->description((string) __('cms_site.seo.defaults_hint'))
                ->schema([
                    Translatable::text('title_suffix', (string) __('cms_site.seo.title_suffix'), 60)
                        ->extraAttributes(['class' => 'cms-seo-suffix']),
                    Text::make((string) __('cms_site.seo.title_suffix_hint'))->color('gray')->size('sm'),
                    Translatable::textarea('default_description', (string) __('cms_site.seo.default_description'), 200, rows: 2),
                    Text::make((string) __('cms_site.seo.default_description_hint'))->color('gray')->size('sm'),
                    MediaPicker::make('default_og_image')->label((string) __('cms_site.seo.default_og_image'))
                        ->helperText((string) __('cms_site.seo.default_og_image_hint')),
                    Toggle::make('robots_index')->label((string) __('cms_site.seo.robots_index'))
                        ->helperText((string) __('cms_site.seo.robots_index_hint')),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            Section::make((string) __('cms_site.seo.records'))
                ->description((string) __('cms_site.seo.records_hint'))
                ->schema([EmbeddedTable::make()])
                ->extraAttributes(['class' => 'cms-seo-records']),
        ]);
    }

    /** @param array<string, mixed> $data */
    protected function settingsToForm(array $data): array
    {
        foreach (['title_suffix', 'default_description'] as $field) {
            $data[$field] = array_merge(array_fill_keys(Tree::LOCALES, null), (array) ($data[$field] ?? []));
        }

        return $data;
    }

    /** Empty languages are left out; a value without any language is null (as stored today). */
    protected function formToSettings(array $data): array
    {
        foreach (['title_suffix', 'default_description'] as $field) {
            $value = [];
            foreach (Tree::LOCALES as $locale) {
                $text = $data[$field][$locale] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    $value[$locale] = $field === 'title_suffix' ? $text : trim($text);
                }
            }
            $data[$field] = $value === [] ? null : $value;
        }
        $data['default_og_image'] = filled($data['default_og_image'] ?? null) ? (string) $data['default_og_image'] : null;
        $data['robots_index'] = (bool) ($data['robots_index'] ?? true);

        return $data;
    }

    // ------------------------------------------------------------------ the records table

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, ?array $filters, int|string $page, int|string $recordsPerPage) => self::paginate(
                self::rows($search, $filters ?? []), (int) $page, $recordsPerPage === 'all' ? 1000 : (int) $recordsPerPage,
            ))
            ->columns([
                TextColumn::make('label')->label((string) __('cms_site.seo.record'))
                    ->description(fn (array $record) => $record['type_label'].' · '.$record['code'])
                    ->wrap()
                    ->searchable(),
                TextColumn::make('seo_title')->label((string) __('cms.field.seo_title'))
                    ->placeholder((string) __('cms.locale.missing'))
                    ->description(fn (array $record) => $record['seo_title'] !== null ? trans_choice('cms_site.seo.characters', mb_strlen($record['seo_title']), ['count' => mb_strlen($record['seo_title'])]) : null)
                    ->extraAttributes(fn (array $record) => ['lang' => $record['locale'], 'dir' => $record['locale'] === 'ar' ? 'rtl' : 'ltr'])
                    ->wrap(),
                TextColumn::make('meta_description')->label((string) __('cms.field.meta_description'))
                    ->placeholder((string) __('cms.locale.missing'))
                    ->extraAttributes(fn (array $record) => ['lang' => $record['locale'], 'dir' => $record['locale'] === 'ar' ? 'rtl' : 'ltr'])
                    ->limit(140)
                    ->wrap(),
                TextColumn::make('languages')->label((string) __('cms.translation.label'))
                    ->state(fn (array $record) => array_map(fn (string $l) => $l.(in_array($l, $record['missing'], true) ? ':missing' : ':ok'), Tree::LOCALES))
                    ->formatStateUsing(fn (string $state) => strtoupper(explode(':', $state)[0]))
                    ->badge()
                    ->color(fn (string $state) => str_ends_with($state, ':missing') ? 'warning' : 'gray')
                    ->icon(fn (string $state) => str_ends_with($state, ':missing') ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check')
                    ->tooltip(fn (array $record) => $record['missing'] === [] ? (string) __('cms_site.seo.complete')
                        : (string) __('cms_site.seo.missing_in', ['languages' => implode(', ', array_map(CmsLabels::locale(...), $record['missing']))]))
                    ->extraAttributes(['class' => 'cms-translation-chips', 'dir' => 'ltr']),
            ])
            ->filters([
                SelectFilter::make('type')->label((string) __('cms_site.seo.type'))
                    ->options(fn () => array_map(fn (string $type) => self::typeLabel($type), array_combine(array_keys(self::MODELS), array_keys(self::MODELS)))),
                SelectFilter::make('locale')->label((string) __('cms_site.seo.language'))
                    ->options(array_combine(Tree::LOCALES, array_map(CmsLabels::locale(...), Tree::LOCALES)))
                    ->placeholder(CmsLabels::locale(self::adminLocale())),
                SelectFilter::make('missing')->label((string) __('cms_site.seo.missing_filter'))
                    ->options([
                        'any' => (string) __('cms_site.seo.missing_any'),
                        'title' => (string) __('cms_site.seo.missing_title'),
                        'description' => (string) __('cms_site.seo.missing_description'),
                    ]),
            ])
            ->recordActions([
                Action::make('editRecord')
                    ->label((string) __('cms_site.seo.edit'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (array $record) => $record['url'])
                    ->visible(fn (array $record) => $record['url'] !== null),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->emptyStateHeading((string) __('cms.empty.filtered'))
            ->extraAttributes(['class' => 'cms-table']);
    }

    /**
     * One row per record with SEO fields (not in the Trash), in the chosen language.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public static function rows(?string $search = null, array $filters = []): array
    {
        $type = $filters['type']['value'] ?? null;
        $locale = $filters['locale']['value'] ?? null;
        $locale = is_string($locale) && in_array($locale, Tree::LOCALES, true) ? $locale : self::adminLocale();
        $missing = $filters['missing']['value'] ?? null;
        $needle = is_string($search) && trim($search) !== '' ? mb_strtolower(trim($search)) : null;
        $rows = [];
        foreach (self::MODELS as $key => $class) {
            if (is_string($type) && $type !== '' && $type !== $key) {
                continue;
            }
            $resource = Filament::getModelResource($class);
            foreach ($class::query()->orderBy((new $class)->getKeyName())->get() as $record) {
                $row = self::row($record, $key, $locale, $resource);
                if ($missing === 'any' && $row['missing'] === []
                    || $missing === 'title' && $row['missing_title'] === []
                    || $missing === 'description' && $row['missing_description'] === []) {
                    continue;
                }
                if ($needle !== null && ! str_contains(mb_strtolower(implode(' ', [$row['code'], $row['label'], ...$row['texts']])), $needle)) {
                    continue;
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private static function row(Model $record, string $type, string $locale, ?string $resource): array
    {
        $working = Publisher::workingCopy($record);
        $title = (array) ($working['seo_title'] ?? []);
        $description = (array) ($working['meta_description'] ?? []);
        $blank = fn (mixed $v) => ! is_string($v) || trim($v) === '';
        $missingTitle = array_values(array_filter(Tree::LOCALES, fn (string $l) => $blank($title[$l] ?? null)));
        $missingDescription = array_values(array_filter(Tree::LOCALES, fn (string $l) => $blank($description[$l] ?? null)));
        $texts = [];
        foreach (Tree::LOCALES as $l) {
            $texts[] = (string) ($title[$l] ?? '');
            $texts[] = (string) ($description[$l] ?? '');
        }
        $url = null;
        if ($resource !== null && $resource::hasPage('edit') && $resource::canAccess()) {
            $url = $resource::getUrl('edit', ['record' => $record]);
        }

        return [
            '__key' => $type.':'.$record->getKey(),
            'type' => $type,
            'type_label' => self::typeLabel($type),
            'code' => (string) $record->getKey(),
            'label' => Registry::label($record, self::adminLocale()),
            'locale' => $locale,
            'seo_title' => $blank($title[$locale] ?? null) ? null : $title[$locale],
            'meta_description' => $blank($description[$locale] ?? null) ? null : $description[$locale],
            'missing_title' => $missingTitle,
            'missing_description' => $missingDescription,
            'missing' => array_values(array_unique([...$missingTitle, ...$missingDescription])),
            'texts' => $texts,
            'url' => $url,
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private static function paginate(array $rows, int $page, int $perPage): LengthAwarePaginator
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        return new LengthAwarePaginator(array_slice($rows, ($page - 1) * $perPage, $perPage), count($rows), $perPage, $page);
    }

    public static function typeLabel(string $type): string
    {
        return (string) __('cms_site.seo.types.'.$type);
    }

    private static function adminLocale(): string
    {
        return in_array(app()->getLocale(), Tree::LOCALES, true) ? app()->getLocale() : 'en';
    }
}
