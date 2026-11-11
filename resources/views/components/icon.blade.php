{{-- Inline element: @endif stays on the svg line so no whitespace is emitted around the icon. --}}@if ($body !== '')
<svg {{ $attributes->class(['icon', "icon--{$name}", 'icon--mirror' => $mirrored])->merge($defaults) }}>{!! $body !!}</svg>@endif
