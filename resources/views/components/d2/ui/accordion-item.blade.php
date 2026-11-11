@aware(['name' => null, 'level' => 3])
@props([
    'id' => null,          // anchor kept from Design 1 (faq-01 …); #id opens the item (accordion-hash.js)
    'title' => null,       // the question / row title (string, or a named slot "title")
    'open' => false,
    'level' => 3,          // heading level of the title, 2–4 (default: the accordion's level)
    'name' => null,        // exclusive group name (default: the accordion's name)
])
{{--
    <details>/<summary>: the summary is a full-width row ≥ 48 px with the title as a real heading and
    a chevron-down that turns on open. Height springs open with interpolate-size + ::details-content
    (resources/css/d2/components/accordion.css); browsers without them open instantly, and reduced
    motion never animates. Styleguide-only modifiers on the item: .is-hover, .is-focus.
--}}
@php
    $level = max(2, min(4, (int) ($level ?: 3)));
@endphp
<details {{ $attributes->class(['acc__item']) }} @if ($id) id="{{ $id }}" @endif @if ($name) name="{{ $name }}" @endif @if ($open) open @endif>
    <summary class="acc__summary">
        <h{{ $level }} class="acc__title">{{ $title }}</h{{ $level }}>
        <span class="acc__chevron" aria-hidden="true"><x-d2.ui.icon name="chevron-down" size="20" /></span>
    </summary>
    <div class="acc__panel">
        {{ $slot }}
    </div>
</details>
