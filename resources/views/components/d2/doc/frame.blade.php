@props([
    'type',                 // cheque | memo | contract | tenancy | transfer | messages | notice
    'variant' => 'stage',   // stage | mobile | still | anatomy (scale preset, see App\Design\D2\Docs)
    'watermark' => true,
])
{{--
    Shared root of every sample document (internal: use <x-d2.doc.cheque/> and friends). Contract:
      .doc.doc--{type}.doc--{variant}[data-doc][data-variant][data-callouts]
        .doc__shadow--rest   resting paper shadow (opacity 1)     ┐ animate their opacity, never box-shadow;
        .doc__shadow--air    lifted shadow (opacity 0 at rest)    ┘ slot "shapes" draws composite silhouettes
        .doc__body           the paper at its natural size, scaled by --doc-scale (aria-hidden illustration)
          …slot…, .doc__wm   the trilingual watermark, never below 9 px on screen
    The root reserves its own width and height at the variant's scale. Its content is aria-hidden: the
    container that shows a document gives it role="img" and an aria-label (App\Design\D2\Docs::alt()).
    data-illustration (Design 2.1 brief §7.1): the document's typed-field mono, its serif and naskh, paper
    shadows, gradients, small print, capitals and tracking stay inside this root; the interface lint skips it.
--}}
@php
    use App\Design\D2\Docs;

    $variant = Docs::variant($variant);
    $json = Docs::calloutsJson($type);
@endphp
@pushOnce('head')
    {{-- Paper texture: url() here resolves against the page origin (img-src 'self'), also behind the Vite dev
         server. A border-image fill, not a background: background (and mask) images are LCP candidates. --}}
    <style nonce="{{ Vite::cspNonce() }}">@layer components{.doc__sheet::before{border-image-source:url("/images/d2/paper-512.avif")}}</style>
@endPushOnce
<div {{ $attributes->class(['doc', 'doc--'.$type, 'doc--'.$variant]) }} data-doc="{{ $type }}" data-variant="{{ $variant }}" data-callouts='{!! $json !!}' data-illustration>
    <div class="doc__shadow doc__shadow--rest" aria-hidden="true">{{ $shapes ?? '' }}</div>
    <div class="doc__shadow doc__shadow--air" aria-hidden="true">{{ $shapes ?? '' }}</div>
    <div class="doc__body" aria-hidden="true">
        {{ $slot }}
        @if ($watermark)
            {{-- Generated content (::before): decorative text inside the aria-hidden illustration, faint by design. --}}
            <span class="doc__wm">
                @foreach (Docs::watermark() as $line)
                    <span class="doc__wm-line" lang="{{ $line['lang'] }}" dir="{{ $line['dir'] }}" data-wm="{{ $line['text'] }}"></span>
                @endforeach
            </span>
        @endif
    </div>
</div>
