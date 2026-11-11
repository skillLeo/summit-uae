@props([
    'block',
    'scope' => [],
])
{{-- Library block "Two columns" (brief §8.4): an H2 and two columns of sanitised rich text. Added by an editor only. --}}
@php
    use App\Content\Cms\Editing\RichText;
    use App\Design\D2\Bidi;

    $d = (array) ($block['data'] ?? []);
    $bg = ['band' => 'section--band', 'dark' => 'section--dark'][$block['background']] ?? '';
    $id = $block['anchor'] ?? \Illuminate\Support\Str::slug((string) $block['id']);
@endphp
<section class="section block-lib {{ $bg }}" id="{{ $id }}" @if (filled($d['heading'] ?? null)) aria-labelledby="{{ $id }}-title" @endif data-copy-source="{{ $block['copy_source'] }}">
    <div class="container block-lib__grid">
        @if (filled($d['heading'] ?? null))
            <div class="block-lib__head"><h2 id="{{ $id }}-title">{{ Bidi::isolate((string) $d['heading']) }}</h2></div>
        @endif
        <div class="block-lib__cols">
            <div class="block-lib__body">{!! RichText::clean(is_string($d['start'] ?? null) ? $d['start'] : '') !!}</div>
            <div class="block-lib__body">{!! RichText::clean(is_string($d['end'] ?? null) ? $d['end'] : '') !!}</div>
        </div>
    </div>
</section>
