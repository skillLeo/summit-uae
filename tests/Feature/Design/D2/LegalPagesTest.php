<?php

namespace Tests\Feature\Design\D2;

use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2 legal pages (resources/views/designs/d2/inner/legal.blade.php; owner: inner-kit): P08 Legal
 * Notice, L01 Privacy Policy and L02 Terms and Conditions render the complete approved text (intro, every
 * numbered H2 section verbatim, the last-updated line) in the 720 px column with the TOC, the TOC sheet and
 * the reading progress, keep the contact release rule and the noindex preview SEO, in EN, AR and RU.
 */
final class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    private const PAGES = ['P08' => 'legal-disclaimer', 'L01' => 'privacy-policy', 'L02' => 'terms-and-conditions'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function page(string $code, string $locale): string
    {
        app()->setLocale($locale);

        return $this->withUnencryptedCookies(['summit_design' => '2'])
            ->get('/'.$locale.'/'.self::PAGES[$code].'/')
            ->assertOk()
            ->getContent();
    }

    /** The <main> element only (the shell repeats navigation labels). */
    private function main(string $html): string
    {
        $this->assertSame(1, preg_match('/<main id="main-content".*?<\/main>/s', $html, $m), 'one <main id="main-content">');

        return $m[0];
    }

    private function text(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    public function test_every_legal_page_renders_the_complete_approved_text_in_order(): void
    {
        foreach (self::PAGES as $code => $path) {
            foreach (self::LOCALES as $locale) {
                $html = $this->page($code, $locale);
                $main = $this->main($html);
                $record = content()->page($code, $locale);
                $content = $record['content'];

                $this->assertSame(1, substr_count($html, '<h1'), "{$code} {$locale}: one H1");
                $this->assertStringContainsString(e($record['h1']).'</h1>', $main, "{$code} {$locale}: approved H1");
                $this->assertStringContainsString('dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', $html);

                // Every approved string, in the DOCX order: last updated, intro, then each section heading + blocks.
                $expected = [$record['last_updated'], ...$content['intro']];
                foreach ($content['sections'] as $section) {
                    $expected[] = $section['heading'];
                    foreach ($section['blocks'] as $block) {
                        array_push($expected, ...($block['type'] === 'p' ? [$block['text']] : $block['items']));
                    }
                }
                $text = $this->text($main);
                $offset = 0;
                foreach ($expected as $string) {
                    // Arabic: the approved digits as the Arabic digits setting shows them (App\Design\D2\Digits).
                    $string = trim((string) preg_replace('/\s+/u', ' ', \App\Design\D2\Digits::localise($string, $locale)));
                    $at = mb_strpos($text, $string, $offset);
                    $this->assertNotFalse($at, "{$code} {$locale}: approved text present and in order: ".mb_substr($string, 0, 60));
                    $offset = $at + mb_strlen($string);
                }

                // Section headings are real H2s with their approved numbering verbatim (no list counter); Arabic
                // digits as the Arabic digits setting shows them.
                preg_match_all('/<h2 class="prose__heading">(.*?)<\/h2>/s', $main, $h2);
                $this->assertSame(array_map(fn ($heading) => \App\Design\D2\Digits::localise($heading, $locale), array_column($content['sections'], 'heading')), array_map(fn ($h) => html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5), $h2[1]), "{$code} {$locale}: H2 = approved section headings");
            }
        }
    }

    public function test_the_toc_links_every_section_and_the_sheet_and_progress_are_wired(): void
    {
        foreach (self::PAGES as $code => $path) {
            foreach (self::LOCALES as $locale) {
                $html = $this->page($code, $locale);
                $sections = content()->page($code, $locale)['content']['sections'];
                $ids = array_map(fn ($i) => sprintf('section-%02d', $i + 1), array_keys($sections));

                foreach ($ids as $id) {
                    $this->assertStringContainsString('<section class="prose__section prose__section--default" id="'.$id.'">', $html, "{$code} {$locale}: #{$id}");
                    // desktop nav, inline (no-JS) list and the sheet each link to it
                    $this->assertSame(3, substr_count($html, 'href="#'.$id.'"'), "{$code} {$locale}: three TOC links to #{$id}");
                }
                $this->assertSame(1, substr_count($html, 'data-sheet="toc"'), 'one TOC sheet');
                $this->assertStringContainsString('data-sheet-open="toc"', $html);
                $this->assertStringContainsString('id="sheet-toc-title"', $html);
                $this->assertStringContainsString('data-reading-progress="legal-text"', $html);
                $this->assertStringContainsString('id="legal-text"', $html);
                // Design 2.1 (brief §3.6 rules 5, 7 and 8): no large-title collapse, no calendar or print glyph.
                $this->assertStringNotContainsString('data-large-title', $html);
                $this->assertStringNotContainsString('<template data-bar-title>', $html);
                $this->assertStringNotContainsString('data-icon="calendar"', $html);
                $this->assertStringNotContainsString('data-icon="print"', $html);
                // proposed copy is marked
                $this->assertMatchesRegularExpression('/data-copy-status="proposed"\s*>'.preg_quote(e(d2p('inner.toc_title', [], $locale)), '/').'</', $html);
                // the print button waits for JavaScript; its row is reserved under html.js (legal.css), so no `hidden`
                $this->assertStringContainsString('<div class="legal__tools" x-data="d2InnerPrint" x-bind:class="readyClass">', $html, 'the print button waits for JavaScript');
                // the contents bar is drawn from the first paint under html.js: its button carries no `hidden`
                $this->assertMatchesRegularExpression('/<button type="button" class="toc-bar__button" data-sheet-open="toc">/', $html);
            }
        }
    }

    public function test_contact_links_follow_the_release_rule_and_cross_links_stay_in_the_same_tab(): void
    {
        config(['summit.contact_page_released' => false]);
        foreach (self::PAGES as $code => $path) {
            foreach (self::LOCALES as $locale) {
                $main = $this->main($this->page($code, $locale));
                $this->assertStringContainsString('href="mailto:info@summitlegaluae.com"', $main);
                $this->assertStringContainsString('href="tel:+971524666191"', $main);
                // e-mail and telephone are the kit's contact channels (one pattern with the CTA panels and the footer),
                // in the DOCX order before "Contact Summit", which stays a link row
                $this->assertStringContainsString('<bdi class="channel__text" dir="ltr">info@summitlegaluae.com</bdi>', $main);
                $this->assertStringContainsString('<bdi class="channel__text" dir="ltr">+971 52 466 6191</bdi>', $main);
                $this->assertStringNotContainsString('link-row--email', $main);
                $this->assertStringNotContainsString('link-row--phone', $main);
                $this->assertLessThan(strpos($main, 'href="'.SiteUrl::home($locale, 'consultation-form').'"'), strpos($main, 'class="channel channel--email"'));
                // "Contact Summit" → P07 → the home form until the contact page is released
                $this->assertStringContainsString('href="'.SiteUrl::home($locale, 'consultation-form').'"', $main);
                $this->assertStringNotContainsString('/contact/', $main);

                foreach (array_diff(array_keys(self::PAGES), [$code]) as $other) {
                    $this->assertStringContainsString('href="'.SiteUrl::page($other, $locale).'"', $main, "{$code} links {$other}");
                }
                $this->assertStringNotContainsString('target="_blank"', $main, 'legal links open in the same tab');
            }
        }
    }

    public function test_the_preview_seo_stays_noindex_and_the_copy_is_compliant(): void
    {
        foreach (self::PAGES as $code => $path) {
            foreach (self::LOCALES as $locale) {
                $html = $this->page($code, $locale);
                $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
                $this->assertStringContainsString('<title>'.e(content()->page($code, $locale)['seo_title']).'</title>', $html);
                $text = mb_strtolower($this->text($this->main($html)));
                foreach (['law firm', '24/7', 'free consultation', 'years of experience', 'success rate', 'award'] as $banned) {
                    $this->assertStringNotContainsString($banned, $text, "{$code} {$locale}: no '{$banned}'");
                }
                // Meta description is metadata only (never visible copy).
                $this->assertStringNotContainsString(e(content()->page($code, $locale)['meta_description']).'</', $this->main($html));
            }
        }
    }

    public function test_arabic_digits_follow_the_setting_and_latin_contact_values_are_isolated(): void
    {
        // The approved Arabic legal copy writes Eastern Arabic digits ("١.", "٢٣ أكتوبر ٢٠٢٦"). Design 2.1 (brief §3.7,
        // owner decision) shows one digit style per Arabic page: Western 0-9 by default, ٠-٩ with the setting.
        $record = content()->page('L01', 'ar');
        $heading = $record['content']['sections'][0]['heading'];
        $this->assertMatchesRegularExpression('/[\x{0660}-\x{0669}]/u', $heading);

        $main = $this->main($this->page('L01', 'ar'));
        $this->assertStringContainsString(e(strtr($heading, ['١' => '1'])), $main, 'western by default: "١." is shown as "1."');
        $this->assertStringContainsString(e(\App\Design\D2\Digits::localise($record['last_updated'], 'ar', 'western')), $main);
        $this->assertDoesNotMatchRegularExpression('/[\x{0660}-\x{0669}]/u', strip_tags($main), 'no Eastern digit left in the text');

        config(['design.d2.arabic_digits' => 'eastern']);
        $main = $this->main($this->page('L01', 'ar'));
        $this->assertStringContainsString(e($heading), $main, 'eastern: the approved digits as written');
        $this->assertStringContainsString(e($record['last_updated']), $main);
        $this->assertDoesNotMatchRegularExpression('/[0-9]/', strip_tags(preg_replace('/<bdi\b.*?<\/bdi>/s', '', $main)), 'no Western digit outside the left-to-right runs');
        // the e-mail isolated as one run (the channel's value, beside its glyph in the page direction), never split by
        // the Latin-run isolation into "info" + "@" + …
        $this->assertStringContainsString('<bdi class="channel__text" dir="ltr">info@summitlegaluae.com</bdi>', $main);
        $this->assertStringNotContainsString('<bdi dir="ltr">info</bdi>', $main, 'e-mail isolated as one run, not split by the Latin-run isolation');
    }

    public function test_without_the_design_2_cookie_design_1_still_renders_its_own_preview(): void
    {
        app()->setLocale('en');
        $html = $this->get('/en/privacy-policy/')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-design="2"', $html);
        $this->assertStringNotContainsString('inner-head', $html);
    }
}
