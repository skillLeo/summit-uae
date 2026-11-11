@props([
    'block',
    'scope' => [],
])
{{--
    Library block "Call-to-action band" (brief §8.4): an H2, a line of text and one button to a site-map target (P07,
    S06, A01 …) or a full address. Added by an editor only.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $d = (array) ($block['data'] ?? []);
    $bg = ['band' => 'section--band', 'dark' => 'section--dark'][$block['background']] ?? '';
    $id = $block['anchor'] ?? \Illuminate\Support\Str::slug((string) $block['id']);
    $target = is_string($d['button_target'] ?? null) ? trim($d['button_target']) : '';
    $href = $target === '' ? null : (preg_match('#^https?://#i', $target) ? $target : SiteUrl::target($target));
@endphp
<section class="section block-lib {{ $bg }}" id="{{ $id }}" @if (filled($d['heading'] ?? null)) aria-labelledby="{{ $id }}-title" @endif data-copy-source="{{ $block['copy_source'] }}">
    <div class="container block-lib__grid">
        @if (filled($d['heading'] ?? null))
            <div class="block-lib__head"><h2 id="{{ $id }}-title">{{ Bidi::isolate((string) $d['heading']) }}</h2></div>
        @endif
        <div class="block-lib__body">
            @if (filled($d['body'] ?? null))
                <p>{{ Bidi::isolate((string) $d['body']) }}</p>
            @endif
            @if ($href !== null && filled($d['button_label'] ?? null))
                <div class="block-lib__actions">
                    <x-d2.ui.button :variant="$block['background'] === 'dark' ? 'night' : 'ink'" :href="$href">{{ $d['button_label'] }}</x-d2.ui.button>
                </div>
            @endif
        </div>
    </div>
</section>
