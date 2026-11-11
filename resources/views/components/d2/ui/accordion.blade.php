@props([
    'name' => null,        // exclusive group: every item gets <details name>, so one item is open at a time
    'level' => 3,          // default heading level of the item titles (2–4)
    'variant' => 'card',   // card (one white grouped surface) | flush (hairlines only, inside a card or column)
])
{{--
    <x-d2.ui.accordion name="faq-cat-01" level="3">
        <x-d2.ui.accordion-item id="faq-01" :title="$faq['question']">…approved answer…</x-d2.ui.accordion-item>
    </x-d2.ui.accordion>
    Items read `name` and `level` from this wrapper (@aware) unless they set their own. Built on
    <details>/<summary>: works without JavaScript; deep links (#faq-01) open the item
    (resources/js/d2/modules/accordion-hash.js).
--}}
@php
    $variant = in_array($variant, ['card', 'flush'], true) ? $variant : 'card';
@endphp
<div {{ $attributes->class(['acc', 'acc--'.$variant]) }}>
    {{ $slot }}
</div>
