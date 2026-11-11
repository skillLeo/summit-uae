@props([
    'tabs' => [],             // [[id, label], …]: id is the id of the matching tab-panel
    'label' => null,          // accessible name of the tab list (required for a meaningful list)
    'active' => null,         // initially active panel id (default: the first; a #hash inside a panel wins)
    'syncHash' => false,      // write the chosen panel id to the URL (#cat-03) when the visitor switches
])
{{--
    <x-d2.ui.tabs :tabs="[['cat-01', $name1], ['cat-02', $name2]]" :label="…">
        <x-d2.ui.tab-panel id="cat-01">…</x-d2.ui.tab-panel>
        <x-d2.ui.tab-panel id="cat-02">…</x-d2.ui.tab-panel>
    </x-d2.ui.tabs>

    Without JavaScript the tabs are in-page anchor links and every panel is visible. Alpine d2Tabs
    (resources/js/d2/components/tabs.js) turns them into role=tablist/tab/tabpanel with a roving tabindex,
    arrow keys (reversed in RTL), Home/End, automatic activation and the sliding indicator (ui spring);
    the list scrolls horizontally with fade edges when it overflows. Root event: `d2:tab` {id}.
    Styleguide-only modifiers on a tab: .is-hover, .is-focus.
--}}
@php
    $items = [];
    foreach ((array) $tabs as $tab) {
        $id = is_array($tab) ? (string) ($tab[0] ?? $tab['id'] ?? '') : '';
        $text = is_array($tab) ? (string) ($tab[1] ?? $tab['label'] ?? '') : '';
        if ($id !== '' && $text !== '') {
            $items[] = [$id, $text];
        }
    }
    $ids = array_column($items, 0);
    $active = in_array($active, $ids, true) ? $active : ($ids[0] ?? '');
@endphp
<div {{ $attributes->class(['tabs']) }} x-data="d2Tabs" data-active="{{ $active }}" @if ($syncHash) data-sync-hash @endif>
    <div class="tabs__bar">
        <nav class="tabs__list" data-tabs-list @if ($label) aria-label="{{ $label }}" @endif
             x-on:click="onClick" x-on:keydown="onKeydown" x-on:scroll.passive="onScroll">
            @foreach ($items as [$id, $text])
                {{-- data-tab-initial (search-hours-docs, final pass): the initially active tab, so a container can draw its tabbed first paint before d2Tabs runs (sections/faq.css) --}}
                <a class="tabs__tab" href="#{{ $id }}" id="{{ $id }}-tab" data-tab="{{ $id }}"{!! $id === $active ? ' data-tab-initial' : '' !!}><span class="tabs__label">{{ $text }}</span></a>
            @endforeach
        </nav>
        <button class="tabs__scroll tabs__scroll--start" type="button" tabindex="-1" aria-hidden="true" data-tabs-scroll="start" hidden
                x-on:mousedown.prevent="noop" x-on:click="onScrollButton"><x-d2.ui.icon name="chevron-start" size="20" /></button>
        <button class="tabs__scroll tabs__scroll--end" type="button" tabindex="-1" aria-hidden="true" data-tabs-scroll="end" hidden
                x-on:mousedown.prevent="noop" x-on:click="onScrollButton"><x-d2.ui.icon name="chevron" size="20" /></button>
    </div>
    <div class="tabs__panels">
        {{ $slot }}
    </div>
</div>
