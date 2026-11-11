/**
 * Consultation wizard controller (<x-d2.wizard.form>, Alpine d2Wizard; spec §12 C14). It turns the plain,
 * server-rendered enquiry form into five steps and talks to the existing endpoint exactly as
 * docs/enquiries.md describes. Every string it shows comes from the page (the page's own approved form copy
 * rendered by Blade: the homepage file's on the home, the Contact file's on P07; proposed wizard copy in the
 * data block); it never writes HTML.
 *
 *   steps        the Contact DOCX order (D38): 1 You (name, phone, e-mail, language), 2 Your matter (type,
 *                summary), 3 Deadline, 4 Documents, 5 Review (notices, consent, submit). One progress
 *                indicator (D37: the step list, "Step 1 of 5: You" on phones). Next checks the step (focus moves
 *                to the first error, the error summary is announced); Back and the finished steps of the list
 *                never check and never lose data (every step stays in the DOM).
 *   documents    the documents usually needed for the chosen service (Navigator data), ticked per service
 *   review       the answers with Change buttons, and the message exactly as it will be sent: the summary
 *                plus visible context lines and the ticked documents (the backend has no fields for them)
 *   prefill      d2:prefill events, ?service=… (Prefill::fromRequest()) and takeSummary(); validated codes
 *   documents    + the one optional attachment (<x-d2.upload.dropzone>, only while attachments are enabled):
 *                sent as `attachment` in the FormData, named on the review, part of the request fingerprint
 *   autosave     pending client approval (D39): only while the page renders "Keep a draft on this device"
 *                (data.draft). localStorage summit:draft:{locale} (never consent, token, key, honeypot or the file) for the
 *                page's lifetime (data.draftDays), throttled "Draft saved on this device" toast, a restore
 *                prompt (decided at start-up by lazy.js), "Keep a draft on this device" (unticked: deleted and
 *                off for this visit), cleared after a received request
 *   sending      aria-busy form, inert steps, the submit button loading with the approved busy label
 *   outcomes     received · invalid (422 errors back on their step and field, in the page's own wording) · failed · rate_limited ·
 *                uncertain (timeout / network / unknown: the key is kept, a changed request gets a new one) ·
 *                security check incomplete or unavailable. Focus moves to the outcome every time.
 *
 * A lazy chunk: wizard/lazy.js imports it when the wizard is about to be used and replays what the visitor did
 * meanwhile (mountWizard(root, { prompt, typed })). The markup already shows the stepped layout before that.
 */
import { announce } from '../lib/announce.js';
import { takeSummary } from '../lib/prefill.js';
import { reducedMotion, scrollBehavior } from '../lib/prefs.js';
import * as storage from '../lib/storage.js';
import { toast } from '../lib/toast.js';
import { attachmentOf } from '../components/upload-checks.js';
import { setBidiText } from './bidi.js';
import { appendText, composeMessage, contextLines, documentsBlock } from './compose.js';
import { sanitisePrefill, situationText, withoutPrefillParams } from './context.js';
import { createDraftStore, createThrottle, hasContent } from './draft.js';
import { ttlFromDays } from './draft-meta.js';
import { firstErrorStep, mapErrors } from './errors.js';
import { classify, newKey, resetsToken } from './outcome.js';
import { checkStep, firstInvalidStep, isPrefixOnly, phoneValue } from './validate.js';

const STEPS = 5;
const SAVE_DELAY = 700;
const TOAST_EVERY = 30000;
const TOKEN_WAIT = 12000;
const DATE_LOCALES = { en: 'en-GB', ar: 'ar-AE', ru: 'ru-RU' };

/** Wizard field → the id suffix of its control (and of its error element, "{id}-error"). */
const FIELD_ID = {
    name: 'name',
    phone: 'phone',
    email: 'email',
    contact: 'contact',
    summary: 'summary',
    message: 'message',
    deadline_date: 'deadline-date',
    deadline_details: 'deadline-details',
    attachment: 'attachment',
    enquiry_consent: 'consent',
};

/** The browser check that clears an error while the visitor corrects the field (validate.js FIELD_STEP). */
const RECHECK = { name: 1, phone: 1, email: 1, contact: 1, summary: 2, deadline_date: 3, message: 5, enquiry_consent: 5 };

/** The steps whose fields the browser checks before sending (4, Documents, never blocks). */
const CHECKED_STEPS = [1, 2, 3, 5];

const NOTICES = ['errors', 'failed', 'rate_limited', 'uncertain', 'turnstile_incomplete', 'turnstile_unavailable'];

function readJson(el) {
    if (!el) return null;
    try {
        return JSON.parse(el.textContent || 'null');
    } catch {
        return null;
    }
}

function addToken(el, attr, token) {
    if (!el) return;
    const list = (el.getAttribute(attr) || '').split(/\s+/).filter(Boolean);
    if (!list.includes(token)) list.push(token);
    el.setAttribute(attr, list.join(' '));
}

function removeToken(el, attr, token) {
    if (!el) return;
    const list = (el.getAttribute(attr) || '').split(/\s+/).filter((t) => t && t !== token);
    if (list.length) el.setAttribute(attr, list.join(' '));
    else el.removeAttribute(attr);
}

function focusEl(el, preventScroll = false) {
    if (!el) return;
    try {
        el.focus({ preventScroll });
    } catch {
        el.focus();
    }
}

