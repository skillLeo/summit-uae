@props([
    'services' => null,       // Matter Navigator services (App\Content\NavigatorData::services()); default: built here
    'audience' => 'individual',
    'situation' => null,      // a situation code such as S06-2 (preselected)
    'map' => null,            // audience => service codes (default config('audience_map.hero')); an unmapped audience lists everything
    'picks' => null,          // quick-pick situation codes (default: the featured situations of the CMS); at most three
    'idPrefix' => 'hero',
    'interactive' => true,    // false: styleguide specimen without the d2HeroTool script
])
{{--
    Tool card "Start your matter" (upgrade spec §12 B1; contracts D, H, I in docs/d2/engineering.md §8; Design 2.1
    brief D19). The home's switchable block under the hero (sections/home/start.blade.php); copy pending approval.
      - audience: <x-d2.ui.segmented> Individual | Business | Investor (proposed labels);
      - "Your situation": the 44 approved Navigator situations grouped by service (optgroups); hero/tool.js
        keeps only the services config('audience_map.hero') maps to the chosen audience (all of them when
        the audience has no mapping);
      - the result half (D19: never an empty box):
          before a choice, "Common situations" and up to three quick picks (the featured situations of the CMS,
          the admin sets them in Phase 4): <x-d2.ui.chip>s holding the approved situation text, no icon, no dot.
          A pick follows the audience like the select does (hidden when the audience does not list its service);
          choosing one selects that situation, shows its documents and moves focus to the select. Each pick is
          a submit button (name="situation"), so without JavaScript it sends the card with that situation;
          after a choice, "Documents usually needed: N" (trans_choice, the polite live line) and the N approved
          documents of the chosen service, each complete (D09) and without decorative icons (D04);
        one panel at a time (.is-off = not displayed, so also hidden from assistive technology);
      - Continue (an Ink button without an arrow: D03, D06): prefill({service, situation, audience})
        (resources/js/d2/lib/prefill.js) scrolls to the wizard. Without JavaScript the card is a GET form to
        App\Design\D2\Prefill::url(): ?audience=…&situation=S06-2#consultation-form (Prefill::fromRequest()
        derives the service). Continue comes first in the form, so it stays the default (Enter) button.
    Layout by the card's own width (container queries in sections/start.css on .hero-tool__body): one column,
    then the situation and Continue side by side (from 420 px), then the form and the result side by side
    (from 52rem: the home block from 1024 px).
    Every situation, service title and document is approved copy; the card's own strings are proposed.
--}}
@php
    use App\Content\NavigatorData;
    use App\Design\D2\Bidi;
    use App\Design\D2\Prefill;

    $services ??= (new NavigatorData(content()))->services();
    $map ??= (array) config('audience_map.hero', []);
    // The featured situations of the CMS (situations.featured, in their order; config design.d2.home.quick_picks is
    // what the import copies there).
    $picks ??= content()->featuredSituations();
    $proposed = app()->isProduction() ? null : 'proposed';

    $audiences = [];
    foreach (['individual', 'business', 'investor'] as $key) {
        $audiences[] = [$key, d2p('hero.tool.audience.'.$key)];
    }
    $audience = in_array($audience, ['individual', 'business', 'investor'], true) ? $audience : 'individual';

    $groups = [];
    $situations = [];
    foreach ($services as $service) {
        $options = [];
        foreach (array_values($service['situations'] ?? []) as $i => $text) {
            $code = $service['code'].'-'.($i + 1);
            $options[] = [$code, $text];
            $situations[$code] = $text;
        }
        if ($options !== []) {
            $groups[] = ['label' => $service['title'] ?? $service['code'], 'options' => $options];
        }
    }
    $situation = is_string($situation) && array_key_exists($situation, $situations) ? $situation : null;
    $selectedService = $situation ? explode('-', $situation)[0] : null;

    // Quick picks: known situation codes only, each once, at most three, in the configured order.
    $quickPicks = [];
    foreach ($picks as $code) {
        if (is_string($code) && array_key_exists($code, $situations) && ! array_key_exists($code, $quickPicks) && count($quickPicks) < 3) {
            $quickPicks[$code] = $situations[$code];
        }
    }
    // A pick shows when the audience lists its service (or has no mapping), like the select's groups (hero/tool.js).
    $allowed = is_array($map[$audience] ?? null) && $map[$audience] !== [] ? array_map('strval', $map[$audience]) : null;
    $pickShown = fn (string $code) => $allowed === null || in_array(explode('-', $code)[0], $allowed, true);
    $picksShown = count(array_filter(array_keys($quickPicks), $pickShown));

    $countLabel = fn (int $n) => trans_choice('proposed.d2.hero.tool.documents', $n, ['count' => $n]);
    $picksLabel = d2p('hero.tool.picks_label');
    $idleLabel = d2p('hero.tool.documents_label');
    $initial = $picksShown > 0 ? $picksLabel : $idleLabel;
    foreach ($services as $service) {
        if ($service['code'] === $selectedService) {
            $initial = $countLabel(count($service['documents'] ?? []));
        }
    }
    $data = ['audiences' => array_map(fn ($list) => array_values((array) $list), $map)];
