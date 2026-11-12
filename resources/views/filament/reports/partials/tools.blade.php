{{-- Card tools: "Chart | Table" (when the card has a chart to swap) and the CSV of the table. Hidden in print. --}}
<div class="slc-report-tools" data-report-tools>
    @if ($toggle ?? true)
        <div class="slc-report-toggle" role="group" aria-label="{{ __('reports.view.toggle') }}">
            <button type="button" class="slc-report-toggle__btn" aria-pressed="true" x-bind:aria-pressed="(mode === 'chart').toString()" x-on:click="mode = 'chart'" data-report-mode="chart">{{ __('reports.view.chart') }}</button>
            <button type="button" class="slc-report-toggle__btn" aria-pressed="false" x-bind:aria-pressed="(mode === 'table').toString()" x-on:click="mode = 'table'" data-report-mode="table">{{ __('reports.view.table') }}</button>
        </div>
    @endif
    @if ($this->canExport())
        <x-filament::button size="sm" color="gray" outlined wire:click="export" wire:loading.attr="disabled" wire:target="export" data-report-csv="{{ $report }}">
            {{ __('reports.view.csv') }}
        </x-filament::button>
    @endif
</div>
