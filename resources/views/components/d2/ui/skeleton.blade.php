@props([
    'shape' => 'text',        // text | title | block | circle | card | row
    'lines' => 3,             // text: number of lines (the last one is shorter)
    'size' => 48,             // circle: diameter, one of 32 | 40 | 48 | 64 | 96 (no inline styles: CSP)
    'label' => null,          // what is loading, for screen readers (default: the proposed "Loading")
])
{{--
    ONLY for real loading (results that are on their way), never as decoration: a skeleton must never
    stand in for content that is already there (the Design 1 "grey rectangles" lesson).
    <x-d2.ui.skeleton shape="card" />    <x-d2.ui.skeleton lines="2" :label="…" />
    Design 2.1 (docs/d2.1/phase-1/components.md §2): the bones mirror text-only content, so card and row draw
    a title and lines (no icon tile, no avatar circle: cards, menu items and result rows carry no decorative
    icon, D04); shape="circle" stays for a real round image. --band bones breathe with an opacity pulse (no
    gradient sweep); still under reduced motion. The wrapper is a polite status with a visually hidden label;
    the bones are hidden from assistive technology. The container that is loading should also carry
    aria-busy="true" until the content arrives.
--}}
@php
    $shape = in_array($shape, ['text', 'title', 'block', 'circle', 'card', 'row'], true) ? $shape : 'text';
    $lines = max(1, min(8, (int) $lines));
    $size = in_array((int) $size, [32, 40, 48, 64, 96], true) ? (int) $size : 48;
@endphp
<div {{ $attributes->class(['skeleton', 'skeleton--'.$shape]) }} role="status">
    <span class="sr-only" @if ($label === null) @proposed @endif>{{ $label ?? d2p('surfaces.skeleton.loading') }}</span>
    @switch($shape)
        @case('title')
            <span class="skeleton__bone skeleton__bone--title" aria-hidden="true"></span>
            @break
        @case('block')
            <span class="skeleton__bone skeleton__bone--block" aria-hidden="true"></span>
            @break
        @case('circle')
            <span class="skeleton__bone skeleton__bone--circle skeleton__bone--s{{ $size }}" aria-hidden="true"></span>
            @break
        @case('card')
            <span class="skeleton__bone skeleton__bone--title" aria-hidden="true"></span>
            <span class="skeleton__lines" aria-hidden="true">
                <span class="skeleton__bone skeleton__bone--line"></span>
                <span class="skeleton__bone skeleton__bone--line"></span>
            </span>
            @break
        @case('row')
            <span class="skeleton__lines" aria-hidden="true">
                <span class="skeleton__bone skeleton__bone--line"></span>
                <span class="skeleton__bone skeleton__bone--line"></span>
            </span>
            @break
        @default
            <span class="skeleton__lines" aria-hidden="true">
                @for ($i = 0; $i < $lines; $i++)
                    <span class="skeleton__bone skeleton__bone--line"></span>
                @endfor
            </span>
    @endswitch
</div>
