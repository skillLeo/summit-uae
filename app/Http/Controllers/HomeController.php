<?php

namespace App\Http\Controllers;

use App\Content\ContentRepository;
use App\Content\NavigatorData;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __invoke(Request $request, ContentRepository $content): View
    {
        $page = $content->page('P01');
        $navigator = new NavigatorData($content);
        $navServices = $navigator->services();

        // No-JS Navigator: ?navigator=S05-2 renders that starting file on the server. Only a plain string
        // counts: a malformed link such as ?navigator[]=S05-2 is ignored instead of failing with an error.
        $navigatorQuery = $request->query('navigator');
        $selected = NavigatorData::parse(is_string($navigatorQuery) ? $navigatorQuery : null);
        $selectedService = null;
        if ($selected !== null) {
            foreach ($navServices as $s) {
                if ($s['code'] === $selected['service'] && isset($s['situations'][$selected['index'] - 1])) {
                    $selectedService = $s;
                }
            }
        }

        return view('pages.home', [
            'seo' => Seo::make($page['seo_title'] ?? ui('brand.name'), $page['meta_description'] ?? null, index: $request->query() === []),
            'pageKey' => 'home',
            'navCurrent' => 'home',
            'page' => $page,
            'c' => $page['content'] ?? [],
            'services' => $content->services(),
            'guideCategories' => $content->guideCategories(),
            'navServices' => $navServices,
            'navPayload' => $navigator->payload(),
            'selected' => $selectedService ? $selected : null,
            'selectedService' => $selectedService,
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }
}
