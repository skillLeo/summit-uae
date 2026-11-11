@props([
    'code',               // service code (S01 … S11)
    'situations' => [],   // approved "This service may be relevant if:" situations of this service (P02 card)
])
{{--
    The related Matter Navigator situations of a service (upgrade spec §12 D). Each approved situation is a
    whole-card link to the home Navigator with that situation's starting file open: /xx/?navigator=S06-2#navigator.
    That URL works without JavaScript (the home renders the chosen file on the server and the browser jumps to
    #navigator) and with it (the Navigator selects the server-checked situation and reveals its file); the
    Navigator itself then writes its #navigator=S06-2 deep link. The footer line is proposed copy.
    Design 2.1 (brief §3.6 rules 3 and 5): a text card, no file glyph and no arrow; hover turns the edge ink
    and underlines the situation (card.css).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $situations = array_values(array_filter((array) $situations, fn ($s) => is_string($s) && trim($s) !== ''));
@endphp
@if ($situations !== [])
    <ul {{ $attributes->class(['svc-sits']) }} role="list">
        @foreach ($situations as $i => $situation)
            @php $ref = $code.'-'.($i + 1); @endphp
            <x-d2.ui.card as="li" padding="md" class="svc-sit" :href="SiteUrl::home().'?navigator='.$ref.'#navigator'"
                :title="Bidi::isolate($situation)" title-level="0" data-situation="{{ $ref }}">
                <x-slot:footer class="svc-sit__foot">
                    <span class="svc-sit__open" @proposed>{{ d2p('service_page.situation_open') }}</span>
                </x-slot:footer>
            </x-d2.ui.card>
        @endforeach
    </ul>
@endif
