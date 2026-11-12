{{--
    Reports (App\Filament\Pages\Reports; brief §11.2 to §11.4). The filter row, the active filter summary (printed
    instead of the controls), the four tabs and the cards of the active tab. Each card is keyed by the filter state, so
    a filter change mounts it again with the new figures. "Change range" in an empty card focuses the date range.
--}}
@php
    use App\Filament\Reports\ReportData;
    use App\Filament\Reports\Widgets\ReportChart;
    use App\Filament\Reports\Widgets\ReportTable;

    $state = $this->reportState();
    $stateArray = $state->toArray();
    $hash = $state->hash();
    $data = new ReportData($state);
@endphp
<x-filament-panels::page class="slc-reports">
    <div
        class="slc-report-filters"
        data-report-filters
        x-data
        x-on:slc-reports-change-range.window="const el = $el.querySelector('[data-report-range]'); if (el) { el.scrollIntoView({ block: 'center' }); el.focus(); }"
    >
        {{ $this->getFiltersForm() }}
        <div class="slc-report-filters__actions">
            <x-filament::link tag="button" color="gray" wire:click="resetFilters" data-report-reset>
                {{ __('reports.filter.reset') }}
            </x-filament::link>
        </div>
    </div>

    <p class="slc-report-summary" data-report-summary>
        <span class="slc-report-summary__tab">{{ $this->tabs()[$tab] }}.</span>
        <span class="slc-report-summary__label">{{ __('reports.summary.label') }}</span>
        {{ $state->summary() }}
    </p>

    <x-filament::tabs :label="__('reports.tabs.label')" class="slc-report-tabs">
        @foreach ($this->tabs() as $key => $label)
            <x-filament::tabs.item
                :active="$tab === $key"
                wire:click="$set('tab', '{{ $key }}')"
                data-report-tab="{{ $key }}"
            >
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    <div class="slc-report-stage">
        <div class="slc-report-skeleton" wire:loading.flex wire:target="tab, filters, resetFilters" aria-hidden="true">
            @foreach ([40, 65, 30, 80, 55, 70, 45, 90, 60, 35] as $h)
                <span style="block-size: {{ $h }}%"></span>
            @endforeach
        </div>
        <span class="fi-sr-only" wire:loading wire:target="tab, filters, resetFilters" role="status">{{ __('reports.loading') }}</span>

        <div class="slc-report-grid" data-report-grid="{{ $tab }}" wire:loading.class="slc-report-grid--loading" wire:target="tab, filters, resetFilters">
            @if ($tab === 'workflow')
                @php($target = $data->withinTarget())
                @php($spam = $data->spamRate())
                <div class="slc-kpis slc-report-tiles" role="list">
                    <div class="slc-kpi" role="listitem" data-kpi="within-target">
                        <span class="slc-kpi__label">{{ __('reports.tile.target') }}</span>
                        <span class="slc-kpi__value">{{ $target['share'] === null ? __('reports.value.no_data') : ReportData::pct($target['share']) }}</span>
                        <span class="slc-kpi__line">
                            {{ __('reports.tile.target_line', ['met' => ReportData::n($target['met']), 'missed' => ReportData::n($target['missed']), 'hours' => ReportData::n($target['target'])]) }}
                        </span>
                        @if ($target['excluded'] > 0)
                            <span class="slc-kpi__line">{{ trans_choice('reports.tile.target_excluded', $target['excluded'], ['count' => ReportData::n($target['excluded'])]) }}</span>
                        @endif
                    </div>
                    <div class="slc-kpi" role="listitem" data-kpi="spam-rate">
                        <span class="slc-kpi__label">{{ __('reports.tile.spam') }}</span>
                        <span class="slc-kpi__value">{{ $spam['rate'] === null ? __('reports.value.no_data') : ReportData::pct($spam['rate']) }}</span>
                        <span class="slc-kpi__line">
                            {{ $spam['all'] === 0 ? __('reports.tile.spam_none') : __('reports.tile.spam_line', ['spam' => ReportData::n($spam['spam']), 'all' => ReportData::n($spam['all'])]) }}
                        </span>
                    </div>
                </div>
            @endif

            @foreach (ReportData::TABS[$tab] as $report)
                @livewire(ReportData::isChart($report) ? ReportChart::class : ReportTable::class, ['report' => $report, 'state' => $stateArray], key($report.'-'.$hash))
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
