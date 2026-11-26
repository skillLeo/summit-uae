<?php

namespace Tests\Feature\Seo;

use App\Models\Content\Service;
use App\Settings\LanguageSettings;
use App\Support\SitePages;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Client PDF item 22: every page that exists in all three languages links en, ar, ru and x-default, each to the
 * equivalent page (never only to the language homepages), reciprocally, on the production host. A language that is off
 * or a page missing in a language is left out; noindex pages carry no alternates.
 */
final class HreflangTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'https://www.summitlegaluae.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    /** @return array<string, string> hreflang => href */
    private function alternates(string $html): array
    {
        preg_match_all('#<link rel="alternate" hreflang="([a-zA-Z-]+)" href="([^"]+)">#', $html, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as [, $lang, $href]) {
            $this->assertArrayNotHasKey($lang, $out, "one {$lang} link");
            $out[$lang] = $href;
        }

        return $out;
    }

    public function test_every_page_links_its_equivalents_reciprocally(): void
    {
        $all = [];
        foreach (SitePages::all() as $page) {
            $this->assertSame(['en', 'ar', 'ru'], $page['locales'], $page['key'].' exists in all three languages');
            foreach ($page['locales'] as $locale) {
                $all["/{$locale}/{$page['path']}"] = $this->alternates($this->get("/{$locale}/{$page['path']}")->assertOk()->getContent());
            }
        }
        foreach ($all as $path => $links) {
            $suffix = substr($path, 4); // the path after "/xx/"
            $this->assertSame([
                'en' => self::HOST.'/en/'.$suffix,
                'ar' => self::HOST.'/ar/'.$suffix,
                'ru' => self::HOST.'/ru/'.$suffix,
                'x-default' => self::HOST.'/en/'.$suffix,
            ], $links, $path);
            // Reciprocal: every page it names names it back with the same set.
            foreach (['en', 'ar', 'ru'] as $lang) {
                $this->assertSame($links, $all[substr($links[$lang], strlen(self::HOST))], "{$path} ↔ {$links[$lang]}");
            }
        }
        $this->assertCount(60, $all); // (10 since Legal Translation left the site: client PDF item 9)
    }

    public function test_a_language_that_is_off_is_left_out(): void
    {
        $settings = app(LanguageSettings::class);
        $settings->enabled_locales = ['en', 'ar'];
        $settings->save();
        content()->flush();

        $links = $this->alternates($this->get('/ar/practice-areas/labor-law/')->assertOk()->getContent());
        $this->assertSame(['en', 'ar', 'x-default'], array_keys($links));
    }

    public function test_a_page_missing_in_a_language_is_not_named_in_it(): void
    {
        // A practice area whose Russian text is missing (for example a new service before its Russian content).
        $service = Service::query()->findOrFail('S06');
        foreach (['h1', 'title'] as $field) {
            $values = $service->getTranslations($field);
            unset($values['ru']);
            DB::table('services')->where('code', 'S06')->update([$field => json_encode($values, JSON_UNESCAPED_UNICODE)]);
        }
        content()->flush();

        $this->assertSame(['en', 'ar'], SitePages::service('S06')['locales']);
        $links = $this->alternates($this->get('/en/practice-areas/labor-law/')->assertOk()->getContent());
        $this->assertSame(['en', 'ar', 'x-default'], array_keys($links));
        // The Russian address itself is not indexed and names no alternates.
        $ru = $this->get('/ru/practice-areas/labor-law/')->getContent();
        $this->assertSame([], $this->alternates($ru));
        $this->assertStringContainsString('<meta name="robots" content="noindex', $ru);
    }

    public function test_noindex_pages_carry_no_alternates(): void
    {
        foreach (['/en/styleguide/' => 200, '/ar/offline/' => 200, '/ru/no-such-page/' => 404] as $uri => $status) {
            $this->assertSame([], $this->alternates($this->get($uri)->assertStatus($status)->getContent()), $uri);
        }
    }
}
