@props([
    'items' => [],      // [['kind' => 'phone' | 'email', 'href' => 'tel:…' | 'mailto:…', 'text' => approved label], …], in DOCX order
    'inline' => false,  // the rows side by side (wrapping) instead of stacked
])
{{--
    <x-d2.inner.channels :items="[['kind' => 'phone', 'href' => setting('phone_href'), 'text' => $phoneLabel], …]" />

    The one contact-channel pattern outside the contact block (Design 2.1 §3.5, finding "one contact-channel
    pattern"): a row per channel, the functional glyph (phone, mail: brief §3.6 rule 5) then the page's own
    approved label: the number or the address itself (in a left-to-right <bdi>, so it never reorders inside
    Arabic) or a label such as "Call Us" (P07). The same look as the footer's channels: the UI label (15/500,
    proportional figures: tabular figures are for calculators, tables, prices and references, §3.2), the glyph
    in ink-2 (on-dark-2 on a dark panel), 12 px apart, a 44 px row (48 on touch), the label underlined on hover.
    No box, no border, no arrow. Used by the service and hub CTA panel, the P03 / P04 consultation panel, the
    P05 / P06 / P07 closing panels, the side CTA card and the legal pages' contact links.
--}}
@php
    use App\Design\D2\Bidi;

    $rows = [];
    foreach ((array) $items as $item) {
        if (! is_array($item)) {
            continue;
        }
        $text = trim((string) ($item['text'] ?? ''));
        $href = (string) ($item['href'] ?? '');
        if ($text === '' || $href === '') {
            continue;
        }
        $kind = ($item['kind'] ?? null) === 'email' || str_starts_with($href, 'mailto:') ? 'email' : 'phone';
        $rows[] = [
            'kind' => $kind,
            'href' => $href,
            'text' => $text,
            // The value itself (a number or an address) reads left to right; a label follows the page.
            'ltr' => (bool) preg_match('/^\+?[\d\s().-]{6,}$|^[^\s@]+@[^\s@]+$/u', $text),
        ];
    }
@endphp
@if ($rows !== [])
    <ul {{ $attributes->class(['channels', 'channels--inline' => $inline]) }} role="list">
        @foreach ($rows as $row)
            <li class="channels__item">
                <a class="channel channel--{{ $row['kind'] }}" href="{{ $row['href'] }}">
                    <x-d2.ui.icon :name="$row['kind'] === 'email' ? 'mail' : 'phone'" size="20" class="channel__icon" />
                    @if ($row['ltr'])
                        <bdi class="channel__text" dir="ltr">{{ $row['text'] }}</bdi>
                    @else
                        <span class="channel__text">{{ Bidi::isolate($row['text']) }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
@endif
