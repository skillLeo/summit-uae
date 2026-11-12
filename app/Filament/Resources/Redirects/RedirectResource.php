<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Cms\Support\CmsLabels;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Models\Redirect;
use App\Providers\Filament\AdminPanelProvider;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Redirects (docs/briefs/design-2.1-and-admin-cms.md §8.2 "SEO: redirects", §8.3, §8.11): from a path of this site to
 * another path or a full address, permanent (301) or temporary (302). App\Http\Middleware\ServeRedirects answers them
 * before routing; changing a page or practice-area slug creates one automatically (App\Content\Cms\Editing\Slugs),
 * listed here with the ones made by hand.
 *
 * Not a CMS record: no drafts, no translations, no revisions. A change takes effect at once (the model replaces the
 * cached redirect map) and delete is permanent. Rules: from starts with "/", is unique and is not an admin path; to is
 * a path or an http(s) address and differs from from; no chains: to is never another redirect's from, and from is
 * never another redirect's to (Slugs keeps its own redirects chain-free the same way).
 */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static ?string $slug = 'redirects';

    protected static string|UnitEnum|null $navigationGroup = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 41;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string
    {
        return (string) __('cms_site.redirects.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('cms_site.redirects.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationParentItem(): ?string
    {
        return (string) __('cms_site.seo.title');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Redirect ? (string) $record->from_path : static::getModelLabel();
    }

    public static function screen(): string
    {
        return 'redirects';
    }

    /** The permission matrix answers every authorization question (App\Filament\Cms\CmsResource). */
    public static function getAuthorizationResponse(string|\UnitEnum $action, ?Model $record = null): \Illuminate\Auth\Access\Response
    {
        $action = $action instanceof \UnitEnum ? $action->name : $action;
        $ability = \App\Filament\Cms\CmsResource::ABILITY[$action] ?? $action;

        return CmsAccess::can('redirects', $ability)
            ? \Illuminate\Auth\Access\Response::allow()
            : \Illuminate\Auth\Access\Response::deny((string) __('cms.access.denied'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('from_path')
                        ->label((string) __('cms_site.redirects.from'))
                        ->helperText((string) __('cms_site.redirects.from_hint'))
                        ->required()
                        ->maxLength(255)
                        ->dehydrateStateUsing(fn (?string $state) => self::clean($state))
                        ->rule(fn (?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            $error = self::fromError(self::clean(is_string($value) ? $value : null), $record?->getKey());
                            if ($error !== null) {
                                $fail((string) __('cms_site.redirects.errors.'.$error));
                            }
                        })
                        ->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('to_path')
                        ->label((string) __('cms_site.redirects.to'))
                        ->helperText((string) __('cms_site.redirects.to_hint'))
                        ->required()
                        ->maxLength(255)
                        ->dehydrateStateUsing(fn (?string $state) => self::clean($state))
                        ->rule(fn (Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $error = self::toError(self::clean(is_string($value) ? $value : null), self::clean($get('from_path')), $record?->getKey());
                            if ($error !== null) {
                                $fail((string) __('cms_site.redirects.errors.'.$error));
                            }
                        })
                        ->extraInputAttributes(['dir' => 'ltr']),
                    Select::make('code')
                        ->label((string) __('cms_site.redirects.code'))
                        ->options(self::codeOptions())
                        ->default(301)
                        ->required()
                        ->in([301, 302])
                        ->selectablePlaceholder(false),
                    Toggle::make('enabled')->label((string) __('cms_site.redirects.enabled'))->default(true)->inline(false),
                ]),
                Text::make(fn (?Model $record) => $record instanceof Redirect
                    ? trans_choice('cms_site.redirects.hits_line', (int) $record->hits, [
                        'count' => (int) $record->hits,
                        'date' => $record->last_hit_at !== null ? CmsLabels::dubai($record->last_hit_at) : (string) __('cms.field.never'),
                    ])
                    : null)
                    ->color('gray')->size('sm')
                    ->visible(fn (?Model $record) => $record instanceof Redirect),
            ]),
        ]);
    }

    /** @return array<int, string> */
    public static function codeOptions(): array
    {
        return [301 => (string) __('cms_site.redirects.code_301'), 302 => (string) __('cms_site.redirects.code_302')];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')->label((string) __('cms_site.redirects.from'))
                    ->searchable()->sortable()->wrap()->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('to_path')->label((string) __('cms_site.redirects.to'))
                    ->searchable()->sortable()->wrap()->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('code')->label((string) __('cms_site.redirects.code'))
                    ->badge()->color(fn (int $state) => $state === 301 ? 'gray' : 'info')
                    ->icon(fn (int $state) => $state === 301 ? Heroicon::OutlinedArrowRight : Heroicon::OutlinedClock)
                    ->formatStateUsing(fn (int $state) => (string) __('cms_site.redirects.code_short_'.$state))
                    ->sortable(),
                IconColumn::make('enabled')->label((string) __('cms_site.redirects.enabled'))->boolean()->sortable(),
                TextColumn::make('hits')->label((string) __('cms_site.redirects.hits'))->numeric()->sortable(),
                TextColumn::make('last_hit_at')->label((string) __('cms_site.redirects.last_hit'))
                    ->formatStateUsing(fn ($state) => CmsLabels::dubai($state))
                    ->placeholder((string) __('cms.field.never'))
                    ->sortable()->toggleable(),
                TextColumn::make('updated_at')->label((string) __('cms.field.updated_at'))
                    ->formatStateUsing(fn ($state) => CmsLabels::dubai($state))
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('code')->label((string) __('cms_site.redirects.code'))->options(self::codeOptions()),
                TernaryFilter::make('enabled')->label((string) __('cms_site.redirects.enabled')),
            ])
            ->defaultSort('from_path')
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription((string) __('cms_site.redirects.delete_body')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->modalDescription((string) __('cms_site.redirects.delete_body')),
                ]),
            ])
            ->emptyStateHeading((string) __('cms_site.redirects.empty'))
            ->emptyStateDescription((string) __('cms_site.redirects.empty_body'))
            ->extraAttributes(['class' => 'cms-table']);
    }

    /** A path as stored: trimmed, no host for this site's own addresses. */
    public static function clean(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }
        $path = trim($path);

        return $path === '' ? null : $path;
    }

    /** The error code of a from path, or null. */
    public static function fromError(?string $from, mixed $ignoreId = null): ?string
    {
        if ($from === null) {
            return 'required';
        }
        if (preg_match('#^/(?!/)[^\s?\#]*$#', $from) !== 1) {
            return 'from_format';
        }
        $admin = '/'.trim(AdminPanelProvider::PATH, '/');
        foreach ([$admin, '/livewire', '/build', '/build-d2', '/build-admin', '/storage'] as $reserved) {
            if ($from === $reserved || str_starts_with($from, $reserved.'/')) {
                return 'from_reserved';
            }
        }
        $others = Redirect::query()->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId));
        if ((clone $others)->whereIn('from_path', self::variants($from))->exists()) {
            return 'from_taken';
        }
        if ((clone $others)->whereIn('to_path', self::variants($from))->exists()) {
            return 'chain_from';
        }

        return null;
    }

    /** The error code of a to path, or null. */
    public static function toError(?string $to, ?string $from, mixed $ignoreId = null): ?string
    {
        if ($to === null) {
            return 'required';
        }
        $isPath = preg_match('#^/(?!/)[^\s]*$#', $to) === 1;
        $isUrl = preg_match('#^https?://#i', $to) === 1 && filter_var($to, FILTER_VALIDATE_URL) !== false;
        if (! $isPath && ! $isUrl) {
            return 'to_format';
        }
        if ($from !== null && in_array($to, self::variants($from), true)) {
            return 'same';
        }
        $others = Redirect::query()->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId));
        if ($isPath && $others->whereIn('from_path', self::variants((string) strtok($to, '?#')))->exists()) {
            return 'chain_to';
        }

        return null;
    }

    /** "/a/b" and "/a/b/": ServeRedirects matches a path with and without its trailing slash. */
    public static function variants(string $path): array
    {
        $bare = rtrim($path, '/');

        return array_values(array_unique([$path, $bare === '' ? '/' : $bare, $bare.'/']));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }
}
