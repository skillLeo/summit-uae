{{--
    A chart card of the Reports page (App\Filament\Reports\Widgets\ReportChart; brief §11.4). Filament's ChartWidget
    canvas (Chart.js with the slcTheme plugin) and, behind "Table", the same figures as a table; CSV of that table.
--}}
@php
    use Filament\Support\Facades\FilamentAsset;

    $info = $this->reportInfo();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $isEmpty = $this->isEmpty();
    $type = $this->getType();
    $height = $this->chartHeight();
@endphp
<x-filament-widgets::widget @class(['slc-report-card', 'slc-report-card--wide' => $info['wide']]) data-report-card="{{ $report }}">
    <div x-data="{ mode: 'chart' }" class="slc-report-card__inner">
        <x-filament::section :heading="$heading" :description="$description">
            @unless ($isEmpty)
                <x-slot name="afterHeader">
                    @include('filament.reports.partials.tools', ['report' => $report])
                </x-slot>
            @endunless

            @if ($isEmpty)
                @include('filament.reports.partials.empty', ['line' => $info['empty_line']])
            @else
                <div x-show="mode === 'chart'" data-report-view="chart">
                    <div class="fi-wi-chart-image slc-report-chart" role="img" aria-label="{{ $heading }}. {{ $description }}">
                        <div
                            x-load
                            x-load-src="{{ FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                            wire:ignore
                            data-chart-type="{{ $type }}"
                            x-data="chart({
                                cachedData: @js($this->getCachedData()),
                                options: @js($this->getOptions()),
                                type: @js($type),
                            })"
                            class="fi-wi-chart-frame fi-wi-chart-canvas-ctn fi-wi-chart-frame-no-aspect-ratio slc-report-chart__frame"
                            style="block-size: {{ $height }}"
                        >
                            <canvas x-ref="canvas" aria-hidden="true" style="width: 100%; max-height: {{ $height }}"></canvas>
                            <span aria-hidden="true" x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                            <span aria-hidden="true" x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                            <span aria-hidden="true" x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                            <span aria-hidden="true" x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
                            <span aria-hidden="true" x-ref="tooltipBackgroundColorElement" class="fi-wi-chart-tooltip-bg-color"></span>
                            <span aria-hidden="true" x-ref="tooltipTextColorElement" class="fi-wi-chart-tooltip-text-color"></span>
                            <span aria-hidden="true" x-ref="tooltipBorderColorElement" class="fi-wi-chart-tooltip-border-color"></span>
                        </div>
                    </div>
                </div>
                <div x-show="mode === 'table'" x-cloak data-report-view="table">
                    @include('filament.reports.partials.table', ['table' => $info, 'report' => $report, 'caption' => $heading])
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
