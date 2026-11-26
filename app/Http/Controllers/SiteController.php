<?php

namespace App\Http\Controllers;

use App\Support\Languages;
use App\Support\Seo;
use App\Support\SitePages;
use App\Support\SiteUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Root redirect, XML sitemap, web manifest, offline page and styleguide. */
final class SiteController extends Controller
{
    /**
     * "/" (client PDF item 29): a permanent 301 to the default language's home page (Settings, Languages: English,
     * /en/), for every visitor and every crawler alike. Never by Accept-Language, IP or cookie, so the answer is the
     * same for everyone and cacheable. The target is an existing page that never redirects back (no loop); the
     * query string is kept (campaign tags of a shared link survive the hop).
     */
    public function root(Request $request): RedirectResponse
    {
        Languages::filter(); // a language that is off (Settings, Languages) is never the target
        // A root-relative Location ("/en/"), never built from the Host header (SiteUrl::redirectTarget()), and the clean
        // folder address, never under the front controller: "/index.php" goes to /en/ too (client PDF item 20).
        $target = SiteUrl::redirectTarget(SiteUrl::pagePath('P01', Languages::defaultLocale()), $request->getQueryString());

        return new RedirectResponse($target, 301);
    }

    /**
     * /sitemap.xml (client PDF item 23): every current page (App\Support\SitePages: the home page, P02-P08, L01, L02 and
     * the published practice areas, from the content, so a removed service drops out by itself) in each language in
     * which it exists, on the production host, with reciprocal hreflang alternates and x-default. Never the 404,
     * calculators, styleguide, offline, admin or preview addresses. The file itself is not indexed.
     */
    public function sitemap(): Response
    {
        Languages::apply();

        return response()
            ->view('sitemap', [
                'pages' => SitePages::all(),
                'locales' => config('summit.locales'),
                'default' => config('summit.default_locale'),
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex');
    }

    /**
     * /robots.txt (client PDF item 24; it replaces the static public/robots.txt). On the production site, exactly:
     *
     *     User-agent: *
     *     Allow: /
     *
     *     Sitemap: https://www.summitlegaluae.com/sitemap.xml
     *
     * No Disallow rule (the admin and the previews send their own "noindex" header). When the site may not be indexed
     * (Seo::siteIndexable(): SUMMIT_NOINDEX, on by default with APP_ENV=staging, or the SEO screen's switch), crawling
     * stays allowed, so crawlers can read the noindex of every page and response, and no sitemap is named.
     */
    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Allow: /'];
        if (Seo::siteIndexable()) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.SiteUrl::canonical('sitemap.xml');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
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
        ]);
    }
}
