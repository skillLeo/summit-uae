{{--
    Design 2 home 03: Who We Help (upgrade spec §12 C7; Design 2.1 brief §5.1 item 4). id="who-we-help" (the
    Design 1 anchor). Approved P01 who_we_help copy only, in the DOCX order: the H2, the intro, then the five audience
    blocks (<x-d2.home.who-list>: title H3 + text each, all visible; the homepage DOCX's note for section 03 asks for
    five audience blocks). The blocks keep the Design 1 ids who-individuals, who-businesses, who-investors,
    who-residents and who-entrepreneurs. Design 2.1 Phase 2 retired the audience switch (only one block showed at a
    time, four sat behind a sideways-scrolling control cut at 390 px) and its service chips (they repeated the
    service cards, which the DOCX note asks this section not to do).
--}}
@php
    $whoSection = $c['who_we_help'] ?? [];
    $whoItems = array_values(array_filter($whoSection['items'] ?? [], fn ($item) => ($item['key'] ?? '') !== '' && ($item['title'] ?? '') !== ''));
@endphp
<section class="section who" id="{{ $sectionId ?? 'who-we-help' }}" aria-labelledby="who-title">
    <div class="container">
        <header class="section__head">
            <h2 id="who-title">{{ $whoSection['heading'] ?? '' }}</h2>
            @if (! empty($whoSection['intro']))
                <p>{{ $whoSection['intro'] }}</p>
            @endif
        </header>

        @if ($whoItems !== [])
            <x-d2.home.who-list :items="$whoItems" />
        @endif
    </div>
</section>
