<?php

namespace Tests\Feature\Cms;

use App\Content\ContentRepository;
use App\Models\Content\Page;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyContent;
use Tests\TestCase;

/**
 * The parity proof of Phase 3 (docs/briefs/design-2.1-and-admin-cms.md §8.8): after the import the CMS holds exactly the
 * current texts, and the public site renders exactly what it rendered from the files.
 *
 *   1. Every record of every dataset, in every language, equals the record the pre-Phase-3 content tables gave
 *      (tests/Support/LegacyContent: the ContentSeeder and ContentRepository of d3e1481 over database/content/*.json),
 *      nested order included; so do the list order, every approved interface string and every setting.
 *   2. Key pages of both designs in EN, AR and RU render the same HTML from the database as from the files.
 *
 * The browser-level check against the text baseline of the polished Design 2 (tests/Snapshots/text, commit 064130d) is
 * `node scripts/qa/text-snapshot.mjs --check` (every route, locale and design; see the Phase 3 report).
 */
final class ContentParityTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    /** Key pages: the home and every page template, one service, the 404 and the calculators page. */
    private const PAGES = ['', 'practice-areas/', 'resources/', 'case-studies/', 'faq/', 'about/', 'contact/', 'legal-disclaimer/',
        'privacy-policy/', 'terms-and-conditions/', 'practice-areas/labor-law/', 'calculators/', 'no-such-page-qa/', '?navigator=S05-2'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    public function test_every_record_equals_the_pre_cms_content_in_every_language(): void
    {
        $legacy = new LegacyContent;
        $content = app(ContentRepository::class);
        $top = function (?array $record): ?array {
            if ($record !== null) {
                ksort($record);
            }

            return $record;
        };
        $checked = 0;
        foreach (array_keys(LegacyContent::TABLES) as $dataset) {
            $this->assertNotSame([], $legacy->codes($dataset), "{$dataset}: the files have records");
            foreach (self::LOCALES as $locale) {
                foreach ($legacy->codes($dataset) as $code) {
                    // Field order inside a record is the approved order (assertSame compares it); the top-level key order
                    // of a record is not read by any view.
                    $this->assertSame($top($legacy->record($dataset, $code, $locale)), $top($content->record($dataset, $code, $locale)), "{$dataset}.{$code} ({$locale})");
                    $checked++;
                }
                $this->assertSame(
                    array_column($legacy->all($dataset, $locale), 'code'),
                    array_column($content->all($dataset, $locale), 'code'),
                    "{$dataset} ({$locale}): the same records in the same order",
                );
            }
        }
        $this->assertSame(405, $checked, '135 records x 3 languages');
    }

    public function test_interface_strings_and_settings_equal_the_pre_cms_content(): void
    {
        $legacy = new LegacyContent;
        foreach (self::LOCALES as $locale) {
            foreach ($legacy->uiKeys() as $key) {
                $this->assertSame($legacy->ui($key, $locale) ?? "⟦ui:{$key}⟧", ui($key, $locale), "ui:{$key} ({$locale})");
            }
            foreach (['whatsapp_url', 'whatsapp_draft', 'phone_display', 'phone_href', 'email', 'email_href', 'link_registry'] as $key) {
                $this->assertSame($legacy->setting($key, $locale), setting($key, $locale), "setting:{$key} ({$locale})");
            }
        }
    }

    public function test_the_content_comes_from_the_database_not_the_files(): void
    {
        $this->assertSame(11, DB::table('pages')->where('status', 'published')->count());
        // A change in the database shows; the files are untouched.
        Page::query()->findOrFail('P06')->setTranslation('h1', 'en', 'Database H1')->save();
        $this->assertSame('Database H1', content()->page('P06', 'en')['h1']);
        $this->assertNotSame('Database H1', (new LegacyContent)->record('pages', 'P06', 'en')['h1']);
    }

    public function test_key_pages_of_both_designs_render_the_same_from_the_database_and_from_the_files(): void
    {
        $content = app(ContentRepository::class);
        $pages = 0;
        foreach (['1', '2'] as $design) {
            foreach (self::LOCALES as $locale) {
                foreach (self::PAGES as $page) {
                    if ($design === '1' && $page === 'calculators/') {
                        continue; // Design 1 has no calculators page (it redirects)
                    }
                    $url = "/{$locale}/{$page}";
                    $content->useSource(ContentRepository::SOURCE_DATABASE);
                    $fromDatabase = $this->render($url, $design);
                    $content->useSource(ContentRepository::SOURCE_FILES);
                    $fromFiles = $this->render($url, $design);
                    $content->useSource(ContentRepository::SOURCE_DATABASE);
                    $this->assertNotSame('', $fromDatabase);
                    $this->assertStringNotContainsString('⟦', $fromDatabase, "design {$design} {$url}: no missing copy");
                    $this->assertSame($fromFiles, $fromDatabase, "design {$design} {$url}");
                    $pages++;
                }
            }
        }
        $this->assertSame(81, $pages);
    }

    /** The page's HTML without what changes on every request (nonce, CSRF token, UUIDs, generated ids). */
    private function render(string $url, string $design): string
    {
        app('translator')->setLoaded([]);
        $response = $this->withUnencryptedCookies(['summit_design' => $design, 'summit_qa' => '1'])->get($url);
        $this->assertContains($response->getStatusCode(), [200, 404], $url);
        $html = (string) $response->getContent();

        return (string) preg_replace(
            ['/nonce="[^"]+"/', '/name="_token" value="[^"]+"/', '/<meta name="csrf-token" content="[^"]+">/', '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '/\b([a-z]{1,4})-[a-z0-9]{8}(?=[-"])/'],
            ['nonce=""', 'name="_token" value=""', '<meta name="csrf-token" content="">', 'uuid', '$1-RANDOM'],
            $html,
        );
    }
}
