@props([
    'heading' => null,        // approved heading (e.g. a service's sections.related.heading); null: no visible heading
    'label' => null,          // accessible name when there is no heading (default: proposed "Related pages")
    'items' => [],            // [['label', 'target' | 'href' | 'url', 'text' => ?, 'meta' => ?], …]
    'variant' => 'links',     // links (whole-row links, 1–2 columns) | cards (a card grid)
    'columns' => 3,           // cards: 2 | 3 (≥ 1280 px; 2 from 768 px, 1 below)
    'level' => 2,             // heading level of the block (card titles use level + 1)
    'id' => null,
])
{{--
    Design 2 related links / related cards (upgrade spec §7.4: related guides, FAQs, situations, service).

    <x-d2.inner.related :heading="$related['heading']" :items="$related['links']" />                 rows
    <x-d2.inner.related :heading="…" variant="cards" :items="[['label' => …, 'target' => 'G01', 'text' => …]]" />

    Targets are site-map IDs resolved with SiteUrl::target() (P07 follows the contact release rule; S01–S11,
    G01–G09, case-01…, faq-NN, P0x, L0x); `url` is external (new tab, hidden "opens in a new tab"); `href` is
    used as given. Rows: the approved label and optional approved text; the whole row is the link (its label
    underlines on hover). Design 2.1 (brief §3.6 rules 3 and 5): no object icon, arrow or chevron; a telephone
    or e-mail row keeps its functional glyph and a link to another website ends with the ↗ glyph. Cards:
    <x-d2.ui.card href> (title = the one link, stretched), text only.
    Items without a label or a destination are dropped. External destinations belong in the links variant
    (a card's stretched title link always opens in the same tab). Rendered as a <section> with its heading, or a
    <nav aria-label> without one.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $variant = $variant === 'cards' ? 'cards' : 'links';
    $columns = (int) $columns === 2 ? 2 : 3;
    $level = max(2, min(5, (int) $level));
    $hasHeading = is_string($heading) && trim($heading) !== '';
    $rows = [];
    foreach ((array) $items as $item) {
        if (! is_array($item)) {
            continue;
        }
        $rowLabel = trim((string) ($item['label'] ?? $item['title'] ?? ''));
        $external = false;
        $href = null;
        if (is_string($item['href'] ?? null) && $item['href'] !== '') {
            $href = $item['href'];
        } elseif (is_string($item['url'] ?? null) && $item['url'] !== '') {
            $href = $item['url'];
            $external = (bool) preg_match('#^https?://#', $href);
        } elseif (is_string($item['target'] ?? null) && $item['target'] !== '') {
            $href = match ($item['target']) {
                'EMAIL' => (string) setting('email_href'),
                'TEL' => (string) setting('phone_href'),
                default => SiteUrl::target($item['target']),
            };
        }
        if ($rowLabel === '' || ! $href) {
            continue;
        }
        $rows[] = [
            'label' => $rowLabel,
            'href' => $href,
            'external' => $external,
            'kind' => match (true) {
                str_starts_with($href, 'mailto:') => 'email',
                str_starts_with($href, 'tel:') => 'phone',
                $external => 'external',
                default => 'page',
            },
            'text' => is_string($item['text'] ?? null) ? $item['text'] : null,
            'meta' => is_string($item['meta'] ?? null) ? $item['meta'] : null,
        ];
    }
    $id ??= 'related-'.substr(md5(json_encode($rows)), 0, 6);
    $titleId = $id.'-title';
    $tag = $hasHeading ? 'section' : 'nav';
@endphp
@if ($rows !== [])
<{{ $tag }} {{ $attributes->class(['related', 'related--'.$variant, 'related--cols-'.$columns]) }} id="{{ $id }}"
    @if ($hasHeading) aria-labelledby="{{ $titleId }}" @else aria-label="{{ is_string($label) && $label !== '' ? $label : d2p('inner.related_label') }}" @endif>
    @if ($hasHeading)
        <h{{ $level }} class="related__title" id="{{ $titleId }}">{{ Bidi::isolate($heading) }}</h{{ $level }}>
    @endif

    @if ($variant === 'cards')
        <ul class="related__grid">
            @foreach ($rows as $row)
                <x-d2.ui.card as="li" :href="$row['href']" :title="$row['label']" :title-level="min(6, $level + 1)" class="related__card">
                    @if ($row['text'])<p class="related__text">{{ Bidi::isolate($row['text']) }}</p>@endif
                    @if ($row['meta'])<p class="related__meta">{{ Bidi::isolate($row['meta']) }}</p>@endif
                </x-d2.ui.card>
            @endforeach
        </ul>
    @else
        <ul class="related__list">
            @foreach ($rows as $row)
                <li>
                    <a class="link-row link-row--{{ $row['kind'] }} related__row" href="{{ $row['href'] }}"
                        @if ($row['external']) target="_blank" rel="noopener noreferrer" @endif>
                        @if (in_array($row['kind'], ['email', 'phone'], true))
                            <x-d2.ui.icon :name="$row['kind'] === 'email' ? 'mail' : 'phone'" size="20" class="link-row__icon" />
                        @endif
                        <span class="link-row__body">
                            @if (in_array($row['kind'], ['email', 'phone'], true))
                                <span class="link-row__label"><bdi dir="ltr">{{ $row['label'] }}</bdi></span>
                            @else
                                <span class="link-row__label">{{ Bidi::isolate($row['label']) }}</span>
                            @endif
                            @if ($row['text'])<span class="link-row__text">{{ Bidi::isolate($row['text']) }}</span>@endif
                        </span>
                        @if ($row['external'])
                            <x-d2.ui.icon name="external" size="20" class="link-row__end" />
                            <span class="sr-only" @proposed>{{ d2p('controls.new_tab') }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</{{ $tag }}>
@endif
