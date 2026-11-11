<?php

namespace Tests\Feature\Design\D2;

use Database\Seeders\ContentSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The screenshot matrix (scripts/qa/screens.mjs, brief §7.3) and the Design 2 shell it drives (owner: platform).
 * The overlay states click real controls: when the header changes a class or an id, the script must follow, or a
 * state is silently recorded as "not existing" or captured from the wrong control (round-1 QA: the language menu
 * state still clicked the retired `.langmenu__pill`, so §7.3 "language menu open" had no evidence at 1440 and 1280
 * and showed the menu sheet at 768). Every selector the Design 2 states use must exist in the rendered page.
 */
final class ScreensMatrixTest extends TestCase
{
    use RefreshDatabase;

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

    private function script(): string
    {
        return (string) file_get_contents(base_path('scripts/qa/screens.mjs'));
    }

    private function dom(string $locale): DOMXPath
    {
        $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/")->assertOk()->getContent();
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

    private static function hasClass(string $class): string
    {
        return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
    }

    /** §7.3 "language menu open": the header trigger and its popover; on phones the menu sheet's row opens the language sheet. */
    #[DataProvider('locales')]
    public function test_the_language_menu_state_drives_the_header_trigger_and_its_popover(string $locale): void
    {
        $script = $this->script();
        $this->assertStringContainsString("const trigger = '.d2-header__lang .langmenu__trigger';", $script);
        $this->assertStringContainsString("await waitOpen(page, '#d2-langmenu');", $script);
        $this->assertStringNotContainsString('langmenu__pill', $script, 'the retired pill');

        // The fallback runs only where the trigger is hidden, and opens the menu sheet's language group.
        $trigger = strpos($script, 'if (await visible(page, trigger))');
        $sheet = strpos($script, 'const sheet = await openMenuSheet(page, 2);');
        $this->assertNotFalse($trigger);
        $this->assertNotFalse($sheet);
        $this->assertLessThan($sheet, $trigger, 'the popover first; the sheet only where the trigger is hidden');

        $x = $this->dom($locale);
        $lang = $this->one($x, '//header[contains(@class,"d2-header")]//*['.self::hasClass('d2-header__lang').']');
        $this->one($x, './/a['.self::hasClass('langmenu__trigger').']', $lang);
        $panel = $this->one($x, './/*[@id="d2-langmenu"]', $lang);
        $this->assertTrue($panel->hasAttribute('data-overlay'), 'the popover is an overlay');
        $this->assertSame(3, $x->query('.//a[@hreflang]', $panel)->length, 'the three approved languages');

        $this->one($x, '//header[contains(@class,"d2-header")]//*['.self::hasClass('d2-header__menu').']');
        $this->assertStringContainsString("const row = '[data-sheet=\"menu\"] [data-sheet-open=\"lang\"]';", $script);
        $this->assertStringContainsString("await waitOpen(page, '[data-sheet=\"lang\"].is-open');", $script);
        $sheetRoot = $this->one($x, '//*[@data-sheet="menu"]');
        $this->one($x, './/*[@data-sheet-open="lang"]', $sheetRoot);
        $langSheet = $this->one($x, '//*[@data-sheet="lang"]');
        $this->assertSame(3, $x->query('.//a[@hreflang]', $langSheet)->length, 'the three approved languages');
    }

    /**
     * Touch widths keep their touch layout while Chrome paints (round-2 QA: the 768 and 390 home captures ended in a
     * 16 to 51 px white band below the footer, and the footer started 14 px early). Headless Playwright starts Chrome
     * with a mouse's Blink settings and emulates touch per page; a capture beyond the viewport resets the page to the
     * start-up values while it paints, so the Design 2 touch styles were dropped mid-capture. Touch widths therefore
     * run in their own Chrome with a touch screen's settings, every capture checks the input of its width, and every
     * full-page or element capture is checked (and the check recorded in manifest.json).
     */
    public function test_touch_captures_keep_the_touch_layout_and_every_capture_is_checked(): void
    {
        $script = $this->script();

        // The Design 2 CSS lays a page out differently for touch: the input the page sees decides what a capture shows.
        $css = collect(File::allFiles(resource_path('css/d2')))->map(fn ($file) => $file->getContents())->implode("\n");
        $this->assertMatchesRegularExpression('/@media[^{]*\(pointer:\s*coarse\)/', $css);
        $this->assertMatchesRegularExpression('/@media[^{]*\(hover:\s*hover\)/', $css);

        // A touch screen's pointer and hover (Blink: pointer type 2 = coarse, hover type 1 = none), only for touch widths.
        $this->assertStringContainsString("const TOUCH_BLINK_SETTINGS = 'primaryPointerType=2,availablePointerTypes=2,primaryHoverType=1,availableHoverTypes=1';", $script);
        $this->assertStringContainsString('const touchArgs = [...DETERMINISTIC, `--blink-settings=${TOUCH_BLINK_SETTINGS}`];', $script);
        $this->assertStringContainsString("args: kind === 'touch' ? touchArgs : DETERMINISTIC", $script);
        $this->assertStringContainsString('const isTouch = (width) => Boolean(viewportFor(width).hasTouch);', $script);
        $this->assertStringContainsString('record = await runJob(await browserFor(job.width), job);', $script);

        // Every capture checks (and records) the input its width needs.
        $this->assertStringContainsString("isTouch(width) ? { pointer: 'coarse', hover: 'none' } : { pointer: 'fine', hover: 'hover' }", $script);
        $this->assertStringContainsString('record.input = await page.evaluate(', $script);
        $this->assertStringContainsString('if (record.input.pointer !== want.pointer || record.input.hover !== want.hover) {', $script);

        // Full-page and element captures: the settled height re-measured before every segment (the last one included)
        // and after, pointer and hover watched while Chrome paints, segments stitched at the heights Chrome returned.
        $clip = substr($script, (int) strpos($script, 'async function clipBuffer('), 1400);
        $this->assertMatchesRegularExpression('/for \(let top = 0;[\s\S]*check\.before\.push\(await settledHeight\(page, check\.measured\)\);[\s\S]*await watchedShot\(page,/', $clip);
        $this->assertStringContainsString('stitched += returned;', $clip);
        $this->assertStringContainsString('height: stitched, channels: 3', $clip);
        $this->assertStringContainsString('check.after = await settledHeight(page, check.measured);', $clip);
        $this->assertStringNotContainsString('height: clip.height, channels', $script, 'a composite is never sized from the request alone');
        $this->assertStringContainsString("['(pointer: coarse)', '(pointer: fine)', '(hover: none)', '(hover: hover)']", $script);
        $this->assertSame(3, substr_count($script, 'return checked('), 'both full-page paths and the element capture are checked');
        $this->assertStringContainsString('result.ok = result.difference === 0 && result.mediaChanges.length === 0 && result.watched;', $script);

        // The check is recorded for every capture, the failed ones included.
        $this->assertStringContainsString('layoutCheck: shot.layoutCheck ?? null,', $script);
        $this->assertStringContainsString('if (error?.layoutCheck) record.layoutCheck = error.layoutCheck;', $script);
    }

    /** §7.3 "mega menu open" and "mobile menu open": the selectors the script clicks and waits for. */
    #[DataProvider('locales')]
    public function test_the_menu_states_use_selectors_the_header_renders(string $locale): void
    {
        $script = $this->script();
        $this->assertStringContainsString("'.d2-nav__item--mega > .d2-nav__toggle'", $script);
        $this->assertStringContainsString("'#d2-megamenu'", $script);
        $this->assertStringContainsString("'.d2-header__menu'", $script);
        $this->assertStringContainsString("'[data-sheet=\"menu\"].is-open'", $script);

        $x = $this->dom($locale);
        $item = $this->one($x, '//header//li['.self::hasClass('d2-nav__item--mega').']');
        $this->one($x, './a['.self::hasClass('d2-nav__toggle').']', $item);
        $this->one($x, '//*[@id="d2-megamenu"][@data-overlay]');
    }

    /**
     * Brief 2.1 §5.1 moved the calculators off the home to /{locale}/calculators/: §7.3's "calculators" page and the
     * "calculator with values" state capture that page (Design 2 only), and every field the state fills exists there.
     */
    #[DataProvider('locales')]
    public function test_the_calculators_page_and_state_capture_the_calculators_page(string $locale): void
    {
        $script = $this->script();
        $this->assertMatchesRegularExpression("/key: 'calculators',\\s*path: 'calculators\\/',\\s*designs: \\[2\\],/", $script);
        $this->assertMatchesRegularExpression("/key: 'calculator-values',[^{}]*page: 'calculators',\\s*designs: \\[2\\],/", $script);
        $this->assertStringContainsString('urlOf(job.locale, statePage(def))', $script);

        $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/calculators/")->assertOk()->getContent();
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $x = new DOMXPath($dom);
        $tools = $this->one($x, '//*[@id="tools"]');
        $this->one($x, './/*[@data-tool="gratuity"]['.self::hasClass('tool--gratuity').']', $tools);
        preg_match_all("/\\['(gr_[a-z_]+)', '[^']*'\\]/", $script, $fields);
        $this->assertNotEmpty($fields[1]);
        foreach ($fields[1] as $name) {
            $this->assertGreaterThan(0, $x->query('.//*[@name="'.$name.'"]', $tools)->length, "#tools [name={$name}]");
        }
    }
}
