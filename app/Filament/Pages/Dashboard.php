<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LatestRequests;
use App\Filament\Widgets\RequestsByLanguage;
use App\Filament\Widgets\RequestsByService;
use App\Filament\Widgets\RequestsOverview;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Internal dashboard: consultation requests in the last 7 / 30 days, awaiting review, by service and by
 * language, and an honest "not tracked" state for the draft-to-submitted ratio (drafts never leave the
 * visitor's device, so there is nothing to count; adding tracking is the owner's decision).
 */
class Dashboard extends BaseDashboard
{
    public static function getNavigationLabel(): string
    {
        return (string) __('admin.dashboard.title');
    }

    public function getTitle(): string|Htmlable
    {
        return (string) __('admin.dashboard.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('admin.dashboard.subheading');
    }

    public function getWidgets(): array
    {
        return [
            RequestsOverview::class,
            LatestRequests::class,
            RequestsByLanguage::class,
            RequestsByService::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 2];
    }
}
