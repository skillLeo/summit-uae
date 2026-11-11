{{--
    Styleguide: the calculators (task C6; Design 2.1 Phase 2 owner: calculators; the page is /{locale}/calculators/).
    The gratuity estimator and the rent increase check in every state: empty ("—" under the helper line, no bar,
    no breakdown), filled, every badge and note, out of scope, validation errors, and RTL. Each specimen
    is the real component with its own id prefix (field names and radio groups never collide) and initial
    values; the real page script mounts on each one when the area nears the viewport, so every state is
    live. The RTL specimens render in Arabic on the English and Russian pages (the app locale is switched
    for that render only). Annotations: lang/en/styleguide_d2/tools.php (lang="en" on other pages).
--}}
@php
    $t = fn (string $key) => __('styleguide_d2/tools.'.$key);
    $en = app()->isLocale('en') ? '' : 'lang=en';

    $filled = ['scope' => 'mainland', 'start' => '2018-03-01', 'end' => '2026-10-31', 'absence' => '20', 'basic' => '15000',
        'total' => '21000', 'leave' => '12', 'unpaid' => '4500', 'notice' => '30', 'served' => '10'];
    $gratuity = [
        'g_empty' => [],
        'g_filled' => $filled,
        'g_cap' => ['start' => '1986-01-01', 'end' => '2025-12-31', 'basic' => '10000'],
        'g_under' => ['start' => '2026-01-01', 'end' => '2026-10-31', 'basic' => '8000', 'leave' => '5'],
        'g_part' => ['contract' => 'part', 'hours' => '24', 'full_hours' => '48', 'start' => '2019-06-01', 'end' => '2026-05-31', 'basic' => '12000'],
        'g_temporary' => ['contract' => 'temporary', 'start' => '2020-01-01', 'end' => '2026-03-31', 'basic' => '9000', 'leave' => '10'],
        'g_errors' => ['start' => '2026-05-02', 'end' => '2026-05-01', 'basic' => '9000', 'total' => '5000', 'leave' => '-1', 'notice' => '20'],
        'g_out' => ['scope' => 'difc'],
    ];
    $rentOk = ['current' => '80000', 'average' => '103000', 'expiry' => '2027-03-31', 'notice' => '2026-12-01'];
    $rent = [
        'r_empty' => [],
        'r_ok' => $rentOk,
        'r_short' => ['notice' => '2027-01-15'] + $rentOk,
        'r_after' => ['notice' => '2027-04-10'] + $rentOk,
        'r_above' => ['current' => '110000', 'average' => '100000', 'expiry' => '2027-06-30'],
        'r_errors' => ['current' => '-1', 'average' => 'abc'],
    ];

    // One specimen rendered in Arabic, right to left (only on the pages that are not Arabic already). The
    // tag is assembled from two strings: written whole, Blade's component compiler would expand it here.
    $arabic = function (string $component, string $prefix, array $values): \Illuminate\Support\HtmlString {
        $tag = '<'.'x-d2.tool.'.$component.' :id-prefix="$prefix" :values="$values" heading-level="4"'.($component === 'gratuity' ? ' :dock="false"' : '').' />';
        $locale = app()->getLocale();
        app()->setLocale('ar');
        try {
            return new \Illuminate\Support\HtmlString(\Illuminate\Support\Facades\Blade::render($tag, ['prefix' => $prefix, 'values' => $values]));
        } finally {
            app()->setLocale($locale);
        }
    };
    $rtl = ! app()->isLocale('ar');
@endphp
<section class="sg-section" id="sg-tools">
    <h2 {{ $en }}>{{ $t('title') }}</h2>
    <p {{ $en }}>{{ $t('intro') }}</p>

    <h3 class="sg-sub" {{ $en }}>{{ $t('section') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('section_note') }}</p>
    <p class="sg-note" {{ $en }}>{{ $t('motion_note') }}</p>
    <p class="sg-note" {{ $en }}>{{ $t('design_note') }}</p>

    {{-- ------------------------------------------------------------------ gratuity --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('gratuity') }}</h3>
    @foreach ($gratuity as $state => $values)
        <div class="sg-grid sg-grid--full" data-sg="tool-{{ $state }}">
            <div class="tool-specimen">
                <x-d2.tool.gratuity :id-prefix="'sg-'.str_replace('_', '-', $state)" :values="$values" heading-level="4" :dock="false" />
                <p class="sg-label" {{ $en }}>{{ $t($state) }}</p>
            </div>
        </div>
    @endforeach
    @if ($rtl)
        <div class="sg-grid sg-grid--full" data-sg="tool-g-rtl">
            <div class="tool-specimen">
                <div dir="rtl" lang="ar">
                    {{ $arabic('gratuity', 'sg-g-rtl', $filled) }}
                </div>
                <p class="sg-label" {{ $en }}>{{ $t('g_rtl') }}</p>
            </div>
        </div>
    @endif

    {{-- ------------------------------------------------------------------ rent --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('rent') }}</h3>
    @foreach ($rent as $state => $values)
        <div class="sg-grid sg-grid--full" data-sg="tool-{{ $state }}">
            <div class="tool-specimen">
                <x-d2.tool.rent :id-prefix="'sg-'.str_replace('_', '-', $state)" :values="$values" heading-level="4" />
                <p class="sg-label" {{ $en }}>{{ $t($state) }}</p>
            </div>
        </div>
    @endforeach
    @if ($rtl)
        <div class="sg-grid sg-grid--full" data-sg="tool-r-rtl">
            <div class="tool-specimen">
                <div dir="rtl" lang="ar">
                    {{ $arabic('rent', 'sg-r-rtl', $rentOk) }}
                </div>
                <p class="sg-label" {{ $en }}>{{ $t('r_rtl') }}</p>
            </div>
        </div>
    @endif
</section>
