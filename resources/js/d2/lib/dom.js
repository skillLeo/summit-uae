/** Small DOM helpers shared by Design 2 modules. */
export const qs = (selector, root = document) => root.querySelector(selector);
export const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

export function on(target, type, handler, options) {
    target.addEventListener(type, handler, options);
    return () => target.removeEventListener(type, handler, options);
}

/** 1 in LTR, −1 in RTL: multiply every horizontal offset and rotation by it. */
export const dir = () => (document.documentElement.dir === 'rtl' ? -1 : 1);

export const locale = () => document.documentElement.lang || 'en';

/** The editable element check used by global keyboard shortcuts ("/", Ctrl/Cmd+K, Alt+1/2). */
export function isEditable(el) {
    return !!el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));
}
