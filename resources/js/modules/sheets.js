// Bottom sheets: handle, 480ms sheet easing, swipe-down to close, Esc, inert background
// (which also traps focus), focus returns to the control that opened the sheet.
import { reducedMotion } from '../lib/util';

let active = null;
let opener = null;

const DURATION = 480;

function background(on) {
    for (const el of document.body.children) {
        if (el.matches('.sheet-root, script, [data-announcer]')) continue;
        el.inert = on;
    }
}

export function openSheet(id, from = null) {
    const root = document.querySelector(`[data-sheet="${id}"]`);
    if (!root || active === root) return;
    if (active) closeSheet(false);
    active = root;
    opener = from || document.activeElement;
    root.hidden = false;
    // Next frame so the transform transition runs from the closed position.
    requestAnimationFrame(() => {
        requestAnimationFrame(() => root.classList.add('is-open'));
    });
    document.documentElement.classList.add('sheet-open');
    background(true);
    document.querySelectorAll(`[data-sheet-open="${id}"]`).forEach((b) => b.setAttribute('aria-expanded', 'true'));
    const dialog = root.querySelector('.sheet');
    const first = root.querySelector('.sheet__body input, .sheet__body a, .sheet__body button');
    window.setTimeout(() => (first || dialog).focus({ preventScroll: true }), reducedMotion() ? 0 : 60);
}

export function closeSheet(restoreFocus = true) {
    const root = active;
    if (!root) return;
    active = null;
    root.classList.remove('is-open');
    const id = root.dataset.sheet;
    document.querySelectorAll(`[data-sheet-open="${id}"]`).forEach((b) => b.setAttribute('aria-expanded', 'false'));
    background(false);
    document.documentElement.classList.remove('sheet-open');
    window.setTimeout(() => {
        if (!root.classList.contains('is-open')) root.hidden = true;
    }, reducedMotion() ? 0 : DURATION);
    if (restoreFocus && opener && document.contains(opener)) opener.focus({ preventScroll: true });
}

function drag(root) {
    const handle = root.querySelector('[data-sheet-drag]');
    const sheet = root.querySelector('.sheet');
    if (!handle || !sheet) return;
    let startY = 0;
    let dy = 0;
    let t0 = 0;
    handle.addEventListener('pointerdown', (e) => {
        if (e.target.closest('button')) return;
        startY = e.clientY;
        dy = 0;
        t0 = performance.now();
        sheet.classList.add('is-dragging');
        handle.setPointerCapture(e.pointerId);
    });
    handle.addEventListener('pointermove', (e) => {
        if (!sheet.classList.contains('is-dragging')) return;
        dy = Math.max(0, e.clientY - startY);
        sheet.style.setProperty('--drag', `${dy}px`);
    });
    const end = () => {
        if (!sheet.classList.contains('is-dragging')) return;
        sheet.classList.remove('is-dragging');
        const velocity = dy / Math.max(1, performance.now() - t0);
        sheet.style.removeProperty('--drag');
        if (dy > 96 || velocity > 0.6) closeSheet();
    };
    handle.addEventListener('pointerup', end);
    handle.addEventListener('pointercancel', end);
}

export function initSheets() {
    document.querySelectorAll('[data-sheet]').forEach(drag);

    document.addEventListener('click', (e) => {
        const openBtn = e.target.closest('[data-sheet-open]');
        if (openBtn) {
            e.preventDefault();
            openSheet(openBtn.dataset.sheetOpen, openBtn);
            return;
        }
        if (!active) return;
        if (e.target.closest('[data-sheet-close]')) {
            e.preventDefault();
            closeSheet();
            return;
        }
        // In-page destinations (e.g. the consultation form) close the sheet first.
        const link = e.target.closest('a[data-sheet-close-on-click]');
        if (link) closeSheet(false);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && active) {
            e.preventDefault();
            closeSheet();
        }
    });

    window.addEventListener('summit:open-sheet', (e) => openSheet(e.detail.id, e.detail.from));
}
