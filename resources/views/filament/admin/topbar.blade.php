{{--
    Top bar additions (docs/briefs/design-2.1-and-admin-cms.md §9.1): Search (Cmd/Ctrl+K, App\Filament\Livewire\AdminSearch)
    and "View site" with the design and the language. Filament's own bell and user menu follow.
--}}
@php
    $locales = ['en' => 'English', 'ar' => 'العربية', 'ru' => 'Русский'];
    $mod = str_contains((string) request()->userAgent(), 'Mac') ? '⌘K' : 'Ctrl+K';
@endphp
<div class="slc-topbar">
    <button type="button" class="slc-topbar__search" data-shortcut-search aria-haspopup="dialog" aria-controls="slc-search"
            title="{{ __('admin_shell.search.button') }} ({{ $mod }})">
        <x-filament::icon icon="heroicon-o-magnifying-glass" class="slc-topbar__icon" />
        <span>{{ __('admin_shell.search.button') }}</span>
        <kbd class="slc-kbd" dir="ltr">{{ $mod }}</kbd>
    </button>
    <x-filament::dropdown placement="bottom-end" teleport>
        <x-slot name="trigger">
            <button type="button" class="slc-topbar__site">
                <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="slc-topbar__icon" />
                <span>{{ __('admin_shell.view_site.button') }}</span>
            </button>
        </x-slot>
        @foreach ([2, 1] as $design)
            <x-filament::dropdown.header>{{ __('admin_shell.view_site.design', ['design' => $design]) }}</x-filament::dropdown.header>
            <x-filament::dropdown.list>
                @foreach ($locales as $code => $name)
                    <x-filament::dropdown.list.item tag="a" :href="url('/'.$code.'/').'?design='.$design" target="_blank" rel="noopener" :attributes="new \Illuminate\View\ComponentAttributeBag(['lang' => $code, 'hreflang' => $code])">
                        {{ $name }}
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        @endforeach
    </x-filament::dropdown>
    @livewire(\App\Filament\Livewire\AdminSearch::class)
</div>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
(() => {
    if (window.__slcSearch) return;
    window.__slcSearch = true;
    const KEY = 'slc-admin-recent';
    const dialog = () => document.getElementById('slc-search');
    let opener = null;
    const recent = () => { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch { return []; } };
    const remember = (item) => {
        try {
            const list = [item, ...recent().filter((r) => r.url !== item.url)].slice(0, 8);
            localStorage.setItem(KEY, JSON.stringify(list));
        } catch { /* private mode: no recent items */ }
    };
    const renderRecent = () => {
        const box = document.querySelector('#slc-search [data-search-recent]');
        if (!box) return;
        const list = recent();
        box.replaceChildren();
        if (!list.length) {
            const p = document.createElement('p');
            p.className = 'slc-search__empty';
            p.textContent = box.dataset.empty;
            box.append(p);
            return;
        }
        const h = document.createElement('p');
        h.className = 'slc-search__group';
        h.textContent = box.dataset.heading;
        box.append(h);
        for (const item of list) {
            const a = document.createElement('a');
            a.href = item.url;
            a.className = 'slc-search__item';
            a.setAttribute('role', 'option');
            a.dataset.searchItem = '';
            a.dataset.title = item.title;
            a.dataset.meta = item.meta || '';
            const t = document.createElement('span'); t.className = 'slc-search__title'; t.textContent = item.title;
            const m = document.createElement('span'); m.className = 'slc-search__meta'; m.textContent = item.meta || '';
            a.append(t, m);
            box.append(a);
        }
    };
    const items = () => Array.from(document.querySelectorAll('#slc-search [data-search-item]'));
    const open = (trigger) => {
        const d = dialog();
        if (!d || d.open) return;
        opener = trigger || document.activeElement;
        renderRecent();
        d.showModal();
        d.querySelector('[data-search-input]')?.focus();
    };
    document.addEventListener('click', (e) => {
        const t = e.target instanceof Element ? e.target : null;
        if (!t) return;
        const trigger = t.closest('[data-shortcut-search]');
        if (trigger) { e.preventDefault(); open(trigger); return; }
        if (t.closest('[data-search-close]')) { dialog()?.close(); return; }
        const item = t.closest('#slc-search [data-search-item]');
        if (item) remember({ url: item.href, title: item.dataset.title, meta: item.dataset.meta });
        if (t === dialog()) dialog().close();
    });
    document.addEventListener('close', (e) => {
        if (e.target === dialog() && opener instanceof HTMLElement) opener.focus();
    }, true);
    document.addEventListener('keydown', (e) => {
        const d = dialog();
        if (!d?.open) return;
        const list = items();
        const at = list.indexOf(document.activeElement);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!list.length) return;
            const next = e.key === 'ArrowDown' ? (at + 1) % list.length : (at <= 0 ? list.length - 1 : at - 1);
            list[next].focus();
        } else if (e.key === 'Enter' && document.activeElement?.matches('[data-search-input]') && list.length) {
            e.preventDefault();
            remember({ url: list[0].href, title: list[0].dataset.title, meta: list[0].dataset.meta });
            window.location.assign(list[0].href);
        }
    });
    document.addEventListener('livewire:navigated', renderRecent);
    document.addEventListener('DOMContentLoaded', renderRecent);
    window.addEventListener('slc-search-rendered', renderRecent);
})();
</script>
