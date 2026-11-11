<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Icons;
use App\Design\D2\WhatsAppLogo;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The official WhatsApp logo (brief §3.8, D10; owner decision of Phase 1; contract docs/d2.1/phase-1/components.md
 * §4): Meta's own files, shown UNCHANGED, from the drop folder resources/brand/whatsapp/. Until they are there every
 * WhatsApp control shows the marked placeholder; dropping the files in switches every control at once, with no code
 * change. The files below are test fixtures (plain shapes), never Meta's logo.
 */
final class WhatsAppLogoTest extends TestCase
{
    use RefreshDatabase;

    /** Fixture bytes standing in for Meta's two files (deliberately not a logo). */
    private const LIGHT_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" fill="#00A884"/></svg>'."\n";

    private const DARK_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" fill="#FFFFFF"/></svg>'."\n";

    /** A 1 × 1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private string $dir;

    private string $source;

    private string $public;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
        $this->dir = sys_get_temp_dir().'/summit-wa-logo-'.bin2hex(random_bytes(6));
        $this->source = $this->dir.'/drop';
        $this->public = $this->dir.'/public';
        File::ensureDirectoryExists($this->source);
        config(['design.d2.whatsapp_logo.source' => $this->source, 'design.d2.whatsapp_logo.public' => $this->public]);
        WhatsAppLogo::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        WhatsAppLogo::flush();
        Icons::flush();
        parent::tearDown();
    }

    private function drop(string $name, string $bytes): string
    {
        $path = $this->source.'/'.$name;
        file_put_contents($path, $bytes);
        WhatsAppLogo::flush();

        return $path;
    }

    private function dataUri(string $type, string $bytes): string
    {
        return 'data:'.$type.';base64,'.base64_encode($bytes);
    }

    private function page(string $url): string
    {
        return (string) $this->withUnencryptedCookies(['summit_design' => '2'])->get($url)->getContent();
    }

    /** @return list<string> the inner HTML of every wa.me / api.whatsapp.com link */
    private function whatsappLinks(string $html): array
    {
        preg_match_all('#<a\b[^>]*href="https://(?:wa\.me|api\.whatsapp\.com)/[^"]*"[^>]*>(.*?)</a>#s', $html, $m);

        return $m[1];
    }

    public function test_until_metas_files_are_dropped_every_mark_is_the_marked_placeholder(): void
    {
        $this->assertNull(WhatsAppLogo::variant('light'));
        $this->assertNull(WhatsAppLogo::variant('dark'));
        $this->assertFalse(WhatsAppLogo::official());

        $html = Blade::render('<x-d2.ui.icon name="whatsapp" />');
        $this->assertMatchesRegularExpression('#^<span class="brand-mark brand-mark--whatsapp" data-brand="whatsapp" data-icon="whatsapp" aria-hidden="true">#', trim($html));
        $this->assertSame(1, substr_count($html, 'data-placeholder="whatsapp-logo"'), 'one placeholder, grey from its surface');
        $this->assertStringContainsString('TEMPORARY placeholder', (string) file_get_contents(resource_path('views/components/d2/ui/brand-mark.blade.php')));
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<use', $html, 'never a sprite glyph');
        $this->assertStringNotContainsString('#25D366', $html, 'the placeholder is neutral, never green');
        $this->assertSame([], Icons::used(), 'the WhatsApp mark never reaches the icon sprite');

        // Named, when the mark is the only content of a control.
        $this->assertStringContainsString('role="img" aria-label="WhatsApp"', Blade::render('<x-d2.ui.icon name="whatsapp" label="WhatsApp" />'));
    }

    public function test_dropping_the_light_file_switches_the_light_surfaces_and_keeps_the_dark_placeholder(): void
    {
        $this->drop('whatsapp-logo-light.svg', self::LIGHT_SVG);

        $html = Blade::render('<x-d2.ui.icon name="whatsapp" size="24" />');
        $this->assertStringContainsString('<img class="brand-mark__img brand-mark__light" src="'.$this->dataUri('image/svg+xml', self::LIGHT_SVG).'" width="24" height="24" alt=""', $html, 'the file\'s own bytes, unchanged');
        $this->assertMatchesRegularExpression('#<svg class="brand-mark__placeholder brand-mark__dark"[^>]*data-placeholder="whatsapp-logo"#', $html, 'the dark surface waits for its own file');
        $this->assertFalse(WhatsAppLogo::official());
    }

