@props([
    'tool' => null,   // gratuity | rent | null (the page itself)
])
{{--
    <x-d2.tool.link />                 home section 09: the calculators page
    <x-d2.tool.link tool="gratuity" />  Employment Law (S06): the page at #tool-gratuity
    <x-d2.tool.link tool="rent" />      Property and Tenancy (S05): the page at #tool-rent

    A text link to the calculators page (Design 2.1 brief §5.1: "linked from section 09 and from the Employment Law and
    Property and Tenancy pages"). The page and its estimators are pending client approval, so the link renders only
    while App\Design\D2\Calculators::linked() (Pending::visible(); off in production) and its label is a proposed key
    (tools.links.*). A body link (ink, underlined); the paragraph takes the spacing of the block it sits in.
--}}
@php
    use App\Design\D2\Calculators;

    $tool = in_array($tool, ['gratuity', 'rent'], true) ? $tool : null;
@endphp
@if (Calculators::linked())
    <p {{ $attributes->class(['tool-link']) }} data-calculators-link="{{ $tool ?? 'page' }}" @proposed>
        <a class="link" href="{{ Calculators::url(null, $tool ? 'tool-'.$tool : null) }}">{{ d2p('tools.links.'.($tool ?? 'page')) }}</a>
    </p>
@endif
