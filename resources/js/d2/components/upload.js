/**
 * d2Upload (<x-d2.upload.dropzone>, resources/views/components/d2/upload/dropzone.blade.php): the backend's
 * one optional attachment (docs/enquiries.md §6), rendered only while attachments are enabled.
 *
 * Without JavaScript the native <input type="file" name="attachment"> is shown as it is (its button styled
 * like a secondary button) and posts with the form. This enhancement lays the same input, invisible, over
 * the whole drop zone, so a click, Enter or Space anywhere on it opens the file picker and the input stays
 * the one control screen readers and keyboards meet. It adds:
 *
 *   drag and drop   a file dragged over the zone lights it up ("Release to attach the document"); a drop
 *                   goes through the same checks as a pick and, when accepted, becomes the input's file.
 *                   A drop that misses the zone never makes the browser leave the half-filled form.
 *   checks          count, size and type (read from the first bytes) with the approved
 *                   ui('form.attachment.error_*') messages (components/upload-checks.js); a refused file
 *                   is never kept, so it is never sent. The server checks again and stays the authority.
 *   file chip       name, size and format of the accepted file, and "Remove Document" (approved): focus goes
 *                   back to the input. Chosen and removed files are announced.
 *   errors          the same DOM contract as <x-d2.ui.field> and the wizard: #{id}-error with
 *                   .field__error-text, its id in the input's aria-describedby only while it shows,
 *                   aria-invalid + .is-invalid, .is-invalid on [data-field="{id}"].
 *   events          `d2-upload-change` { file: { name, size, type } | null } bubbles from the root after every
 *                   change; `d2-upload-reset` on the root (or the form's reset event) empties it.
 *
 * Strings come from the JSON block <script type="application/json" data-upload-copy>; the DOM is changed
 * directly (no template expressions), as the Alpine CSP build requires.
 */
import { announce } from '../lib/announce.js';
import { checkUpload, fill, formatSize } from './upload-checks.js';

const hasFiles = (event) => Array.from(event.dataTransfer?.types ?? []).includes('Files');

function readJson(el) {
    try {
        return JSON.parse(el?.textContent || '{}') || {};
    } catch {
        return {};
    }
}

function addToken(el, attr, token) {
    const list = (el.getAttribute(attr) || '').split(/\s+/).filter(Boolean);
    if (!list.includes(token)) list.push(token);
    el.setAttribute(attr, list.join(' '));
}

function removeToken(el, attr, token) {
    const list = (el.getAttribute(attr) || '').split(/\s+/).filter((t) => t && t !== token);
    if (list.length) el.setAttribute(attr, list.join(' '));
    else el.removeAttribute(attr);
}

