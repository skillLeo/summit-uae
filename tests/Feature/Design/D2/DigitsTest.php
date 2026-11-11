<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Digits;
use App\Design\D2\Hours;
use Database\Seeders\ContentSeeder;
use Dom\CDATASection;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\HTMLElement;
use Dom\Node;
use Dom\Text;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * The Arabic digits setting (Design 2.1 brief §3.7, fix D11; owner decision: Western 0-9 by default):
 * App\Design\D2\Digits converts every Design 2 Arabic HTML response at render time so a page never mixes 0-9 and
 * ٠-٩, and marks it <html data-digits> for the scripts (resources/js/d2/lib/digits.js, which mirrors the text
 * rules: the cases below are the same as in lib/digits.test.js). Since Phase 1 round 2 it converts the Arabic
 * blocks (lang="ar") of the English and Russian pages too, by the same rules, so Arabic text shows the same digits
 * wherever it appears (the styleguide's specimens in the other direction).
 *
 * Cost, measured on all 24 Arabic routes of this tree (PHP 8.4.17, one core, Phase 1): western, the default, 0.5 to
 * 1.4 ms on a page whose copy has no Eastern digit (it is not tokenised: one byte search) and 2.4 to 3.9 ms on the
 * five whose approved copy has them (About, Contact, Legal Notice, Privacy, Terms); eastern 2.2 to 7.6 ms on inner
 * pages of 140 to 360 KB and 22 ms on the 870 KB home page (the 3.1 MB internal styleguide: 64 / 108 ms). An
 * HTML5-parser implementation of the same rules (Dom\HTMLDocument) took 1.5 to 8 times as long; on those 24 pages
 * both gave the same tree in both modes (test_the_converter_agrees_with_an_html5_parser_on_real_pages keeps three
 * of them under guard). English and Russian pages (round 2, a loaded machine; the Arabic pages' cost unchanged
 * within noise): western 0.01 to 0.03 ms (no Eastern digit: one byte search), eastern 0.03 to 0.05 ms on a page
 * without an Arabic block and about 5 ms on the home pages (the scan ends after the last one, a sample document's
 * watermark line); the internal styleguides, whose specimens are converted, 45 to 60 ms (western) and 65 to 100 ms
 * (eastern). The English styleguide is under the parser guard too.
 */
final class DigitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    /** @return array<string, array{string, string}> */
    public static function eastern(): array
    {
        $cases = [
            ['1. نفهم ما حدث', '١. نفهم ما حدث'],
            ['من 9 صباحًا إلى 6 مساءً', 'من ٩ صباحًا إلى ٦ مساءً'],
            ['1.5 ساعة', '١٫٥ ساعة'],
            ['10,000 درهم', '١٠٬٠٠٠ درهم'],
            ['الساعة 18:00', 'الساعة ١٨:٠٠'],
            ['00/00/2026', '٠٠/٠٠/٢٠٢٦'],
            ['© 2026 سَمِت للاستشارات القانونية', '© ٢٠٢٦ سَمِت للاستشارات القانونية'],
            ['المادة 53 من القانون', 'المادة ٥٣ من القانون'],
            ['۱۲ شهرًا', '١٢ شهرًا'],
            ['AED 10,000.00', 'AED 10,000.00'],
            ['+971 52 466 6191', '+971 52 466 6191'],
            ['اتصل على 00971 52 466 6191 الآن', 'اتصل على 00971 52 466 6191 الآن'],
            ['12 00971 52 466 6191', '١٢ 00971 52 466 6191'],
            ['الحساب 12345678 فقط 3', 'الحساب 12345678 فقط ٣'],
            ['info@summitlegaluae.com 3', 'info@summitlegaluae.com ٣'],
            ['https://laborlawuae.com/a1 2', 'https://laborlawuae.com/a1 ٢'],
            ['www.summitlegaluae.com/ar/ 4', 'www.summitlegaluae.com/ar/ ٤'],
            ['S01 و 5', 'S01 و ٥'],
            ['SLC-261110-ABC123', 'SLC-261110-ABC123'],
            ['المرسوم Federal Decree-Law No. 33 of 2021 رقم 33', 'المرسوم Federal Decree-Law No. 33 of 2021 رقم ٣٣'],
            ['Время 3', 'Время 3'],
            ['10MB و 4', '10MB و ٤'],
            ['&#039;3&amp;4', '&#039;٣&amp;٤'],
            ['بلا أرقام', 'بلا أرقام'],
        ];

        return array_combine(array_column($cases, 0), $cases);
    }

    /** @return array<string, array{string, string}> */
    public static function western(): array
    {
        $cases = [
            ['ساعات العمل: من الاثنين إلى الجمعة، من ٩ صباحًا إلى ٦ مساءً بتوقيت الإمارات.', 'ساعات العمل: من الاثنين إلى الجمعة، من 9 صباحًا إلى 6 مساءً بتوقيت الإمارات.'],
            ['١. نفهم ما حدث', '1. نفهم ما حدث'],
            ['آخر تحديث: ٢٣ أكتوبر ٢٠٢٦', 'آخر تحديث: 23 أكتوبر 2026'],
            ['١٫٥ ساعة', '1.5 ساعة'],
            ['١٠٬٠٠٠ درهم', '10,000 درهم'],
            ['١٬٠٠٠٬٠٠٠٫٥٠ درهم', '1,000,000.50 درهم'],
            ['۱۲ شهرًا', '12 شهرًا'],
            ['1٫5', '1.5'],
            ['٫ وحدها', '٫ وحدها'],
            ['Open now, closes at 6 pm', 'Open now, closes at 6 pm'],
        ];

        return array_combine(array_column($cases, 0), $cases);
    }

    #[DataProvider('eastern')]
    public function test_eastern_text_converts_outside_left_to_right_tokens(string $input, string $expected): void
    {
        $this->assertSame($expected, Digits::text($input, Digits::EASTERN));
        if (preg_match('/[\x{06F0}-\x{06F9}]/u', $input) !== 1) {
            $this->assertSame($input, Digits::text($expected, Digits::WESTERN), 'the western mode undoes it');
        }
    }

    #[DataProvider('western')]
    public function test_western_text_converts_every_eastern_digit(string $input, string $expected): void
    {
        $this->assertSame($expected, Digits::text($input, Digits::WESTERN));
    }

    public function test_the_mode_comes_from_the_config_and_defaults_to_western(): void
    {
        // Since Phase 3 the CMS setting languages.arabic_digits decides ("western", owner decision: Western 0-9);
        // SUMMIT_ARABIC_DIGITS (config design.d2.arabic_digits) overrides it for one environment when set.
        $this->assertNull(config('design.d2.arabic_digits'), 'no environment override');
        $this->assertSame('western', content()->settings('languages')['arabic_digits'], 'owner decision: Western 0-9');
        $this->assertSame(Digits::WESTERN, Digits::mode());
        foreach (['eastern' => Digits::EASTERN, ' Eastern ' => Digits::EASTERN, 'western' => Digits::WESTERN, 'arabic' => Digits::WESTERN, '' => Digits::WESTERN] as $value => $mode) {
            config(['design.d2.arabic_digits' => $value]);
            $this->assertSame($mode, Digits::mode(), var_export($value, true));
        }
    }

    public function test_localise_applies_to_arabic_only(): void
    {
        $this->assertSame('من 9 صباحًا', Digits::localise('من ٩ صباحًا', 'ar'));
        $this->assertSame('من ٩ صباحًا', Digits::localise('من 9 صباحًا', 'ar', Digits::EASTERN));
        $this->assertSame('٩ x', Digits::localise('٩ x', 'en'), 'other languages are never touched');
        $this->assertSame('9 y', Digits::localise('9 y', 'ru', Digits::EASTERN));
    }

    // ------------------------------------------------------------------------------------------ markup

    public function test_western_converts_text_and_spoken_attributes_and_nothing_else(): void
    {
        $html = '<p class="c٣" id="a٣" data-n="٣" title="الخطوة ٣" aria-label="من ٥" href="/٣">الخطوة ٣ من ٥</p>'
            .'<img alt="صورة ٢" src="/i٢.png"><input placeholder="مثال ٧" value="٧" name="n٧">'
            .'<option label="خيار ١" value="١">خيار ١</option>';
        $expected = '<p class="c٣" id="a٣" data-n="٣" title="الخطوة 3" aria-label="من 5" href="/٣">الخطوة 3 من 5</p>'
            .'<img alt="صورة 2" src="/i٢.png"><input placeholder="مثال 7" value="٧" name="n٧">'
            .'<option label="خيار 1" value="١">خيار 1</option>';
        $this->assertSame($expected, Digits::html($html, Digits::WESTERN));
    }

    public function test_scripts_json_styles_comments_and_the_visitors_text_are_never_touched(): void
    {
        $keep = '<script type="application/json" id="x">{"a":"٣ 3","b":"<\/p> ٤"}</script>'
            .'<script>if (a<b) { s = "٥"; }</script>'
            .'<style>.x::after { content: "٦"; }</style>'
            .'<textarea name="summary">٧ شيكات</textarea>'
            .'<!-- ٨ comment <p>٨</p> -->'
            .'<![CDATA[ ٩ ]]>';
        foreach ([Digits::WESTERN, Digits::EASTERN] as $mode) {
            $this->assertSame($keep.'<p>x</p>', Digits::html($keep.'<p>x</p>', $mode), $mode);
        }
        // ... and the text after them is converted again
        $this->assertSame($keep.'<p>1</p>', Digits::html($keep.'<p>١</p>', Digits::WESTERN));
        $this->assertSame($keep.'<p>١</p>', Digits::html($keep.'<p>1</p>', Digits::EASTERN));
    }

    public function test_verbatim_subtrees_keep_their_digits_in_both_modes(): void
    {
        $html = '<code>٣ 3</code><kbd>٤ 4</kbd><pre>٥ <b>5</b></pre><samp>٦</samp><var>٧</var>'
            .'<span translate="no">٨ 8 <span>٨</span></span><td dir="auto">٩ شيكات 9</td>';
        foreach ([Digits::WESTERN, Digits::EASTERN] as $mode) {
            $this->assertSame($html.'<p>-</p>', Digits::html($html.'<p>-</p>', $mode), $mode);
        }
    }

    public function test_eastern_keeps_left_to_right_runs(): void
    {
        $html = '<p>اتصل على <bdi dir="ltr">+971 52 466 6191</bdi> أو <bdi>SLC-000000-000000</bdi> قبل 6</p>'
            .'<p dir="ltr">Version 2</p><span lang="en">English 3</span><span lang="ru">Русский 4</span>'
            .'<a href="tel:+971524666191" aria-label="اتصل 2">+971 52 466 6191</a><a href="mailto:info@summitlegaluae.com">info 5</a>'
            .'<bdi dir="rtl">٧ ساعات 7</bdi><span lang="ar">عربي 8</span>'
            .'<div dir="ltr"><div>nested 1</div><div>still 2</div></div><p>بعد 3</p>';
        $expected = '<p>اتصل على <bdi dir="ltr">+971 52 466 6191</bdi> أو <bdi>SLC-000000-000000</bdi> قبل ٦</p>'
            .'<p dir="ltr">Version 2</p><span lang="en">English 3</span><span lang="ru">Русский 4</span>'
            .'<a href="tel:+971524666191" aria-label="اتصل 2">+971 52 466 6191</a><a href="mailto:info@summitlegaluae.com">info 5</a>'
            .'<bdi dir="rtl">٧ ساعات ٧</bdi><span lang="ar">عربي ٨</span>'
            .'<div dir="ltr"><div>nested 1</div><div>still 2</div></div><p>بعد ٣</p>';
        $this->assertSame($expected, Digits::html($html, Digits::EASTERN));
        // western mode has no left-to-right exception: an Eastern digit anywhere becomes 0-9
        $this->assertSame('<bdi dir="ltr">1</bdi><span lang="en">2</span>', Digits::html('<bdi dir="ltr">١</bdi><span lang="en">٢</span>', Digits::WESTERN));
    }

    public function test_a_bdi_without_dir_isolates_a_number_of_the_sentence_and_follows_the_setting(): void
    {
        // The views isolate counts inside Arabic sentences with a bare <bdi> (service cards, guide cards, the
        // navigator's progress, the form's draft hint): their digits must match the rest of the page.
        $counts = '<p><bdi>4</bdi> مستندات</p><span><bdi>0</bdi> من <bdi>4</bdi> مستندات جاهزة</span><p>الخطوة 1 من 5</p>';
        $this->assertSame(
            '<p><bdi>٤</bdi> مستندات</p><span><bdi>٠</bdi> من <bdi>٤</bdi> مستندات جاهزة</span><p>الخطوة ١ من ٥</p>',
            Digits::html($counts, Digits::EASTERN),
        );
        $this->assertSame($counts, Digits::html(Digits::html($counts, Digits::EASTERN), Digits::WESTERN));
        // ... while a value that reads left to right keeps its digits through the text rules
        $values = '<bdi>+971 52 466 6191</bdi><bdi>SLC-261110-ABC123</bdi><bdi>info@summitlegaluae.com</bdi><bdi>2.4 ميغابايت</bdi>';
        $this->assertSame(
            '<bdi>+971 52 466 6191</bdi><bdi>SLC-261110-ABC123</bdi><bdi>info@summitlegaluae.com</bdi><bdi>٢٫٤ ميغابايت</bdi>',
            Digits::html($values, Digits::EASTERN),
        );
    }

    public function test_titles_templates_svg_text_and_void_elements(): void
    {
        $html = '<title>الصفحة 2</title><template><p>نموذج 3</p></template><svg><title>رمز 4</title><text>5</text><path d="M0 0L10 10"/></svg>'
            .'<br><input dir="ltr" placeholder="6"><img src="/a.png" alt="7"><p>8</p>';
        $expected = '<title>الصفحة ٢</title><template><p>نموذج ٣</p></template><svg><title>رمز ٤</title><text>٥</text><path d="M0 0L10 10"/></svg>'
            .'<br><input dir="ltr" placeholder="6"><img src="/a.png" alt="٧"><p>٨</p>';
        $this->assertSame($expected, Digits::html($html, Digits::EASTERN), 'a void element with dir="ltr" opens no left-to-right run');
    }

    public function test_odd_markup_is_survived(): void
    {
        $html = '<p>a < 3 and 4 > b</p><p data-x=\'{"a":">٢"}\' title=\'٣ "x"\'>٤</p><br/><img alt=٥ src=x>'
            .'<p>unclosed <b>٦';
        $expected = '<p>a < 3 and 4 > b</p><p data-x=\'{"a":">٢"}\' title=\'3 "x"\'>4</p><br/><img alt=5 src=x>'
            .'<p>unclosed <b>6';
        $this->assertSame($expected, Digits::html($html, Digits::WESTERN));
        $this->assertSame('<script>never closed ٣', Digits::html('<script>never closed ٣', Digits::WESTERN));
    }

    public function test_a_document_without_anything_to_convert_is_returned_as_it_is(): void
    {
        $html = '<p>بلا أرقام شرقية 2026</p>';
        $this->assertSame($html, Digits::html($html, Digits::WESTERN));
        $this->assertSame('<p>لا</p>', Digits::html('<p>لا</p>', Digits::EASTERN));
    }

    // ------------------------------------------------------------------------------------------ whole pages

    public function test_page_converts_only_design_two_arabic_documents_and_marks_them(): void
    {
        $body = '<body><p>من ٩ إلى ٦</p></body></html>';
        $d2ar = "<!DOCTYPE html>\n<html lang=\"ar\" dir=\"rtl\" class=\"no-js\" data-design=\"2\" data-page=\"home\" >\n";
        $this->assertSame(
            "<!DOCTYPE html>\n<html lang=\"ar\" dir=\"rtl\" class=\"no-js\" data-design=\"2\" data-page=\"home\" data-digits=\"western\">\n<body><p>من 9 إلى 6</p></body></html>",
            Digits::page($d2ar.$body, Digits::WESTERN),
        );
        $this->assertStringContainsString('<html lang="ar" dir="rtl" class="no-js" data-design="2" data-page="home" data-digits="eastern">', Digits::page($d2ar.$body, Digits::EASTERN));

        // Design 1 (no data-design), Design 2 in English or Russian without an Arabic block, other documents: byte for
        // byte.
        foreach ([
            "<!DOCTYPE html>\n<html lang=\"ar\" dir=\"rtl\" class=\"no-js\" data-page=\"home\">\n".$body,
            "<!DOCTYPE html>\n<html lang=\"en\" dir=\"ltr\" data-design=\"2\">\n".$body,
            "<!DOCTYPE html>\n<html lang=\"ru\" data-design=\"2\">\n".$body,
            '<p>من ٩</p>',
            '{"a":"٩"}',
            '<html lang="ar" data-design="20">'.$body,
        ] as $html) {
            $this->assertSame($html, Digits::page($html, Digits::WESTERN));
            $this->assertSame($html, Digits::page($html, Digits::EASTERN));
        }

        // an attribute already there is kept; lang="ar-AE" is Arabic too
        $this->assertSame('<html lang="ar-AE" data-design="2" data-digits="western"><p>1</p>', Digits::page('<html lang="ar-AE" data-design="2" data-digits="western"><p>١</p>', Digits::WESTERN));
    }

    /**
     * Phase 1 round 2 (D11): the styleguide's specimens in the other direction (lang="ar" on the English and Russian
     * pages) printed "١." and "٩ … ٦" beside 0-9, a mix that no Arabic page shows. The Arabic blocks of a page in
     * another language follow the setting by the rules of an Arabic page; the rest of that page, and its <html> tag,
     * stay as they are.
     */
    public function test_the_arabic_blocks_of_a_page_in_another_language_follow_the_setting(): void
    {
        $body = '<body><p>Step 3 of ٣</p>'
            .'<div class="sg-specimen" dir="rtl" lang="ar"><h4>١. المعلومات العامة</h4><p>من 9 صباحًا إلى ٦ مساءً</p>'
            .'<div><div>الخطوة 2 من ٥</div></div><p title="الخطوة ٤" data-n="٤">4</p>'
            .'<bdi dir="ltr">+971 52 466 6191</bdi><a href="tel:+971524666191">+971 52 466 6191</a><p lang="en">Label 7</p><code>٧ 7</code></div>'
            .'<p>after ٨ and 8</p><table><tr><td dir="auto">دعوى، ٢٠٢١ <span lang="ar">٢٠٢١</span></td></tr></table>'
            .'<img lang="ar" alt="صورة ٢" src="/i٢.png"><span lang="ar-AE">٥ و 5</span>'
            .'<link rel="alternate" hreflang="ar" href="/ar/"><a hreflang="ar" href="/ar/">٦ 6</a>'
            .'<script>var s = \'<p lang="ar">٩ 9</p>\';</script><!-- <p lang="ar">٩ 9</p> --><p>end ٩ 9</p></body></html>';
        $western = '<body><p>Step 3 of ٣</p>'
            .'<div class="sg-specimen" dir="rtl" lang="ar"><h4>1. المعلومات العامة</h4><p>من 9 صباحًا إلى 6 مساءً</p>'
            .'<div><div>الخطوة 2 من 5</div></div><p title="الخطوة 4" data-n="٤">4</p>'
            .'<bdi dir="ltr">+971 52 466 6191</bdi><a href="tel:+971524666191">+971 52 466 6191</a><p lang="en">Label 7</p><code>٧ 7</code></div>'
            .'<p>after ٨ and 8</p><table><tr><td dir="auto">دعوى، ٢٠٢١ <span lang="ar">٢٠٢١</span></td></tr></table>'
            .'<img lang="ar" alt="صورة 2" src="/i٢.png"><span lang="ar-AE">5 و 5</span>'
            .'<link rel="alternate" hreflang="ar" href="/ar/"><a hreflang="ar" href="/ar/">٦ 6</a>'
            .'<script>var s = \'<p lang="ar">٩ 9</p>\';</script><!-- <p lang="ar">٩ 9</p> --><p>end ٩ 9</p></body></html>';
        $eastern = '<body><p>Step 3 of ٣</p>'
            .'<div class="sg-specimen" dir="rtl" lang="ar"><h4>١. المعلومات العامة</h4><p>من ٩ صباحًا إلى ٦ مساءً</p>'
            .'<div><div>الخطوة ٢ من ٥</div></div><p title="الخطوة ٤" data-n="٤">٤</p>'
            .'<bdi dir="ltr">+971 52 466 6191</bdi><a href="tel:+971524666191">+971 52 466 6191</a><p lang="en">Label 7</p><code>٧ 7</code></div>'
            .'<p>after ٨ and 8</p><table><tr><td dir="auto">دعوى، ٢٠٢١ <span lang="ar">٢٠٢١</span></td></tr></table>'
            .'<img lang="ar" alt="صورة ٢" src="/i٢.png"><span lang="ar-AE">٥ و ٥</span>'
            .'<link rel="alternate" hreflang="ar" href="/ar/"><a hreflang="ar" href="/ar/">٦ 6</a>'
            .'<script>var s = \'<p lang="ar">٩ 9</p>\';</script><!-- <p lang="ar">٩ 9</p> --><p>end ٩ 9</p></body></html>';

        foreach (['en', 'ru'] as $locale) {
            $head = "<!DOCTYPE html>\n<html lang=\"{$locale}\" dir=\"ltr\" class=\"no-js\" data-design=\"2\" data-page=\"styleguide\">\n";
            $this->assertSame($head.$western, Digits::page($head.$body, Digits::WESTERN), "{$locale} western");
            $this->assertSame($head.$eastern, Digits::page($head.$body, Digits::EASTERN), "{$locale} eastern");
            $this->assertSame($head.$eastern, Digits::page($head.$western, Digits::EASTERN), "{$locale}: either way");
        }

        // Design 1 is never touched, whatever its blocks
        $design1 = "<!DOCTYPE html>\n<html lang=\"en\" dir=\"ltr\" class=\"no-js\" data-page=\"home\">\n".$body;
        $this->assertSame($design1, Digits::page($design1, Digits::WESTERN));
        $this->assertSame($design1, Digits::page($design1, Digits::EASTERN));
    }

    public function test_an_arabic_block_ends_with_its_own_end_tag(): void
    {
        // elements of the same name inside the block, and a skipped subtree of that name, are counted apart
        $this->assertSame(
            '<div lang="ar"><div>1</div><div dir="auto">٢</div>3</div>٤<div>٥</div><section lang="ar">6<section>7</section>8</section>٩',
            Digits::html('<div lang="ar"><div>١</div><div dir="auto">٢</div>٣</div>٤<div>٥</div><section lang="ar">٦<section>٧</section>٨</section>٩', Digits::WESTERN, false),
        );
        $this->assertSame(
            '<div lang="ar"><div dir="ltr">1 <div>2</div></div>٣</div>4<p lang="ar">٥</p>6<pre><span lang="ar">7</span></pre>8',
            Digits::html('<div lang="ar"><div dir="ltr">1 <div>2</div></div>3</div>4<p lang="ar">5</p>6<pre><span lang="ar">7</span></pre>8', Digits::EASTERN, false),
        );
        // a raw-text element or a void element of the block, and a block inside a left-to-right run of the page
        $this->assertSame(
            '<textarea lang="ar" placeholder="مثال 1">٢</textarea>٣<input lang="ar" placeholder="رقم 4">٥<div dir="ltr"><p lang="ar">6</p>٧</div>',
            Digits::html('<textarea lang="ar" placeholder="مثال ١">٢</textarea>٣<input lang="ar" placeholder="رقم ٤">٥<div dir="ltr"><p lang="ar">٦</p>٧</div>', Digits::WESTERN, false),
        );
        // nothing to convert without an Arabic block (hreflang and xml:lang are not lang)
        $none = '<p>٣</p><a hreflang="ar" href="/ar/">٤</a><p xml:lang="ar">٥</p>';
        $this->assertSame($none, Digits::html($none, Digits::WESTERN, false));
    }

    // ------------------------------------------------------------------------------------------ against an HTML5 parser

    /**
     * The converter is a single-pass tokenizer (fast: a few ms per page). On real pages it must agree, node by node,
     * with the rules applied to the tree PHP's HTML5 parser builds (Dom\HTMLDocument): every text node, every
     * attribute, template contents included, in both modes. (Measured on all 24 Arabic routes during Phase 1, and
     * on the English and Russian styleguides and home pages in round 2: no difference; this test keeps three Arabic
     * pages and the English styleguide, whose Arabic blocks are converted, under guard.)
     */
    public function test_the_converter_agrees_with_an_html5_parser_on_real_pages(): void
    {
        $this->withoutMiddleware(Digits::class);
        foreach (['/ar/', '/ar/contact/', '/ar/legal-disclaimer/', '/en/styleguide/'] as $path) {
            $locale = explode('/', $path)[1];
            $arabic = $locale === 'ar';
            app()->setLocale($locale);
            $raw = $this->withUnencryptedCookies(['summit_design' => '2'])->get($path)->assertOk()->getContent();
            $this->assertStringNotContainsString('data-digits', $raw, 'the page as the views render it');
            if (! $arabic) {
                $this->assertNotSame($raw, Digits::page($raw, Digits::WESTERN), "{$path}: Arabic blocks with something to convert");
            }
            foreach ([Digits::WESTERN, Digits::EASTERN] as $mode) {
                $reference = HTMLDocument::createFromString($raw, LIBXML_NOERROR);
                if ($arabic) {
                    $reference->documentElement->setAttribute('data-digits', $mode);
                }
                self::referenceWalk($reference->documentElement, $mode, false, $arabic);
                $actual = self::rows(HTMLDocument::createFromString(Digits::page($raw, $mode), LIBXML_NOERROR));
                $count = 0;
                foreach (self::rows($reference) as $row) {
                    $count++;
                    if ($actual->current() !== $row) {
                        $this->fail("{$path} {$mode}: ".json_encode(['converter' => $actual->current(), 'reference' => $row], JSON_UNESCAPED_UNICODE));
                    }
                    $actual->next();
                }
                $this->assertFalse($actual->valid(), "{$path} {$mode}: same tree ({$count} text nodes and attributes)");
            }
        }
    }

    /**
     * The specification on a parsed tree: what Digits::html() must do, written without its tokenizer. $arabic: the
     * language so far is Arabic (an Arabic page; on a page in another language, an element with an Arabic lang
     * starts an Arabic block, converted by the same rules).
     */
    private static function referenceWalk(Node $node, string $mode, bool $skip, bool $arabic = true): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof CDATASection) {
                continue;
            }
            if ($child instanceof Text) {
                if (! $skip && $arabic) {
                    $child->data = Digits::text($child->data, $mode);
                }

                continue;
            }
            if (! $child instanceof Element) {
                continue;
            }
            $name = strtolower($child->localName);
            $dir = strtolower(trim((string) $child->getAttribute('dir')));
            $lang = trim((string) $child->getAttribute('lang'));
            $inArabic = $arabic || preg_match('/^ar(?:-|$)/i', $lang) === 1;
            $verbatim = in_array($name, ['code', 'kbd', 'samp', 'pre', 'var'], true) || $dir === 'auto'
                || strtolower(trim((string) $child->getAttribute('translate'))) === 'no';
            $ltr = $mode === Digits::EASTERN && ($dir === 'ltr' || ($lang !== '' && preg_match('/^ar(?:-|$)/i', $lang) !== 1)
                || ($name === 'a' && preg_match('~^\s*(?:tel|mailto):~i', (string) $child->getAttribute('href')) === 1));
            if (! $skip && $inArabic && ! $verbatim && ! $ltr) {
                foreach (['aria-label', 'aria-description', 'aria-placeholder', 'aria-roledescription', 'aria-valuetext', 'title', 'alt', 'placeholder', 'label'] as $attribute) {
                    if ($child->hasAttribute($attribute)) {
                        $child->setAttribute($attribute, Digits::text((string) $child->getAttribute($attribute), $mode));
                    }
                }
            }
            // (a left-to-right run matters inside Arabic text only)
            $inner = $skip || $verbatim || ($inArabic && $ltr) || in_array($name, ['script', 'style', 'textarea', 'xmp', 'iframe', 'noembed', 'noframes'], true);
            // (PHP 8.4 keeps a template's children as ordinary child nodes; a later version may move them to ->content)
            if ($child instanceof HTMLElement && $name === 'template' && isset($child->content)) {
                self::referenceWalk($child->content, $mode, $inner, $inArabic);
            }
            self::referenceWalk($child, $mode, $inner, $inArabic);
        }
    }

    /**
     * Every text node and attribute in document order, one at a time (the styleguide has about 100 000).
     *
     * @return \Generator<array{string, string, string}>
     */
    private static function rows(Node $node, string $path = ''): \Generator
    {
        $i = 0;
        foreach ($node->childNodes as $child) {
            $here = $path.'/'.++$i;
            if ($child instanceof Text) {
                yield [$here, '#text', $child->data];
            } elseif ($child instanceof Element) {
                $here .= ':'.strtolower($child->localName);
                foreach ($child->attributes as $attribute) {
                    yield [$here, '@'.$attribute->name, $attribute->value];
                }
                if ($child instanceof HTMLElement && strtolower($child->localName) === 'template' && isset($child->content)) {
                    yield from self::rows($child->content, $here.'/#content');
                }
                yield from self::rows($child, $here);
            }
        }
    }

    // ------------------------------------------------------------------------------------------ the response hook

    private function get2(string $path): string
    {
        return $this->withUnencryptedCookies(['summit_design' => '2'])->get($path)->getContent();
    }

    public function test_design_two_arabic_pages_show_one_digit_style_western_by_default(): void
    {
        app()->setLocale('ar');
        $html = $this->get2('/ar/contact/');
        $this->assertMatchesRegularExpression('/<html lang="ar" dir="rtl"[^>]* data-design="2"[^>]* data-digits="western">/', $html);

        $hours = content()->page('P07', 'ar')['content']['details']['hours'];
        $this->assertMatchesRegularExpression('/[\x{0660}-\x{0669}]/u', $hours, 'the approved Contact line is written with ٩ and ٦');
        // the contact card prints it with each time bound to صباحًا / مساءً (Hours::keepTimes()); the conversion
        // changes the digits only, so the no-break spaces stay where they are
        $this->assertStringContainsString(e(Digits::localise(Hours::keepTimes($hours), 'ar')), $html);
        $this->assertStringContainsString("من 9\u{00A0}صباحًا إلى 6\u{00A0}مساءً بتوقيت الإمارات", $html);
        $this->assertStringNotContainsString(e(Hours::keepTimes($hours)), $html);

        // no Eastern digit in the text or the spoken attributes (scripts and JSON keep theirs)
        $visible = (string) preg_replace('~<script\b.*?</script>~s', '', $html);
        $this->assertDoesNotMatchRegularExpression('/[\x{0660}-\x{0669}]/u', $visible);
    }

    public function test_eastern_setting_converts_the_page_except_left_to_right_runs(): void
    {
        config(['design.d2.arabic_digits' => 'eastern']);
        app()->setLocale('ar');
        $html = $this->get2('/ar/');
        $this->assertMatchesRegularExpression('/<html lang="ar"[^>]* data-digits="eastern">/', $html);

        $home = content()->page('P01', 'ar')['content']['contact']['hours'];
        $this->assertStringContainsString('من 9 صباحًا إلى 6 مساءً', $home, 'the approved homepage line is written with 9 and 6');
        $this->assertStringContainsString("من ٩\u{00A0}صباحًا إلى ٦\u{00A0}مساءً", $html);
        $this->assertStringContainsString('<bdi class="contact-card__value" dir="ltr">+971 52 466 6191</bdi>', $html, 'the phone number stays left to right');
        $this->assertStringContainsString('info@summitlegaluae.com', $html);
        $this->assertStringContainsString('href="tel:+971524666191"', $html, 'links are never touched');
        $this->assertStringContainsString(e(setting('whatsapp_url')), $html, 'nor the WhatsApp draft');
        // counts isolated with a bare <bdi> follow the setting like the sentence around them
        $this->assertDoesNotMatchRegularExpression('~<bdi>\s*[0-9]+\s*</bdi>~', $html);
    }

    public function test_design_one_english_russian_and_other_responses_pass_through_untouched(): void
    {
        // Design 1 (the default design in the test environment): no attribute, nothing converted.
        $d1 = $this->get('/ar/')->getContent();
        $this->assertStringNotContainsString('data-design="2"', $d1);
        $this->assertStringNotContainsString('data-digits', $d1);

        foreach (['en', 'ru'] as $locale) {
            app()->setLocale($locale);
            $this->assertStringNotContainsString('data-digits', $this->get2("/{$locale}/"), $locale);
        }

        // The middleware itself returns every non-Design 2 Arabic response as it is.
        $hook = new Digits;
        $request = Request::create('/ar/');
        $design1 = '<!DOCTYPE html><html lang="ar" dir="rtl"><body><p>٩ و 6</p></body></html>';
        $this->assertSame($design1, $hook->handle($request, fn () => new Response($design1))->getContent());
        $json = new JsonResponse(['html' => '<html lang="ar" data-design="2"><p>٩</p>']);
        $this->assertSame($json->getContent(), $hook->handle($request, fn () => $json)->getContent());
        $text = new Response('<html lang="ar" data-design="2"><p>٩</p>', 200, ['Content-Type' => 'text/plain']);
        $this->assertSame('<html lang="ar" data-design="2"><p>٩</p>', $hook->handle($request, fn () => $text)->getContent());
        $stream = new StreamedResponse(fn () => print ('٩'));
        $this->assertSame($stream, $hook->handle($request, fn () => $stream));

        // ... and converts a Design 2 Arabic one, dropping a stale Content-Length.
        $d2 = new Response('<html lang="ar" data-design="2"><p>٩</p>', 200, ['Content-Length' => '40']);
        $out = $hook->handle($request, fn () => $d2);
        $this->assertSame('<html lang="ar" data-design="2" data-digits="western"><p>9</p>', $out->getContent());
        $this->assertFalse($out->headers->has('Content-Length'));
    }

    public function test_error_pages_follow_the_setting_too(): void
    {
        app()->setLocale('ar');
        $response = $this->withUnencryptedCookies(['summit_design' => '2'])->get('/ar/no-such-page/');
        $response->assertNotFound();
        $this->assertMatchesRegularExpression('/<html lang="ar"[^>]* data-design="2"[^>]* data-digits="western">/', $response->getContent());
    }

    public function test_the_arabic_legal_pages_show_their_eastern_digits_only_with_the_eastern_setting(): void
    {
        app()->setLocale('ar');
        $updated = content()->page('P08', 'ar')['last_updated'];
        $this->assertStringContainsString('٢٣ أكتوبر ٢٠٢٦', $updated);

        $western = $this->get2('/ar/legal-disclaimer/');
        $this->assertStringContainsString('23 أكتوبر 2026', $western);
        $this->assertStringNotContainsString('٢٣ أكتوبر ٢٠٢٦', $western);

        config(['design.d2.arabic_digits' => 'eastern']);
        $eastern = $this->get2('/ar/legal-disclaimer/');
        $this->assertStringContainsString('٢٣ أكتوبر ٢٠٢٦', $eastern);
        $this->assertStringContainsString('© ٢٠', $eastern, 'the copyright year too');
    }

    public function test_the_styleguides_show_their_arabic_specimens_with_the_digits_of_the_arabic_pages(): void
    {
        // The inner kit's specimens in the other direction print the approved Contact copy, written with ١. and ٩ / ٦.
        $contact = content()->page('P07', 'ar')['content'];
        $step = (string) $contact['after']['steps'][0]['title'];
        $hours = (string) $contact['details']['hours'];
        $this->assertMatchesRegularExpression('/^[\x{0660}-\x{0669}]/u', $step);
        $this->assertMatchesRegularExpression('/[\x{0660}-\x{0669}]/u', $hours);

        foreach (['en', 'ru'] as $locale) {
            app()->setLocale($locale);
            $html = $this->get2("/{$locale}/styleguide/");
            $this->assertMatchesRegularExpression('/<html lang="'.$locale.'"[^>]* data-design="2"/', $html);
            $this->assertStringNotContainsString('data-digits', $html, "{$locale}: the page keeps its <html> tag");
            // 0-9 by default, as /ar/contact/ prints them
            $this->assertStringContainsString(e(Digits::localise($step, 'ar')), $html, "{$locale}: the step");
            $this->assertStringContainsString(e(Digits::localise(Hours::keepTimes($hours), 'ar')), $html, "{$locale}: the hours line");
            // no Eastern digit in the text or the attributes but the search specimen shown as typed (dir="auto")
            $visible = (string) preg_replace(['~<script\b.*?</script>~s', '~<(\w+)\b[^>]*\bdir="auto"[^>]*>.*?</\1>~su'], '', $html);
            $this->assertDoesNotMatchRegularExpression('/[\x{0660}-\x{0669}]/u', $visible, $locale);
        }

        // ... and ٠-٩ with the eastern setting, while the English text around them keeps 0-9
        config(['design.d2.arabic_digits' => 'eastern']);
        app()->setLocale('en');
        $html = $this->get2('/en/styleguide/');
        $this->assertStringContainsString(e(Digits::localise($step, 'ar', Digits::EASTERN)), $html);
        $this->assertStringContainsString(e(Digits::localise(Hours::keepTimes($hours), 'ar', Digits::EASTERN)), $html);
        $this->assertStringContainsString(e(Hours::keepTimes(Hours::sentence('contact', 'en'))), $html, 'the English card: 9 am, 6 pm');
        $this->assertStringNotContainsString('data-digits', $html);
    }
}
