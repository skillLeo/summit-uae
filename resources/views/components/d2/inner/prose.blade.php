@props([
    'sections' => [],           // DOCX sections, in order (see "Section shapes"); each becomes a <section id>
    'blocks' => [],             // or one run of blocks without section headings
    'level' => 2,               // heading level of the section headings (2–5); sub-headings use level + 1
    'idPrefix' => 'section',    // section ids {idPrefix}-01, -02 … (index in `sections`) unless a section has its own 'id'
    'size' => 'md',             // md (body 17/28) | lg (body-L 19/30, short pages)
])
{{--
    Design 2 prose: renders approved DOCX structures faithfully in the 720 px reading column (§7.4, §12 D).

    <x-d2.inner.prose :sections="$page['content']['sections']" id-prefix="section" />
    <x-d2.inner.prose :blocks="$guide['body']['intro']" />
    <x-d2.inner.toc :sections="…the same array…" id-prefix="section" />   ← same ids, same order

    Section shapes (each item of `sections`):
      canonical  ['heading' => ?string, 'id' => ?string, 'blocks' => [block, …]]
      raw DOCX   any mapper section, read in its stored (DOCX) order: heading | title, lead, intro, text, body,
                 paragraphs, closing (paragraphs) · items, bullets, principles, questions (lists; items of
                 {question, answer} become an accordion, items with paragraphs become H3 sub-sections) ·
                 steps (numbered sequence) · note, notice (callout) · links, link (link rows) · blocks.
                 Other keys (anchors, labels, menus) are ignored: render them yourself.
      options    'variant' => 'note' draws the whole section as a Phase A callout (scope notes; the heading
                 becomes the callout's heading), 'tone' => info | warning | success | danger.
                 'variant' => 'panel' draws it as the band panel of the service pages' "Mistakes to Avoid"
                 (heading inside, no icon): with a marked list (below) one treatment on every page (D07, §3.6 rule 5).
    Block shapes:
      'text' or ['type' => 'p', 'text']                          paragraph
      ['type' => 'ul' | 'ol', 'items' => [string | ['title', 'text'] | ['title', 'paragraphs', 'bullets']]]
      ['type' => 'ul', 'marker' => 'cross' | 'check', 'items' => [string]]   the service pages' marked list
                 (<x-d2.service.list>): a cross per mistake to avoid, a check per useful step (§3.6 rule 5)
      ['type' => 'steps', 'items' => [string | ['title', 'text']]]      numbered sequence (1, 2 … in CSS)
      ['type' => 'note', 'text' | 'paragraphs', 'title' => ?, 'tone' => ?, 'size' => md | sm]   callout
      ['type' => 'heading' | 'h3', 'text']                        sub-heading (level + 1)
      ['type' => 'qa', 'items' => [['question', 'answer', 'id' => ?]]]  accordion (ids such as S01-Q01 kept)
      ['type' => 'links', 'items' => [['label', 'target' | 'url' | 'href']]]  link rows: target
                 = site-map ID (SiteUrl::target, so P07 follows the release rule), url = external (new tab,
                 the ↗ glyph), href = as given; e-mail and telephone (mailto:/tel:, EMAIL, TEL) are the kit's
                 contact channels instead (<x-d2.inner.channels>: glyph + the value left to right), in the
                 given order; no other icon or arrow on a row (brief §3.6 rules 3 and 5)
    The default slot is printed after the sections (extra markup inside the same column).

    Text is approved copy printed unchanged and escaped: e-mail addresses, phone numbers and URLs get their
    own <bdi dir="ltr">, and on Arabic pages Latin runs are isolated (App\Design\D2\Bidi). The approved H2
    numbering ("1. …", "٢. …") is printed verbatim; no list counter is added.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;
    use Illuminate\Support\HtmlString;

    $level = max(2, min(5, (int) $level));
    $sub = min(6, $level + 1);
    $size = $size === 'lg' ? 'lg' : 'md';

    // E-mail addresses, URLs and phone numbers keep their own direction; the rest goes through Bidi::isolate.
    $isolatePattern = '/([\w.+-]+@[\w-]+(?:\.[\w-]+)+|https?:\/\/[^\s<>"]+[^\s<>".,;:!?)]|www\.[^\s<>"]+[^\s<>".,;:!?)]|\+\d[\d ()-]{6,}\d)/u';
    $fmt = function ($text) use ($isolatePattern): HtmlString {
        $text = is_scalar($text) ? (string) $text : '';
        $parts = preg_split($isolatePattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $html = '';
        foreach ($parts as $i => $part) {
            $html .= $i % 2 === 1 ? '<bdi dir="ltr">'.e($part).'</bdi>' : Bidi::isolate($part)->toHtml();
        }

        return new HtmlString($html);
    };
    $strings = fn ($value): array => array_values(array_filter(is_array($value) ? $value : [$value], fn ($v) => is_string($v) && trim($v) !== ''));

    // A list of items becomes an accordion (Q&A), H3 sub-sections (items with paragraphs) or a plain list.
    $listBlock = function (array $items, string $type = 'ul'): ?array {
        $items = array_values(array_filter($items, fn ($i) => (is_string($i) && trim($i) !== '') || (is_array($i) && $i !== [])));
        if ($items === []) {
            return null;
        }
        $first = $items[0];
        if (is_array($first) && isset($first['question'])) {
            return ['type' => 'qa', 'items' => $items];
        }
        if (is_array($first) && isset($first['paragraphs'])) {
            return ['type' => 'subsections', 'items' => $items];
        }

        return ['type' => $type, 'items' => $items];
    };

    // Raw DOCX section → blocks, in the stored (DOCX) key order.
    $blocksOf = function (array $section) use ($strings, $listBlock): array {
        $out = [];
        foreach ($section as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $block = match (true) {
                $key === 'blocks' => null,
                in_array($key, ['lead', 'intro', 'text', 'body', 'paragraphs', 'closing', 'narrative'], true) => 'paragraphs',
                in_array($key, ['items', 'bullets', 'principles', 'questions'], true) && is_array($value) => $listBlock($value),
                $key === 'steps' && is_array($value) => ['type' => 'steps', 'items' => array_values($value)],
                in_array($key, ['note', 'notice'], true) => ['type' => 'note', 'paragraphs' => $strings($value), 'size' => 'sm'],
                $key === 'links' && is_array($value) && array_is_list($value) => ['type' => 'links', 'items' => $value],
                $key === 'link' && is_array($value) && isset($value['label']) => ['type' => 'links', 'items' => [$value]],
                default => false,
            };
            if ($key === 'blocks' && is_array($value)) {
                array_push($out, ...array_values($value));
            } elseif ($block === 'paragraphs') {
                // Paragraph lists may also hold typed blocks (guides store intro as [{type, text}]).
                foreach (is_array($value) ? $value : [$value] as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $out[] = ['type' => 'p', 'text' => $item];
                    } elseif (is_array($item) && isset($item['type'])) {
                        $out[] = $item;
                    }
                }
            } elseif (is_array($block)) {
                $out[] = $block;
            }
        }

        return $out;
    };

    $normalise = function (array $blocks) use ($strings): array {
        $out = [];
        foreach ($blocks as $block) {
            if (is_string($block)) {
                $block = ['type' => 'p', 'text' => $block];
            }
            if (! is_array($block)) {
                continue;
            }
            $type = (string) ($block['type'] ?? 'p');
            $type = match ($type) {
                'h3', 'h4', 'subheading' => 'heading',
                'callout' => 'note',
                default => $type,
            };
            if ($type === 'p' && ! $strings($block['text'] ?? null)) {
                continue;
            }
            $out[] = ['type' => $type] + $block;
        }

        return $out;
    };

    $resolveLink = function (array $link): array {
        $label = (string) ($link['label'] ?? '');
        $href = null;
        $external = false;
        if (is_string($link['href'] ?? null) && $link['href'] !== '') {
            $href = $link['href'];
        } elseif (is_string($link['url'] ?? null) && $link['url'] !== '') {
            $href = $link['url'];
            $external = (bool) preg_match('#^https?://#', $href);
        } elseif (is_string($link['target'] ?? null) && $link['target'] !== '') {
            $target = $link['target'];
            $href = match ($target) {
                'EMAIL' => (string) setting('email_href'),
                'TEL' => (string) setting('phone_href'),
                default => SiteUrl::target($target),
            };
        }
        $kind = match (true) {
            is_string($href) && str_starts_with($href, 'mailto:') => 'email',
            is_string($href) && str_starts_with($href, 'tel:') => 'phone',
            $external => 'external',
            default => 'page',
        };

        return ['label' => $label, 'href' => $href, 'kind' => $kind, 'external' => $external];
    };

    // Sections: canonical or raw; ids follow the index in the given array (the TOC uses the same rule).
    $items = [];
    foreach (array_values((array) $sections) as $i => $section) {
        if (is_string($section)) {
            $section = ['blocks' => [$section]];
        }
        if (! is_array($section)) {
            continue;
        }
        $heading = $section['heading'] ?? $section['title'] ?? null;
        $raw = $section;
        unset($raw['heading'], $raw['title'], $raw['id'], $raw['variant'], $raw['tone']);
        $items[] = [
            'id' => is_string($section['id'] ?? null) && $section['id'] !== '' ? $section['id'] : sprintf('%s-%02d', $idPrefix, $i + 1),
            'heading' => is_string($heading) && trim($heading) !== '' ? $heading : null,
            'variant' => in_array($section['variant'] ?? null, ['note', 'panel'], true) ? $section['variant'] : 'default',
            'tone' => in_array($section['tone'] ?? null, ['info', 'warning', 'success', 'danger'], true) ? $section['tone'] : 'info',
            'blocks' => $normalise($blocksOf($raw)),
        ];
    }
    $loose = $normalise((array) $blocks);
@endphp
<div {{ $attributes->class(['prose', 'prose--'.$size]) }}>
    @if ($loose !== [])
        <x-d2.inner.blocks :blocks="$loose" :level="$sub" :fmt="$fmt" :resolve-link="$resolveLink" :qa-name="$idPrefix.'-qa'" />
    @endif

    @foreach ($items as $section)
        <section class="prose__section prose__section--{{ $section['variant'] }}" id="{{ $section['id'] }}">
            @if ($section['variant'] === 'note')
                <x-d2.ui.callout :tone="$section['tone']" :title="$section['heading']" :title-level="$level" class="prose__callout">
                    <x-d2.inner.blocks :blocks="$section['blocks']" :level="$sub" :fmt="$fmt" :resolve-link="$resolveLink" :qa-name="$section['id'].'-qa'" />
                </x-d2.ui.callout>
            @else
                @if ($section['heading'])
                    <h{{ $level }} class="prose__heading">{{ $fmt($section['heading']) }}</h{{ $level }}>
                @endif
                <x-d2.inner.blocks :blocks="$section['blocks']" :level="$sub" :fmt="$fmt" :resolve-link="$resolveLink" :qa-name="$section['id'].'-qa'" />
            @endif
        </section>
    @endforeach

    {{ $slot }}
</div>
