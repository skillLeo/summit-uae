<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use App\Support\SiteUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Root language chooser, XML sitemap, web manifest, offline page and styleguide. */
final class SiteController extends Controller
{
    /** "/": a 302 to the language the visitor chose before (cookie only), otherwise a chooser. */
    public function root(Request $request): RedirectResponse|Response
    {
        $remembered = (string) $request->cookie(config('summit.locale_cookie'));
        if (array_key_exists($remembered, config('summit.locales'))) {
            return redirect()->to(SiteUrl::home($remembered), 302);
        }
        app()->setLocale(config('summit.default_locale'));

        return response()->view('pages.chooser', [
            'locale' => config('summit.default_locale'),
            'dir' => 'ltr',
            'seo' => Seo::make(ui('brand.name'), null, index: false, path: '/en/'),
        ]);
    }

    /** Released pages only (the three homepages in Phase 1), with reciprocal alternates. */
    public function sitemap(): Response
    {
        $released = [['code' => 'P01', 'path' => '']];
        foreach (config('summit.pages') as $code => $page) {
            if ($code !== 'P01' && ($page['built'] ?? false)) {
                $released[] = ['code' => $code, 'path' => $page['path']];
            }
        }

        return response()
            ->view('sitemap', ['pages' => $released, 'locales' => config('summit.locales')])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function manifest(): JsonResponse
    {
        $locale = app()->getLocale();

        return response()->json([
            'name' => ui('brand.name'),
            'short_name' => ui('brand.name'),
            'lang' => $locale,
            'dir' => config("summit.locales.{$locale}.dir"),
            'start_url' => "/{$locale}/",
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#000000',
            'theme_color' => '#000000',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/icons/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function offline(): View
    {
        return view('pages.offline', [
            'seo' => Seo::make(proposed('offline.title').' | '.ui('brand.name'), null, index: false, alternates: false),
            'pageKey' => 'offline',
        ]);
    }

    public function styleguide(): View
    {
        return view('pages.styleguide', [
            'seo' => Seo::make('Styleguide | '.ui('brand.name'), null, index: false),
            'pageKey' => 'styleguide',
            'extraStyles' => ['resources/scss/styleguide.scss'],
            'p01' => content()->page('P01'),
        ]);
    }
}
