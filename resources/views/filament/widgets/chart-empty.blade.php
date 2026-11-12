{{-- A chart without data (brief §11.4): no axes, one line and a "Change range" action. --}}
<x-filament-widgets::widget>
    <x-filament::section :heading="$this->getHeading()" :description="$this->getDescription()">
        <div class="slc-chart-empty" data-chart-empty>
            <p>{{ __('dashboard.chart.empty') }}</p>
            @if (\App\Filament\Cms\Support\CmsAccess::can('reports', 'view') && \Illuminate\Support\Facades\Route::has('filament.admin.pages.reports'))
                <x-filament::link :href="route('filament.admin.pages.reports')">{{ __('dashboard.chart.change_range') }}</x-filament::link>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
