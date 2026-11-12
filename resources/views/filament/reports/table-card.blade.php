{{--
    A Blade report card (App\Filament\Reports\Widgets\ReportTable; brief §11.4 "Build"): the weekday by hour heatmap
    with its "Chart | Table" toggle, or a table with bars in cells (already a table: no toggle). CSV of the table.
--}}
@php
    $info = $this->reportInfo();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $isEmpty = $this->isEmpty();
    $isHeatmap = $info['kind'] === 'heatmap';
@endphp
<x-filament-widgets::widget @class(['slc-report-card', 'slc-report-card--wide' => $info['wide']]) data-report-card="{{ $report }}">
    <div x-data="{ mode: 'chart' }" class="slc-report-card__inner">
        <x-filament::section :heading="$heading" :description="$description">
            @unless ($isEmpty)
                <x-slot name="afterHeader">
                    @include('filament.reports.partials.tools', ['report' => $report, 'toggle' => $isHeatmap])
                </x-slot>
            @endunless

            @if ($isEmpty)
                @include('filament.reports.partials.empty', ['line' => $info['empty_line']])
            @elseif ($isHeatmap)
                <div x-show="mode === 'chart'" data-report-view="chart">
                    @include('filament.reports.partials.heatmap', ['info' => $info, 'caption' => $heading.'. '.$description])
                </div>
                <div x-show="mode === 'table'" x-cloak data-report-view="table">
                    @include('filament.reports.partials.table', ['table' => $info, 'report' => $report, 'caption' => $heading])
                </div>
            @else
                @include('filament.reports.partials.bar-table', ['info' => $info, 'caption' => $heading])
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
