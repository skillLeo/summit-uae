<?php

namespace App\Filament\Resources\Enquiries\Tables;

use App\Filament\Resources\Enquiries\EnquiryActions;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Support\AdminBidi;
use App\Filament\Support\AdminFormat;
use App\Filament\Support\EnquiryLabels;
use App\Filament\Support\EnquiryStats;
use App\Filament\Support\EnquiryStatus;
use App\Filament\Support\RequestFormat;
use App\Models\Enquiry;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * The requests inbox (docs/briefs/design-2.1-and-admin-cms.md §10.3): tabs with counts (ListEnquiries), the columns
 * Reference (tabular figures, never monospace), Name, Matter type, Language (site and preferred), Contact given,
 * Received (relative, exact Dubai time in the tooltip), Waiting (business hours; a warning badge near and after the
 * internal target), Status (icon and label) and Assignee. Bulk actions: assign, change status, mark as spam, export
 * CSV or XLSX. Stacks into cards on a phone. The summary is never listed.
 */
final class EnquiriesTable
{
    public static function configure(Table $table): Table
    {
        $zone = EnquiryStats::timezone();

        return $table
            ->defaultSort('received_at', 'desc')
            ->extraAttributes(['class' => 'slc-requests-table', 'data-inbox' => 'list'])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('assignee'))
            ->stackedOnMobile()
            ->columns([
                TextColumn::make('reference')
                    ->label(__('requests.column.reference'))
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::ltr($state))
                    ->extraAttributes(['class' => 'slc-tabular'])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('requests.column.name_contact'))
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::auto($state))
                    ->tooltip(fn (Enquiry $record): string => $record->name)
                    ->description(fn (Enquiry $record): string => RequestFormat::contactGiven($record))
                    ->searchable(['name', 'email', 'phone'])
                    ->extraAttributes(['class' => 'slc-person']),
                TextColumn::make('matter_type')
                    ->label(__('requests.column.matter'))
                    ->formatStateUsing(fn (?string $state): string => EnquiryLabels::matter($state))
                    ->placeholder(__('admin.value.not_chosen'))
                    ->wrap(),
                TextColumn::make('locale')
                    ->label(__('requests.column.language'))
                    ->formatStateUsing(fn (Enquiry $record): string => RequestFormat::languages($record))
                    ->description(fn (Enquiry $record): ?string => filled($record->preferred_language) ? RequestFormat::preferred($record) : null)
                    ->toggleable(),
                TextColumn::make('received_at')
                    ->label(__('requests.column.received'))
                    ->formatStateUsing(fn (Enquiry $record): string => RequestFormat::received($record->received_at ?? $record->created_at))
                    ->tooltip(fn (Enquiry $record): string => RequestFormat::exact($record->received_at ?? $record->created_at))
                    ->sortable(),
                TextColumn::make('waiting')
                    ->label(__('requests.column.waiting'))
                    ->state(fn (Enquiry $record): string => RequestFormat::waitingLabel($record))
                    ->badge(fn (Enquiry $record): bool => RequestFormat::targetState($record) === 'over')
                    ->color(fn (Enquiry $record): string => RequestFormat::waitingColor($record))
                    ->icon(fn (Enquiry $record): ?string => RequestFormat::waitingIcon($record))
                    ->tooltip(fn (Enquiry $record): string => RequestFormat::waitingTooltip($record))
                    ->extraAttributes(['class' => 'slc-tabular']),
                TextColumn::make('status')
                    ->label(__('requests.column.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EnquiryStatus::label($state))
                    ->color(fn (?string $state): string => EnquiryStatus::color($state))
                    ->icon(fn (?string $state) => EnquiryStatus::icon($state))
                    ->description(fn (Enquiry $record): ?string => RequestFormat::outcome($record->outcome))
                    ->sortable(),
                TextColumn::make('assignee.name')
                    ->label(__('requests.column.assignee'))
                    ->state(fn (Enquiry $record): string => RequestFormat::assignee($record->assignee))
                    ->color(fn (Enquiry $record): ?string => $record->assignee_id === null ? 'gray' : null)
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('matter_type')
                    ->label(__('admin.filter.service'))
                    ->options(EnquiryLabels::matterOptions())
                    ->multiple(),
                SelectFilter::make('locale')
                    ->label(__('admin.filter.language'))
                    ->options(EnquiryLabels::languageOptions())
                    ->multiple(),
                SelectFilter::make('assignee_id')
                    ->label(__('requests.filter.assignee'))
                    ->options(fn (): array => EnquiryActions::staffOptions()),
                Filter::make('received')
                    ->schema([
                        DatePicker::make('from')->label(__('admin.filter.received_from'))->placeholder(__('admin.filter.any_date'))
                            ->native(false)->displayFormat('j M Y')->closeOnDateSelection()->maxDate(now($zone)),
                        DatePicker::make('until')->label(__('admin.filter.received_until'))->placeholder(__('admin.filter.any_date'))
                            ->native(false)->displayFormat('j M Y')->closeOnDateSelection()->maxDate(now($zone)),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->where('received_at', '>=', Carbon::parse($date, $zone)->startOfDay()->utc()))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->where('received_at', '<', Carbon::parse($date, $zone)->addDay()->startOfDay()->utc())))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make(__('admin.filter.from_indicator', ['date' => Carbon::parse($data['from'])->translatedFormat(AdminFormat::shortDate())]))->removeField('from');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make(__('admin.filter.until_indicator', ['date' => Carbon::parse($data['until'])->translatedFormat(AdminFormat::shortDate())]))->removeField('until');
                        }

                        return $indicators;
                    }),
                TernaryFilter::make('is_demo')
                    ->label(__('requests.filter.demo'))
                    ->trueLabel(__('requests.filter.demo_only'))
                    ->falseLabel(__('requests.filter.demo_none'))
                    ->visible(fn (): bool => Enquiry::query()->where('is_demo', true)->exists()),
            ], layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(['default' => 1, 'md' => 2])
            ->filtersFormWidth('2xl')
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->recordActions([
                ActionGroup::make([
                    EnquiryActions::assignMe(),
                    ActionGroup::make(EnquiryActions::transitions())->dropdown(false),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->label(__('admin.action.menu'))
                    ->tooltip(__('admin.action.menu'))
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make(EnquiryActions::bulk()),
            ])
            ->recordUrl(fn (Enquiry $record): string => EnquiryResource::getUrl('view', ['record' => $record]))
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon(Heroicon::OutlinedInbox)
            ->emptyStateHeading(__('requests.empty.heading'))
            ->emptyStateDescription(__('requests.empty.description'));
    }
}
