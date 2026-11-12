{{-- A report without data (brief §11.4): no axes, one line and "Change range" (focuses the date range filter). --}}
<div class="slc-chart-empty slc-report-empty" data-chart-empty>
    <p>{{ __($line) }}</p>
    <x-filament::link tag="button" x-on:click="$dispatch('slc-reports-change-range')" data-report-change-range>
        {{ __('reports.empty.action') }}
    </x-filament::link>
</div>
