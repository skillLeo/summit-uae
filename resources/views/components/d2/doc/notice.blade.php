@props([
    'variant' => 'stage',  // stage | mobile | still | anatomy
    'hot' => null,         // selected field id: case | parties | hearing | department | instructions | issued
])
{{--
    Sample hearing notice (360×470): case no. 0000/2026, hearing date 00/00/2026, a sample department,
    the parties "B. Sample LLC" and "A. Example", a short instruction and the documents to bring. The
    header is generic on purpose: no court, ministry or authority name, emblem, crest, seal or QR code.
    Contract: <x-d2.doc.frame>.
--}}
@php
    use App\Design\D2\Docs;

    $t = fn (string $key) => Docs::t('notice.'.$key);
    $n = fn (string $key) => Docs::ltr(Docs::t('notice.'.$key));
    $f = fn (string $id) => Docs::field($id, $hot);
@endphp
<x-d2.doc.frame type="notice" :variant="$variant" {{ $attributes }}>
    <div class="doc__sheet page notice">
        <div class="notice__band">
            <span class="notice__kicker">{{ $t('kicker') }}</span>
            <span class="notice__ref">{{ $t('ref') }} <span class="doc__num">{{ $n('ref_value') }}</span></span>
        </div>
        <p class="page__title notice__title">{{ $t('title') }}</p>

        <div class="notice__facts">
            <div class="notice__fact" {{ $f('case') }}>
                <span class="page__label">{{ $t('case') }}</span>
                <span class="notice__value doc__num">{{ $n('case_value') }}</span>
            </div>
            <div class="notice__fact" {{ $f('department') }}>
                <span class="page__label">{{ $t('department') }}</span>
                <span class="notice__value">{{ $t('department_value') }}</span>
            </div>
            <div class="notice__fact notice__fact--hearing" {{ $f('hearing') }}>
                <span class="page__label">{{ $t('hearing') }}</span>
                <span class="notice__value notice__value--lg doc__num">{{ $n('hearing_value') }}</span>
                <span class="notice__time"><span class="page__label">{{ $t('time') }}</span> <span class="doc__num">{{ $n('time_value') }}</span></span>
            </div>
        </div>

        <div class="page__parties page__parties--grid" {{ $f('parties') }}>
            <div class="page__party">
                <span class="page__label">{{ $t('claimant') }}</span>
                <span class="page__value">{{ $t('claimant_value') }}</span>
            </div>
            <div class="page__party">
                <span class="page__label">{{ $t('respondent') }}</span>
                <span class="page__value">{{ $t('respondent_value') }}</span>
            </div>
        </div>

        <div class="notice__body" {{ $f('instructions') }}>
            <p class="page__p">{{ $t('body') }}</p>
            <p class="page__h">{{ $t('bring') }}</p>
            <ol class="notice__list">
                <li>{{ $t('b1') }}</li>
                <li>{{ $t('b2') }}</li>
                <li>{{ $t('b3') }}</li>
            </ol>
        </div>

        <div class="notice__foot" {{ $f('issued') }}>
            <span class="notice__sign">
                <svg class="doc__scribble" viewBox="0 0 120 34" focusable="false"><path d="M5 22c5-2 9-11 14-12 4-1 2 7-1 10-2 3 2 4 5 1 4-4 6-8 10-7 3 1-1 7 3 7 3 0 5-4 8-4 4 0 2 4 6 4 6 0 10-5 16-5 5 0 9 3 16 1 6-2 12-5 19-6"/></svg>
                <span class="page__sign-line"></span>
            </span>
            <span class="notice__issued"><span class="page__label">{{ $t('issued') }}</span> <span class="doc__num">{{ $n('issued_value') }}</span></span>
        </div>
    </div>
</x-d2.doc.frame>
