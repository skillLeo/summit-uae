{{--
    Google Analytics 4 (docs/briefs/design2-only-black-header-ga4.md Part C), in the <head> of d2::layouts.app and the
    root chooser: @include('d2::partials.analytics', ['pageKey' => …]).
    Printed only when App\Support\Analytics::tag() returns a measurement ID: production, a valid ID (Settings,
    Integrations, else GA4_MEASUREMENT_ID), a public page (no admin, CMS preview, error, offline or styleguide page),
    nobody signed in. Otherwise nothing at all, and the CSP stays closed to Google.

    Consent Mode v2: everything starts denied. A visitor who accepted before (first-party cookie summit_consent=granted,
    6 months, written by resources/js/d2/modules/analytics.js) is granted analytics storage here, before the first
    hit; ad storage, ad user data and ad personalisation stay denied always, Google signals and ad personalisation
    signals are off and ad identifiers are redacted. page_location is the address without its query and #hash (only
    the utm_* campaign fields are kept: they are set by whoever wrote the link, never typed by the visitor);
    page_referrer likewise without its query. The ID is validated (^G-[A-Z0-9]{6,12}$) and escaped.
    The consent bar: d2::partials.consent; the events: resources/js/d2/modules/analytics.js.
--}}
@php $gaId = \App\Support\Analytics::tag($pageKey ?? null); @endphp
@if ($gaId !== null)
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}" nonce="{{ Vite::cspNonce() }}"></script>
<script nonce="{{ Vite::cspNonce() }}">
window.dataLayer = window.dataLayer || [];
function gtag() { dataLayer.push(arguments); }
gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
if (/(?:^|;\s*)summit_consent=granted(?:;|$)/.test(document.cookie)) { gtag('consent', 'update', { analytics_storage: 'granted' }); }
gtag('set', 'ads_data_redaction', true);
gtag('js', new Date());
(function (id, campaign) {
    var clean = function (href, keep) {
        try {
            var url = new URL(href), query = new URLSearchParams();
            keep.forEach(function (key) { if (url.searchParams.has(key)) { query.set(key, url.searchParams.get(key)); } });
            query = query.toString();
            return url.origin + url.pathname + (query ? '?' + query : '');
        } catch (e) { return ''; }
    };
    gtag('config', id, {
        page_location: clean(location.href, campaign),
        page_referrer: clean(document.referrer, []),
        allow_google_signals: false,
        allow_ad_personalization_signals: false
    });
})(@json($gaId), ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id']);
</script>
@endif
