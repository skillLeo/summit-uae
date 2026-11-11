/** User and device preferences. Read them at use time: they can change while the page is open. */
export const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
export const finePointer = () => window.matchMedia('(pointer: fine)').matches;
export const isMobile = () => window.matchMedia('(width < 768px)').matches;
export const scrollBehavior = () => (reducedMotion() ? 'auto' : 'smooth');