class Wizard {
    constructor(root, data, services, options = {}) {
        this.root = root;
        this.data = data;
        this.services = services;
        this.options = options && typeof options === 'object' ? options : {};
        this.text = data.text || {};
        this.labels = data.labels || {};
        this.locale = data.locale || 'en';
        this.p = data.prefix || 'wz';
        this.specimen = data.specimen && typeof data.specimen === 'object' ? data.specimen : null;
        this.known = {
            services,
            audiences: this.labels.audience || {},
            consultants: this.labels.consultant || {},
            tools: this.labels.tool || {},
        };

        this.step = 1;
        this.status = 'idle';
        this.context = { consultant: '', tool: '' };
        this.ticks = {};
        this.message = { edited: false, base: '' };
        this.errors = new Map();
        this.snapshot = null;
        this.pendingDraft = null;
        this.saveTimer = 0;
        // The local draft is pending client approval (D39): off when the page does not offer it.
        this.draftEnabled = data.draft !== false;
        this.autosave = this.draftEnabled;
        this.listeners = [];

        const q = (selector) => root.querySelector(selector);
        this.form = q('[data-wizard-form]');
        this.stepsEl = q('[data-wizard-steps]');
        this.panels = Array.from(root.querySelectorAll('[data-step-panel]'));
        this.back = q('[data-wizard-back]');
        this.nextBtn = q('[data-wizard-next]');
        this.submitBtn = q('[data-wizard-submit]');
        this.submitLabel = this.submitBtn?.querySelector('.btn__label')?.textContent ?? '';
        this.success = q('[data-wizard-success]');
        this.reference = q('[data-wizard-reference]');
        this.restoreEl = q('[data-wizard-restore]');
        this.contextEl = q('[data-wizard-context]');
        this.contextList = q('[data-wizard-context-list]');
        this.staleEl = q('[data-wizard-stale]');
        this.errorList = q('[data-wizard-error-list]');
        this.situationField = q('[data-wizard-situation-field]');
        this.docsField = q('[data-wizard-docs]');
        this.docsNote = q('[data-wizard-docs-note]');
        this.docRows = Array.from(root.querySelectorAll('[data-wizard-doc-row]'));
        this.audienceInputs = Array.from(root.querySelectorAll('[data-wizard-audience]'));
        this.notices = Object.fromEntries(NOTICES.map((kind) => [kind, q(`[data-wizard-notice="${kind}"]`)]));
        this.emptyDocs = { none: q('[data-wizard-docs-empty="none"]'), generic: q('[data-wizard-docs-empty="generic"]') };

        const field = (suffix) => document.getElementById(`${this.p}-${suffix}`);
        this.f = {
            name: field('name'),
            matter: field('matter'),
            situation: field('situation'),
            summary: field('summary'),
            deadlineDate: field('deadline-date'),
            deadlineDetails: field('deadline-details'),
            phone: field('phone'),
            email: field('email'),
            language: field('language'),
            message: field('message'),
            consent: field('consent'),
            attachment: field('attachment'),
            autosave: root.querySelector('[data-wizard-autosave]'),
            key: this.form?.querySelector('[data-wizard-key]'),
            website: this.form?.querySelector('input[name="website"]'),
            csrf: this.form?.querySelector('input[name="_token"]'),
        };

        // The approved message of every field, as rendered (a 422 may replace the text; this restores it).
        this.defaults = {};
        for (const [name, suffix] of Object.entries(FIELD_ID)) {
            const text = document.getElementById(`${this.p}-${suffix}-error`)?.querySelector('.field__error-text')?.textContent;
            if (text) this.defaults[name] = text.trim();
        }

        this.drafts = createDraftStore(data.draftKey || `summit:draft:${this.locale}`, storage, undefined, ttlFromDays(data.draftDays));
        this.shouldToast = createThrottle(TOAST_EVERY);
        // Cloudflare Turnstile: its helper (and the third-party script) load only when the review step needs it.
        this.turnstileContainer = q('[data-wizard-turnstile]');
        this.turnstile = null;
        this.turnstileLoading = null;
    }

    // ------------------------------------------------------------------ setup

    mount() {
        if (!this.form || !this.panels.length || !this.f.matter || !this.f.summary) return false;
        this.bind();
        // The pre-filled country code (Blade trims attribute values): keep the space so typing reads naturally.
        if (this.f.phone && this.f.phone.value === '+971') this.f.phone.value = '+971 ';
        // "Keep a draft on this device" unticked before the controller loaded (or not offered): nothing is kept.
        this.autosave = this.draftEnabled && (this.f.autosave ? this.f.autosave.checked : true);
        if (!this.autosave && !this.specimen) this.drafts.clear();
        const initial = this.data.initial || {};
        let start = 1;
        let after = null;

        if (this.specimen) {
            start = this.applySpecimenValues();
        } else if (initial.status === 'received') {
            this.status = 'received';
            this.drafts.clear();
            after = () => this.showSuccess(initial.reference || this.reference?.textContent || '', { focus: true });
        } else {
            this.takeServerContext();
            const list = mapErrors(initial.errors || {});
            if (list.length) {
                start = firstErrorStep(list) ?? 1;
                after = () => this.showErrors(list, { focus: 'summary' });
            } else if (initial.status === 'failed' || initial.status === 'rate_limited') {
                start = STEPS;
                after = () => this.showNotice(initial.status, { focus: true });
            }
            if (!initial.status && !initial.old && this.draftEnabled) this.offerDraft(this.options.prompt);
        }

        this.root.classList.add('is-stepped');
        this.go(start, { focus: false, animate: false });
        this.renderContext();
        if (after) after();
        if (this.specimen) this.applySpecimenState();
        // Answers typed before the controller loaded are kept like any other edit.
        else if (this.options.typed) this.scheduleSave();
        return true;
    }

    bind() {
        const on = (target, type, handler, options) => {
            target.addEventListener(type, handler, options);
            this.listeners.push(() => target.removeEventListener(type, handler, options));
        };
        on(this.form, 'submit', (event) => this.onSubmit(event));
        // Tabbing into the phone field selects its text, so the first digit would replace the pre-filled
        // country code. While the field holds only that prefix, a typed digit 1–9 goes after it instead
        // ("+" or "0" still replaces it: the visitor is typing their own international prefix).
        if (this.f.phone) {
            on(this.f.phone, 'keydown', (event) => {
                const input = this.f.phone;
                if (event.ctrlKey || event.metaKey || event.altKey || !/^[1-9]$/.test(event.key)) return;
                const end = input.value.length;
                if (end && isPrefixOnly(input.value) && input.selectionStart === 0 && input.selectionEnd === end) {
                    input.setSelectionRange(end, end);
                }
            });
        }
        on(this.root, 'click', (event) => this.onClick(event));
        on(this.root, 'input', (event) => this.onEdit(event));
        on(this.root, 'change', (event) => this.onEdit(event));
        // <x-d2.upload.dropzone> reports every accepted, dropped or removed file (after its own checks).
        on(this.root, 'd2-upload-change', () => this.onAttachmentChange());
        if (this.specimen) return;
        on(window, 'd2:prefill', (event) => this.onPrefill(event.detail));
        on(window, 'pagehide', () => this.flushSave());
        on(document, 'visibilitychange', () => {
            if (document.hidden) this.flushSave();
        });
    }

