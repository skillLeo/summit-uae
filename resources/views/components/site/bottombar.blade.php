@props(['current' => null])
@php use App\Support\SiteUrl; @endphp
{{-- Native-app bottom bar (<768). Labels are the approved navigation labels. --}}
<nav class="bottombar" aria-label="{{ proposed('nav.quick') }}" data-bottombar>
    <ul class="bottombar__list">
        <li>
            <a class="bottombar__item" href="{{ SiteUrl::home() }}" @if ($current === 'home') aria-current="page" @endif>
                <x-icon name="home" />
                <span class="bottombar__label">{{ ui('nav.home') }}</span>
            </a>
        </li>
        <li>
            <button class="bottombar__item" type="button" data-sheet-open="services" aria-haspopup="dialog" aria-expanded="false">
                <x-icon name="services" />
                <span class="bottombar__label">{{ ui('nav.practice_areas') }}</span>
            </button>
        </li>
        <li>
            <a class="bottombar__item" href="{{ SiteUrl::page('P03') }}" @if ($current === 'resources') aria-current="page" @endif>
                <x-icon name="guides" />
                <span class="bottombar__label">{{ ui('nav.resources') }}</span>
            </a>
        </li>
        <li>
            <a class="bottombar__item" href="{{ SiteUrl::page('P05') }}" @if ($current === 'faq') aria-current="page" @endif>
                <x-icon name="faq" />
                <span class="bottombar__label">{{ ui('nav.faq') }}</span>
            </a>
        </li>
        <li>
            <button class="bottombar__item" type="button" data-sheet-open="contact" aria-haspopup="dialog" aria-expanded="false">
                <x-icon name="contact" />
                <span class="bottombar__label">{{ ui('nav.contact') }}</span>
            </button>
        </li>
    </ul>
</nav>
