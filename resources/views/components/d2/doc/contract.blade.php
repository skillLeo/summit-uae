@props([
    'variant' => 'stage',  // stage | mobile | still | anatomy
    'hot' => null,         // selected field id: parties | start | wage | allowances | notice | signatures
])
{{--
    Sample employment contract page (360×470) between "B. Sample LLC" (employer) and "A. Example"
    (employee): numbered clause headings, basic wage AED 00,000 and allowances AED 0,000 (spec §4.2), a
    notice clause and two signature blocks. Short lines only (no large text block: the hero H1 stays the
    LCP element). Contract: <x-d2.doc.frame>.
--}}
@php
    use App\Design\D2\Docs;

    $t = fn (string $key) => Docs::t('contract.'.$key);
    $n = fn (string $key) => Docs::ltr(Docs::t('contract.'.$key));
    $f = fn (string $id) => Docs::field($id, $hot);
@endphp
<x-d2.doc.frame type="contract" :variant="$variant" {{ $attributes }}>
    <div class="doc__sheet page contract">
        <div class="page__head">
            <p class="page__title">{{ $t('title') }}</p>
            {{-- The number and the date are set apart by space alone (no dot between them: brief §3.6 rule 2, D02). --}}
            <p class="page__meta"><span>{{ $t('ref') }} <span class="doc__num">{{ $n('ref_value') }}</span></span><span class="doc__num">{{ $n('date') }}</span></p>
        </div>

        <div class="page__parties page__parties--grid" {{ $f('parties') }}>
            <p class="page__lead">{{ $t('between') }}</p>
            <div class="page__party">
                <span class="page__label">{{ $t('employer') }}</span>
                <span class="page__value">{{ $t('employer_value') }}</span>
            </div>
            <div class="page__party">
                <span class="page__label">{{ $t('employee') }}</span>
                <span class="page__value">{{ $t('employee_value') }}</span>
            </div>
        </div>

        <div class="page__clause" {{ $f('start') }}>
            <p class="page__h">{{ $t('c1') }}</p>
            <p class="page__p">{{ \App\Design\D2\Bidi::isolate($t('c1_text')) }}</p>
        </div>

        <div class="page__clause">
            <p class="page__h">{{ $t('c2') }}</p>
            <div class="page__table">
                <div class="page__tr" {{ $f('wage') }}>
                    <span class="page__td">{{ $t('basic') }}</span>
                    <span class="page__td page__td--num doc__num">{{ $n('basic_value') }}</span>
                    <span class="page__td page__td--unit">{{ $t('per_month') }}</span>
                </div>
                <div class="page__tr" {{ $f('allowances') }}>
                    <span class="page__td">{{ $t('allowances') }}</span>
                    <span class="page__td page__td--num doc__num">{{ $n('allowances_value') }}</span>
                    <span class="page__td page__td--unit">{{ $t('per_month') }}</span>
                </div>
                <div class="page__tr page__tr--total">
                    <span class="page__td">{{ $t('total') }}</span>
                    <span class="page__td page__td--num doc__num">{{ $n('total_value') }}</span>
                    <span class="page__td page__td--unit">{{ $t('per_month') }}</span>
                </div>
            </div>
        </div>

        <div class="page__clause">
            <p class="page__h">{{ $t('c3') }}</p>
            <p class="page__p">{{ \App\Design\D2\Bidi::isolate($t('c3_text')) }}</p>
        </div>

        <div class="page__clause" {{ $f('notice') }}>
            <p class="page__h">{{ $t('c4') }}</p>
            <p class="page__p">{{ \App\Design\D2\Bidi::isolate($t('c4_text')) }}</p>
        </div>

        <div class="page__clause">
            <p class="page__h">{{ $t('c5') }}</p>
            <p class="page__p">{{ $t('c5_text') }}</p>
        </div>

        <div class="page__signs" {{ $f('signatures') }}>
            <div class="page__sign">
                <svg class="doc__scribble" viewBox="0 0 120 34" focusable="false"><path d="M4 24c4-1 7-9 10-15 2-4 6-5 6-1 0 6-8 14-5 15 4 1 9-13 14-13 3 0-1 9 2 9 4 0 6-7 10-7 2 0 1 5 4 5 5 0 9-6 14-6M54 9c6 2 18 1 30-2 8-2 15-2 26 0"/></svg>
                <span class="page__sign-line"></span>
                <span class="page__label">{{ $t('sign_employer') }}</span>
                <span class="page__value">{{ $t('employer_value') }}</span>
            </div>
            <div class="page__sign">
                <svg class="doc__scribble" viewBox="0 0 120 34" focusable="false"><path d="M10 27c2-8 4-18 9-19 4-1 1 9-2 13-2 3 1 4 4 1 4-4 7-9 10-8 2 1-2 7 1 7 3 0 5-5 8-5 2 0 1 4 3 4 6-1 10-6 17-6 6 0 12 2 20 1 6-1 11-3 15-5"/></svg>
                <span class="page__sign-line"></span>
                <span class="page__label">{{ $t('sign_employee') }}</span>
                <span class="page__value">{{ $t('employee_value') }}</span>
            </div>
        </div>

        <p class="page__folio">{{ \App\Design\D2\Bidi::isolate($t('page')) }}</p>
    </div>
</x-d2.doc.frame>
