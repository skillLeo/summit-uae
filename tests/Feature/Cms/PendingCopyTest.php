<?php

namespace Tests\Feature\Cms;

use App\Content\Cms\CopySource;
use App\Models\Content\Page;
use App\Settings\ContentSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Show pending copy on the public site" (brief §8.7) from the CMS setting content.show_pending
 * (App\Settings\ContentSettings): when it is off, the pending blocks and the calculators page are not rendered; a block
 * the client approved shows; SUMMIT_SHOW_PENDING (config design.d2.show_pending) still decides for one environment.
 */
final class PendingCopyTest extends TestCase
{
    use RefreshDatabase;

    private const PENDING = ['start', 'anatomy', 'navigator'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
        config(['design.d2.show_pending' => null]); // no environment override: the CMS setting decides
    }

    private function home(string $locale): string
    {
        return (string) $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])->get("/{$locale}/")->assertOk()->getContent();
    }

    private function showPending(?bool $value): void
    {
        $settings = app(ContentSettings::class);
        $settings->show_pending = $value;
        $settings->save();
    }

    public function test_pending_copy_shows_by_default_outside_production(): void
    {
        $this->assertNull(content()->settings('content')['show_pending']);
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = $this->home($locale);
            foreach (self::PENDING as $id) {
                $this->assertStringContainsString('id="'.$id.'"', $html, "{$locale}: #{$id}");
            }
        }
        $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/calculators/')->assertOk();
    }

    public function test_pending_blocks_and_pages_are_hidden_when_the_setting_is_off(): void
    {
        $this->showPending(false);
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = $this->home($locale);
            foreach (self::PENDING as $id) {
                $this->assertStringNotContainsString('id="'.$id.'"', $html, "{$locale}: #{$id} is pending copy");
            }
            foreach (['services', 'who-we-help', 'review', 'about', 'case-studies', 'guides', 'questions', 'consultation-form'] as $id) {
                $this->assertStringContainsString('id="'.$id.'"', $html, "{$locale}: #{$id} is approved copy");
            }
            $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/calculators/")->assertNotFound();
        }

        // The environment's flag still decides for one environment.
        config(['design.d2.show_pending' => true]);
        $this->assertStringContainsString('id="anatomy"', $this->home('en'));
    }

    public function test_a_block_the_client_approved_shows_while_pending_copy_is_hidden(): void
    {
        $page = Page::query()->findOrFail('P01');
        $page->blocks = array_map(fn (array $b) => $b['type'] === 'start' ? ['copy_source' => CopySource::CLIENT_APPROVED] + $b : $b, $page->blocks);
        $page->save();
        $this->showPending(false);

        $html = $this->home('en');
        $this->assertStringContainsString('id="start"', $html, 'approved: shown');
        $this->assertStringNotContainsString('id="anatomy"', $html, 'still pending: hidden');
    }
}
