@props([
    'title',                    // required: the page H1 (approved: $h1 from PagePreviewController)
    'crumbs' => [],             // [['label' => …, 'href' => ?string], …] ($crumbs); the last one is the current page
    'intro' => [],              // approved introduction: a string or a list of paragraphs ($intro)
    'size' => 'display',        // display | document (long reading pages: the text column keeps the 720 px reading measure)
    'tone' => 'canvas',         // canvas | surface (white with a hairline under it)
    'titleId' => 'page-title',  // id of the H1
    'titleTag' => 'h1',         // h1 | p (styleguide specimens only: one H1 per page)
])
{{--
    Design 2 inner-page head (brief §3.2, §3.6; upgrade spec §7.4).

    <x-d2.inner.head :title="$h1" :crumbs="$crumbs" :intro="$intro">
        <x-slot:meta><p class="legal__updated">{{ $page['last_updated'] }}</p></x-slot:meta>   (under the H1)
        <x-slot:actions><x-d2.ui.button …>…</x-d2.ui.button></x-slot:actions>                  (CTA row)
        <x-slot:aside>…"Documents That Help Us Get Started"…</x-slot:aside>                      (side column ≥ 1280)
    </x-d2.inner.head>

    Order: breadcrumbs (nav > ol; the current page is plain text with aria-current="page"; long labels wrap,
    never cut), the H1 (the Display role, 56 / 40), meta, the intro paragraphs (Lead, ink-2), actions. With an
    aside slot the head is two columns from 1280 px (main 7 / aside 5) and stacks below.

    Design 2.1 (Phase 1): no icon object, no eyebrow and no large-title collapse any more (brief §3.6 rules 5, 7
    and 8: a decorative tile, a scroll-linked animation and a bar title cut with an ellipsis). The H1 stays on the
    type scale at every width: a word too wide for a phone's column at 40 px (about 8 em: «конфиденциальности» is
    ~9.9 em) gets .inner-head__title--fit, which lets the browser hyphenate that title (the page's lang chooses
    the dictionary) instead of shrinking it below the scale.
    Approved copy is printed with Bidi::isolate (Latin runs isolated on Arabic pages).
--}}
@php
    use App\Design\D2\Bidi;

    $intro = array_values(array_filter(is_array($intro) ? $intro : [$intro], fn ($p) => is_string($p) && trim($p) !== ''));
    $crumbs = array_values(array_filter((array) $crumbs, fn ($c) => is_array($c) && trim((string) ($c['label'] ?? '')) !== ''));
    $size = $size === 'document' ? 'document' : 'display';
    $tone = $tone === 'surface' ? 'surface' : 'canvas';
    $hasAside = isset($aside) && trim((string) $aside) !== '';
    $titleTag = $titleTag === 'p' ? 'p' : 'h1';
    // A word wider than a 360 px phone's column at the 40 px Display size (320 px = 8 em) may hyphenate
    // (inner.css .inner-head__title--fit) rather than overflow or leave the type scale. Estimated from the
    // letters (semibold widths of the interface faces): Cyrillic ≈ 0.58 em, Latin capitals ≈ 0.62, other
    // Latin ≈ 0.5; Arabic words are short and never hyphenated.
    $wordEm = function (string $word): float {
        $em = 0.0;
        foreach (mb_str_split($word) as $char) {
            $em += match (true) {
                (bool) preg_match('/\p{Cyrillic}/u', $char) => 0.58,
                (bool) preg_match('/\p{Lu}/u', $char) => 0.62,
                (bool) preg_match('/\p{Arabic}/u', $char) => 0.45,
                default => 0.5,
            };
        }

        return $em;
    };
    $widest = max(array_map($wordEm, preg_split('/[\s\x{00A0}]+/u', trim((string) $title)) ?: ['']) ?: [0]);
    $fitTitle = $widest > 8.0;
@endphp
<header {{ $attributes->class([
        'inner-head',
        'inner-head--'.$size,
        'inner-head--surface' => $tone === 'surface',
        'inner-head--aside' => $hasAside,
    ]) }}>
    <div class="container inner-head__inner">
        <div class="inner-head__main">
            @if ($crumbs !== [])
                <nav class="crumbs" aria-label="{{ proposed('nav.breadcrumb') }}">
                    <ol class="crumbs__list">
                        @foreach ($crumbs as $crumb)
                            <li class="crumbs__item">
                                @if (! empty($crumb['href']) && ! $loop->last)
                                    <a class="crumbs__link" href="{{ $crumb['href'] }}">{{ Bidi::isolate($crumb['label']) }}</a>
                                    <x-d2.ui.icon name="chevron" size="16" class="crumbs__sep" />
                                @else
                                    <span class="crumbs__current" aria-current="page">{{ Bidi::isolate($crumb['label']) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            <{{ $titleTag }} @class(['inner-head__title', 'inner-head__title--fit' => $fitTitle]) id="{{ $titleId }}">{{ Bidi::isolate($title) }}</{{ $titleTag }}>

            @isset($meta)
                <div class="inner-head__meta">{{ $meta }}</div>
            @endisset

            @foreach ($intro as $paragraph)
                <p class="inner-head__intro">{{ Bidi::isolate($paragraph) }}</p>
            @endforeach

            @isset($actions)
                <div class="inner-head__actions">{{ $actions }}</div>
            @endisset
        </div>

        @if ($hasAside)
            <div class="inner-head__aside">{{ $aside }}</div>
        @endif
    </div>
</header>
