@props([
    'block',
    'scope' => [],
])
{{--
    Library block "Image and text" (brief §8.4, §8.9): an image of the Media Library (its UUID; AVIF and WebP srcset, the
    alt text of the language, the focal point as object-position) beside an H2 and sanitised rich text. Added by an
    editor only. Never a stock or generated image (the client's rule): the editor uploads the client's own files.
--}}
@php
    use App\Content\Cms\Editing\RichText;
    use App\Content\Cms\MediaFiles;
    use App\Design\D2\Bidi;

    $d = (array) ($block['data'] ?? []);
    $bg = ['band' => 'section--band', 'dark' => 'section--dark'][$block['background']] ?? '';
    $id = $block['anchor'] ?? \Illuminate\Support\Str::slug((string) $block['id']);
    $uuid = is_string($d['image'] ?? null) ? $d['image'] : null;
    $src = MediaFiles::url($uuid, 'webp-1200') ?? MediaFiles::url($uuid);
    $alt = is_string($d['image_alt'] ?? null) && $d['image_alt'] !== '' ? $d['image_alt'] : MediaFiles::alt($uuid, app()->getLocale());
    $position = MediaFiles::objectPosition($uuid);
    $end = ($d['image_side'] ?? 'start') === 'end';
@endphp
<section class="section block-lib {{ $bg }} {{ $end ? 'block-lib--image-end' : '' }}" id="{{ $id }}" @if (filled($d['heading'] ?? null)) aria-labelledby="{{ $id }}-title" @endif data-copy-source="{{ $block['copy_source'] }}">
    <div class="container block-lib__grid">
        @if ($src !== null)
            <figure class="block-lib__figure">
                <picture>
                    @if (($avif = MediaFiles::srcset($uuid, 'avif')) !== '')<source type="image/avif" srcset="{{ $avif }}">@endif
                    @if (($webp = MediaFiles::srcset($uuid, 'webp')) !== '')<source type="image/webp" srcset="{{ $webp }}">@endif
                    <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" decoding="async" @if ($position) data-focus="{{ $position }}" @endif>
                </picture>
            </figure>
        @endif
        <div class="block-lib__body">
            @if (filled($d['heading'] ?? null))
                <h2 id="{{ $id }}-title">{{ Bidi::isolate((string) $d['heading']) }}</h2>
            @endif
            {!! RichText::clean(is_string($d['body'] ?? null) ? $d['body'] : '') !!}
        </div>
    </div>
</section>
