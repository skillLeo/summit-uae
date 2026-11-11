<?php

namespace Tests\Feature\Design\D2;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The Design 2 QA harness's PHP behaviour (qa/README.md): config/design.php reads SUMMIT_D2_BUILD_DIR and
 * SUMMIT_D2_HOT_FILE with today's defaults, so scripts/qa/lighthouse-d2.mjs can serve a production build
 * from public/build-d2-qa while the Vite dev server (public/hot-d2) keeps running for everyone else.
 */
final class QaHarnessTest extends TestCase
{
    private string $public;

    protected function setUp(): void
    {
        parent::setUp();
        $this->public = sys_get_temp_dir().'/summit-qa-harness-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->public.'/build-d2-qa/assets');
        File::put($this->public.'/build-d2-qa/manifest.json', json_encode([
            'resources/js/d2/app.js' => ['file' => 'assets/app-qa.js', 'src' => 'resources/js/d2/app.js', 'isEntry' => true],
        ]));
        // A running Design 2 dev server, as on the shared machine.
        File::put($this->public.'/hot-d2', 'http://127.0.0.1:5183');
        $this->app->usePublicPath($this->public);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->public);
        foreach (['SUMMIT_D2_BUILD_DIR', 'SUMMIT_D2_HOT_FILE'] as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
        parent::tearDown();
    }

    /** config/design.php evaluated with the given environment (the cached config is not affected). */
    private function designConfig(array $env = []): array
    {
        foreach ($env as $name => $value) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        return require config_path('design.php');
    }

    public function test_defaults_are_unchanged(): void
    {
        $config = $this->designConfig();

        $this->assertSame('build-d2', $config['d2']['build_directory']);
        $this->assertSame('hot-d2', $config['d2']['hot_file']);
    }

    public function test_the_environment_overrides_the_build_directory_and_the_hot_file(): void
    {
        $config = $this->designConfig(['SUMMIT_D2_BUILD_DIR' => 'build-d2-qa', 'SUMMIT_D2_HOT_FILE' => 'hot-d2-qa-disabled']);

        $this->assertSame('build-d2-qa', $config['d2']['build_directory']);
        $this->assertSame('hot-d2-qa-disabled', $config['d2']['hot_file']);
    }

    public function test_d2_vite_loads_the_qa_build_and_ignores_the_dev_server(): void
    {
        config(['design.d2.build_directory' => 'build-d2-qa', 'design.d2.hot_file' => 'hot-d2-qa-disabled']);

        $html = (string) d2_vite(['resources/js/d2/app.js']);

        $this->assertStringContainsString('/build-d2-qa/assets/app-qa.js', $html);
        $this->assertStringNotContainsString('5183', $html);
    }

    public function test_d2_vite_still_uses_the_dev_server_by_default(): void
    {
        $html = (string) d2_vite(['resources/js/d2/app.js']);

        $this->assertStringContainsString('http://127.0.0.1:5183/resources/js/d2/app.js', $html);
    }

    public function test_the_csp_allows_the_dev_server_only_while_its_hot_file_is_configured(): void
    {
        $this->assertStringContainsString('http://127.0.0.1:5183', (string) $this->get('/up')->headers->get('Content-Security-Policy'));

        config(['design.d2.hot_file' => 'hot-d2-qa-disabled']);

        $csp = (string) $this->get('/up')->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('5183', $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
    }

    /** Brief 2.1 §5.3 and §6: the DOCX conformance check and the per-page budgets are npm scripts of the harness. */
    public function test_the_conformance_and_budget_scripts_are_wired(): void
    {
        $scripts = json_decode((string) file_get_contents(base_path('package.json')), true)['scripts'];
        $this->assertSame('node scripts/qa/docx-conformance.mjs', $scripts['qa:docx']);
        $this->assertSame('node --test scripts/qa/docx-conformance.test.mjs', $scripts['qa:docx:selftest']);
        $this->assertSame('node scripts/qa/budgets.mjs', $scripts['qa:budgets']);
        foreach (['docx-conformance.mjs', 'docx-conformance.test.mjs', 'budgets.mjs'] as $file) {
            $this->assertFileExists(base_path('scripts/qa/'.$file));
        }
        $budgets = (string) file_get_contents(base_path('scripts/qa/budgets.mjs'));
        $this->assertStringContainsString('export const BUDGET = { js: 100 * KB, css: 60 * KB, fontPreloads: 2 };', $budgets);
        // Its production build never touches public/build-d2 or the dev server's hot file.
        $this->assertStringContainsString("buildDir: 'build-d2-budgets'", $budgets);
    }

    /** The calculators page (brief 2.1 §5.1) is a Design 2 route of the text snapshots and the design lint. */
    public function test_the_route_lists_include_the_design_2_calculators_page(): void
    {
        $snapshot = (string) file_get_contents(base_path('scripts/qa/text-snapshot.mjs'));
        $this->assertStringContainsString("routes.push({ locale, slug: 'calculators', path: `/\${locale}/calculators/`, expect: 200, designs: ['2'] });", $snapshot);
        $this->assertStringContainsString('if (route.designs && !route.designs.includes(design)) continue;', $snapshot);
        $lint = (string) file_get_contents(base_path('scripts/qa/lint-design.mjs'));
        $this->assertStringContainsString('(!r.designs || r.designs.includes(String(opt.design)))', $lint);
        $config = (string) file_get_contents(base_path('qa/lib/config.mjs'));
        $this->assertStringContainsString("{ key: 'calculators', path: 'calculators/'", $config);
        // The page itself renders with seeded content in ScreensMatrixTest (the calculators state's fields).
    }
}
