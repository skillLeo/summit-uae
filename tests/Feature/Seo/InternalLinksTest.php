<?php

namespace Tests\Feature\Seo;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client PDF item 28 (the full crawl is scripts/qa/links.mjs): no internal link to an old numbered or WordPress
 * address, and a record that is no longer published (Legal Translation once it is removed) leaves the navigation menus
 * instead of staying as an empty link to a page that answers 404.
 */
final class InternalLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    public function test_no_link_to_an_html_or_wordpress_address(): void
    {
        foreach (['/en/', '/ar/practice-areas/', '/ru/resources/', '/en/faq/', '/ar/about/'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            preg_match_all('/<a\b[^>]*\bhref="([^"]+)"/', $html, $m);
            foreach ($m[1] as $href) {
                $this->assertDoesNotMatchRegularExpression('#\.html?(?:$|[?\#])|/wp-(?:content|admin|json)|[?&](?:p|page_id)=\d|/index\.php#i', $href, "{$path}: {$href}");
            }
        }
    }

    public function test_an_unpublished_practice_area_leaves_the_menus(): void
    {
        // Legal Translation is unpublished (client PDF item 9, owner decision 6): no link to it, and its old address
        // answers a temporary 302 to Practice Areas.
        $this->get('/en/practice-areas/legal-translation/')->assertStatus(302)->assertRedirect(\App\Support\SiteUrl::page('P02', 'en'));
        foreach (['/en/about/', '/ar/', '/ru/practice-areas/labor-law/', '/en/practice-areas/'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $this->assertStringNotContainsString('/practice-areas/legal-translation/"', $html, $path);
            $this->assertDoesNotMatchRegularExpression('#<a class="d2-footer__link" href="[^"]*"></a>#', $html, "{$path}: no empty footer link");
        }
    }
}
