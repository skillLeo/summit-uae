<?php

namespace App\Http\Middleware;

use App\Content\Cms\ContentCache;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The CMS redirects (docs/briefs/design-2.1-and-admin-cms.md §8.3, §8.11; App\Models\Redirect): a GET or HEAD request
 * for an enabled redirect's from_path is answered with its 301 or 302 before routing, keeping the query string. The map
 * is cached under the content cache version; hits are counted without touching the cache. With no redirects (today)
 * every response is untouched.
 */
final class ServeRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() || $request->isMethod('OPTIONS')) {
            return $next($request);
        }
        $map = self::map();
        if ($map === []) {
            return $next($request);
        }
        $path = '/'.ltrim($request->getPathInfo(), '/');
        $hit = $map[$path] ?? $map[rtrim($path, '/')] ?? $map[rtrim($path, '/').'/'] ?? null;
        if ($hit === null) {
            return $next($request);
        }
        rescue(fn () => DB::table('redirects')->where('id', $hit['id'])->update([
            'hits' => DB::raw('hits + 1'),
            'last_hit_at' => now(),
        ]), report: false);
        $query = $request->getQueryString();
        $to = $hit['to'];
        if (! preg_match('#^https?://#i', $to)) {
            $to = rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim($to, '/');
        }
        $response = new RedirectResponse($to.($query !== null && $query !== '' && ! str_contains($to, '?') ? '?'.$query : ''), $hit['code']);
        $response->headers->set('Cache-Control', $hit['code'] === 301 ? 'public, max-age=3600' : 'no-store, private');

        return $response;
    }

    /** @return array<string, array{id: int, to: string, code: int}> from_path => target */
    public static function map(): array
    {
        try {
            return Cache::rememberForever(ContentCache::key('redirects'), function () {
                $map = [];
                foreach (DB::table('redirects')->where('enabled', true)->get(['id', 'from_path', 'to_path', 'code']) as $row) {
                    $map['/'.ltrim((string) $row->from_path, '/')] = [
                        'id' => (int) $row->id,
                        'to' => (string) $row->to_path,
                        'code' => in_array((int) $row->code, [301, 302], true) ? (int) $row->code : 301,
                    ];
                }

                return $map;
            });
        } catch (Throwable) {
            return [];
        }
    }
}
