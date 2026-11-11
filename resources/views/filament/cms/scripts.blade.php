{{--
    Admin CMS helpers (docs/briefs/design-2.1-and-admin-cms.md §8.4, §9.3), printed once at the end of every panel page:
    the visible character counter of every field with a hard limit (data-cms-limit, App\Filament\Cms\Forms\Translatable):
    "120 / 184 characters" under the field, in the admin's language. Delegated, so fields Livewire re-renders keep it.
--}}
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
(() => {
    const template = @json(__('cms.field.limit', ['count' => '__COUNT__', 'max' => '__MAX__']));
    const counter = (input) => {
        const max = Number(input.dataset.cmsLimit || 0);
        if (!max) return;
        let out = input.parentElement.querySelector(':scope > .cms-counter');
        if (!out) {
            out = document.createElement('div');
            out.className = 'cms-counter';
            out.setAttribute('aria-live', 'polite');
            input.parentElement.appendChild(out);
        }
        const count = [...(input.value || '')].length;
        const text = template.replace('__COUNT__', String(count)).replace('__MAX__', String(max));
        if (out.textContent !== text) out.textContent = text;
        const near = count >= max * 0.9;
        if (out.classList.contains('cms-counter--near') !== near) out.classList.toggle('cms-counter--near', near);
    };
    let queued = false;
    const scan = () => {
        queued = false;
        document.querySelectorAll('[data-cms-limit]').forEach(counter);
    };
    const queue = () => { if (!queued) { queued = true; requestAnimationFrame(scan); } };
    document.addEventListener('input', (e) => { if (e.target instanceof HTMLElement && e.target.matches('[data-cms-limit]')) counter(e.target); });
    document.addEventListener('DOMContentLoaded', queue);
    // new fields (Livewire re-renders, repeater items): only fields without a counter are touched, so this settles
    new MutationObserver(queue).observe(document.documentElement, { childList: true, subtree: true });
})();
</script>
