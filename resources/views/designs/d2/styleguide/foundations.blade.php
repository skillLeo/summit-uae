{{--
    Styleguide: Design 2.1 foundations (docs/briefs/design-2.1-and-admin-cms.md §3.3 and §3.4; contract
    docs/d2.1/phase-1/tokens.md): colour with its contrast, the badge pairs, elevation, radius, space, layout and
    grid, the focus ring, motion, layers and the Design 2.0 names that are now aliases. Every value is read from
    resources/css/d2/tokens.css when the page renders; contrast is computed from the token values (WCAG 2.2
    relative luminance). Colours and bars are drawn as SVG from those values (the CSP forbids style attributes);
    shadows, radii and easings use their var(--…) through the .sg-elev--*, .sg-radius--* and .sg-ease--* classes
    in resources/css/d2/styleguide.css. Annotations are internal: lang/en/styleguide_d2/foundations.php.
--}}
@php
    $t = fn (string $key, array $replace = []) => __('styleguide_d2/foundations.'.$key, $replace);
    $en = app()->isLocale('en') ? '' : 'lang="en"';

    // ------------------------------------------------------------------ tokens.css
    $css = (string) @file_get_contents(resource_path('css/d2/tokens.css'));
    $tokens = [];
    $notes = [];                                           // the token's trailing /* comment */
    if (preg_match('/^:root\s*\{(.*?)^\}/ms', $css, $block)) {
        foreach (preg_split('/\R/', $block[1]) as $line) {
            if (preg_match('/^\s*--([a-z0-9-]+)\s*:\s*(.+?);\s*(?:\/\*\s*(.*?)\s*\*\/)?\s*$/i', $line, $m)) {
                $tokens[$m[1]] ??= trim($m[2]);
                $notes[$m[1]] ??= trim($m[3] ?? '');
            }
        }
    }
    $breakpoints = [0 => $tokens];
    preg_match_all('/@media\s*\(width\s*>=\s*(\d+)px\)\s*\{\s*:root\s*\{(.*?)\}\s*\}/s', $css, $queries, PREG_SET_ORDER);
    foreach ($queries as $query) {
        $values = end($breakpoints);
        foreach (preg_split('/\R/', $query[2]) as $line) {
            if (preg_match('/--([a-z0-9-]+)\s*:\s*([^;]+);/', $line, $m)) {
                $values[$m[1]] = trim($m[2]);
            }
        }
        $breakpoints[(int) $query[1]] = $values;
    }
    // follow var() aliases to the value they stand for
    $resolve = function (?string $value) use ($tokens): ?string {
        for ($hops = 0; $value !== null && $hops < 8 && preg_match('/^var\(--([a-z0-9-]+)\)$/', $value, $m); $hops++) {
            $value = $tokens[$m[1]] ?? null;
        }

        return $value;
    };
    $px = fn (?string $v) => $v !== null && preg_match('/^(-?[\d.]+)px$/', $v, $m) ? (float) $m[1] : null;
    $ms = fn (?string $v) => $v !== null && preg_match('/^([\d.]+)ms$/', $v, $m) ? (float) $m[1] : null;
    $num = fn (float $n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    $known = fn (array $names) => array_values(array_filter($names, fn ($n) => isset($tokens[$n])));

    // ------------------------------------------------------------------ colour
    $rgb = function (?string $v): ?array {
        if ($v === null || ! preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $v, $m)) {
            return null;
        }
        $h = strlen($m[1]) === 3 ? preg_replace('/(.)/', '$1$1', $m[1]) : $m[1];

        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    };
    $luminance = function (array $c): float {
        [$r, $g, $b] = array_map(function (int $x): float {
            $x /= 255;

            return $x <= 0.04045 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4;
        }, $c);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    };
    // contrast of two token names or two #hex values
    $contrast = function (string $a, string $b) use ($tokens, $rgb, $luminance, $resolve): ?float {
        $ca = $rgb(str_starts_with($a, '#') ? $a : $resolve($tokens[$a] ?? null));
        $cb = $rgb(str_starts_with($b, '#') ? $b : $resolve($tokens[$b] ?? null));
        if (! $ca || ! $cb) {
            return null;
        }
        [$la, $lb] = [$luminance($ca), $luminance($cb)];

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    };
    $grade = fn (float $r) => $r >= 7 ? 'aaa' : ($r >= 4.5 ? 'aa' : ($r >= 3 ? 'large' : 'fail'));
    $groups = [
        'surfaces' => ['canvas', 'band', 'dark', 'dark-2', 'dark-3'],
        'text' => ['ink', 'ink-2', 'ink-3', 'on-dark', 'on-dark-2', 'on-dark-3', 'on-dark-gold'],
        'lines' => ['line', 'line-strong', 'field-border', 'line-on-dark', 'field-border-on-dark'],
        'brand' => ['gold', 'gold-hover', 'bronze', 'bronze-700'],
        'states' => ['danger', 'success', 'warning', 'info', 'whatsapp', 'success-on-dark', 'warning-on-dark', 'danger-on-dark'],
        'interaction' => ['focus', 'focus-on-dark', 'ink-hover', 'canvas-hover', 'dark-hover', 'selection'],
    ];
    // text colour => the surfaces it is read on (WCAG 1.4.3)
    $textOn = [
        'ink' => ['canvas', 'band'], 'ink-2' => ['canvas', 'band'], 'ink-3' => ['canvas', 'band'],
        'on-dark' => ['dark', 'dark-2'], 'on-dark-2' => ['dark', 'dark-2'], 'on-dark-3' => ['dark', 'dark-2'], 'on-dark-gold' => ['dark'],
        'bronze' => ['canvas', 'band'], 'bronze-700' => ['canvas', 'badge-brand-bg'],
        'danger' => ['canvas'], 'success' => ['canvas'], 'warning' => ['canvas'], 'info' => ['canvas'],
    ];
    // surface => the text read on it
    $carries = ['canvas' => 'ink', 'band' => 'ink', 'dark' => 'on-dark', 'dark-2' => 'on-dark', 'dark-3' => 'on-dark',
        'gold' => 'ink', 'gold-hover' => 'ink', 'ink-hover' => 'on-dark', 'canvas-hover' => 'ink', 'dark-hover' => 'on-dark', 'selection' => 'ink'];
    // control boundaries, status marks and the focus rings (WCAG 1.4.11 asks 3:1 against what is next to them)
    $boundary = ['field-border' => ['canvas', 'band'], 'field-border-on-dark' => ['dark', 'dark-2'],
        'whatsapp' => ['canvas'], 'success-on-dark' => ['dark'], 'warning-on-dark' => ['dark'], 'danger-on-dark' => ['dark'],
        'focus' => ['canvas', 'band'], 'focus-on-dark' => ['dark']];
    $badges = ['neutral', 'info', 'success', 'warning', 'danger', 'brand'];
    // §3.3 "Not allowed": [text, background, why]
    $forbidden = [['bronze', 'badge-brand-bg', 'no_bronze_on_brand'], ['on-dark', 'whatsapp', 'no_white_on_whatsapp'], ['field-border-on-dark', 'dark', 'no_grey_on_dark']];
    $translucent = $known(['hover-tint', 'dark-hover-tint', 'scrim', 'scrim-menu']);
    $paper = array_filter($tokens, fn ($v, $n) => (bool) preg_match('/^(paper|phone|bubble|receipt)/', $n) && $rgb($v), ARRAY_FILTER_USE_BOTH);
    // Design 2.0 names that are now aliases: --old: var(--new) (or a value) with a "legacy" comment
    $legacy = array_filter($tokens, fn ($v, $n) => str_starts_with($notes[$n] ?? '', 'legacy'), ARRAY_FILTER_USE_BOTH);

    // ------------------------------------------------------------------ elevation, radius, space
    $paperShadows = $known(['shadow-paper-rest', 'shadow-paper-air', 'shadow-paper-lift']);
    $radii = $known(['radius-control', 'radius-card', 'radius-overlay', 'radius-pill']);
    $docRadii = $known(['r-paper', 'r-phone']);
    $space = array_filter($tokens, fn ($v, $n) => preg_match('/^s-\d+$/', $n) && ! in_array($n, ['s-10', 's-20'], true) && $px($v) !== null, ARRAY_FILTER_USE_BOTH);
    uasort($space, fn ($a, $b) => $px($a) <=> $px($b));
    $tiers = [0 => 'phone', 640 => 'large', 768 => 'tablet', 1024 => 'desktop'];
    $sizes = $known(['button-h', 'button-h-hero', 'field-h', 'chip-h', 'badge-h', 'icon-size', 'target', 'target-touch', 'tabbar-h', 'prose', 'modal-w', 'sheet-side-w']);

    // ------------------------------------------------------------------ motion, layers
    $layers = array_filter($tokens, fn ($v, $n) => str_starts_with($n, 'z-') && is_numeric($v), ARRAY_FILTER_USE_BOTH);
    asort($layers, SORT_NUMERIC);
    $durations = array_filter($tokens, fn ($v, $n) => str_starts_with($n, 'dur-') && $ms($v) !== null && ! str_starts_with($notes[$n] ?? '', 'legacy'), ARRAY_FILTER_USE_BOTH);
    asort($durations, SORT_NATURAL);
    $maxDuration = max(array_map($ms, $durations) ?: [1]);
    $beziers = [];
    $springs = [];
    foreach ($tokens as $name => $value) {
        if (str_starts_with($name, 'ease-') && preg_match('/^cubic-bezier\(([^)]*)\)$/', $value, $m)) {
            $beziers[$name] = array_map('floatval', array_map('trim', explode(',', $m[1])));
        } elseif (preg_match('/^spring-([a-z]+)$/', $name, $s) && preg_match('/^linear\(([^)]*)\)$/', $value, $m)) {
            $springs[$name] = ['points' => array_map('floatval', array_map('trim', explode(',', $m[1]))), 'dur' => $tokens[$name.'-dur'] ?? null];
        }
    }
    // plot: x 12..188, value -0.1..1.15 mapped to y 108..12
    $plotX = fn (float $x) => round(12 + $x * 176, 2);
    $plotY = fn (float $v) => round(108 - ($v + 0.1) / 1.25 * 96, 2);
    $springPath = function (array $points) use ($plotX, $plotY): string {
        $n = count($points) - 1;

        return implode(' ', array_map(fn ($v, $i) => $plotX($n ? $i / $n : 0).','.$plotY($v), $points, array_keys($points)));
    };
    $bezierPath = fn (array $c) => 'M'.$plotX(0).' '.$plotY(0).' C'.$plotX($c[0]).' '.$plotY($c[1]).' '.$plotX($c[2]).' '.$plotY($c[3]).' '.$plotX(1).' '.$plotY(1);
