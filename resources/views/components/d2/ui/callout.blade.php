@props([
    'tone' => 'info',         // info | warning | success | danger
    'title' => null,          // optional title (approved copy only)
    'titleLevel' => null,     // 2–6 makes the title a real heading (scope notes); null keeps it a <p>
    'icon' => null,           // override the tone icon (info, warning, check, error: functional icons only)
    'size' => 'md',           // md (body text) | sm (Small text, for compact notes)
])
{{--
    <x-d2.ui.callout tone="info" :title="$scope['heading']" title-level="2">…approved paragraphs…</x-d2.ui.callout>
    Design 2.1 (docs/d2.1/phase-1/components.md §2): a panel (radius 10) for disclaimers, scope notes and form
    notices: the tone's icon (info, check, warning, error), an optional title and the text. info is the neutral
    --band panel; success, warning and danger take their badge pair's background. A static note: it is not a live
    region (add role="status" / role="alert" yourself when you insert one in response to an action).
    Tone is carried by the icon, the title and the words, never by colour alone. Turns dark inside .on-dark.
--}}
@php
    $tone = in_array($tone, ['info', 'warning', 'success', 'danger'], true) ? $tone : 'info';
    $size = $size === 'sm' ? 'sm' : 'md';
    $icon ??= ['info' => 'info', 'warning' => 'warning', 'success' => 'check', 'danger' => 'error'][$tone];
    $level = (int) $titleLevel;
    $titleTag = $level >= 2 && $level <= 6 ? 'h'.$level : 'p';
    $hasTitle = $title !== null && trim((string) $title) !== '';
@endphp
<div {{ $attributes->class(['callout', 'callout--'.$tone, 'callout--sm' => $size === 'sm', 'callout--titled' => $hasTitle]) }}>
    <span class="callout__icon" aria-hidden="true"><x-d2.ui.icon :name="$icon" size="20" /></span>
    <div class="callout__body">
        @if ($hasTitle)
            <{{ $titleTag }} class="callout__title">{{ $title }}</{{ $titleTag }}>
        @endif
        <div class="callout__text">{{ $slot }}</div>
    </div>
</div>
