{{--
    Design 2 home block "Start your matter" (Design 2.1 brief §5.1: a switchable tool block between approved
    sections, by default directly under the hero; copy pending client approval). The card itself is
    <x-d2.hero.tool> (resources/views/components/d2/hero/tool.blade.php, script resources/js/d2/hero/tool.js,
    styles sections/start.css). Rendered while its home block is on (App\Design\D2\Pending::shows()). D19: before a
    choice the card's result half shows the quick picks (the featured situations of the CMS), after a choice the
    documents.
--}}
@php
    use App\Design\D2\Prefill;

    // A no-JS round trip of the card (?audience=…&situation=…) keeps the visitor's choice.
    $prefill = Prefill::fromRequest();
@endphp
<section class="section start" id="{{ $sectionId ?? 'start' }}" aria-labelledby="hero-tool-title">
    <div class="container">
        <x-d2.hero.tool class="start__tool" :services="$navServices ?? null" :audience="$prefill['audience'] ?? 'individual'" :situation="$prefill['situation'] ?? null" />
    </div>
</section>
