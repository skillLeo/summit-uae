<?php

use App\Content\Cms\Editing\Slugs;
use App\Design\D2\Calculators;
use App\Http\Controllers\CalculatorsController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PagePreviewController;
use App\Http\Controllers\SiteController;
use App\Http\Middleware\EnsureConsultationForm;
use App\Http\Middleware\EnsureTrailingSlash;
use App\Http\Middleware\SetLocale;
use App\Support\SiteUrl;
use Illuminate\Support\Facades\Route;

// The published slugs of the CMS (pages, practice areas) decide the addresses below (App\Content\Cms\Editing\Slugs; brief
// §8.3). While they equal config/summit.php, which they do after the import, nothing changes.
Slugs::apply();

// Root: a 301 to the default language's home page, /en/ (client PDF item 29). No chooser page any more.
Route::get('/', [SiteController::class, 'root'])->name('root');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
// Production rules or the test site's (client PDF item 24); replaces the static public/robots.txt.
Route::get('/robots.txt', [SiteController::class, 'robots'])->name('robots');

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
            // A practice area taken off the site (Legal Translation, client PDF item 9, owner decision 6): a temporary 302
            // to its page in the same language (Practice Areas), so it can point at the replacement once that is live.
            foreach ((array) config('summit.retired_services') as $code => $retired) {
                // (SetLocale has set the page language and taken {locale} out of the route parameters)
                Route::get('practice-areas/'.$retired['slug'].'/', fn () => redirect()->away(SiteUrl::target((string) $retired['to']), 302))
                    ->name('retired-service.'.$code);
            }

            // Design 2 only (brief 2.1 §5.1): the calculators page. Design 1 has none and is sent to its home page;
            // noindex and out of the sitemap while its copy is pending client approval.
            Route::get(Calculators::PATH, CalculatorsController::class)->name('calculators');
        });

        Route::get('manifest.webmanifest', [SiteController::class, 'manifest'])->name('manifest');
        // The consultation form's endpoint: 404 while the form is off (WhatsApp only, client PDF item 7).
        Route::post('enquiries', [EnquiryController::class, 'store'])->middleware(EnsureConsultationForm::class)->name('enquiries.store');
    });
