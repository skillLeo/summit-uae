<?php

namespace App\Filament\Cms\Tables;

use App\Content\Cms\CopySource;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\Registry;
use App\Content\Cms\Editing\TranslationStatus;
use App\Content\Cms\Status;
use App\Content\Cms\Tree;
use App\Filament\Cms\Actions\CmsActions;
use App\Filament\Cms\Support\CmsLabels;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The shared parts of every CMS list (docs/briefs/design-2.1-and-admin-cms.md §8.3, §8.7, §9.2): the status, approval
 * (copy_source) and translation status (EN / AR / RU) columns, the filters "Status", "Pending client approval" (the
 * approval filter) and "Missing translations", the edit action, the bulk actions (publish, take off the site, Trash,
 * export), 25 / 50 / 100 rows and filters remembered per user (session).
 */
final class CmsTable
{
    /** @var array<string, array<string, list<string>>> class => locale => keys (one request) */
    private static array $missing = [];

    /**
     * @param  list<\Filament\Tables\Columns\Column>  $columns  the resource's own columns (shown first)
     * @param  list<\Filament\Tables\Filters\BaseFilter>  $filters
     * @param  callable(Model): array<string, string|null>|null  $export
     */
    public static function configure(Table $table, array $columns, array $filters = [], ?callable $export = null, string $exportName = 'content', bool $reorderable = false): Table
    {
        $table = $table
            ->columns([...$columns, ...self::columns()])
            ->filters([...$filters, ...self::filters()])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    CmsActions::bulkPublish(),
                    CmsActions::bulkUnpublish(),
                    CmsActions::bulkExport($exportName, $export ?? fn (Model $record) => self::exportRow($record)),
                    CmsActions::bulkDelete(),
                ]),
            ])
            ->emptyStateHeading((string) __('cms.empty.heading'))
            ->extraAttributes(['class' => 'cms-table']);
        if ($reorderable) {
            $table->reorderable('position')->defaultSort('position');
        }

        return $table;
    }

    /** @return list<TextColumn> */
    public static function columns(): array
    {
        return [
            self::statusColumn(),
            self::copyColumn(),
            self::translationColumn(),
            TextColumn::make('updated_at')
                ->label((string) __('cms.field.updated_at'))
                ->formatStateUsing(fn ($state) => CmsLabels::dubai($state))
                ->description(fn (Model $record) => $record->getAttribute('updated_by') !== null ? optional($record->updatedBy)->name : (string) __('cms.field.nobody'))
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label((string) __('cms.status.label'))
            ->state(fn (Model $record) => CmsLabels::state($record))
            ->formatStateUsing(fn (string $state) => CmsLabels::stateLabel($state))
            ->badge()
            ->color(fn (string $state) => CmsLabels::stateColor($state))
            ->icon(fn (string $state) => CmsLabels::stateIcon($state))
            ->toggleable();
    }

    public static function copyColumn(): TextColumn
    {
        return TextColumn::make('copy_source')
            ->label((string) __('cms.copy.label'))
            ->formatStateUsing(fn (?string $state) => CmsLabels::copy($state, short: true))
            ->tooltip(fn (Model $record) => CmsLabels::copy($record->getAttribute('copy_source')))
            ->badge()
            ->color(fn (?string $state) => CmsLabels::copyColor($state))
            ->icon(fn (?string $state) => CmsLabels::copyIcon($state))
            ->toggleable();
    }

    public static function translationColumn(): TextColumn
    {
        return TextColumn::make('cms_translations')
            ->label((string) __('cms.translation.label'))
            ->state(function (Model $record): array {
                $missing = TranslationStatus::missingLocales($record);

                return array_map(fn (string $l) => $l.(in_array($l, $missing, true) ? ':missing' : ':ok'), Tree::LOCALES);
            })
            ->formatStateUsing(fn (string $state) => strtoupper(explode(':', $state)[0]))
            ->badge()
            ->color(fn (string $state) => str_ends_with($state, ':missing') ? 'warning' : 'gray')
            ->icon(fn (string $state) => str_ends_with($state, ':missing') ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check')
            ->tooltip(function (Model $record) {
                $missing = TranslationStatus::missingLocales($record);

                return $missing === [] ? (string) __('cms.translation.complete')
                    : (string) __('cms.translation.missing_in', ['languages' => implode(', ', array_map(CmsLabels::locale(...), $missing))]);
            })
            ->extraAttributes(['class' => 'cms-translation-chips', 'dir' => 'ltr']);
    }

    /** @return list<SelectFilter> */
    public static function filters(): array
    {
        return [
            SelectFilter::make('cms_status')
                ->label((string) __('cms.status.label'))
                ->options([
                    'published' => CmsLabels::stateLabel('published'),
                    'changes' => CmsLabels::stateLabel('changes'),
                    'draft' => CmsLabels::stateLabel('draft'),
                    'scheduled' => CmsLabels::stateLabel('scheduled'),
                ])
                ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                    'published' => $query->where('status', Status::PUBLISHED)->whereNull('draft'),
                    'changes' => $query->where('status', Status::PUBLISHED)->whereNotNull('draft'),
                    'draft' => $query->where('status', Status::DRAFT),
                    'scheduled' => $query->whereNotNull('publish_at'),
                    default => $query,
                }),
            SelectFilter::make('copy_source')
                ->label((string) __('cms.copy.label'))
                ->options(CmsLabels::copyOptions()),
            SelectFilter::make('cms_missing')
                ->label((string) __('cms.translation.filter'))
                ->options(['any' => (string) __('cms.translation.filter_any')] + array_combine(Tree::LOCALES, array_map(CmsLabels::locale(...), Tree::LOCALES)))
                ->query(function (Builder $query, array $data) {
                    $locale = $data['value'] ?? null;
                    if ($locale === null || $locale === '') {
                        return $query;
                    }
                    $model = $query->getModel();

                    return $query->whereIn($model->getQualifiedKeyName(), self::missingKeys($model::class, $locale));
                }),
        ];
    }

    /** The pending filter preset (brief §8.7 "Pending client approval"): copy_source = pending. */
    public static function isPending(Builder $query): Builder
    {
        return $query->where('copy_source', CopySource::PENDING);
    }

    /**
     * Keys of the records (not in the Trash) with a missing translation in $locale ("any": in any language).
     *
     * @param  class-string<Model>  $class
     * @return list<string|int>
     */
    public static function missingKeys(string $class, string $locale): array
    {
        if (! isset(self::$missing[$class])) {
            $index = ['any' => []] + array_fill_keys(Tree::LOCALES, []);
            $class::query()->chunk(200, function ($records) use (&$index) {
                foreach ($records as $record) {
                    $missing = TranslationStatus::missingLocales($record);
                    foreach ($missing as $l) {
                        $index[$l][] = $record->getKey();
                    }
                    if ($missing !== []) {
                        $index['any'][] = $record->getKey();
                    }
                }
            });
            self::$missing[$class] = $index;
        }

        return self::$missing[$class][$locale] ?? [];
    }

    public static function forgetMissing(): void
    {
        self::$missing = [];
    }

    /** @return array<string, string|null> key, status, approval, then every translatable text per language */
    public static function exportRow(Model $record): array
    {
        $row = [
            'key' => (string) $record->getKey(),
            'status' => CmsLabels::state($record),
            'copy_source' => (string) $record->getAttribute('copy_source'),
        ];
        $working = Publisher::workingCopy($record);
        foreach (Registry::fields($record) as $column => $kind) {
            if ($kind !== Registry::TRANSLATABLE) {
                continue;
            }
            foreach (Tree::LOCALES as $locale) {
                $value = $working[$column][$locale] ?? null;
                $row[$column.'_'.$locale] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            }
        }

        return $row;
    }
}
