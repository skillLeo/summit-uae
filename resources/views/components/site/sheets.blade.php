@php
    use App\Support\SiteUrl;
    $current = app()->getLocale();
    $path = request()->getPathInfo();
    $services = content()->services();
    $guides = content()->guides();
    $sheets = [
        'services' => ui('nav.practice_areas'),
        'contact' => ui('nav.contact'),
        'language' => ui('a11y.choose_language'),
        'search' => ui('resources.search.label'),
        'menu' => ui('brand.name'),
    ];
@endphp
{{-- Bottom sheets (mobile shell and tablet menu): handle, 12px top radius, 480ms, swipe-down,
     focus trap, Esc, inert background. Hidden until opened. --}}
@foreach ($sheets as $id => $title)
    <div class="sheet-root" id="sheet-{{ $id }}" data-sheet="{{ $id }}" hidden>
        <div class="sheet-scrim" data-sheet-close></div>
        <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sheet-{{ $id }}-title" tabindex="-1">
            <div class="sheet__header" data-sheet-drag>
                <span class="sheet__handle" aria-hidden="true"></span>
                <h2 class="sheet__title" id="sheet-{{ $id }}-title">{{ $title }}</h2>
                <button class="icon-btn" type="button" data-sheet-close>
                    <x-icon name="close" />
                    <span class="sr-only">{{ $id === 'menu' || $id === 'services' ? ui('a11y.close_menu') : proposed('sheet.close') }}</span>
                </button>
            </div>
            <div class="sheet__body">
                @switch($id)
                    @case('services')
                        <ul class="sheet-list">
                            @foreach ($services as $i => $service)
                                <li>
                                    <a href="{{ SiteUrl::service($service['code']) }}">
                                        <span class="sheet-list__index ltr">{{ sprintf('%02d', $i + 1) }}</span>
                                        <span>{{ $service['title'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <div class="sheet-group">
                            <ul class="sheet-list">
                                <li><a href="{{ SiteUrl::page('P02') }}"><x-icon name="services" /><span>{{ ui('cta.view_all_practice_areas') }}</span></a></li>
                                <li><a href="{{ SiteUrl::page('P04') }}"><x-icon name="document" /><span>{{ ui('nav.case_studies') }}</span></a></li>
                                <li><a href="{{ SiteUrl::page('P06') }}"><x-icon name="info" /><span>{{ ui('nav.about') }}</span></a></li>
                            </ul>
                        </div>
                        @break

                    @case('contact')
                        <ul class="sheet-list">
                            <li><a href="{{ setting('phone_href') }}"><x-icon name="phone" /><span>{{ content()->page('P07')['content']['final']['call_label'] ?? ui('nav.contact') }}</span> <bdi class="ltr" dir="ltr">{{ setting('phone_display') }}</bdi></a></li>
                            <li><a href="{{ setting('whatsapp_url') }}" target="_blank" rel="noopener noreferrer"><x-icon name="whatsapp" /><span>{{ ui('cta.whatsapp') }}</span><span class="sr-only">{{ proposed('external.new_tab') }}</span></a></li>
                            <li><a href="{{ setting('email_href') }}"><x-icon name="mail" /><span>{{ content()->page('P07')['content']['final']['email_label'] ?? ui('nav.contact') }}</span> <bdi class="ltr" dir="ltr">{{ setting('email') }}</bdi></a></li>
                            <li><a href="{{ SiteUrl::consultation() }}" data-sheet-close-on-click><x-icon name="document" /><span>{{ ui('cta.request_consultation') }}</span></a></li>
                        </ul>
                        <p class="sheet__meta">{{ content()->page('P07')['content']['details']['hours'] ?? '' }}</p>
                        @break

                    @case('language')
                        <ul class="sheet-list">
                            @foreach (array_keys(config('summit.locales')) as $code)
                                <li>
                                    <a href="{{ SiteUrl::abs(SiteUrl::swapLocale($path, $code)) }}" lang="{{ $code }}" hreflang="{{ $code }}" dir="{{ locale_dir($code) }}" data-lang-switch="{{ $code }}" @if ($code === $current) aria-current="true" @endif>
                                        <x-icon name="globe" />
                                        <span>{{ ui('lang.'.$code, $code) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @break

                    @case('search')
                        <div class="stack stack-16" x-data="guideSearch">
                            <div class="field">
                                <label class="field__label" for="site-search-input">{{ ui('resources.search.label') }}</label>
                                <div class="picker__filter">
                                    <x-icon name="search" />
                                    <input class="input" id="site-search-input" type="search" autocomplete="off" enterkeyhint="search"
                                        placeholder="{{ ui('resources.search.placeholder') }}" x-model="query">
                                </div>
                            </div>
                            <ul class="sheet-list" data-search-results>
                                @foreach ($guides as $guide)
                                    <li data-search-item data-search-text="{{ mb_strtolower(($guide['card_title'] ?? $guide['home_title'] ?? '').' '.($guide['card_summary'] ?? '').' '.($guide['home_summary'] ?? '')) }}">
                                        <a href="{{ SiteUrl::guide($guide['code']) }}">
                                            <x-icon name="guides" />
                                            <span>{{ $guide['card_title'] ?? $guide['home_title'] ?? '' }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="picker__empty" data-search-empty hidden>{{ ui('resources.no_results') }}</p>
                        </div>
                        @break

                    @case('menu')
                        <ul class="sheet-list">
                            <li><a href="{{ SiteUrl::home() }}"><x-icon name="home" /><span>{{ ui('nav.home') }}</span></a></li>
                            <li><a href="{{ SiteUrl::page('P02') }}"><x-icon name="services" /><span>{{ ui('nav.practice_areas') }}</span></a></li>
                            <li><a href="{{ SiteUrl::page('P03') }}"><x-icon name="guides" /><span>{{ ui('nav.resources') }}</span></a></li>
                            <li><a href="{{ SiteUrl::page('P04') }}"><x-icon name="document" /><span>{{ ui('nav.case_studies') }}</span></a></li>
                            <li><a href="{{ SiteUrl::page('P05') }}"><x-icon name="faq" /><span>{{ ui('nav.faq') }}</span></a></li>
                            <li><a href="{{ SiteUrl::page('P06') }}"><x-icon name="info" /><span>{{ ui('nav.about') }}</span></a></li>
                            <li><a href="{{ SiteUrl::contact() }}" data-sheet-close-on-click><x-icon name="contact" /><span>{{ ui('nav.contact') }}</span></a></li>
                        </ul>
                        <div class="sheet-group">
                            <p class="sheet-group__title">{{ ui('nav.practice_areas') }}</p>
                            <ul class="sheet-list">
                                @foreach ($services as $i => $service)
                                    <li>
                                        <a href="{{ SiteUrl::service($service['code']) }}">
                                            <span class="sheet-list__index ltr">{{ sprintf('%02d', $i + 1) }}</span>
                                            <span>{{ $service['title'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        @break
                @endswitch
            </div>
        </div>
    </div>
@endforeach
