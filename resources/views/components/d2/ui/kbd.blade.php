@props([
    'keys' => null,          // a combination: ['Ctrl', 'K'] renders nested <kbd> keys; otherwise the slot is one key
])
{{--
    <x-d2.ui.kbd>/</x-d2.ui.kbd>   <x-d2.ui.kbd :keys="['Ctrl', 'K']" />
    Keycap (Design 2.1): the interface sans at 13/500, white, 1px --line-strong edge, radius 6, no shadow;
    left-to-right in Arabic as well. The header shows no key cap (D14): its search button names "/" in its tooltip.
--}}
@if (is_array($keys) && $keys !== [])
    <kbd {{ $attributes->class(['kbd-combo']) }} dir="ltr">@foreach ($keys as $key)<kbd class="kbd">{{ $key }}</kbd>@endforeach</kbd>
@else
    <kbd {{ $attributes->class(['kbd']) }} dir="ltr">{{ $slot }}</kbd>
@endif
