@props([
    'services' => [],          // App\Content\NavigatorData::services()
    'selected' => null,        // HomeController $selected: ['service' => 'S05', 'index' => 2] or null
    'prefix' => 'navigator',
    'only' => null,            // styleguide only: render just these service codes
    'ticked' => [],            // styleguide only: [code => [document indices]]
    'forced' => [],            // styleguide only: forced states of the actions
    'tip' => 'top',            // tooltip placement of the actions (styleguide: 'bottom', so a forced tip clears the CTA)
    'deferred' => true,        // other services' files wait in inert <template>s (false: render them all)
])
{{--
    "Your starting file" card (white on the dark band, radius 10, no shadow, padding 32): the empty state, one
    <x-d2.navigator.file> per service and the shared actions, plus the print-only head and foot (brand, date,
    approved disclaimer). .on-light switches the Phase A controls back to their light look.
    Design 2.1: no "YOUR STARTING FILE" kicker (D01: the sheet title still names the file below 1280 px) and no
    decorative icons in the empty state (D04).
    Only the chosen service's file is live DOM; the others are <template data-nav-file-template> that
    navigator/controller.js instantiates the first time their service is chosen (fewer DOM nodes; without
    JavaScript the GET form asks the server for the chosen file).
--}}
@php
    $selectedRef = is_array($selected) ? $selected['service'].'-'.$selected['index'] : null;
    $headings = $services[0]['headings'] ?? [];
@endphp
<div {{ $attributes->class(['nav-file', 'on-light']) }} id="{{ $prefix }}-file" data-nav-file>
    <div class="nav-file__print-head">
        <span class="nav-file__brand">{{ ui('brand.name') }}</span>
        <span class="nav-file__date" data-nav-print-date @proposed></span>
    </div>

    <div class="nav-file__empty" data-nav-file-empty @if ($selectedRef) hidden @endif>
        <p class="nav-file__empty-text" @proposed>{{ d2p('navigator.empty') }}</p>
        <div class="nav-file__preview">
            <p class="nav-file__preview-label" @proposed>{{ d2p('navigator.empty_contents') }}</p>
            <ul class="nav-file__preview-list" role="list">
                <li>{{ $headings['documents'] ?? '' }}</li>
                <li>{{ $headings['first_steps'] ?? '' }}</li>
                <li>{{ $headings['mistakes'] ?? '' }}</li>
            </ul>
        </div>
    </div>

    @foreach ($services as $s)
        @continue(is_array($only) && ! in_array($s['code'], $only, true))
        @php $isSelected = $selectedRef !== null && $s['code'] === $selected['service']; @endphp
        @if ($isSelected || ! $deferred)
            <x-d2.navigator.file :service="$s" :prefix="$prefix" :hidden="! $isSelected && $deferred"
                :situation="$isSelected ? ($s['situations'][$selected['index'] - 1] ?? null) : null"
                :situation-ref="$isSelected ? $selectedRef : null" :ticked="$ticked[$s['code']] ?? []" />
        @else
            <template data-nav-file-template="{{ $s['code'] }}"><x-d2.navigator.file :service="$s" :prefix="$prefix" :hidden="true" /></template>
        @endif
    @endforeach

    <x-d2.navigator.actions class="nav-file__actions" :code="$selected['service'] ?? null" :ref="$selectedRef" :forced="$forced" :tip="$tip"
        data-nav-file-actions :hidden="$selectedRef === null && $deferred" />

    <p class="nav-file__print-foot">{{ ui('footer.disclaimer') }}</p>
</div>
