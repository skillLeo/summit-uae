@props([
    'service',                 // one App\Content\NavigatorData::services() record
    'situation' => null,       // the chosen situation text (server render), or null
    'situationRef' => null,    // its reference, e.g. S05-2
    'prefix' => 'navigator',
    'hidden' => false,         // every service is rendered; only the chosen one is shown
    'ticked' => [],            // styleguide only: document indices drawn ticked
])
{{--
    "Your starting file" for one service, all approved copy (NavigatorData): service title (H3), the chosen
    situation as a quote (the lead role, upright: no serif or italics in the interface) after the approved
    "This service may be relevant if:", the documents checklist with its progress line (proposed, trans_choice),
    the approved first steps (check marks) and mistakes (cross marks) in two columns whose rows line up (CSS
    subgrid) and the guide and service links as text cards. The actions are shared by every file
    (<x-d2.navigator.card>). No Alpine inside: navigator/controller.js wires it, so the card can move into the
    mobile sheet and the files of other services can wait in <template>s.
    Design 2.1: no kicker (D01), no service icon tile, no icons or arrows on the link cards (D03, D04); the check
    and cross marks of the two lists stay (functional, brief §3.6 rule 5).
--}}
@php
    use App\Design\D2\Bidi;
    use Illuminate\Support\HtmlString;

    $code = $service['code'];
    $id = $prefix.'-file-'.$code;
    $docs = array_values($service['documents'] ?? []);
    $steps = array_values($service['first_steps'] ?? []);
    $mistakes = array_values($service['mistakes'] ?? []);
    $total = count($docs);
    $ready = count(array_filter(array_keys($docs), fn ($d) => in_array($d, $ticked, true)));
    $rows = max(count($steps), count($mistakes), 1);
    // Numbers are isolated (<bdi>) so an Arabic sentence keeps its direction around them.
    $num = fn (int $n) => "\u{2068}{$n}\u{2069}";
    $isolated = fn (string $text) => new HtmlString((string) preg_replace('/\x{2068}(.*?)\x{2069}/u', '<bdi>$1</bdi>', e($text)));
    $progress = trans_choice('proposed.d2.navigator.progress', $total, ['ready' => $num($ready), 'total' => $num($total)]);
@endphp
<article {{ $attributes->class(['nav-file__service']) }} id="{{ $id }}" data-file="{{ $code }}" aria-labelledby="{{ $id }}-title" @if ($hidden) hidden @endif>
    <header class="nav-file__head">
        <h3 class="nav-file__title" id="{{ $id }}-title" tabindex="-1">{{ Bidi::isolate($service['title'] ?? '') }}</h3>
        <div class="nav-file__situation" data-nav-situation @unless (filled($situation)) hidden @endunless>
            <p class="nav-file__intro">{{ ui('service.situations_intro') }}</p>
            <p class="nav-file__quote"><q data-nav-quote>{{ Bidi::isolate($situation ?? '') }}</q></p>
        </div>
    </header>

    <section class="nav-file__docs" aria-labelledby="{{ $id }}-docs">
        <div class="nav-file__docs-head">
            <h4 class="nav-file__h" id="{{ $id }}-docs">{{ Bidi::isolate($service['headings']['documents'] ?? '') }}</h4>
            <div class="nav-file__progress" @proposed>
                <x-d2.ui.progress :id="$id.'-progress'" :value="$ready" :max="max($total, 1)" :label="$isolated($progress)" data-nav-progress />
            </div>
        </div>
        <ul class="nav-file__checklist" role="list">
            @foreach ($docs as $d => $doc)
                <li class="nav-file__doc">
                    <x-d2.ui.checkbox :id="$id.'-doc-'.($d + 1)" :label="Bidi::isolate($doc)" data-nav-doc="{{ $d }}" :checked="in_array($d, $ticked, true)" />
                </li>
            @endforeach
        </ul>
        @if (filled($service['documents_note'] ?? null))
            <p class="nav-file__note">{{ Bidi::isolate($service['documents_note']) }}</p>
        @endif
        <div class="nav-file__saved">
            <p class="nav-file__saved-text" @proposed>{{ d2p('navigator.saved_note') }}</p>
            <x-d2.ui.button variant="ghost" size="40" class="nav-file__clear" data-nav-clear-ticks
                :data-copy-status="app()->isProduction() ? null : 'proposed'" :hidden="$ready === 0">{{ d2p('navigator.clear_ticks') }}</x-d2.ui.button>
        </div>
    </section>

    <div class="nav-file__cols" data-rows="{{ min($rows, 8) }}">
        <section class="nav-file__col nav-file__col--steps" aria-labelledby="{{ $id }}-steps">
            <h4 class="nav-file__h" id="{{ $id }}-steps">{{ Bidi::isolate($service['headings']['first_steps'] ?? '') }}</h4>
            <ul class="nav-file__items" role="list">
                @foreach ($steps as $item)
                    <li class="nav-file__item"><x-d2.ui.icon name="check" size="20" class="nav-file__mark" /><span>{{ Bidi::isolate($item) }}</span></li>
                @endforeach
            </ul>
        </section>
        <section class="nav-file__col nav-file__col--avoid" aria-labelledby="{{ $id }}-avoid">
            <h4 class="nav-file__h" id="{{ $id }}-avoid">{{ Bidi::isolate($service['headings']['mistakes'] ?? '') }}</h4>
            <ul class="nav-file__items" role="list">
                @foreach ($mistakes as $item)
                    <li class="nav-file__item"><x-d2.ui.icon name="cross" size="20" class="nav-file__mark" /><span>{{ Bidi::isolate($item) }}</span></li>
                @endforeach
            </ul>
        </section>
    </div>

    <ul class="nav-file__links" role="list">
        @foreach ($service['guides'] ?? [] as $guide)
            <li class="nav-file__link">
                <x-d2.ui.card as="a" :href="$guide['url']" tone="sunken" padding="sm" title-level="0" class="nav-link"
                    :title="Bidi::isolate($guide['label'] ?? '')">
                    <span class="nav-link__meta">{{ ui('cta.read_guide') }}</span>
                </x-d2.ui.card>
            </li>
        @endforeach
        <li class="nav-file__link">
            <x-d2.ui.card as="a" :href="$service['url']" tone="sunken" padding="sm" title-level="0" class="nav-link"
                :title="ui('cta.explore_service')">
                <span class="nav-link__meta">{{ Bidi::isolate($service['title'] ?? '') }}</span>
            </x-d2.ui.card>
        </li>
    </ul>
</article>
