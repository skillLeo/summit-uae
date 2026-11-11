{{--
    Styleguide: Design 2 home sections (owner: sections): the practice-area card in every
    state (rest, hover, focus, a row of the equal grid, the closing row) with the live audience filter and a
    filtered snapshot, the workflow steps with their line (in a row, stacked), the review's paragraphs and questions
    (D49), the five who-we-help blocks, and the "Start your matter" result half (quick picks, documents, no picks:
    D19). Annotations: lang/en/styleguide_d2/sections.php
    (marked lang="en" on Arabic and Russian pages). Demo content is approved copy only. Forced states use the
    styleguide-only modifiers .is-hover / .is-focus; ids carry an sg- prefix (never the home anchors).
--}}
@php
    use App\Support\SiteUrl;

    $t = fn (string $key) => __('styleguide_d2/sections.'.$key);
    $en = app()->isLocale('en') ? '' : 'lang=en';

    $p01 = content()->page('P01')['content'] ?? [];
    $p02 = content()->page('P02')['content'] ?? [];
    $p06 = content()->page('P06')['content'] ?? [];
    $p07 = content()->page('P07')['content'] ?? [];
    $sgService = fn (string $code) => content()->service($code) ?? ['code' => $code];
    $sgMap = array_filter((array) config('audience_map.hero', []), 'is_array');
    $sgAudiences = fn (string $code) => array_keys(array_filter($sgMap, fn (array $codes) => in_array($code, $codes, true)));

    $who = $p01['who_we_help'] ?? [];
    $whoItems = array_values($who['items'] ?? []);
    $whoTitles = [];
    foreach ($whoItems as $item) {
        $whoTitles[$item['key']] = $item['title'];
    }

    // Live filter demo: five practice areas.
    $demo = ['S01', 'S02', 'S03', 'S04', 'S05'];
    $demoLabels = array_filter(array_intersect_key([
        'individual' => $whoTitles['individuals'] ?? '',
        'business' => $whoTitles['businesses'] ?? '',
        'investor' => $whoTitles['investors'] ?? '',
    ], $sgMap));
    $demoShown = fn (?string $audience) => $audience === null ? count($demo) : count(array_filter($demo, fn (string $code) => in_array($audience, $sgAudiences($code), true)));
    $review = $p01['review'] ?? [];
    $steps = array_values($review['steps'] ?? []);
    $paragraphs = array_values($review['paragraphs'] ?? []);
    $questions = array_values($review['questions'] ?? []);
