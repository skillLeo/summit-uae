@props([
    'steps' => [],        // approved P01 review.steps: [['title', 'text'], …]
    'labelledby' => null, // id of the heading that names the list
])
{{--
    The four approved steps of "How We Work on Your Matter" (upgrade spec §12 C4; Design 2.1 D49;
    resources/css/d2/sections/method.css): an ordered list, each step its marker 01-04 in the interface sans
    (decorative: the list already numbers the steps for assistive technology; numbers are allowed on the four
    workflow steps, brief §3.6 rule 4), its approved title (H4, under the H3 "How We Work on Your Matter") and
    text. Not cards (nothing is interactive, brief §3.5); a quiet line joins the markers. One row of four from
    1024 px, 2 x 2 from 768 px (side by side, the steps share their rows through subgrid, so every text starts on
    one line whatever its title's length), stacked below. Modifier class method__steps--stacked: the stacked
    layout at every width (styleguide specimen).
--}}
@php
    use App\Design\D2\Bidi;
@endphp
<ol {{ $attributes->class(['method__steps']) }} role="list" @if ($labelledby) aria-labelledby="{{ $labelledby }}" @endif>
    @foreach (array_values($steps) as $i => $step)
        <li class="method-step">
            <span class="method-step__num" aria-hidden="true">{{ sprintf('%02d', $i + 1) }}</span>
            <h4 class="method-step__title">{{ Bidi::isolate($step['title'] ?? '') }}</h4>
            <p class="method-step__text">{{ Bidi::isolate($step['text'] ?? '') }}</p>
        </li>
    @endforeach
</ol>
