@props([
    'id',                     // the id named in the tabs list (and the deep-link anchor, e.g. cat-03)
    'title' => null,          // optional heading: visible without JavaScript, visually hidden once tabs run
    'titleLevel' => 2,        // heading level of the title (2–6)
])
{{-- One panel of <x-d2.ui.tabs>. Without JavaScript every panel is visible and the tabs link to them. --}}
@php
    $level = max(2, min(6, (int) $titleLevel));
@endphp
<div {{ $attributes->class(['tabs__panel']) }} id="{{ $id }}" data-tab-panel>
    @if ($title !== null && $title !== '')
        <h{{ $level }} class="tabs__panel-title">{{ $title }}</h{{ $level }}>
    @endif
    {{ $slot }}
</div>
