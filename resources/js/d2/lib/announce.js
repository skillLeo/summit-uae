/** Polite screen-reader announcement through the layout's single live region ([data-announcer]). */
export function announce(message) {
    const region = document.querySelector('[data-announcer]');
    if (!region || !message) return;
    region.textContent = '';
    // A new text node after a frame is announced even when the message repeats.
    window.requestAnimationFrame(() => {
        region.textContent = message;
    });
}
