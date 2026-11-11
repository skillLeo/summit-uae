{{--
    Styleguide: Design 2.1 type (docs/briefs/design-2.1-and-admin-cms.md §3.2; contract docs/d2.1/phase-1/tokens.md
    §4 and §5). Every role (.t-display … .t-caption) with its desktop and mobile size, line height, tracking and
    weight, set in approved copy of this page's language; the interface families and their three weights; the
    sample-document families (never in the interface); the three scripts side by side; figures and links. Samples
    are <p class="t-…">, never headings: the page keeps one H1. Annotations are internal:
    lang/en/styleguide_d2/type.php.
--}}
@php
    $t = fn (string $key, array $replace = []) => __('styleguide_d2/type.'.$key, $replace);
    $en = app()->isLocale('en') ? '' : 'lang="en"';
    $p01 = content()->page('P01');
    $c = $p01['content'] ?? [];
    $s06 = content()->service('S06') ?? [];
    $phone = setting('phone_display');
    $roles = [
        't-display' => ['--fs-display', '--lh-display', '--fw-semibold', '--ls-display'],
        't-h2' => ['--fs-h2', '--lh-h2', '--fw-semibold', '--ls-h2'],
        't-h3' => ['--fs-h3', '--lh-h3', '--fw-semibold', '--ls-h3'],
        't-minor' => ['--fs-minor', '--lh-minor', '--fw-semibold'],
        't-lead' => ['--fs-lead', '--lh-lead', '--fw-regular'],
        't-body' => ['--fs-body', '--lh-body', '--fw-regular'],
        't-ui' => ['--fs-ui', '--lh-ui', '--fw-medium'],
        't-small' => ['--fs-small', '--lh-small', '--fw-regular'],
        't-caption' => ['--fs-caption', '--lh-caption', '--fw-regular'],
        't-tabular' => ['font-variant-numeric'],
    ];
    $spec = (array) $t('spec');
    $families = [
        'sans' => ['class' => 'sg-ff-sans', 'lang' => 'en', 'weights' => [400, 500, 600], 'samples' => [['en', 'ltr', ui('nav.practice_areas', 'en')], ['ru', 'ltr', ui('nav.practice_areas', 'ru')]]],
        'arabic' => ['class' => 'sg-ff-sans', 'lang' => 'ar', 'weights' => [400, 500, 600], 'samples' => [['ar', 'rtl', ui('nav.practice_areas', 'ar')], [null, 'ltr', $phone]]],
    ];
    $docFamilies = [
        'mono' => ['class' => 'sg-ff-doc-mono', 'weights' => [400, 500], 'samples' => [[null, 'ltr', '00/00/2026']]],
        'serif' => ['class' => 'sg-ff-doc-serif', 'weights' => [400, 600], 'samples' => [['en', 'ltr', ui('nav.practice_areas', 'en')], ['ru', 'ltr', ui('nav.practice_areas', 'ru')]]],
        'naskh' => ['class' => 'sg-ff-doc-naskh', 'weights' => [400, 600], 'samples' => [['ar', 'rtl', ui('nav.practice_areas', 'ar')]]],
    ];
