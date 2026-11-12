<?php

namespace App\Filament\Cms\Pages\System;

use App\Filament\Cms\Support\CmsAccess;
use App\Models\User;
use App\Requests\RequestExport;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Activity Log (docs/briefs/design-2.1-and-admin-cms.md §12.2, §12.4; Phase 7): every change of a content record, a
 * request's workflow, a user, every sign-in and every privacy action or export (spatie/laravel-activitylog, written by
 * App\Support\ActivityLogging and App\Support\Audit). Read-only. Filters by user, record type, action and date (Dubai
 * days); "View changes" shows the fields before and after; CSV export of the filtered list (activity.export).
 */
class ActivityLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'system/activity';

    protected string $view = 'filament.cms.table-page';

    public static function canAccess(): bool
    {
        return CmsAccess::can('activity', 'view');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('system.activity.title');
    }

    public function getTitle(): string
    {
        return (string) __('system.activity.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('system.activity.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label((string) __('system.activity.export'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => CmsAccess::can('activity', 'export'))
                ->authorize(fn (): bool => CmsAccess::can('activity', 'export'))
                ->action(fn (): StreamedResponse => $this->export()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Activity::query()->with(['causer', 'subject']))
            ->columns([
                TextColumn::make('created_at')
                    ->label((string) __('system.activity.column.time'))
                    ->since(SystemLabels::TIMEZONE)
                    ->dateTimeTooltip('j M Y, H:i:s', SystemLabels::TIMEZONE)
                    // below md the user sits under the time (the User column shows from md up)
                    ->description(fn (Activity $record): HtmlString => new HtmlString('<span class="slc-system-below-md">'.e(SystemLabels::causer($record)).'</span>'))
                    ->sortable(),
                TextColumn::make('causer')
                    ->label((string) __('system.activity.column.user'))
                    ->state(fn (Activity $record): string => SystemLabels::causer($record))
                    ->visibleFrom('md'),
                TextColumn::make('event')
                    ->label((string) __('system.activity.column.action'))
                    ->badge()
                    ->color(fn (Activity $record): string => SystemLabels::eventColor((string) $record->event))
                    ->formatStateUsing(fn (?string $state): string => SystemLabels::event((string) $state)),
                TextColumn::make('subject_type')
                    ->label((string) __('system.activity.column.type'))
                    ->formatStateUsing(fn (?string $state): string => SystemLabels::type($state))
                    ->placeholder((string) __('system.activity.no_record'))
                    ->visibleFrom('lg'),
                TextColumn::make('record')
                    ->label((string) __('system.activity.column.record'))
                    ->state(fn (Activity $record): ?string => SystemLabels::record($record))
                    // below lg the record type sits under the record (the Record type column shows from lg up)
                    ->description(fn (Activity $record): ?HtmlString => $record->subject_type === null ? null
                        : new HtmlString('<span class="slc-system-below-lg">'.e(SystemLabels::type($record->subject_type)).'</span>'))
                    ->wrap(),
                TextColumn::make('summary')
                    ->label((string) __('system.activity.column.description'))
                    ->state(fn (Activity $record): string => SystemLabels::summary($record))
                    ->visibleFrom('md')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('causer')
                    ->label((string) __('system.activity.filter.user'))
                    ->options(fn (): array => SystemLabels::causerOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if ($value === null || $value === '') {
                            return $query;
                        }

                        return $value === SystemLabels::SYSTEM
                            ? $query->whereNull('causer_id')
                            : $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', (string) $value);
                    }),
                SelectFilter::make('subject_type')
                    ->label((string) __('system.activity.filter.type'))
                    ->options(fn (): array => SystemLabels::typeOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if ($value === null || $value === '') {
                            return $query;
                        }

                        return $value === SystemLabels::SYSTEM ? $query->whereNull('subject_type') : $query->where('subject_type', $value);
                    }),
                SelectFilter::make('event')
                    ->label((string) __('system.activity.filter.action'))
                    ->options(fn (): array => SystemLabels::eventOptions()),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label((string) __('system.activity.filter.from'))->native(false)->displayFormat('j M Y')->maxDate(now(SystemLabels::TIMEZONE)->endOfDay()),
                        DatePicker::make('until')->label((string) __('system.activity.filter.until'))->native(false)->displayFormat('j M Y')->maxDate(now(SystemLabels::TIMEZONE)->endOfDay()),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(fn (Builder $query, array $data): Builder => self::dateRange($query, $data['from'] ?? null, $data['until'] ?? null))
                    ->indicateUsing(function (array $data): array {
                        $out = [];
                        if (filled($data['from'] ?? null)) {
                            $out[] = (string) __('system.activity.filter.from').' '.Carbon::parse($data['from'])->translatedFormat('j M Y');
                        }
                        if (filled($data['until'] ?? null)) {
                            $out[] = (string) __('system.activity.filter.until').' '.Carbon::parse($data['until'])->translatedFormat('j M Y');
                        }

                        return $out;
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(['default' => 1, 'md' => 2, 'xl' => 5])
            ->deferFilters(false)
            ->recordActions([
                Action::make('changes')
                    ->label((string) __('system.activity.view_changes'))
                    ->visible(fn (Activity $record): bool => SystemLabels::diff($record) !== [] || SystemLabels::properties($record) !== [])
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->color('gray')
                    ->labeledFrom('md')
                    ->modalHeading(fn (Activity $record): HtmlString => new HtmlString(e((string) __('system.activity.heading', [
                        'action' => SystemLabels::event((string) $record->event),
                        'record' => "\u{2068}".(SystemLabels::record($record) ?? SystemLabels::type($record->subject_type))."\u{2069}",
                    ]))))
                    ->modalDescription(fn (Activity $record): string => (string) __('system.activity.by_at', [
                        'user' => "\u{2068}".SystemLabels::causer($record)."\u{2069}",
                        'time' => "\u{2068}".SystemLabels::dubai($record->created_at, 'j M Y, H:i:s')."\u{2069}",
                    ]))
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel((string) __('system.close'))
                    ->modalContent(fn (Activity $record) => view('filament.system.activity-changes', [
                        'rows' => SystemLabels::diff($record),
                        'properties' => SystemLabels::properties($record),
                    ])),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading((string) __('system.activity.empty'))
            ->emptyStateDescription((string) __('system.activity.empty_body'));
    }

    /** A Dubai day range on the UTC times of the log. */
    public static function dateRange(Builder $query, ?string $from, ?string $until): Builder
    {
        if (filled($from)) {
            $query->where('created_at', '>=', Carbon::parse($from, SystemLabels::TIMEZONE)->startOfDay()->utc());
        }
        if (filled($until)) {
            $query->where('created_at', '<=', Carbon::parse($until, SystemLabels::TIMEZONE)->endOfDay()->utc());
        }

        return $query;
    }

    /** CSV of the filtered, sorted list, in the admin's language; every text cell guarded against formulas. */
    public function export(): StreamedResponse
    {
        abort_unless(CmsAccess::can('activity', 'export'), 403);
        $records = $this->getFilteredSortedTableQuery()->limit(10000)->get();
        $headings = [
            (string) __('system.activity.column.time'),
            (string) __('system.activity.column.user'),
            (string) __('system.activity.column.action'),
            (string) __('system.activity.column.type'),
            (string) __('system.activity.column.record'),
            (string) __('system.activity.column.description'),
            (string) __('system.activity.column.changes'),
        ];
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                SystemLabels::dubai($record->created_at, 'Y-m-d H:i:s'),
                SystemLabels::causer($record),
                SystemLabels::event((string) $record->event),
                $record->subject_type === null ? '' : SystemLabels::type($record->subject_type),
                (string) SystemLabels::record($record),
                SystemLabels::summary($record),
                SystemLabels::diffText($record),
            ];
        }
        Audit::log('activity', 'export', null, ['format' => 'csv', 'rows' => count($rows)]);
        $name = 'activity-'.now(SystemLabels::TIMEZONE)->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($headings, $rows): void {
            echo RequestExport::csv($headings, $rows);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
