{{--
    Weekday by hour in Dubai time (brief §11.2 Volume, §11.4): a CSS grid of 7 x 24 cells on the ordinal ramp in four
    even steps of the busiest hour; zero cells are neutral and the legend says so. Hours run with the reading direction
    (right to left in Arabic). Each cell names its weekday, hour and count (title); the Table view lists every figure.
--}}
@php
    use App\Filament\Reports\ReportData;
    use App\Reports\ChartTheme;
    $ramp = ChartTheme::LIGHT['ordinal'];
@endphp
<div
    class="slc-heatmap"
    role="img"
    aria-label="{{ $caption }}"
    data-heatmap
    style="--slc-ord-0: {{ ChartTheme::LIGHT['zero'] }}; @foreach ($ramp as $i => $c)--slc-ord-{{ $i + 1 }}: {{ $c }}; @endforeach"
>
    <div class="slc-heatmap__scroll">
        <div class="slc-heatmap__grid">
            <span class="slc-heatmap__corner" aria-hidden="true"></span>
            @foreach (range(0, 23) as $h)
                <span class="slc-heatmap__hour" aria-hidden="true">{{ $h % 3 === 0 ? sprintf('%02d', $h) : '' }}</span>
            @endforeach
            @foreach ($info['grid'] as $day => $hours)
                <span class="slc-heatmap__day" aria-hidden="true">{{ ReportData::weekday($day, 'D') }}</span>
                @foreach ($hours as $h => $count)
                    @php($level = $info['levels'][$day][$h])
                    <span
                        class="slc-heatmap__cell slc-heatmap__cell--{{ $level }}"
                        title="{{ __('reports.heatmap.cell', ['day' => ReportData::weekday($day), 'hour' => sprintf('%02d:00', $h), 'count' => ReportData::n($count)]) }}"
                        data-count="{{ $count }}"
                    ></span>
                @endforeach
            @endforeach
        </div>
    </div>
    <div class="slc-heatmap__legend" aria-hidden="true">
        <span class="slc-heatmap__key"><span class="slc-heatmap__swatch slc-heatmap__cell--0"></span>{{ __('reports.heatmap.zero') }}</span>
        <span class="slc-heatmap__key">
            {{ __('reports.heatmap.fewer') }}
            @foreach (range(1, 4) as $l)
                <span class="slc-heatmap__swatch slc-heatmap__cell--{{ $l }}"></span>
            @endforeach
            {{ __('reports.heatmap.more', ['max' => ReportData::n($info['max'])]) }}
        </span>
    </div>
</div>