@endphp
<form {{ $attributes->class(['hero-tool']) }} action="{{ Prefill::url() }}" method="get" aria-labelledby="{{ $idPrefix }}-tool-title" data-hero-tool
    @if ($interactive) x-data="d2HeroTool" @endif>
    <div class="hero-tool__body">
        <h2 class="hero-tool__title" id="{{ $idPrefix }}-tool-title" @proposed>{{ d2p('hero.tool.title') }}</h2>

        <x-d2.ui.segmented class="hero-tool__audience" name="audience" :id="$idPrefix.'-audience'" :options="$audiences" :value="$audience"
            :label="d2p('hero.tool.audience_label')" :full="true" :data-copy-status="$proposed" />

        <div class="hero-tool__row">
            <x-d2.ui.field class="hero-tool__field" :id="$idPrefix.'-situation'" :label="d2p('hero.tool.situation_label')" :data-copy-status="$proposed">
                <x-d2.ui.select name="situation" :placeholder="d2p('hero.tool.situation_placeholder')" :options="$groups" :value="$situation" data-tool-situation />
            </x-d2.ui.field>
            <x-d2.ui.button class="hero-tool__continue" type="submit" variant="ink" :data-copy-status="$proposed" data-tool-continue>{{ d2p('hero.tool.continue') }}</x-d2.ui.button>
        </div>

        <div class="hero-tool__result" data-tool-result>
            <p class="hero-tool__count" id="{{ $idPrefix }}-tool-count" aria-live="polite" data-tool-count
                data-picks-label="{{ $picksLabel }}" data-empty-label="{{ $idleLabel }}" @proposed>{{ $initial }}</p>
            <div class="hero-tool__docs">
                <div @class(['hero-tool__picks', 'is-off' => $selectedService]) data-docs-panel="">
                    @if ($quickPicks !== [])
                        <ul class="hero-tool__pick-list" role="list" aria-labelledby="{{ $idPrefix }}-tool-count">
                            @foreach ($quickPicks as $code => $text)
                                <li class="hero-tool__pick" data-pick-service="{{ explode('-', $code)[0] }}" @if (! $pickShown($code)) hidden @endif>
                                    <x-d2.ui.chip type="submit" name="situation" :value="$code" class="hero-tool__pick-chip" data-quick-pick="{{ $code }}">{{ Bidi::isolate($text) }}</x-d2.ui.chip>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <p class="hero-tool__hint" data-picks-none @if ($picksShown > 0) hidden @endif @proposed>{{ d2p('hero.tool.documents_empty') }}</p>
                </div>
                @foreach ($services as $service)
                    @php $documents = array_values($service['documents'] ?? []); @endphp
                    @if ($documents !== [])
                        <ul @class(['hero-tool__chips', 'is-off' => $service['code'] !== $selectedService]) role="list" data-docs-panel="{{ $service['code'] }}"
                            data-count-label="{{ $countLabel(count($documents)) }}" aria-label="{{ $service['headings']['documents'] ?? $service['title'] }}">
                            @foreach ($documents as $document)
                                <li class="hero-tool__chip"><span class="hero-tool__chip-text">{{ Bidi::isolate($document) }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    <script type="application/json" data-tool-data>{!! json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
</form>
