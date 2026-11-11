@props([
    'id',                       // section anchor (table of contents target)
    'heading' => null,          // H2 text (approved; proposed with :heading-proposed)
    'headingProposed' => false, // the heading is proposed copy (data-copy-status="proposed")
    'intro' => null,            // approved lead-in line under the heading (e.g. "This service may be relevant if:")
])
{{--
    A service-page section that is not plain DOCX prose (first steps / mistakes, Navigator situations): the
    same outline and look as an <x-d2.inner.prose> section (H2 with the kit's .prose__heading role, the same
    scroll margin under the header and the "On this page" bar), so the reading column stays one rhythm.
    Approved heading (or a proposed one, marked), an optional approved lead-in line under it, Latin runs isolated
    on Arabic pages.
--}}
@php
    use App\Design\D2\Bidi;
    $hasHeading = $heading !== null && trim((string) $heading) !== '';
@endphp
<section {{ $attributes->class(['svc-sec']) }} id="{{ $id }}" @if ($hasHeading) aria-labelledby="{{ $id }}-title" @endif>
    @if ($hasHeading)
        <h2 class="prose__heading svc-sec__title" id="{{ $id }}-title" @if ($headingProposed) @proposed @endif>{{ Bidi::isolate($heading) }}</h2>
    @endif
    @if (is_string($intro) && trim($intro) !== '')
        <p class="svc-sec__intro">{{ Bidi::isolate($intro) }}</p>
    @endif
    {{ $slot }}
</section>
