{{--
    Styleguide: Design 2 cards (team profile, case file, guide; home C8–C10, reused by Phase D pages), every
    state. Annotations are internal: lang/en/styleguide_d2/cards.php. Demo content is approved copy
    (content(), ui()) and the proposed keys of proposed-d2/team. Forced states use the
    styleguide-only modifiers .is-hover / .is-focus. Every specimen has its own id prefix (no id of the
    home page or of another specimen is repeated). The "other direction" specimens render the approved
    copy of the other script (Arabic on LTR pages, English on the Arabic page) with that locale active.
--}}
@php
    $t = fn (string $key) => __('styleguide_d2/cards.'.$key);
    $en = app()->isLocale('en') ? '' : 'lang=en';
    $members = collect(content()->teamMembers())->keyBy('code');
    $ahmed = $members['team-ahmed'] ?? $members->first();
    $mohamed = $members['team-mohamed'] ?? $members->first();
    $sarah = $members['team-sarah'] ?? $members->last();
    $case = fn (string $code) => content()->caseStudy($code) ?? [];
    $guide = fn (string $code) => content()->guide($code) ?? [];
    $categoryName = fn (array $g) => collect(content()->guideCategories())->firstWhere('code', $g['category'] ?? null)['name'] ?? null;
    $other = app()->isLocale('ar') ? 'en' : 'ar';
    $otherDir = $other === 'ar' ? 'rtl' : 'ltr';
    $previousLocale = app()->getLocale();
