<?php

namespace Tests\Feature\Design\D2;

use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The header (docs/briefs/design-2.1-and-admin-cms.md §3.7, §3.8; D06, D12, D13, D48; the client's final changes,
 * items 1 and 18, 2026-11-25): black, holding only the official lockup, the seven approved labels in the DOCX order
 * and the language menu (a flag and each language's own name; no button, no search, no WhatsApp, no subtitle); the
 * text-only mega menu over its scrim; the retired night glass and metal.
 */
final class HeaderTest extends TestCase
{
    use RefreshDatabase;

    private const NAV_KEYS = ['nav.home', 'nav.practice_areas', 'nav.resources', 'nav.case_studies', 'nav.faq', 'nav.about', 'nav.contact'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    /** @return array<string, array{string}> */
    public static function locales(): array
    {
        return ['en' => ['en'], 'ar' => ['ar'], 'ru' => ['ru']];
    }

    private function visit(string $path): string
    {
        $html = $this->get($path)->assertOk()->getContent();
        app()->setLocale(explode('/', trim($path, '/'))[0] ?: 'en');

        return $html;
    }

    private function header(string $html): string
    {
        $start = strpos($html, '<header class="d2-header"');
        $this->assertNotFalse($start, 'the Design 2 header');
        $end = strpos($html, '</header>', $start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start + 9);
    }

    private function dom(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    private function one(DOMXPath $x, string $query, ?\DOMNode $context = null): DOMElement
    {
        $nodes = $x->query($query, $context);
        $this->assertNotFalse($nodes);
        $this->assertSame(1, $nodes->length, "exactly one {$query}");
        $node = $nodes->item(0);
        $this->assertInstanceOf(DOMElement::class, $node);

        return $node;
    }

    private function text(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** Item 18: a language's flag is the local SVG (UK, UAE, Russia), decorative: alt="" and aria-hidden. */
    private function assertFlag(DOMElement $img, string $file): void
    {
        $this->assertSame('img', $img->tagName);
        $this->assertSame('flag', $img->getAttribute('class'));
        $this->assertSame(asset("images/d2/flags/{$file}.svg"), $img->getAttribute('src'));
        $this->assertTrue($img->hasAttribute('alt'));
        $this->assertSame('', $img->getAttribute('alt'));
        $this->assertSame('true', $img->getAttribute('aria-hidden'));
        $this->assertFileExists(public_path("images/d2/flags/{$file}.svg"));
    }

    /** brief D13 + the DOCX header note: all seven labels, Home first, in the DOCX order, never shortened or moved into "More". */
    #[DataProvider('locales')]
    public function test_the_seven_approved_labels_in_the_docx_order(string $locale): void
    {
        $x = $this->dom($this->visit("/{$locale}/"));
        $links = $x->query('//header[contains(@class,"d2-header")]//nav[contains(@class,"d2-nav")]/ul/li/a[contains(@class,"d2-nav__link")]');
        $this->assertSame(7, $links->length);
        $hrefs = [SiteUrl::home(), SiteUrl::page('P02'), SiteUrl::page('P03'), SiteUrl::page('P04'), SiteUrl::page('P05'), SiteUrl::page('P06'), SiteUrl::contact()];
        foreach (self::NAV_KEYS as $i => $key) {
            $link = $links->item($i);
            $this->assertInstanceOf(DOMElement::class, $link);
            $this->assertSame(ui($key), $this->text($link->textContent), "{$locale}: label ".($i + 1)." is the approved {$key}");
            $this->assertSame($hrefs[$i], $link->getAttribute('href'));
        }
        $this->assertSame('page', $links->item(0)?->getAttribute('aria-current'), 'Home is the current page on the home page');
        $this->assertSame(proposed('nav.main'), $this->one($x, '//header//nav[contains(@class,"d2-nav")]')->getAttribute('aria-label'));

        $header = $this->header($this->visit("/{$locale}/"));
        $this->assertStringNotContainsString('d2-nav__more', $header, 'no "More" list');
        $this->assertStringNotContainsString('d2-nav__item--from', $header);
        $this->assertStringContainsString('class="container d2-nav"', $header, 'the row sits on the page container');
    }

    /** The current section is marked; a service page marks Practice Areas as its section ("true"). */
    public function test_the_current_page_and_section_are_marked(): void
    {
        $x = $this->dom($this->visit('/en/about/'));
        $this->assertSame('page', $this->one($x, '//header//a[contains(@class,"d2-nav__link")][@href="'.SiteUrl::page('P06').'"]')->getAttribute('aria-current'));
        $this->assertSame(0, $x->query('//header//a[contains(@class,"d2-brand")][@aria-current]')->length, 'the brand is current on the home page only');

        $x = $this->dom($this->visit('/en/practice-areas/labor-law/'));
        $this->assertSame('true', $this->one($x, '//header//a[contains(@class,"d2-nav__toggle")]')->getAttribute('aria-current'));
        $this->assertSame('page', $this->one($x, '//header//a[contains(@class,"megamenu__item")][@data-code="S06"]')->getAttribute('aria-current'));
    }

    /** brief §3.8, D13: the official lockup (not the mark in a black tile), the approved logo alt text as the brand link's name. */
    #[DataProvider('locales')]
    public function test_the_brand_is_the_official_lockup_with_the_approved_name(string $locale): void
    {
        $x = $this->dom($this->visit("/{$locale}/"));
        $brand = $this->one($x, '//header//a[contains(@class,"d2-brand")]');
        $this->assertSame(SiteUrl::home(), $brand->getAttribute('href'), 'the brand links to the home page (P01)');
        $this->assertSame('page', $brand->getAttribute('aria-current'));

        $manifest = json_decode((string) file_get_contents(public_path('images/d2/brand/manifest.json')), true);
        $img = $this->one($x, './/picture[contains(@class,"d2-brand__lockup")]//img', $brand);
        $this->assertSame(ui('brand.logo_alt'), $img->getAttribute('alt'), 'the approved logo alt text names the link');
        $this->assertSame((string) $manifest['files']['lockup@1x.png']['width'], $img->getAttribute('width'));
        $this->assertSame('56', $img->getAttribute('height'));
        $this->assertSame(asset('images/d2/brand/lockup@1x.png'), $img->getAttribute('src'));
        foreach (['1x', '2x', '3x'] as $density) {
            $this->assertStringContainsString(asset("images/d2/brand/lockup@{$density}.png")." {$density}", $img->getAttribute('srcset'));
        }
        $source = $this->one($x, './/picture//source[@type="image/webp"]', $brand);
        $this->assertStringContainsString(asset('images/d2/brand/lockup@3x.webp').' 3x', $source->getAttribute('srcset'));

        // The full lockup always: no shield standing in for it, no subtitle under the name.
        $this->assertSame(0, $x->query('.//*[local-name()="svg"]', $brand)->length, 'no shield alone, ever');
        $this->assertSame(1, $x->query('.//img', $brand)->length, 'the lockup is the link\'s only content');

        $header = $this->header($this->visit("/{$locale}/"));
        $this->assertStringNotContainsString('d2-brand__mark', $header);
        $this->assertStringNotContainsString('d2-brand__tile', $header, 'no mark in a black tile');
        $this->assertStringNotContainsString('d2-brand__name', $header, 'the wordmark is never set in a font');
    }

    /** scripts/d2/brand-lockup.mjs: the trimmed master at 56, 112 and 168 px, WebP and PNG, as its manifest records. */
    public function test_the_lockup_files_are_the_trimmed_master_as_recorded(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('images/d2/brand/manifest.json')), true);
        $this->assertSame('package/brand/logo-04-gold-on-light.png', $manifest['source']);
        $this->assertSame(hash_file('sha256', base_path($manifest['source'])), $manifest['source_sha256'], 'the client master the files come from');
        $this->assertSame(['left' => 710, 'top' => 1728, 'width' => 2820, 'height' => 710], $manifest['trim']);

        foreach (['1x' => 56, '2x' => 112, '3x' => 168] as $density => $height) {
            foreach (['webp' => IMAGETYPE_WEBP, 'png' => IMAGETYPE_PNG] as $ext => $type) {
                $file = "lockup@{$density}.{$ext}";
                $path = public_path("images/d2/brand/{$file}");
                $this->assertFileExists($path);
                $size = getimagesize($path);
                $this->assertNotFalse($size);
                $this->assertSame($type, $size[2], "{$file} format");
                $this->assertSame($height, $size[1], "{$file} height");
                $this->assertSame((int) round(2820 * $height / 710), $size[0], "{$file} keeps the master's aspect ratio");
                $this->assertSame($manifest['files'][$file]['sha256'], hash_file('sha256', $path), "{$file} matches the manifest");
            }
        }
        $this->assertFileExists(base_path('scripts/d2/brand-lockup.mjs'));
    }

    /**
     * The client's final changes, item 1: the header holds the logo and company name (the lockup), the main
     * navigation and the language menu only. No consultation button, no WhatsApp, no search, no phone or e-mail.
     */
    #[DataProvider('locales')]
    public function test_the_header_holds_only_the_lockup_the_navigation_and_the_language_menu(string $locale): void
    {
        $html = $this->visit("/{$locale}/");
        $x = $this->dom($html);
        // The bar itself (the masthead) and the navigation row's own links; the mega menu panel under Practice Areas
        // is a page of its own and keeps its "Explore This Service" buttons.
        $mast = $this->one($x, '//header//div[contains(@class,"d2-header__mast")]');
        $bar = (string) $mast->ownerDocument?->saveHTML($mast);
        foreach (['d2-header__cta', 'btn--ink', 'btn--gold', 'btn--signature', 'btn--secondary', 'btn--primary', 'data-d2-search', 'd2-search', 'wa.me', 'whatsapp', 'tel:', 'mailto:'] as $gone) {
            $this->assertStringNotContainsStringIgnoringCase($gone, $bar, "{$locale}: no {$gone} in the header bar");
        }
        $this->assertStringNotContainsString(ui('cta.request_consultation'), $bar, 'no consultation button');
        $rowLinks = $x->query('//header//ul[contains(@class,"d2-nav__list")]/li/*[self::a or self::button]');
        $this->assertSame(7, $rowLinks->length, 'the seven navigation labels and nothing else in the row');
        foreach (['wa.me', 'tel:', 'mailto:'] as $gone) {
            $this->assertStringNotContainsString($gone, $this->header($html), "{$locale}: no {$gone} anywhere in the header");
        }

        // The tools: the language menu and the one menu button (shown where the seven labels do not).
        $tools = $this->one($x, '//header//div[contains(@class,"d2-header__tools")]');
        $children = [];
        foreach ($tools->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $children[] = $node->getAttribute('class');
            }
        }
        $this->assertCount(2, $children, 'the language menu and the menu button only');
        $this->assertStringContainsString('langmenu', $children[0]);
        $this->assertStringContainsString('d2-header__menu--end', $children[1]);

        // "/" and Ctrl/Cmd+K still open the search palette; without it they lead to Legal Resources.
        $root = $this->one($x, '//header[contains(@class,"d2-header")]');
        $this->assertSame(SiteUrl::page('P03'), $root->getAttribute('data-search-url'));
    }

    /**
     * Where the seven labels do not show (or tuck away once scrolled), the one menu button at the end of the bar
     * (.d2-header__menu: the QA matrix and the menu sheet find it by that class). It opens the menu sheet and is a
     * link to the footer navigation without JavaScript.
     */
    #[DataProvider('locales')]
    public function test_the_menu_button(string $locale): void
    {
        $x = $this->dom($this->visit("/{$locale}/"));
        $menu = $this->one($x, '//header//a[contains(concat(" ", normalize-space(@class), " "), " d2-header__menu ")]');
        $this->assertStringContainsString('d2-header__menu--end', $menu->getAttribute('class'));
        $this->assertSame(0, $x->query('//header//*[contains(@class,"d2-header__compact-menu")]')->length);
        $this->assertSame('#d2-footer-nav', $menu->getAttribute('href'));
        $this->assertSame('menu', $menu->getAttribute('data-sheet-open'));
        $this->assertSame(ui('a11y.open_menu'), $menu->getAttribute('aria-label'));
        $this->one($x, './/*[@data-icon="menu"]', $menu);
    }

    /** D06, §3.6 rule 6: the metal-once machinery and the night glass are gone with their hooks. */
    public function test_the_metal_and_night_glass_are_retired(): void
    {
        $this->assertFileDoesNotExist(resource_path('js/d2/modules/metal.js'));
        $boot = (string) file_get_contents(resource_path('views/designs/d2/shell/boot.blade.php'));
        $this->assertStringNotContainsString("classList.add('has-metal-in-view')", $boot);
        $this->assertStringNotContainsString("classList.add('metal-top')", $boot);

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path('css/d2/components/header.css')));
        foreach (['has-metal-in-view', 'metal-top', 'is-night', 'backdrop-filter', '--glass', '--metal', '--shadow-header'] as $gone) {
            $this->assertStringNotContainsString($gone, $css, "header.css: no {$gone}");
        }
        $js = (string) file_get_contents(resource_path('js/d2/modules/header.js'));
        $this->assertStringNotContainsString('is-night', $js);
        $this->assertStringNotContainsString('IntersectionObserver', $js, 'no night-section observer');
    }

    /**
     * brief §3.7, D12; the client's final changes, item 18 (the owner, 2026-11-25): one compact dropdown; the trigger
     * shows the current language's flag and approved name; the list [flag] English, [flag] العربية, [flag] Русский,
     * each name the approved label of its own language; UK, UAE and Russia flags (local SVGs, decorative).
     */
    #[DataProvider('locales')]
    public function test_the_language_menu_shows_a_flag_and_the_name_of_each_language(string $locale): void
    {
        $flags = ['en' => 'gb', 'ar' => 'ae', 'ru' => 'ru'];
        $x = $this->dom($this->visit("/{$locale}/about/"));
        $menu = $this->one($x, '//header//div[contains(@class,"langmenu")][@x-data="d2Langmenu"]');
        $trigger = $this->one($x, './/a[contains(@class,"langmenu__trigger")]', $menu);
        $this->assertSame(ui('a11y.choose_language').' '.ui('lang.'.$locale), $this->text($trigger->textContent), 'name: "Choose language" + the visible label');
        $this->assertSame(ui('lang.'.$locale), $this->text($this->one($x, './/span[@class="langmenu__current"]', $trigger)->textContent));
        $this->assertSame('#d2-footer-languages', $trigger->getAttribute('href'), 'no-JS: the footer language list');
        $this->one($x, './/*[@data-icon="chevron-down"]', $trigger);
        $this->assertFlag($this->one($x, './/img[contains(@class,"flag")]', $trigger), $flags[$locale]);
        $this->assertSame('flag', $this->one($x, './*[not(contains(@class,"sr-only"))][1]', $trigger)->getAttribute('class'), 'the flag before the name');

        $panel = $this->one($x, './/div[contains(@class,"langmenu__panel")]', $menu);
        $this->assertTrue($panel->hasAttribute('data-overlay'));
        $this->assertTrue($panel->hasAttribute('hidden'));
        $options = $x->query('.//a[contains(@class,"langmenu__option")]', $panel);
        $this->assertSame(3, $options->length);
        foreach (['en', 'ar', 'ru'] as $i => $code) {
            $option = $options->item($i);
            $this->assertInstanceOf(DOMElement::class, $option);
            $this->assertSame(ui('lang.'.$code, $code), $this->text($option->textContent), "{$locale}: the approved name of {$code}, in its own language");
            $this->assertSame($code, $option->getAttribute('hreflang'));
            $this->assertSame($code, $option->getAttribute('lang'), 'the name is in its own language');
            $this->assertSame($code === 'ar' ? 'rtl' : 'ltr', $this->one($x, './span[@class="langmenu__name"]', $option)->getAttribute('dir'));
            $this->assertSame($code, $option->getAttribute('data-lang-switch'));
            $this->assertStringEndsWith("/{$code}/about/", $option->getAttribute('href'), 'the translated page');
            $this->assertSame($code === $locale ? 'true' : '', $option->getAttribute('aria-current'));
            $this->assertFlag($this->one($x, './*[1]', $option), $flags[$code]);
        }
        $this->assertSame(0, $x->query('.//*[@data-icon]', $panel)->length, 'no tick or other icon in a menu item');
        $this->assertDoesNotMatchRegularExpression('/[\x{1F1E6}-\x{1F1FF}]/u', $this->header($this->visit("/{$locale}/")), 'no emoji flags');

        $header = $this->header($this->visit("/{$locale}/"));
        $this->assertStringNotContainsString('langmenu__dot', $header, 'no "EN · ع · RU"');
        $this->assertStringNotContainsString(' · ', $header);
    }

    /**
     * D48: text only, the approved labels, a container-wide overlay over the 6 % scrim; no icons, tiles, arrows or
     * eyebrows. QA round 1: every preview (title, full summary, the button directly under it, the documents) fits a
     * 1024 x 768 window under the header, so the panel is the list, then per service the title, the card (summary +
     * button) and the documents; no heading above the list (the toggle names it) and no guides column.
     */
    #[DataProvider('locales')]
    public function test_the_mega_menu_is_text_only(string $locale): void
    {
        $html = $this->visit("/{$locale}/");
        $x = $this->dom($html);
        $panel = $this->one($x, '//header//div[contains(@class,"megamenu")][@id="d2-megamenu"]');
        $this->assertTrue($panel->hasAttribute('data-overlay'));
        $this->assertSame(0, $x->query('.//*[@data-icon]', $panel)->length, 'no icon anywhere in the panel');
        $this->assertSame(0, $x->query('.//*[contains(@class,"t-eyebrow") or contains(@class,"card-icon") or contains(@class,"item-icon")]', $panel)->length);
        $body = $this->one($x, './div[@class="megamenu__body"]', $panel);
        $this->assertSame('body', $body->getAttribute('x-ref'), 'megamenu.js measures the body (the fade when a window is too short)');

        $services = content()->services();
        $list = $this->one($x, './/ul[@class="megamenu__list"]', $panel);
        $this->assertSame(ui('nav.practice_areas'), $list->getAttribute('aria-label'), 'the list carries the approved label of its toggle');
        $items = $x->query('.//a[contains(@class,"megamenu__item")]', $list);
        $this->assertSame(count($services), $items->length);
        foreach ($services as $i => $service) {
            $this->assertSame($this->text($service['title']), $this->text($items->item($i)?->textContent ?? ''));
        }
        $this->assertSame(0, $x->query('.//*[contains(@class,"megamenu__title") or contains(@class,"megamenu__guide") or contains(@class,"megamenu__centre")]', $panel)->length, 'no heading above the list, no guides column');
        $this->assertSame(ui('cta.view_all_practice_areas'), $this->text($this->one($x, './/a[contains(@class,"megamenu__all")]', $panel)->textContent));

        $this->assertSame(count($services), $x->query('./div[@data-mega-preview]', $body)->length, 'one preview per service, server-rendered');
        foreach ($services as $service) {
            $preview = $this->one($x, './div[@data-mega-preview="'.$service['code'].'"]', $body);
            $this->assertSame($service['code'] !== $services[0]['code'], $preview->hasAttribute('hidden'), 'the first service is previewed by default');
            // Order inside the preview: the title, then the card (the full summary, never clamped, and the button
            // directly under it), then the documents heading and the first four documents of the checklist.
            $parts = array_map(fn ($n) => $n instanceof DOMElement ? $n->getAttribute('class') : '', iterator_to_array($x->query('./*', $preview)));
            $this->assertSame(['megamenu__card-title', 'megamenu__col megamenu__col--card', 'megamenu__col megamenu__col--docs'], $parts, "{$service['code']}: title, card, documents");
            $this->assertSame($this->text($service['title']), $this->text($this->one($x, './p[@class="megamenu__card-title"]', $preview)->textContent));
            $card = $this->one($x, './div[contains(@class,"megamenu__col--card")]', $preview);
            $cardParts = array_map(fn ($n) => $n instanceof DOMElement ? $n->getAttribute('class') : '', iterator_to_array($x->query('./*', $card)));
            $this->assertSame('megamenu__summary', $cardParts[0]);
            $this->assertStringContainsString('megamenu__explore', $cardParts[1] ?? '', 'the button directly under the summary');
            $this->assertCount(2, $cardParts);
            $this->assertSame($this->text($service['home_summary']), $this->text($this->one($x, './p[@class="megamenu__summary"]', $card)->textContent), 'the full homepage summary');
            $explore = $this->one($x, './a[contains(@class,"megamenu__explore")]', $card);
            $this->assertStringContainsString('btn--ink', $explore->getAttribute('class'));
            $this->assertSame(ui('cta.explore_service'), $this->text($explore->textContent));
            $this->assertSame(SiteUrl::service($service['code']), $explore->getAttribute('href'));
            $docs = $this->one($x, './div[contains(@class,"megamenu__col--docs")]', $preview);
            $this->assertSame($this->text($service['sections']['documents']['heading']), $this->text($this->one($x, './p[@class="megamenu__docs-title"]', $docs)->textContent));
            $docItems = array_map(fn ($n) => $this->text($n->textContent), iterator_to_array($x->query('./ul[@class="megamenu__docs"]/li', $docs)));
            $this->assertSame(array_map(fn ($d) => $this->text($d), array_slice($service['documents'], 0, 4)), $docItems, "{$service['code']}: the first four documents, whole");
        }

        // The scrim (one, for the live header only), outside the header.
        $this->assertSame(1, substr_count($html, '<div class="d2-scrim" aria-hidden="true"></div>'));
        $this->assertStringNotContainsString('d2-scrim', $this->header($html));
    }

    /** brief 8.7: the header's own strings are proposed keys in all three languages; the retired ones are deleted. */
    public function test_the_proposed_header_strings(): void
    {
        $keys = null;
        foreach (['en', 'ar', 'ru'] as $locale) {
            $file = require lang_path("{$locale}/proposed-d2/shell.php");
            $keys ??= array_keys($file);
            $this->assertSame($keys, array_keys($file), "{$locale}: the same keys");
            $this->assertSame(['search_placeholder', 'search_tip'], array_keys($file), "{$locale}: the header's only proposed strings");
            foreach (['cta_short', 'nav_more', 'mega_guides', 'lang_code_en', 'lang_code_ar', 'lang_code_ru'] as $retired) {
                $this->assertArrayNotHasKey($retired, $file, "{$locale}: {$retired} retired");
            }
            $this->assertNotSame('', trim($file['search_tip']));
        }
        $this->assertSame('Search (/)', (require lang_path('en/proposed-d2/shell.php'))['search_tip']);
        $this->assertNotSame('Search (/)', (require lang_path('ar/proposed-d2/shell.php'))['search_tip'], 'a real Arabic draft');
        $this->assertNotSame('Search (/)', (require lang_path('ru/proposed-d2/shell.php'))['search_tip'], 'a real Russian draft');
    }

    /** The layout contract (header.css): heights from the tokens, sticky shrink, Russian fit, the scrim; overlays 16 + the overlay shadow. */
    public function test_the_header_layout_contract(): void
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path('css/d2/components/header.css')));
        $this->assertStringContainsString('--mast-h: var(--header-h-top);', $css);
        $this->assertStringContainsString('--bar-h: var(--header-h);', $css);
        $this->assertStringContainsString('inset-block-start: calc(var(--bar-h) - var(--mast-h));', $css, 'the masthead shrinks by scrolling');
        $this->assertMatchesRegularExpression('/@container d2-header \(64rem <= width < 69rem\)\s*\{\s*\.d2-header__navrow:lang\(ru\)\s*\{\s*display: none;/', $css, 'Russian: the menu button until the seven fit');
        $this->assertStringContainsString('background: var(--scrim-menu);', $css);
        $this->assertDoesNotMatchRegularExpression('/box-shadow:(?!\s*none)/', $css, 'no shadow on the header');

        // WCAG 2.5.8 and brief §3.5: 44 px targets, 48 px on touch screens (a label, the brand, the rows of both menus).
        $this->assertMatchesRegularExpression('/@media \(pointer: coarse\)\s*\{\s*\.d2-header\s*\{\s*--nav-link-h: var\(--target-touch\);/', $css);
        $this->assertStringContainsString('block-size: var(--nav-link-h);', $css);

        // D12 + the client's item 1: the language menu shows at every width, in every language (only a bar too narrow
        // for the lockup and the tools moves it to the menu sheet: header.js, .is-mast-tight).
        $this->assertDoesNotMatchRegularExpression('/(?<!is-mast-tight )\.d2-header__lang(:lang\(\w+\))?\s*\{\s*display: none;/', $css, 'never hidden by width or language');
        $this->assertMatchesRegularExpression('/\.d2-header\.is-mast-tight \.d2-header__lang\s*\{\s*display: none;/', $css);
        $this->assertStringNotContainsString('d2-header__cta', $css);

        // The client's item 1: black, the gold lockup straight on it, light text, the current page's gold bar, the
        // light gold focus ring; no gradient, glass or shadow.
        $this->assertMatchesRegularExpression('/\.d2-header__mast\s*\{[^}]*background: var\(--dark\);/', $css);
        $this->assertMatchesRegularExpression('/\.d2-header__navrow\s*\{[^}]*background: var\(--dark\);/', $css);
        $this->assertSame(2, substr_count($css, 'background: var(--line-on-dark);'), 'the masthead\'s and the row\'s 1 px line');
        $this->assertMatchesRegularExpression('/\.d2-nav__link\s*\{[^}]*color: var\(--on-dark-2\);/', $css);
        $this->assertMatchesRegularExpression('/&\[aria-current\]::after\s*\{[^}]*background: var\(--gold\);/', $css);
        $this->assertStringContainsString('--focus-color: var(--focus-on-dark);', $css);
        $this->assertMatchesRegularExpression('/\.d2-header \.langmenu__trigger\s*\{\s*color: var\(--on-dark-3\);/', $css);
        $this->assertDoesNotMatchRegularExpression('/gradient|backdrop-filter/', $css);
        $this->assertDoesNotMatchRegularExpression('/d2-brand__mark|is-docked|--brand-w/', $css, 'the shield and its docking spacer are gone');
        // D13 + QA round 1: a 48 px lockup on tablets (the 72 px masthead), 56 px from 1024 px.
        $this->assertStringContainsString('block-size: var(--brand-h);', $css);
        $this->assertMatchesRegularExpression('/@container d2-header \(width >= 48rem\)\s*\{[^@]*\.d2-header__mast\s*\{\s*--brand-h: var\(--s-12\);/', $css);

        // D13 + the client's item 1: the seven labels ride in the bar, at its end before the tools, where header.js
        // measures that they fit beside the lockup (centred on the 88 px masthead at the top of the page, on the 72 px
        // bar once scrolled), click-through except for the labels; otherwise their own row at the top of the page
        // and, once scrolled, tucked away behind the menu button. The lockup eases from 56 to 48 px (a scale; the owner, 2026-11-25).
        $this->assertStringContainsString('translate: 0 calc(-1 * (var(--mast-h) + var(--navrow-h)) / 2);', $css);
        $this->assertStringContainsString('translate: 0 calc(-1 * (var(--navrow-h) + (var(--bar-h) - var(--navrow-h)) / 2));', $css);
        $this->assertMatchesRegularExpression('/\.d2-header\.is-inline\s*\{[^}]*\.d2-header__navrow\s*\{[^}]*z-index: 1;\s*pointer-events: none;\s*background: none;/', $css);
        $this->assertMatchesRegularExpression('/\.d2-nav__list\s*\{\s*justify-content: flex-end;\s*margin-inline-start: 0;\s*&::after\s*\{\s*flex: none;\s*inline-size: calc\(var\(--tools-w\) \+ var\(--s-4\) - var\(--nav-pad\)\);/', $css);
        $this->assertMatchesRegularExpression('/\.d2-header\.is-lifted\s*\{\s*margin-block-end: var\(--navrow-h\);/', $css, 'no layout shift when the row leaves the flow by scrolling');
        $this->assertMatchesRegularExpression('/\.d2-header\.is-scrolled\s*\{\s*--lockup-scale: 0\.8571;/', $css);
        $this->assertStringContainsString('scale: var(--lockup-scale);', $css);
        $this->assertMatchesRegularExpression('/\.d2-header\.is-tucked\s*\{[^}]*\.d2-header__navrow\s*\{\s*visibility: hidden;\s*opacity: 0;\s*translate: 0 -100%;/', $css);
        $this->assertMatchesRegularExpression('/\.d2-header\.is-docking \.d2-nav__link\s*\{\s*animation: d2-nav-dock var\(--dur-enter\) var\(--ease-out\) both;/', $css);

        // Keyboard: a control of the sticky header always sits inside the page's scroll padding, so Tab into the
        // compact bar scrolled the page to centre it; while focus is inside the live header that padding is off.
        $this->assertMatchesRegularExpression('/:root:has\(\[data-d2-header\]:focus-within\)\s*\{\s*scroll-padding-block-start: 0;/', $css);
        // Without JavaScript the header never compacts: in-page targets keep clear of the bar and the row (112 px).
        $this->assertMatchesRegularExpression('/@media \(width >= 1024px\)\s*\{\s*:root\.no-js\s*\{\s*scroll-padding-block-start: calc\(var\(--header-h\) \+ var\(--s-12\) \+ var\(--s-4\)\);/', $css);

        // QA round 1 (axe target-size): the language list hangs under the header's bottom line, never over the row.
        $this->assertStringContainsString('--lang-drop: calc((var(--mast-h) - var(--lang-h)) / 2 + var(--row-below) + var(--s-2));', $css);
        $this->assertStringContainsString('--lang-drop: calc((var(--bar-h) - var(--lang-h)) / 2 + var(--s-2));', $css);
        $lang = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path('css/d2/components/langmenu.css')));
        $this->assertMatchesRegularExpression('/\.d2-header \.langmenu__panel\s*\{\s*inset-block-start: calc\(100% \+ var\(--lang-drop\)\);/', $lang);
        $this->assertMatchesRegularExpression('/@media \(pointer: coarse\)\s*\{\s*\.langmenu__trigger,\s*\.langmenu__option\s*\{\s*min-block-size: var\(--target-touch\);/', $lang);

        $mega = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path('css/d2/components/megamenu.css')));
        $this->assertStringContainsString('border-radius: var(--radius-overlay);', $mega);
        $this->assertStringContainsString('box-shadow: var(--shadow-overlay);', $mega);
        $this->assertStringContainsString('inset-inline: var(--pad-x);', $mega, 'the panel spans the container');
        $this->assertDoesNotMatchRegularExpression('/linear-gradient|text-transform|letter-spacing:(?! var\(--ls-h3\);)|var\(--lift|font-family/', $mega, 'no gradient, capitals, label tracking (only the H3 role\'s own), lift or own font');
        // D48 + QA round 2: list | summary and button | documents, 4 / 4 / 4 in every language (Russian too: two of its
        // titles take two lines and its summary keeps a 357 px measure, not 268), never past the window; a short window
        // scrolls the body behind a fade (an alpha mask). The preview's title (a heading role) and its summary never
        // hyphenate, although base.css hyphenates Russian paragraphs.
        $this->assertMatchesRegularExpression('/\.megamenu__body\s*\{[^}]*grid-template-columns: minmax\(0, 4fr\) minmax\(0, 4fr\) minmax\(0, 4fr\);/', $mega);
        $this->assertSame(1, substr_count($mega, 'grid-template-columns:'), 'one column split for every language');
        $this->assertDoesNotMatchRegularExpression('/\.megamenu__body:lang\(/', $mega, 'no per-language split');
        $this->assertMatchesRegularExpression('/\.megamenu__card-title,\s*\.megamenu__summary\s*\{\s*hyphens: manual;\s*\}/', $mega);
        $this->assertStringContainsString('max-block-size: calc(100dvh - var(--mega-top) - var(--s-2));', $mega);
        $this->assertStringContainsString('--mega-top: calc(var(--mast-h) + var(--navrow-h) + var(--s-2));', $css, 'under the row at the top of the page');
        $this->assertStringContainsString('--mega-top: calc(var(--bar-h) + var(--s-2));', $css, 'under the bar when inline and scrolled');
        $this->assertStringContainsString('--mega-top: calc(var(--mast-h) + var(--s-2));', $css, 'under the masthead when inline at the top');
        $this->assertMatchesRegularExpression('/&\.is-clipped\s*\{\s*mask-image: var\(--mask-fade-bottom\);/', $mega);
        $this->assertMatchesRegularExpression('/@media \(pointer: coarse\)\s*\{\s*\.megamenu__item,\s*\.megamenu__all\s*\{\s*min-block-size: var\(--target-touch\);/', $mega);
    }

    /** The styleguide shows the header states (static specimens, no scrim, no Alpine). */
    public function test_the_styleguide_shows_the_header_states(): void
    {
        $html = $this->visit('/en/styleguide/');
        foreach (['sg-top', 'sg-docked', 'sg-tucked', 'sg-mega', 'sg-lang', 'sg-collapsed'] as $prefix) {
            $this->assertStringContainsString('id="'.$prefix.'-megamenu"', $html, $prefix);
        }
        $this->assertStringNotContainsString('id="sg-search-megamenu"', $html, 'no search specimen: the header has no search');
        $this->assertStringContainsString('d2-header d2-header--demo is-scrolled is-inline', $html);
        $this->assertStringContainsString('d2-header d2-header--demo is-scrolled is-tucked', $html);
        $this->assertStringContainsString('d2-header d2-header--demo is-nav-collapsed', $html);
        $this->assertSame(1, substr_count($html, 'data-d2-header'), 'only the live header is enhanced');
        $this->assertSame(1, substr_count($html, 'class="d2-scrim"'));

        // Russian labels never dock beside the Russian tools: its styleguide shows the tucked bar only.
        $ru = $this->visit('/ru/styleguide/');
        $this->assertStringNotContainsString('id="sg-docked-megamenu"', $ru);
        $this->assertStringContainsString('id="sg-tucked-megamenu"', $ru);

        // QA round 2: each header specimen's explanation is a .sg-note (on the Arabic page a left-to-right reading
        // block whose lines start on one edge), never a .sg-label, which runs flush right with a ragged left edge.
        foreach (['en' => [$html, 7], 'ar' => [$this->visit('/ar/styleguide/'), 7], 'ru' => [$ru, 6]] as $locale => [$page, $count]) {
            $x = $this->dom($page);
            $demos = $this->one($x, '//section[@id="sg-shell"]/div[@class="d2-shell-demos"]');
            $notes = $x->query('./div[contains(@class,"d2-shell-demo")]/p[@class="sg-note"]', $demos);
            $this->assertSame($count, $notes->length, "{$locale}: one note per header specimen, and the rules");
            $this->assertSame(0, $x->query('.//*[contains(concat(" ", @class, " "), " sg-label ")]', $demos)->length, "{$locale}: no label");
            foreach ($notes as $note) {
                $this->assertSame($locale === 'en' ? '' : 'en', $note->getAttribute('lang'), "{$locale}: the English annotation says so");
            }
        }

        // QA round 2: the docked bar needs 78rem; it is scaled to its card (zoom = the frame's width / 78rem, from 1 down
        // to 0.875), so its approved CTA is never cut at the card's edge; the notes sit under their specimens.
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path('css/d2/components/header.css')));
        $this->assertMatchesRegularExpression('/\.d2-shell-demo__frame--docked\s*\{\s*container-type: inline-size;\s*> \.d2-header\s*\{\s*min-inline-size: 78rem;\s*zoom: clamp\(0\.875, tan\(atan2\(100cqi, 78rem\)\), 1\);/', $css);
        $this->assertMatchesRegularExpression('/@layer pages\s*\{\s*\.d2-shell-demo > \.sg-note\s*\{\s*margin-block: 0;\s*hyphens: manual;\s*\}\s*\.d2-shell-demo > \* \+ \.sg-note\s*\{\s*margin-block-start: var\(--s-1\);/', $css);
    }
}