export default (Alpine) => {
    Alpine.data('d2Upload', () => {
        // Per-instance handles outside Alpine's reactive state.
        let offs = [];
        let seq = 0;

        return {
            init() {
                const root = this.$root;
                const input = root.querySelector('[data-upload-input]');
                const zone = root.querySelector('[data-upload-zone]');
                if (!input || !zone) return;
                this.root = root;
                this.input = input;
                this.zone = zone;
                this.copy = readJson(root.querySelector('script[data-upload-copy]'));
                this.maxBytes = Number(root.dataset.maxBytes) || undefined;
                this.chip = root.querySelector('[data-upload-file]');
                this.nameEl = root.querySelector('[data-upload-name]');
                this.sizeEl = root.querySelector('[data-upload-size]');
                this.typeEl = root.querySelector('[data-upload-type]');
                this.chipText = root.querySelector('[data-upload-file-text]');
                this.errorEl = document.getElementById(`${input.id}-error`);
                this.errorText = this.errorEl?.querySelector('.field__error-text') ?? null;
                this.field = root.closest(`[data-field="${input.id}"]`);
                root.classList.add('is-enhanced');

                const on = (target, type, handler, options) => {
                    target.addEventListener(type, handler, options);
                    offs.push(() => target.removeEventListener(type, handler, options));
                };

                on(input, 'change', () => this.take(input.files, 'pick'));
                on(zone, 'dragenter', (event) => this.over(event));
                on(zone, 'dragover', (event) => this.over(event));
                on(zone, 'dragleave', (event) => {
                    if (!zone.contains(event.relatedTarget)) zone.classList.remove('is-over');
                });
                on(zone, 'drop', (event) => {
                    if (!hasFiles(event)) return;
                    event.preventDefault();
                    zone.classList.remove('is-over');
                    if (this.locked()) return;
                    this.take(event.dataTransfer.files, 'drop');
                });
                // A file dropped next to the zone must not replace the page (and the visitor's answers).
                on(window, 'dragover', (event) => {
                    if (!hasFiles(event) || zone.contains(event.target)) return;
                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'none';
                });
                on(window, 'drop', (event) => {
                    if (hasFiles(event) && !zone.contains(event.target)) event.preventDefault();
                });
                on(window, 'dragend', () => zone.classList.remove('is-over'));

                on(root, 'click', (event) => {
                    if (event.target.closest?.('[data-upload-remove]')) this.remove();
                });
                on(root, 'd2-upload-reset', () => this.clear({ silent: true }));
                if (input.form) on(input.form, 'reset', () => this.clear({ silent: true }));

                // A file kept by the browser (back/forward cache, autofill of a reloaded form) gets the checks too.
                if (input.files?.length) this.take(input.files, 'pick');
            },

            destroy() {
                offs.forEach((off) => off());
                offs = [];
            },

            /** The wizard makes its steps inert while sending: no drop then. */
            locked() {
                return this.input.disabled || !!this.root.closest('[inert]');
            },

            over(event) {
                if (!hasFiles(event)) return;
                event.preventDefault();
                if (this.locked()) {
                    event.dataTransfer.dropEffect = 'none';
                    return;
                }
                event.dataTransfer.dropEffect = 'copy';
                this.zone.classList.add('is-over');
            },

            async take(files, source) {
                const token = ++seq;
                const list = Array.from(files ?? []);
                if (!list.length) {
                    // The picker was cancelled and the browser emptied the input.
                    if (source === 'pick') this.clear({ silent: true });
                    return;
                }
                const result = await checkUpload(list, { maxBytes: this.maxBytes });
                if (token !== seq) return;
                if (result.error) {
                    this.clear({ silent: true });
                    this.showError(result.error);
                    return;
                }
                if (source === 'drop') {
                    try {
                        const transfer = new DataTransfer();
                        transfer.items.add(result.file);
                        this.input.files = transfer.files;
                    } catch {
                        // Browsers that cannot hand a dropped file to the input: nothing is attached.
                        this.clear({ silent: true });
                        this.showError('error_rejected');
                        return;
                    }
                }
                this.hideError();
                this.showFile(result.file, result.type);
            },

            showFile(file, type) {
                const size = this.sizeText(file.size);
                if (this.nameEl) this.nameEl.textContent = file.name;
                if (this.sizeEl) this.sizeEl.textContent = size;
                if (this.typeEl) this.typeEl.textContent = this.copy.types?.[type] ?? '';
                if (this.chip) this.chip.hidden = false;
                this.zone.classList.add('has-file');
                if (this.chipText) addToken(this.input, 'aria-describedby', this.chipText.id);
                announce(fill(this.copy.attached, { name: file.name, size }));
                this.emit({ name: file.name, size: file.size, type });
            },

            remove() {
                this.clear({ silent: false });
                this.input.focus();
            },

            /** Empty the control (and the chip and error). `silent`: no announcement. */
            clear({ silent = false } = {}) {
                const had = !!this.chip && !this.chip.hidden;
                this.input.value = '';
                if (this.chip) this.chip.hidden = true;
                this.zone.classList.remove('has-file', 'is-over');
                if (this.chipText) removeToken(this.input, 'aria-describedby', this.chipText.id);
                this.hideError();
                if (had) {
                    if (!silent) announce(this.copy.removed);
                    this.emit(null);
                }
            },

            showError(key) {
                const message = this.copy.errors?.[key] ?? '';
                if (!this.errorEl || !message) return;
                if (this.errorText) this.errorText.textContent = message;
                this.errorEl.hidden = false;
                addToken(this.input, 'aria-describedby', this.errorEl.id);
                this.input.setAttribute('aria-invalid', 'true');
                this.input.classList.add('is-invalid');
                this.zone.classList.add('is-invalid');
                this.field?.classList.add('is-invalid');
                announce(message);
            },

            hideError() {
                if (this.errorEl) this.errorEl.hidden = true;
                if (this.errorEl) removeToken(this.input, 'aria-describedby', this.errorEl.id);
                this.input.removeAttribute('aria-invalid');
                this.input.classList.remove('is-invalid');
                this.zone.classList.remove('is-invalid');
                this.field?.classList.remove('is-invalid');
            },

            sizeText(bytes) {
                const { unit, value } = formatSize(bytes, this.copy.locale || document.documentElement.lang || 'en');
                return fill(this.copy.sizes?.[unit] ?? ':size', { size: value });
            },

            emit(file) {
                this.root.dispatchEvent(new CustomEvent('d2-upload-change', { bubbles: true, detail: { file } }));
            },
        };
    });
};
