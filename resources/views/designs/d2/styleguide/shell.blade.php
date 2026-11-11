{{--
    Styleguide: Design 2.1 shell (brief §3.7, §3.8, D06, D12, D13, D14, D47, D48): the header at the top of the
    page and compact (docked and tucked), the Practice Areas mega menu, the language menu, the search trigger, the
    collapsed navigation, and the footer (its own block, owned by footer-shell). Annotations are internal:
    lang/en/styleguide_d2/shell.php. Every specimen is the real component with approved copy, rendered
    statically (demo="…": not sticky, no Alpine) with its own id prefix. The desktop-only states sit in a
    frame at least 70rem (1,120 px) wide, where the seven labels fit in every language (it scrolls sideways on
    narrow screens; the page never does); the docked bar needs 78rem and is scaled to its card (header.css), so no
    approved label is cut. Each header specimen's explanation is a .sg-note under it (on the Arabic page a
    left-to-right reading block whose lines start on one edge, styleguide.css).
--}}
@php
    $t = fn (string $key, array $replace = []) => __('styleguide_d2/shell.'.$key, $replace);
    $s06 = content()->service('S06', 'en') ?? [];   // named in the (English) annotation only
    // Annotations are English on every page: say so for screen readers on the Arabic and Russian pages.
    $en = app()->isLocale('en') ? '' : 'lang="en"';
@endphp
<section class="sg-section" id="sg-shell">
    <h2 {!! $en !!}>{{ $t('title') }}</h2>
    <p {!! $en !!}>{{ $t('intro') }}</p>

    {{-- ------------------------------------------------------------------ header: block owned by header (Design 2.1) --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('header') }}</h3>
    <div class="d2-shell-demos">
        {{-- At the specimen's own width: the phone, tablet or desktop header. --}}
        <div class="sg-specimen d2-shell-demo d2-shell-demo--canvas">
            <x-d2.shell.header current="home" id-prefix="sg-top" demo="top" />
            <p class="sg-note" {!! $en !!}>{{ $t('top') }}</p>
        </div>

        {{-- Compact. Docked: the seven labels in the 64 px bar (English and Arabic in a full container; Russian's
             never fit beside its tools, so its page shows the tucked bar only). Tucked: the menu button instead. --}}
        @unless (app()->isLocale('ru'))
            <div class="sg-specimen d2-shell-demo d2-shell-demo--canvas">
                <div class="d2-shell-demo__frame d2-shell-demo__frame--docked">
                    <x-d2.shell.header current="practice" id-prefix="sg-docked" demo="docked" />
                </div>
                <p class="sg-note" {!! $en !!}>{{ $t('docked') }}</p>
            </div>
        @endunless

        <div class="sg-specimen d2-shell-demo d2-shell-demo--canvas">
            <div class="d2-shell-demo__frame">
                <x-d2.shell.header current="practice" id-prefix="sg-tucked" demo="tucked" />
            </div>
            <p class="sg-note" {!! $en !!}>{{ $t('tucked') }}</p>
        </div>

        <div class="sg-specimen d2-shell-demo d2-shell-demo--canvas">
            <div class="d2-shell-demo__frame d2-shell-demo__frame--mega">
                <x-d2.shell.header current="practice" id-prefix="sg-mega" demo="mega" preview="S06" />
            </div>
            <p class="sg-note" {!! $en !!}>{{ $t('mega', ['service' => $s06['title'] ?? 'S06']) }}</p>
        </div>

        <div class="sg-specimen d2-shell-demo d2-shell-demo--canvas">
            <div class="d2-shell-demo__frame d2-shell-demo__frame--lang">
                <x-d2.shell.header id-prefix="sg-lang" demo="lang" />
            </div>
            <p class="sg-note" {!! $en !!}>{{ $t('lang') }}</p>
        </div>

        <div class="sg-specimen d2-shell-demo d2-shell-demo--canvas">
            <div class="d2-shell-demo__frame">
                <x-d2.shell.header id-prefix="sg-search" demo="search" />
            </div>
            <p class="sg-note" {!! $en !!}>{{ $t('search') }}</p>
        </div>

        <div class="sg-specimen d2-shell-demo d2-shell-demo--canvas">
            <div class="d2-shell-demo__frame">
                <x-d2.shell.header id-prefix="sg-collapsed" demo="collapsed" />
            </div>
            <p class="sg-note" {!! $en !!}>{{ $t('collapsed') }}</p>
        </div>

        <div class="sg-specimen d2-shell-demo">
            <p class="sg-note" {!! $en !!}>{{ $t('rules') }}</p>
        </div>
    </div>
    {{-- ------------------------------------------------------------------ end of the header block --}}

    {{-- ------------------------------------------------------------------ footer: block owned by footer-shell (Design 2.1, D47) --}}
    {{-- Its two annotations are running text, so they are the frame's notes under the heading (heading, notes,
         specimen), not specimen labels: on the Arabic page each is a left-to-right block on the reading column at
         the page's start edge, aligned with the other notes (styleguide.css), never a right-aligned ragged label. --}}
    <h3 class="sg-sub" {!! $en !!}>{{ $t('footer') }}</h3>
    <p class="sg-note" {!! $en !!}>{{ $t('footer_note') }}</p>
    <p class="sg-note" {!! $en !!}>{{ $t('footer_rules') }}</p>
    <div class="sg-specimen d2-shell-demo d2-shell-demo--footer">
        <x-d2.shell.footer id-prefix="sg" />
    </div>
    {{-- ------------------------------------------------------------------ end of the footer block --}}
</section>
