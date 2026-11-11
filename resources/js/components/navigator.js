// Matter Navigator: approved situation → starting file (documents checklist, first steps,
// mistakes, related guide) → consultation form. Local only: zero network calls. Document ticks
// persist in localStorage; a situation can be shared as #navigator=S05-2.
import { focusEl, isMobile, normalize } from '../lib/util';

const REF = /^#navigator=(S(?:0[1-9]|1[01])-[1-9])$/;

export function matterNavigator() {
    return {
        ready: false,
        current: null,
        query: '',
        noResults: false,
        audience: '',
        step: 'pick',
        sheetOpen: false,
        isSheet: false,
        announcement: '',
        printDate: '',
        progressLabel: '',
        labels: {},
        ticks: {},

        init() {
            const data = JSON.parse(this.$el.querySelector('[data-navigator-json]').textContent);
            this.data = data;
            this.labels = data.labels;
            this.index = new Map();
            this.options = [...this.$el.querySelectorAll('[data-option]')].map((el) => ({
                el,
                ref: el.dataset.ref,
                group: el.closest('[data-group]'),
                text: normalize(el.textContent + ' ' + (el.closest('[data-group]')?.querySelector('legend')?.textContent || '')),
            }));
            data.services.forEach((s, i) => {
                s.index = String(i + 1).padStart(2, '0');
                s.situations.forEach((t, j) => this.index.set(`${s.code}-${j + 1}`, { service: s, situation: t }));
            });
            this.loadTicks();
            this.$refs.ssr?.remove();
            this.ready = true;

            const media = window.matchMedia('(max-width: 767.98px)');
            const sync = () => {
                this.isSheet = media.matches;
                if (!media.matches) this.closeSheet(false);
            };
            sync();
            media.addEventListener('change', sync);

            const fromHash = (location.hash.match(REF) || [])[1];
            const checked = this.$el.querySelector('input[name="navigator"]:checked');
            if (fromHash) {
                this.select(fromHash, { restored: true });
                this.$nextTick(() => document.getElementById('navigator')?.scrollIntoView({ block: 'start' }));
            } else if (checked) {
                this.select(checked.value, { restored: true });
            }
            window.addEventListener('hashchange', () => {
                const ref = (location.hash.match(REF) || [])[1];
                if (ref && ref !== this.current?.ref) this.select(ref, { restored: true });
            });
            this.updateProgress();
            this.$watch('step', () => this.updateProgress());
        },

        // ---------------------------------------------------------------- selection
        noop() {},
        choose(e) {
            this.select(e.target.value, { user: true });
        },
        select(ref, opts = {}) {
            const hit = this.index.get(ref);
            if (!hit) return;
            this.current = { ref, service: hit.service, situation: hit.situation };
            const radio = this.$el.querySelector(`input[name="navigator"][value="${ref}"]`);
            if (radio) radio.checked = true;
            try {
                history.replaceState(history.state, '', `${location.pathname}${location.search}#navigator=${ref}`);
            } catch {
                // ignore (sandboxed)
            }
            this.step = 'file';
            const date = new Intl.DateTimeFormat(this.data.dateLocale, { dateStyle: 'long', timeZone: 'Asia/Dubai' }).format(new Date());
            this.printDate = this.labels.printDate.replace(':date', date);
            this.$nextTick(() => {
                const card = this.$refs.card;
                if (card) {
                    card.classList.remove('is-entering');
                    void card.offsetWidth;
                    card.classList.add('is-entering');
                }
                if (opts.user || (opts.restored && location.hash.startsWith('#navigator='))) {
                    const heading = document.getElementById('navigator-file-title');
                    if (heading && (!isMobile() || this.sheetOpen)) focusEl(heading, !isMobile());
                }
            });
        },
        backToPick() {
            this.step = 'pick';
            this.$nextTick(() => this.$el.querySelector('input[name="navigator"]:checked, #navigator-filter')?.focus());
        },

        // ---------------------------------------------------------------- filter & audience
        applyFilter() {
            const q = normalize(this.query);
            const allowed = this.audience && this.data.audiences ? new Set(this.data.audiences[this.audience] || []) : null;
            let visible = 0;
            const groups = new Map();
            for (const o of this.options) {
                const code = o.ref.split('-')[0];
                const show = (!q || o.text.includes(q)) && (!allowed || allowed.has(code));
                o.el.hidden = !show;
                if (show) visible++;
                groups.set(o.group, (groups.get(o.group) || false) || show);
            }
            groups.forEach((show, group) => {
                group.hidden = !show;
            });
            this.noResults = visible === 0;
        },
        clearFilter() {
            this.query = '';
            this.applyFilter();
            this.$el.querySelector('#navigator-filter')?.focus();
        },
        setAudience(e) {
            this.audience = e.currentTarget.dataset.audience || '';
            this.applyFilter();
        },

        // ---------------------------------------------------------------- checklist (localStorage)
        loadTicks() {
            try {
                this.ticks = JSON.parse(window.localStorage.getItem(this.data.storageKey) || '{}') || {};
            } catch {
                this.ticks = {};
            }
        },
        saveTicks() {
            try {
                window.localStorage.setItem(this.data.storageKey, JSON.stringify(this.ticks));
            } catch {
                // storage unavailable: ticks last for this visit only
            }
        },
        isTicked(i) {
            return !!(this.current && this.ticks[this.current.service.code]?.[i]);
        },
        tick(e) {
            const code = this.current.service.code;
            const i = Number(e.target.dataset.doc);
            const list = this.ticks[code] || [];
            list[i] = e.target.checked;
            this.ticks = { ...this.ticks, [code]: list };
            this.saveTicks();
        },
        clearTicks() {
            if (!this.current) return;
            const next = { ...this.ticks };
            delete next[this.current.service.code];
            this.ticks = next;
            this.saveTicks();
            this.$el.querySelectorAll('.doc-list input[type="checkbox"]').forEach((c) => {
                c.checked = false;
            });
        },

        // ---------------------------------------------------------------- actions
        print() {
            const html = document.documentElement;
            html.classList.add('print-file');
            const done = () => {
                html.classList.remove('print-file');
                window.removeEventListener('afterprint', done);
            };
            window.addEventListener('afterprint', done);
            window.print();
            window.setTimeout(done, 1500);
        },
        async copyLink() {
            const url = `${location.origin}${location.pathname}#navigator=${this.current.ref}`;
            try {
                await window.navigator.clipboard.writeText(url);
            } catch {
                const input = document.createElement('input');
                input.value = url;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                input.remove();
            }
            this.announcement = '';
            this.$nextTick(() => {
                this.announcement = this.labels.copied;
            });
        },
        continueToForm() {
            const code = this.current?.service.code;
            this.closeSheet(false);
            window.dispatchEvent(new CustomEvent('summit:matter', { detail: { code } }));
            const target = document.getElementById('consultation-form');
            target?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
            window.setTimeout(() => document.getElementById('cf-name')?.focus({ preventScroll: true }), 420);
        },

        // ---------------------------------------------------------------- mobile sheet
        openSheet() {
            this.sheetOpen = true;
            this.step = this.current ? 'file' : 'pick';
            document.documentElement.classList.add('sheet-open');
            for (const el of document.body.children) {
                if (!el.contains(this.$el) && !el.matches('script, [data-announcer]')) el.inert = true;
            }
            this.$nextTick(() => {
                const first = this.step === 'file' ? document.getElementById('navigator-file-title') : this.$el.querySelector('#navigator-filter');
                first?.focus({ preventScroll: true });
            });
        },
        closeSheet(restore = true) {
            if (!this.sheetOpen) return;
            this.sheetOpen = false;
            document.documentElement.classList.remove('sheet-open');
            for (const el of document.body.children) el.inert = false;
            if (restore) this.$nextTick(() => this.$el.querySelector('.navigator__launch button')?.focus({ preventScroll: true }));
        },
        updateProgress() {
            const total = this.data.total;
            const current = this.step === 'file' ? total : total - 1;
            this.progressLabel = this.labels.progress.replace(':current', current);
        },
    };
}
