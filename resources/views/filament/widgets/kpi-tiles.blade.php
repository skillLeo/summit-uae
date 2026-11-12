{{-- Dashboard row 1 (App\Filament\Widgets\KpiTiles; brief §11.1, §11.4). No icons on the tiles. --}}
<x-filament-widgets::widget class="slc-kpi-widget">
    <div class="slc-kpis" role="list">
        @foreach ($this->tiles() as $tile)
            @php($tag = $tile['url'] ? 'a' : 'div')
            <{{ $tag }} @if ($tile['url']) href="{{ $tile['url'] }}" @endif class="slc-kpi" role="listitem" data-kpi="{{ $tile['key'] }}">
                <span class="slc-kpi__label">{{ $tile['label'] }}</span>
                @if ($tile['value'] !== null)
                    <span class="slc-kpi__value">{{ $tile['value'] }}</span>
                @endif
                <span class="slc-kpi__line">
                    @if ($tile['trend'] !== null && $tile['trend'] !== 0 && ($tile['key'] === 'new'))
                        <x-filament::icon :icon="$tile['trend'] > 0 ? 'heroicon-m-arrow-up' : 'heroicon-m-arrow-down'" class="slc-kpi__arrow" />
                        <span class="fi-sr-only">{{ __($tile['trend'] > 0 ? 'dashboard.line.up' : 'dashboard.line.down') }}</span>
                    @endif
                    {{ $tile['line'] }}
                </span>
                @if (! empty($tile['sub']))
                    <span class="slc-kpi__line @if ($tile['tone']) slc-kpi__line--{{ $tile['tone'] }} @endif">
                        <x-filament::icon :icon="$tile['trend'] > 0 ? 'heroicon-m-arrow-up' : 'heroicon-m-arrow-down'" class="slc-kpi__arrow" />
                        {{ $tile['sub'] }}
                        <span class="fi-sr-only">({{ __('dashboard.line.'.$tile['tone']) }})</span>
                    </span>
                @endif
                @if ($points = \App\Filament\Widgets\KpiTiles::sparkline($tile['spark']))
                    <svg class="slc-kpi__spark" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                        <polyline points="{{ $points }}" fill="none" vector-effect="non-scaling-stroke" />
                    </svg>
                @endif
            </{{ $tag }}>
        @endforeach
    </div>
</x-filament-widgets::widget>
