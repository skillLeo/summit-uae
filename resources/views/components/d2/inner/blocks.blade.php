@props([
    'blocks' => [],         // normalised blocks (built and documented by the prose component)
    'level' => 3,           // heading level of sub-headings, sub-sections and accordion titles
    'fmt' => null,          // text formatter (prose: escapes, isolates e-mail / phone / URL / Latin runs)
    'resolveLink' => null,  // link resolver (prose: target / url / href → href, kind, icon)
    'qaName' => null,       // exclusive group name of a Q&A accordion
])
{{--
    Internal renderer of <x-d2.inner.prose> (one run of blocks). Use <x-d2.inner.prose :blocks="…"> in
    pages: it prepares the formatter and the link resolver this component expects.
--}}
@php
    use Illuminate\Support\HtmlString;

    $level = max(3, min(6, (int) $level));
    $accLevel = min(4, $level);
    $fmt ??= fn ($text) => new HtmlString(e((string) $text));
    $resolveLink ??= fn (array $link) => ['label' => (string) ($link['label'] ?? ''), 'href' => $link['href'] ?? null, 'kind' => 'page', 'external' => false];
    // Approved items that already carry their own numbers ("1. …", "١. …") get no extra counter.
    $firstText = fn ($item) => is_string($item) ? $item : (string) ($item['title'] ?? $item['text'] ?? '');
    $selfNumbered = fn (array $items) => $items !== [] && count(array_filter($items, fn ($i) => preg_match('/^\s*\p{Nd}+[.)]\s/u', $firstText($i)) === 1)) === count($items);
    $texts = fn ($block) => array_values(array_filter(
        array_merge((array) ($block['paragraphs'] ?? []), is_string($block['text'] ?? null) ? [$block['text']] : (array) ($block['text'] ?? [])),
        fn ($v) => is_string($v) && trim($v) !== ''
    ));
