@props([
    'types' => null,     // pager order (default: App\Design\D2\Docs::TYPES reversed: cheque first)
    'active' => 'cheque',
    'idPrefix' => 'hero',
    'focus' => null,     // styleguide only: prev | next | segment (forced focus look)
])
{{--
    Hero pager (Design 2.1 brief D17; owner: hero). Under the stage:
      - six thin progress segments, one per document, filled up to the one shown (each a button named after its
        document, 44 px tall targets, 48 px on touch screens; aria-current on the one shown);
      - the document's name (a polite live region: a change is announced once) and the previous / next buttons
        (the kit's 40 px secondary icon buttons with the carousel arrows brief §3.6 rule 3 allows; on the first
        and the last document the step that leads nowhere is aria-disabled, at 40 % opacity).
    No counter, no dots, no autoplay, no wrap-around. One Tab stop: a roving tabindex on the segments (only the
    document shown is in the tab order); ← / → move while focus is in the pager (mirrored in RTL), Home / End jump
    to the ends and focus follows the document. Previous / next are for pointers and assistive technology
    (tabindex -1: the keys do the same). hero/pager.js drives it; without JavaScript the pager is not shown.
--}}
@php
    use App\Design\D2\Docs;

    $types = $types ?? array_reverse(Docs::TYPES);
    $active = in_array($active, $types, true) ? $active : $types[0];
    $index = array_search($active, $types, true);
    $proposed = app()->isProduction() ? null : 'proposed';
@endphp
<div {{ $attributes->class(['hero-pager']) }} data-hero-pager data-types="{{ implode(' ', $types) }}">
    <div class="hero-pager__track" role="group" aria-label="{{ d2p('hero.segments_label') }}" @proposed>
        @foreach ($types as $i => $type)
            <button type="button" @class(['hero-pager__seg', 'is-focus' => $focus === 'segment' && $i === $index])
                data-pager-seg="{{ $type }}" tabindex="{{ $type === $active ? '0' : '-1' }}" aria-label="{{ Docs::name($type) }}"
                @if ($type === $active) aria-current="true" @endif @proposed><span class="hero-pager__bar" aria-hidden="true"></span></button>
        @endforeach
    </div>
    <div class="hero-pager__row">
        <p class="hero-pager__name" aria-live="polite" data-pager-name @proposed>{{ Docs::name($active) }}</p>
        <div class="hero-pager__steps">
            <x-d2.ui.icon-button icon="carousel-prev" :label="d2p('hero.prev')" variant="secondary" size="40" tip="top"
                :disabled="$index === 0" @class(['hero-pager__step', 'is-focus' => $focus === 'prev']) data-pager-step="-1" tabindex="-1" :data-copy-status="$proposed" />
            <x-d2.ui.icon-button icon="carousel-next" :label="d2p('hero.next')" variant="secondary" size="40" tip="top-end"
                :disabled="$index === count($types) - 1" @class(['hero-pager__step', 'is-focus' => $focus === 'next']) data-pager-step="1" tabindex="-1" :data-copy-status="$proposed" />
        </div>
    </div>
</div>
