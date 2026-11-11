/**
 * Latin runs inside Arabic text (TxID, USDT, PDF …) get their own <bdi dir="ltr">, exactly like
 * App\Design\D2\Bidi::isolate() does on the server, for approved text the wizard writes at run time
 * (the documents of the chosen service). Other locales get plain text.
 */
const LATIN_RUN = /([A-Za-z][A-Za-z0-9./-]*(?:[ \t]+[A-Za-z0-9][A-Za-z0-9./-]*)*)/u;

/** [{ text, latin }] pieces of `text` (pure; unit-tested in bidi.test.js). */
export function bidiParts(text, locale) {
    const value = String(text ?? '');
    if (value === '' || locale !== 'ar') return value === '' ? [] : [{ text: value, latin: false }];
    return value
        .split(LATIN_RUN)
        .map((part, i) => ({ text: part, latin: i % 2 === 1 }))
        .filter((part) => part.text !== '');
}

/** Replace el's content with the text, Latin runs isolated (text nodes only, never HTML). */
export function setBidiText(el, text, locale) {
    const nodes = bidiParts(text, locale).map((part) => {
        if (!part.latin) return document.createTextNode(part.text);
        const bdi = document.createElement('bdi');
        bdi.dir = 'ltr';
        bdi.textContent = part.text;
        return bdi;
    });
    el.replaceChildren(...nodes);
}
