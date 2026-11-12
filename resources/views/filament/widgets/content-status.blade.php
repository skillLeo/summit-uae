{{-- Dashboard "Content status" (App\Filament\Widgets\ContentStatus; brief §11.1). --}}
<x-filament-widgets::widget>
    <x-filament::section :heading="__('dashboard.content.heading')">
        @php($f = $this->figures())
        @php($pages = \App\Filament\Resources\ContentPages\ContentPageResource::getUrl('index'))
        <dl class="slc-status-list">
            <div>
                <dt>{{ __('dashboard.content.drafts') }}</dt>
                <dd class="slc-tabular">{{ \App\Filament\Support\RequestFormat::number($f['drafts'], 0) }}</dd>
            </div>
            <div>
                <dt>{{ __('dashboard.content.scheduled') }}</dt>
                <dd class="slc-tabular">{{ \App\Filament\Support\RequestFormat::number($f['scheduled'], 0) }}</dd>
            </div>
            <div>
                <dt>
                    @if (\App\Filament\Cms\Pages\ApprovalReport::canAccess())
                        <a href="{{ \App\Filament\Cms\Pages\ApprovalReport::getUrl() }}">{{ __('dashboard.content.pending') }}</a>
                    @else
                        {{ __('dashboard.content.pending') }}
                    @endif
                </dt>
                <dd class="slc-tabular">{{ \App\Filament\Support\RequestFormat::number($f['pending'], 0) }}</dd>
            </div>
            @foreach ($f['missing'] as $locale => $count)
                <div>
                    <dt>{{ __('dashboard.content.missing') }}: <span lang="{{ $locale }}">{{ \App\Filament\Support\AdminLocale::nativeName($locale) }}</span></dt>
                    <dd class="slc-tabular">{{ \App\Filament\Support\RequestFormat::number($count, 0) }}</dd>
                </div>
            @endforeach
        </dl>
        <x-slot name="footer">
            <x-filament::link :href="$pages">{{ __('dashboard.content.open') }}</x-filament::link>
        </x-slot>
    </x-filament::section>
</x-filament-widgets::widget>
