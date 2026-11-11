<?php

namespace App\Filament\Resources\Enquiries\Tables;

use App\Filament\Resources\Enquiries\EnquiryActions;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Support\AdminBidi;
use App\Filament\Support\AdminFormat;
use App\Filament\Support\EnquiryLabels;
use App\Filament\Support\EnquiryStats;
use App\Filament\Support\EnquiryStatus;
use App\Models\Enquiry;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * The request list: comfortable rows, a sticky header (theme.css), status badges in the semantic
 * colours, and filters for status, service, site language and the date received (Dubai time).
 * The summary is never listed: it is decrypted only on the request's own page.
 */
final class EnquiriesTable
{
    public static function configure(Table $table): Table
    {
        $zone = EnquiryStats::timezone();

        return $table
            ->defaultSort('created_at', 'desc')
            ->extraAttributes(['class' => 'slc-requests-table'])
            ->columns([
                TextColumn::make('reference')
                    ->label(__('admin.field.reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::ltr($state))
                    ->searchable()
                    ->copyable()
                    ->copyableState(fn (Enquiry $record): string => $record->reference),
                TextColumn::make('created_at')
                    ->label(__('admin.field.received'))
                    ->date(AdminFormat::shortDate(), $zone)
                    ->description(fn (Enquiry $record): string => $record->created_at->copy()->setTimezone($zone)->format(AdminFormat::time()))
                    ->sinceTooltip()
                    ->sortable(),
                // name and contact isolated inline (AdminBidi), so they start under the header in either
                // interface direction; a long one is cut at its own end (theme.css `.fi-ta-cell-name bdi`)
                TextColumn::make('name')
                    ->label(__('admin.field.name'))
                    ->tooltip(fn (Enquiry $record): string => $record->name)
                    ->searchable()
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::auto($state))
                    ->description(fn (Enquiry $record): HtmlString => AdminBidi::ltr($record->email ?: $record->phone))
                    ->extraAttributes(['class' => 'slc-person']),
                TextColumn::make('matter_type')
                    ->label(__('admin.field.service'))
                    ->formatStateUsing(fn (?string $state): string => EnquiryLabels::matter($state))
                    ->placeholder(__('admin.value.not_chosen'))
                    ->wrap(),
                TextColumn::make('locale')
                    ->label(__('admin.by_language.language'))
                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::ltr(strtoupper((string) $state)))
                    ->tooltip(fn (Enquiry $record): string => __('admin.field.site_language').': '.EnquiryLabels::language($record->locale)
                        .(filled($record->preferred_language) ? ' · '.__('admin.field.preferred_language').': '.EnquiryLabels::language($record->preferred_language) : ''))
                    ->fontFamily(FontFamily::Mono)
                    ->toggleable(),
                TextColumn::make('deadline_date')
                    ->label(__('admin.field.deadline_date'))
                    ->date(AdminFormat::shortDate())
                    ->placeholder(__('admin.value.none'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('attachment_path')
                    ->label(__('admin.field.attachment'))
                    ->icon(fn (?string $state) => filled($state) ? Heroicon::OutlinedPaperClip : null)
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('admin.field.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EnquiryStatus::label($state))
                    ->color(fn (?string $state): string => EnquiryStatus::color($state))
                    ->icon(fn (?string $state) => EnquiryStatus::icon($state))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.filter.status'))
                    ->options(EnquiryStatus::options())
                    ->multiple(),
                SelectFilter::make('matter_type')
                    ->label(__('admin.filter.service'))
                    ->options(EnquiryLabels::matterOptions())
                    ->multiple(),
                SelectFilter::make('locale')
                    ->label(__('admin.filter.language'))
                    ->options(EnquiryLabels::languageOptions())
                    ->multiple(),
                Filter::make('received')
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('admin.filter.received_from'))
                            ->placeholder(__('admin.filter.any_date'))
                            ->native(false)
                            ->displayFormat('j M Y')
                            ->closeOnDateSelection()
                            ->maxDate(now($zone)),
                        DatePicker::make('until')
                            ->label(__('admin.filter.received_until'))
                            ->placeholder(__('admin.filter.any_date'))
                            ->native(false)
                            ->displayFormat('j M Y')
                            ->closeOnDateSelection()
                            ->maxDate(now($zone)),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data) use ($zone): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->where('created_at', '>=', Carbon::parse($date, $zone)->startOfDay()->utc()))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->where('created_at', '<=', Carbon::parse($date, $zone)->endOfDay()->utc()));
                    })
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
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(['default' => 1, 'md' => 2, 'xl' => 5])
            ->persistFiltersInSession()
            ->recordActions([
                // The whole row opens the request (recordUrl); review and status changes sit in one menu.
                ActionGroup::make([
                    EditAction::make(),
                    ActionGroup::make(EnquiryActions::transitions())->dropdown(false),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->label(__('admin.action.menu'))
                    ->tooltip(__('admin.action.menu'))
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    EnquiryActions::bulkStatus(),
                ]),
            ])
            ->recordUrl(fn (Enquiry $record): string => EnquiryResource::getUrl('view', ['record' => $record]))
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon(Heroicon::OutlinedInbox)
            ->emptyStateHeading(__('admin.empty.heading'))
            ->emptyStateDescription(__('admin.empty.description'));
    }
}
