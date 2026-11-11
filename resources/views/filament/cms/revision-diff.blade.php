{{-- Field-by-field difference of a revision against the current version (App\Content\Cms\Editing\Diff). --}}
@if ($rows === [])
    <p class="cms-diff__none">{{ __('cms.revision.diff_none') }}</p>
@else
    <div class="cms-diff">
        <table class="cms-diff__table">
            <thead>
                <tr>
                    <th scope="col">{{ __('cms.revision.diff_field') }}</th>
                    <th scope="col">{{ __('cms.revision.diff_this') }}</th>
                    <th scope="col">{{ __('cms.revision.diff_current') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <th scope="row" dir="ltr"><bdi>{{ $row['path'] }}</bdi></th>
                        <td class="cms-diff__old" dir="auto">{{ $row['before'] ?? '' }}</td>
                        <td class="cms-diff__new" dir="auto">{{ $row['after'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
