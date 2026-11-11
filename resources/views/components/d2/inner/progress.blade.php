@props([
    'target' => null,   // id of the element whose reading progress the bar shows (the article); default: #main-content
])
{{--
    Design 2 reading progress (upgrade spec §7.4 guide page, §12 Phase D): a 3 px ink bar fixed under the
    sticky header (--header-h), growing from the inline-start edge as the target is read: 0 when its top
    reaches the bottom of the header, 1 when its end reaches the bottom of the window.

    <x-d2.inner.progress target="legal-text" />

    Drawn with transform: scaleX() only. modules/reading-progress.js sets --reading (0–1) through CSSOM on
    scroll and resize (rAF, passive). It is decorative (aria-hidden): the TOC carries the position for
    assistive technology. Hidden without JavaScript and in print.
--}}
<div {{ $attributes->class(['reading-progress']) }} data-reading-progress="{{ $target ?: 'main-content' }}" aria-hidden="true">
    <span class="reading-progress__bar"></span>
</div>
