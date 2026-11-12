<?php

namespace App\Filament\Reports\Widgets;

use Filament\Widgets\ChartWidget;

/**
 * A chart of the Reports page: Filament's ChartWidget (Chart.js) with App\Reports\ChartTheme options and datasets
 * (ReportData), plus the table view and the CSV of the same figures (view filament.reports.chart-card). No pies,
 * donuts, gauges or second axes (§11.2).
 */
class ReportChart extends ChartWidget
{
    use IsReportCard;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.reports.chart-card';

    protected function getData(): array
    {
        return $this->reportData()->chart($this->report)['data'];
    }

    protected function getOptions(): array
    {
        return $this->reportData()->chart($this->report)['options'];
    }

    protected function getType(): string
    {
        return $this->reportData()->chart($this->report)['type'];
    }

    protected function getMaxHeight(): ?string
    {
        return $this->reportData()->chart($this->report)['height'];
    }

    public function chartHeight(): string
    {
        return (string) $this->getMaxHeight();
    }

    public function isEmpty(): bool
    {
        return (bool) $this->reportInfo()['empty'];
    }
}
