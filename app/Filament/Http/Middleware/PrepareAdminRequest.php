<?php

namespace App\Filament\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel only (persistent middleware, so Livewire updates get it too):
 *
 *  - the admin theme comes from its own Vite build (public/build-admin, see ->viteTheme()) and, in
 *    development, from its own dev server (public/hot-admin, `npm run dev:admin`), never from the
 *    Design 1 or Design 2 dev servers;
 *  - the floating "Design 1 | Design 2" switcher (App\Http\Middleware\DesignSwitcher) is a public-site
 *    demo tool: the request is marked with its documented QA opt-out header so it is never injected;
 *  - responses hold personal data: never stored by the browser or a proxy, never indexed.
 */
final class PrepareAdminRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useHotFile(public_path('hot-admin'));

        $request->headers->set((string) config('design.qa_header', 'X-Summit-QA'), '1');

        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
