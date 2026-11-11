<?php

use App\Content\Cms\Editing\Slugs;
use App\Design\D2\Calculators;
use App\Http\Controllers\CalculatorsController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PagePreviewController;
use App\Http\Controllers\SiteController;
use App\Http\Middleware\EnsureTrailingSlash;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// The published slugs of the CMS (pages, practice areas) decide the addresses below (App\Content\Cms\Editing\Slugs; brief
// §8.3). While they equal config/summit.php, which they do after the import, nothing changes.
Slugs::apply();

// Neutral root: language chooser, or a 302 to a language the visitor chose before (cookie only).
Route::get('/', [SiteController::class, 'root'])->name('root');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');

Route::prefix('{locale}')
    ->where(['locale' => implode('|', array_keys(config('summit.locales')))])
    ->middleware([SetLocale::class])
    ->group(function () {
        Route::middleware(EnsureTrailingSlash::class)->group(function () {
            Route::get('/', HomeController::class)->name('home');
            Route::get('styleguide/', [SiteController::class, 'styleguide'])->name('styleguide');
            Route::get('offline/', [SiteController::class, 'offline'])->name('offline');

            // Registered P02–P08, L01, L02 and S01–S11 routes. Templates arrive in Phase 2;
            // until then each renders an honest, noindex preview of its approved H1 and intro.
            foreach (config('summit.pages') as $code => $page) {
                if ($code !== 'P01') {
                    Route::get($page['path'], [PagePreviewController::class, 'page'])
                        ->defaults('code', $code)
                        ->name($page['route']);
                }
            }
            Route::get('practice-areas/{service}/', [PagePreviewController::class, 'service'])
                ->whereIn('service', array_values(config('summit.services')))
                ->name('service');

            // Design 2 only (brief 2.1 §5.1): the calculators page. Design 1 has none and is sent to its home page;
            // noindex and out of the sitemap while its copy is pending client approval.
            Route::get(Calculators::PATH, CalculatorsController::class)->name('calculators');
        });

        Route::get('manifest.webmanifest', [SiteController::class, 'manifest'])->name('manifest');
        Route::post('enquiries', [EnquiryController::class, 'store'])->name('enquiries.store');
    });
