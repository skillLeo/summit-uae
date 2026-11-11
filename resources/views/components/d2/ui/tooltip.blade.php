@props([
    'text',                   // tooltip text: extra information, NEVER the only label of the control
    'placement' => 'top',     // top (flips below near the top edge) | bottom
    'dir' => null,            // 'ltr' when the text is a phone number, e-mail, URL or Latin code
])
{{--
    <x-d2.ui.tooltip :text="setting('phone_display')" dir="ltr"><x-d2.ui.button …>…</x-d2.ui.button></x-d2.ui.tooltip>
    An overlay (data-overlay): ink bubble, caption 13/500, radius 16, the overlay shadow (Design 2.1).
    Wraps one trigger (the first focusable element inside). resources/js/d2/modules/tooltip.js shows the
    bubble 120 ms after hover or keyboard focus, hides it on leave, blur, press and Esc, keeps it inside
    the viewport (above, flipping below near the top edge) and wires aria-describedby to it unless the
    text only repeats the trigger's own name. The bubble is a manual popover (top layer): no ancestor
    transform, overflow or z-index can clip it. Without JavaScript nothing is shown (the control keeps its
    own label). Attribute variant for any element: data-tooltip="…" (+ data-tooltip-placement, data-tooltip-dir).
--}}
@php
    $placement = in_array($placement, ['top', 'bottom'], true) ? $placement : 'top';
    $tipId = 'd2-tip-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
@endphp
<span {{ $attributes->class(['tooltip-host']) }} data-tooltip-host>{{ $slot }}<span class="tooltip" role="tooltip" id="{{ $tipId }}" popover="manual" data-overlay data-placement="{{ $placement }}" @if ($dir) dir="{{ $dir }}" @endif>{{ $text }}</span></span>