@endphp
<section class="sg-section" id="sg-foundations">
    <h2 {!! $en !!}>{{ $t('title') }}</h2>
    <p {!! $en !!}>{{ $t('intro') }}</p>

    {{-- ------------------------------------------------------------------ colour --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('colour') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('colour_note') }}</p>
    @foreach ($groups as $group => $names)
        <h4 class="sg-minor" {!! $en !!}>{{ $t('groups.'.$group) }}</h4>
        <ul class="sg-swatches" role="list">
            @foreach ($known($names) as $name)
                @php
                    $value = $tokens[$name];
                    $rows = [];
                    foreach ($textOn[$name] ?? [] as $surface) {
                        if (($r = $contrast($name, $surface)) !== null) {
                            $rows[] = [$r, $t('on', ['surface' => '--'.$surface]), $grade($r)];
                        }
                    }
                    if ($name === 'gold' && ($r = $contrast('gold', 'canvas')) !== null) {
                        $rows[] = [$r, $t('on', ['surface' => '--canvas']), 'never'];
                    }
                    if (isset($carries[$name]) && ($r = $contrast($carries[$name], $name)) !== null) {
                        $rows[] = [$r, $t('with_text', ['text' => '--'.$carries[$name]]), $grade($r)];
                    }
                    foreach ($boundary[$name] ?? [] as $surface) {
                        if (($r = $contrast($name, $surface)) !== null) {
                            $rows[] = [$r, $t('non_text', ['surface' => '--'.$surface]), $r >= 3 ? 'ui' : 'fail'];
                        }
                    }
                @endphp
                <li class="sg-swatch">
                    <svg class="sg-swatch__chip" viewBox="0 0 160 64" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect width="160" height="64" fill="{{ $value }}" /></svg>
                    <div class="sg-swatch__body" {!! $en !!}>
                        <code class="sg-code">--{{ $name }}</code>
                        <span class="sg-swatch__value">{{ $value }}</span>
                        <span class="sg-swatch__role">{{ \Illuminate\Support\Facades\Lang::has('styleguide_d2/foundations.roles.'.$name, 'en') ? $t('roles.'.$name) : ($notes[$name] ?? '') }}</span>
                        @if ($rows)
                            <ul class="sg-contrast" role="list">
                                @foreach ($rows as [$ratio, $where, $level])
                                    <li>
                                        <span class="sg-contrast__ratio">{{ number_format($ratio, 2) }}:1</span>
                                        <span>{{ $where }}</span>
                                        <span class="sg-grade sg-grade--{{ in_array($level, ['fail', 'never'], true) ? 'fail' : 'pass' }}">{{ $t('grade_'.$level) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @elseif (! isset($carries[$name]))
                            <span class="sg-swatch__role">{{ $t('decorative') }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endforeach

    {{-- badge pairs: text on its own background --}}
    <h4 class="sg-minor" {!! $en !!}>{{ $t('groups.badges') }}</h4>
    <ul class="sg-pairs" role="list">
        @foreach ($badges as $badge)
            @php
                [$fg, $bg] = ['badge-'.$badge.'-fg', 'badge-'.$badge.'-bg'];
                $r = $contrast($fg, $bg);
            @endphp
            @continue (! isset($tokens[$fg], $tokens[$bg]))
            <li class="sg-pair">
                <svg class="sg-pair__chip" viewBox="0 0 120 32" aria-hidden="true" focusable="false">
                    <rect width="120" height="32" rx="6" fill="{{ $tokens[$bg] }}" />
                    <rect x="12" y="13" width="56" height="6" rx="3" fill="{{ $tokens[$fg] }}" />
                </svg>
                <div class="sg-swatch__body" {!! $en !!}>
                    <span><code class="sg-code">--{{ $fg }}</code> <span class="sg-swatch__value">{{ $tokens[$fg] }}</span></span>
                    <span><code class="sg-code">--{{ $bg }}</code> <span class="sg-swatch__value">{{ $tokens[$bg] }}</span></span>
                    @if ($r !== null)
                        <span class="sg-contrast__ratio">{{ number_format($r, 2) }}:1 <span class="sg-grade sg-grade--{{ $r >= 4.5 ? 'pass' : 'fail' }}">{{ $t('grade_'.$grade($r)) }}</span></span>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    {{-- §3.3 "Not allowed" --}}
    <h4 class="sg-minor" {!! $en !!}>{{ $t('groups.forbidden') }}</h4>
    <ul class="sg-pairs" role="list">
        @foreach ($forbidden as [$fg, $bg, $why])
            @php $r = $contrast($fg, $bg); @endphp
            @continue ($r === null)
            <li class="sg-pair">
                <svg class="sg-pair__chip" viewBox="0 0 120 32" aria-hidden="true" focusable="false">
                    <rect width="120" height="32" rx="6" fill="{{ $resolve($tokens[$bg]) }}" />
                    <rect x="12" y="13" width="56" height="6" rx="3" fill="{{ $resolve($tokens[$fg]) }}" />
                </svg>
                <div class="sg-swatch__body" {!! $en !!}>
                    <span><code class="sg-code">--{{ $fg }}</code> {{ $t('on', ['surface' => '--'.$bg]) }}</span>
                    <span class="sg-contrast__ratio">{{ number_format($r, 2) }}:1 <span class="sg-grade sg-grade--fail">{{ $t('grade_never') }}</span></span>
                    <span class="sg-swatch__role">{{ $t($why) }}</span>
                </div>
            </li>
        @endforeach
    </ul>

    @if ($translucent)
        <h4 class="sg-minor" {!! $en !!}>{{ $t('groups.translucent') }}</h4>
        <ul class="sg-layers" role="list" {!! $en !!}>
            @foreach ($translucent as $name)
                <li class="sg-layers__row"><code class="sg-code">--{{ $name }}</code><span class="sg-layers__value">{{ $tokens[$name] }}</span><span class="sg-layers__role">{{ $notes[$name] }}</span></li>
            @endforeach
        </ul>
    @endif

    @if ($paper)
        <h4 class="sg-minor" {!! $en !!}>{{ $t('groups.paper') }}</h4>
        <p class="sg-label" {!! $en !!}>{{ $t('paper_note') }}</p>
        <ul class="sg-chips" role="list" {!! $en !!}>
            @foreach ($paper as $name => $value)
                <li class="sg-chip-row">
                    <svg class="sg-chip-row__dot" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect width="16" height="16" rx="3" fill="{{ $value }}" /></svg>
                    <code class="sg-code">--{{ $name }}</code>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- ------------------------------------------------------------------ elevation --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('elevation') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('elevation_note') }}</p>
    <div class="sg-grid">
        <div class="sg-specimen">
            <div class="sg-well"><div class="sg-elev sg-elev--card"></div></div>
            <p class="sg-label" {!! $en !!}>{{ $t('elev_card') }}</p>
        </div>
        {{-- Lint markers (qa/README.md): the overlay specimen is the one overlay shadow (data-overlay); the paper
             specimens are sample-document art (data-illustration), the only place paper shadows may appear. --}}
        @isset($tokens['shadow-overlay'])
            <div class="sg-specimen">
                <div class="sg-well sg-well--band"><div class="sg-elev sg-elev--shadow-overlay" data-overlay></div></div>
                <p class="sg-label" {!! $en !!}><code class="sg-code">--shadow-overlay</code></p>
                <p class="sg-label" {!! $en !!}>{{ $t('elev_overlay') }}</p>
            </div>
        @endisset
        @foreach ($paperShadows as $name)
            <div class="sg-specimen">
                <div class="sg-well sg-well--band">
                    <div class="sg-paper sg-paper--{{ \Illuminate\Support\Str::after($name, 'shadow-paper-') }}" data-illustration aria-hidden="true">
                        <span></span><span></span><span></span><span></span><span></span>
                    </div>
                </div>
                <p class="sg-label" {!! $en !!}><code class="sg-code">--{{ $name }}</code></p>
                <p class="sg-label" {!! $en !!}>{{ $t('elev_paper') }}</p>
            </div>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ radius --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('radius') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('radius_note') }}</p>
    <div class="sg-grid">
        @foreach ([...$radii, ...$docRadii] as $name)
            <div class="sg-specimen">
                <div class="sg-radius sg-radius--{{ $name }}"></div>
                <p class="sg-label" {!! $en !!}><code class="sg-code">--{{ $name }}</code> {{ $tokens[$name] }}</p>
                <p class="sg-label" {!! $en !!}>{{ $t('radius_roles.'.$name) }}</p>
            </div>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ space --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('space') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('space_note') }}</p>
    <ul class="sg-space" role="list" {!! $en !!}>
        @foreach ($space as $name => $value)
            <li class="sg-space__row">
                <code class="sg-code">--{{ $name }}</code>
                <span class="sg-space__value">{{ $value }}</span>
                <svg class="sg-space__bar" width="{{ $px($value) }}" height="12" viewBox="0 0 {{ $px($value) }} 12" aria-hidden="true" focusable="false"><rect width="{{ $px($value) }}" height="12" rx="2" /></svg>
            </li>
        @endforeach
    </ul>

    {{-- ------------------------------------------------------------------ layout and grid --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('layout') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('layout_note', ['container' => $tokens['container'] ?? '']) }}</p>
    <div class="grid sg-cols" aria-hidden="true">
        @for ($i = 1; $i <= 12; $i++)
            <span>{{ $i }}</span>
        @endfor
    </div>
    <div class="sg-grid sg-bps" {!! $en !!}>
        @foreach ($tiers as $min => $tier)
            @php $v = $breakpoints[$min] ?? end($breakpoints); @endphp
            <div class="sg-specimen sg-bp sg-bp--{{ $tier }}">
                <p class="sg-bp__name">{{ $t('bp_'.$tier) }} <span class="sg-bp__now">{{ $t('bp_current') }}</span></p>
                <ul class="sg-bp__facts" role="list">
                    <li>{{ $t('bp_pad', ['pad' => $v['pad-x'] ?? '']) }}</li>
                    <li>{{ $t('bp_cols', ['cols' => $v['cols'] ?? '', 'gutter' => $v['gutter'] ?? '']) }}</li>
                    <li>{{ $t('bp_section', ['section' => $v['section-y'] ?? '']) }}</li>
                    <li>{{ $t('bp_card', ['card' => $v['pad-card'] ?? '']) }}</li>
                    <li>{{ $t('bp_header', ['top' => $v['header-h-top'] ?? '', 'compact' => $v['header-h'] ?? '']) }}</li>
                </ul>
            </div>
        @endforeach
    </div>
    <p class="sg-label" {!! $en !!}>{{ $t('rhythm_note') }}</p>
    @if ($sizes)
        <h4 class="sg-minor" {!! $en !!}>{{ $t('sizes') }}</h4>
        <ul class="sg-layers" role="list" {!! $en !!}>
            @foreach ($sizes as $name)
                <li class="sg-layers__row"><code class="sg-code">--{{ $name }}</code><span class="sg-layers__value">{{ $resolve($tokens[$name]) }}</span><span class="sg-layers__role">{{ $notes[$name] ?? '' }}</span></li>
            @endforeach
        </ul>
    @endif

    {{-- ------------------------------------------------------------------ focus --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('focus') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('focus_note') }}</p>
    <div class="sg-grid">
        <div class="sg-specimen">
            <div class="sg-well"><span class="sg-focus" aria-hidden="true"></span></div>
            <p class="sg-label" {!! $en !!}><code class="sg-code">--focus</code> {{ $tokens['focus'] ?? '' }}</p>
        </div>
        <div class="sg-specimen">
            <div class="sg-well sg-well--dark on-dark"><span class="sg-focus" aria-hidden="true"></span></div>
            <p class="sg-label" {!! $en !!}><code class="sg-code">--focus-on-dark</code> {{ $tokens['focus-on-dark'] ?? '' }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ motion --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('motion') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('motion_note') }}</p>
    <h4 class="sg-minor" {!! $en !!}>{{ $t('durations') }}</h4>
    <ul class="sg-durations" role="list" {!! $en !!}>
        @foreach ($durations as $name => $value)
            @php $w = round($ms($value) / $maxDuration * 240, 1); @endphp
            <li class="sg-durations__row">
                <code class="sg-code">--{{ $name }}</code>
                <span class="sg-durations__value">{{ $value }}</span>
                <svg class="sg-durations__bar" viewBox="0 0 240 12" preserveAspectRatio="xMinYMid meet" aria-hidden="true" focusable="false"><rect class="sg-durations__track" width="240" height="12" rx="6" /><rect width="{{ max($w, 12) }}" height="12" rx="6" /></svg>
                <span class="sg-durations__role">{{ $notes[$name] ?? '' }}</span>
            </li>
        @endforeach
    </ul>

    <h4 class="sg-minor" {!! $en !!}>{{ $t('easings') }}</h4>
    <div class="sg-grid sg-grid--narrow">
        @foreach ($beziers as $name => $c)
            <div class="sg-specimen sg-curve">
                <svg class="sg-curve__plot" viewBox="0 0 200 120" aria-hidden="true" focusable="false">
                    <line class="sg-curve__axis" x1="12" x2="188" y1="{{ $plotY(0) }}" y2="{{ $plotY(0) }}" />
                    <line class="sg-curve__target" x1="12" x2="188" y1="{{ $plotY(1) }}" y2="{{ $plotY(1) }}" />
                    <path class="sg-curve__path" d="{{ $bezierPath($c) }}" />
                </svg>
                <span class="sg-ease sg-ease--{{ $name }}" aria-hidden="true"><span class="sg-ease__dot"></span></span>
                <p class="sg-label" {!! $en !!}><code class="sg-code">--{{ $name }}</code></p>
                <p class="sg-label" {!! $en !!}>cubic-bezier({{ implode(', ', array_map($num, $c)) }})</p>
            </div>
        @endforeach
    </div>

    <h4 class="sg-minor" {!! $en !!}>{{ $t('springs') }}</h4>
    <div class="sg-grid sg-grid--narrow">
        @foreach ($springs as $name => $spring)
            <div class="sg-specimen sg-curve">
                <svg class="sg-curve__plot" viewBox="0 0 200 120" aria-hidden="true" focusable="false">
                    <line class="sg-curve__axis" x1="12" x2="188" y1="{{ $plotY(0) }}" y2="{{ $plotY(0) }}" />
                    <line class="sg-curve__target" x1="12" x2="188" y1="{{ $plotY(1) }}" y2="{{ $plotY(1) }}" />
                    <polyline class="sg-curve__path" points="{{ $springPath($spring['points']) }}" />
                </svg>
                <span class="sg-ease sg-ease--{{ $name }}" aria-hidden="true"><span class="sg-ease__dot"></span></span>
                <p class="sg-label" {!! $en !!}><code class="sg-code">--{{ $name }}</code></p>
                <p class="sg-label" {!! $en !!}>{{ $spring['dur'] ? $t('duration', ['ms' => $num((float) $ms($spring['dur']))]) : '' }} {{ $notes[$name.'-dur'] ?? '' }}</p>
            </div>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ layers --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('layers') }}</h3>
    <ol class="sg-layers" role="list" {!! $en !!}>
        @foreach (array_reverse($layers, true) as $name => $value)
            <li class="sg-layers__row">
                <code class="sg-code">--{{ $name }}</code>
                <span class="sg-layers__value">{{ $value }}</span>
                <span class="sg-layers__role">{{ $t('z.'.$name) }}</span>
            </li>
        @endforeach
    </ol>

    {{-- ------------------------------------------------------------------ legacy names --}}
    @if ($legacy)
        <h3 class="sg-sub" {!! $en !!}>{{ $t('legacy') }}</h3>
        <p class="sg-note" {!! $en !!}>{{ $t('legacy_note') }}</p>
        <ul class="sg-legacy" role="list" {!! $en !!}>
            @foreach ($legacy as $name => $value)
                @php $note = trim(preg_replace('/^legacy:?\s*/', '', $notes[$name])); @endphp
                <li class="sg-legacy__row">
                    <code class="sg-code">--{{ $name }}</code>
                    @if (preg_match('/^var\((--[a-z0-9-]+)\)$/', $value, $target))
                        <code class="sg-code sg-legacy__to">{{ $target[1] }}</code>
                    @else
                        <span class="sg-layers__value sg-legacy__to">{{ $value }}</span>
                    @endif
                    @if ($note !== '')
                        <span class="sg-layers__role sg-legacy__note">{{ $note }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
