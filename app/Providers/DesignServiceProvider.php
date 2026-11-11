<?php

namespace App\Providers;

use App\Design\DesignManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/** Switchable designs (docs/upgrade-plan.md). */
final class DesignServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('Design/D2/helpers.php');

        $this->app->singleton(DesignManager::class, fn ($app) => new DesignManager($app['view']));
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
