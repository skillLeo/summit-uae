import { describe, expect, it } from 'vitest';
import { wrapTarget } from '../control-wrap.js';

// Lives outside modules/*.js on purpose: app.js eagerly imports every modules/*.js file.
// Wrapping placeholders (<x-d2.ui.input wrap-placeholder>, D09): a press anywhere in the field focuses its input.

/** A minimal element: closest() walks the parents and matches [data-control-wrap]. */
function el({ wrap = false, parent = null, input = null } = {}) {
    const node = {
        parent,
        wrap,
        closest(selector) {
            expect(selector).toBe('[data-control-wrap]');
            for (let n = this; n; n = n.parent) if (n.wrap) return n;
            return null;
        },
        querySelector(selector) {
            expect(selector).toBe('input');
            return input;
        },
    };
    return node;
}

describe('wrapping placeholder: wrapTarget', () => {
    it('focuses the input after a press on the field, its copy or its icon', () => {
        const input = { disabled: false };
        const box = el({ wrap: true, input });
        input.closest = () => box;
        const icon = el({ parent: box });
        expect(wrapTarget(box)).toBe(input); // the second line of the copy (pointer-events: none) hits the box
        expect(wrapTarget(icon)).toBe(input);
    });

    it('leaves presses on the input itself to the browser', () => {
        const input = { disabled: false };
        const box = el({ wrap: true, input });
        input.closest = () => box;
        expect(wrapTarget(input)).toBe(null);
    });

    it('never focuses a disabled field', () => {
        const input = { disabled: true };
        expect(wrapTarget(el({ wrap: true, input }))).toBe(null);
    });

    it('ignores everything outside a wrapping field', () => {
        expect(wrapTarget(el())).toBe(null);
        expect(wrapTarget(null)).toBe(null);
        expect(wrapTarget({})).toBe(null); // a node without closest() (a text node, the document)
        expect(wrapTarget(el({ wrap: true, input: null }))).toBe(null);
    });
});
