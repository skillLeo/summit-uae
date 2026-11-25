<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Design 2, the site's only design (docs/upgrade-plan.md). Its folder resources/views/designs/d2 is first on the view
 * path (config/view.php); this provider adds its helpers, the d2:: namespace and the @proposed directive.
 */
final class DesignServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('Design/D2/helpers.php');
    }

    public function boot(): void
    {
        // Design 2 internals: @extends('d2::layouts.app'), @include('d2::sections.home.hero') …
        View::addNamespace('d2', (string) config('design.d2.views'));

        // data-copy-status="proposed" on elements that show proposed (not yet approved) copy,
        // outside production only (upgrade spec §12: "add data-copy-status=proposed (non-production only)").
        Blade::directive('proposed', fn () => "<?php if (! app()->isProduction()) echo 'data-copy-status=\"proposed\"'; ?>");
    }
}
