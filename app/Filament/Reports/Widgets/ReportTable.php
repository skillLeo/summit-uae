<?php

namespace App\Filament\Reports\Widgets;

use Filament\Widgets\Widget;

/**
 * A report drawn in Blade rather than Chart.js (brief §11.4 "Build"): the weekday by hour heatmap, and the tables with
 * bars in cells (workload per assignee, top sources). Same card as the charts: heading, toggle where there is a
 * chart to swap (the heatmap), CSV of the table (view filament.reports.table-card).
 */
class ReportTable extends Widget
{
    use IsReportCard;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.reports.table-card';

    public function isEmpty(): bool
    {
        return (bool) $this->reportInfo()['empty'];
    }
}
