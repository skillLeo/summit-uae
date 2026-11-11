{{--
    Styleguide: Matter Navigator (home #navigator), every state of the list, its controls on night, the
    checklist progress and "Your starting file". Static specimens (no controller): forced states use the
    Phase A styleguide modifiers (.is-hover / .is-focus) and the components' state props. Annotations are
    internal (lang/en/styleguide_d2/navigator.php); the demo content is approved copy (NavigatorData) plus
    the navigator area's proposed copy. Every specimen has its own id prefix and radio group name.
--}}
@php
    use App\Content\NavigatorData;

    $t = fn (string $key) => __('styleguide_d2/navigator.'.$key);
    $en = app()->isLocale('en') ? '' : 'lang=en';
    $services = app(NavigatorData::class)->services();
    $s06 = collect($services)->firstWhere('code', 'S06') ?? ($services[0] ?? []);
    $total = count($s06['documents'] ?? []);
    $num = fn (int $n) => "\u{2068}{$n}\u{2069}";
    $line = fn (int $ready) => new \Illuminate\Support\HtmlString((string) preg_replace('/\x{2068}(.*?)\x{2069}/u', '<bdi>$1</bdi>',
        e(trans_choice('proposed.d2.navigator.progress', $total, ['ready' => $num($ready), 'total' => $num($total)]))));
    $firstSituation = $s06['situations'][0] ?? '';
@endphp
<section class="sg-section" id="sg-navigator">
    <h2 {{ $en }}>{{ $t('title') }}</h2>
    <p {{ $en }}>{{ $t('intro') }}</p>

    {{-- ------------------------------------------------------------------ list --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('list') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('list_note') }}</p>
    <div class="sg-night on-night nav-specimen" data-sg="navigator-list">
        <div class="sg-grid sg-grid--wide">
            <div class="nav-specimen__col">
                <div class="nav-panel">
                    <x-d2.navigator.list :services="$services" :only="['S05']" prefix="sg-nav-a" name="sg-nav-a" selected="S05-4"
                        :states="['S05-2' => 'hover', 'S05-3' => 'focus']" />
                </div>
                <p class="sg-label" {{ $en }}>{{ $t('list_states') }}</p>
            </div>
            <div class="nav-specimen__col">
                {{-- The pressed chip is the first service's, so it shows in the static rail at every width (round 2). --}}
                <x-d2.navigator.tools :services="$services" prefix="sg-nav-b" pressed="S01" />
                <div class="nav-panel">
                    <x-d2.navigator.list :services="$services" :only="['S01']" prefix="sg-nav-b" name="sg-nav-b" />
                </div>
                <p class="sg-label" {{ $en }}>{{ $t('filtered') }}</p>
            </div>
            <div class="nav-specimen__col">
                <x-d2.navigator.tools :services="$services" prefix="sg-nav-c" search="is-focus" />
                <div class="nav-panel">
                    <x-d2.navigator.list :services="$services" :only="[]" prefix="sg-nav-c" name="sg-nav-c" empty-visible />
                </div>
                <p class="sg-label" {{ $en }}>{{ $t('empty_search') }}</p>
            </div>
        </div>
        <div class="nav-specimen__col nav-specimen__tools">
            {{-- Focus on the second chip (always whole in the static rail), hover on the third (round 2). --}}
            <x-d2.navigator.tools :services="$services" prefix="sg-nav-t" :chip-states="['S01' => 'focus', 'S02' => 'hover']" />
            <p class="sg-label" {{ $en }}>{{ $t('chip_states') }}. {{ $t('tools') }}</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ controls --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('controls') }}</h3>
    <div class="sg-night on-night nav-specimen" data-sg="navigator-controls">
        <div class="sg-grid">
            @foreach (['rest' => null, 'hover' => 'hover', 'focus' => 'focus', 'checked' => null] as $label => $state)
                <div class="nav-specimen__col">
                    <x-d2.navigator.item :ref="'S06-'.$loop->iteration" :situation="$s06['situations'][$loop->index] ?? $firstSituation"
                        prefix="sg-nav-r" :name="'sg-nav-r-'.$label" :checked="$label === 'checked'" :state="$state" />
                    <p class="sg-label" {{ $en }}>{{ $t('radio_'.$label) }}</p>
                </div>
            @endforeach
        </div>
        <div class="nav-file on-light nav-specimen__card">
            <div class="sg-grid">
                @foreach (['rest' => null, 'hover' => 'is-hover', 'focus' => 'is-focus', 'checked' => null] as $label => $modifier)
                    <div class="nav-specimen__col">
                        <x-d2.ui.checkbox :id="'sg-nav-cb-'.$label" :label="$s06['documents'][$loop->index] ?? ''" :class="$modifier" :checked="$label === 'checked'" />
                        <p class="sg-label" {{ $en }}>{{ $t('check_'.$label) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ progress --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('progress') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('progress_note') }}</p>
    <div class="sg-grid" data-sg="navigator-progress">
        @foreach (['none' => 0, 'some' => 2, 'all' => $total] as $label => $ready)
            <div class="sg-specimen">
                <div class="nav-file__progress" @proposed>
                    <x-d2.ui.progress :id="'sg-nav-progress-'.$label" :value="$ready" :max="max($total, 1)" :label="$line($ready)" />
                </div>
                <p class="sg-label" {{ $en }}>{{ $t('progress_'.$label) }}</p>
            </div>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ card --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('card') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('card_note') }}</p>
    <div class="sg-night on-night nav-specimen" data-sg="navigator-card">
        <x-d2.navigator.card :services="$services" :selected="['service' => 'S06', 'index' => 2]" :only="['S06']" prefix="sg-nav-d"
            :ticked="['S06' => [0, 2]]" :forced="['copy' => 'focus']" tip="bottom" />
        <p class="sg-label" {{ $en }}>{{ $t('card_filled') }}</p>
        <div class="sg-grid sg-grid--wide">
            <div class="nav-specimen__col">
                <x-d2.navigator.card :services="$services" :only="[]" prefix="sg-nav-e" />
                <p class="sg-label" {{ $en }}>{{ $t('card_empty') }}</p>
            </div>
            <div class="nav-specimen__col">
                <p class="sg-label" {{ $en }}>{{ $t('card_mobile') }}</p>
            </div>
        </div>
    </div>
</section>
