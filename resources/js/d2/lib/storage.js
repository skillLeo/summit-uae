/** localStorage that never throws (private mode, blocked storage, quota). */
export function read(key, fallback = null) {
    try {
        const raw = window.localStorage.getItem(key);
        return raw === null ? fallback : JSON.parse(raw);
    } catch {
        return fallback;
    }
}

export function write(key, value) {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
        return true;
    } catch {
        return false;
    }
}

export function remove(key) {
    try {
        window.localStorage.removeItem(key);
    } catch {
        /* ignore */
    }
}
