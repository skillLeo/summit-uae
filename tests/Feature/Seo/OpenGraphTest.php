<?php

namespace Tests\Feature\Seo;

use App\Support\SitePages;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client PDF item 27: og:title, og:description, og:url, og:image (absolute https, 1200 × 630, with type and alt),
 * og:type, og:locale (+ the other languages), og:site_name and twitter:card summary_large_image on every page, in
 * the page language and specific to the page. In production og:url and og:image are on the production host; on the
 * test site they are on its own APP_URL, so a link shared from it previews the test page.
 */
final class OpenGraphTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    /** @return array<string, list<string>> property => contents */
    private function og(string $html): array
    {
        preg_match_all('#<meta (?:property|name)="((?:og|twitter):[a-z:_]+)" content="([^"]*)">#', $html, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as [, $key, $value]) {
            $out[$key][] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
        }

        return $out;
    }

    public function test_every_page_in_production_has_complete_page_specific_tags(): void
    {
        $this->app['env'] = 'production';
        $titles = [];
        foreach (SitePages::all() as $page) {
            foreach ($page['locales'] as $locale) {
                $path = "/{$locale}/{$page['path']}";
                $record = $page['type'] === 'page' ? content()->page($page['code'], $locale) : content()->service($page['code'], $locale);
                $og = $this->og((string) $this->get($path)->assertOk()->getContent());
                $this->assertSame([$record['seo_title']], $og['og:title'], $path);
                $this->assertSame([$record['meta_description']], $og['og:description'], $path);
                $this->assertSame(['https://www.summitlegaluae.com'.$path], $og['og:url'], $path);
                $this->assertSame(['website'], $og['og:type']);
                $this->assertSame([ui('brand.name', $locale)], $og['og:site_name']);
                $this->assertSame(['https://www.summitlegaluae.com/images/og-default.png'], $og['og:image']);
                $this->assertSame(['https://www.summitlegaluae.com/images/og-default.png'], $og['og:image:secure_url']);
                $this->assertSame(['image/png'], $og['og:image:type']);
                $this->assertSame(['1200'], $og['og:image:width']);
                $this->assertSame(['630'], $og['og:image:height']);
                $this->assertSame([ui('brand.logo_alt', $locale)], $og['og:image:alt']);
                $this->assertSame([config("summit.locales.{$locale}.og")], $og['og:locale']);
                $this->assertCount(2, $og['og:locale:alternate']);
                $this->assertNotContains(config("summit.locales.{$locale}.og"), $og['og:locale:alternate']);
                $this->assertSame(['summary_large_image'], $og['twitter:card']);
                $titles[] = $og['og:title'][0];
            }
        }
        $this->assertSame($titles, array_values(array_unique($titles)), 'page-specific');
    }

    public function test_the_default_image_is_a_real_1200_by_630_png(): void
    {
        $size = getimagesize(public_path('images/og-default.png'));
        $this->assertSame([1200, 630, 'image/png'], [$size[0], $size[1], $size['mime']]);
        $this->assertLessThan(300 * 1024, filesize(public_path('images/og-default.png')), 'WhatsApp previews images under 300 KB');
    }

    public function test_the_test_site_shares_its_own_addresses_never_the_request_host(): void
    {
        $this->app['env'] = 'staging';
        config(['app.url' => 'https://moccasin-turtle-185698.hostingersite.com']);
        $html = (string) $this->withServerVariables(['HTTP_HOST' => 'evil.example'])->get('/ru/faq/?utm_source=x')->getContent();
        $og = $this->og($html);
        $this->assertSame(['https://moccasin-turtle-185698.hostingersite.com/ru/faq/'], $og['og:url']);
        $this->assertSame(['https://moccasin-turtle-185698.hostingersite.com/images/og-default.png'], $og['og:image']);
        // The canonical link still names the production page.
        $this->assertStringContainsString('<link rel="canonical" href="https://www.summitlegaluae.com/ru/faq/">', $html);
    }

    public function test_noindex_pages_have_no_og_url(): void
    {
        $og = $this->og((string) $this->get('/en/no-such-page/')->assertNotFound()->getContent());
        $this->assertArrayNotHasKey('og:url', $og);
        $this->assertArrayHasKey('og:image', $og);
    }
}