    destroy() {
        this.listeners.forEach((off) => off());
        this.listeners = [];
        window.clearTimeout(this.saveTimer);
        this.turnstile?.destroy();
        this.turnstile = null;
    }

    /** The no-JS prefill: the server wrote the context into the summary; with JS it belongs to the review. */
    takeServerContext() {
        const summary = this.f.summary;
        const context = summary.dataset.context || '';
        if (context && summary.value.trim() === context.trim()) summary.value = '';
        const prefill = sanitisePrefill(this.data.prefill || {}, this.known);
        if (prefill.consultant) this.context.consultant = prefill.consultant;
        if (prefill.tool) this.context.tool = prefill.tool;
        const handed = takeSummary();
        if (handed) {
            const clean = sanitisePrefill({ summary: handed }, this.known);
            if (clean.summary) summary.value = appendText(summary.value, clean.summary);
        }
        // The codes are applied: drop them from the address so a reload does not apply them again.
        try {
            const clean = withoutPrefillParams(window.location.href);
            if (clean) window.history.replaceState(window.history.state, '', clean);
        } catch {
            /* history unavailable: harmless */
        }
    }

    // ------------------------------------------------------------------ navigation

    go(n, { focus = true, animate = true } = {}) {
        const target = Math.max(1, Math.min(STEPS, Number(n) || 1));
        const direction = target >= this.step ? 1 : -1;
        this.step = target;
        this.root.dataset.step = String(target);
        const animated = animate && !reducedMotion();
        for (const panel of this.panels) {
            const current = Number(panel.dataset.stepPanel) === target;
            panel.hidden = !current;
            panel.classList.remove('is-entering', 'is-forward', 'is-backward');
            if (current && animated) {
                void panel.offsetWidth;
                panel.classList.add('is-entering', direction > 0 ? 'is-forward' : 'is-backward');
            }
        }
        this.renderSteps(target);
        if (this.back) this.back.hidden = target === 1;
        if (this.nextBtn) this.nextBtn.hidden = target === STEPS;
        if (this.submitBtn) this.submitBtn.hidden = target !== STEPS;
        if (target === 2) this.renderSituations();
        if (target === 4) this.renderDocuments();
        if (target === STEPS) {
            this.renderReview();
            this.securityCheck().then((check) => check?.load());
        }
        if (focus) this.focusStep();
    }

    focusStep() {
        const panel = this.panels.find((p) => !p.hidden);
        focusEl(panel?.querySelector('[data-step-title]'), true);
        this.reveal(this.stepsEl && !this.stepsEl.hidden ? this.stepsEl : panel);
    }

    /** Bring an element into view when its top is under the header or far down the screen. */
    reveal(el) {
        if (!el) return;
        const top = el.getBoundingClientRect().top;
        if (top < 72 || top > window.innerHeight * 0.6) el.scrollIntoView({ block: 'start', behavior: scrollBehavior() });
    }

    /**
     * Keep a focused control (and its error message) on screen together with a context element above it (the error summary, else the
     * step head): the first anchor that fits with the control is scrolled to just under the sticky header (its
     * scroll-margin); when none fits, the field is. Nothing moves when an anchor and the control are
     * both in view already.
     */
    revealWith(control, anchors) {
        if (!control) return;
        const view = window.innerHeight;
        const padEnd = parseFloat(getComputedStyle(document.documentElement).scrollPaddingBlockEnd) || 0;
        // The control and its error message below it.
        const error = control.id ? document.getElementById(`${control.id}-error`) : null;
        const bottom = Math.max(control.getBoundingClientRect().bottom, error && !error.hidden ? error.getBoundingClientRect().bottom : -Infinity);
        for (const anchor of anchors) {
            if (!anchor || anchor.hidden || !anchor.getClientRects().length) continue;
            const margin = parseFloat(getComputedStyle(anchor).scrollMarginBlockStart) || 0;
            const top = anchor.getBoundingClientRect().top;
            if (top >= margin && bottom <= view - padEnd) return;
            const shift = top - margin;
            if (bottom - shift <= view - padEnd) {
                window.scrollTo({ top: window.scrollY + shift, behavior: scrollBehavior() });
                return;
            }
        }
        // Neither fits: the field itself (label, help, control, error) just under the header.
        const field = (control.id && document.querySelector(`[data-field="${control.id}"]`)) || control;
        field.scrollIntoView({ block: 'start', behavior: scrollBehavior() });
    }

    /** Swap in the step list of step n (the one progress indicator, D37; a server-rendered template). */
    renderSteps(n) {
        const template = this.root.querySelector(`template[data-wizard-steps-for="${n}"]`);
        const live = this.stepsEl?.querySelector('.step-list');
        const fresh = template?.content.querySelector('.step-list');
        if (!live || !fresh) return;
        live.replaceWith(fresh.cloneNode(true));
    }

    next() {
        const errors = checkStep(this.step, this.values());
        if (Object.keys(errors).length) {
            this.showErrors(this.clientErrors(errors), { focus: 'field' });
            return;
        }
        this.clearErrors();
        this.go(this.step + 1);
        this.save(false);
    }

    // ------------------------------------------------------------------ events

    onSubmit(event) {
        event.preventDefault();
        if (this.status === 'sending' || this.status === 'received') return;
        if (this.step < STEPS) this.next();
        else this.send();
    }

    onClick(event) {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        const hit = (selector) => target.closest(selector);
        let el;
        if ((el = hit('[data-wizard-back]'))) {
            this.go(this.step - 1);
        } else if ((el = hit('button[data-step]')) && this.stepsEl?.contains(el)) {
            this.go(Number(el.dataset.step));
        } else if ((el = hit('[data-goto-step]'))) {
            this.go(Number(el.dataset.gotoStep));
        } else if ((el = hit('a[data-error-target]'))) {
            event.preventDefault();
            this.go(Number(el.dataset.errorStep), { focus: false });
            const control = document.getElementById(el.dataset.errorTarget);
            focusEl(control, true);
            this.revealWith(control, [this.panels.find((panel) => !panel.hidden)?.querySelector('[data-step-title]')]);
        } else if ((el = hit('[data-wizard-restore-accept]'))) {
            this.restoreDraft();
        } else if ((el = hit('[data-wizard-restore-discard]'))) {
            this.discardDraft();
        } else if ((el = hit('[data-wizard-context-remove]'))) {
            this.context = { consultant: '', tool: '' };
            this.renderContext();
            this.announce(this.text.removed);
            this.focusStep();
            this.save(false);
        } else if ((el = hit('[data-wizard-message-update]'))) {
            const composed = this.composed();
            this.f.message.value = composed;
            this.message = { edited: false, base: composed };
            if (this.staleEl) this.staleEl.hidden = true;
            focusEl(this.f.message);
            this.save(false);
        } else if ((el = hit('[data-wizard-new]'))) {
            event.preventDefault();
            this.reset();
        }
    }

