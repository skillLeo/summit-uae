@props([
    'items' => [],          // approved P01 who_we_help.items: [['key', 'title', 'text'], …]
    'idPrefix' => 'who',    // block ids {prefix}-{key} (home: the Design 1 anchors who-individuals …)
])
{{--
    Who We Help: the five approved audience blocks (Design 2.1, homepage DOCX note of section 03: "Use five audience
    blocks. These explain who the work serves, rather than repeating the service cards."; brief §5.1 item 4;
    resources/css/d2/sections/who.css). All five are visible and complete in the HTML, in the DOCX order: each block
    is its approved title (H3) and text, nothing else (no service chips: those repeated the service cards, and the
    same label on every block was repeated metadata, §3.6 rule 10). Not a card: nothing here is interactive (§3.5).
    Each block keeps its Design 1 anchor (#who-individuals, #who-businesses, #who-investors, #who-residents,
    #who-entrepreneurs), so links to an audience land on it without a script.
--}}
@php
    use App\Design\D2\Bidi;
@endphp
<ul {{ $attributes->class(['who__list']) }} role="list">
    @foreach ($items as $item)
        @php $blockId = $idPrefix.'-'.$item['key']; @endphp
        <li class="who__item" id="{{ $blockId }}">
            <h3 class="who__title" id="{{ $blockId }}-title">{{ Bidi::isolate($item['title']) }}</h3>
            <p class="who__text">{{ Bidi::isolate($item['text'] ?? '') }}</p>
        </li>
    @endforeach
</ul>
