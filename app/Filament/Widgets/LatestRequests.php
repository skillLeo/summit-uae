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
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\HtmlString;

/**
 * Dashboard row 3 (docs/briefs/design-2.1-and-admin-cms.md §11.1): the eight most recent requests (spam excluded):
 * reference with the matter type under it, received (relative, the exact Dubai time in the tooltip) and status. It
 * shares a row with Content status, so the matter type wraps (never the reference).
 */
class LatestRequests extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return \App\Filament\Cms\Support\CmsAccess::can('requests', 'view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('dashboard.latest.heading'))
            ->extraAttributes(['class' => 'slc-latest-table'])
            ->query(fn () => \App\Filament\Cms\Support\CmsAccess::scopeRequests(Enquiry::query()->counted())->latest('received_at')->limit(8))
            ->paginated(false)
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label(__('admin.field.reference'))
                    ->weight(FontWeight::Medium)
                    ->extraAttributes(['class' => 'slc-tabular'])
                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::ltr($state))
                    ->description(fn (Enquiry $record): string => EnquiryLabels::matter($record->matter_type)),
                TextColumn::make('received_at')
                    ->label(__('requests.column.received'))
                    ->formatStateUsing(fn (Enquiry $record): string => \App\Filament\Support\RequestFormat::received($record->received_at ?? $record->created_at))
                    ->tooltip(fn (Enquiry $record): string => \App\Filament\Support\RequestFormat::exact($record->received_at ?? $record->created_at)),
                TextColumn::make('status')
                    ->label(__('admin.field.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EnquiryStatus::label($state))
                    ->color(fn (?string $state): string => EnquiryStatus::color($state))
                    ->icon(fn (?string $state) => EnquiryStatus::icon($state)),
            ])
            ->recordUrl(fn (Enquiry $record): ?string => \App\Filament\Cms\Support\CmsAccess::readOnly() ? null : EnquiryResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('all')
                    ->label(__('dashboard.latest.all'))
                    ->url(EnquiryResource::getUrl('index'))
                    ->link(),
            ])
            ->emptyStateHeading(__('admin.empty.heading'))
            ->emptyStateDescription(__('admin.empty.description'));
    }
}
