// Summit Legal Consultancy: Prototype 1 "The File".
// Alpine (CSP build, no eval) holds UI state; small modules handle the page chrome.
import Alpine from '@alpinejs/csp';
import { disclosure } from './components/disclosure';
import { matterNavigator } from './components/navigator';
import { consultationForm } from './components/consultation-form';
import { whoScroller } from './components/who-scroller';
import { guideSearch } from './components/guide-search';
import { initSheets } from './modules/sheets';
import { initHero } from './modules/hero';
import { initMethod } from './modules/method';
import { initChrome } from './modules/chrome';

Alpine.data('disclosure', disclosure);
Alpine.data('navigator', matterNavigator);
Alpine.data('consultationForm', consultationForm);
Alpine.data('whoScroller', whoScroller);
Alpine.data('guideSearch', guideSearch);

initHero();
Alpine.start();
initSheets();
initMethod();
initChrome();

// Offline support (enabled by config; network-first, never caches form data).
if ('serviceWorker' in window.navigator && document.documentElement.hasAttribute('data-pwa')) {
    window.addEventListener('load', () => window.navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
