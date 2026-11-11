@props([
    'hot' => null,         // selected field id
    'nested' => false,     // stapled to the cheque: the reason field is "memo" (the cheque's hotspot)
])
{{--
    Internal: the paper of the cheque return memo (300×150), shared by the standalone memo document and
    the cheque (stapled behind its top edge, where only the top 70 px show: bank, title, return stamp,
    cheque details and the reason; the signatory and reference sit in the hidden part).
--}}
@php
    use App\Design\D2\Docs;

    $t = fn (string $key) => Docs::t('memo.'.$key);
    $n = fn (string $key) => Docs::ltr(Docs::t('memo.'.$key));
    // Stapled to the cheque, only the reason is a field (the cheque's "memo" hotspot).
    $f = fn (string $id) => $nested ? '' : Docs::field($id, $hot);
@endphp
<div @class(['doc__sheet', 'memo', 'doc__memo' => $nested])>
    <span class="doc__fold"></span>
    <p class="memo__bank">{{ $t('bank') }}</p>
    <p class="memo__title">{{ $t('title') }}</p>
    <span class="memo__stamp" {{ $f('stamp') }}>{{ $t('stamp') }}</span>
    <div class="memo__meta">
        <span class="memo__item" {{ $f('cheque') }}><span class="memo__label">{{ $t('cheque') }}</span> <span class="doc__num">{{ $n('cheque_value') }}</span></span>
        <span class="memo__item"><span class="memo__label">{{ $t('date') }}</span> <span class="doc__num">{{ $n('date_value') }}</span></span>
        <span class="memo__item"><span class="memo__label">{{ $t('amount') }}</span> <span class="doc__num">{{ $n('amount_value') }}</span></span>
    </div>
    <div class="memo__reason" {{ $nested ? Docs::field('memo', $hot) : $f('reason') }}>
        <span class="memo__label">{{ $t('reason') }}</span>
        <span class="memo__check">
            <svg class="memo__box" viewBox="0 0 10 10" focusable="false"><rect x="0.5" y="0.5" width="9" height="9" rx="1"/><path d="M2.4 2.4l5.2 5.2M7.6 2.4l-5.2 5.2"/></svg>
            <span class="memo__value">{{ $t('reason_value') }}</span>
        </span>
    </div>
    <div class="memo__foot">
        <span class="memo__sign" {{ $f('signatory') }}>
            <svg class="doc__scribble" viewBox="0 0 120 34" focusable="false"><path d="M5 25c3-7 7-15 11-14 5 1-6 15-1 15s8-17 13-16c4 1-5 14 0 14 4 0 7-10 11-10s0 8 4 8c5 0 6-6 10-6 3 0 3 4 7 4 6 0 13-4 22-7"/></svg>
            <span class="doc__rule"></span>
            <span>{{ $t('signatory') }}</span>
        </span>
        <span class="memo__ref">{{ $t('ref') }} <span class="doc__num">{{ $n('ref_value') }}</span></span>
    </div>
</div>
