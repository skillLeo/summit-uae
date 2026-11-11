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
 * Design 2.1 header (docs/briefs/design-2.1-and-admin-cms.md §3.7, §3.8; fixes D06, D12, D13, D14, D48; owner:
 * header): the official lockup and the shield, the seven approved labels in the DOCX order, the Ink CTA, the
 * language menu with the approved labels of the page language, the search trigger without a key cap, the
 * text-only mega menu over its scrim, and the retired night glass and metal.
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
        $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get($path)->assertOk()->getContent();
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

        // The shield alone for the compact bar: the official traced emblem, decorative inside the named link.
        $mark = $this->one($x, './/*[local-name()="svg"][contains(@class,"d2-brand__mark")]', $brand);
        $this->assertSame('true', $mark->getAttribute('aria-hidden'));
        $this->assertSame('0 0 2586 4256', $mark->getAttribute('viewbox'), 'resources/brand/emblem.svg, never redrawn');

        $header = $this->header($this->visit("/{$locale}/"));
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

    /** D06: the header CTA is the Ink button with the approved label; no metal, no night glass, no short proposed label. */
    #[DataProvider('locales')]
    public function test_the_header_cta_is_always_the_ink_button(string $locale): void
    {
        $x = $this->dom($this->visit("/{$locale}/"));
        $cta = $this->one($x, '//header//a[contains(@class,"d2-header__cta")]');
        $this->assertStringContainsString('btn--ink', $cta->getAttribute('class'));
        $this->assertStringNotContainsString('btn--signature', $cta->getAttribute('class'));
        $this->assertSame(SiteUrl::consultation(), $cta->getAttribute('href'));
        $this->assertSame(ui('cta.request_consultation'), $this->text($this->one($x, './/span[@class="btn__label"]', $cta)->textContent));

        $header = $this->header($this->visit("/{$locale}/"));
        $this->assertStringNotContainsString('btn--signature', $header);
        $this->assertStringNotContainsString('btn--gold', $header);
        $this->assertStringNotContainsString('d2-header__night', $header);
        $this->assertStringNotContainsString('d2-header__cta-short', $header);
    }

    /**
     * Where the seven labels do not fit, the tools' menu button (.d2-header__menu, the only one: the QA matrix and
     * the menu sheet find it by that class); the compact bar has its own beside the shield. Both open the menu sheet
     * and are a link to the footer navigation without JavaScript.
     */
    #[DataProvider('locales')]
    public function test_the_menu_buttons(string $locale): void
    {
        $x = $this->dom($this->visit("/{$locale}/"));
        $menu = $this->one($x, '//header//a[contains(concat(" ", normalize-space(@class), " "), " d2-header__menu ")]');
        $this->assertStringContainsString('d2-header__menu--end', $menu->getAttribute('class'));
        $compact = $this->one($x, '//header//a[contains(@class,"d2-header__compact-menu")]');
        $this->assertNotNull($compact->parentNode);
        $this->assertStringContainsString('d2-header__start', $compact->parentNode instanceof DOMElement ? $compact->parentNode->getAttribute('class') : '', 'beside the shield');
        foreach ([$menu, $compact] as $button) {
            $this->assertSame('#d2-footer-nav', $button->getAttribute('href'));
            $this->assertSame('menu', $button->getAttribute('data-sheet-open'));
            $this->assertSame(ui('a11y.open_menu'), $button->getAttribute('aria-label'));
            $this->one($x, './/*[@data-icon="menu"]', $button);
        }
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

    /** brief §3.7, D12: the trigger shows the current language's approved label; the list the three approved labels of the page language. */
    #[DataProvider('locales')]
    public function test_the_language_menu_uses_the_approved_labels_of_the_page_language(string $locale): void
    {
        $x = $this->dom($this->visit("/{$locale}/about/"));
        $menu = $this->one($x, '//header//div[contains(@class,"langmenu")][@x-data="d2Langmenu"]');
        $trigger = $this->one($x, './/a[contains(@class,"langmenu__trigger")]', $menu);
        $this->assertSame(ui('a11y.choose_language').' '.ui('lang.'.$locale), $this->text($trigger->textContent), 'name: "Choose language" + the visible label');
        $this->assertSame(ui('lang.'.$locale), $this->text($this->one($x, './/span[@class="langmenu__current"]', $trigger)->textContent));
        $this->assertSame('#d2-footer-languages', $trigger->getAttribute('href'), 'no-JS: the footer language list');
        $this->one($x, './/*[@data-icon="chevron-down"]', $trigger);

        $panel = $this->one($x, './/div[contains(@class,"langmenu__panel")]', $menu);
        $this->assertTrue($panel->hasAttribute('data-overlay'));
        $this->assertTrue($panel->hasAttribute('hidden'));
        $options = $x->query('.//a[contains(@class,"langmenu__option")]', $panel);
        $this->assertSame(3, $options->length);
        foreach (['ar', 'en', 'ru'] as $i => $code) {
            $option = $options->item($i);
            $this->assertInstanceOf(DOMElement::class, $option);
            $this->assertSame(ui('lang.'.$code), $this->text($option->textContent), "{$locale}: the approved label for {$code}, in the page language");
            $this->assertSame($code, $option->getAttribute('hreflang'));
            $this->assertSame($locale, $option->getAttribute('lang'), 'the label is in the page language');
            $this->assertSame($code, $option->getAttribute('data-lang-switch'));
            $this->assertStringEndsWith("/{$code}/about/", $option->getAttribute('href'), 'the translated page');
            $this->assertSame($code === $locale ? 'true' : '', $option->getAttribute('aria-current'));
        }
        $this->assertSame(0, $x->query('.//*[@data-icon]', $panel)->length, 'no tick or other icon in a menu item');

        $header = $this->header($this->visit("/{$locale}/"));
        $this->assertStringNotContainsString('langmenu__dot', $header, 'no "EN · ع · RU"');
        $this->assertStringNotContainsString(' · ', $header);
    }

    /** D14: no visible key cap; the shortcuts stay and the tooltip names "/". */
    #[DataProvider('locales')]
    public function test_the_search_trigger_has_no_key_cap(string $locale): void
    {
        $html = $this->visit("/{$locale}/");
        $x = $this->dom($html);
        $search = $this->one($x, '//header//a[contains(@class,"d2-search")][@data-d2-search]');
        $this->assertSame(SiteUrl::page('P03'), $search->getAttribute('href'), 'no-JS: Legal Resources');
        $this->assertSame('/ Control+K Meta+K', $search->getAttribute('aria-keyshortcuts'));
        $this->assertSame(d2p('shell.search_placeholder'), $search->getAttribute('aria-label'));
        $this->assertSame(d2p('shell.search_tip'), $search->getAttribute('data-tooltip'));
        $this->assertStringContainsString('(/)', d2p('shell.search_tip'));
        $this->one($x, './/*[@data-icon="search"]', $search);

        $header = $this->header($html);
        $this->assertStringNotContainsString('class="kbd', $header);
        $this->assertStringNotContainsString('<kbd', $header);
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

        // D12 + QA round 1: the language menu shows from 48rem in every language; Russian closes its tools up to
        // 4 px below 52rem and keeps the 40 px lockup below 50rem instead of hiding the menu.
        $this->assertDoesNotMatchRegularExpression('/\.d2-header__lang:lang\(ru\)\s*\{\s*display: none;/', $css, 'Russian tablets keep the language menu');
        $this->assertMatchesRegularExpression('/@container d2-header \(width >= 48rem\)\s*\{\s*\.d2-header \.d2-header__cta\s*\{\s*display: inline-flex;\s*\}\s*\.d2-header \.d2-header__lang\s*\{\s*display: flex;/', $css);
        $this->assertMatchesRegularExpression('/@container d2-header \(48rem <= width < 52rem\)\s*\{\s*\.d2-header__tools:lang\(ru\)\s*\{\s*gap: var\(--s-1\);/', $css);
        $this->assertMatchesRegularExpression('/@container d2-header \(48rem <= width < 50rem\)\s*\{\s*\.d2-header__mast:lang\(ru\)\s*\{\s*--brand-h: var\(--lockup-h\);/', $css);
        // D13 + QA round 1: a 48 px lockup on tablets (the 72 px masthead), 56 px from 1024 px.
        $this->assertStringContainsString('block-size: var(--brand-h);', $css);
        $this->assertMatchesRegularExpression('/@container d2-header \(width >= 48rem\)\s*\{[^@]*\.d2-header__mast\s*\{\s*--brand-h: var\(--s-12\);/', $css);

        // D13 + QA round 1: compact, the seven labels dock in the 64 px bar where header.js measures that they fit;
        // the row is centred on the bar, above the masthead, click-through except for its labels; a spacer as wide as
        // the shield's target starts them; otherwise the row tucks away and the menu button shows beside the shield.
        $this->assertStringContainsString('translate: 0 calc(-1 * (var(--navrow-h) + (var(--bar-h) - var(--navrow-h)) / 2));', $css);
        $this->assertMatchesRegularExpression('/\.d2-header\.is-docked\s*\{[^}]*\.d2-header__navrow\s*\{[^}]*z-index: 1;\s*pointer-events: none;\s*background: none;/', $css);
        $this->assertMatchesRegularExpression('/\.d2-nav__list\s*\{\s*margin-inline-start: 0;\s*&::before\s*\{\s*flex: none;\s*inline-size: var\(--brand-w\);/', $css);
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
        $this->assertStringContainsString('--mega-top: calc(var(--bar-h) + var(--s-2));', $css, 'under the bar when docked');
        $this->assertMatchesRegularExpression('/&\.is-clipped\s*\{\s*mask-image: var\(--mask-fade-bottom\);/', $mega);
        $this->assertMatchesRegularExpression('/@media \(pointer: coarse\)\s*\{\s*\.megamenu__item,\s*\.megamenu__all\s*\{\s*min-block-size: var\(--target-touch\);/', $mega);
    }

    /** The styleguide shows the header states (static specimens, no scrim, no Alpine). */
    public function test_the_styleguide_shows_the_header_states(): void
    {
        $html = $this->visit('/en/styleguide/');
        foreach (['sg-top', 'sg-docked', 'sg-tucked', 'sg-mega', 'sg-lang', 'sg-search', 'sg-collapsed'] as $prefix) {
            $this->assertStringContainsString('id="'.$prefix.'-megamenu"', $html, $prefix);
        }
        $this->assertStringContainsString('d2-header d2-header--demo is-scrolled is-docked', $html);
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
        foreach (['en' => [$html, 8], 'ar' => [$this->visit('/ar/styleguide/'), 8], 'ru' => [$ru, 7]] as $locale => [$page, $count]) {
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
