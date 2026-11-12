{{-- The table of a report: exactly the cells of its CSV (ReportData::table()). The first column names the row. --}}
<div class="slc-report-table-wrap" tabindex="0" role="region" aria-label="{{ $caption }}">
    <table class="slc-report-table" data-report-table="{{ $report }}">
        <caption class="fi-sr-only">{{ $caption }}</caption>
        <thead>
            <tr>
                @foreach ($table['columns'] as $i => $column)
                    <th scope="col" @class(['slc-num' => $i > 0])>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($table['rows'] as $row)
                <tr>
                    @foreach ($row as $i => $cell)
                        @if ($i === 0)
                            <th scope="row"><bdi>{{ $cell }}</bdi></th>
                        @else
                            <td class="slc-num">{{ $cell }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