@endphp
<section class="sg-section" id="sg-type">
    <h2 {!! $en !!}>{{ $t('title') }}</h2>
    <p {!! $en !!}>{{ $t('intro') }}</p>

    {{-- ------------------------------------------------------------------ roles --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('roles') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('roles_note') }}</p>
    <div class="sg-type">
        @foreach ($roles as $role => $names)
            @php [$name, $metrics, $note] = ($spec[$role] ?? []) + ['', '', '']; @endphp
            <div class="sg-type__row">
                <div class="sg-type__spec" {!! $en !!}>
                    <p class="sg-type__name"><code class="sg-code">.{{ $role }}</code> {{ $name }}</p>
                    <p class="sg-label">{{ $metrics }}</p>
                    @if ($note)
                        <p class="sg-label">{{ $note }}</p>
                    @endif
                    <p class="sg-type__tokens">
                        @foreach ($names as $token)
                            <code class="sg-code">{{ $token }}</code>
                        @endforeach
                    </p>
                </div>
                <div class="sg-type__sample">
                    @switch ($role)
                        @case('t-display')
                            <p class="t-display">{{ $p01['h1'] ?? '' }}</p>
                            @break
                        @case('t-h2')
                            <p class="t-h2">{{ $c['services']['heading'] ?? '' }}</p>
                            @break
                        @case('t-h3')
                            <p class="t-h3">{{ $s06['title'] ?? '' }}</p>
                            @break
                        @case('t-minor')
                            <p class="t-minor">{{ ui('nav.practice_areas') }}</p>
                            @break
                        @case('t-lead')
                            <p class="t-lead">{{ $c['hero']['lead'] ?? '' }}</p>
                            @break
                        @case('t-body')
                            <p class="t-body">{{ $c['hero']['body'] ?? '' }}</p>
                            @break
                        @case('t-ui')
                            <p class="t-ui sg-row"><span>{{ ui('cta.request_consultation') }}</span><span>{{ ui('nav.practice_areas') }}</span><span>{{ ui('nav.faq') }}</span></p>
                            @break
                        @case('t-small')
                            <p class="t-small">{{ $c['contact']['hours'] ?? '' }}</p>
                            @break
                        @case('t-caption')
                            <p class="t-caption">{{ $c['contact']['languages'] ?? '' }}</p>
                            @break
                        @case('t-tabular')
                            <p class="t-body t-tabular"><bdi dir="ltr">{{ $phone }}</bdi></p>
                            @break
                    @endswitch
                </div>
            </div>
        @endforeach
    </div>
    <p class="sg-label" {!! $en !!}>{{ $t('legacy') }}</p>

    {{-- ------------------------------------------------------------------ links --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('links') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('links_note') }}</p>
    <div class="sg-grid">
        <div class="sg-specimen">
            <p class="t-body"><a class="link" href="#sg-type">{{ ui('nav.about') }}</a></p>
            <p class="sg-label" {!! $en !!}><code class="sg-code">.link</code> {{ $t('link_body') }}</p>
        </div>
        <div class="sg-specimen">
            <p class="t-ui"><a class="nav-link" href="#sg-type">{{ ui('nav.contact') }}</a></p>
            <p class="sg-label" {!! $en !!}><code class="sg-code">.nav-link</code> {{ $t('link_nav') }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ families --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('families') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('families_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        @foreach ($families as $key => $family)
            <div class="sg-specimen sg-family" lang="{{ $family['lang'] }}">
                <p class="sg-label" lang="en">{{ $t('family.'.$key) }}</p>
                <ul class="sg-weights" role="list">
                    @foreach ($family['weights'] as $w)
                        <li class="sg-weight">
                            <code class="sg-code">{{ $w }}</code>
                            <span class="sg-weight__sample {{ $family['class'] }} sg-fw-{{ $w }}">
                                @foreach ($family['samples'] as [$lang, $dir, $text])
                                    <bdi @if ($lang) lang="{{ $lang }}" @endif dir="{{ $dir }}">{{ $text }}</bdi>
                                @endforeach
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <h4 class="sg-minor" {!! $en !!}>{{ $t('doc_families') }}</h4>
    <p class="sg-label" {!! $en !!}>{{ $t('doc_families_note') }}</p>
    <div class="sg-grid">
        @foreach ($docFamilies as $key => $family)
            <div class="sg-specimen sg-family" data-illustration>
                <p class="sg-label" lang="en">{{ $t('doc_family.'.$key) }}</p>
                <ul class="sg-weights" role="list">
                    @foreach ($family['weights'] as $w)
                        <li class="sg-weight">
                            <code class="sg-code">{{ $w }}</code>
                            <span class="sg-weight__sample {{ $family['class'] }} sg-fw-{{ $w }}">
                                @foreach ($family['samples'] as [$lang, $dir, $text])
                                    <bdi @if ($lang) lang="{{ $lang }}" @endif dir="{{ $dir }}">{{ $text }}</bdi>
                                @endforeach
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ scripts --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('scripts') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('scripts_note') }}</p>
    <div class="sg-scripts">
        @foreach (['en' => 'ltr', 'ru' => 'ltr', 'ar' => 'rtl'] as $l => $dir)
            @php $lc = content()->page('P01', $l)['content'] ?? []; @endphp
            <article class="sg-script" lang="{{ $l }}" dir="{{ $dir }}">
                <p class="t-ui">{{ ui('nav.practice_areas', $l) }}</p>
                <p class="t-h2">{{ content()->page('P01', $l)['h1'] ?? '' }}</p>
                <p class="t-body">{{ $lc['hero']['body'] ?? '' }}</p>
                <p class="sg-label" lang="en" dir="ltr">{{ $t('script.'.$l) }}</p>
            </article>
        @endforeach
    </div>
</section>
