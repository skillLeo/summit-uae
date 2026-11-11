import { describe, expect, it } from 'vitest';
import { attachmentOf, checkFiles, checkUpload, fill, formatSize, sniffType, TYPES } from '../components/upload-checks.js';

const PDF = [0x25, 0x50, 0x44, 0x46, 0x2d, 0x31, 0x2e, 0x37];
const JPEG = [0xff, 0xd8, 0xff, 0xe0, 0x00, 0x10, 0x4a, 0x46];
const PNG = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];
const GIF = [0x47, 0x49, 0x46, 0x38, 0x39, 0x61, 0x01, 0x00];

/** A File-like object: name, size and the bytes a read() returns. */
const fake = (name, size, bytes = PDF) => ({ name, size, bytes: new Uint8Array(bytes) });
const read = async (file) => file.bytes;

describe('checkFiles (count, then size: StoreEnquiryRequest order)', () => {
    it('accepts no file at all (the attachment is optional)', () => {
        expect(checkFiles([])).toEqual({ error: null, file: null });
        expect(checkFiles(null)).toEqual({ error: null, file: null });
    });

    it('rejects more than one file with error_count, whatever their size', () => {
        expect(checkFiles([fake('a.pdf', 10), fake('b.pdf', 20)]).error).toBe('error_count');
        expect(checkFiles([fake('a.pdf', 20_000_000), fake('b.pdf', 0)]).error).toBe('error_count');
    });

    it('rejects a file over the limit with error_size and keeps exactly the limit', () => {
        expect(checkFiles([fake('big.pdf', 10_000_001)]).error).toBe('error_size');
        expect(checkFiles([fake('max.pdf', 10_000_000)]).error).toBeNull();
        expect(checkFiles([fake('small.pdf', 600)], { maxBytes: 500 }).error).toBe('error_size');
    });

    it('rejects an empty or unreadable size with error_rejected', () => {
        expect(checkFiles([fake('empty.pdf', 0)]).error).toBe('error_rejected');
        expect(checkFiles([fake('odd.pdf', Number.NaN)]).error).toBe('error_rejected');
        expect(checkFiles([fake('odd.pdf', -1)]).error).toBe('error_rejected');
    });

    it('falls back to the 10 MB default for a missing or broken limit', () => {
        expect(checkFiles([fake('a.pdf', 9_999_999)], { maxBytes: 0 }).error).toBeNull();
        expect(checkFiles([fake('a.pdf', 10_000_001)], { maxBytes: 'x' }).error).toBe('error_size');
    });
});

describe('sniffType (content, never the extension)', () => {
    it('recognises PDF, JPEG and PNG signatures', () => {
        expect(sniffType(new Uint8Array(PDF))).toBe('pdf');
        expect(sniffType(new Uint8Array(JPEG))).toBe('jpeg');
        expect(sniffType(new Uint8Array(PNG))).toBe('png');
        expect(sniffType(new Uint8Array(PDF).buffer)).toBe('pdf');
    });

    it('returns null for other formats, short or empty input', () => {
        expect(sniffType(new Uint8Array(GIF))).toBeNull();
        expect(sniffType(new Uint8Array([0x25, 0x50, 0x44]))).toBeNull();
        expect(sniffType(new Uint8Array([]))).toBeNull();
        expect(sniffType(undefined)).toBeNull();
    });

    it('maps every sniffed format to a MIME type the server accepts', () => {
        expect(Object.values(TYPES)).toEqual(['application/pdf', 'image/jpeg', 'image/png']);
    });
});

describe('checkUpload (the full check in server order)', () => {
    it('accepts a real PDF, JPEG and PNG and reports the sniffed type', async () => {
        await expect(checkUpload([fake('a.pdf', 1200, PDF)], { read })).resolves.toMatchObject({ error: null, type: 'pdf' });
        await expect(checkUpload([fake('photo.JPG', 1200, JPEG)], { read })).resolves.toMatchObject({ error: null, type: 'jpeg' });
        await expect(checkUpload([fake('scan.png', 1200, PNG)], { read })).resolves.toMatchObject({ error: null, type: 'png' });
    });

    it('rejects a renamed file by its content with error_type', async () => {
        const result = await checkUpload([fake('contract.pdf', 1200, GIF)], { read });
        expect(result).toEqual({ error: 'error_type', file: null, type: null });
    });

    it('reports a file that cannot be read as error_rejected', async () => {
        const broken = async () => {
            throw new Error('NotReadableError');
        };
        await expect(checkUpload([fake('gone.pdf', 1200)], { read: broken })).resolves.toMatchObject({ error: 'error_rejected' });
    });

    it('checks count and size before reading the content', async () => {
        let reads = 0;
        const counting = async (file) => {
            reads += 1;
            return file.bytes;
        };
        await expect(checkUpload([fake('a.pdf', 10), fake('b.pdf', 10)], { read: counting })).resolves.toMatchObject({ error: 'error_count' });
        await expect(checkUpload([fake('big.pdf', 10_000_001, GIF)], { read: counting })).resolves.toMatchObject({ error: 'error_size' });
        await expect(checkUpload([], { read: counting })).resolves.toEqual({ error: null, file: null, type: null });
        expect(reads).toBe(0);
    });
});

describe('formatSize (decimal units, Western digits)', () => {
    it('uses whole KB under 1 MB, never 0', () => {
        expect(formatSize(1, 'en')).toEqual({ unit: 'kb', value: '1' });
        expect(formatSize(84_400, 'en')).toEqual({ unit: 'kb', value: '84' });
        expect(formatSize(999_499, 'en')).toEqual({ unit: 'kb', value: '999' });
    });

    it('uses MB with one decimal from 1 MB', () => {
        expect(formatSize(1_000_000, 'en')).toEqual({ unit: 'mb', value: '1' });
        expect(formatSize(2_440_000, 'en')).toEqual({ unit: 'mb', value: '2.4' });
        expect(formatSize(10_000_000, 'en')).toEqual({ unit: 'mb', value: '10' });
    });

    it('writes the decimal separator of the page language with Western digits', () => {
        expect(formatSize(2_440_000, 'ru').value).toBe('2,4');
        expect(formatSize(2_440_000, 'ar').value).toMatch(/^2[.٫]4$/);
        expect(formatSize(2_440_000, 'ar').value).not.toMatch(/[٠-٩]/);
    });
});

describe('fill and attachmentOf', () => {
    it('fills :placeholders with plain text and keeps unknown ones', () => {
        expect(fill('Document attached: :name (:size)', { name: 'a.pdf', size: '2.4 MB' })).toBe('Document attached: a.pdf (2.4 MB)');
        expect(fill(':size MB', {})).toBe(':size MB');
        expect(fill(undefined, {})).toBe('');
    });

    it('returns the one attachment of a form, or null', () => {
        const file = { name: 'a.pdf' };
        const form = { querySelector: (selector) => (selector.includes('name="attachment"') ? { files: [file] } : null) };
        expect(attachmentOf(form)).toBe(file);
        expect(attachmentOf({ querySelector: () => ({ files: [] }) })).toBeNull();
        expect(attachmentOf({ querySelector: () => null })).toBeNull();
        expect(attachmentOf(null)).toBeNull();
    });
});
