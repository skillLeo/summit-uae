@props([
    'id' => 'documents',  // anchor (table of contents target): the DOCX "Document checklist" section
    'heading' => null,    // approved sections.documents.heading ("Documents That Help Us Get Started")
    'documents' => [],    // approved documents (4 per service; the Matter Navigator uses the same list)
    'note' => null,       // approved documents_note (originals, no passwords or access codes)
])
{{--
    <x-d2.service.documents id="documents" :heading="…" :documents="…" :note="…" />
    The service page's document checklist section (upgrade spec §12 D, §7.4), in the reading column where the DOCX
    puts it: after "How We Handle Your Matter", before "Before Taking the Next Step" (brief §5.3; it used to be the
    head's aside card, read before "Who This Service Is For"). A section of the reading column like the others
    (<x-d2.service.section>: the approved H2 in the kit's heading role, listed in "On this page"), then the four
    documents as one white panel of hairline-separated rows with plain bullets (a static checklist, not a sequence:
    no numbers; P02's "Before Requesting a Consultation" panel) and the approved note as a small info callout.
    Design 2.1 (brief §3.6 rule 5): no object icon beside the heading and no file glyph on the rows.
--}}
@php
    use App\Design\D2\Bidi;
    $documents = array_values(array_filter((array) $documents, fn ($doc) => is_string($doc) && trim($doc) !== ''));
@endphp
<x-d2.service.section :id="$id" :heading="$heading" {{ $attributes->class(['svc-docs']) }}>
    @if ($documents !== [])
        <x-d2.service.list :items="$documents" icon="dot" variant="rows" class="svc-docs__list" />
    @endif
    @if ($note !== null && trim((string) $note) !== '')
        <x-d2.ui.callout tone="info" size="sm" class="svc-docs__note">{{ Bidi::isolate($note) }}</x-d2.ui.callout>
    @endif
</x-d2.service.section>
