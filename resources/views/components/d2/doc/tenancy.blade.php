@props([
    'variant' => 'stage',  // stage | mobile | still | anatomy
    'hot' => null,         // selected field id: parties | period | rent | renewal | registration | signatures
])
{{--
    Sample tenancy contract (380×480) between "B. Sample LLC" (landlord) and "A. Example" (tenant): a
    plain private-contract page with a schedule of terms (contract period, annual rent AED 000,000,
    registration no. 0000000000), three short articles and two signatures. Deliberately NOT the Ejari /
    unified tenancy contract: no authority name, emblem, form grid or bilingual government layout.
    Contract: <x-d2.doc.frame>.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Docs;

    $t = fn (string $key) => Docs::t('tenancy.'.$key);
    $n = fn (string $key) => Docs::ltr(Docs::t('tenancy.'.$key));
    $f = fn (string $id) => Docs::field($id, $hot);
@endphp
<x-d2.doc.frame type="tenancy" :variant="$variant" {{ $attributes }}>
    <div class="doc__sheet page tenancy">
        <div class="page__head page__head--center">
            <p class="page__title">{{ $t('title') }}</p>
            <p class="page__sub">{{ $t('subtitle') }}</p>
            <p class="page__meta"><span>{{ $t('ref') }} <span class="doc__num">{{ $n('ref_value') }}</span></span></p>
        </div>

        <div class="page__parties page__parties--grid" {{ $f('parties') }}>
            <div class="page__party">
                <span class="page__label">{{ $t('landlord') }}</span>
                <span class="page__value">{{ $t('landlord_value') }}</span>
            </div>
            <div class="page__party">
                <span class="page__label">{{ $t('tenant') }}</span>
                <span class="page__value">{{ $t('tenant_value') }}</span>
            </div>
            <div class="page__party page__party--wide">
                <span class="page__label">{{ $t('property') }}</span>
                <span class="page__value">{{ Bidi::isolate($t('property_value')) }}</span>
            </div>
        </div>

        <div class="page__schedule">
            <p class="page__h">{{ $t('schedule') }}</p>
            <div class="page__table page__table--terms">
                <div class="page__tr" {{ $f('period') }}>
                    <span class="page__td">{{ $t('period') }}</span>
                    <span class="page__td page__td--num doc__num">{{ $n('period_value') }}</span>
                </div>
                <div class="page__tr" {{ $f('rent') }}>
                    <span class="page__td">{{ $t('rent') }}</span>
                    <span class="page__td page__td--num doc__num">{{ $n('rent_value') }}</span>
                </div>
                <div class="page__tr">
                    <span class="page__td">{{ $t('payments') }}</span>
                    <span class="page__td page__td--num">{{ Bidi::isolate($t('payments_value')) }}</span>
                </div>
                <div class="page__tr">
                    <span class="page__td">{{ $t('deposit') }}</span>
                    <span class="page__td page__td--num doc__num">{{ $n('deposit_value') }}</span>
                </div>
                <div class="page__tr" {{ $f('registration') }}>
                    <span class="page__td">{{ $t('registration') }}</span>
                    <span class="page__td page__td--num doc__num">{{ $n('registration_value') }}</span>
                </div>
            </div>
        </div>

        <div class="page__clause">
            <p class="page__h">{{ $t('a1') }}</p>
            <p class="page__p">{{ $t('a1_text') }}</p>
        </div>
        <div class="page__clause" {{ $f('renewal') }}>
            <p class="page__h">{{ $t('a2') }}</p>
            <p class="page__p">{{ $t('a2_text') }}</p>
        </div>
        <div class="page__clause">
            <p class="page__h">{{ $t('a3') }}</p>
            <p class="page__p">{{ $t('a3_text') }}</p>
        </div>

        <div class="page__signs" {{ $f('signatures') }}>
            <div class="page__sign">
                <svg class="doc__scribble" viewBox="0 0 120 34" focusable="false"><path d="M6 21c5-3 10-12 15-12 4 0 1 9-3 12-3 2 0 4 4 1 5-4 8-10 13-9 3 1-1 8 2 8 3 0 6-6 9-6 3 0 1 5 5 5 7 0 12-8 20-8 6 0 3 6 9 6 7 0 14-4 21-7"/></svg>
                <span class="page__sign-line"></span>
                <span class="page__label">{{ $t('sign_landlord') }}</span>
            </div>
            <div class="page__sign">
                <svg class="doc__scribble" viewBox="0 0 120 34" focusable="false"><path d="M5 26c6-4 9-15 14-17 3-1 4 3 1 8-2 4-4 9 0 8 5-1 7-11 12-11 3 0 2 6 5 6 4 0 6-8 10-8 3 0 2 7 6 7 5 0 8-5 13-6 9-1 18 1 32-3"/></svg>
                <span class="page__sign-line"></span>
                <span class="page__label">{{ $t('sign_tenant') }}</span>
            </div>
        </div>
    </div>
</x-d2.doc.frame>
