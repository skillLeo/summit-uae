<?php

namespace App\Http\Controllers;

use App\Design\D2\Pending;
use App\Design\DesignManager;
use App\Support\Seo;
use App\Support\SiteUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design 2 calculators page, /{locale}/calculators/ (Design 2.1 brief §5.1: "The calculators live on their own page,
 * linked from section 09 and from the Employment Law and Property and Tenancy pages"; §8.2 lists it as a page).
 *
 * The two estimators (gratuity, Dubai rent increase) and every text on the page are pending client approval (and a
 * legal sign-off of their rules), so the page is noindex, stays out of the sitemap and renders only while pending
 * copy is shown (App\Design\D2\Pending; off in production). Design 1 has no calculators: there the address leads to
 * the home page, so the floating design switch never lands on an error page.
 */
final class CalculatorsController extends Controller
{
    public function __invoke(Request $request, DesignManager $design): View|RedirectResponse
    {
        if (! $design->is(2)) {
            // A plain RedirectResponse: redirect()->to() would strip the site's trailing slash.
            return new RedirectResponse(SiteUrl::home(), 302);
        }
        abort_unless(Pending::visible(), 404);

        return view('d2::pages.calculators', [
            'seo' => Seo::make(d2p('tools.page.seo_title', ['brand' => ui('brand.name')]), d2p('tools.page.meta_description'), index: false),
            'pageKey' => 'calculators',
            'navCurrent' => null,
            'crumbs' => [
                ['label' => ui('nav.home'), 'href' => SiteUrl::home()],
                ['label' => d2p('tools.section.title'), 'href' => null],
            ],
        ]);
    }
}
