<?php

namespace Tests\Feature\Seo;

use App\Models\Content\Service;
use App\Settings\LanguageSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Client PDF item 23: https://www.summitlegaluae.com/sitemap.xml lists the current English, Arabic and Russian pages
 * only (21 pages × 3 languages today), each once, with reciprocal hreflang alternates; never the 404, offline,
 * styleguide, calculators, admin or preview addresses. The practice areas come from the published content, so an
 * unpublished one (Legal Translation once it is removed) leaves the sitemap by itself.
 */
final class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'https://www.summitlegaluae.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function sitemap(): \SimpleXMLElement
    {
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml, 'well-formed XML');
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');

        return $xml;
    }

    /** @return list<string> */
    private function locs(\SimpleXMLElement $xml): array
    {
        return array_map('strval', $xml->xpath('//s:url/s:loc'));
    }

    public function test_every_current_page_once_in_three_languages_and_nothing_else(): void
    {
        $locs = $this->locs($this->sitemap());
        $this->assertCount(60, $locs); // 20 pages x 3 languages (10 since Legal Translation left the site: client PDF item 9)
        $this->assertSame($locs, array_values(array_unique($locs)), 'no duplicate');

        $expected = ['', 'practice-areas/', 'resources/', 'case-studies/', 'faq/', 'about/', 'contact/', 'legal-disclaimer/', 'privacy-policy/', 'terms-and-conditions/'];
        foreach (config('summit.services') as $slug) {
            $expected[] = 'practice-areas/'.$slug.'/';
        }
        foreach (['en', 'ar', 'ru'] as $locale) {
            foreach ($expected as $path) {
                $this->assertContains(self::HOST.'/'.$locale.'/'.$path, $locs, "{$locale}/{$path}");
            }
        }
        foreach ($locs as $loc) {
            $this->assertMatchesRegularExpression('#^https://www\.summitlegaluae\.com/(en|ar|ru)/([a-z0-9-]+/)*$#', $loc);
            $this->assertDoesNotMatchRegularExpression('#/(styleguide|offline|calculators|admin|preview|no-such-page)/|\.html#', $loc);
            // Every listed URL is a live, indexable page.
            $html = $this->get(substr($loc, strlen(self::HOST)))->assertOk()->getContent();
            $this->assertStringContainsString('<link rel="canonical" href="'.$loc.'">', $html, $loc);
        }
    }

    public function test_alternates_are_reciprocal_with_x_default(): void
    {
        $xml = $this->sitemap();
        $sets = [];
        foreach ($xml->xpath('//s:url') as $url) {
            $url->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            $url->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
            $loc = (string) $url->xpath('s:loc')[0];
            $links = [];
            foreach ($url->xpath('x:link') as $link) {
                $this->assertSame('alternate', (string) $link['rel']);
                $links[(string) $link['hreflang']] = (string) $link['href'];
            }
            $this->assertSame(['en', 'ar', 'ru', 'x-default'], array_keys($links), $loc);
            $this->assertContains($loc, $links, "{$loc} names itself");
            $sets[$loc] = $links;
        }
        foreach ($sets as $loc => $links) {
            foreach (['en', 'ar', 'ru'] as $lang) {
                $this->assertSame($links, $sets[$links[$lang]], "{$loc} ↔ {$links[$lang]}");
            }
            $this->assertSame($links['en'], $links['x-default']);
        }
    }

    public function test_an_unpublished_practice_area_leaves_the_sitemap(): void
    {
        // Legal Translation (S10) once Track 1 removes it: unpublished in the CMS, so it is no current page.
        DB::table('services')->where('code', 'S10')->update(['status' => 'draft']);
        content()->flush();

        $locs = $this->locs($this->sitemap());
        $this->assertCount(60, $locs);
        foreach ($locs as $loc) {
            $this->assertStringNotContainsString('legal-translation', $loc);
        }
        $this->assertStringNotContainsString('legal-translation', (string) $this->get('/sitemap.xml')->getContent());
    }

    public function test_a_page_missing_in_a_language_is_listed_in_the_others_only(): void
    {
        $service = Service::query()->findOrFail('S06');
        foreach (['h1', 'title'] as $field) {
            $values = $service->getTranslations($field);
            unset($values['ru']);
            DB::table('services')->where('code', 'S06')->update([$field => json_encode($values, JSON_UNESCAPED_UNICODE)]);
        }
        content()->flush();

        $locs = $this->locs($this->sitemap());
        $this->assertContains(self::HOST.'/en/practice-areas/labor-law/', $locs);
        $this->assertNotContains(self::HOST.'/ru/practice-areas/labor-law/', $locs);
        $this->assertStringNotContainsString('href="'.self::HOST.'/ru/practice-areas/labor-law/"', (string) $this->get('/sitemap.xml')->getContent());
    }

    public function test_a_language_that_is_off_is_left_out(): void
    {
        $settings = app(LanguageSettings::class);
        $settings->enabled_locales = ['en', 'ar'];
        $settings->save();
        content()->flush();

        $xml = (string) $this->get('/sitemap.xml')->getContent();
        $this->assertStringNotContainsString('/ru/', $xml);
        $this->assertSame(40, substr_count($xml, '<loc>'));
    }

    public function test_the_sitemap_itself_is_not_indexed(): void
    {
        $this->get('/sitemap.xml')->assertHeader('X-Robots-Tag', 'noindex');
    }
}
