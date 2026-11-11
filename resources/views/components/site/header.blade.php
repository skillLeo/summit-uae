@props(['current' => null])
@php
    use App\Support\SiteUrl;
    $nav = [
        ['key' => 'home', 'label' => ui('nav.home'), 'href' => SiteUrl::home()],
        ['key' => 'practice', 'label' => ui('nav.practice_areas'), 'href' => SiteUrl::page('P02'), 'menu' => true],
        ['key' => 'resources', 'label' => ui('nav.resources'), 'href' => SiteUrl::page('P03')],
        ['key' => 'cases', 'label' => ui('nav.case_studies'), 'href' => SiteUrl::page('P04')],
        ['key' => 'faq', 'label' => ui('nav.faq'), 'href' => SiteUrl::page('P05')],
        ['key' => 'about', 'label' => ui('nav.about'), 'href' => SiteUrl::page('P06')],
        ['key' => 'contact', 'label' => ui('nav.contact'), 'href' => SiteUrl::contact()],
    ];
@endphp
<header class="site-header" data-header>
    <div class="container site-header__bar">
        {{-- Official emblem (unaltered) + the approved brand name as live text: the full lockup's
             wordmark is too small to read at header size (it is used in the footer and chooser). --}}
        <a class="brand" href="{{ SiteUrl::home() }}" @if ($current === 'home') aria-current="page" @endif>
            <x-brand name="emblem" class="brand__emblem" />
            <span class="brand__name">{{ ui('brand.name') }}</span>
        </a>

        <nav class="site-nav" aria-label="{{ proposed('nav.main') }}">
            <ul class="site-nav__list">
                @foreach ($nav as $item)
                    <li class="site-nav__item" @if ($item['menu'] ?? false) x-data="disclosure" x-on:keydown.escape="close" x-on:focusout="onFocusOut" x-on:click.outside="close" @endif>
                        <a class="site-nav__link" href="{{ $item['href'] }}" @if ($current === $item['key']) aria-current="page" @endif>{{ $item['label'] }}</a>
                        @if ($item['menu'] ?? false)
                            <button class="site-nav__toggle" type="button" aria-controls="nav-panel-services" aria-expanded="false"
                                x-bind:aria-expanded="open" x-on:click="toggle" x-ref="button">
                                <x-icon name="chevron-down" />
                                <span class="sr-only">{{ $item['label'] }}</span>
                            </button>
                            <div class="nav-panel" id="nav-panel-services" hidden x-bind:hidden="!open">
                                <ul class="nav-panel__list">
                                    @foreach (content()->services() as $i => $service)
                                        <li>
                                            <a href="{{ SiteUrl::service($service['code']) }}">
                                                <span class="nav-panel__index ltr">{{ sprintf('%02d', $i + 1) }}</span>
                                                <span>{{ $service['title'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                                <a class="text-link" href="{{ SiteUrl::page('P02') }}">{{ ui('cta.view_all_practice_areas') }}</a>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="site-header__tools">
            <x-site.lang-menu />
            <button class="icon-btn site-header__search" type="button" data-sheet-open="search" aria-haspopup="dialog">
                <x-icon name="search" />
                <span class="sr-only">{{ ui('resources.search.label') }}</span>
            </button>
            <button class="btn btn--ghost-light site-header__menu-btn" type="button" data-sheet-open="menu" aria-haspopup="dialog">
                <x-icon name="menu" />
                <span>{{ ui('a11y.open_menu') }}</span>
            </button>
        </div>
    </div>
</header>
