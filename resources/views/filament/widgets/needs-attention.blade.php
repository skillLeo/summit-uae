{{-- Dashboard "Needs attention" (App\Filament\Widgets\NeedsAttention; brief §11.1). --}}
<x-filament-widgets::widget>
    <x-filament::section :heading="__('dashboard.attention.heading')" class="slc-attention">
        @php($groups = $this->groups())
        @forelse ($groups as $key => $group)
            <section class="slc-attention__group" data-attention="{{ $key }}">
                <h3 class="slc-attention__title">{{ __('dashboard.attention.'.$key) }}</h3>
                <ul class="slc-attention__list">
                    @foreach ($group['items'] as $e)
                        <li>
                            @php($url = $this->url($e))
                            <{{ $url ? 'a' : 'span' }} @if ($url) href="{{ $url }}" @endif class="slc-attention__item">
                                <span class="slc-attention__ref slc-tabular"><bdi dir="ltr">{{ $e->reference }}</bdi></span>
                                <span class="slc-attention__meta">
                                    @if ($key === 'deadline')
                                        {{ \App\Filament\Support\RequestFormat::deadlineRelative($e) }}
                                    @else
                                        {{ __('dashboard.attention.waited', ['hours' => \App\Filament\Support\RequestFormat::number(\App\Filament\Support\RequestFormat::waited($e))]) }}
                                    @endif
                                </span>
                            </{{ $url ? 'a' : 'span' }}>
                        </li>
                    @endforeach
                </ul>
                @if ($group['more'] > 0)
                    <p class="slc-attention__more">{{ trans_choice('dashboard.attention.more', $group['more'], ['count' => $group['more']]) }}</p>
                @endif
            </section>
        @empty
            <p class="slc-muted">{{ __('dashboard.attention.none') }}</p>
        @endforelse
        @if ($groups !== [] && ! \App\Filament\Cms\Support\CmsAccess::readOnly())
            <x-slot name="footer">
                <x-filament::link :href="\App\Filament\Resources\Enquiries\EnquiryResource::getUrl('index')">{{ __('dashboard.attention.open_inbox') }}</x-filament::link>
            </x-slot>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
