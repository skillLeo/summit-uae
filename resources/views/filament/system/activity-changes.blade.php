{{-- Activity Log, "View changes" (App\Filament\Cms\Pages\System\ActivityLog): the fields before and after, then the
     entry's details. Secrets are masked (App\Support\ActivityLogging::mask()). --}}
<div class="slc-system-changes">
    @if ($rows === [])
        <p class="cms-diff__none">{{ __('system.activity.no_changes') }}</p>
    @else
        <div class="cms-diff">
            <table class="cms-diff__table slc-system-changes__table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('system.activity.diff.field') }}</th>
                        <th scope="col">{{ __('system.activity.diff.before') }}</th>
                        <th scope="col">{{ __('system.activity.diff.after') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <th scope="row">{{ $row['field'] }}@if ($row['path'] !== '')<span class="slc-system-changes__path" dir="ltr"><bdi>{{ $row['path'] }}</bdi></span>@endif</th>
                            <td class="slc-system-changes__before" dir="auto">@if ($row['before'] === '')<span class="slc-system-changes__empty">{{ __('system.activity.diff.empty') }}</span>@else{{ $row['before'] }}@endif</td>
                            <td class="slc-system-changes__after" dir="auto">@if ($row['after'] === '')<span class="slc-system-changes__empty">{{ __('system.activity.diff.empty') }}</span>@else{{ $row['after'] }}@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($properties !== [])
        <dl class="slc-system-changes__details">
            @foreach ($properties as $row)
                <div class="slc-system-changes__detail">
                    <dt>{{ $row['label'] }}</dt>
                    <dd dir="auto">{{ $row['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
