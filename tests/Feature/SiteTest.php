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
        'ru' => 'Чёткие правовые рекомендации.',
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
        // (assertRedirect() would normalise the expected URL through url(), which drops the slash.)
        $this->get('/en')->assertStatus(301)->assertHeader('Location', url('/').'/en/');
        $this->get('/ar/styleguide')->assertStatus(301)->assertHeader('Location', url('/').'/ar/styleguide/');
        $this->get('/ru/practice-areas/labor-law?x=1')->assertStatus(301)->assertHeader('Location', url('/').'/ru/practice-areas/labor-law/?x=1');
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
        $this->assertStringContainsString('noindex', $html, 'Query-string variants are not indexed');
    }

    public function test_navigator_data_covers_44_approved_situations(): void
    {
        $html = $this->get('/ru/')->assertOk()->getContent();
        $this->assertSame(44, preg_match_all('/name="navigator" value="S\d{2}-\d"/', $html));
    }

    public function test_phase_2_routes_render_an_honest_noindex_preview(): void
    {
        $html = $this->get('/en/practice-areas/real-estate-rental-disputes/')->assertOk()->getContent();
        $this->assertStringContainsString('Property and Tenancy Disputes in Dubai and the UAE', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
        foreach (['/ar/practice-areas/', '/ru/resources/', '/en/case-studies/', '/en/faq/', '/en/about/', '/en/contact/', '/en/legal-disclaimer/', '/en/privacy-policy/', '/en/terms-and-conditions/'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_root_offers_a_language_choice_and_redirects_only_to_a_remembered_choice(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        foreach (['en', 'ar', 'ru'] as $code) {
            $this->assertStringContainsString('href="'.url('/').'/'.$code.'/"', $html);
        }
        $this->withUnencryptedCookie('summit_lang', 'ar')->get('/')->assertStatus(302)->assertHeader('Location', url('/').'/ar/');
        $this->withUnencryptedCookie('summit_lang', 'xx')->get('/')->assertOk();
        // Accept-Language never redirects.
        $this->withHeaders(['Accept-Language' => 'ru'])->get('/')->assertOk();
        $this->withHeaders(['Accept-Language' => 'ru'])->get('/en/')->assertOk();
    }

    public function test_sitemap_lists_only_released_pages_with_reciprocal_alternates(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertSame(3, substr_count($xml, '<loc>'));
        $this->assertStringContainsString('<loc>https://www.summitlegaluae.com/ar/</loc>', $xml);
        $this->assertStringNotContainsString('styleguide', $xml);
        $this->assertSame(12, substr_count($xml, 'xhtml:link'));
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

    public function test_whatsapp_uses_the_draft_of_the_page_language_and_phone_uses_tel(): void
    {
        $ar = $this->get('/ar/')->getContent();
        $this->assertStringContainsString('https://wa.me/971524666191?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7', $ar);
        $this->assertStringNotContainsString('text=Hello%20Summit', $ar);
        $en = $this->get('/en/')->getContent();
        $this->assertStringContainsString('https://wa.me/971524666191?text=Hello%20Summit%20Legal%20Consultancy', $en);
        $this->assertStringContainsString('href="tel:+971524666191"', $en);
        $this->assertStringContainsString('rel="noopener noreferrer"', $en);
    }

    public function test_contact_navigation_targets_the_homepage_form_until_p07_is_released(): void
    {
        $html = $this->get('/ru/')->getContent();
        $this->assertStringContainsString('href="'.url('/').'/ru/#consultation-form"', $html);
        $this->assertStringNotContainsString('/ru/contact/"', $html);
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
        // Design 1.1 (review SEC-02): an array-shaped ?navigator used to end in an HTTP 500.
        foreach (['/en/?navigator[]=S05-2', '/ar/?navigator[x]=S05-2', '/ru/?navigator[][]=1'] as $uri) {
            $this->get($uri)->assertOk()->assertViewHas('selected', null);
        }

        // A plain link still opens its starting file.
        $this->get('/en/?navigator=S05-2')->assertOk()
            ->assertViewHas('selected', fn ($selected) => $selected['service'] === 'S05' && $selected['index'] === 2);
    }

    public function test_header_menus_close_only_on_clicks_outside_the_whole_component(): void
    {
        // Design 1.1 (review A11Y-01, LANG-01, SEC-01): click.outside sits on the component root, which also
        // holds the toggle, so the click that opens the language menu or Practice Areas never closes it.
        $html = (string) $this->get('/en/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div class="lang-menu" x-data="disclosure"[^>]*x-on:click\.outside="close"/', $html);
        $this->assertMatchesRegularExpression('/<li class="site-nav__item"[^>]*x-data="disclosure"[^>]*x-on:click\.outside="close"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<ul class="lang-menu__list"[^>]*x-on:click\.outside/', $html);
        $this->assertDoesNotMatchRegularExpression('/<div class="nav-panel"[^>]*x-on:click\.outside/', $html);
    }
}
