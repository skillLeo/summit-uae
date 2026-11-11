@props([
    'current' => null,   // navCurrent: home | practice | resources | cases | faq | about | contact
    'static' => false,   // styleguide specimen: drawn in place, inert, never fixed or hidden
    'fab' => true,       // the floating WhatsApp button above the bar (a static specimen draws it in place)
])
{{--
    Design 2 mobile tab bar (below 768 px; brief 2.1 §6 "Mobile"). Styles: resources/css/d2/components/tabbar.css,
    behaviour (hide on scroll down, show on scroll up, never while a sheet is open or focus is inside it, out of the
    way of the on-screen keyboard; the WhatsApp button follows it): resources/js/d2/modules/tabbar.js.

    Four cells, the approved navigation labels of the homepage DOCX header in its order (ui(), never shortened or
    reworded; a long one is set on two lines between its words, see $lines):
    Home, Practice Areas (opens the services sheet, as the header's Practice Areas opens the mega menu; a plain link
    to the Practice Areas page without JavaScript), Legal Resources, Contact Us. The menu is the header's menu button
    (it opens the menu sheet with the seven labels, the language sheet and the contact rows); the Design 2.0 Tools,
    Request and Menu cells are gone. No invented short label.
    Current: the matching item only (aria-current "page" on the page itself, "true" on a service page under Practice
    Areas): ink, weight 600 and a 2 px ink mark under the label (.tabbar__mark, a real element so the page change
    can slide it from the old item to the new one: view-transition-name d2-tab-mark, base/motion.css). Case Studies,
    FAQs, About and the legal pages have no cell, so nothing is current there.
    Text-only cells (brief §3.6 rule 5: no icon on a navigation item).

    The floating WhatsApp button (brief §5.2, §6): the approved WA01 draft for the page language
    (setting('whatsapp_url'): a new tab, rel="noopener noreferrer", never sent), the official mark
    (<x-d2.ui.brand-mark> through the whatsapp icon), the approved "Contact Us on WhatsApp" as its accessible name.
    It floats above the bar at the inline end (safe areas included) and steps aside while another WhatsApp control
    is on screen (the hero, the contact block), while the footer or the consultation form is on screen (it would
    float over their controls), while a sheet or dialog is open and while the visitor types. Phones only, like the bar.
    The sheet opener gets aria-haspopup / aria-controls / aria-expanded from lib/sheet.js, only when the sheet can
    open.
--}}
@php
    use App\Support\SiteUrl;

    $practiceHref = SiteUrl::page('P02');
    $path = rtrim(request()->getPathInfo(), '/');
    $samePage = fn (string $href) => rtrim((string) parse_url($href, PHP_URL_PATH), '/') === $path;

    // The four approved labels (01_Homepage DOCX header, H-L001, H-L002, H-L003, H-L007), in the DOCX order.
    $items = [
        ['key' => 'home', 'label' => ui('nav.home'), 'href' => SiteUrl::home(), 'sheet' => null],
        ['key' => 'practice', 'label' => ui('nav.practice_areas'), 'href' => $practiceHref, 'sheet' => 'services'],
        ['key' => 'resources', 'label' => ui('nav.resources'), 'href' => SiteUrl::page('P03'), 'sheet' => null],
        ['key' => 'contact', 'label' => ui('nav.contact'), 'href' => SiteUrl::contact(), 'sheet' => null],
    ];
    // "page" on the page itself; "true" on its sub-pages (a service page is under Practice Areas).
    $currentAttr = fn (array $item) => $current === $item['key'] ? ($static || $samePage($item['href']) ? 'page' : 'true') : null;

    // Line breaks decided here, not by the font: a label of up to 10 characters stays on one line; a longer one is
    // set on two lines split between words where the longer line is shortest ("Practice / Areas", «Связаться / с
    // нами»). With the browser's own wrapping, a label that fits one line in the metric-matched fallback font but not
    // in the web font (Arabic «مناطق الممارسة» at 390 px) re-wrapped when the font arrived: a layout shift in the first
    // screen of every page. The text and its words are the approved label, unchanged.
    $lines = function (string $label): array {
        $words = preg_split('/\s+/u', trim($label)) ?: [];
        if (count($words) < 2 || mb_strlen($label) <= 10) {
            return [trim($label)];
        }
        $best = null;
        for ($k = 1; $k < count($words); $k++) {
            $pair = [implode(' ', array_slice($words, 0, $k)), implode(' ', array_slice($words, $k))];
            $longest = max(mb_strlen($pair[0]), mb_strlen($pair[1]));
            if ($best === null || $longest < $best[0]) {
                $best = [$longest, $pair];
            }
        }

        return $best[1];
    };
@endphp
<nav {{ $attributes->class(['tabbar', 'tabbar--static' => $static]) }} aria-label="{{ proposed('nav.quick') }}" @proposed
    @if ($static) inert aria-hidden="true" @else data-tabbar @endif>
    <ul class="tabbar__list" role="list">
        @foreach ($items as $item)
            @php $aria = $currentAttr($item); @endphp
            <li class="tabbar__cell">
                <a class="tabbar__item" href="{{ $item['href'] }}" data-tabbar-item="{{ $item['key'] }}"
                    @if ($item['sheet']) data-sheet-open="{{ $item['sheet'] }}" @endif
                    @if ($aria) aria-current="{{ $aria }}" @endif>
                    <span class="tabbar__label">@foreach ($lines($item['label']) as $i => $line)@if ($i > 0) @endif<span class="tabbar__line">{{ $line }}</span>@endforeach @if ($aria)<span class="tabbar__mark" aria-hidden="true"></span>@endif</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
@if ($fab)
    {{-- Not inside the navigation landmark: it is a contact action, not a page. --}}
    {{-- .is-away until modules/tabbar.js has looked at the page (no flash of the button over the hero's own WhatsApp
         button); without JavaScript it shows (tabbar.css). --}}
    <a @class(['btn', 'btn--secondary', 'btn--48', 'icon-btn', 'wa-fab', 'wa-fab--static' => $static, 'is-away' => ! $static])
        href="{{ setting('whatsapp_url') }}" target="_blank" rel="noopener noreferrer"
        aria-label="{{ ui('cta.whatsapp') }} {{ d2p('controls.new_tab') }}"
        @if ($static) inert aria-hidden="true" tabindex="-1" @else data-wa-fab @endif>
        <span class="btn__icon wa-fab__icon" aria-hidden="true"><x-d2.ui.brand-mark size="24" /></span>
    </a>
@endif
