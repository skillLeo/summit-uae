<?php

namespace App\Http\Middleware;

use App\Models\Content\CalculatorRuleSet;
use App\Models\Content\Page;
use App\Models\Content\Service;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * "Edit this page" (docs/briefs/design-2.1-and-admin-cms.md §8.5): a signed-in admin sees a small bar on the public site
 * that opens the page's record in the admin. Visitors never get it: for anyone who is not an admin the response is
 * untouched, byte for byte. A page with the bar is never cached (Cache-Control: no-store, private), so no shared cache
 * can hand it to a visitor. The bar's labels are admin interface text (lang/{locale}/cms.php) in the admin's language.
 */
final class EditThisPageBar
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if (! $user instanceof User || $user->is_admin !== true || $request->query('cms_preview') !== null) {
            return $response;
        }
        if ($response->getStatusCode() !== 200 || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }
        $html = $response->getContent();
        if (! is_string($html) || ! preg_match('/<body\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return $response;
        }
        $bar = rescue(fn () => $this->bar($request, $user), report: false);
        $response->headers->set('Cache-Control', 'no-store, private');
        if (! is_string($bar)) {
            return $response;
        }
        $at = $m[0][1] + strlen($m[0][0]);
        $response->setContent(substr($html, 0, $at).$bar.substr($html, $at));

        return $response;
    }

    /** The admin record that holds the content of the current page, or null. */
    public static function record(Request $request): Model|string|null
    {
        $route = $request->route();
        $name = $route?->getName();
        if ($name === null) {
            return null;
        }
        if ($name === 'service') {
            $code = array_search((string) $route->parameter('service'), (array) config('summit.services'), true);

            return is_string($code) ? Service::query()->find($code) : null;
        }
        if ($name === 'calculators') {
            return CalculatorRuleSet::class;
        }
        foreach ((array) config('summit.pages') as $code => $page) {
            if (($page['route'] ?? null) === $name) {
                return Page::query()->find($code);
            }
        }

        return null;
    }

    private function bar(Request $request, User $user): string
    {
        $locale = in_array($user->admin_locale, User::ADMIN_LOCALES, true) ? $user->admin_locale : 'en';
        $panel = Filament::getPanel('admin');
        $record = self::record($request);
        $edit = null;
        try {
            if ($record instanceof Model && ($resource = $panel->getModelResource($record)) !== null) {
                $edit = $resource::getUrl('edit', ['record' => $record], panel: 'admin');
            } elseif (is_string($record) && ($resource = $panel->getModelResource($record)) !== null) {
                $edit = $resource::getUrl('index', panel: 'admin');
            }
        } catch (Throwable) {
            $edit = null;
        }
        $t = fn (string $key) => e((string) __('cms.editbar.'.$key, [], $locale));
        $dir = $locale === 'ar' ? 'rtl' : 'ltr';
        $nonce = e((string) Vite::cspNonce());
        $links = $edit !== null ? '<a href="'.e($edit).'">'.$t('edit').'</a>' : '';
        $links .= '<a href="'.e($panel->getUrl()).'">'.$t('admin').'</a>';

        return '<style nonce="'.$nonce.'">'
            .'.cms-editbar{position:fixed;inset-block-end:16px;inset-inline-start:16px;z-index:2147483000;display:flex;gap:4px;align-items:center;'
            .'padding:4px;border-radius:10px;background:#18181b;color:#fff;font:500 14px/1.2 system-ui,sans-serif}'
            .'.cms-editbar span{padding-inline:8px;color:#d4d4d8}'
            .'.cms-editbar a{display:inline-flex;align-items:center;min-height:44px;padding-inline:12px;border-radius:6px;color:#fff;text-decoration:none}'
            .'.cms-editbar a:hover{background:#3f3f46}.cms-editbar a:focus-visible{outline:2px solid #fff;outline-offset:2px}'
            .'@media (width < 1024px){.cms-editbar{inset-block-end:calc(88px + env(safe-area-inset-bottom,0px))}}'
            .'@media print{.cms-editbar{display:none}}'
            .'</style>'
            .'<div class="cms-editbar" role="region" aria-label="'.$t('label').'" lang="'.e($locale).'" dir="'.$dir.'" data-cms-editbar>'
            .'<span>'.$t('label').'</span>'.$links.'</div>';
    }
}
