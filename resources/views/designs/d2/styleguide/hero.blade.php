{{--
    Styleguide: Design 2 home hero (owner: hero). Annotations are internal: lang/en/styleguide_d2/hero.php.
    Demo content is approved copy (Navigator situations and documents) plus the hero's and the sample
    documents' proposed copy. The stage specimens are live (Alpine d2HeroStage) in their final state: the pager
    switches documents; the entrance plays only on the home page (the boot script arms it there).
--}}
@php
    use App\Design\D2\Docs;

    $t = fn (string $key) => __('styleguide_d2/hero.'.$key);
    $en = app()->isLocale('en') ? '' : 'lang="en"';
    $services = (new \App\Content\NavigatorData(content()))->services();
    $sample = Docs::callouts('cheque')[0]['label'] ?? '';
    // The other direction: Arabic on English / Russian pages, English on the Arabic page.
    $here = app()->getLocale();
    $other = $here === 'ar' ? 'en' : 'ar';
@endphp
<section class="sg-section" id="sg-hero">
    <h2 {!! $en !!}>{{ $t('title') }}</h2>
    <p {!! $en !!}>{{ $t('intro') }}</p>

    {{-- ------------------------------------------------------------------ stage --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('stage') }}</h3>
    <div class="sg-grid sg-grid--full">
        <div class="sg-specimen sg-specimen--clip">
            <x-d2.hero.visual id-prefix="sg-hero-stage" />
            <p class="sg-label" {!! $en !!}>{{ $t('stage_final') }}</p>
        </div>
        <div class="sg-specimen sg-specimen--clip">
            @php app()->setLocale($other); @endphp
            <div lang="{{ $other }}" dir="{{ $other === 'ar' ? 'rtl' : 'ltr' }}">
                <x-d2.hero.visual id-prefix="sg-hero-other" />
            </div>
            @php app()->setLocale($here); @endphp
            <p class="sg-label" {!! $en !!}>{{ $t('stage_other') }}</p>
        </div>
    </div>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen sg-specimen--clip">
            <x-d2.hero.visual id-prefix="sg-hero-phone" :phone="true" />
            <p class="sg-label" {!! $en !!}>{{ $t('stage_phone') }}</p>
        </div>
        <div class="sg-specimen">
            <p class="sg-label" {!! $en !!}>{{ $t('deal') }}</p>
            <ul class="stack" {!! $en !!}>
                @foreach ($t('deal_items') as $item)
                    <li class="t-meta">{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ callouts --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('callouts') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('callouts_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <x-d2.hero.visual id-prefix="sg-hero-contract" active="contract" />
            <p class="sg-label" {!! $en !!}>{{ $t('callouts_contract') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.hero.visual id-prefix="sg-hero-notice" active="notice" />
            <p class="sg-label" {!! $en !!}>{{ $t('callouts_notice') }}</p>
        </div>
        <div class="sg-specimen">
            {{-- The stage's own parts (a dot with its paper ring, a leader, a label, a phone marker): illustration, as on the stage. --}}
            <div class="hero-parts" aria-hidden="true" data-illustration>
                <svg focusable="false"><path class="hero-line" d="M4 8H64"></path><circle class="hero-dot" cx="4" cy="8" r="4"></circle></svg>
                <span class="hero-callout hero-callout--end hero-callout--wide"><span class="hero-callout__text">{{ $sample }}</span></span>
                <span class="hero-callout hero-callout--end"><span class="hero-callout__num t-num">1</span></span>
            </div>
            <p class="sg-label" {!! $en !!}>{{ $t('parts') }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ pager --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('pager') }}</h3>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <x-d2.hero.pager active="cheque" id-prefix="sg-pager-first" />
            <p class="sg-label" {!! $en !!}>{{ $t('pager_first') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.hero.pager active="tenancy" id-prefix="sg-pager-middle" />
            <p class="sg-label" {!! $en !!}>{{ $t('pager_middle') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.hero.pager active="notice" id-prefix="sg-pager-last" />
            <p class="sg-label" {!! $en !!}>{{ $t('pager_last') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.hero.pager active="transfer" focus="next" id-prefix="sg-pager-focus-next" />
            <x-d2.hero.pager active="transfer" focus="segment" id-prefix="sg-pager-focus-segment" />
            <p class="sg-label" {!! $en !!}>{{ $t('pager_focus') }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ tool card --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('tool') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('tool_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <x-d2.hero.tool :services="$services" id-prefix="sg-tool-individual" />
            <p class="sg-label" {!! $en !!}>{{ $t('tool_individual') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.hero.tool :services="$services" audience="business" id-prefix="sg-tool-business" />
            <p class="sg-label" {!! $en !!}>{{ $t('tool_business') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.hero.tool :services="$services" audience="investor" id-prefix="sg-tool-investor" />
            <p class="sg-label" {!! $en !!}>{{ $t('tool_investor') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.hero.tool :services="$services" situation="S06-2" id-prefix="sg-tool-situation" />
            <p class="sg-label" {!! $en !!}>{{ $t('tool_situation') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.hero.tool :services="$services" :map="[]" id-prefix="sg-tool-unmapped" />
            <p class="sg-label" {!! $en !!}>{{ $t('tool_unmapped') }}</p>
        </div>
    </div>
</section>
