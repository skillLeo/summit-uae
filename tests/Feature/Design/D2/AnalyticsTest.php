<?php

namespace Tests\Feature\Design\D2;

use App\Content\Cms\Editing\Preview;
use App\Models\Content\Page;
use App\Models\User;
use App\Settings\IntegrationSettings;
use App\Support\Analytics;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Google Analytics 4 (docs/briefs/design2-only-black-header-ga4.md Part C; App\Support\Analytics,
 * d2::partials.analytics, d2::partials.consent): the tag, the consent bar, the footer's Privacy Settings button and
 * Google's CSP origins exist only in production, with a valid measurement ID, on public pages, for visitors who are
 * not signed in. Never on the admin, its login, a signed CMS preview, an error or offline page; an empty or invalid ID
 * prints nothing. The Integrations setting wins over GA4_MEASUREMENT_ID.
 */
final class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'G-TEST123ABC';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
        config(['services.ga4.id' => self::ID]);
    }

    private function production(): void
    {
        $this->app['env'] = 'production';
    }

    /** Design 2 (the summit_design cookie selects it while Design 1 exists; it is ignored once Design 2 is the only design). */
    private function visit(string $path): TestResponse
    {
        return $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])->get($path);
    }

    private function assertTag(TestResponse $response, string $id = self::ID): string
    {
        $html = (string) $response->getContent();
        $this->assertSame(1, substr_count($html, 'https://www.googletagmanager.com/gtag/js?id='.$id.'"'), 'one tag with the ID');
        $this->assertSame(1, substr_count($html, "gtag('config', id,"), 'one config call');
        $this->assertStringContainsString("gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });", $html);
        $this->assertStringContainsString('})("'.$id.'", [', $html, 'the ID is a JSON string in the script');
        $this->assertStringNotContainsString('gtm.js', $html, 'gtag.js, not the Tag Manager container');
        $this->assertMatchesRegularExpression('/<script async src="https:\/\/www\.googletagmanager\.com\/gtag\/js\?id='.$id.'" nonce="[^"]+"><\/script>\s*<script nonce="[^"]+">/', $html, 'both scripts carry the CSP nonce');
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src [^;]*https:\/\/www\.googletagmanager\.com/", $csp);
        $this->assertMatchesRegularExpression("/connect-src [^;]*https:\/\/\*\.google-analytics\.com [^;]*https:\/\/\*\.analytics\.google\.com/", $csp);
        $this->assertMatchesRegularExpression("/img-src [^;]*https:\/\/\*\.google-analytics\.com/", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);

        return $html;
    }

    private function assertNoTag(TestResponse $response, string $where): void
    {
        $html = (string) $response->getContent();
        foreach (['googletagmanager', 'gtag(', 'data-consent', 'data-consent-open'] as $needle) {
            $this->assertStringNotContainsString($needle, $html, "{$where}: {$needle}");
        }
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('google', $csp, "{$where}: the CSP stays closed to Google");
    }

    public function test_the_tag_consent_bar_and_csp_appear_only_in_production_on_public_pages(): void
    {
        // Outside production: nothing, whatever the ID.
        $this->assertNoTag($this->visit('/en/')->assertOk(), 'testing environment');

        $this->production();
        foreach (['/en/', '/ar/', '/ru/', '/en/contact/', '/ar/about/', '/ru/practice-areas/criminal-cases/'] as $path) {
            $html = $this->assertTag($this->visit($path)->assertOk());
            // The bar: hidden until the module reads the choice, approved copy only, two equal buttons, the policy link.
            $this->assertSame(1, preg_match('/<section class="consent" id="consent" aria-labelledby="consent-title" data-consent hidden[^>]*>(.*?)<\/section>/s', $html, $bar), $path);
            $locale = substr($path, 1, 2);
            $this->assertStringContainsString(e(ui('cookies.banner_title', $locale)), $bar[1]);
            $this->assertStringContainsString(e(ui('cookies.banner_text', $locale)), $bar[1]);
            $this->assertStringContainsString('href="'.target_url('L01', $locale).'">'.e(ui('cookies.policy_link', $locale)).'</a>', $bar[1]);
            $this->assertSame(2, preg_match_all('/<button class="btn btn--ink btn--44 consent__button" type="button" data-consent-choice="(denied|granted)">/', $bar[1], $choices), $path);
            $this->assertSame(['denied', 'granted'], $choices[1]);
            $this->assertStringContainsString(e(ui('cookies.reject_optional', $locale)), $bar[1]);
            $this->assertStringContainsString(e(ui('cookies.accept_optional', $locale)), $bar[1]);
            $this->assertStringNotContainsString('data-copy-status', $bar[1], 'approved copy (L01), nothing pending');
            // The footer's approved Privacy Settings button reopens it.
            $this->assertMatchesRegularExpression('/<button type="button" class="d2-footer__link consent-open" data-consent-open aria-controls="consent" aria-expanded="false">'.preg_quote(e(ui('cookies.footer_button', $locale)), '/').'<\/button>/', $html);
            // The bar comes right after the skip link, before the header.
            $this->assertLessThan(strpos($html, '<header'), strpos($html, 'data-consent hidden'));
            $this->assertGreaterThan(strpos($html, 'class="skip-link"'), strpos($html, 'data-consent hidden'));
        }

        // The root chooser (no single language): the tag, no bar.
        $chooser = $this->assertTag($this->visit('/')->assertOk());
        $this->assertStringNotContainsString('data-consent', $chooser);
    }

    public function test_never_on_the_admin_its_login_error_offline_or_preview_pages(): void
    {
        $this->production();

        $this->assertNoTag($this->get('/admin/login')->assertOk(), 'admin login');
        $this->assertNoTag($this->visit('/en/no-such-page/')->assertNotFound(), 'unmatched URL (404)');
        $this->assertNoTag($this->visit('/xx/')->assertNotFound(), 'unknown language (404)');
        // A matched route that answers 404 (the calculators page while its copy is pending: hidden in production).
        $this->assertNoTag($this->visit('/en/calculators/')->assertNotFound(), 'abort(404) on a matched route');
        $this->assertNoTag($this->visit('/en/offline/')->assertOk(), 'offline page');

        // A public page next to them still has it.
        $this->assertTag($this->visit('/en/')->assertOk());

        // Last: a signed preview leaves the test's shared content repository in preview mode.
        $this->assertNoTag($this->visit('/en/?cms_preview=page:P01')->assertForbidden(), 'a broken preview link (403)');
        $preview = Preview::url(Page::query()->findOrFail('P01'), 'en');
        $path = parse_url($preview, PHP_URL_PATH).'?'.parse_url($preview, PHP_URL_QUERY);
        $this->assertNoTag($this->visit($path)->assertOk(), 'signed CMS preview');
    }

    public function test_never_for_a_signed_in_admin(): void
    {
        $this->production();
        $admin = $this->grantRole(User::factory()->create(), 'super_admin');

        $this->actingAs($admin);
        $this->assertNoTag($this->visit('/en/')->assertOk(), 'signed-in super admin');
        $this->assertNoTag($this->get('/admin/settings/integrations')->assertOk(), 'admin screen');

        auth()->logout();
        $this->assertTag($this->visit('/en/')->assertOk());
    }

    public function test_an_empty_or_invalid_id_prints_nothing_and_the_setting_wins_over_the_environment(): void
    {
        $this->production();

        foreach (['', '   ', 'G-12345', 'G-1234567890123', 'g-test123abc', 'UA-1234567-1', 'GTM-ABC1234', 'G-TEST123ABC"><script>', 'G-TEST 123'] as $id) {
            config(['services.ga4.id' => $id]);
            $this->assertNull(Analytics::measurementId(), "invalid: {$id}");
            $this->assertNoTag($this->visit('/en/')->assertOk(), "ID {$id}");
        }
        config(['services.ga4.id' => null]);
        $this->assertNoTag($this->visit('/en/')->assertOk(), 'no ID at all');

        // The environment value is the fallback; a stored Integrations value replaces it.
        config(['services.ga4.id' => ' G-ENVVALUE1 ']);
        $this->assertTag($this->visit('/en/')->assertOk(), 'G-ENVVALUE1');

        app(IntegrationSettings::class)->fill(['ga4_measurement_id' => 'G-SETTING42'])->save();
        $html = $this->assertTag($this->visit('/en/')->assertOk(), 'G-SETTING42');
        $this->assertStringNotContainsString('G-ENVVALUE1', $html);

        // An invalid stored value is not replaced by the environment: nothing at all.
        app(IntegrationSettings::class)->fill(['ga4_measurement_id' => 'G-bad'])->save();
        $this->assertNoTag($this->visit('/en/')->assertOk(), 'invalid stored ID');

        // Cleared: the environment again.
        app(IntegrationSettings::class)->fill(['ga4_measurement_id' => null])->save();
        $this->assertTag($this->visit('/en/')->assertOk(), 'G-ENVVALUE1');
    }

    public function test_the_pattern(): void
    {
        foreach (['G-ABC123', 'G-ABCDEF123456', 'G-YDERNR1R8D', 'G-YDFRNR1R8D', 'G-0000000'] as $id) {
            $this->assertTrue(Analytics::valid($id), $id);
        }
        foreach (['G-ABC12', 'G-ABCDEF1234567', 'G-abc123', 'G_ABC123', 'ABC123', 'G-ABC123 ', "G-ABC123\n", 'G-ÄBC123', null, 123] as $id) {
            $this->assertFalse(Analytics::valid($id), var_export($id, true));
        }
    }
}
