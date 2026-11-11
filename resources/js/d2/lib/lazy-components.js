/**
 * Which Alpine components a page needs (brief 2.1 §6 "Performance": per page at most 100 KB of JS gzip).
 * resources/js/d2/app.js registers the shell's components at once and loads every other component file (its own
 * chunk) before Alpine starts, only when the page uses one of the x-data names it registers. The names come from
 * the virtual module of vite.d2.config.js (d2ComponentNames: read from the sources, never a hand-kept list).
 *
 *   usedNames(document)                       x-data names in the page, <template> contents included
 *   wantedFiles(lazyFiles, map, used)         the component files to load
 *
 * Safety: a used name that no component file declares (a component added while the dev server ran, a name the
 * scan missed) makes it load every component, as before the split: a page is never left with a dead component.
 */

const NAME = /^\s*([A-Za-z_$][\w$]*)/;

/** The x-data names used in a document or fragment, including the contents of its <template> elements. */
export function usedNames(root) {
    const names = new Set();
    const visit = (scope) => {
        if (!scope || typeof scope.querySelectorAll !== 'function') return;
        for (const el of scope.querySelectorAll('[x-data]')) {
            const match = NAME.exec(el.getAttribute('x-data') || '');
            if (match) names.add(match[1]);
        }
        for (const template of scope.querySelectorAll('template')) visit(template.content);
    };
    visit(root);
    return names;
}

/**
 * The lazy component files a page needs, in a stable order: every file that registers a used name, plus the files
 * that register none but run page wiring (always). `lazyFiles` are the loaders' keys ('./components/faq.js'),
 * `map` the virtual module ({ file: { names, always } }), `used` the names in the page. Names registered by an
 * eager (shell) file need nothing. A used name that the map does not know: every lazy file.
 */
export function wantedFiles(lazyFiles, map, used) {
    const files = Array.isArray(lazyFiles) ? lazyFiles : [];
    const table = map && typeof map === 'object' ? map : {};
    const owner = new Map();
    for (const [file, entry] of Object.entries(table)) {
        for (const name of entry?.names ?? []) owner.set(name, file);
    }
    const wanted = new Set(files.filter((file) => table[file]?.always));
    for (const name of used ?? []) {
        const file = owner.get(name);
        if (file === undefined) return [...files];
        if (files.includes(file)) wanted.add(file);
    }
    return files.filter((file) => wanted.has(file));
}
