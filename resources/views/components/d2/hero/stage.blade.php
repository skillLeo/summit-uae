@props([
    'types' => null,         // the documents, in pager order (default: App\Design\D2\Docs::TYPES top of the pile first)
    'active' => 'cheque',    // the document on top (its content and callouts show)
    'idPrefix' => 'hero',
])
{{--
    Hero document stage (Design 2.1 brief §4 D15/D16, §6 "Hero document switch"; owner: hero; the owner's option 3:
    the brief's rules with the Phase 1 pile back). One labelled illustration (role="img", proposed aria-label,
    data-illustration) holding one canvas 620 design px wide (510 tall from 768 px, 650 below), scaled to the stage
    width by CSS alone (scale = stage width / 620 through tan(atan2()), never above 1); the stage reserves its size
    with aspect-ratio: CLS 0.

    The pile  all six sample documents, each in its own pose (App\Design\D2\HeroStage::POSE: a hand-placed offset and
              a few degrees of rotation, from a nonce'd <style> block), stacked by data-depth (0 = on top). The
              document on top (.is-active) sits straight at the pile's centre and shows its content.
    D15       every sheet behind it shows only its paper (tint, texture, edges, the cheque's security waves, the
              memo's folded corner and staple, the phone's frame): the stage hides each back document's ink
              (components/stage.css), so nothing readable is ever cut off at a sheet's edge.
    D16       each document carries its own callouts (aria-hidden; <x-d2.hero.notes> holds the same text), laid out for
              its top pose: an SVG with one dot at the edge of each key field and one leader through the empty paper
              beside it, and the labels in the clear margin on the inline-end side of the pile. Positions come from
              HeroStage (measured fields, resources/js/d2/hero/callout-layout.json) through nonce'd <style> blocks
              (the CSP forbids style=""): the geometry once, the callout positions once per language (keyed by
              data-loc, so a specimen in another language on the styleguide gets its own). From 768 px a label shows
              its text; below 768 px a numbered marker (the legend under the stage lists the text).
    §6        hero/stage.js deals the pile in once, lifts a chosen sheet out of the pile onto the top (the old top goes
              back to its place), draws its callouts once the paper has settled, and moves the pile with the pointer;
              without JavaScript or under reduced motion the composed pile is what the server printed.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Docs;
    use App\Design\D2\HeroStage;

    $types = array_values(array_filter($types ?? array_reverse(Docs::TYPES), fn ($t) => isset(Docs::SIZE[$t])));
    $active = in_array($active, $types, true) ? $active : $types[0];
    // The pile from the top down: the document shown, then the others in pager order.
    $order = [$active, ...array_values(array_filter($types, fn ($t) => $t !== $active))];
    $locale = app()->getLocale();
@endphp
@pushOnce('head', 'hero-stage-geometry')
    {{-- Document scales, pile poses and the top pose, per layout (App\Design\D2\HeroStage::css()). --}}
    <style nonce="{{ Vite::cspNonce() }}">@layer components{!! '{'.HeroStage::css().'}' !!}</style>
@endPushOnce
@pushOnce('head', 'hero-stage-callouts-'.$locale)
    {{-- Callout positions in this language (inset of the label's facing edge, room to the canvas edge, leader y). --}}
    <style nonce="{{ Vite::cspNonce() }}">@layer components{!! '{'.HeroStage::calloutCss($locale).'}' !!}</style>
@endPushOnce
<div {{ $attributes->class(['hero-stage']) }} role="img" aria-label="{{ d2p('hero.stage_label') }}" @proposed data-hero-stage
    data-loc="{{ $locale }}" data-active="{{ $active }}" data-illustration>
    <div class="hero-canvas" data-canvas>
        <div class="hero-tilt" data-tilt>
            @foreach ($types as $type)
                @php $callouts = HeroStage::callouts($type, $locale); @endphp
                <div @class(['hero-doc', 'hero-doc--'.$type, 'is-active' => $type === $active]) data-hero-doc="{{ $type }}" data-depth="{{ array_search($type, $order, true) }}">
                    <div class="hero-sheet" data-sheet>
                        <div class="hero-doc__paper" data-doc-paper>
                            <x-dynamic-component :component="'d2.doc.'.$type" variant="stage" />
                        </div>
                    </div>
                    {{-- data-hero-callouts, not data-callouts: every document root already carries data-callouts (its anchors). --}}
                    <div class="hero-callouts" data-hero-callouts aria-hidden="true">
                        {{-- No viewBox: one user unit is one canvas px, whatever the layout's canvas height. --}}
                        <svg class="hero-lines" focusable="false">
                            @foreach (HeroStage::LAYOUTS as $layout)
                                <g class="hero-lines__set hero-lines__set--{{ $layout }}">
                                    @foreach ($callouts as $c)
                                        <path class="hero-line" d="{{ $c[$layout]['d'] }}" pathLength="1" data-callout="{{ $c['id'] }}"></path>
                                        <circle class="hero-dot" cx="{{ $c[$layout]['dot'][0] }}" cy="{{ $c[$layout]['dot'][1] }}" r="4" data-callout="{{ $c['id'] }}"></circle>
                                    @endforeach
                                </g>
                            @endforeach
                        </svg>
                        @foreach ($callouts as $c)
                            <span class="hero-callout hero-callout--{{ $c['side'] }} hero-callout--{{ $c['align'] }}" data-callout="{{ $c['id'] }}"><span class="hero-callout__num t-num">{{ $c['n'] }}</span><span class="hero-callout__text" @proposed>{{ Bidi::isolate($c['label']) }}</span></span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
