<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Support\AdminBidi;
use App\Filament\Support\AdminFormat;
use App\Filament\Support\EnquiryLabels;
use App\Filament\Support\EnquiryStats;
use App\Filament\Support\EnquiryStatus;
use App\Models\Enquiry;
use Filament\Actions\Action;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\HtmlString;

/**
 * The five most recent requests (spam excluded): reference with the service under it, received (date, the
 * Dubai time under it, as in the request list) and status. It shares a row with "Requests by language"
 * from 1280 px, so it must fit half the content width in every language: the service wraps (never the
 * reference), the date and time take two short lines (`.slc-latest-table` in theme.css).
 */
class LatestRequests extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.latest.heading'))
            ->extraAttributes(['class' => 'slc-latest-table'])
            ->query(fn () => EnquiryStats::received()->latest()->limit(5))
            ->paginated(false)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label(__('admin.field.reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::ltr($state))
                    ->description(fn (Enquiry $record): string => EnquiryLabels::matter($record->matter_type)),
                TextColumn::make('created_at')
                    ->label(__('admin.field.received'))
                    ->date(AdminFormat::shortDate(), EnquiryStats::timezone())
                    ->description(fn (Enquiry $record): string => $record->created_at->copy()->setTimezone(EnquiryStats::timezone())->format(AdminFormat::time()))
                    ->sinceTooltip(),
                TextColumn::make('status')
                    ->label(__('admin.field.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EnquiryStatus::label($state))
                    ->color(fn (?string $state): string => EnquiryStatus::color($state))
                    ->icon(fn (?string $state) => EnquiryStatus::icon($state)),
            ])
            ->recordUrl(fn (Enquiry $record): string => EnquiryResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('all')
                    ->label(__('admin.latest.all'))
                    ->url(EnquiryResource::getUrl('index'))
                    ->link(),
            ])
            ->emptyStateHeading(__('admin.empty.heading'))
            ->emptyStateDescription(__('admin.empty.description'));
    }
}
