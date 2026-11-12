{{--
    A table with bars in cells (brief §11.4 "Build"): the bar is a bronze block whose inline size is the row's share of
    the largest value, the number beside it in ink with tabular figures. It mirrors in Arabic (logical sizes).
--}}
<div class="slc-report-table-wrap" tabindex="0" role="region" aria-label="{{ $caption }}">
    <table class="slc-report-table slc-report-table--bars" data-report-table="{{ $info['key'] }}">
        <caption class="fi-sr-only">{{ $caption }}</caption>
        <thead>
            <tr>
                @foreach ($info['columns'] as $i => $column)
                    <th scope="col" @class(['slc-num' => $i > 0 && $i !== $info['bar_column'], 'slc-report-table__barhead' => $i === $info['bar_column']])>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($info['rows'] as $r => $row)
                <tr>
                    @foreach ($row as $i => $cell)
                        @if ($i === 0)
                            <th scope="row" class="slc-report-table__name"><bdi>{{ $cell }}</bdi></th>
                        @elseif ($i === $info['bar_column'])
                            <td class="slc-report-table__barcell">
                                <span class="slc-report-bar">
                                    <span class="slc-report-bar__fill" style="inline-size: {{ max(0.5, (float) $info['bar'][$r]) }}%" aria-hidden="true"></span>
                                    <span class="slc-report-bar__value">{{ $cell }}</span>
                                </span>
                            </td>
                        @else
                            <td class="slc-num">{{ $cell }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
