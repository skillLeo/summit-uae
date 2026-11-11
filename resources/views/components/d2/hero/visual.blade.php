@props([
    'active' => 'cheque',
    'idPrefix' => 'hero',
    'interactive' => true,   // false: a still specimen (styleguide) without the d2HeroStage script
    'phone' => false,        // styleguide: the phone layout (numbered markers and the legend) at any width
    'autoplay' => false,     // the home hero only: the samples change by themselves (the owner's decision; never on
                             // the styleguide, never under reduced motion, never without JavaScript)
])
{{--
    The hero's right column (owner: hero; Design 2.1 brief D15–D17, §6): <x-d2.hero.stage> (the illustration: the
    pile of the six sample documents, the one on top with its callouts), <x-d2.hero.notes> (the callout text: the
    legend under the document on phones, read by screen readers everywhere), <x-d2.hero.pager> (six progress
    segments, the document's name, pause / play, previous / next) and the caption (proposed; one sentence in the Small
    role, ink-3, without middle dots: D02, D17).
    Alpine d2HeroStage (resources/js/d2/components/hero.js) imports resources/js/d2/hero/stage.js on demand: the
    entrance (deal.js), the document switch and its callouts, the pager (pager.js), the autoplay (autoplay.js) and
    the pointer depth (hover.js), so no other page pays for them. data-autoplay holds the dwell (ms) once the
    document on top has settled and its callouts are drawn.
--}}
@php
    use App\Design\D2\Docs;

    $pagerTypes = array_reverse(Docs::TYPES);
    $active = in_array($active, $pagerTypes, true) ? $active : $pagerTypes[0];
@endphp
<div {{ $attributes->class(['hero-visual', 'hero-visual--phone' => $phone]) }} data-hero-visual @if ($phone) data-phone @endif
    @if ($autoplay) data-autoplay="{{ \App\Design\D2\HeroStage::DWELL }}" @endif @if ($interactive) x-data="d2HeroStage" @endif>
    <x-d2.hero.stage :types="$pagerTypes" :active="$active" :id-prefix="$idPrefix" />
    <x-d2.hero.notes :types="$pagerTypes" :active="$active" />
    <x-d2.hero.pager :types="$pagerTypes" :active="$active" :id-prefix="$idPrefix" :autoplay="$autoplay" />
    <p class="hero-visual__caption" @proposed>{{ d2p('hero.caption') }}</p>
</div>