    public function test_dropping_both_files_switches_every_whatsapp_control_on_every_page_without_code_change(): void
    {
        // Before: every link shows the placeholder.
        foreach (['/en/', '/ar/contact/', '/ru/practice-areas/labor-law/'] as $url) {
            $links = $this->whatsappLinks($this->page($url));
            $this->assertNotEmpty($links, $url);
            foreach ($links as $inner) {
                $this->assertStringContainsString('data-brand="whatsapp"', $inner, "{$url}: every WhatsApp link holds the mark (lint rule 14)");
                $this->assertStringContainsString('data-placeholder="whatsapp-logo"', $inner, $url);
            }
        }

        $this->drop('whatsapp-logo-light.svg', self::LIGHT_SVG);
        $this->drop('whatsapp-logo-dark.svg', self::DARK_SVG);
        $this->assertTrue(WhatsAppLogo::official());
        $light = '<img class="brand-mark__img brand-mark__light" src="'.$this->dataUri('image/svg+xml', self::LIGHT_SVG).'"';
        $dark = '<img class="brand-mark__img brand-mark__dark" src="'.$this->dataUri('image/svg+xml', self::DARK_SVG).'"';

        foreach (['/en/', '/ar/', '/ru/', '/en/contact/', '/ar/contact/', '/ru/practice-areas/labor-law/', '/en/faq/', '/ar/about/', '/en/no-such-page/'] as $url) {
            $html = $this->page($url);
            $this->assertStringNotContainsString('data-placeholder="whatsapp-logo"', $html, "{$url}: no placeholder left anywhere");
            $this->assertStringNotContainsString('d2i-whatsapp', $html, $url);
            $links = $this->whatsappLinks($html);
            $this->assertNotEmpty($links, $url);
            foreach ($links as $inner) {
                $this->assertStringContainsString('data-brand="whatsapp"', $inner, $url);
                $this->assertStringContainsString($light, $inner, "{$url}: the light-background file");
                $this->assertStringContainsString($dark, $inner, "{$url}: the dark-background file (CSS shows the one that fits the surface)");
            }
        }
    }

    public function test_svg_wins_over_png_and_png_or_webp_files_work_unchanged(): void
    {
        $png = (string) base64_decode(self::PNG);
        $this->drop('whatsapp-logo-dark.png', $png);
        $this->assertSame('image/png', WhatsAppLogo::variant('dark')['type']);
        $this->assertSame($this->dataUri('image/png', $png), WhatsAppLogo::variant('dark')['src']);

        $this->drop('whatsapp-logo-dark.svg', self::DARK_SVG);
        $this->assertSame('image/svg+xml', WhatsAppLogo::variant('dark')['type'], 'SVG is preferred');

        $this->drop('whatsapp-logo-light.webp', 'RIFF fixture');
        $this->assertSame('image/webp', WhatsAppLogo::variant('light')['type']);
        $this->drop('whatsapp-logo-unknown.svg', self::LIGHT_SVG);
        $this->assertNull(WhatsAppLogo::variant('unknown'), 'only the two variants');
        $this->drop('whatsapp-logo-light.webp', '');
        $this->assertNull(WhatsAppLogo::variant('light'), 'an empty file is not a logo');
    }

