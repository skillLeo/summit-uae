<?php

namespace Tests\Feature\Seo;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Client PDF item 33: a missing page answers a real HTTP 404 (never a 200 or a redirect to the home page), in the
 * language of its address, noindex, with no canonical, hreflang, og:url or structured data, and the 404 page is never
 * linked from the navigation or listed in the sitemap. The existing Design 2 404 page (U01) is kept.
 */
final class NotFoundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    public function test_missing_addresses_answer_404_noindex_without_seo_links(): void
    {
        foreach ([
            '/en/no-such-page/' => 'en',
            '/ar/practice-areas/no-such-service/' => 'ar',
            '/ru/resources/old-guide/' => 'ru',
            '/fr/' => 'en',
            '/wp-admin/' => 'en',
            '/14-resources.html' => 'en',
        ] as $uri => $locale) {
            $html = (string) $this->get($uri)->assertNotFound()->getContent();
            $this->assertStringContainsString('<html lang="'.$locale.'"', $html, $uri);
            $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html, $uri);
            foreach (['rel="canonical"', 'rel="alternate" hreflang', 'og:url', 'application/ld+json'] as $absent) {
                $this->assertStringNotContainsString($absent, $html, "{$uri}: {$absent}");
            }
            $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html), "{$uri}: one H1");
        }
    }

    public function test_an_unpublished_page_is_a_404_too(): void
    {
        DB::table('pages')->where('code', 'P04')->update(['status' => 'draft']);
        content()->flush();
        $this->get('/en/case-studies/')->assertNotFound();
        $this->assertStringNotContainsString('/case-studies/', (string) $this->get('/sitemap.xml')->getContent());
    }

    public function test_the_404_page_is_neither_linked_nor_listed(): void
    {
        $sitemap = (string) $this->get('/sitemap.xml')->getContent();
        $this->assertDoesNotMatchRegularExpression('#/(404|not-found|no-such-page)[/<]#', $sitemap);
        foreach (['/en/', '/ar/', '/ru/'] as $home) {
            $this->assertDoesNotMatchRegularExpression('#href="[^"]*/(404|not-found)/?"#', (string) $this->get($home)->getContent(), $home);
        }
    }
}
