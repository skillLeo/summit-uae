@props([
    'as' => 'article',        // article | a | div | li | section
    'href' => null,           // whole-card link (see below)
    'interactive' => null,    // hover: ink edge + underlined title (no lift, no shadow); on by default when href is set
    'tone' => 'default',      // default (white) | sunken (the --band panel) | night (--dark-2 on dark sections)
    'padding' => 'md',        // md (--pad-card: 24, 32 from 768 px) | sm (16 / 20) | lg (32) | none
    'title' => null,          // card title (string, or a named slot "title" for markup)
    'titleLevel' => 3,        // heading level of the title (2–6); 0 renders a <p>
    'label' => null,          // accessible name of the link when the card has no title (href only)
])
{{--
    <x-d2.ui.card :href="$url" :title="$service['title']" title-level="3">
        <p class="card__text">Approved summary (complete, never clamped)</p>
        <x-slot:footer>…the approved link label…</x-slot:footer>
    </x-d2.ui.card>

    Design 2.1 (brief §3.5, D04, D05; docs/d2.1/phase-1/components.md §2): only for interactive containers and
    clickable listings. White, 1px --line edge, radius 10, the card padding, no shadow. No decorative icon in the
    media slot (D04): it is for a real image only. Whole-card link: with href and as="article"/"div" the title is
    ONE real <a class="card__link"> whose ::after is stretched over the card, so the whole card is one link with
    one accessible name while nested links and buttons stay usable (they are raised above the stretched area).
    Without a title the link is an empty overlay named by `label`. With as="a" the card itself is the link: never
    nest links or buttons inside it then.
    States: hover and keyboard focus = the edge turns ink and the title underlines (nothing moves: no lift, no
    shadow, D05); focus ring around the whole card when its link has keyboard focus. Night tone sets .on-night
    (light-gold focus ring inside). Styleguide-only modifiers: .is-hover, .is-focus.
--}}
@php
    $tag = in_array($as, ['article', 'a', 'div', 'li', 'section'], true) ? $as : 'article';
    if ($tag === 'a' && ! $href) {
        $tag = 'div';
    }
    $tone = in_array($tone, ['default', 'sunken', 'night'], true) ? $tone : 'default';
    $padding = in_array($padding, ['none', 'sm', 'md', 'lg'], true) ? $padding : 'md';
    $interactive = $interactive ?? (bool) $href;
    $stretched = $href && $tag !== 'a';
    $hasTitle = $title !== null && trim((string) $title) !== '';
    $level = (int) $titleLevel;
    $titleTag = $level >= 2 && $level <= 6 ? 'h'.$level : 'p';
    $classes = [
        'card',
        'card--'.$tone,
        'card--pad-'.$padding,
        'card--interactive' => $interactive,
        'card--link' => (bool) $href,
        'on-night' => $tone === 'night',
    ];
@endphp
<{{ $tag }} {{ $attributes->class($classes) }} @if ($tag === 'a') href="{{ $href }}" @endif>
    @isset($media)
        <div {{ $media->attributes->class(['card__media']) }}>{{ $media }}</div>
    @endisset
    @if ($hasTitle)
        <{{ $titleTag }} class="card__title">
            @if ($stretched)<a class="card__link" href="{{ $href }}">{{ $title }}</a>@else{{ $title }}@endif
        </{{ $titleTag }}>
    @elseif ($stretched)
        <a class="card__link card__link--bare" href="{{ $href }}" @if ($label) aria-label="{{ $label }}" @endif></a>
    @endif
    {{ $slot }}
    @isset($footer)
        <div {{ $footer->attributes->class(['card__footer']) }}>{{ $footer }}</div>
    @endisset
</{{ $tag }}>
