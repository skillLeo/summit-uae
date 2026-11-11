/**
 * Unit tests for resources/js/d2/components/team.js (d2TeamCta). This file lives in a
 * sub-folder on purpose: app.js eagerly imports ./components/*.js, and a test file there would be
 * bundled into the page.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('../../lib/prefill.js', () => ({ prefill: vi.fn() }));

import { prefill } from '../../lib/prefill.js';
import register, { consultantOf, isModified } from '../team.js';

function registry() {
    const components = {};
    register({ data: (name, factory) => { components[name] = factory; } });
    return components;
}

describe('consultantOf', () => {
    it('accepts the team codes only', () => {
        expect(consultantOf({ dataset: { consultant: 'team-ahmed' } })).toBe('team-ahmed');
        expect(consultantOf({ dataset: { consultant: 'team-Ahmed' } })).toBeNull();
        expect(consultantOf({ dataset: { consultant: 'ahmed' } })).toBeNull();
        expect(consultantOf({ dataset: { consultant: 'team-ahmed"><script>' } })).toBeNull();
        expect(consultantOf({ dataset: {} })).toBeNull();
        expect(consultantOf(null)).toBeNull();
    });
});

describe('isModified', () => {
    it('keeps the browser behaviour for other buttons, modifier keys and handled events', () => {
        expect(isModified({ button: 0 })).toBe(false);
        expect(isModified({ button: 1 })).toBe(true);
        for (const key of ['metaKey', 'ctrlKey', 'shiftKey', 'altKey']) {
            expect(isModified({ button: 0, [key]: true })).toBe(true);
        }
        expect(isModified({ button: 0, defaultPrevented: true })).toBe(true);
        expect(isModified(null)).toBe(false);
    });
});

describe('d2TeamCta', () => {
    beforeEach(() => prefill.mockClear());

    it('registers the request button (the phone rail is <x-d2.ui.carousel> now)', () => {
        const components = registry();
        expect(Object.keys(components)).toEqual(['d2TeamCta']);
    });

    it('hands the consultant to the wizard on a plain click', () => {
        const cta = registry().d2TeamCta();
        cta.$root = { dataset: { consultant: 'team-sarah' } };
        const event = { button: 0, defaultPrevented: false, preventDefault: vi.fn() };
        cta.request(event);
        expect(event.preventDefault).toHaveBeenCalledOnce();
        expect(prefill).toHaveBeenCalledWith({ consultant: 'team-sarah' });
    });

    it('lets a modified click follow the link (new tab)', () => {
        const cta = registry().d2TeamCta();
        cta.$root = { dataset: { consultant: 'team-sarah' } };
        const event = { button: 0, metaKey: true, preventDefault: vi.fn() };
        cta.request(event);
        expect(event.preventDefault).not.toHaveBeenCalled();
        expect(prefill).not.toHaveBeenCalled();
    });

    it('follows the link when the code is not valid', () => {
        const cta = registry().d2TeamCta();
        cta.$root = { dataset: { consultant: 'someone' } };
        const event = { button: 0, preventDefault: vi.fn() };
        cta.request(event);
        expect(event.preventDefault).not.toHaveBeenCalled();
        expect(prefill).not.toHaveBeenCalled();
    });
});
