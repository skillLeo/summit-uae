(function (d, w) {
    var root = d.querySelector('[data-design-switch]');
    if (!root) return;
    function go(number) {
        var link = root.querySelector('[data-design-option="' + number + '"]');
        if (!link || link.getAttribute('aria-current') === 'true') return;
        // Same page, query and language; the #fragment survives the server's redirect.
        w.location.assign(link.href.split('#')[0] + w.location.hash);
    }
    root.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('[data-design-option]') : null;
        if (!link || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        go(link.getAttribute('data-design-option'));
    });
    d.addEventListener('keydown', function (e) {
        if (!e.altKey || e.ctrlKey || e.metaKey || e.shiftKey || e.defaultPrevented) return;
        var n = e.code === 'Digit1' || e.code === 'Numpad1' ? '1' : (e.code === 'Digit2' || e.code === 'Numpad2' ? '2' : null);
        if (!n) return;
        var t = e.target;
        if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
        e.preventDefault();
        go(n);
    });
})(document, window);
