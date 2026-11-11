@props([
    'types' => null,          // documents in pager order (default: App\Design\D2\Docs::TYPES reversed)
    'active' => 'cheque',
])
{{--
    The callout text of the document shown, as a numbered list (owner: hero; D16). One list per document; only the
    shown one is visible (hero/stage.js swaps them).
      < 768 px   the legend under the document: its numbers match the stage's markers;
      ≥ 768 px   visually hidden (the labels beside the document draw the same text; they are aria-hidden, so
                 screen readers read this list).
    Labels are the documents' own proposed callout copy (App\Design\D2\Docs::callouts()).
--}}
@php
    use App\Design\D2\Docs;

    $types = $types ?? array_reverse(Docs::TYPES);
@endphp
<div {{ $attributes->class(['hero-notes']) }} data-hero-notes>
    @foreach ($types as $type)
        <ol class="hero-notes__list" data-notes="{{ $type }}" aria-label="{{ d2p('hero.callouts_label') }}: {{ Docs::name($type) }}" @proposed
            @if ($type !== $active) hidden @endif>
            @foreach (Docs::callouts($type) as $c)
                <li class="hero-notes__item">{{ \App\Design\D2\Bidi::isolate($c['label']) }}</li>
            @endforeach
        </ol>
    @endforeach
</div>
