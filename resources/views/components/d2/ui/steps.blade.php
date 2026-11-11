@props([
    'steps' => [],           // [label, …] in order
    'current' => 1,          // the current step NUMBER: 1 = the first step (count($steps) + 1 = all done)
    'label' => null,         // accessible name of the step list (e.g. the wizard's name)
    'interactive' => false,  // completed steps become buttons with data-step="n" (going back)
    'proposed' => false,     // the step names are proposed copy (the wizard's): marked data-copy-status="proposed"
])
{{--
    <x-d2.ui.steps :steps="[$you, $matter, $deadline, $documents, $review]" :current="2" />
    The wizard's ONE progress indicator (Design 2.1, D37; brief §3.6 rule 4 allows numbers on form steps):
      wide    a step list: each step's number (a check once done) and name on one row, joined by hairlines;
              done steps ink-2, the current one ink with aria-current="step", upcoming ones ink-3
      phones  one line, "Step 1 of 5: You" (below 768 px, or in a narrow container: components/wizard.css)
    No chips and no progress bar. The phone line repeats the list for sighted visitors only (aria-hidden): the
    list stays the structure, and the wizard's step heading announces "Step n of N:" itself.
    Interactive: completed steps are buttons with data-step="n" (the wizard moves back to them).
--}}
@php
    $steps = array_values($steps);
    $total = count($steps);
    $current = max(1, min((int) $current, $total + 1));
@endphp
<div {{ $attributes->class(['step-list']) }} data-current="{{ $current }}">
    <ol class="step-list__items" @if (filled($label)) aria-label="{{ $label }}" @endif>
        @foreach ($steps as $i => $text)
            @php
                $n = $i + 1;
                $state = $n < $current ? 'done' : ($n === $current ? 'current' : 'todo');
                $tag = $interactive && $state === 'done' ? 'button' : 'span';
            @endphp
            <li class="step-list__item is-{{ $state }}" @if ($state === 'current') aria-current="step" @endif>
                <{{ $tag }} class="step-list__step" @if ($tag === 'button') type="button" data-step="{{ $n }}" @endif>
                    <span class="step-list__num" aria-hidden="true">@if ($state === 'done')<x-d2.ui.icon name="check" size="16" />@else{{ $n }}@endif</span>
                    <span class="step-list__label" @if ($proposed) @proposed @endif>{{ $text }}</span>
                    @if ($state === 'done')<span class="sr-only" @proposed>{{ d2p('controls.step_done') }}</span>@endif
                </{{ $tag }}>
            </li>
        @endforeach
    </ol>
    @if ($current <= $total)
        <p class="step-list__now" aria-hidden="true"><span @proposed>{{ d2p('controls.step_of', ['current' => $current, 'total' => $total]) }}</span>: <span @if ($proposed) @proposed @endif>{{ $steps[$current - 1] }}</span></p>
    @endif
</div>
