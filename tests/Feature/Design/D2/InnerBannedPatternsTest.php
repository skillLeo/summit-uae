<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Icons;
use Database\Seeders\ContentSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Design 2.1 Phase 1, inner area (docs/briefs/design-2.1-and-admin-cms.md §3.5, §3.6; fix list D01 to D06): every
 * Design 2 page other than the home, in EN, AR and RU, as the server renders it. The CSS side of the same rules
 * (no shadow, gradient, hover lift, warm tint, off-scale size or weight, line clamp) is Stylelint's
 * (resources/css/d2/.stylelintrc.json); the rendered measurements are scripts/qa/lint-design.mjs.
 *
 *  - D06: at most one gold button on a page, and only as the inner head's primary action (the page's hero):
 *    exactly one on the practice-areas hub, the eleven service pages, the FAQ and Contact, none elsewhere.
 *  - D01: no eyebrow label (.t-eyebrow) in the main content.
 *  - D02: no middle dot in the main content's text, except inside the approved strings that are written with
 *    them (the Contact file's languages line and the About file's team "Working Languages" lines; approved copy
 *    is never rewritten).
 *  - D03: no arrow glyph and no arrow character on a link or button (the form's Back and Next are form-back and
 *    form-next; a link to another website ends with the ↗ glyph, "external").
 *  - D04: only the functional icons of §3.6 rule 5 (App\Design\D2\Icons::ALLOWED, plus the WhatsApp mark), the
 *    clock only in a contact block, and never an icon inside a card title.
 */
final class InnerBannedPatternsTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    /** Pages whose inner head carries the page's one gold action (the hero primary). */
    private const GOLD = ['P02', 'P05', 'P07', 'S01', 'S02', 'S03', 'S04', 'S05', 'S06', 'S07', 'S08', 'S09', 'S10', 'S11'];

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

    /** @return array<string, array{path: string, status: int}> page key => its path in $locale */
    private function pages(string $locale): array
    {
        $pages = [];
        foreach (['P02', 'P03', 'P04', 'P05', 'P06', 'P07', 'P08', 'L01', 'L02'] as $code) {
            $pages[$code] = ['path' => '/'.$locale.'/'.config("summit.pages.{$code}.path"), 'status' => 200];
        }
        foreach (config('summit.services') as $code => $slug) {
            $pages[$code] = ['path' => '/'.$locale.'/practice-areas/'.$slug.'/', 'status' => 200];
        }
        $pages['offline'] = ['path' => '/'.$locale.'/offline/', 'status' => 200];
        $pages['404'] = ['path' => '/'.$locale.'/no-such-page-inner-qa/', 'status' => 404];

        return $pages;
    }

    private function dom(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    private function text(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    #[DataProvider('locales')]
    public function test_every_inner_page_keeps_the_phase_one_rules(string $locale): void
    {
        $allowed = [...Icons::ALLOWED, 'whatsapp'];
        // The Contact file's languages line and, since Phase 2 (D44: languages as plain text, the approved wording),
        // the About file's "Working Languages: …" line of each team member, and the approved focus line About shows
        // under each role (brief §5.3: "Doctorate in Law · Civil, Commercial and Criminal Legal Advice").
        $approvedDots = array_values(array_filter(
            [
                (string) (content()->page('P07', $locale)['content']['details']['languages'] ?? ''),
                ...array_map(fn (array $m) => (string) ($m['languages'] ?? ''), content()->teamMembers($locale)),
                ...array_map(fn (array $m) => (string) ($m['focus'] ?? ''), content()->teamMembers($locale)),
            ],
            fn (string $s) => str_contains($s, '·'),
        ));

        foreach ($this->pages($locale) as $key => $page) {
            $ctx = "{$key} {$locale}";
            $html = (string) $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])
                ->get($page['path'])->assertStatus($page['status'])->getContent();
            $x = $this->dom($html);
            $main = $x->query('//main')->item(0);
            $this->assertInstanceOf(DOMElement::class, $main, $ctx);

            // D06: one gold action at most, the inner head's primary, on the pages that have one.
            $gold = $x->query('//*[contains(concat(" ", normalize-space(@class), " "), " btn--gold ") or contains(concat(" ", normalize-space(@class), " "), " btn--signature ")]');
            $inHead = $x->query('//header[contains(concat(" ", normalize-space(@class), " "), " inner-head ")]//*[contains(concat(" ", normalize-space(@class), " "), " btn--gold ")]');
            $expected = in_array($key, self::GOLD, true) ? 1 : 0;
            $this->assertSame($expected, $gold->length, "{$ctx}: gold buttons on the page");
            $this->assertSame($expected, $inHead->length, "{$ctx}: the gold button is the inner head's");

            // D01: no eyebrow label.
            $this->assertSame(0, $x->query('.//*[contains(concat(" ", normalize-space(@class), " "), " t-eyebrow ")]', $main)->length, "{$ctx}: eyebrow");

            // D02: no middle dot outside the approved string written with them.
            $text = $this->text($main->textContent);
            foreach ($approvedDots as $approved) {
                $text = str_replace($this->text($approved), '', $text);
            }
            $this->assertStringNotContainsString('·', $text, "{$ctx}: middle dot");

            // D03: no arrow glyph, no arrow character on a link or button.
            $this->assertSame(0, $x->query('.//*[@data-icon="arrow" or @data-icon="arrow-start"]', $main)->length, "{$ctx}: arrow glyph");
            foreach ($x->query('.//a | .//button', $main) as $control) {
                $this->assertDoesNotMatchRegularExpression('/[→←↗↖↘↙»›‹«]|->/u', $control->textContent, "{$ctx}: arrow character in a control");
            }

            // D04: functional icons only; the clock only in a contact block; no icon in a card title. (Every page
            // draws some functional icon: breadcrumb chevrons, the 404's search glyph, the contact glyphs; so the
            // query is known to work.)
            $icons = $x->query('.//*[@data-icon]', $main);
            $this->assertGreaterThan(0, $icons->length, "{$ctx}: functional icons found");
            foreach ($icons as $icon) {
                $name = $icon->getAttribute('data-icon');
                $this->assertContains($name, $allowed, "{$ctx}: icon {$name}");
                if ($name === 'clock') {
                    $this->assertSame(1, $x->query('ancestor::*[contains(@class, "contact-card") or contains(@class, "cta-card")][1]', $icon)->length, "{$ctx}: the clock belongs to a contact block");
                }
            }
            $this->assertSame(0, $x->query('.//*[contains(concat(" ", normalize-space(@class), " "), " card__title ")]//*[@data-icon]', $main)->length, "{$ctx}: icon in a card title");
        }
    }
}
