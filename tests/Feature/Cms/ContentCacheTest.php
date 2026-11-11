<?php

namespace Tests\Feature\Cms;

use App\Content\Cms\ContentCache;
use App\Models\Content\Page;
use App\Models\Content\UiString;
use App\Settings\WhatsAppSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The published-content cache (brief §8.6) on the file store this site uses (no tags): a versioned key that every
 * content change replaces, so the next request shows the change; the previous version's entries are forgotten.
 */
final class ContentCacheTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = storage_path('framework/testing/cms-cache-'.bin2hex(random_bytes(4)));
        config(['cache.default' => 'file', 'cache.stores.file.path' => $this->path]);
        Cache::purge('file');
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);
        parent::tearDown();
    }

    private function get2(string $url): string
    {
        return (string) $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])->get($url)->assertOk()->getContent();
    }

    public function test_a_content_change_clears_the_cache_and_shows_at_once(): void
    {
        $approved = content()->page('P06', 'en')['h1'];
        $this->assertStringContainsString(e($approved), $this->get2('/en/about/'));
        $version = ContentCache::version();
        $this->assertTrue(Cache::has(ContentCache::key('records.en')), 'the records are cached');

        $page = Page::query()->findOrFail('P06');
        $page->setTranslation('h1', 'en', 'A changed heading')->save();

        $this->assertNotSame($version, ContentCache::version(), 'a new cache version');
        $this->assertFalse(Cache::has('summit.cms.'.$version.'.records.en'), 'the previous version is forgotten');
        $this->assertStringContainsString('A changed heading', $this->get2('/en/about/'));
        $this->assertStringNotContainsString(e($approved), $this->get2('/en/about/'));
    }

    public function test_interface_text_changes_clear_the_cache(): void
    {
        $line = UiString::query()->where('group', 'proposed')->where('key', 'd2.shell.search_placeholder')->firstOrFail();
        $before = d2p('shell.search_placeholder', [], 'ru');
        $line->text = ['en' => 'Search the site (CMS)'] + $line->text;
        $line->save();

        $this->assertStringContainsString('aria-label="Search the site (CMS)"', $this->get2('/en/'));
        $this->assertSame($before, d2p('shell.search_placeholder', [], 'ru'), 'the other languages keep theirs');
    }

    public function test_a_new_wa01_draft_changes_every_whatsapp_link(): void
    {
        $settings = app(WhatsAppSettings::class);
        $settings->draft = ['en' => 'Hello Summit (CMS draft)'] + $settings->draft;
        $settings->save();

        $expected = 'https://wa.me/971524666191?text='.rawurlencode('Hello Summit (CMS draft)');
        preg_match_all('/href="(https:\/\/wa\.me\/[^"]+)"/', $this->get2('/en/'), $m);
        $this->assertNotEmpty($m[1]);
        $this->assertSame([$expected], array_values(array_unique(array_map('html_entity_decode', $m[1]))), 'every WhatsApp link of the page opens the one WA01 draft');
    }
}
