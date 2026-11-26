<?php

namespace Tests\Feature;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SiteTest extends TestCase
{
    use RefreshDatabase;

    private const H1 = [
        'en' => 'Clear Legal Guidance.',
        'ar' => 'توجيه قانوني واضح.',
        'ru' => 'Понятная юридическая поддержка.', // client PDF item 4 (client layer)
    ];

    /** Fragments that only appear in red designer notes. */
    private const NOTE_FRAGMENTS = ['Same tab', 'H-L0', 'See the separate sitemap', 'Open in a new tab', 'Launch the matching handler', '[[RED'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_home_renders_in_each_language_with_one_h1_and_correct_direction(): void
    {
        foreach (self::H1 as $locale => $h1) {
            $html = $this->get("/{$locale}/")->assertOk()->getContent();
            $dir = $locale === 'ar' ? 'rtl' : 'ltr';
            $this->assertStringContainsString("<html lang=\"{$locale}\" dir=\"{$dir}\"", $html);
            $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html), "{$locale}: exactly one <h1>");
            $this->assertStringContainsString($h1, $html);
            $this->assertStringNotContainsString('⟦', $html, "{$locale}: missing content marker");
            foreach (['id="main-content"', 'id="consultation-form"', 'id="navigator"'] as $anchor) {
                $this->assertStringContainsString($anchor, $html);
            }
            $this->assertStringContainsString('<link rel="canonical" href="https://www.summitlegaluae.com/'.$locale.'/">', $html);
            $this->assertStringContainsString('hreflang="x-default" href="https://www.summitlegaluae.com/en/"', $html);
            foreach (['en', 'ar', 'ru'] as $alt) {
                $this->assertStringContainsString('hreflang="'.$alt.'" href="https://www.summitlegaluae.com/'.$alt.'/"', $html);
            }
        }
    }

    public function test_public_pages_contain_no_designer_note_text(): void
    {
        foreach (['/en/', '/ar/', '/ru/', '/en/styleguide/', '/en/practice-areas/labor-law/'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            foreach (self::NOTE_FRAGMENTS as $fragment) {
                $this->assertStringNotContainsString($fragment, strip_tags($html), "{$url} leaks: {$fragment}");
            }
        }
    }

    public function test_urls_without_a_trailing_slash_redirect_permanently(): void
    {
        // Root-relative Location (SiteUrl::redirectTarget(): never built from the Host header).
        $this->get('/en')->assertStatus(301)->assertHeader('Location', '/en/');
        $this->get('/ar/styleguide')->assertStatus(301)->assertHeader('Location', '/ar/styleguide/');
        $this->get('/ru/practice-areas/labor-law?x=1')->assertStatus(301)->assertHeader('Location', '/ru/practice-areas/labor-law/?x=1');
    }

    public function test_missing_urls_return_a_real_404_in_the_url_language(): void
    {
        $res = $this->get('/ar/does-not-exist/')->assertStatus(404);
        $html = $res->getContent();
        $this->assertStringContainsString('<html lang="ar" dir="rtl"', $html);
        $this->assertStringContainsString('noindex', $html);
        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringNotContainsString('does-not-exist', strip_tags($html), 'The missing URL must not be echoed');

        $this->get('/en/practice-areas/not-a-service/')->assertStatus(404);
        $this->get('/fr/')->assertStatus(404);
    }

    public function test_navigator_works_without_javascript(): void
    {
        $html = $this->get('/en/?navigator=S05-2')->assertOk()->getContent();
        $this->assertStringContainsString('The sale or tenancy agreement and its schedules', $html);
        $this->assertStringContainsString('value="S05-2"', $html);
        $this->assertMatchesRegularExpression('/value="S05-2"\s+checked/', $html);
        // Query-string variants name the clean page as their canonical URL (client PDF item 21).
        $this->assertStringContainsString('<link rel="canonical" href="https://www.summitlegaluae.com/en/">', $html);
    }

    public function test_navigator_data_covers_40_approved_situations(): void
    {
        $html = $this->get('/ru/')->assertOk()->getContent();
        $this->assertSame(40, preg_match_all('/name="navigator" value="S\d{2}-\d"/', $html)); // Legal Translation's four left with it (client PDF item 9)
    }

    public function test_every_registered_page_renders_as_an_indexable_page(): void
    {
        $html = $this->get('/en/practice-areas/real-estate-rental-disputes/')->assertOk()->getContent();
        $this->assertStringContainsString('Property and Tenancy Disputes in Dubai and the UAE', $html);
        // Design 2 renders every page in full: canonical, no noindex (client PDF item 21; tests/Feature/Seo).
        $this->assertStringNotContainsString('<meta name="robots" content="noindex', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://www.summitlegaluae.com/en/practice-areas/real-estate-rental-disputes/">', $html);
        foreach (['/ar/practice-areas/', '/ru/resources/', '/en/case-studies/', '/en/faq/', '/en/about/', '/en/contact/', '/en/legal-disclaimer/', '/en/privacy-policy/', '/en/terms-and-conditions/'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_root_is_a_permanent_redirect_to_the_english_home_page(): void
    {
        // Client PDF item 29 (tests/Feature/RootRedirectTest.php has the details): no chooser page any more.
        $this->get('/')->assertStatus(301)->assertHeader('Location', '/en/');
        $this->withUnencryptedCookie('summit_lang', 'ar')->get('/')->assertStatus(301)->assertHeader('Location', '/en/');
        // Accept-Language never redirects.
        $this->withHeaders(['Accept-Language' => 'ru'])->get('/')->assertStatus(301)->assertHeader('Location', '/en/');
        $this->withHeaders(['Accept-Language' => 'ru'])->get('/en/')->assertOk();
    }

    public function test_sitemap_lists_every_current_page_with_reciprocal_alternates(): void
    {
        // Client PDF item 23 (tests/Feature/Seo/SitemapTest.php has the details): 21 pages × 3 languages.
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertSame(60, substr_count($xml, '<loc>')); // 20 pages x 3 (Legal Translation left: client PDF item 9)
        $this->assertStringContainsString('<loc>https://www.summitlegaluae.com/ar/</loc>', $xml);
        $this->assertStringNotContainsString('styleguide', $xml);
        $this->assertSame(60 * 4, substr_count($xml, 'xhtml:link'));
    }

    public function test_security_headers_and_a_strict_nonce_csp(): void
    {
        $res = $this->get('/en/')->assertOk();
        $csp = $res->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /** Client PDF item 6: one English WhatsApp message on every page in every language; the phone uses tel:. */
    public function test_whatsapp_opens_the_one_english_message_in_every_language_and_phone_uses_tel(): void
    {
        $message = 'https://wa.me/971524666191?text='.rawurlencode('Hello Summit Legal Consultancy, I would like to request a legal consultation. Please contact me.');
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = $this->get("/{$locale}/")->getContent();
            $this->assertStringContainsString('href="'.e($message).'"', $html, $locale);
            $this->assertStringNotContainsString('text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7', $html, "{$locale}: no Arabic draft");
            $this->assertStringNotContainsString('text=%D0%97%D0%B4%D1%80%D0%B0%D0%B2', $html, "{$locale}: no Russian draft");
            $this->assertStringContainsString('href="tel:+971524666191"', $html);
            $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        }
    }

    /** The Contact page is released (2026-11-25: the form is gone, client PDF item 7): "Contact Us" opens it. */
    public function test_contact_navigation_targets_the_contact_page(): void
    {
        $html = $this->get('/ru/')->getContent();
        $this->assertStringContainsString('href="'.url('/').'/ru/contact/"', $html);
        $this->assertStringNotContainsString('href="'.url('/').'/ru/#consultation-form"', $html);
    }

    public function test_styleguide_renders_in_three_languages_and_is_not_indexed(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = $this->get("/{$locale}/styleguide/")->assertOk()->getContent();
            $this->assertStringContainsString('noindex', $html);
            $this->assertStringNotContainsString('⟦', $html);
        }
    }

    public function test_web_manifest_per_language(): void
    {
        $this->get('/ar/manifest.webmanifest')->assertOk()->assertJson(['lang' => 'ar', 'dir' => 'rtl', 'start_url' => '/ar/']);
    }

    public function test_a_malformed_navigator_link_is_ignored_instead_of_failing(): void
    {
        // Design 1.1 (review SEC-02): an array-shaped ?navigator used to end in an HTTP 500. Ignored: no file is
        // opened on the server (the response is HTML changed by the sprite and digits middleware, so not a view).
        foreach (['/en/?navigator[]=S05-2', '/ar/?navigator[x]=S05-2', '/ru/?navigator[][]=1'] as $uri) {
            $this->assertDoesNotMatchRegularExpression('/value="S05-2"\s+checked/', (string) $this->get($uri)->assertOk()->getContent(), $uri);
        }

        // A plain link still opens its starting file.
        $this->assertMatchesRegularExpression('/value="S05-2"\s+checked/', (string) $this->get('/en/?navigator=S05-2')->assertOk()->getContent());
    }
}
