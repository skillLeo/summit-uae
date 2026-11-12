{{--
    Admin keyboard shortcuts (docs/briefs/design-2.1-and-admin-cms.md §10.4, §13), printed once at the end of every panel
    page (AdminPanelProvider BODY_END):
    - "?" opens this sheet (a native modal dialog: Escape closes it and focus returns to where it was);
    - G then D / R / P go to the dashboard, the requests and the pages;
    - Cmd/Ctrl+K opens the search (App\Filament\Livewire\AdminSearch, when present on the page);
    - a page offers its own keys through elements with data-shortcut="<key>": the key presses that button or link
      (request detail: J next, K previous, A assign, S status, N note, E email);
    - in the inbox list J / K move between the rows and Enter opens the focused request.
    Nothing fires while the cursor is in a text field, a select or an editor, or while a modal is open.
--}}
@php
    $keys = fn (string ...$k) => collect($k)->map(fn ($key) => '<kbd>'.e($key).'</kbd>')->implode(' ');
    $mod = request()->userAgent() !== null && str_contains((string) request()->userAgent(), 'Mac') ? '⌘' : 'Ctrl';
    $then = e(__('requests.shortcuts.then'));
    $groups = [
        __('requests.shortcuts.global') => [
            [$keys($mod, 'K'), __('requests.shortcuts.search')],
            [$keys('G').' '.$then.' '.$keys('D'), __('requests.shortcuts.go_dashboard')],
            [$keys('G').' '.$then.' '.$keys('R'), __('requests.shortcuts.go_requests')],
            [$keys('G').' '.$then.' '.$keys('P'), __('requests.shortcuts.go_pages')],
            [$keys('?'), __('requests.shortcuts.help')],
        ],
        __('requests.shortcuts.editing') => [
            [$keys($mod, 'S'), __('requests.shortcuts.save')],
            [$keys($mod, 'Enter'), __('requests.shortcuts.publish')],
        ],
        __('requests.shortcuts.inbox') => [
            [$keys('J'), __('requests.shortcuts.next')],
            [$keys('K'), __('requests.shortcuts.previous')],
            [$keys('Enter'), __('requests.shortcuts.open')],
            [$keys('A'), __('requests.shortcuts.assign')],
            [$keys('S'), __('requests.shortcuts.status')],
            [$keys('N'), __('requests.shortcuts.note')],
            [$keys('E'), __('requests.shortcuts.email')],
        ],
    ];
    $routes = [
        'd' => filament()->getUrl(),
        'r' => \App\Filament\Resources\Enquiries\EnquiryResource::getUrl('index'),
        'p' => \App\Filament\Resources\ContentPages\ContentPageResource::getUrl('index'),
    ];
@endphp
<dialog id="slc-shortcuts" class="slc-shortcuts" aria-labelledby="slc-shortcuts-title">
    <div class="slc-shortcuts__head">
        <h2 id="slc-shortcuts-title">{{ __('requests.shortcuts.title') }}</h2>
        <button type="button" class="slc-shortcuts__close" data-close>{{ __('requests.shortcuts.close') }}</button>
    </div>
    <p class="slc-shortcuts__intro">{{ __('requests.shortcuts.intro') }}</p>
    @foreach ($groups as $title => $rows)
        <section class="slc-shortcuts__group">
            <h3>{{ $title }}</h3>
            <dl>
                @foreach ($rows as [$combo, $label])
                    <div><dt dir="ltr">{!! $combo !!}</dt><dd>{{ $label }}</dd></div>
                @endforeach
            </dl>
        </section>
    @endforeach
</dialog>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
(() => {
    if (window.__slcShortcuts) return;
    window.__slcShortcuts = true;
    const routes = @json($routes);
    const dialog = () => document.getElementById('slc-shortcuts');
    const typing = (el) => el instanceof HTMLElement && (el.isContentEditable || el.closest('input, textarea, select, [contenteditable="true"], .ProseMirror, .tiptap, [role="combobox"], [role="listbox"]'));
    const modalOpen = () => !!document.querySelector('.fi-modal.fi-modal-open, .fi-modal-window:not([hidden]) [aria-modal="true"], dialog[open]');
    let pending = null;
    let timer = 0;

    const rows = () => Array.from(document.querySelectorAll('[data-inbox="list"] .fi-ta-row'));
    const moveRow = (step) => {
        const list = rows();
        if (!list.length) return false;
        const current = list.findIndex((row) => row.contains(document.activeElement));
        const next = list[Math.max(0, Math.min(list.length - 1, current === -1 ? 0 : current + step))];
        const link = next.querySelector('a[href]');
        if (link) { link.focus(); next.scrollIntoView({ block: 'nearest' }); }
        return true;
    };

    document.addEventListener('click', (event) => {
        const close = event.target instanceof Element && event.target.closest('#slc-shortcuts [data-close]');
        if (close) dialog()?.close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || event.isComposing) return;
        const key = event.key;
        if ((event.metaKey || event.ctrlKey) && !event.altKey && key.toLowerCase() === 'k') {
            const trigger = document.querySelector('[data-shortcut-search]');
            if (trigger) { event.preventDefault(); trigger.click(); }
            return;
        }
        if (event.metaKey || event.ctrlKey || event.altKey || typing(event.target)) return;
        if (key === '?') {
            const d = dialog();
            if (d && !d.open && !modalOpen()) { event.preventDefault(); d.showModal(); }
            return;
        }
        if (modalOpen()) return;
        const lower = key.length === 1 ? key.toLowerCase() : key;
        if (pending === 'g') {
            pending = null;
            window.clearTimeout(timer);
            if (routes[lower]) { event.preventDefault(); window.location.assign(routes[lower]); }
            return;
        }
        if (lower === 'g') {
            pending = 'g';
            timer = window.setTimeout(() => { pending = null; }, 1200);
            return;
        }
        if ((lower === 'j' || lower === 'k') && document.querySelector('[data-inbox="list"]')) {
            if (moveRow(lower === 'j' ? 1 : -1)) event.preventDefault();
            return;
        }
        if (key.length !== 1) return;
        const target = Array.from(document.querySelectorAll(`[data-shortcut="${CSS.escape(lower)}"]`))
            .find((el) => !el.closest('[hidden], [inert]') && !el.hasAttribute('disabled') && el.getAttribute('aria-disabled') !== 'true' && el.offsetParent !== null);
        if (target) {
            event.preventDefault();
            const clickable = target.matches('a, button') ? target : target.querySelector('a, button') || target;
            clickable.click();
        }
    });
})();
</script>
