@props([
    'target' => null,  // CSS selector of the page's hero: the bar shows once it has scrolled out of view
    'until' => null,   // optional CSS selector: the bar hides once this element is reached (the page's own form or end CTA)
    'prefill' => [],   // wizard prefill (App\Design\D2\Prefill::KEYS), e.g. ['service' => 'S06']
    'label' => null,   // default: the approved "Request a Legal Consultation" (ui('cta.request_consultation'))
    'static' => false, // styleguide specimen: drawn in place, inert, never fixed or hidden
])
{{--
    <x-d2.shell.sticky-cta target="#service-hero" until="#service-cta" :prefill="['service' => 'S06']" />

    Design 2.1 (brief §6 "Mobile", one bottom bar on phones): where the tab bar exists (every page of the Design 2
    layout) this bar is never drawn (components/sticky-cta.css, modules/sticky-cta.js), so it never stacks on the tab
    bar; the service pages keep their own consultation CTAs and the floating WhatsApp button. The rest describes the
    bar on a page without the tab bar.

    Sticky consultation bar below 768 px (upgrade spec §8 "Sticky CTA", §12 E): a 56 px solid white bar
    (--sticky-cta-h, a 1 px top line; no glass or blur, Design 2.1 brief §3.6) on the bottom edge, holding one
    full-width Ink button (the default variant; gold stays the hero primary's) with the approved consultation
    label and the page's prefill. It appears once the element named by `target` has scrolled out of view
    (IntersectionObserver), hides again once the `until` element is reached (on screen or scrolled past; back
    above it, the bar returns), while a text field has focus or the on-screen keyboard is open, and is never
    drawn from 768 px. Sheets and dialogs cover it (the page is inert behind them). Without JavaScript it is not
    drawn: every page keeps its own CTAs.

    Rendered at body level (pushed to the layout's "sheets" stack), so no transformed ancestor can trap the
    fixed bar; include it once per page, anywhere in the page view.
    Link: App\Design\D2\Prefill::url($prefill) (no JavaScript: the consultation form with the query); with
    JavaScript a plain click hands the same values to lib/prefill.js (scroll to the wizard on its own page).
    Styles: resources/css/d2/components/sticky-cta.css; behaviour: resources/js/d2/modules/sticky-cta.js.
--}}
@php
    use App\Design\D2\Prefill;

    $label ??= ui('cta.request_consultation');
    $params = array_filter(
        array_intersect_key((array) $prefill, array_flip(Prefill::KEYS)),
        fn ($value) => is_string($value) && $value !== '',
    );
    $href = Prefill::url($params);
@endphp
@if ($static)
    <div {{ $attributes->class(['sticky-cta', 'sticky-cta--static']) }} inert aria-hidden="true">
        <div class="sticky-cta__inner">
            <x-d2.ui.button class="sticky-cta__button" variant="ink" :full="true" :href="$href">{{ $label }}</x-d2.ui.button>
        </div>
    </div>
@else
    @push('sheets')
        <div {{ $attributes->class(['sticky-cta']) }} data-sticky-cta
            @if ($target) data-target="{{ $target }}" @endif
            @if ($until) data-until="{{ $until }}" @endif
            @if ($params !== []) data-prefill="{{ json_encode($params, JSON_UNESCAPED_SLASHES) }}" @endif>
            <div class="sticky-cta__inner">
                <x-d2.ui.button class="sticky-cta__button" variant="ink" :full="true" :href="$href" data-sticky-cta-link>{{ $label }}</x-d2.ui.button>
            </div>
        </div>
    @endpush
@endif
