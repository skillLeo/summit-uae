@props([
    'services' => [],          // App\Content\NavigatorData::services() ($navServices)
    'selected' => null,        // situation reference checked on the server (?navigator=S05-2)
    'prefix' => 'navigator',
    'name' => 'navigator',
    'only' => null,            // styleguide only: list just these service codes
    'states' => [],            // styleguide only: [ref => 'hover' | 'focus']
    'emptyVisible' => false,   // styleguide only: show the "no situation matches" panel
    'open' => null,            // service codes whose group starts open (default: the group of the selected situation)
])
{{--
    The 44 approved situations as ONE radio group, grouped by service under sticky labels (the approved service
    titles in the minor heading role, 16/600, white on the dark band; no mono, no capitals, no gold and no
    decorative numbers: Design 2.1 D01, brief §3.6 rules 1 and 4).
    Works without JavaScript inside the GET form (?navigator=S05-2); navigator/controller.js filters it.

    Phones and tablets (final pass, home-mobile): below 1280 px with JavaScript each group label is also a
    disclosure row (.nav-group__toggle, aria-expanded / aria-controls, the whole row is the target) and the
    group's situations show only while it is open (.is-open; d2Navigator toggles it, the controller opens the
    group of a chosen situation, a search or a chip shows every match). Server side the group of the selected
    situation starts open, so the first paint is the final layout. Without JavaScript and from 1280 px every
    situation is listed and the toggles are not drawn (navigator.css).
--}}
@php
    use App\Design\D2\Bidi;

    $cs = app()->isProduction() ? null : 'proposed';
    $selectedCode = is_string($selected) && str_contains($selected, '-') ? explode('-', $selected)[0] : null;
    $openCodes = is_array($open) ? $open : array_filter([$selectedCode]);
@endphp
<fieldset {{ $attributes->class(['nav-list']) }} data-nav-list>
    <legend class="sr-only" @proposed>{{ d2p('navigator.list_label') }}</legend>
    @foreach ($services as $s)
        @continue(is_array($only) && ! in_array($s['code'], $only, true))
        @php
            $groupId = $prefix.'-group-'.$s['code'];
            $isOpen = in_array($s['code'], $openCodes, true);
        @endphp
        <div @class(['nav-group', 'is-open' => $isOpen]) role="group" aria-labelledby="{{ $groupId }}" data-nav-group="{{ $s['code'] }}">
            <p class="nav-group__label" id="{{ $groupId }}">
                <span class="nav-group__title" id="{{ $groupId }}-title" data-nav-group-title>{{ Bidi::isolate($s['title'] ?? '') }}</span>
                {{-- Named by the service title; the chevron is its only drawing. --}}
                <button type="button" class="nav-group__toggle" aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                    aria-controls="{{ $groupId }}-items" aria-labelledby="{{ $groupId }}-title" data-nav-toggle="{{ $s['code'] }}">
                    <x-d2.ui.icon name="chevron-down" size="20" class="nav-group__chevron" />
                </button>
            </p>
            <div class="nav-group__items" id="{{ $groupId }}-items">
                @foreach ($s['situations'] ?? [] as $j => $situation)
                    @php $ref = $s['code'].'-'.($j + 1); @endphp
                    <x-d2.navigator.item :ref="$ref" :situation="$situation" :checked="$selected === $ref"
                        :prefix="$prefix" :name="$name" :state="$states[$ref] ?? null" />
                @endforeach
            </div>
        </div>
    @endforeach
    <div class="nav-empty" data-nav-empty @unless ($emptyVisible) hidden @endunless>
        <p class="nav-empty__text" @proposed>{{ d2p('navigator.no_results') }}</p>
        <x-d2.ui.button variant="night" size="40" data-nav-clear :data-copy-status="$cs">{{ d2p('navigator.clear_filter') }}</x-d2.ui.button>
    </div>
</fieldset>
