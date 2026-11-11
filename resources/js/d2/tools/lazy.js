/**
 * Load a tool's page script only when the tool is about to be used: when its section comes within
 * ~1.5 screens of the viewport, or as soon as the visitor focuses or touches it (keyboard users who tab
 * in, a link that jumps there). The calculators therefore never weigh on the first paint of the home
 * page (engineering §5 budgets); the server-rendered fields work before and without it.
 *
 *   whenNear(root, () => import('./gratuity-view.js'))   // the module's mount(root) is then called once
 */
export function whenNear(root, load) {
    let done = false;
    let observer = null;
    const start = () => {
        if (done) return;
        done = true;
        observer?.disconnect();
        root.removeEventListener('focusin', start);
        root.removeEventListener('pointerdown', start);
        load()
            .then((module) => module.mount(root))
            .catch((error) => {
                // The fields and the consultation link keep working; only the live estimate is missing.
                console.error(error);
            });
    };
    root.addEventListener('focusin', start);
    root.addEventListener('pointerdown', start);
    if (!('IntersectionObserver' in window)) {
        start();
        return;
    }
    // Observe the section (a hidden panel never intersects: the rent tool loads with its section).
    const target = root.closest('section') ?? root;
    observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) start();
    }, { rootMargin: '150% 0px' });
    observer.observe(target);
}
