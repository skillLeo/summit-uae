@php
    use App\Support\SiteUrl;
    $current = app()->getLocale();
    $path = request()->getPathInfo();
@endphp
@if (config('summit.features.language_suggestion'))
    {{-- One bar per other language, written in that language. Revealed before first paint by the
         boot script when the browser prefers it. Never redirects. --}}
    @foreach (array_keys(config('summit.locales')) as $code)
        @continue($code === $current)
        <div class="lang-suggest" data-lang="{{ $code }}" lang="{{ $code }}" dir="{{ locale_dir($code) }}" role="region"
            aria-label="{{ proposed('suggest.message', [], $code) }}">
            <div class="container lang-suggest__inner">
                <x-icon name="globe" />
                <p>{{ proposed('suggest.message', [], $code) }}</p>
                <div class="lang-suggest__actions">
                    <a class="btn btn--ghost" href="{{ SiteUrl::abs(SiteUrl::swapLocale($path, $code)) }}" hreflang="{{ $code }}" data-lang-switch="{{ $code }}">{{ proposed('suggest.action', [], $code) }}</a>
                    <button class="btn btn--ghost" type="button" data-suggest-dismiss>{{ proposed('suggest.dismiss', [], $code) }}</button>
                </div>
            </div>
        </div>
    @endforeach
@endif
