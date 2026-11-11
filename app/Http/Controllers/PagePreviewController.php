<?php

namespace App\Http\Controllers;

use App\Content\ContentRepository;
use App\Support\Seo;
use App\Support\SiteUrl;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Registered routes whose templates arrive in Phase 2 (P02–P08, L01, L02, S01–S11).
 * They render an honest, noindex preview: approved breadcrumb, H1 and introduction only.
 * Nothing is invented and nothing is silently redirected to the homepage.
 */
final class PagePreviewController extends Controller
{
    public function page(Request $request, ContentRepository $content, string $code): View
    {
        $page = $content->page($code);
        abort_if($page === [], 404);

        $crumbs = array_map(self::crumbLabel(...), $page['breadcrumb'] ?? []);

        return $this->render($page, $page['content'] ?? [], $this->navKey($code), [
            ['label' => $crumbs[0] ?? ui('nav.home'), 'href' => SiteUrl::home()],
            ['label' => $crumbs[1] ?? (string) ($page['h1'] ?? ''), 'href' => null],
        ]);
    }

    public function service(Request $request, ContentRepository $content, string $service): View
    {
        $code = array_search($service, config('summit.services'), true);
        $record = $code ? $content->service($code) : null;
        abort_if($record === null, 404);
        $crumbs = array_map(self::crumbLabel(...), $record['breadcrumb'] ?? []);

        return $this->render([
            'h1' => $record['h1'] ?? $record['title'] ?? '',
            'seo_title' => $record['seo_title'] ?? null,
            'meta_description' => $record['meta_description'] ?? null,
        ], ['intro' => $record['intro'] ?? null], 'practice', [
            ['label' => $crumbs[0] ?? ui('nav.home'), 'href' => SiteUrl::home()],
            ['label' => $crumbs[1] ?? ui('nav.practice_areas'), 'href' => SiteUrl::page('P02')],
            ['label' => $crumbs[2] ?? $record['title'] ?? '', 'href' => null],
        ]);
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $content
     * @param  list<array{label: string, href: ?string}>  $crumbs
     */
    private function render(array $page, array $content, ?string $nav, array $crumbs): View
    {
        return view('pages.preview', [
            'seo' => Seo::make($page['seo_title'] ?? ($page['h1'] ?? ui('brand.name')), $page['meta_description'] ?? null, index: false),
            'pageKey' => 'preview',
            'navCurrent' => $nav,
            'h1' => $page['h1'] ?? '',
            'intro' => $this->intro($content),
            'crumbs' => $crumbs,
        ]);
    }

    /** Breadcrumbs are stored either as labels or as {label, target} records. */
    private static function crumbLabel(mixed $crumb): string
    {
        return is_array($crumb) ? (string) ($crumb['label'] ?? '') : (string) $crumb;
    }

    /**
     * The approved introduction of the page, whatever shape its mapper used:
     * a string, a list of paragraphs, or {lead, paragraphs[]} (under hero/intro).
     *
     * @return list<string>
     */
    private function intro(array $content): array
    {
        foreach (['hero', 'intro', 'lead', 'paragraphs', 'story'] as $key) {
            $value = $content[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $out = [$value];
                if ($key === 'lead' && is_string($content['intro'] ?? null)) {
                    $out[] = $content['intro'];
                } elseif ($key === 'lead' && is_array($content['paragraphs'] ?? null)) {
                    $out[] = (string) ($content['paragraphs'][0] ?? '');
                }

                return array_values(array_filter($out));
            }
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $strings = array_values(array_filter($value, fn ($v) => is_string($v) && $v !== ''));
                    if ($strings !== []) {
                        return array_slice($strings, 0, 2);
                    }

                    continue;
                }
                $out = array_values(array_filter([
                    is_string($value['lead'] ?? null) ? $value['lead'] : null,
                    is_string($value['text'] ?? null) ? $value['text'] : null,
                    is_string($value['paragraphs'][0] ?? null) ? $value['paragraphs'][0] : null,
                ]));
                if ($out !== []) {
                    return array_slice($out, 0, 2);
                }
            }
        }

        return [];
    }

    private function navKey(string $code): ?string
    {
        return ['P02' => 'practice', 'P03' => 'resources', 'P04' => 'cases', 'P05' => 'faq', 'P06' => 'about', 'P07' => 'contact'][$code] ?? null;
    }
}
