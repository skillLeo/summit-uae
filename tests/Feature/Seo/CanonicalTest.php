<?php

namespace Tests\Feature\Seo;

use App\Support\SitePages;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client PDF item 21: every current page has one correct, unique canonical URL on the production host
 * (config summit.canonical_host, https://www.summitlegaluae.com), in its folder form, without a query string; pages
 * that are never indexed (404, calculators, styleguide, offline) have none.
 */
final class CanonicalTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'https://www.summitlegaluae.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    /** @return list<string> */
    private function canonicals(string $html): array
    {
        preg_match_all('#<link rel="canonical" href="([^"]+)">#', $html, $m);

        return $m[1];
    }

    public function test_every_current_page_has_one_self_referencing_canonical_and_they_are_unique(): void
    {
        $seen = [];
        $pages = SitePages::all();
        $this->assertCount(20, $pages, 'home, P02-P08, L01, L02 and the ten practice areas (Legal Translation left: client PDF item 9)');
        foreach ($pages as $page) {
            foreach ($page['locales'] as $locale) {
                $path = '/'.$locale.'/'.$page['path'];
                $html = $this->get($path)->assertOk()->getContent();
                $canonicals = $this->canonicals($html);
                $this->assertSame([self::HOST.$path], $canonicals, $path);
                $this->assertStringNotContainsString('<meta name="robots" content="noindex', $html, $path);
                $this->assertArrayNotHasKey($canonicals[0], $seen, $path.' repeats the canonical of '.($seen[$canonicals[0]] ?? ''));
                $seen[$canonicals[0]] = $path;
            }
        }
        $this->assertCount(60, $seen);
        foreach (array_keys($seen) as $url) {
            $this->assertMatchesRegularExpression('#^https://www\.summitlegaluae\.com/(en|ar|ru)/([a-z0-9-]+/)*$#', $url, 'folder URL, no .html, no query');
        }
    }

    public function test_query_strings_never_reach_the_canonical(): void
    {
        foreach ([
            '/en/?navigator=S05-2' => '/en/',
            '/ar/?utm_source=whatsapp&utm_medium=share' => '/ar/',
            '/ru/contact/?service=S04' => '/ru/contact/',
            '/en/practice-areas/labor-law/?x=1#faq' => '/en/practice-areas/labor-law/',
            '/en/faq/?q=cheque' => '/en/faq/',
        ] as $uri => $path) {
            $this->assertSame([self::HOST.$path], $this->canonicals($this->get($uri)->assertOk()->getContent()), $uri);
        }
    }

    public function test_the_host_comes_from_the_configuration_never_from_the_request(): void
    {
        $html = $this->withServerVariables(['HTTP_HOST' => 'moccasin-turtle-185698.hostingersite.com'])->get('/en/resources/')->getContent();
        $this->assertSame([self::HOST.'/en/resources/'], $this->canonicals($html));

        config(['summit.canonical_host' => 'https://www.summitlegaluae.com/']);
        $this->assertSame([self::HOST.'/en/about/'], $this->canonicals($this->get('/en/about/')->getContent()), 'a trailing slash in the setting is ignored');
    }

    public function test_pages_that_are_never_indexed_have_no_canonical(): void
    {
        foreach (['/en/no-such-page/' => 404, '/en/styleguide/' => 200, '/en/offline/' => 200] as $uri => $status) {
            $html = $this->get($uri)->assertStatus($status)->getContent();
            $this->assertSame([], $this->canonicals($html), $uri);
            $this->assertStringContainsString('<meta name="robots" content="noindex', $html, $uri);
        }
    }
}
