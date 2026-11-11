<?php

namespace Tests\Feature\Design\D2;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2 PWA (spec §5.5, §12 E; owner: mobile-shell): its own static manifest per language in public/d2/
 * (scripts/d2/pwa-icons.mjs), linked only from the Design 2 layout, while Design 1's manifest route, link and
 * frozen service worker stay as they are. The manifests must match their sources (approved brand name and
 * home description, proposed short name, the night / canvas tokens): when this test fails after a copy or
 * token change, run `node scripts/d2/pwa-icons.mjs`.
 */
final class PwaTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    /** @return array<string, mixed> */
    private function manifest(string $locale): array
    {
        $path = public_path("d2/manifest-{$locale}.webmanifest");
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data, "{$locale}: valid JSON");

        return $data;
    }

    private function token(string $name): string
    {
        $css = (string) file_get_contents(resource_path('css/d2/tokens.css'));
        $this->assertSame(1, preg_match('/--'.$name.':\s*(#[0-9A-Fa-f]{6})\b/', $css, $m), "tokens.css --{$name}");

        return strtoupper($m[1]);
    }

    public function test_design_two_links_its_own_manifest_and_theme_colour(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/")->assertOk()->getContent();

            $this->assertStringContainsString('<link rel="manifest" href="/d2/manifest-'.$locale.'.webmanifest">', $html, $locale);
            $this->assertStringNotContainsString('href="/'.$locale.'/manifest.webmanifest"', $html, $locale);
            $this->assertStringContainsString('<meta name="theme-color" content="'.$this->token('night').'">', $html, $locale);
        }
    }

    public function test_design_one_keeps_its_manifest_route_and_link(): void
    {
        $html = $this->get('/en/')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-design="2"', $html);
        $this->assertStringContainsString('/en/manifest.webmanifest', $html);
        $this->assertStringNotContainsString('/d2/manifest-', $html);

        $manifest = $this->get('/en/manifest.webmanifest')->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')->json();
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('/en/', $manifest['start_url']);
        $this->assertSame('#000000', $manifest['theme_color']);
        $this->assertSame('/icons/icon-192.png', $manifest['icons'][0]['src']);
    }

    public function test_each_manifest_matches_its_approved_and_proposed_sources(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $m = $this->manifest($locale);

            $this->assertSame("/{$locale}/", $m['id'], $locale);
            $this->assertSame("/{$locale}/", $m['start_url'], $locale);
            $this->assertSame("/{$locale}/", $m['scope'], $locale);
            $this->assertSame($locale, $m['lang']);
            $this->assertSame(config("summit.locales.{$locale}.dir"), $m['dir']);
            $this->assertSame(ui('brand.name'), $m['name'], "{$locale}: approved brand name");
            $this->assertSame(d2p('pwa.short_name'), $m['short_name'], "{$locale}: proposed short name");
            $this->assertSame(content()->page('P01')['meta_description'] ?? null, $m['description'], "{$locale}: approved home description");
            $this->assertSame('standalone', $m['display']);
            $this->assertSame($this->token('night'), $m['theme_color']);
            $this->assertSame($this->token('canvas'), $m['background_color']);
        }
        // The spec's literal values (§12 E) for the English manifest; the background is the Design 2.1 white
        // canvas (docs/briefs/design-2.1-and-admin-cms.md §3.3, D07: no warm off-white page).
        $en = $this->manifest('en');
        $this->assertSame('Summit Legal Consultancy', $en['name']);
        $this->assertSame('Summit Legal', $en['short_name']);
        $this->assertSame('#0B0B0C', $en['theme_color']);
        $this->assertSame('#FFFFFF', $en['background_color']);
    }

    public function test_the_icons_exist_at_their_declared_sizes_and_purposes(): void
    {
        foreach (self::LOCALES as $locale) {
            $icons = $this->manifest($locale)['icons'];
            $this->assertSame(['any', 'any', 'maskable', 'maskable'], array_column($icons, 'purpose'), $locale);
            foreach ($icons as $icon) {
                $this->assertStringStartsWith('/d2/icons/', $icon['src']);
                $file = public_path(ltrim($icon['src'], '/'));
                $this->assertFileExists($file);
                $size = getimagesize($file);
                $this->assertNotFalse($size, $icon['src']);
                $this->assertSame($icon['sizes'], $size[0].'x'.$size[1], $icon['src']);
                $this->assertSame('image/png', $icon['type']);
                $this->assertSame(IMAGETYPE_PNG, $size[2]);
            }
            $this->assertContains('192x192', array_column($icons, 'sizes'));
            $this->assertContains('512x512', array_column($icons, 'sizes'));
        }
    }

    public function test_design_two_registers_only_design_ones_service_worker(): void
    {
        // One worker for both designs: the same script and scope, never a second registration.
        $module = (string) file_get_contents(resource_path('js/d2/modules/pwa.js'));
        $this->assertStringContainsString("export const WORKER = '/sw.js';", $module);
        $this->assertStringContainsString('serviceWorker.register(WORKER)', $module);
        $registrations = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('js/d2'), \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() === 'js' && ! str_ends_with($file->getFilename(), '.test.js')) {
                $registrations += substr_count((string) file_get_contents($file->getPathname()), 'serviceWorker.register(');
            }
        }
        $this->assertSame(1, $registrations, 'Design 2 registers exactly one worker');
        $this->assertFileDoesNotExist(public_path('d2/sw.js'));

        // The frozen worker it relies on never touches POST requests and caches nothing but its own lists.
        $worker = (string) file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("if (req.method !== 'GET') return;", $worker);
        $this->assertStringNotContainsString('push', strtolower($worker));
    }

    public function test_the_registration_switch_follows_the_pwa_feature_flag(): void
    {
        config(['summit.features.pwa' => false]);
        $off = $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/<html[^>]*\sdata-pwa[\s>]/', $off);

        config(['summit.features.pwa' => true]);
        $on = $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<html[^>]*\sdata-pwa[\s>]/', $on);
    }
}
