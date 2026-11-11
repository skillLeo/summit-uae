@props([
    'image',                  // App\Design\D2\Cover::image(): ['src', 'alt', 'width', 'height']
])
{{--
    <x-d2.guide.cover :image="Cover::image($guide)" />
    A guide's REAL cover (Design 2.1 D27): the image the admin uploads (Phase 4). Drawn only when one is set, in
    the card's media area (2:1, cropped to fill: sections/guides.css). No generated covers: a pattern or an icon
    in a circle is a fake thumbnail (brief §3.6 rule 9). The alt text is empty unless the admin wrote one (the
    card's title names the card).
--}}
<img {{ $attributes->class(['guide-cover']) }} src="{{ $image['src'] }}" alt="{{ $image['alt'] ?? '' }}"
    @if (! empty($image['width'])) width="{{ $image['width'] }}" @endif
    @if (! empty($image['height'])) height="{{ $image['height'] }}" @endif
    loading="lazy" decoding="async">
