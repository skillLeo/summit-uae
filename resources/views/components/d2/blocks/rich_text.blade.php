@props([
    'block',        // content()->blocks('P01') item: id, type, enabled, anchor, background, copy_source, data
    'scope' => [],
])
{{--
    Library block "Rich text" (docs/briefs/design-2.1-and-admin-cms.md §8.4): an H2 and sanitised rich text (p, h2, h3,
    lists, links, bold, italic, quote; App\Content\Cms\Editing\RichText, again on render). Added by an editor only.
--}}
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
        <div class="block-lib__body">{!! RichText::clean(is_string($d['body'] ?? null) ? $d['body'] : '') !!}</div>
    </div>
</section>