@endphp
@foreach ($blocks as $block)
    @php $type = $block['type']; @endphp
    @if ($type === 'p')
            <p>{{ $fmt($block['text']) }}</p>

    @elseif ($type === 'heading')
            <h{{ $level }} class="prose__subheading">{{ $fmt($block['text'] ?? '') }}</h{{ $level }}>

    @elseif ($type === 'ul' && in_array($block['marker'] ?? null, ['cross', 'check'], true))
            {{-- A marked list: the service pages' check (useful steps) and cross (mistakes to avoid), plain glyphs. --}}
            <x-d2.service.list :items="(array) ($block['items'] ?? [])" :icon="$block['marker'] === 'cross' ? 'close' : 'check'"
                :tone="$block['marker'] === 'cross' ? 'danger' : 'success'" class="prose__marks" />

    @elseif ($type === 'ul' || $type === 'ol')
            <{{ $block['type'] }} @class(['prose__list', 'prose__list--'.$block['type'], 'prose__list--self' => $block['type'] === 'ol' && $selfNumbered((array) ($block['items'] ?? []))])>
                @foreach ((array) ($block['items'] ?? []) as $item)
                    @if (is_string($item))
                        <li>{{ $fmt($item) }}</li>
                    @elseif (is_array($item))
                        <li class="prose__item">
                            @if (! empty($item['title']))<span class="prose__item-title">{{ $fmt($item['title']) }}</span>@endif
                            @foreach ($texts($item) as $text)<span class="prose__item-text">{{ $fmt($text) }}</span>@endforeach
                        </li>
                    @endif
                @endforeach
            </{{ $block['type'] }}>

    @elseif ($type === 'steps')
            <ol @class(['prose__steps', 'prose__steps--self' => $selfNumbered((array) ($block['items'] ?? []))])>
                @foreach ((array) ($block['items'] ?? []) as $item)
                    <li class="prose__step">
                        <span class="prose__step-marker" aria-hidden="true"></span>
                        <div class="prose__step-body">
                            @if (is_string($item))
                                <p>{{ $fmt($item) }}</p>
                            @elseif (is_array($item))
                                @if (! empty($item['title']))<p class="prose__item-title">{{ $fmt($item['title']) }}</p>@endif
                                @foreach ($texts($item) as $text)<p>{{ $fmt($text) }}</p>@endforeach
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>

    @elseif ($type === 'subsections')
            @foreach ((array) ($block['items'] ?? []) as $item)
                <div class="prose__sub">
                    @if (! empty($item['title'] ?? $item['heading'] ?? null))
                        <h{{ $level }} class="prose__subheading">{{ $fmt($item['title'] ?? $item['heading']) }}</h{{ $level }}>
                    @endif
                    @foreach ($texts($item) as $text)
                        <p>{{ $fmt($text) }}</p>
                    @endforeach
                    @if (! empty($item['bullets']))
                        <ul class="prose__list prose__list--ul">
                            @foreach ((array) $item['bullets'] as $bullet)
                                @if (is_string($bullet) && trim($bullet) !== '')<li>{{ $fmt($bullet) }}</li>@endif
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach

    @elseif ($type === 'note')
            @php
                $tone = in_array($block['tone'] ?? null, ['info', 'warning', 'success', 'danger'], true) ? $block['tone'] : 'info';
                $noteTitle = is_string($block['title'] ?? null) && trim($block['title']) !== '' ? $block['title'] : null;
            @endphp
            <x-d2.ui.callout :tone="$tone" :title="$noteTitle" :size="($block['size'] ?? 'md') === 'sm' ? 'sm' : 'md'" class="prose__note">
                @foreach ($texts($block) as $text)
                    <p>{{ $fmt($text) }}</p>
                @endforeach
            </x-d2.ui.callout>

    @elseif ($type === 'qa')
            <x-d2.ui.accordion :name="$qaName" :level="$accLevel" variant="flush" class="prose__qa">
                @foreach ((array) ($block['items'] ?? []) as $item)
                    @continue (! is_array($item) || empty($item['question']))
                    <x-d2.ui.accordion-item :id="is_string($item['id'] ?? null) ? $item['id'] : null" :title="$fmt($item['question'])">
                        @foreach ($texts(['paragraphs' => $item['answer'] ?? []]) as $text)
                            <p>{{ $fmt($text) }}</p>
                        @endforeach
                    </x-d2.ui.accordion-item>
                @endforeach
            </x-d2.ui.accordion>

    @elseif ($type === 'links')
            @php
                // In the given (DOCX) order: telephone and e-mail rows are the kit's contact channels (one pattern with
                // the CTA panels and the footer); page links and links to other websites are link rows.
                $runs = [];
                foreach ((array) ($block['items'] ?? []) as $link) {
                    if (! is_array($link)) {
                        continue;
                    }
                    $l = $resolveLink($link);
                    if (! $l['href'] || $l['label'] === '') {
                        continue;
                    }
                    $channel = in_array($l['kind'], ['email', 'phone'], true);
                    $last = array_key_last($runs);
                    if ($last !== null && $runs[$last]['channel'] === $channel) {
                        $runs[$last]['items'][] = $l;
                    } else {
                        $runs[] = ['channel' => $channel, 'items' => [$l]];
                    }
                }
            @endphp
            @foreach ($runs as $run)
                @if ($run['channel'])
                    <x-d2.inner.channels class="prose__channels" :items="array_map(fn ($l) => ['kind' => $l['kind'], 'href' => $l['href'], 'text' => $l['label']], $run['items'])" />
                @else
                    <ul class="prose__links">
                        @foreach ($run['items'] as $l)
                            <li>
                                {{-- Design 2.1 (brief §3.6 rules 3 and 5): no object icon and no arrow or chevron on a row; a link
                                     to another website keeps its ↗. --}}
                                <a class="link-row link-row--{{ $l['kind'] }}" href="{{ $l['href'] }}" @if ($l['external']) target="_blank" rel="noopener noreferrer" @endif>
                                    <span class="link-row__label">{{ $fmt($l['label']) }}</span>
                                    @if ($l['external'])
                                        <x-d2.ui.icon name="external" size="20" class="link-row__end" />
                                        <span class="sr-only" @proposed>{{ d2p('controls.new_tab') }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
    @endif
@endforeach
