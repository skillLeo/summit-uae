@props([
    'types' => null,     // pager order (default: App\Design\D2\Docs::TYPES reversed: cheque first)
    'active' => 'cheque',
    'idPrefix' => 'hero',
    'autoplay' => false, // the home hero: the samples change by themselves (the owner's decision), the toggle reads "Pause"
    'focus' => null,     // styleguide only: prev | next | toggle | segment (forced focus look)
])
{{--
    Hero pager (Design 2.1 brief D17, amended by the owner's autoplay; owner: hero). Under the stage:
      - six thin progress segments, one per document, filled up to the one shown (each a button named after its
        document, 44 px tall targets, 48 px on touch screens; aria-current on the one shown). While the samples play,
        the shown segment fills over its dwell time (a transform, mirrored in RTL), so the timer is visible;
      - the document's name (a polite live region: a change is announced once, never while the samples play by
        themselves: aria-live turns off then), the pause / play toggle and the previous / next buttons (the kit's
        40 px secondary icon buttons with the carousel arrows brief §3.6 rule 3 allows). The pile wraps around, so
        previous and next are always enabled.
    No counter and no dots. The segments are one Tab stop (a roving tabindex: only the document shown is in the tab
    order; ← / → move while focus is in the pager, mirrored in RTL, wrapping; Home / End jump to the ends and focus
    follows the document); the toggle is the second (WCAG 2.2.2: a visible way to pause). Previous / next are for
    pointers and assistive technology (tabindex -1: the keys do the same). hero/pager.js drives it; without
    JavaScript the pager is not shown.
--}}
@php
    use App\Design\D2\Docs;

    $types = $types ?? array_reverse(Docs::TYPES);
    $active = in_array($active, $types, true) ? $active : $types[0];
    $index = array_search($active, $types, true);
    $proposed = app()->isProduction() ? null : 'proposed';
    $pause = d2p('hero.pause');
    $play = d2p('hero.play');
@endphp
<div {{ $attributes->class(['hero-pager']) }} data-hero-pager data-types="{{ implode(' ', $types) }}" @if ($autoplay) data-autoplay @endif>
    <div class="hero-pager__track" role="group" aria-label="{{ d2p('hero.segments_label') }}" @proposed>
        @foreach ($types as $i => $type)
            <button type="button" @class(['hero-pager__seg', 'is-focus' => $focus === 'segment' && $i === $index])
                data-pager-seg="{{ $type }}" tabindex="{{ $type === $active ? '0' : '-1' }}" aria-label="{{ Docs::name($type) }}"
                @if ($type === $active) aria-current="true" @endif @proposed><span class="hero-pager__bar" aria-hidden="true"><span class="hero-pager__fill" data-pager-fill></span></span></button>
        @endforeach
    </div>
    <div class="hero-pager__row">
        <p class="hero-pager__name" aria-live="polite" data-pager-name @proposed>{{ Docs::name($active) }}</p>
        <div class="hero-pager__steps">
            {{-- The toggle: its name (and tooltip) says what a press does; both icons are printed, CSS shows one by
                 data-state (auto: playing unless the visitor prefers reduced motion; JavaScript then sets playing or
                 stopped). --}}
            <button type="button" @class(['btn', 'btn--secondary', 'btn--40', 'icon-btn', 'hero-pager__toggle', 'is-focus' => $focus === 'toggle'])
                aria-label="{{ $autoplay ? $pause : $play }}" data-pager-toggle data-state="{{ $autoplay ? 'auto' : 'stopped' }}"
                data-label-pause="{{ $pause }}" data-label-play="{{ $play }}" @if ($proposed) data-copy-status="proposed" @endif>
                <span class="btn__icon hero-pager__glyph hero-pager__glyph--pause" aria-hidden="true"><x-d2.ui.icon name="pause" /></span>
                <span class="btn__icon hero-pager__glyph hero-pager__glyph--play" aria-hidden="true"><x-d2.ui.icon name="play" /></span>
                <span class="icon-btn__tip icon-btn__tip--top" aria-hidden="true" data-overlay data-pager-tip>{{ $autoplay ? $pause : $play }}</span>
            </button>
            <x-d2.ui.icon-button icon="carousel-prev" :label="d2p('hero.prev')" variant="secondary" size="40" tip="top"
                @class(['hero-pager__step', 'is-focus' => $focus === 'prev']) data-pager-step="-1" tabindex="-1" :data-copy-status="$proposed" />
            <x-d2.ui.icon-button icon="carousel-next" :label="d2p('hero.next')" variant="secondary" size="40" tip="top-end"
                @class(['hero-pager__step', 'is-focus' => $focus === 'next']) data-pager-step="1" tabindex="-1" :data-copy-status="$proposed" />
        </div>
    </div>
</div>
