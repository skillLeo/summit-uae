<?php

namespace App\Filament\Widgets;

use App\Filament\Cms\Support\CmsAccess;
use App\Reports\ChartTheme;
use App\Reports\Kpi;
use App\Reports\Period;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Dashboard row 2 (docs/briefs/design-2.1-and-admin-cms.md §11.1): "Requests, last 30 days", one series of columns per
 * Dubai day, from App\Reports\Kpi with App\Reports\ChartTheme. With no request in the period it shows no axes, one
 * line and a "Change range" link to the reports instead (§11.4 empty state).
 */
class RequestsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    protected ?string $maxHeight = '260px';

    protected static bool $isLazy = false;

    /** @var array<string, int>|null */
    private ?array $series = null;

    public static function canView(): bool
    {
        return CmsAccess::can('requests', 'view') || CmsAccess::can('reports', 'view');
    }

    public function getHeading(): string
    {
        return (string) __('dashboard.chart.heading');
    }

    public function getDescription(): ?string
    {
        return (string) __('dashboard.chart.description');
    }

    /** @return array<string, int> */
    public function series(): array
    {
        return $this->series ??= (new Kpi)->series(Period::make('30d'));
    }

    public function isEmpty(): bool
    {
        return array_sum($this->series()) === 0;
    }

    protected function getData(): array
    {
        $labels = [];
        foreach (array_keys($this->series()) as $day) {
            $labels[] = CarbonImmutable::parse($day)->locale(app()->getLocale())->translatedFormat('j M');
        }

        return [
            'labels' => $labels,
            'datasets' => [ChartTheme::bars((string) __('dashboard.chart.series'), array_values($this->series()))],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ChartTheme::options(['labels' => false]);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return $this->isEmpty() ? view('filament.widgets.chart-empty') : parent::render();
    }
}