    onEdit(event) {
        const target = event.target;
        if (!(target instanceof Element)) return;
        // The autosave choice is not part of the request: it is honoured in every state (it stays reachable
        // while sending, outside the inert steps), so the box and the behaviour never disagree.
        if (target === this.f.autosave) {
            if (event.type === 'change') this.setAutosave(target.checked);
            return;
        }
        if (this.status === 'sending') return;
        if (target === this.f.attachment) this.onAttachmentChange();
        if (target === this.f.matter && event.type === 'change') this.renderSituations();
        if (target.matches('[data-wizard-doc]') && event.type === 'change') {
            const code = this.f.matter.value;
            const set = (this.ticks[code] ||= new Set());
            const index = Number(target.value);
            if (target.checked) set.add(index);
            else set.delete(index);
        }
        if (target === this.f.message && event.type === 'input') {
            this.message.edited = this.f.message.value !== this.message.base;
        }
        this.recheck(target);
        if (this.pendingDraft && event.type === 'input') this.dismissDraftPrompt();
        this.scheduleSave();
    }

    /** A shown error disappears as soon as the field is right (new errors only appear on Next / Submit). */
    recheck(target) {
        if (!this.errors.size) return;
        const fieldFor = Object.entries(FIELD_ID).find(([, suffix]) => target.id === `${this.p}-${suffix}`)?.[0];
        if (!fieldFor) return;
        const values = this.values();
        const names = fieldFor === 'phone' || fieldFor === 'email' ? ['phone', 'email', 'contact'] : [fieldFor];
        let changed = false;
        for (const name of names) {
            if (!this.errors.has(name)) continue;
            const step = RECHECK[name];
            if (!step || checkStep(step, values)[name]) continue;
            this.errors.delete(name);
            this.clearField(name);
            changed = true;
        }
        if (!changed) return;
        this.syncInvalid();
        if (this.errors.size) this.renderErrorList();
        else this.hideNotice('errors');
    }

    /**
     * Another document was chosen, dropped or removed: the server's message about the previous one no longer
     * applies. Only the wizard's list changes here; the upload control shows (or hides) its own message for the
     * new file, so its error element is left to it.
     */
    onAttachmentChange() {
        if (!this.errors.has('attachment')) return;
        this.errors.delete('attachment');
        this.syncInvalid();
        if (this.errors.size) this.renderErrorList();
        else this.hideNotice('errors');
    }

    // ------------------------------------------------------------------ values

    values() {
        const f = this.f;
        return {
            name: f.name?.value ?? '',
            summary: f.summary?.value ?? '',
            deadline_date: f.deadlineDate?.value ?? '',
            deadline_date_bad: !!f.deadlineDate?.validity?.badInput,
            phone: f.phone?.value ?? '',
            email: f.email?.value ?? '',
            message: f.message?.value ?? '',
            enquiry_consent: !!f.consent?.checked,
        };
    }

    audience() {
        return this.audienceInputs.find((input) => input.checked)?.value ?? '';
    }

    situation() {
        const value = this.f.situation?.value ?? '';
        return value.startsWith(`${this.f.matter.value}-`) ? value : '';
    }

    tickedDocuments() {
        const code = this.f.matter.value;
        const service = this.services[code];
        const set = this.ticks[code];
        if (!service || !set) return [];
        return service.documents.filter((_, i) => set.has(i));
    }

    composed() {
        const context = {
            audience: this.labels.audience?.[this.audience()] ?? '',
            situation: situationText(this.situation(), this.services),
            consultant: this.labels.consultant?.[this.context.consultant] ?? '',
            tool: this.labels.tool?.[this.context.tool] ?? '',
        };
        return composeMessage({
            summary: this.f.summary.value,
            context: contextLines(context, this.text.context || {}),
            documents: documentsBlock(this.tickedDocuments(), this.text.documents),
        });
    }

    /** Exactly the endpoint's fields (docs/enquiries.md §1): nothing the visitor has not seen. */
    payload() {
        const f = this.f;
        const out = {
            idempotency_key: f.key?.value ?? '',
            website: f.website?.value ?? '',
            name: f.name.value,
            phone: f.phone.value,
            email: f.email.value,
            preferred_language: f.language?.value ?? '',
            matter_type: f.matter.value,
            summary: f.message.value,
            deadline_date: f.deadlineDate?.value ?? '',
            deadline_details: f.deadlineDetails?.value ?? '',
        };
        if (f.consent.checked) out.enquiry_consent = '1';
        return out;
    }

    // ------------------------------------------------------------------ rendering

    renderSituations() {
        const select = this.f.situation;
        if (!select || !this.situationField) return;
        const code = this.f.matter.value;
        const service = this.services[code];
        const current = select.value;
        this.situationField.hidden = !service;
        if (select.dataset.for !== code) {
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = this.text.situationPlaceholder || '';
            const options = (service?.situations || []).map((text, i) => {
                const option = document.createElement('option');
                option.value = `${code}-${i + 1}`;
                option.textContent = text;
                return option;
            });
            select.replaceChildren(placeholder, ...options);
            select.dataset.for = code;
        }
        select.value = service && current.startsWith(`${code}-`) ? current : '';
    }

    renderDocuments() {
        const code = this.f.matter.value;
        const service = this.services[code];
        if (this.docsField) this.docsField.hidden = !service;
        if (this.emptyDocs.none) this.emptyDocs.none.hidden = code !== '';
        if (this.emptyDocs.generic) this.emptyDocs.generic.hidden = code === '' || !!service;
        if (!service) return;
        const set = this.ticks[code];
        this.docRows.forEach((row, i) => {
            const doc = service.documents[i];
            row.hidden = !doc;
            if (!doc) return;
            const label = row.querySelector('[data-wizard-doc-label]');
            if (label) setBidiText(label, doc, this.locale);
            const input = row.querySelector('[data-wizard-doc]');
            if (input) input.checked = !!set?.has(i);
        });
        if (this.docsNote) this.docsNote.textContent = service.note || '';
    }