@endphp
<section class="sg-section sg-cards" id="sg-cards">
    <h2 {{ $en }}>{{ $t('title') }}</h2>
    <p {{ $en }}>{{ $t('intro') }}</p>

    {{-- ================================================================== profile card --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('profile') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('profile_note') }}</p>
    <div class="sg-grid sg-grid--wide" data-sg="profile-states">
        <div class="sg-specimen">
            <x-d2.team.profile :member="$ahmed" :href="\App\Support\SiteUrl::target((string) $ahmed['code'])" level="4" />
            <p class="sg-label" {{ $en }}>{{ $t('profile_default') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.team.profile :member="$mohamed" :href="\App\Support\SiteUrl::target((string) $mohamed['code'])" level="4" class="is-hover" />
            <p class="sg-label" {{ $en }}>{{ $t('profile_hover') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.team.profile :member="$ahmed" :href="\App\Support\SiteUrl::target((string) $ahmed['code'])" level="4" class="is-focus" />
            <p class="sg-label" {{ $en }}>{{ $t('profile_focus') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.team.profile :member="$sarah" :href="\App\Support\SiteUrl::target((string) $sarah['code'])" level="4" />
            <p class="sg-label" {{ $en }}>{{ $t('profile_pending') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.team.profile :member="$mohamed" level="4" :cta="false" />
            <p class="sg-label" {{ $en }}>{{ $t('profile_static') }}</p>
        </div>
        @php app()->setLocale($other); $otherMember = content()->teamMembers()[1] ?? null; @endphp
        <div class="sg-specimen" dir="{{ $otherDir }}" lang="{{ $other }}">
            @if ($otherMember)
                <x-d2.team.profile :member="$otherMember" :href="\App\Support\SiteUrl::target((string) $otherMember['code'])" level="4" />
            @endif
            @php app()->setLocale($previousLocale); @endphp
            <p class="sg-label" {{ $en }} dir="ltr">{{ $t('profile_dir') }}</p>
        </div>
    </div>
    <div class="sg-grid sg-grid--full">
        <div class="sg-specimen">
            <x-d2.team.profile :member="$sarah" :href="\App\Support\SiteUrl::target((string) $sarah['code'])" level="4" />
            <p class="sg-label" {{ $en }}>{{ $t('profile_wide') }}</p>
        </div>
    </div>
    <p class="sg-label" {{ $en }}>{{ $t('profile_rail') }}</p>

    {{-- ================================================================== case preview (home) --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('preview') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('preview_note') }}</p>
    <div class="sg-grid sg-grid--wide" data-sg="preview-states">
        <div class="sg-specimen">
            <x-d2.case.preview :case="$case('case-01')" id-prefix="sg-pa-" />
            <p class="sg-label" {{ $en }}>{{ $t('preview_default') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.case.preview :case="$case('case-04')" id-prefix="sg-pb-" class="is-hover" />
            <p class="sg-label" {{ $en }}>{{ $t('preview_hover') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.case.preview :case="$case('case-01')" id-prefix="sg-pc-" class="is-focus" />
            <p class="sg-label" {{ $en }}>{{ $t('preview_focus') }}</p>
        </div>
        @php app()->setLocale($other); $otherCase = content()->caseStudy('case-04') ?? []; @endphp
        <div class="sg-specimen" dir="{{ $otherDir }}" lang="{{ $other }}">
            <x-d2.case.preview :case="$otherCase" id-prefix="sg-pd-" />
            @php app()->setLocale($previousLocale); @endphp
            <p class="sg-label" {{ $en }} dir="ltr">{{ $t('preview_dir') }}</p>
        </div>
    </div>

    {{-- ================================================================== case directory card (P04) --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('case') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('case_note') }}</p>
    <div class="sg-grid sg-grid--wide" data-sg="case-states">
        <div class="sg-specimen">
            <x-d2.case.card :case="$case('case-02')" href="#sg-cards" id-prefix="sg-a-" />
            <p class="sg-label" {{ $en }}>{{ $t('case_default') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.case.card :case="$case('case-03')" href="#sg-cards" id-prefix="sg-b-" class="is-hover" />
            <p class="sg-label" {{ $en }}>{{ $t('case_hover') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.case.card :case="$case('case-05')" href="#sg-cards" id-prefix="sg-c-" class="is-focus" />
            <p class="sg-label" {{ $en }}>{{ $t('case_focus') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.case.card :case="$case('case-06')" service="" href="#sg-cards" id-prefix="sg-d-" />
            <p class="sg-label" {{ $en }}>{{ $t('case_empty') }}</p>
        </div>
        @php app()->setLocale($other); $otherCase = content()->caseStudy('case-04') ?? []; @endphp
        <div class="sg-specimen" dir="{{ $otherDir }}" lang="{{ $other }}">
            <x-d2.case.card :case="$otherCase" href="#sg-cards" id-prefix="sg-e-" />
            @php app()->setLocale($previousLocale); @endphp
            <p class="sg-label" {{ $en }} dir="ltr">{{ $t('case_dir') }}</p>
        </div>
    </div>

    {{-- ================================================================== guide card --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('guide') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('guide_note') }}</p>
    <div class="sg-grid sg-grid--wide" data-sg="guide-states">
        @foreach ([['G01', 'default', ''], ['G04', 'hover', 'is-hover'], ['G06', 'focus', 'is-focus']] as [$code, $state, $class])
            <div class="sg-specimen">
                <x-d2.guide.card :guide="$guide($code)" :category="$categoryName($guide($code))" :id-prefix="'sg-'.$state.'-'" :class="$class" />
                <p class="sg-label" {{ $en }}>{{ $t('guide_'.$state) }}</p>
            </div>
        @endforeach
        @php app()->setLocale($other); $otherGuide = content()->guide('G06') ?? []; $otherCategory = collect(content()->guideCategories())->firstWhere('code', $otherGuide['category'] ?? null)['name'] ?? null; @endphp
        <div class="sg-specimen" dir="{{ $otherDir }}" lang="{{ $other }}">
            <x-d2.guide.card :guide="$otherGuide" :category="$otherCategory" id-prefix="sg-dir-" />
            @php app()->setLocale($previousLocale); @endphp
            <p class="sg-label" {{ $en }} dir="ltr">{{ $t('guide_dir') }}</p>
        </div>
    </div>
    <div class="sg-grid sg-grid--full" data-sg="guide-row">
        <div class="sg-specimen">
            <x-d2.ui.carousel class="carousel-demo__bleed" :label="$t('guide')" until="768" list-class="guides__grid">
                @foreach (['G04', 'G05', 'G06'] as $code)
                    <li class="guides__item">
                        <x-d2.guide.card :guide="$guide($code)" :category="$categoryName($guide($code))" :id-prefix="'sg-row-'" />
                    </li>
                @endforeach
            </x-d2.ui.carousel>
            <p class="sg-label" {{ $en }}>{{ $t('guide_row') }}</p>
        </div>
    </div>
</section>
