<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Support\AdminLocale;
use App\Filament\Support\EnquiryStats;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Received in the last 7 and 30 days (with the previous period and a daily sparkline), awaiting review,
 * and the draft-to-submitted ratio, which is honestly "Not tracked": drafts stay in the visitor's
 * browser by design (nothing leaves the page before submission), so there is nothing to count.
 * The sparklines sit inside the card padding, at the foot of the card (`.slc-stat-trend` in theme.css).
 */
class RequestsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|array|null $columns = ['default' => 1, 'md' => 2, 'xl' => 4];

    protected function getStats(): array
    {
        $week = EnquiryStats::countSince(7);
        $month = EnquiryStats::countSince(30);

        return [
            Stat::make(__('admin.stats.week'), $week)
                ->description(__('admin.stats.week_previous', ['count' => EnquiryStats::countSince(7, 7)]))
                ->chart(EnquiryStats::daily(7))
                ->color('primary')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->extraAttributes(['class' => 'slc-stat-trend']),
            Stat::make(__('admin.stats.month'), $month)
                ->description(__('admin.stats.month_previous', ['count' => EnquiryStats::countSince(30, 30)]))
                ->chart(EnquiryStats::daily(30))
                ->color('primary')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->extraAttributes(['class' => 'slc-stat-trend']),
            Stat::make(__('admin.stats.awaiting'), EnquiryStats::awaitingReview())
                ->description(__('admin.stats.awaiting_hint'))
                ->descriptionIcon(AdminLocale::isRtl() ? Heroicon::OutlinedArrowLeft : Heroicon::OutlinedArrowRight)
                ->color('warning')
                ->icon(Heroicon::OutlinedClock)
                ->url(EnquiryResource::getUrl('index', ['filters' => ['status' => ['values' => ['new']]]])),
            Stat::make(__('admin.stats.ratio'), __('admin.stats.ratio_value'))
                ->description(__('admin.stats.ratio_hint'))
                ->color('gray')
                ->icon(Heroicon::OutlinedLockClosed)
                ->extraAttributes(['class' => 'slc-stat-untracked']),
        ];
    }
}