@endphp
<section class="sg-section" id="sg-sections">
    <h2 {{ $en }}>{{ $t('title') }}</h2>
    <p {{ $en }}>{{ $t('intro') }}</p>

    {{-- ================================================================== practice-area card --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('card') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('card_note') }}</p>
    <div class="sg-grid" data-sg="svc-states">
        <div class="sg-specimen">
            <x-d2.home.service-card as="div" :service="$sgService('S02')" :audiences="$sgAudiences('S02')" />
            <p class="sg-label" {{ $en }}>{{ $t('card_rest') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.home.service-card as="div" class="is-hover" :service="$sgService('S05')" :audiences="$sgAudiences('S05')" />
            <p class="sg-label" {{ $en }}>{{ $t('card_hover') }}</p>
        </div>
        <div class="sg-specimen">
            <x-d2.home.service-card as="div" class="is-focus" :service="$sgService('S08')" :audiences="$sgAudiences('S08')" />
            <p class="sg-label" {{ $en }}>{{ $t('card_focus') }}</p>
        </div>
    </div>
    <div class="sg-grid sg-grid--full" data-sg="svc-row">
        <div class="sg-specimen">
            <ul class="svc__grid" role="list">
                @foreach (['S06', 'S07', 'S09'] as $code)
                    <x-d2.home.service-card :service="$sgService($code)" :audiences="$sgAudiences($code)" />
                @endforeach
            </ul>
            <p class="sg-label" {{ $en }}>{{ $t('card_row') }}</p>
        </div>
    </div>
    <div class="sg-grid sg-grid--full" data-sg="svc-more">
        <div class="sg-specimen">
            <div class="svc__more">
                <p class="svc__more-text">{{ $p01['services']['closing'] ?? '' }}</p>
                <x-d2.ui.button variant="ink" class="svc__more-action" :href="SiteUrl::page('P02')">{{ ui('cta.view_all_practice_areas') }}</x-d2.ui.button>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('closing') }}</p>
        </div>
    </div>

    {{-- ================================================================== audience filter --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('filter') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('filter_note') }}</p>
    @foreach (['live' => null, 'filtered' => 'investor'] as $state => $pressed)
        <div class="sg-grid sg-grid--full" data-sg="svc-{{ $state }}">
            <div class="sg-specimen svc" x-data="d2Services">
                <div class="svc__filter" role="group" aria-labelledby="sg-svc-{{ $state }}-label" data-svc-filter x-on:click="pick">
                    <span class="sr-only" id="sg-svc-{{ $state }}-label" @proposed>{{ d2p('services.filter_label') }}</span>
                    <x-d2.ui.chip :pressed="$pressed === null" :count="$demoShown(null)" data-svc-audience="all"
                        :data-svc-result="trans_choice('proposed.d2.services.result', $demoShown(null))"><span @proposed>{{ d2p('services.filter_all') }}</span></x-d2.ui.chip>
                    @foreach ($demoLabels as $audience => $label)
                        <x-d2.ui.chip :pressed="$pressed === $audience" :count="$demoShown($audience)" :data-svc-audience="$audience"
                            :data-svc-result="trans_choice('proposed.d2.services.result', $demoShown($audience))">{{ $label }}</x-d2.ui.chip>
                    @endforeach
                </div>
                <ul class="svc__grid" role="list" data-svc-grid>
                    @foreach ($demo as $code)
                        <x-d2.home.service-card :service="$sgService($code)" :audiences="$sgAudiences($code)"
                            :hidden="$pressed !== null && ! in_array($pressed, $sgAudiences($code), true)" />
                    @endforeach
                </ul>
                <p class="sg-label" {{ $en }}>{{ $t('filter_'.$state) }}</p>
            </div>
        </div>
    @endforeach

    {{-- ================================================================== steps --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('steps') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('steps_note') }}</p>
    <div class="sg-grid sg-grid--full" data-sg="steps">
        <div class="sg-specimen">
            <x-d2.home.steps :steps="$steps" />
            <p class="sg-label" {{ $en }}>{{ $t('steps_row') }}</p>
        </div>
    </div>
    <div class="sg-grid sg-grid--wide" data-sg="steps-stacked">
        <div class="sg-specimen">
            <x-d2.home.steps :steps="array_slice($steps, 0, 3)" class="method__steps--stacked" />
            <p class="sg-label" {{ $en }}>{{ $t('steps_stacked') }}</p>
        </div>
    </div>

    {{-- ================================================================== review: paragraphs + questions (D49) --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('questions') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('questions_note') }}</p>
    <div class="sg-grid sg-grid--full" data-sg="questions">
        <div class="sg-specimen">
            <div class="grid method__intro">
                <div class="method__text">
                    @foreach ($paragraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
                <div class="method__questions">
                    <h4 class="method__heading">{{ $review['questions_heading'] ?? '' }}</h4>
                    <ul class="method__checklist" role="list">
                        @foreach ($questions as $question)
                            <li class="method__question">{{ $question }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('questions_layout') }}</p>
        </div>
    </div>

    {{-- ================================================================== who we help (five blocks) --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('who') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('who_note') }}</p>
    <div class="sg-grid sg-grid--full" data-sg="who-list">
        <div class="sg-specimen">
            <x-d2.home.who-list :items="$whoItems" id-prefix="sg-who" />
            <p class="sg-label" {{ $en }}>{{ $t('who_list') }}</p>
        </div>
    </div>

    {{-- ================================================================== start your matter: the result half (D19) --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('start') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('start_note') }}</p>
    <div class="sg-grid sg-grid--full" data-sg="start-picks">
        <div class="sg-specimen">
            <x-d2.hero.tool id-prefix="sg-start-picks" />
            <p class="sg-label" {{ $en }}>{{ $t('start_picks') }}</p>
        </div>
    </div>
    <div class="sg-grid sg-grid--full" data-sg="start-documents">
        <div class="sg-specimen">
            <x-d2.hero.tool situation="S01-2" id-prefix="sg-start-documents" />
            <p class="sg-label" {{ $en }}>{{ $t('start_documents') }}</p>
        </div>
    </div>
    <div class="sg-grid sg-grid--full" data-sg="start-none">
        <div class="sg-specimen">
            <x-d2.hero.tool :picks="[]" id-prefix="sg-start-none" />
            <p class="sg-label" {{ $en }}>{{ $t('start_none') }}</p>
        </div>
    </div>
</section>
