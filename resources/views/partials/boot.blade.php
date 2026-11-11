{{--
    Runs before first paint (no layout shift):
    - swaps no-js → js;
    - arms the one-time hero intro (home only, motion allowed, once per session);
    - reveals the matching language-suggestion bar when the browser prefers another
      supported language. It never redirects, and stays off once the visitor chose a
      language or dismissed the bar (12-month cookies).
--}}
<script nonce="{{ Vite::cspNonce() }}">
(function (d, w) {
    var h = d.documentElement;
    h.classList.remove('no-js');
    h.classList.add('js');
    try {
        if (h.getAttribute('data-page') === 'home'
            && !w.matchMedia('(prefers-reduced-motion: reduce)').matches
            && !w.sessionStorage.getItem('summit.intro')) {
            h.classList.add('hero-intro');
            w.setTimeout(function () { h.classList.remove('hero-intro'); }, 2600);
        }
    } catch (e) {}
    @if (config('summit.features.language_suggestion'))
    try {
        var cookies = d.cookie;
        if (!/(?:^|;\s*)summit_suggest_off=1/.test(cookies) && !/(?:^|;\s*){{ config('summit.locale_cookie') }}=/.test(cookies)) {
            var supported = @json(array_keys(config('summit.locales')));
            var prefs = w.navigator.languages || [w.navigator.language || ''];
            for (var i = 0; i < prefs.length; i++) {
                var code = String(prefs[i] || '').slice(0, 2).toLowerCase();
                if (supported.indexOf(code) > -1) {
                    if (code !== h.lang) { h.setAttribute('data-suggest', code); }
                    break;
                }
            }
        }
    } catch (e) {}
    @endif
})(document, window);
</script>
