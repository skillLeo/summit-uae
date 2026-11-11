@props([
    'variant' => 'stage',  // stage | mobile | still | anatomy
    'hot' => null,         // selected field id: amount | date | wallet | txid | network | status
])
{{--
    Sample crypto transfer receipt (320×420) from "Sample Exchange": 00,000.00 USDT to "B. Sample LLC",
    dummy addresses and TxID 0x0000…0000, date 00/00/2026. No real exchange's look, logo or QR code.
    Contract: <x-d2.doc.frame>.
--}}
@php
    use App\Design\D2\Docs;

    $t = fn (string $key) => Docs::t('transfer.'.$key);
    $n = fn (string $key) => Docs::ltr(Docs::t('transfer.'.$key));
    $f = fn (string $id) => Docs::field($id, $hot);
@endphp
<x-d2.doc.frame type="transfer" :variant="$variant" {{ $attributes }}>
    <div class="doc__sheet receipt">
        <div class="receipt__head">
            <span class="receipt__mark"></span>
            <span class="receipt__platform">{{ $t('platform') }}</span>
        </div>
        <p class="receipt__title">{{ $t('title') }}</p>

        <div class="receipt__hero">
            <span class="receipt__status" {{ $f('status') }}>
                <svg viewBox="0 0 12 12" focusable="false"><circle cx="6" cy="6" r="5.25"/><path d="M3.6 6.2l1.6 1.6 3.2-3.4"/></svg>
                <span>{{ $t('status') }}</span>
            </span>
            <span class="receipt__sent">{{ $t('sent') }}</span>
            <span class="receipt__amount" {{ $f('amount') }}><span class="doc__num">{{ $n('amount') }}</span> <span class="receipt__asset doc__num">{{ $n('asset') }}</span></span>
        </div>

        <div class="receipt__rows">
            <div class="receipt__row" {{ $f('date') }}>
                <span class="receipt__k">{{ $t('date') }}</span>
                <span class="receipt__v doc__num">{{ $n('date_value') }}</span>
            </div>
            <div class="receipt__row" {{ $f('network') }}>
                <span class="receipt__k">{{ $t('network') }}</span>
                <span class="receipt__v doc__num">{{ $n('network_value') }}</span>
            </div>
            <div class="receipt__row">
                <span class="receipt__k">{{ $t('from') }}</span>
                <span class="receipt__v doc__num">{{ $n('address') }}</span>
            </div>
            <div class="receipt__row receipt__row--stack" {{ $f('wallet') }}>
                <span class="receipt__k">{{ $t('to') }}</span>
                <span class="receipt__v doc__num">{{ $n('address') }}</span>
                <span class="receipt__k">{{ $t('recipient') }}</span>
                <span class="receipt__v">{{ $t('recipient_value') }}</span>
            </div>
            <div class="receipt__row receipt__row--stack" {{ $f('txid') }}>
                <span class="receipt__k">{{ \App\Design\D2\Bidi::isolate($t('txid')) }}</span>
                <span class="receipt__v doc__num">{{ $n('txid_value') }}</span>
            </div>
            <div class="receipt__row">
                <span class="receipt__k">{{ $t('fee') }}</span>
                <span class="receipt__v doc__num">{{ $n('fee_value') }}</span>
            </div>
            <div class="receipt__row">
                <span class="receipt__k">{{ $t('reference') }}</span>
                <span class="receipt__v doc__num">{{ $n('reference_value') }}</span>
            </div>
        </div>

        <p class="receipt__foot">{{ $t('footer') }}</p>
    </div>
</x-d2.doc.frame>
