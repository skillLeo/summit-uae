@php
    use App\Support\SiteUrl;
    $current = app()->getLocale();
    // Each language is named in its own script (its own approved label), never with a flag.
    $path = request()->getPathInfo();
    $query = request()->getQueryString();
@endphp
<div class="lang-menu" x-data="disclosure" x-on:keydown.escape="close" x-on:focusout="onFocusOut" x-on:click.outside="close">
    <span class="sr-only" id="lang-menu-label">{{ ui('a11y.choose_language') }}</span>
    <button class="lang-menu__button" type="button" aria-controls="lang-menu-list" aria-expanded="false"
        aria-labelledby="lang-menu-label lang-menu-current"
        x-bind:aria-expanded="open" x-on:click="toggleOrSheet" x-ref="button" data-lang-button>
        <x-icon name="globe" />
        <span class="lang-menu__current" id="lang-menu-current" lang="{{ $current }}">{{ ui('lang.'.$current, $current) }}</span>
        <x-icon name="chevron-down" />
    </button>
    <ul class="lang-menu__list" id="lang-menu-list" hidden x-bind:hidden="!open">
        @foreach (array_keys(config('summit.locales')) as $code)
            <li>
                <a class="lang-option" href="{{ SiteUrl::abs(SiteUrl::swapLocale($path, $code)).($query ? '?'.$query : '') }}"
                    lang="{{ $code }}" hreflang="{{ $code }}" dir="{{ locale_dir($code) }}" data-lang-switch="{{ $code }}"
                    @if ($code === $current) aria-current="true" @endif>
                    <span>{{ ui('lang.'.$code, $code) }}</span>
                    @if ($code === $current)
                        <x-icon name="check" />
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</div>
