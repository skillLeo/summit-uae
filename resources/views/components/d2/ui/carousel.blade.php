@props([
    'labelledby' => null, // id of the visible heading that names the carousel (preferred)
    'label' => null,      // or an accessible name (approved copy), when no heading names it
    'until' => null,      // 768 | 1280: a carousel below that width only; from it the <ul> is a plain list the caller lays out
    'listClass' => null,  // classes of the <ul> (the caller's list or grid layout at the plain-list widths)
    'listAttributes' => [], // more attributes for the <ul>, e.g. ['data-svc-grid' => true, 'x-ref' => 'list']
    'dots' => true,       // position dots under the cards (built by JavaScript: one per visible card)
])
{{--
    <x-d2.ui.carousel labelledby="cases-title" until="768" list-class="cases__grid">
        @foreach ($cases as $case) <li class="cases__item">…</li> @endforeach
    </x-d2.ui.carousel>

    `list-attributes` puts more attributes on the <ul> (a caller's own hooks: data-*, x-ref, id).

    Swipe rail of cards (upgrade spec §8 "Carousels", §12 E): CSS scroll-snap (x mandatory), cards 85 % of the
    track (--carousel-card), 16 px gap (--carousel-gap), bleeding to the screen edges (--carousel-bleed, default
    the container padding --pad-x), no autoplay. The slot holds the <li> slides; every slide snaps to the
    inline start, RTL included. Styles: resources/css/d2/components/carousel.css.

    JavaScript (Alpine d2Carousel, resources/js/d2/components/carousel.js) adds what CSS cannot:
    - one position marker per visible card (Design 2.1 draws them as thin segments, not dots: carousel.css): the
      marker of the card in view is current (aria-current) and every one
      scrolls to its card; the dots are one Tab stop (←/→, reversed in RTL, Home, End move and scroll); every
      dot is a 48 × 48 target. A rail with more cards than 48 px dots fit in its row (the twelve practice areas,
      the nine guides on a phone) gets the pager of the <template data-carousel-pager> instead: previous and
      next buttons (48 px icon buttons named carousel-prev / carousel-next, the allowed arrows of §3.6 rule 3;
      aria-disabled at the ends) around the position "3/12" (final pass, home-mobile);
    - role="group" + aria-roledescription "carousel" + the name, only while it is a carousel (so a plain list
      on desktop is announced as a plain list);
    - a focusable track (←/→/Home/End) when the cards hold no link or button of their own.
    Without JavaScript the rail still swipes and every card stays reachable by Tab; the dots are not drawn.
    Strings: d2p('mobile.carousel.*') (proposed copy; the pager's position is the only one drawn on screen).
--}}
@php
    $until = in_array((int) $until, [768, 1280], true) ? (int) $until : null;
    // The track keeps its own class, role and hook; the caller's attributes add to them (never style="").
    $listBag = new \Illuminate\View\ComponentAttributeBag(array_diff_key((array) $listAttributes, array_flip(['class', 'role', 'style', 'data-carousel-track'])));
@endphp
<div {{ $attributes->class(['carousel', 'carousel--until-'.$until => $until]) }} x-data="d2Carousel" data-carousel
    @if ($until) data-until="{{ $until }}" @endif
    @if ($labelledby) data-labelledby="{{ $labelledby }}" @elseif ($label) data-label="{{ $label }}" @endif
    data-roledescription="{{ d2p('mobile.carousel.roledescription') }}" @proposed>
    <ul {{ $listBag->class(array_filter(['carousel__track', $listClass])) }} role="list" data-carousel-track>
        {{ $slot }}
    </ul>
    @if ($dots)
        <div class="carousel__dots" role="group" aria-label="{{ d2p('mobile.carousel.dots') }}"
            data-carousel-dots data-count="{{ d2p('mobile.carousel.count') }}" data-dot-label="{{ d2p('mobile.carousel.dot') }}" hidden @proposed></div>
        {{-- Long rails: previous / next and the position instead of the dots (carousel.js fills it in). --}}
        <template data-carousel-pager>
            <x-d2.ui.icon-button icon="carousel-prev" :label="d2p('mobile.carousel.prev')" variant="secondary" size="48" tip="none"
                class="carousel__step" data-carousel-step="-1" :data-copy-status="app()->isProduction() ? null : 'proposed'" />
            <p class="carousel__count" @proposed>
                <bdi class="carousel__position t-num" dir="ltr" aria-hidden="true" data-carousel-position></bdi>
                <span class="sr-only" aria-live="polite" data-carousel-position-label></span>
            </p>
            <x-d2.ui.icon-button icon="carousel-next" :label="d2p('mobile.carousel.next')" variant="secondary" size="48" tip="none"
                class="carousel__step" data-carousel-step="1" :data-copy-status="app()->isProduction() ? null : 'proposed'" />
        </template>
    @endif
</div>
