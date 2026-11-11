@props([
    'items' => [],       // approved list items (strings), in DOCX order
    'icon' => 'check',   // marker: check (useful steps) · close (mistakes to avoid) · dot (a plain bullet)
    'tone' => 'bronze',  // success · danger · muted (bronze: legacy name of muted): colour of the marker (the words carry the meaning)
    'variant' => 'plain', // plain (compact stack) · rows (one white card with hairline-separated rows)
])
{{--
    An approved list with markers (service pages: "Useful First Steps" with checks, "Mistakes to Avoid" with
    crosses; P02: "We Start with the File" with bullets, "Before Requesting a Consultation" with checks).
    role="list" keeps the list semantics in Safari/VoiceOver (list-style is none). Markers are aria-hidden
    and one text line tall, so they sit on the first line in every language; check marks never mirror in RTL.
    Design 2.1 (brief §3.5, §3.6 rule 5): check and cross are the only icons a list may carry (useful steps,
    mistakes to avoid), drawn as plain glyphs (no tinted disc); any other list takes a plain bullet. Latin runs
    are isolated in Arabic.
--}}
@php
    use App\Design\D2\Bidi;
    $items = array_values(array_filter((array) $items, fn ($item) => is_string($item) && trim($item) !== ''));
    $icon = in_array($icon, ['check', 'close'], true) ? $icon : 'dot';
    $tone = in_array($tone, ['success', 'danger'], true) ? $tone : 'muted';
    $variant = $variant === 'rows' ? 'rows' : 'plain';
@endphp
@if ($items !== [])
    <ul {{ $attributes->class(['svc-list', 'svc-list--'.$variant, 'svc-list--'.$tone]) }} role="list">
        @foreach ($items as $item)
            <li class="svc-list__item">
                <span class="svc-list__mark" aria-hidden="true">
                    @if ($icon === 'dot')
                        <span class="svc-list__dot"></span>
                    @else
                        <x-d2.ui.icon :name="$icon" size="20" />
                    @endif
                </span>
                <span class="svc-list__text">{{ Bidi::isolate($item) }}</span>
            </li>
        @endforeach
    </ul>
@endif
