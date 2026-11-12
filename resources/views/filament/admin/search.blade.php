{{--
    The admin search (App\Filament\Livewire\AdminSearch; brief §9.1, §13): a native modal dialog opened with Cmd/Ctrl+K
    or the Search button (data-shortcut-search). Arrow keys move through the results, Enter opens one, Escape closes and
    focus returns to the trigger. Recent items are kept in this browser only (localStorage, per admin convenience).
--}}
<div>
    <dialog id="slc-search" class="slc-search" aria-labelledby="slc-search-title" wire:ignore.self>
        <h2 id="slc-search-title" class="fi-sr-only">{{ __('admin_shell.search.title') }}</h2>
        <div class="slc-search__field">
            <x-filament::icon icon="heroicon-o-magnifying-glass" class="slc-search__icon" />
            <input type="search" wire:model.live.debounce.250ms="q" placeholder="{{ __('admin_shell.search.placeholder') }}"
                   aria-label="{{ __('admin_shell.search.placeholder') }}" autocomplete="off" spellcheck="false" data-search-input
                   aria-controls="slc-search-results" />
            <button type="button" class="slc-search__close" data-search-close>{{ __('requests.shortcuts.close') }}</button>
        </div>
        <div id="slc-search-results" class="slc-search__results" role="listbox" aria-label="{{ __('admin_shell.search.results') }}">
            @if (mb_strlen(trim($q)) < 2)
                <div class="slc-search__recent" x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('slc-search-rendered')))" data-search-recent data-empty="{{ __('admin_shell.search.hint') }}" data-heading="{{ __('admin_shell.search.recent') }}"></div>
            @elseif ($results === [])
                <p class="slc-search__empty">{{ __('admin_shell.search.none') }}</p>
            @else
                @foreach (collect($results)->groupBy('group') as $group => $rows)
                    <p class="slc-search__group">{{ $group }}</p>
                    @foreach ($rows as $row)
                        <a href="{{ $row['url'] }}" class="slc-search__item" role="option" data-search-item data-title="{{ $row['title'] }}" data-meta="{{ $row['meta'] }}">
                            <span class="slc-search__title">@if ($row['ltr'])<bdi dir="ltr">{{ $row['title'] }}</bdi>@else{{ $row['title'] }}@endif</span>
                            <span class="slc-search__meta"><bdi dir="auto">{{ $row['meta'] }}</bdi></span>
                        </a>
                    @endforeach
                @endforeach
            @endif
        </div>
    </dialog>
</div>
