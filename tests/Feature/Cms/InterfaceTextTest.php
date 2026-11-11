<?php

namespace Tests\Feature\Cms;

use App\Models\Content\UiString;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Interface text from the database (brief §8.6, §8.10; spatie/laravel-translation-loader, App\Models\Content\UiString):
 * ui(), proposed() and d2p() read the published ui_strings rows first and today's files after, never across languages.
 * Design 1 reads its interface strings through the same helpers, so they became editable too.
 */
final class InterfaceTextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function line(string $group, string $key): UiString
    {
        return UiString::query()->where('group', $group)->where('key', $key)->firstOrFail();
    }

    public function test_d2p_reads_the_database_first_and_the_lang_files_after(): void
    {
        $file = (require lang_path('en/proposed-d2/hero.php'))['pause'];
        $this->assertSame($file, d2p('hero.pause'));

        $line = $this->line('proposed', 'd2.hero.pause');
        $line->text = ['en' => 'Pause (CMS)'] + $line->text;
        $line->save();
        app('translator')->setLoaded([]);
        $this->assertSame('Pause (CMS)', d2p('hero.pause'));

        $line->delete(); // the Trash: the lang file's text again
        app('translator')->setLoaded([]);
        $this->assertSame($file, d2p('hero.pause'));
    }

    public function test_a_text_missing_in_one_language_never_comes_from_another(): void
    {
        $line = $this->line('proposed', 'd2.hero.pause');
        $line->text = ['en' => 'Pause (CMS)'];
        $line->save();
        app('translator')->setLoaded([]);
        $this->assertSame((require lang_path('ar/proposed-d2/hero.php'))['pause'], d2p('hero.pause', [], 'ar'), 'the Arabic file, not the English row');
    }

    public function test_design_1_reads_its_interface_strings_from_the_database(): void
    {
        $nav = $this->line('ui', 'nav.home');
        $nav->text = ['en' => 'Home (CMS)'] + $nav->text;
        $nav->save();
        $main = $this->line('proposed', 'nav.main');
        $main->text = ['en' => 'Main navigation (CMS)'] + $main->text;
        $main->save();

        $html = (string) $this->withUnencryptedCookies(['summit_design' => '1'])->get('/en/')->assertOk()->getContent();
        $this->assertStringContainsString('Home (CMS)', $html, 'ui()');
        $this->assertStringContainsString('Main navigation (CMS)', $html, 'proposed()');
    }

    public function test_every_interface_string_has_its_source_and_copy_source(): void
    {
        $this->assertSame(0, UiString::query()->whereNull('note')->count());
        $this->assertSame('lang/{locale}/proposed-d2/hero.php (Design 2 proposed copy)', $this->line('proposed', 'd2.hero.pause')->note);
        $this->assertSame(0, UiString::query()->where('key', 'like', 'd2.anatomy.fields.%')->count(), 'the Anatomy parts live in document_samples');
    }
}
