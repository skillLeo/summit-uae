// Consultation form state machine:
// idle → invalid (focused summary + inline errors) | busy ("Sending request…", idempotency key)
// → success (only after 201/200 + stored reference) | failed (confirmed) | uncertain (timeout or
// lost response; same key so a retry cannot duplicate) | rate_limited. Values are kept on errors.
// All visible text comes from the server-rendered approved copy.
import { focusEl, uuid } from '../lib/util';

const PHONE_PREFIX_ONLY = /^\+?\d{0,4}$/;

function phoneValue(raw) {
    const v = String(raw || '').trim();
    return PHONE_PREFIX_ONLY.test(v.replace(/\s/g, '')) ? '' : v;
}

function validPhone(v) {
    if (!/^(\+|00)[\d\s().-]+$/.test(v)) return false;
    const digits = v.replace(/\D/g, '').replace(/^00/, '');
    return digits.length >= 7 && digits.length <= 15;
}

function validEmail(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
}

export function consultationForm() {
    return {
        state: 'idle',
        reference: '',
        token: '',

        init() {
            const el = this.$el;
            this.endpoint = el.dataset.endpoint;
            this.timeout = Number(el.dataset.timeout) || 20000;
            this.siteKey = el.dataset.turnstile || '';
            this.locale = el.dataset.locale;
            // Start in whatever state the server rendered (no-JS round trip or flashed outcome).
            if (!this.$refs.success.hidden) this.state = 'success';
            else if (!this.$refs.summary.hidden) this.state = 'invalid';
            else if (!this.$refs.failed.hidden) this.state = 'failed';
            else if (!this.$refs.rateLimited.hidden) this.state = 'rate_limited';
            el.addEventListener('focusin', () => this.loadTurnstile(), { once: true });
            window.addEventListener('summit:matter', (e) => this.presetMatter(e.detail?.code));
        },

        field(name) {
            return this.$refs.form.querySelector(`[data-field="${name}"]`);
        },
        errorEl(name) {
            return this.$refs.form.querySelector(`[data-error-for="${name}"]`);
        },

        validate() {
            const errors = {};
            const msg = (name) => this.errorEl(name)?.dataset.message || '';
            const name = this.field('name').value.trim();
            const phone = phoneValue(this.field('phone').value);
            const email = this.field('email').value.trim();
            const summary = this.field('summary').value.trim();
            const date = this.field('deadline_date');
            if (!name) errors.name = msg('name');
            if (!phone && !email) errors.contact = msg('contact');
            if (phone && !validPhone(phone)) errors.phone = msg('phone');
            if (email && !validEmail(email)) errors.email = msg('email');
            if (!summary) errors.summary = msg('summary');
            if (date.validity?.badInput || (date.value && !/^\d{4}-\d{2}-\d{2}$/.test(date.value))) errors.deadline_date = msg('deadline_date');
            if (!this.field('enquiry_consent').checked) errors.enquiry_consent = msg('enquiry_consent');
            return errors;
        },

        clearErrors() {
            this.$refs.form.querySelectorAll('[data-error-for]').forEach((el) => {
                el.hidden = true;
            });
            this.$refs.form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
            this.$refs.summaryList.replaceChildren();
        },

        showErrors(errors) {
            this.clearErrors();
            const order = ['name', 'contact', 'phone', 'email', 'summary', 'deadline_date', 'attachment', 'enquiry_consent'];
            const fields = Object.keys(errors).sort((a, b) => order.indexOf(a) - order.indexOf(b));
            for (const name of fields) {
                const message = Array.isArray(errors[name]) ? errors[name][0] : errors[name];
                const el = this.errorEl(name);
                if (el) {
                    el.querySelector('span').textContent = message;
                    el.hidden = false;
                }
                const inputs = name === 'contact' ? [this.field('phone'), this.field('email')] : [this.field(name)];
                inputs.forEach((i) => i?.setAttribute('aria-invalid', 'true'));
                const target = name === 'contact' ? this.field('phone') : this.field(name);
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = `#${target?.id || ''}`;
                a.textContent = message;
                a.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    target?.focus();
                });
                li.appendChild(a);
                this.$refs.summaryList.appendChild(li);
            }
            this.state = 'invalid';
            this.$nextTick(() => focusEl(this.$refs.summary));
        },

        setBusy(on) {
            const btn = this.$refs.form.querySelector('[data-submit]');
            this.$refs.submitLabel.textContent = on ? btn.dataset.busyLabel : btn.dataset.idleLabel;
        },

        async submit() {
            if (this.state === 'busy') return;
            const errors = this.validate();
            if (Object.keys(errors).length) {
                this.showErrors(errors);
                return;
            }
            this.clearErrors();
            this.state = 'busy';
            this.setBusy(true);
            await this.waitForToken();

            const data = new FormData(this.$refs.form);
            data.set('phone', phoneValue(this.field('phone').value));
            if (this.token) data.set('cf-turnstile-response', this.token);
            const controller = new AbortController();
            const timer = window.setTimeout(() => controller.abort(), this.timeout);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const offline = navigator.onLine === false;

            try {
                const res = await fetch(this.endpoint, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                });
                window.clearTimeout(timer);
                let body = {};
                try {
                    body = await res.json();
                } catch {
                    body = {};
                }
                if ((res.status === 201 || res.status === 200) && body.reference) {
                    this.reference = body.reference;
                    this.state = 'success';
                    this.$nextTick(() => focusEl(this.$refs.success));
                } else if (res.status === 422 && body.errors && Object.keys(body.errors).length) {
                    this.state = 'idle';
                    this.showErrors(body.errors);
                } else if (res.status === 429) {
                    this.state = 'rate_limited';
                } else {
                    this.state = 'failed';
                }
            } catch (err) {
                window.clearTimeout(timer);
                // A timeout or a lost response may still have reached the server: never claim failure.
                this.state = offline ? 'failed' : 'uncertain';
            } finally {
                this.setBusy(false);
                this.resetToken();
            }
        },

        reset() {
            this.$refs.form.reset();
            this.field('phone').value = '+971 ';
            this.$refs.key.value = uuid();
            this.reference = '';
            this.clearErrors();
            this.state = 'idle';
            this.$nextTick(() => this.field('name').focus());
        },

        presetMatter(code) {
            if (!code) return;
            const select = this.$refs.matter;
            if ([...select.options].some((o) => o.value === code)) select.value = code;
        },

        // ---------------------------------------------------------------- Turnstile (lazy)
        loadTurnstile() {
            if (!this.siteKey || window.__summitTurnstile) return;
            window.__summitTurnstile = true;
            window.summitTurnstileReady = () => this.renderTurnstile();
            const s = document.createElement('script');
            s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=summitTurnstileReady';
            s.async = true;
            s.defer = true;
            const nonce = document.querySelector('meta[property="csp-nonce"]')?.nonce;
            if (nonce) s.nonce = nonce;
            document.head.appendChild(s);
        },
        renderTurnstile() {
            if (!window.turnstile || this.widget !== undefined) return;
            this.widget = window.turnstile.render(this.$refs.turnstile, {
                sitekey: this.siteKey,
                action: 'enquiry',
                appearance: 'interaction-only',
                language: this.locale,
                callback: (t) => {
                    this.token = t;
                },
                'expired-callback': () => {
                    this.token = '';
                },
                'error-callback': () => {
                    this.token = '';
                },
            });
        },
        resetToken() {
            this.token = '';
            if (window.turnstile && this.widget !== undefined) window.turnstile.reset(this.widget);
        },
        waitForToken() {
            if (!this.siteKey || this.token) return Promise.resolve();
            this.loadTurnstile();
            return new Promise((resolve) => {
                const started = Date.now();
                const check = () => {
                    if (this.token || Date.now() - started > 4000) resolve();
                    else window.setTimeout(check, 100);
                };
                check();
            });
        },
    };
}
