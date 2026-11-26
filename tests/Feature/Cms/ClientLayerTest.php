<?php

namespace Tests\Feature\Cms;

use App\Content\Cms\ClientLayer;
use App\Content\Cms\ContentImporter;
use App\Content\Cms\CopySource;
use App\Content\Cms\Sources;
use App\Content\Cms\Status;
use App\Content\Import\ImportException;
use App\Models\Content\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The client-approved content layer (App\Content\Cms\ClientLayer; docs/briefs/client-final-changes-2026-11-25.md, "Rules
 * for this step"): committed PHP files in database/content/client/ applied after the DOCX import, so a re-import
 * (summit:import, which writes only database/content/*.json) can never overwrite a client change, and db:seed on every
 * deploy brings it into the CMS tables, marked client_approved, without touching what the admin edited.
 */
final class ClientLayerTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        // A copy of today's sources: the JSON of the DOCX import and an empty client layer to fill per test.
        $this->dir = storage_path('framework/testing/client-layer-'.getmypid());
        File::deleteDirectory($this->dir);
        File::ensureDirectoryExists($this->dir.'/client');
        foreach (glob(database_path('content/*.json')) as $file) {
            File::copy($file, $this->dir.'/'.basename($file));
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** @param list<array<string, mixed>> $changes */
    private function layer(string $name, array $changes): void
    {
        File::put($this->dir.'/client/'.$name.'.php', '<?php return '.var_export(['source' => 'test', 'changes' => $changes], true).';');
    }

    private function sources(): Sources
    {
        return new Sources($this->dir);
    }

    private function import(): array
    {
        return (new ContentImporter($this->sources()))->run();
    }

    public function test_the_committed_layer_exists_and_applies_cleanly(): void
    {
        $layer = new ClientLayer;
        $this->assertSame([database_path('content/client/2026-11-25.php')], $layer->files());
        foreach ($layer->changes() as $change) {
            $this->assertIsInt($change['item'] ?? null, 'every change names its PDF item');
        }
        (new Sources)->build(); // every change finds its target (an ImportException otherwise)
        $this->assertSame((new Sources)->clientLayer()->dir(), database_path('content').'/client');
    }

    public function test_a_change_overrides_the_docx_json_and_is_marked_client_approved(): void
    {
        $this->layer('2026-11-25', [
            ['item' => 4, 'set' => 'pages.P01.h1', 'value' => ['ru' => 'Новый заголовок.']],
            ['item' => 4, 'set' => 'pages.P01.content.hero.lead', 'value' => ['ru' => 'Новый текст.']],
            ['item' => 8, 'ui' => 'nav.case_studies', 'value' => ['ru' => 'Кейсы']],
        ]);
        $build = $this->sources()->build();
        $p01 = $build['records']['pages']['P01'];
        $this->assertSame('Новый заголовок.', $p01['h1']['ru']);
        $this->assertSame(json_decode(File::get($this->dir.'/pages.json'), true)['P01']['h1']['en'], $p01['h1']['en'], 'other languages stay');
        $this->assertSame(CopySource::CLIENT_APPROVED, $p01['copy_source']);
        $hero = collect($p01['blocks'])->firstWhere('type', 'hero');
        $this->assertSame('Новый текст.', $hero['data']['hero']['lead']['ru']);
        $this->assertSame(CopySource::CLIENT_APPROVED, $hero['copy_source'], 'the block that holds the change');
        $this->assertSame(CopySource::DOCX, collect($p01['blocks'])->firstWhere('type', 'services')['copy_source'], 'the other blocks stay docx');
        $this->assertSame(CopySource::DOCX, $build['records']['pages']['P02']['copy_source'], 'untouched records stay docx');
        $nav = collect($build['ui_strings'])->first(fn ($r) => $r['group'] === 'ui' && $r['key'] === 'nav.case_studies');
        $this->assertSame('Кейсы', $nav['text']['ru']);
        $this->assertSame(CopySource::CLIENT_APPROVED, $nav['copy_source']);
        $item = collect(collect($build['menus'])->firstWhere('key', 'primary')['items'])->firstWhere('link_key', 'H-L004');
        $this->assertSame('Кейсы', $item['label']['ru']);
        $this->assertSame(CopySource::CLIENT_APPROVED, $item['copy_source']);
    }

    public function test_a_re_import_of_the_docx_files_never_overwrites_the_layer(): void
    {
        $this->layer('2026-11-25', [['item' => 4, 'set' => 'pages.P01.h1', 'value' => ['ru' => 'Новый заголовок.']]]);
        // summit:import rewrites the JSON files only (ImportRunner::writeDatasets): simulate it with the DOCX values.
        $pages = json_decode(File::get($this->dir.'/pages.json'), true);
        $docx = $pages['P01']['h1']['ru'];
        File::put($this->dir.'/pages.json', json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        touch($this->dir.'/pages.json', time() + 5);
        $this->assertNotSame('Новый заголовок.', $docx);
        $this->assertFileExists($this->dir.'/client/2026-11-25.php');
        $this->assertSame('Новый заголовок.', $this->sources()->build()['records']['pages']['P01']['h1']['ru']);
        // and the real importer never writes into the layer's folder
        $this->assertStringNotContainsString('client', (string) File::get(app_path('Content/Import/ImportRunner.php')));
    }

    public function test_db_seed_brings_the_change_in_keeps_admin_edits_and_takes_a_later_change(): void
    {
        $this->import();
        $this->assertSame(CopySource::DOCX, Page::query()->findOrFail('P01')->copy_source);

        // First deploy with the layer: an untouched row is updated and marked client_approved.
        $this->layer('2026-11-25', [['item' => 4, 'set' => 'pages.P01.h1', 'value' => ['ru' => 'Новый заголовок.']]]);
        $this->import();
        $p01 = Page::query()->findOrFail('P01');
        $this->assertSame('Новый заголовок.', $p01->getTranslation('h1', 'ru'));
        $this->assertSame(CopySource::CLIENT_APPROVED, $p01->copy_source);
        $this->assertSame(0, $this->import()['counts']['pages']['updated'], 'a second run changes nothing');

        // A later client change (a new layer file) to the same row still applies: the layer's own row is not "edited".
        $this->layer('2026-12-01', [['item' => 4, 'set' => 'pages.P01.h1', 'value' => ['ru' => 'Ещё новее.']]]);
        $this->import();
        $this->assertSame('Ещё новее.', Page::query()->findOrFail('P01')->getTranslation('h1', 'ru'));

        // A row the admin edited is kept, and the run says which client change waits.
        DB::table('pages')->where('code', 'P01')->update(['updated_by' => \App\Models\User::factory()->create()->getKey()]);
        $this->layer('2026-12-02', [['item' => 4, 'set' => 'pages.P01.h1', 'value' => ['ru' => 'Не применится.']]]);
        $result = $this->import();
        $this->assertSame('Ещё новее.', Page::query()->findOrFail('P01')->getTranslation('h1', 'ru'));
        $this->assertContains('pages P01: a client-approved change was not applied (the row was edited in the admin): apply it there', $result['notes']);
    }

    public function test_status_create_remove_and_setting(): void
    {
        $this->layer('2026-11-25', [
            ['item' => 9, 'status' => 'services.S10', 'value' => Status::DRAFT],
            ['item' => 9, 'create' => 'services.S99', 'value' => ['title' => ['en' => 'A draft'], 'slug' => 'a-draft', 'position' => 99], 'status' => Status::DRAFT],
            ['item' => 9, 'remove' => 'guides.G08.body.related.items', 'where' => ['target' => 'S10']],
            ['item' => 5, 'setting' => 'contact.phone_display', 'value' => '+971524666191'],
        ]);
        $build = $this->sources()->build();
        $this->assertSame(Status::DRAFT, $build['records']['services']['S10']['status']);
        $this->assertSame(Status::DRAFT, $build['records']['services']['S99']['status']);
        $this->assertSame(['en' => 'A draft'], $build['records']['services']['S99']['title']);
        $this->assertTrue(collect($build['situations'])->where('practice_area', 'S10')->every(fn ($s) => $s['status'] === Status::DRAFT));
        foreach (['mega', 'footer_practice'] as $menu) {
            $this->assertNotContains('S10', array_column(collect($build['menus'])->firstWhere('key', $menu)['items'], 'target'), $menu);
        }
        $g08 = collect($build['records']['guides']['G08']['blocks'])->firstWhere('id', 'G08.related');
        $this->assertNotNull($g08);
        $this->assertStringNotContainsString('"S10"', json_encode($g08['data']));
        $this->assertStringContainsString('"G05"', json_encode($g08['data']), 'the other related links stay');
        $this->assertSame(CopySource::CLIENT_APPROVED, $g08['copy_source']);
        $this->assertSame('+971524666191', $build['settings']['contact']['phone_display']);
    }

    public function test_replace_corrects_a_text_wherever_it_stands(): void
    {
        $this->layer('2026-11-25', [['item' => 5, 'replace' => '+971 52 466 6191', 'with' => '+971524666191']]);
        $build = $this->sources()->build();
        $this->assertStringNotContainsString('+971 52 466 6191', json_encode([$build['records'], $build['ui_strings'], $build['settings'], $build['menus']], JSON_UNESCAPED_UNICODE));
        $this->assertSame('+971524666191', $build['settings']['contact']['phone_display']);
        $this->assertSame(CopySource::CLIENT_APPROVED, $build['records']['services']['S06']['copy_source']);
        $this->assertSame(CopySource::CLIENT_APPROVED, collect($build['records']['services']['S06']['blocks'])->firstWhere('id', 'S06.cta')['copy_source']);
        $this->assertSame(CopySource::DOCX, collect($build['records']['services']['S06']['blocks'])->firstWhere('id', 'S06.faq')['copy_source']);
    }

    public function test_replace_with_locales_changes_only_those_languages(): void
    {
        $this->layer('2026-11-25', [['item' => 10, 'replace' => 'https://u.ae/ar/', 'with' => 'https://u.ae/en/', 'locales' => ['en', 'ru']]]);
        // S11 (Business Services) links the same u.ae page from its three language versions (its blocks hold them per
        // language): after the replace the English and Russian ones point at /en/, the Arabic one stays on /ar/.
        $json = json_encode($this->sources()->build()['records']['services']['S11'], JSON_UNESCAPED_SLASHES);
        $this->assertSame(2, substr_count($json, 'https://u.ae/en/information-and-services/business'), 'English and Russian');
        $this->assertSame(1, substr_count($json, 'https://u.ae/ar/information-and-services/business'), 'Arabic keeps it');

        $this->layer('2026-11-25', [['item' => 10, 'replace' => 'a', 'with' => 'b', 'locales' => ['fr']]]);
        $this->expectException(ImportException::class);
        $this->sources()->build();
    }

    public function test_a_new_interface_string_exists_only_in_the_languages_given(): void
    {
        $this->layer('2026-11-25', [['item' => 11, 'ui' => 'resources.external_note', 'new' => true, 'value' => ['ar' => 'ملاحظة.', 'ru' => 'Примечание.']]]);
        $row = collect($this->sources()->build()['ui_strings'])->first(fn ($r) => $r['group'] === 'ui' && $r['key'] === 'resources.external_note');
        $this->assertSame(['ar' => 'ملاحظة.', 'ru' => 'Примечание.'], $row['text']);
        $this->assertSame(CopySource::CLIENT_APPROVED, $row['copy_source']);

        $this->layer('2026-11-25', [['item' => 8, 'ui' => 'nav.case_studies', 'new' => true, 'value' => ['ru' => 'x']]]);
        $this->expectException(ImportException::class);
        $this->sources()->build();
    }

    public function test_a_change_whose_target_is_gone_fails_the_build(): void
    {
        $this->layer('2026-11-25', [['item' => 4, 'set' => 'pages.P01.content.no_such_key.lead', 'value' => ['ru' => 'x']]]);
        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('PDF item 4');
        $this->sources()->build();
    }
}
