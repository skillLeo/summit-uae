<?php

namespace Tests\Feature\Seo;

use App\Settings\SeoSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client PDF item 24: on the production site robots.txt is exactly
 *
 *     User-agent: *
 *     Allow: /
 *
 *     Sitemap: https://www.summitlegaluae.com/sitemap.xml
 *
 * with no Disallow rule. The test site stays out of search results: SUMMIT_NOINDEX (config summit.noindex, on by
 * default when APP_ENV is staging) or the SEO screen's switch makes every page and response noindex, and robots.txt
 * then names no sitemap. Crawling stays allowed there, so crawlers can read the noindex.
 */
final class RobotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    public function test_production_rules(): void
    {
        config(['summit.noindex' => false]);
        $response = $this->get('/robots.txt')->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame("User-agent: *\nAllow: /\n\nSitemap: https://www.summitlegaluae.com/sitemap.xml\n", $response->getContent());
        $this->assertStringNotContainsString('Disallow', (string) $response->getContent());
        $this->assertFalse($response->headers->has('X-Robots-Tag'));
        $this->assertFileDoesNotExist(public_path('robots.txt'), 'a static file would be served instead of the route');
    }

    public function test_the_test_site_is_noindex_everywhere_and_names_no_sitemap(): void
    {
        config(['summit.noindex' => true]);
        $robots = (string) $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringNotContainsString('Sitemap:', $robots);
        $this->assertStringNotContainsString('Disallow: /en/', $robots);

        $response = $this->get('/en/resources/')->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $html = (string) $response->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
        // The canonical and hreflang links stay (they name the production pages), so the head can be checked on it.
        $this->assertStringContainsString('<link rel="canonical" href="https://www.summitlegaluae.com/en/resources/">', $html);
    }

    public function test_the_seo_screen_switch_also_closes_the_site(): void
    {
        config(['summit.noindex' => false]);
        $settings = app(SeoSettings::class);
        $settings->robots_index = false;
        $settings->save();
        content()->flush();

        $this->assertStringNotContainsString('Sitemap:', (string) $this->get('/robots.txt')->getContent());
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', (string) $this->get('/ar/')->getContent());
    }

    public function test_staging_defaults_to_noindex(): void
    {
        $config = fn (string $env, ?string $noindex) => (function () use ($env, $noindex) {
            $previous = [getenv('APP_ENV'), getenv('SUMMIT_NOINDEX')];
            putenv("APP_ENV={$env}");
            $noindex === null ? putenv('SUMMIT_NOINDEX') : putenv("SUMMIT_NOINDEX={$noindex}");
            $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $env;
            if ($noindex === null) {
                unset($_ENV['SUMMIT_NOINDEX'], $_SERVER['SUMMIT_NOINDEX']);
            } else {
                $_ENV['SUMMIT_NOINDEX'] = $_SERVER['SUMMIT_NOINDEX'] = $noindex;
            }
            try {
                return (require config_path('summit.php'))['noindex'];
            } finally {
                putenv('APP_ENV='.$previous[0]);
                $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = (string) $previous[0];
                $previous[1] === false ? putenv('SUMMIT_NOINDEX') : putenv('SUMMIT_NOINDEX='.$previous[1]);
                unset($_ENV['SUMMIT_NOINDEX'], $_SERVER['SUMMIT_NOINDEX']);
            }
        })();

        $this->assertTrue($config('staging', null), 'staging without SUMMIT_NOINDEX: noindex');
        $this->assertFalse($config('staging', 'false'), 'an explicit value wins');
        $this->assertFalse($config('production', null), 'production: indexable');
        $this->assertTrue($config('production', 'true'), 'SUMMIT_NOINDEX=true closes any environment');
    }
}
