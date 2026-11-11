{{--
    Design 2 home hero 01 (upgrade spec §4.2-4.4, §7.1-7.2 hero rows, §12 B1-B5; docs/d2/engineering.md; Design 2.1
    brief §3, §5.1 item 2, fixes D01-D18). Owner: hero.

    Left (columns 1-6; first in reading order): the approved H1 (the LCP element, never hidden or animated), the two
    approved hero paragraphs and the approved CTAs (Request a Legal Consultation → the consultation form, the page's
    one Gold button, flat (D06); WhatsApp with the approved per-language draft, opened in a new tab, never sent; both
    52 px). Nothing else: no eyebrow (D01), no office hours and no live status (D18: they stay in the contact block
    and the footer). "Start your matter" is its own block under the hero (sections/home/start.blade.php, §5.1).
    Right (columns 7-12): the document stage, pager and caption (<x-d2.hero.visual>).
    Below 1280 px everything stacks: copy, then stage, pager and captions.

    Motion (hero/deal.js) is armed by the boot script (shell/boot.blade.php: html.hero-armed, removed by a
    2.5 s safety timeout); without JavaScript or with reduced motion the composed final state shows at once.
--}}
@php
    use App\Support\SiteUrl;

    $h1 = $page['h1'] ?? '';
    // The approved H1 is set on two lines at its sentence boundary; the wording is unchanged.
    $lines = preg_split('/(?<=[.!?؟])\s+/u', trim($h1), 2) ?: [$h1];
    $hero = $c['hero'] ?? [];
    // The stage script (deal, callouts, pager, parallax) is a lazy chunk: preload it on the home page only,
    // so it downloads with app.js instead of after it and the deal can keep the storyboard's clock.
    try {
        $stageChunk = \Illuminate\Support\Facades\Vite::useHotFile(public_path((string) config('design.d2.hot_file')))
            ->useBuildDirectory((string) config('design.d2.build_directory'))
            ->asset('resources/js/d2/hero/stage.js');
    } catch (\Throwable) {
        $stageChunk = null;
    }
@endphp
@if ($stageChunk)
    @push('head')
        <link rel="modulepreload" href="{{ $stageChunk }}">
    @endpush
@endif
@if (is_file(public_path('images/d2/paper-512.avif')))
    @push('head')
        {{-- hero-perf: the sample documents' paper texture (a border-image fill, found only once the stylesheet is
             applied) loads with the head where the stage is in the first screen, so the documents are complete at the
             first paint (reduced motion: nothing changes afterwards) and fly in finished. --}}
        <link rel="preload" href="/images/d2/paper-512.avif" as="image" type="image/avif" media="(width >= 768px)">
    @endpush
@endif
<section class="section hero" aria-labelledby="hero-title" data-hero>
    <div class="container hero__grid">
        <div class="hero__copy">
            {{-- One text block (a <br> between the sentences, no block spans) so the H1 itself is the LCP element. --}}
            <h1 class="hero__title" id="hero-title">@foreach ($lines as $i => $line)@if ($i > 0)<br>@endif<span class="hero__line">{{ $line }}</span>@endforeach</h1>
            <div class="hero__text">
                @if (! empty($hero['lead']))
                    <p>{{ \App\Design\D2\Bidi::isolate($hero['lead']) }}</p>
                @endif
                @if (! empty($hero['body']))
                    <p>{{ \App\Design\D2\Bidi::isolate($hero['body']) }}</p>
                @endif
            </div>
            <div class="hero__actions">
                {{-- The page's one Gold button (flat --gold, D06); every other button on the home is Ink or Secondary.
                     Both CTAs are the 52 px hero size (24 px sides, a 16/500 label: brief §3.5). --}}
                <x-d2.ui.button class="hero__cta" variant="gold" size="52" :href="SiteUrl::consultation()">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
                <x-d2.ui.button class="hero__cta" variant="whatsapp" size="52" :href="setting('whatsapp_url')">{{ ui('cta.whatsapp') }}</x-d2.ui.button>
            </div>
        </div>

        <x-d2.hero.visual class="hero__visual" />
    </div>
</section>
