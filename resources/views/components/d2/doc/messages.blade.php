@props([
    'variant' => 'stage',  // stage | mobile | still | anatomy
    'hot' => null,         // selected field id: contact | date | promise
])
{{--
    Sample message thread (260×460) on a generic phone (ink frame, radius --r-phone): four short bubbles
    about a payment date with "B. Sample LLC". A neutral chat layout: no WhatsApp (or any app's) colours,
    wallpaper, ticks, icons or header. Contract: <x-d2.doc.frame>.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Docs;

    $t = fn (string $key) => Docs::t('messages.'.$key);
    $n = fn (string $key) => Docs::ltr(Docs::t('messages.'.$key));
    $f = fn (string $id) => Docs::field($id, $hot);
@endphp
<x-d2.doc.frame type="messages" :variant="$variant" {{ $attributes }}>
    <div class="phone">
        <div class="phone__screen doc__sheet">
            <div class="phone__status">
                <span class="doc__num">{{ $n('time') }}</span>
                <span class="phone__camera"></span>
                <span class="phone__signal"><i></i><i></i><i></i></span>
            </div>
            <div class="phone__head" {{ $f('contact') }}>
                <span class="phone__avatar">{{ $t('initial') }}</span>
                <span class="phone__who">
                    <span class="phone__name">{{ $t('contact') }}</span>
                    <span class="phone__seen">{{ Bidi::isolate($t('status')) }}</span>
                </span>
            </div>
            <div class="phone__thread">
                <span class="phone__day doc__num" {{ $f('date') }}>{{ $n('day') }}</span>
                <p class="bubble bubble--in">{{ $t('m1') }}<span class="bubble__time doc__num">{{ $n('stamp') }}</span></p>
                <p class="bubble bubble--out">{{ $t('m2') }}<span class="bubble__time doc__num">{{ $n('stamp') }}</span></p>
                <p class="bubble bubble--in" {{ $f('promise') }}>{{ $t('m3') }}<span class="bubble__time doc__num">{{ $n('stamp') }}</span></p>
                <p class="bubble bubble--out">{{ $t('m4') }}<span class="bubble__time doc__num">{{ $n('stamp') }}</span></p>
            </div>
            <div class="phone__compose">
                <span class="phone__field">{{ $t('input') }}</span>
                <span class="phone__send"></span>
            </div>
        </div>
    </div>
</x-d2.doc.frame>