    public function test_the_published_copy_is_used_when_it_has_the_same_bytes(): void
    {
        $source = $this->drop('whatsapp-logo-light.svg', self::LIGHT_SVG);
        $name = WhatsAppLogo::publishedName($source);
        $this->assertSame('whatsapp-logo-light.'.substr(hash('sha256', self::LIGHT_SVG), 0, 8).'.svg', $name);
        $this->assertFalse(WhatsAppLogo::variant('light')['published']);

        File::ensureDirectoryExists($this->public);
        copy($source, $this->public.'/'.$name);
        WhatsAppLogo::flush();
        $this->assertSame(WhatsAppLogo::PUBLIC_URL.$name, WhatsAppLogo::variant('light')['src']);
        $this->assertStringContainsString('src="/d2/brand/whatsapp/'.$name.'"', Blade::render('<x-d2.ui.icon name="whatsapp" />'));

        // A copy that differs from the dropped file is never served: the page inlines the dropped bytes instead.
        file_put_contents($this->public.'/'.$name, self::DARK_SVG);
        WhatsAppLogo::flush();
        $this->assertFalse(WhatsAppLogo::variant('light')['published']);
        $this->assertSame($this->dataUri('image/svg+xml', self::LIGHT_SVG), WhatsAppLogo::variant('light')['src']);
    }

    public function test_the_publish_script_copies_byte_for_byte_and_checks_for_stale_copies(): void
    {
        $node = Process::run(['node', '--version']);
        if (! $node->successful()) {
            $this->markTestSkipped('node is not available');
        }
        $script = base_path('scripts/d2/whatsapp-logo.mjs');
        $run = fn (string ...$args) => Process::path(base_path())->run(['node', $script, '--source', $this->source, '--public', $this->public, ...$args]);

        $this->assertTrue($run('--check')->successful(), 'nothing dropped, nothing to publish');

        $light = $this->drop('whatsapp-logo-light.svg', self::LIGHT_SVG);
        $png = $this->drop('whatsapp-logo-dark.png', (string) base64_decode(self::PNG));
        $this->assertSame(1, $run('--check')->exitCode(), 'missing copies fail the check');
        $this->assertTrue($run()->successful());
        foreach ([$light, $png] as $file) {
            $copy = $this->public.'/'.WhatsAppLogo::publishedName($file);
            $this->assertFileExists($copy);
            $this->assertSame(hash_file('sha256', $file), hash_file('sha256', $copy), 'byte for byte');
        }
        $this->assertTrue($run('--check')->successful());

        // A new file from Meta: a new name, the old copy is stale until the script runs again.
        $old = WhatsAppLogo::publishedName($light);
        $this->drop('whatsapp-logo-light.svg', self::DARK_SVG);
        $this->assertSame(1, $run('--check')->exitCode());
        $this->assertTrue($run()->successful());
        $this->assertFileDoesNotExist($this->public.'/'.$old);
        $this->assertFileExists($this->public.'/'.WhatsAppLogo::publishedName($light));
        WhatsAppLogo::flush();
        $this->assertTrue(WhatsAppLogo::variant('light')['published']);
    }

    public function test_the_drop_folder_documents_the_files_and_whatever_is_dropped_is_published(): void
    {
        config(['design.d2.whatsapp_logo.source' => resource_path('brand/whatsapp'), 'design.d2.whatsapp_logo.public' => public_path('d2/brand/whatsapp')]);
        WhatsAppLogo::flush();

        // The drop folder's note, where it is kept (optional in a deployment copy).
        $readmePath = resource_path('brand/whatsapp/README.md');
        if (is_file($readmePath)) {
            $readme = (string) file_get_contents($readmePath);
            $this->assertStringContainsString('whatsapp-logo-light.svg', $readme);
            $this->assertStringContainsString('whatsapp-logo-dark.svg', $readme);
            $this->assertStringContainsString('https://www.meta.com/brand/resources/whatsapp/whatsapp-brand/', $readme);
        }

        foreach (WhatsAppLogo::VARIANTS as $variant) {
            $file = WhatsAppLogo::variant($variant);
            if ($file === null) {
                continue; // still the placeholder: nothing to publish
            }
            $this->assertTrue($file['published'], "Meta's {$variant} file is in the drop folder: run node scripts/d2/whatsapp-logo.mjs");
        }
        $this->assertDirectoryDoesNotExist(resource_path('svg/d2/brand'), 'no redrawn copy of the logo anywhere in the icon set');
    }
}
