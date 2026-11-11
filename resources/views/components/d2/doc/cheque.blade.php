@props([
    'variant' => 'stage',  // stage | mobile | still | anatomy
    'hot' => null,         // selected field id: date | payee | words | figures | signature | memo
])
{{--
    Sample cheque (520×230) from "Sample Bank" to "A. Example", amount in words and figures, date
    00/00/2026, zeroed account and MICR line, with the return memo (300×150) stapled behind its top edge:
    the document box is 520×314 (84 px of the memo show above the cheque). A generic security tint and
    wave pattern; never a real bank's layout, logo or colours. Contract: <x-d2.doc.frame>.
--}}
@php
    use App\Design\D2\Docs;

    $t = fn (string $key) => Docs::t('cheque.'.$key);
    $n = fn (string $key) => Docs::ltr(Docs::t('cheque.'.$key));
    $f = fn (string $id) => Docs::field($id, $hot);
@endphp
<x-d2.doc.frame type="cheque" :variant="$variant" {{ $attributes }}>
    <x-slot:shapes><i class="doc__shape doc__shape--memo"></i><i class="doc__shape doc__shape--cheque"></i></x-slot:shapes>

    <x-d2.doc.memo-slip :nested="true" :hot="$hot" />

    <div class="doc__sheet cheque">
        <svg class="cheque__waves" viewBox="0 0 520 230" preserveAspectRatio="none" focusable="false">
            <path d="M0 40 C 65 22 130 58 195 40 S 325 22 390 40 S 520 58 585 40"/>
            <path d="M0 74 C 65 56 130 92 195 74 S 325 56 390 74 S 520 92 585 74"/>
            <path d="M0 108 C 65 90 130 126 195 108 S 325 90 390 108 S 520 126 585 108"/>
            <path d="M0 142 C 65 124 130 160 195 142 S 325 124 390 142 S 520 160 585 142"/>
            <path d="M0 176 C 65 158 130 194 195 176 S 325 158 390 176 S 520 194 585 176"/>
        </svg>

        <div class="cheque__top">
            <div class="cheque__bank">
                <span class="cheque__mark"></span>
                <span class="cheque__bank-name">{{ $t('bank') }}</span>
                <span class="cheque__branch">{{ $t('branch') }}</span>
            </div>
            <span class="cheque__no"><span class="cheque__label">{{ $t('no') }}</span> <span class="doc__num">{{ $n('number') }}</span></span>
        </div>

        <div class="cheque__row cheque__row--date">
            <span class="cheque__crossing"><span>{{ $t('crossing') }}</span></span>
            <span class="cheque__date" {{ $f('date') }}>
                <span class="cheque__label">{{ $t('date') }}</span>
                <span class="cheque__boxes doc__num doc__pen">{{ $n('date_value') }}</span>
            </span>
        </div>

        <div class="cheque__row cheque__row--pay">
            <span class="cheque__label">{{ $t('pay') }}</span>
            <span class="cheque__line"><span class="cheque__fill doc__pen" {{ $f('payee') }}>{{ $t('payee') }}</span></span>
            <span class="cheque__label cheque__label--end">{{ $t('bearer') }}</span>
        </div>

        <div class="cheque__row cheque__row--amount">
            <span class="cheque__label">{{ $t('words_label') }}</span>
            <span class="cheque__line"><span class="cheque__fill doc__pen" {{ $f('words') }}>{{ $t('words') }}</span></span>
            <span class="cheque__figures" {{ $f('figures') }}>
                <span class="cheque__currency doc__num">{{ $n('currency') }}</span>
                <span class="doc__num doc__pen">{{ $n('figures') }}</span>
            </span>
        </div>

        <div class="cheque__row cheque__row--sign">
            <span class="cheque__account"><span class="cheque__label">{{ $t('account') }}</span> <span class="doc__num">{{ $n('account_value') }}</span></span>
            <span class="cheque__signature" {{ $f('signature') }}>
                <svg class="doc__scribble" viewBox="0 0 120 34" focusable="false"><path d="M4 25c3-2 6-9 9-15 2-4 6-6 7-2 1 5-7 13-4 15 3 2 8-12 13-13 4 0 0 10 3 10s6-9 10-9c3 0 1 7 4 7 4 0 6-5 10-5s3 4 7 4c6 0 11-5 18-6 7-1 12 1 18-1M30 30c14-1 40-2 62-6"/></svg>
                <span class="cheque__drawer">{{ $t('drawer') }}</span>
            </span>
        </div>

        <div class="cheque__micr doc__num">{{ $n('micr') }}</div>
    </div>

    <span class="doc__staple"></span>
</x-d2.doc.frame>
