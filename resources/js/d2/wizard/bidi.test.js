import { describe, expect, it } from 'vitest';
import { bidiParts } from './bidi.js';

describe('bidiParts (App\\Design\\D2\\Bidi::isolate in the browser)', () => {
    it('isolates Latin runs in Arabic text', () => {
        expect(bidiParts('رقم المعاملة على الشبكة TxID واسم الشبكة', 'ar')).toEqual([
            { text: 'رقم المعاملة على الشبكة ', latin: false },
            { text: 'TxID', latin: true },
            { text: ' واسم الشبكة', latin: false },
        ]);
    });

    it('keeps multi-word Latin runs together', () => {
        expect(bidiParts('ملف PDF أو JPG', 'ar').filter((p) => p.latin).map((p) => p.text)).toEqual(['PDF', 'JPG']);
        expect(bidiParts('خدمة Summit Legal Consultancy هنا', 'ar').filter((p) => p.latin).map((p) => p.text)).toEqual([
            'Summit Legal Consultancy',
        ]);
    });

    it('leaves other locales and empty text alone', () => {
        expect(bidiParts('The TxID and wallet addresses.', 'en')).toEqual([{ text: 'The TxID and wallet addresses.', latin: false }]);
        expect(bidiParts('', 'ar')).toEqual([]);
        expect(bidiParts(null, 'ar')).toEqual([]);
    });
});