    renderContext() {
        if (!this.contextEl || !this.contextList) return;
        const lines = contextLines(
            {
                consultant: this.labels.consultant?.[this.context.consultant] ?? '',
                tool: this.labels.tool?.[this.context.tool] ?? '',
            },
            this.text.context || {},
        );
        this.contextList.replaceChildren(
            ...lines.map((line) => {
                const li = document.createElement('li');
                li.textContent = line;
                return li;
            }),
        );
        this.contextEl.hidden = lines.length === 0;
    }

    renderReview() {
        const empty = this.text.notProvided || '';
        const set = (key, value) => {
            const el = this.root.querySelector(`[data-review="${key}"]`);
            if (!el) return;
            el.textContent = value || empty;
            el.classList.toggle('is-empty', !value);
        };
        const row = (key, shown) => {
            const el = this.root.querySelector(`[data-review-row="${key}"]`);
            if (el) el.hidden = !shown;
        };
        set('audience', this.labels.audience?.[this.audience()] ?? '');
        set('name', this.f.name.value.trim());
        set('matter', this.labels.matter?.[this.f.matter.value] ?? '');
        const situation = situationText(this.situation(), this.services);
        set('situation', situation);
        row('situation', !!this.services[this.f.matter.value]);
        set('deadline_date', this.formatDate(this.f.deadlineDate?.value ?? ''));
        set('deadline_details', this.f.deadlineDetails?.value.trim() ?? '');
        set('phone', phoneValue(this.f.phone.value));
        set('email', this.f.email.value.trim());
        set('language', this.labels.language?.[this.f.language?.value ?? ''] ?? '');
        set('attachment', attachmentOf(this.form)?.name ?? '');
        row('attachment', !!this.f.attachment);

        const docsEl = this.root.querySelector('[data-review="documents"]');
        if (docsEl) {
            const docs = this.tickedDocuments();
            const items = (docs.length ? docs : [empty]).map((doc) => {
                const li = document.createElement('li');
                if (docs.length) setBidiText(li, doc, this.locale);
                else li.textContent = doc;
                return li;
            });
            docsEl.replaceChildren(...items);
            docsEl.classList.toggle('is-empty', !docs.length);
            // With the labelled document row below, an empty checklist needs no "Not provided" line of its own.
            docsEl.hidden = !docs.length && !!this.f.attachment;
        }

        const composed = this.composed();
        if (!this.message.edited) {
            this.f.message.value = composed;
            this.message.base = composed;
            if (this.staleEl) this.staleEl.hidden = true;
        } else if (this.staleEl) {
            this.staleEl.hidden = composed === this.message.base;
        }
    }

