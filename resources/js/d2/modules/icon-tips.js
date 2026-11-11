/**
 * WCAG 1.4.13 for the icon button's CSS tooltip (<x-d2.ui.icon-button>, .icon-btn__tip): Escape hides a
 * visible tip without moving hover or focus. The tip may show again once the pointer has left the
 * button and the button has lost focus. Escape is not swallowed, so the same key still closes an open
 * sheet, modal or menu. Tooltips built with <x-d2.ui.tooltip> are handled by modules/tooltip.js.
 */
const DISMISSED = 'is-tip-dismissed';

export default function iconTips() {
    const dismissed = new Set();

    document.addEventListener(
        'keydown',
        (event) => {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('.icon-btn:is(:hover, :focus-visible) .icon-btn__tip').forEach((tip) => {
                const button = tip.closest('.icon-btn');
                if (!button || window.getComputedStyle(tip).display === 'none') return;
                button.classList.add(DISMISSED);
                dismissed.add(button);
            });
        },
        true,
    );

    const release = () => {
        if (dismissed.size === 0) return;
        window.requestAnimationFrame(() => {
            for (const button of dismissed) {
                if (!button.isConnected || !button.matches(':hover, :focus-visible')) {
                    button.classList.remove(DISMISSED);
                    dismissed.delete(button);
                }
            }
        });
    };

    document.addEventListener('pointerout', release, { passive: true });
    document.addEventListener('focusout', release);
}
