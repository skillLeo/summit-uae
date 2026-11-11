/**
 * Document upload: pure browser-side checks for <x-d2.upload.dropzone> (docs/enquiries.md §6). They mirror
 * App\Http\Requests\StoreEnquiryRequest::attachmentError() so a file that is certain to fail is caught
 * before the request; the server stays the authority (it checks again and sniffs the content itself).
 *
 *   count     more than one file                         → error_count
 *   size      larger than attachment_max_bytes            → error_size
 *             empty (0 bytes) or an unreadable size       → error_rejected
 *   type      PDF, JPEG or PNG as read from the first bytes (never the extension or the declared type)
 *                                                         → error_type; unreadable → error_rejected
 *
 * Error keys are the suffixes of the approved messages ui('form.attachment.error_*'). No DOM here (except
 * attachmentOf, which only reads an input): unit-tested in resources/js/d2/upload/checks.test.js.
 */

/** Sniffed format → the MIME types the server accepts (config('summit.enquiries.attachment_mimes')). */
export const TYPES = {
    pdf: 'application/pdf',
    jpeg: 'image/jpeg',
    png: 'image/png',
};

/** The `accept` hint for the file picker (a hint only: the content decides). */
export const ACCEPT = '.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png';

/** Bytes needed to recognise every accepted format. */
export const SNIFF_BYTES = 8;

export const DEFAULT_MAX_BYTES = 10_000_000;

/**
 * The checks that need no file content: count, then size (the server's order).
 * Returns { error: null | 'error_count' | 'error_size' | 'error_rejected', file: File | null }.
 * An empty list is not an error: { error: null, file: null }.
 */
export function checkFiles(files, { maxBytes = DEFAULT_MAX_BYTES } = {}) {
    const list = Array.from(files ?? []);
    if (list.length === 0) return { error: null, file: null };
    if (list.length > 1) return { error: 'error_count', file: null };
    const file = list[0];
    const size = Number(file?.size);
    const limit = Number(maxBytes) > 0 ? Number(maxBytes) : DEFAULT_MAX_BYTES;
    if (!Number.isFinite(size) || size < 0) return { error: 'error_rejected', file: null };
    if (size > limit) return { error: 'error_size', file: null };
    if (size === 0) return { error: 'error_rejected', file: null };
    return { error: null, file };
}

/** The format of a file from its first bytes: 'pdf' | 'jpeg' | 'png' | null (anything else). */
export function sniffType(bytes) {
    const b = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes ?? []);
    const starts = (signature) => signature.every((value, i) => b[i] === value);
    if (starts([0x25, 0x50, 0x44, 0x46, 0x2d])) return 'pdf'; // %PDF-
    if (starts([0xff, 0xd8, 0xff])) return 'jpeg';
    if (starts([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a])) return 'png';
    return null;
}

/** Read the first bytes of a Blob (File); resolves to a Uint8Array, rejects when the file cannot be read. */
export async function headBytes(file, length = SNIFF_BYTES) {
    const slice = file.slice(0, length);
    if (typeof slice.arrayBuffer === 'function') return new Uint8Array(await slice.arrayBuffer());
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(new Uint8Array(reader.result));
        reader.onerror = () => reject(reader.error);
        reader.readAsArrayBuffer(slice);
    });
}

/**
 * Every check in the server's order. Resolves to { error, file, type } where type is the sniffed
 * format ('pdf' | 'jpeg' | 'png') of an accepted file. `read` is injectable for tests.
 */
export async function checkUpload(files, { maxBytes = DEFAULT_MAX_BYTES, read = headBytes } = {}) {
    const first = checkFiles(files, { maxBytes });
    if (first.error || !first.file) return { ...first, type: null };
    let type = null;
    try {
        type = sniffType(await read(first.file, SNIFF_BYTES));
    } catch {
        return { error: 'error_rejected', file: null, type: null };
    }
    if (!type) return { error: 'error_type', file: null, type: null };
    return { error: null, file: first.file, type };
}

/**
 * A file size for the chip, in decimal units like the approved "up to 10 MB": under 1 MB in whole KB
 * (at least 1), from 1 MB in MB with one decimal. Returns { unit: 'kb' | 'mb', value } where value is
 * the number formatted for the page language with Western digits (as the approved Arabic copy writes them).
 */
export function formatSize(bytes, locale = 'en') {
    const size = Math.max(0, Number(bytes) || 0);
    const mb = size >= 1_000_000;
    const raw = mb ? Math.round(size / 100_000) / 10 : Math.max(1, Math.round(size / 1000));
    let value = String(raw);
    try {
        value = new Intl.NumberFormat(`${locale}-u-nu-latn`, { maximumFractionDigits: 1 }).format(raw);
    } catch {
        /* unknown locale: the plain number */
    }
    return { unit: mb ? 'mb' : 'kb', value };
}

/** Fill a server template such as "Document attached: :name (:size)" (plain text, no markup). */
export function fill(template, values = {}) {
    return String(template ?? '').replace(/:([a-z]+)/g, (match, key) => (Object.hasOwn(values, key) ? String(values[key]) : match));
}

/**
 * The attachment a form will send, for scripts that build their own FormData (the consultation wizard):
 * the one file of its input[type=file][name="attachment"], or null.
 */
export function attachmentOf(form) {
    const input = form?.querySelector?.('input[type="file"][name="attachment"]');
    const file = input?.files?.[0];
    return file ?? null;
}