    formatDate(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        if (!match) return '';
        try {
            return new Intl.DateTimeFormat(DATE_LOCALES[this.locale] || this.locale, {
                dateStyle: 'long',
                timeZone: 'UTC',
                numberingSystem: 'latn',
            }).format(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));
        } catch {
            return value;
        }
    }

    // ------------------------------------------------------------------ errors

    /**
     * A 422 carries the endpoint's message for each field (the Contact file's). The page shows its own approved
     * wording for that field (the home form: the homepage file's), as rendered in the field's error element; the
     * document keeps the server's message (four different refusals).
     */
    pageMessages(list) {
        return list.map((item) => (item.field !== 'attachment' && this.defaults[item.field] ? { ...item, message: this.defaults[item.field] } : item));
    }

    clientErrors(errors) {
        return mapErrors(errors, {
            resolve: (field, key) => (key === 'message_long' ? this.text.messageLong || '' : this.defaults[field] || ''),
        });
    }

    /** Show errors inline and in the summary. focus: 'field' (first error), 'summary' or false. */
    showErrors(list, { focus = 'field' } = {}) {
        this.clearErrors();
        this.hideNotices();
        for (const item of list) this.errors.set(item.field, item);
        for (const item of list) this.showField(item.field, item.message);
        this.syncInvalid();
        this.renderErrorList();
        const notice = this.notices.errors;
        if (notice) notice.hidden = false;
        if (focus === 'summary') {
            focusEl(notice);
        } else if (focus === 'field') {
            const first = list.find((item) => item.step === this.step) || list[0];
            const control = first?.target ? document.getElementById(`${this.p}-${FIELD_ID[first.target] ?? first.target}`) : null;
            // Focus without the browser's own scroll (which can leave the cards above the field clipped under the
            // sticky header), then bring the error summary or the step head into view with the field.
            focusEl(control || notice, true);
            const title = this.panels.find((panel) => !panel.hidden)?.querySelector('[data-step-title]');
            this.revealWith(control || notice, [notice, title]);
            this.announce(this.text.errorsTitle);
        }
    }

    renderErrorList() {
        if (!this.errorList) return;
        const ordered = mapErrors(Object.fromEntries([...this.errors.values()].map((item) => [item.field, item.message])));
        this.errorList.replaceChildren(
            ...ordered.map((item) => {
                const li = document.createElement('li');
                const control = item.target ? document.getElementById(`${this.p}-${FIELD_ID[item.target] ?? item.target}`) : null;
                if (!control) {
                    li.textContent = item.message;
                    return li;
                }
                const a = document.createElement('a');
                a.className = 't-link';
                a.href = `#${control.id}`;
                a.dataset.errorTarget = control.id;
                a.dataset.errorStep = String(item.step);
                a.textContent = item.message;
                li.append(a);
                return li;
            }),
        );
    }

    showField(field, message) {
        const id = `${this.p}-${FIELD_ID[field] ?? field}`;
        const errorEl = document.getElementById(`${id}-error`);
        if (!errorEl) return;
        const textEl = errorEl.querySelector('.field__error-text');
        if (textEl) textEl.textContent = message || this.defaults[field] || textEl.textContent;
        errorEl.hidden = false;
        // The group (fieldset) carries the contact error; every other field its own control.
        addToken(document.getElementById(id), 'aria-describedby', errorEl.id);
        document.querySelector(`[data-field="${id}"]`)?.classList.add('is-invalid');
        if (field === 'enquiry_consent') this.f.consent?.closest('.check')?.classList.add('is-invalid');
    }

    clearField(field) {
        const id = `${this.p}-${FIELD_ID[field] ?? field}`;
        const errorEl = document.getElementById(`${id}-error`);
        if (errorEl) {
            errorEl.hidden = true;
            const textEl = errorEl.querySelector('.field__error-text');
            if (textEl && this.defaults[field]) textEl.textContent = this.defaults[field];
        }
        removeToken(document.getElementById(id), 'aria-describedby', `${id}-error`);
        document.querySelector(`[data-field="${id}"]`)?.classList.remove('is-invalid');
        if (field === 'enquiry_consent') this.f.consent?.closest('.check')?.classList.remove('is-invalid');
    }

    /** aria-invalid and the error look follow the shown errors (the contact error marks both inputs). */
    syncInvalid() {
        const marks = {
            name: this.errors.has('name'),
            summary: this.errors.has('summary'),
            message: this.errors.has('message'),
            deadline_date: this.errors.has('deadline_date'),
            deadline_details: this.errors.has('deadline_details'),
            phone: this.errors.has('phone') || this.errors.has('contact'),
            email: this.errors.has('email') || this.errors.has('contact'),
            enquiry_consent: this.errors.has('enquiry_consent'),
        };
        // The document input: marked while the server's message shows; unmarked only when no message shows
        // (a refused file's own message belongs to the upload control, which marks and unmarks it itself).
        if (this.f.attachment) {
            const shown = document.getElementById(`${this.p}-${FIELD_ID.attachment}-error`)?.hidden === false;
            if (this.errors.has('attachment') || !shown) marks.attachment = this.errors.has('attachment');
        }
        for (const [field, invalid] of Object.entries(marks)) {
            const control = document.getElementById(`${this.p}-${FIELD_ID[field]}`);
            if (!control) continue;
            if (invalid) control.setAttribute('aria-invalid', 'true');
            else control.removeAttribute('aria-invalid');
            control.classList.toggle('is-invalid', invalid);
        }
    }

    clearErrors() {
        for (const field of Object.keys(FIELD_ID)) {
            // A refused file's own message (the upload control's) stays until the visitor picks another file.
            if (field === 'attachment' && !this.errors.has('attachment')) continue;
            this.clearField(field);
        }
        this.errors.clear();
        this.syncInvalid();
        this.errorList?.replaceChildren();
        this.hideNotice('errors');
    }

    // ------------------------------------------------------------------ notices

    hideNotice(kind) {
        if (this.notices[kind]) this.notices[kind].hidden = true;
    }

    hideNotices() {
        for (const kind of NOTICES) if (kind !== 'errors') this.hideNotice(kind);
    }

    showNotice(kind, { focus = true } = {}) {
        this.hideNotices();
        const notice = this.notices[kind];
        if (!notice) return;
        notice.hidden = false;
        if (focus) focusEl(notice);
    }

    showSuccess(reference, { focus = true } = {}) {
        this.status = 'received';
        this.root.dataset.state = 'received';
        window.clearTimeout(this.saveTimer);
        this.drafts.clear();
        if (this.reference) this.reference.textContent = reference;
        this.clearErrors();
        this.hideNotices();
        if (this.restoreEl) this.restoreEl.hidden = true;
        this.form.hidden = true;
        if (this.stepsEl) this.stepsEl.hidden = true;
        if (this.success) {
            this.success.hidden = false;
            if (focus) focusEl(this.success);
        }
    }

    announce(message) {
        if (!this.specimen && message) announce(message);
    }

    // ------------------------------------------------------------------ sending

    setSending(on) {
        this.status = on ? 'sending' : 'idle';
        this.root.dataset.state = this.status;
        this.form.setAttribute('aria-busy', on ? 'true' : 'false');
        for (const panel of this.panels) panel.inert = on;
        const button = this.submitBtn;
        if (button) {
            if (on) button.style.setProperty('--wz-busy-w', `${button.offsetWidth}px`);
            button.classList.toggle('is-loading', on);
            const label = button.querySelector('.btn__label');
            if (label) label.textContent = on ? this.text.busy || this.submitLabel : this.submitLabel;
            for (const attr of ['aria-disabled', 'aria-busy']) {
                if (on) button.setAttribute(attr, 'true');
                else button.removeAttribute(attr);
            }
        }
        if (this.back) {
            if (on) this.back.setAttribute('aria-disabled', 'true');
            else this.back.removeAttribute('aria-disabled');
        }
    }

    async send() {
        const invalid = firstInvalidStep(this.values(), CHECKED_STEPS);
        if (invalid) {
            if (invalid.step !== this.step) this.go(invalid.step, { focus: false });
            this.showErrors(this.clientErrors(invalid.errors), { focus: 'field' });
            return;
        }
        this.clearErrors();
        this.hideNotices();
        if (this.specimen) return;
        if (navigator.onLine === false) {
            this.finish('failed');
            return;
        }
        this.setSending(true);
        this.announce(this.text.busy);

        const check = await this.securityCheck();
        if (check === false) {
            this.setSending(false);
            this.showNotice('turnstile_unavailable');
            return;
        }
        if (check && !check.token()) {
            this.announce(this.text.turnstilePending);
            const state = await check.waitForToken(TOKEN_WAIT);
            if (state !== 'ready') {
                this.setSending(false);
                this.showNotice(state === 'error' ? 'turnstile_unavailable' : 'turnstile_incomplete');
                return;
            }
        }

        const payload = this.payload();
        const file = attachmentOf(this.form);
        const { idempotency_key: _key, ...content } = payload;
        // The document is part of the request: another file (or none) after an uncertain result is a new one.
        const fingerprint = JSON.stringify({ ...content, attachment: file ? [file.name, file.size, file.lastModified] : null });
        // After an uncertain result an edited request is a new enquiry for the server: a new key.
        if (this.snapshot !== null && this.snapshot !== fingerprint && this.f.key) {
            this.f.key.value = newKey();
            payload.idempotency_key = this.f.key.value;
        }
        this.snapshot = null;

        const body = new FormData();
        for (const [name, value] of Object.entries(payload)) body.append(name, value);
        if (file) body.append('attachment', file, file.name);
        if (this.f.csrf) body.append('_token', this.f.csrf.value);
        if (check) body.append('cf-turnstile-response', check.token());

        const result = await this.post(body);
        this.setSending(false);
        const outcome = classify({ ...result, offline: navigator.onLine === false });
        if (check && resetsToken(outcome)) check.reset();
        this.finish(outcome, { body: result.body, fingerprint });
    }

    async post(body) {
        const controller = new AbortController();
        const timer = window.setTimeout(() => controller.abort(), Number(this.data.timeout) || 20000);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || this.f.csrf?.value || '';
        try {
            const response = await fetch(this.data.endpoint, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            let json = null;
            try {
                json = await response.json();
            } catch {
                json = null;
            }
            return { status: response.status, body: json, error: null };
        } catch (error) {
            return { status: 0, body: null, error };
        } finally {
            window.clearTimeout(timer);
        }
    }

    finish(outcome, { body = null, fingerprint = null } = {}) {
        if (outcome === 'received') {
            this.showSuccess(body?.reference || '');
            return;
        }
        if (outcome === 'invalid') {
            const list = this.pageMessages(mapErrors(body?.errors || {}, { server: true }));
            const step = firstErrorStep(list);
            if (step && step !== this.step) this.go(step, { focus: false });
            this.showErrors(list, { focus: 'summary' });
            return;
        }
        if (outcome === 'uncertain') this.snapshot = fingerprint;
        this.showNotice(outcome === 'rate_limited' ? 'rate_limited' : outcome === 'uncertain' ? 'uncertain' : 'failed');
    }

    /**
     * The Turnstile helper, imported on first use. Resolves to null when the security check is off (or on
     * a styleguide specimen) and to false when its code could not be loaded.
     */
    securityCheck() {
        if (this.specimen || !this.data.turnstile) return Promise.resolve(null);
        this.turnstileLoading ||= import('./turnstile.js').then(
            ({ createTurnstile }) => {
                this.turnstile = createTurnstile({
                    siteKey: this.data.turnstile,
                    locale: this.locale,
                    container: this.turnstileContainer,
                    onChange: (state) => this.onTurnstile(state),
                });
                return this.turnstile;
            },
            () => {
                this.turnstileLoading = null;
                return false;
            },
        );
        return this.turnstileLoading;
    }

    onTurnstile(state) {
        // The invisible check takes no room on the review step until Cloudflare asks the visitor to act
        // (components/wizard.css keys on this), so the actions never jump when the widget renders.
        if (this.turnstileContainer) this.turnstileContainer.dataset.check = state;
        if (state === 'ready') {
            this.hideNotice('turnstile_incomplete');
            this.hideNotice('turnstile_unavailable');
        }
    }

    /** "Send a New Enquiry": an empty form, a new key, no draft. */
    reset({ focus = true } = {}) {
        const f = this.f;
        for (const input of [f.name, f.summary, f.deadlineDate, f.deadlineDetails, f.email, f.message]) if (input) input.value = '';
        if (f.phone) f.phone.value = '+971 ';
        for (const select of [f.matter, f.language]) if (select) select.value = '';
        if (f.consent) f.consent.checked = false;
        this.audienceInputs.forEach((input) => {
            input.checked = false;
        });
        if (f.situation) f.situation.value = '';
        // The document control empties itself (file, chip and message).
        this.root.querySelector('[data-upload]')?.dispatchEvent(new CustomEvent('d2-upload-reset'));
        this.ticks = {};
        this.context = { consultant: '', tool: '' };
        this.message = { edited: false, base: '' };
        this.snapshot = null;
        if (f.key) f.key.value = newKey();
        this.status = 'idle';
        this.root.dataset.state = 'idle';
        this.clearErrors();
        this.hideNotices();
        this.drafts.clear();
        this.turnstile?.reset();
        if (this.success) this.success.hidden = true;
        this.form.hidden = false;
        if (this.stepsEl) this.stepsEl.hidden = false;
        this.renderContext();
        this.go(1, { focus, animate: false });
    }

    // ------------------------------------------------------------------ prefill

    onPrefill(detail) {
        if (this.status === 'sending') return;
        const clean = sanitisePrefill(detail, this.known);
        if (this.status === 'received') this.reset({ focus: false });
        this.applyPrefill(clean);
        this.announce(this.text.prefillApplied);
        // prefill() scrolls the section into view; focus the step without fighting that scroll, then make sure
        // the step really is on screen (Design 1 review A11Y-07: focus never lands off screen).
        const title = this.panels.find((p) => !p.hidden)?.querySelector('[data-step-title]');
        focusEl(title, true);
        let done = false;
        const check = () => {
            if (done) return;
            done = true;
            const rect = title?.getBoundingClientRect();
            if (rect && (rect.top < 64 || rect.bottom > window.innerHeight)) title.scrollIntoView({ block: 'center', behavior: scrollBehavior() });
        };
        window.addEventListener('scrollend', check, { once: true });
        window.setTimeout(check, 900);
        this.save(false);
    }

    applyPrefill(clean) {
        const f = this.f;
        if (clean.service && Array.from(f.matter.options).some((o) => o.value === clean.service)) {
            f.matter.value = clean.service;
        }
        this.renderSituations();
        if (clean.situation && f.situation) {
            f.situation.value = clean.situation;
            this.renderSituations();
        }
        if (clean.audience) {
            this.audienceInputs.forEach((input) => {
                input.checked = input.value === clean.audience;
            });
        }
        if (clean.consultant) this.context.consultant = clean.consultant;
        if (clean.tool) this.context.tool = clean.tool;
        if (clean.summary) f.summary.value = appendText(f.summary.value, clean.summary);
        this.renderContext();
        if (this.step === 4) this.renderDocuments();
        if (this.step === STEPS) this.renderReview();
    }

    // ------------------------------------------------------------------ drafts

    draftValues() {
        const f = this.f;
        const docs = {};
        for (const [code, set] of Object.entries(this.ticks)) if (set.size) docs[code] = [...set];
        return {
            step: this.step,
            audience: this.audience(),
            name: f.name.value,
            matter: f.matter.value,
            situation: this.situation(),
            summary: f.summary.value,
            deadlineDate: f.deadlineDate?.value ?? '',
            deadlineDetails: f.deadlineDetails?.value ?? '',
            docs,
            phone: f.phone.value,
            email: f.email.value,
            language: f.language?.value ?? '',
            message: f.message?.value ?? '',
            messageEdited: this.message.edited,
            messageBase: this.message.base,
            consultant: this.context.consultant,
            tool: this.context.tool,
        };
    }

    canSave() {
        return this.draftEnabled && this.autosave && !this.specimen && !this.pendingDraft && this.status !== 'received' && this.status !== 'sending';
    }

    /**
     * "Keep a draft on this device". Unticked: the stored draft is deleted at once (and a restore prompt with
     * it) and nothing is saved again during this visit. Ticked again: the answers are saved now.
     */
    setAutosave(on) {
        if (this.specimen || !this.draftEnabled) return;
        this.autosave = !!on;
        if (this.autosave) {
            this.save(true);
            return;
        }
        window.clearTimeout(this.saveTimer);
        this.saveTimer = 0;
        this.drafts.clear();
        if (this.pendingDraft || (this.restoreEl && !this.restoreEl.hidden)) this.dismissDraftPrompt();
        this.announce(this.text.draftOff);
    }

    scheduleSave() {
        if (!this.canSave()) return;
        window.clearTimeout(this.saveTimer);
        this.saveTimer = window.setTimeout(() => this.save(true), SAVE_DELAY);
    }

    flushSave() {
        if (!this.saveTimer) return;
        window.clearTimeout(this.saveTimer);
        this.saveTimer = 0;
        this.save(false);
    }

    save(fromEdit) {
        this.saveTimer = 0;
        if (!this.canSave()) return;
        const values = this.draftValues();
        if (!hasContent(values)) return;
        const saved = this.drafts.write(values);
        if (saved && fromEdit && this.shouldToast()) toast(this.text.draftSaved, { duration: 3000 });
    }

    /**
     * The restore prompt. lazy.js already showed it at start-up when a fresh draft is stored ('shown') or
     * hid it again because the visitor started typing ('dismissed': the new answers replace the draft).
     * The full check here hides it when the stored draft holds nothing worth restoring.
     */
    offerDraft(prompt) {
        if (!this.restoreEl || prompt === 'dismissed') return;
        const draft = this.drafts.read();
        if (!hasContent(draft)) {
            this.restoreEl.hidden = true;
            return;
        }
        this.pendingDraft = draft;
        this.restoreEl.hidden = false;
    }

    dismissDraftPrompt() {
        this.pendingDraft = null;
        if (this.restoreEl) this.restoreEl.hidden = true;
    }

    discardDraft() {
        this.drafts.clear();
        this.dismissDraftPrompt();
        this.announce(this.text.draftDiscarded);
        this.focusStep();
    }

    restoreDraft() {
        const draft = this.pendingDraft;
        this.dismissDraftPrompt();
        if (!draft) return;
        this.fillValues(draft);
        this.announce(this.text.draftRestored);
        this.go(draft.step || 1);
        this.save(false);
    }

    /** Put stored or sample values back into the fields (unknown codes are ignored). */
    fillValues(values) {
        const f = this.f;
        const setSelect = (select, value) => {
            if (select && Array.from(select.options).some((o) => o.value === value)) select.value = value;
        };
        this.audienceInputs.forEach((input) => {
            input.checked = input.value === values.audience;
        });
        if (typeof values.name === 'string') f.name.value = values.name;
        setSelect(f.matter, values.matter || '');
        this.renderSituations();
        setSelect(f.situation, values.situation || '');
        if (typeof values.summary === 'string') f.summary.value = values.summary;
        if (f.deadlineDate && typeof values.deadlineDate === 'string') f.deadlineDate.value = values.deadlineDate;
        if (f.deadlineDetails && typeof values.deadlineDetails === 'string') f.deadlineDetails.value = values.deadlineDetails;
        if (typeof values.phone === 'string') f.phone.value = values.phone;
        if (typeof values.email === 'string') f.email.value = values.email;
        setSelect(f.language, values.language || '');
        this.ticks = {};
        for (const [code, list] of Object.entries(values.docs || {})) {
            if (this.services[code] && Array.isArray(list)) this.ticks[code] = new Set(list.filter((i) => Number.isInteger(i)));
        }
        if (values.consultant && Object.hasOwn(this.known.consultants, values.consultant)) this.context.consultant = values.consultant;
        if (values.tool && Object.hasOwn(this.known.tools, values.tool)) this.context.tool = values.tool;
        if (values.messageEdited && typeof values.message === 'string' && f.message) {
            f.message.value = values.message;
            this.message = { edited: true, base: typeof values.messageBase === 'string' ? values.messageBase : '' };
        }
        this.renderContext();
    }

    // ------------------------------------------------------------------ styleguide specimens

    applySpecimenValues() {
        const values = this.specimen.values && typeof this.specimen.values === 'object' ? this.specimen.values : {};
        this.fillValues(values);
        return Math.max(1, Math.min(STEPS, Number(this.specimen.step) || 1));
    }

    applySpecimenState() {
        const state = this.specimen.state;
        if (state === 'errors') {
            const errors = checkStep(this.step, this.values());
            if (Object.keys(errors).length) this.showErrors(this.clientErrors(errors), { focus: false });
        } else if (state === 'invalid') {
            this.showErrors(mapErrors(this.specimen.errors || {}, { server: true }), { focus: false });
        } else if (state === 'sending') {
            this.setSending(true);
        } else if (state === 'received') {
            this.showSuccess(this.specimen.reference || '', { focus: false });
        } else if (NOTICES.includes(state)) {
            this.showNotice(state, { focus: false });
        } else if (state === 'restore' && this.restoreEl) {
            this.restoreEl.hidden = false;
        } else if (state === 'stale') {
            this.message = { edited: true, base: '' };
            this.renderReview();
        }
    }
}

/**
 * Read the page data and start the wizard; null when the markup is not a wizard (lazy.js then puts the plain
 * form back). options: { prompt: 'none' | 'shown' | 'dismissed', typed } from the start-up code.
 * A start that throws half way removes its listeners before the error reaches lazy.js.
 */
export function mountWizard(root, options = {}) {
    const data = readJson(root.querySelector('[data-wizard-data]'));
    if (!data) return null;
    const services = readJson(document.getElementById(data.servicesRef || '')) || {};
    const wizard = new Wizard(root, data, services, options);
    try {
        if (wizard.mount()) return wizard;
    } catch (error) {
        wizard.destroy();
        throw error;
    }
    wizard.destroy();
    return null;
}

