<?php

namespace App\Filament\Pages;

use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Widgets\ContentStatus;
use App\Filament\Widgets\KpiTiles;
use App\Filament\Widgets\LatestRequests;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\RequestsChart;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The dashboard: today's work, not analytics (docs/briefs/design-2.1-and-admin-cms.md §11.1, D50).
 * Row 1: four KPI tiles. Row 2: "Requests, last 30 days" and "Needs attention". Row 3: latest requests and Content
 * status. It adapts to the role: whoever edits content but does not handle requests sees Content status first; a
 * widget the user may not see is left out (no placeholders, no filler tiles). Analysis lives in Reports.
 */
class Dashboard extends BaseDashboard
{
    /** Brief §9.1 group "Overview" (AdminPanelProvider::navigationGroups()). */
    protected static string|\UnitEnum|null $navigationGroup = 'overview';

    public static function getNavigationLabel(): string
    {
        return (string) __('dashboard.title');
    }

    public function getTitle(): string|Htmlable
    {
        return (string) __('dashboard.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('dashboard.subheading');
    }

    public function getWidgets(): array
    {
        $widgets = [KpiTiles::class, RequestsChart::class, NeedsAttention::class, LatestRequests::class, ContentStatus::class];
        // an editor (content, not requests) sees the content status first
        if (CmsAccess::can('pages', 'update') && ! CmsAccess::can('requests', 'view')) {
            $widgets = [ContentStatus::class, ...array_diff($widgets, [ContentStatus::class])];
        }

        return array_values(array_filter($widgets, fn (string $w): bool => $w::canView()));
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 3];
    }
}
