@props([
    'code' => null,            // service code of the starting file (prefill)
    'ref' => null,             // situation reference (prefill)
    'tip' => 'top',            // no effect since Design 2.1 (labelled buttons, no tooltips); kept so callers stay valid
    'forced' => [],            // styleguide only: [action => 'hover' | 'focus']
])
{{--
    Starting-file actions: Request (an Ink button: the hero's request is the page's one Gold button, Design 2.1
    D06; JavaScript calls prefill({service, situation}) and moves to the wizard, without JavaScript the link
    carries ?service=…&situation=…#consultation-form), Print (prints only the starting file) and Copy link
    (#navigator=S05-2, toast "Link copied") as labelled Secondary buttons (Design 2.1: print and link glyphs are
    not in the brief's functional icon list, §3.6 rule 5, so the proposed labels show instead of icons and
    tooltips). Print and Copy need JavaScript and are hidden without it. In the sheet the Request button lives in
    the sheet footer and this row keeps only Print and Copy. navigator/controller.js binds [data-nav-action].
--}}
@php
    $href = \App\Design\D2\Prefill::url(['service' => $code, 'situation' => $ref]);
    $cs = app()->isProduction() ? null : 'proposed';
    $state = fn (string $action) => \Illuminate\Support\Arr::toCssClasses(['is-hover' => ($forced[$action] ?? null) === 'hover', 'is-focus' => ($forced[$action] ?? null) === 'focus']) ?: null;
@endphp
<div {{ $attributes->class(['nav-actions']) }}>
    <x-d2.ui.button variant="ink" :href="$href" class="nav-actions__request" data-nav-action="request" data-nav-request>{{ ui('cta.request_consultation') }}</x-d2.ui.button>
    <span class="nav-actions__tools">
        <x-d2.ui.button variant="secondary" data-nav-action="print" :data-copy-status="$cs" :class="$state('print')">{{ d2p('navigator.print') }}</x-d2.ui.button>
        <x-d2.ui.button variant="secondary" data-nav-action="copy" :data-copy-status="$cs" :class="$state('copy')">{{ d2p('navigator.copy_link') }}</x-d2.ui.button>
    </span>
</div>
