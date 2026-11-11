<?php

namespace Tests\Feature\Design;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Design 1 stays untouched and the two designs never mix (docs/upgrade-plan.md):
 *  - a Design 2 view may reuse a Design 1 view name only for the intended entry views;
 *  - Design 2 never renders Design 1 components, partials, layouts or assets, and the reverse.
 */
final class ViewIsolationTest extends TestCase
{
    /** Design 1 views that Design 2 replaces by name. */
    private const ENTRY_VIEWS = [
        'pages/home', 'pages/preview', 'pages/styleguide', 'pages/chooser', 'pages/offline', 'errors/404',
    ];

    public function test_design_two_only_shadows_entry_views(): void
    {
        $d2 = resource_path('views/designs/d2');
        foreach (Finder::create()->files()->in($d2)->name('*.blade.php') as $file) {
            $name = substr($file->getRelativePathname(), 0, -strlen('.blade.php'));
            if (is_file(resource_path('views/'.$name.'.blade.php'))) {
                $this->assertContains($name, self::ENTRY_VIEWS, "designs/d2/{$name} would silently replace the Design 1 view {$name}");
            }
        }
    }

    public function test_design_two_never_uses_design_one_templates_or_assets(): void
    {
        $forbidden = [
            '<x-site.', '<x-icon ', '<x-icon>', '<x-brand', '<x-consultation-form', "layouts.site", "'partials.",
            "@include('pages.", 'resources/scss/', "'resources/js/app.js'",
        ];
        foreach ([resource_path('views/designs/d2'), resource_path('views/components/d2')] as $dir) {
            foreach (Finder::create()->files()->in($dir)->name('*.php') as $file) {
                $source = $file->getContents();
                foreach ($forbidden as $needle) {
                    $this->assertStringNotContainsString($needle, $source, $file->getRelativePathname()." uses Design 1's {$needle}");
                }
            }
        }
    }

    public function test_design_one_never_references_design_two(): void
    {
        $finder = Finder::create()->files()->in(resource_path('views'))->name('*.php')
            ->exclude(['designs', 'components/d2', 'design-switch']);
        foreach ($finder as $file) {
            $source = $file->getContents();
            foreach (['d2::', '<x-d2.', 'resources/css/d2', 'resources/js/d2', 'build-d2'] as $needle) {
                $this->assertStringNotContainsString($needle, $source, $file->getRelativePathname());
            }
        }
        foreach (['resources/scss', 'resources/js/components', 'resources/js/modules', 'resources/js/lib'] as $dir) {
            foreach (Finder::create()->files()->in(base_path($dir)) as $file) {
                $this->assertStringNotContainsString('/d2/', $file->getContents(), $file->getRelativePathname());
            }
        }
    }

    public function test_the_design_one_build_config_has_no_design_two_entries(): void
    {
        $config = (string) file_get_contents(base_path('vite.config.js'));
        $this->assertStringNotContainsString('d2', $config);
        $this->assertStringContainsString("buildDirectory: 'build-d2'", (string) file_get_contents(base_path('vite.d2.config.js')));
    }
}
